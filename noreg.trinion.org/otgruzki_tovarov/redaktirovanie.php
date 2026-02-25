<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../config/database_config.php';
require_once '../queries/id_index_helper.php';
require '../queries/database_queries.php';
require '../queries/otgruzki_tovarov_queries.php';
require '../queries/zakaz_pokupatelya_query.php';
require '../queries/zakaz_query.php';

if (!function_exists('getTovaryZakazasostatkom')) {
    require_once __DIR__ . '/../queries/id_index_helper.php';
}


$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$id = $is_edit ? intval($_GET['id']) : null;


$zakaz_id = isset($_GET['zakaz_id']) ? intval($_GET['zakaz_id']) : (isset($_POST['zakaz_id']) ? intval($_POST['zakaz_id']) : null);


$ot_postavshchika = isset($_GET['ot_postavshchika']) ? (bool)intval($_GET['ot_postavshchika']) : (isset($_POST['ot_postavshchika']) ? (bool)intval($_POST['ot_postavshchika']) : false);
$pokupatelya = isset($_GET['pokupatelya']) ? (bool)intval($_GET['pokupatelya']) : (isset($_POST['pokupatelya']) ? (bool)intval($_POST['pokupatelya']) : false);


$page_title = $is_edit ? 'Редактировать отгрузки товаров' : 'Новый отгрузки товаров';
$data_vypuska = date('Y-m-d');
$naimenovanie_postavschika = '';
$id_postavschika = '';
$naimenovanie_organizacii = '';
$id_organizacii = '';
$naimenovanie_otvetstvennogo = '';
$id_otvetstvennogo = '';
$naimenovanie_sklada = '';
$id_sklada = '';
$utverzhden = '';
$document = null;
$line_items = [];


if ($zakaz_id && !$is_edit) {
    
    if ($ot_postavshchika) {
        
        $zakaz = fetchZakazHeader($mysqli, $zakaz_id);
        if ($zakaz) {
            $data_vypuska = date('Y-m-d');
            $naimenovanie_postavschika = $zakaz['naimenovanie_postavschika'] ?? ''; 
            $id_postavschika = $zakaz['id_kontragenti_pokupatel'] ?? '';
            $naimenovanie_organizacii = $zakaz['naimenovanie_organizacii'] ?? ''; 
            $id_organizacii = $zakaz['id_kontragenti_postavshchik'] ?? '';
            $naimenovanie_otvetstvennogo = $zakaz['naimenovanie_otvetstvennogo'] ?? '';
            $id_otvetstvennogo = $zakaz['id_otvetstvennyj'] ?? '';
            $zakaz_line_items = getZakazStrokiItems($mysqli, $zakaz['id_index']);
            $line_items = getTovaryZakazasostatkom($mysqli, $zakaz, $zakaz_line_items, 'shipment');
        }
    } else {
        
        $zakaz = getZakazHeader($mysqli, $zakaz_id);
        if ($zakaz) {
            $data_vypuska = date('Y-m-d');
            $naimenovanie_postavschika = $zakaz['naimenovanie_postavschika'] ?? '';
            $id_postavschika = $zakaz['id_kontragenti_pokupatel'] ?? '';
            $naimenovanie_organizacii = $zakaz['naimenovanie_organizacii'] ?? '';
            $id_organizacii = $zakaz['id_kontragenti_postavshik'] ?? '';
            $naimenovanie_otvetstvennogo = $zakaz['naimenovanie_otvetstvennogo'] ?? '';
            $id_otvetstvennogo = $zakaz['id_otvetstvennyj'] ?? '';
            $zakaz_line_items = getZakazStrokiItems($mysqli, $zakaz['id_index']);
            $line_items = getTovaryZakazasostatkom($mysqli, $zakaz, $zakaz_line_items, 'shipment');
        }
    }
}

if ($is_edit) {
    $document = fetchOtgruzkiHeader($mysqli, $id);
    
    if (!$document) {
        die("Документ не найден.");
    }
    
    $line_items = fetchOtgruzkiLineItems($mysqli, $document['id_index']);
    $data_vypuska = $document['data_dokumenta'];
    $naimenovanie_postavschika = $document['naimenovanie_postavschika'] ?? '';
    $id_postavschika = $document['id_kontragenti'] ?? '';
    $naimenovanie_organizacii = $document['naimenovanie_organizacii'] ?? '';
    $id_organizacii = $document['id_kontragenti_postavshik'] ?? '';
    $naimenovanie_otvetstvennogo = $document['naimenovanie_otvetstvennogo'] ?? '';
    $id_otvetstvennogo = $document['id_otvetstvennyj'] ?? '';
    $naimenovanie_sklada = $document['naimenovanie_sklada'] ?? '';
    $id_sklada = $document['id_sklada'] ?? '';
    $zakaz_id = $document['id_zakazy_pokupatelei'] ?? '';
    $utverzhden = $document['utverzhden'] ?? 0;
}

$stavki_nds = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $stavki_nds = $nds_result->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validation
    $validations = array(
        'otgruzki_date' => 'Требуется дата',
        'naimenovanie_organizacii' => 'Требуется указать поставщик',
        'naimenovanie_postavschika' => 'Требуется указать покупатель',
        'naimenovanie_otvetstvennogo' => 'Требуется указать ответственного'
    );
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
    if (!$error && empty($_POST['id_organizacii'])) {
        $error = 'Пожалуйста, выберите поставщик из списка';
    }
    
    if (!$error && empty($_POST['id_otvetstvennogo'])) {
        $error = 'Пожалуйста, выберите ответственного из списка';
    }
    
    if (!$error && (!isset($_POST['tovary']) || empty($_POST['tovary']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error && !empty($_POST['tovary'])) {
        foreach ($_POST['tovary'] as $index => $tovar) {
            if (!empty($tovar['naimenovanie_tovara']) || !empty($tovar['kolichestvo']) || !empty($tovar['cena']) || !empty($tovar['nds_id'])) {
                if (empty($tovar['naimenovanie_tovara'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется указать товар';
                    break;
                }
                if (empty($tovar['kolichestvo'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется указать количество';
                    break;
                }
                if (empty($tovar['cena'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется указать цену';
                    break;
                }
                if (empty($tovar['nds_id'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется выбрать НДС';
                    break;
                }
            }
        }
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                $result = updateOtgruzkiDocument($mysqli, $id, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $id);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                $zakaz_id_for_create = $zakaz_id ?? ($_POST['zakaz_id'] ?? null);
                $result = createOtgruzkiDocument($mysqli, $_POST, $zakaz_id_for_create);
                
                if ($result['success']) {
                    $redirect_type = (!empty($_POST['ot_postavshchika']) && $_POST['ot_postavshchika'] === '1') ? 'postavschik' : 'pokupatel';
                    header("Location: spisok.php?type=" . $redirect_type);
                    exit;
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="page-body">
<div class="container-fluid mt-5">
    <h2 class="card-title" style="font-size: 2rem; margin-top: 20px; margin-bottom: 30px;">
        <?= htmlspecialchars($page_title) ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">
                <input type="hidden" name="zakaz_id" value="<?= htmlspecialchars($zakaz_id ?? '') ?>">
                <input type="hidden" name="ot_postavshchika" value="<?= $ot_postavshchika ? '1' : '0' ?>">
                <input type="hidden" name="pokupatelya" value="<?= $pokupatelya ? '1' : '0' ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="schet_date">Дата</label>
                        <input class="form-control" type="date" id="otgruzki_date" name="otgruzki_date"
                        value="<?= htmlspecialchars($_POST['otgruzki_date'] ?? $data_vypuska) ?>">
                    </div>
                     <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_sklada">Склад</label>
                        <input type="text" class="form-control" id="id_sklada" name="naimenovanie_sklada" placeholder="- Выберите склад -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_sklada'] ?? $naimenovanie_sklada) ?>">
                        <input type="hidden" name="id_sklada" class="sklad-id" value="<?= htmlspecialchars($_POST['id_sklada'] ?? $id_sklada) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_postavschika">Покупатель</label>
                        <input class="form-control" type="text" id="id_postavschika" name="naimenovanie_postavschika" placeholder="- Выберите поставщика -" autocomplete="off" 
                        value="<?= htmlspecialchars($_POST['naimenovanie_postavschika'] ?? $naimenovanie_postavschika) ?>">
                        <input type="hidden" name="id_postavschika" class="id-postavschika" value="<?= htmlspecialchars($_POST['id_postavschika'] ?? $id_postavschika) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_organizacii">Поставщик</label>
                        <input class="form-control" type="text" id="id_organizacii" name="naimenovanie_organizacii" placeholder="- Выберите организацию -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_organizacii'] ?? $naimenovanie_organizacii) ?>">
                        <input type="hidden" name="id_organizacii" class="id-organizacii" value="<?= htmlspecialchars($_POST['id_organizacii'] ?? $id_organizacii) ?>">
                    </div>
                </div>



                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_otvetstvennogo">Ответственный</label>
                        <input type="text" class="form-control" id="id_otvetstvennogo" name="naimenovanie_otvetstvennogo" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_otvetstvennogo'] ?? $naimenovanie_otvetstvennogo) ?>">
                        <input type="hidden" name="id_otvetstvennogo" class="id-otvetstvennogo" value="<?= htmlspecialchars($_POST['id_otvetstvennogo'] ?? $id_otvetstvennogo) ?>">
                    </div>
                </div>

                <h2 style="margin-top: 30px;"></h2>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="tablitsaTovarov">
                    <thead>
                        <tr>
                            <th class="col-num">№</th>
                            <th class="col-tovar">Товар</th>
                            <th class="col-seria">Серия</th>
                            <th class="col-edinitsa">Ед</th>
                            <th class="col-kolichestvo">Кол-во</th>
                            <th class="col-cena">Цена</th>
                            <th class="col-nds">НДС</th>
                            <th class="col-summa-stavka">Сумма НДС</th>
                            <th class="col-summa">Сумма</th>
                            <?php if (!$ot_postavshchika): ?>
                            <th class="col-sklad">Склад</th>
                            <?php endif; ?>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="tovaryBody">
                        <?php if (!empty($_POST['tovary'])): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($_POST['tovary'] as $submitted_item): ?>
                        <tr class="tovar-row">
                            <td class="col-num"><?= $row_index + 1 ?></td>
                            <td class="col-tovar">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($submitted_item['naimenovanie_tovara'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id_tovara" value="<?= htmlspecialchars($submitted_item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][seria_name]" placeholder="Введите серию..." autocomplete="off" value="<?= htmlspecialchars($submitted_item['seria_name'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][seria_id]" class="seria-id" value="<?= htmlspecialchars($submitted_item['seria_id'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($submitted_item['naimenovanie_edinitsii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($submitted_item['id_edinitsii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-kolichestvo"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['kolichestvo'] ?? '') ?>"></td>
                            <td class="col-cena"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['cena'] ?? '') ?>"></td>
                            <td class="col-nds">
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($submitted_item['nds_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['summa_nds'] ?? '') ?>"></td>
                            <td class="col-summa"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['summa'] ?? '') ?>"></td>
                            <?php if (!$ot_postavshchika): ?>
                            <td class="col-sklad">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_sklada]" placeholder="Введите склад" autocomplete="off" value="<?= htmlspecialchars($submitted_item['naimenovanie_sklada'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_sklada]" class="sklad-id" value="<?= htmlspecialchars($submitted_item['id_sklada'] ?? '') ?>">
                                </div>
                            </td>
                            <?php endif; ?>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php elseif (!empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="tovar-row">
                            <td class="col-num"><?= $row_index + 1 ?></td>
                            <td class="col-tovar">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id_tovara" value="<?= htmlspecialchars($item['id_tovary_i_uslugi'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][seria_name]" placeholder="Введите серию..." autocomplete="off" value="<?= htmlspecialchars($item['seria_name'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][seria_id]" class="seria-id" value="<?= htmlspecialchars($item['id_serii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($item['id_edinicy_izmereniya'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-kolichestvo">
                                <?php 
                                    $field_qty_value = isset($item['kolichestvo_ostatka']) && $item['kolichestvo_ostatka'] > 0 ? $item['kolichestvo_ostatka'] : (isset($item['kolichestvo']) ? $item['kolichestvo'] : '0');
                                ?>
                                <input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($field_qty_value) ?>">
                            </td>
                            <td class="col-cena"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['ed_cena'] ?? '') ?>"></td>
                            <td class="col-nds">
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($item['id_stavka_nds'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['summa_nds'] ?? '') ?>"></td>
                            <td class="col-summa"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars(isset($item['summa_ostatka']) && $item['summa_ostatka'] > 0 ? $item['summa_ostatka'] : (isset($item['summa']) ? $item['summa'] : '0')) ?>"></td>
                            <?php if (!$ot_postavshchika): ?>
                            <td class="col-sklad">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_sklada]" placeholder="Введите склад" autocomplete="off" value="<?= htmlspecialchars($item['naimenovanie_sklada'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_sklada]" class="sklad-id" value="<?= htmlspecialchars($item['id_sklada'] ?? '') ?>">
                                </div>
                            </td>
                            <?php endif; ?>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="tovar-row">
                            <td class="col-num">1</td>
                            <td class="col-tovar">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_tovara]" class="id_tovara">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][seria_name]" placeholder="Введите серию..." autocomplete="off">
                                    <input type="hidden" name="tovary[0][seria_id]" class="seria-id">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_edinitsii]" class="id-edinitsii">
                                </div>
                            </td>
                            <td class="col-kolichestvo"><input class="form-control" type="text" name="tovary[0][kolichestvo]" placeholder="0" autocomplete="off"></td>
                            <td class="col-cena"><input class="form-control" type="text" name="tovary[0][cena]" placeholder="0" autocomplete="off"></td>
                            <td class="col-nds">
                                <select class="form-control" name="tovary[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[0][summa_stavka]" placeholder="0" autocomplete="off"></td>
                            <td class="col-summa"><input class="form-control" type="text" name="tovary[0][summa]" placeholder="0" autocomplete="off"></td>
                            <?php if (!$ot_postavshchika): ?>
                            <td class="col-sklad">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_sklada]" placeholder="Введите склад" autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_sklada]" class="sklad-id">
                                </div>
                            </td>
                            <?php endif; ?>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                         <div class="btn-group" role="group" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            <?= $is_edit ? 'Сохранить' : 'Сохранить' ?>
                        </button>
                        <a href="spisok.php" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
        <script src="https://cdn.jsdelivm.net/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        
        <script>            
            const formConfig = {
                columns: [
                    { key: 'tovar', label: 'Товар', type: 'autocomplete' },
                    { key: 'seria', label: 'Серия', type: 'autocomplete' },
                    { key: 'edinitsa', label: 'Ед', type: 'autocomplete' },
                    { key: 'kolichestvo', label: 'Кол-во', type: 'text' },
                    { key: 'cena', label: 'Цена', type: 'text' },
                    { key: 'nds_id', label: 'НДС', type: 'select' },
                    { key: 'sklad', label: 'Склад', type: 'autocomplete' }
                ]
            };
                        let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($stavki_nds as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
            
            
            let unitsData = [];
            <?php
            $units_query = "SELECT id, naimenovanie FROM edinicy_izmereniya ORDER BY naimenovanie ASC";
            $units_result = $mysqli->query($units_query);
            if ($units_result) {
                $units_data = $units_result->fetch_all(MYSQLI_ASSOC);
            ?>
                unitsData = <?= json_encode($units_data) ?>;
            <?php } ?>
        </script>
        <script src="../js/schet.js"></script>
        <script src="../js/add_product.js"></script>
</div>
<?php include '../footer.php'; ?>