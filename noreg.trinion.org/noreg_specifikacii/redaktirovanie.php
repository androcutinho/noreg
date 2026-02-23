<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../config/database_config.php';
require '../queries/database_queries.php';
require '../queries/zosdat_edit_specifikatsiu.php';
require '../queries/zakaz_pokupatelya_query.php';
require '../queries/zakaz_query.php';


$spec_id = $_GET['id'] ?? null;
$is_edit = !empty($spec_id);


$zakaz_id = isset($_GET['zakaz_id']) ? intval($_GET['zakaz_id']) : (isset($_POST['zakaz_id']) ? intval($_POST['zakaz_id']) : null);
$ot_postavshchika = isset($_GET['ot_postavshchika']) ? (bool)intval($_GET['ot_postavshchika']) : (isset($_POST['ot_postavshchika']) ? (bool)intval($_POST['ot_postavshchika']) : false);
$pokupatelya = isset($_GET['pokupatelya']) ? (bool)intval($_GET['pokupatelya']) : (isset($_POST['pokupatelya']) ? (bool)intval($_POST['pokupatelya']) : false);


if ($is_edit && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delete_result = deleteSpecification($mysqli, $spec_id);
    if ($delete_result['success']) {
        header('Location: spisok.php');
        exit;
    } else {
        $error = $delete_result['error'];
    }
}

if ($is_edit) {
    $page_title = 'Редактирование спецификации к заказу';
} else {
    $page_title = 'Новая спецификация к заказу';
}

$data_dogovora = date('Y-m-d');
$gorod = '';
$nomer_dogovora = '';
$naimenovanie_organizacii = '';
$id_organizacii = '';
$naimenovanie_kontragenta = '';
$id_kontragenta = '';
$planiruemaya_data_postavki = date('Y-m-d');
$usloviya_otgruzki = '';
$usloviya_oplaty = '';
$inye_usloviya = '';
$naimmenovanie_sotrudnika = '';
$id_sotrudnika = '';
$podpisant_postavshchika_dolzhnost = '';
$podpisant_postavshchika_fio = '';
$line_items = [];
$nomer_zakaza = null;


if ($zakaz_id && !$is_edit) {
    $info_zakaza = loadOrderDataForSpecification($mysqli, $zakaz_id, $ot_postavshchika);
    $nomer_zakaza = $info_zakaza['nomer_zakaza'];
    $data_dogovora = $info_zakaza['data_dogovora'];
    $nomer_dogovora = $info_zakaza['nomer_dogovora'];
    $id_organizacii = $info_zakaza['id_organizacii'];
    $naimenovanie_organizacii = $info_zakaza['naimenovanie_organizacii'];
    $id_kontragenta = $info_zakaza['id_kontragenta'];
    $naimenovanie_kontragenta = $info_zakaza['naimenovanie_kontragenta'];
    $id_sotrudnika = $info_zakaza['id_sotrudnika'];
    $naimmenovanie_sotrudnika = $info_zakaza['naimmenovanie_sotrudnika'];
    $line_items = $info_zakaza['line_items'];
}


if ($is_edit) {
    $spec_result = getSpecificationById($mysqli, $spec_id);
    if (!$spec_result['success']) {
        header('Location: spisok.php');
        exit;
    }
    
    $spec = $spec_result['data'];
    $data_dogovora = $spec['data_dogovora'];
    $gorod = $spec['gorod'];
    $nomer_dogovora = $spec['nomer_dogovora'];
    $id_organizacii = $spec['id_kontragenti_postavshik'];
    $id_kontragenta = $spec['id_kontragenti_pokupatel'];
    $usloviya_otgruzki = $spec['usloviya_otgruzki'];
    $usloviya_oplaty = $spec['usloviya_oplaty'];
    $inye_usloviya = $spec['inye_usloviya'];
    $id_sotrudnika = $spec['id_sotrudniki'];
    $podpisant_postavshchika_dolzhnost = $spec['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $spec['podpisant_postavshchika_fio'];
    $utverzhden = $spec['utverzhden'] ?? 0;
    
    $org_query = $mysqli->query("SELECT naimenovanie FROM kontragenti WHERE id = " . intval($id_organizacii));
    if ($org = $org_query->fetch_assoc()) {
        $naimenovanie_organizacii = $org['naimenovanie'];
    }
    
    $vendor_query = $mysqli->query("SELECT naimenovanie FROM kontragenti WHERE id = " . intval($id_kontragenta));
    if ($vendor = $vendor_query->fetch_assoc()) {
        $naimenovanie_kontragenta = $vendor['naimenovanie'];
    }
    
    $sotrudnik_query = $mysqli->query("SELECT CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) as fio FROM sotrudniki WHERE id = " . intval($id_sotrudnika));
    if ($sotrudnik = $sotrudnik_query->fetch_assoc()) {
        $naimmenovanie_sotrudnika = trim($sotrudnik['fio']);
    }
    
    
    $line_items_result = getSpecificationLineItems($mysqli, $spec['id_index']);
    if ($line_items_result['success']) {
        $line_items = $line_items_result['data'];
    }
}

$stavki_nds = getAllNdsRates($mysqli);
$units = getAllUnits($mysqli);

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $validations = [
        'data_dogovora' => 'Требуется дата договора',
        'gorod' => 'Требуется указать город',
        'nomer_dogovora' => 'Требуется указать номер договора',
        'id_organizacii' => 'Требуется выбрать организацию',
        'id_kontragenta' => 'Требуется выбрать поставщика',
        'id_sotrudnika' => 'Требуется выбрать ответственного',
    ];
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
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
    
    
    if (!$error && isset($_POST['tovary'])) {
        foreach ($_POST['tovary'] as $index => $tovar) {
            if (empty($tovar['planiruemaya_data_postavki'])) {
                $error = 'Требуется указать плановую дату поставки для всех товаров';
                break;
            }
        }
    }
    
    if (!$error) {
        if ($is_edit) {
            
            $result = updateSpecification($mysqli, $spec_id, $_POST);
            
            if (!$result['success']) {
                $error = $result['error'];
            } else {
                
                $line_result = updateSpecificationLineItems($mysqli, $spec_id, $_POST['tovary']);
                
                if (!$line_result['success']) {
                    $error = $line_result['error'];
                } else {
                    
                    header('Location: prosmotr.php?id=' . $spec_id);
                    exit;
                }
            }
        } else {
            
            $result = createSpecification($mysqli, $_POST, $nomer_zakaza, $zakaz_id, $ot_postavshchika, $pokupatelya);
            
            if (!$result['success']) {
                $error = $result['error'];
            } else {
                $doc_id = $result['id'];
                
               
                $get_index_query = "SELECT id_index FROM noreg_specifikacii_k_zakazam  WHERE id = ?";
                $get_stmt = $mysqli->prepare($get_index_query);
                $get_stmt->bind_param('i', $doc_id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                $doc_data = $get_result->fetch_assoc();
                $get_stmt->close();
                $id_index = $doc_data['id_index'];
                
                $line_result = createSpecificationLineItems($mysqli, $doc_id, $_POST['tovary'], $id_index);
                
                if (!$line_result['success']) {
                    $error = $line_result['error'];
                } else {
                    
                    $redirect_url = $ot_postavshchika ? 'spisok.php?type=postavschik' : 'spisok.php?type=pokupatel';
                    header('Location: ' . $redirect_url);
                    exit;
                }
            }
        }
    }
}

include '../header.php';
?>

<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs4.min.css" rel="stylesheet">

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
                        <label class="form-label" for="data_dogovora">Дата договора</label>
                        <input class="form-control" type="date" id="data_dogovora" name="data_dogovora"
                        value="<?= htmlspecialchars($_POST['data_dogovora'] ?? $data_dogovora) ?>">
                    </div>


                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="gorod">Город</label>
                        <input class="form-control" type="text" id="gorod" name="gorod" placeholder="Введите город..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['gorod'] ?? $gorod) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="nomer_dogovora">№ договора</label>
                        <input class="form-control" type="text" id="nomer_dogovora" name="nomer_dogovora" placeholder="Введите номер договора..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['nomer_dogovora'] ?? $nomer_dogovora) ?>">
                    </div>
                </div>

                <div class="row">
                     <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_kontragenta">Поставщик</label>
                        <input class="form-control" type="text" id="id_kontragenta" name="naimenovanie_kontragenta" placeholder="- Выберите поставщика -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_kontragenta'] ?? $naimenovanie_kontragenta) ?>">
                        <input type="hidden" name="id_kontragenta" class="id-kontragenta" value="<?= htmlspecialchars($_POST['id_kontragenta'] ?? $id_kontragenta) ?>">
                    </div>
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_organizacii">Организация</label>
                        <input class="form-control" type="text" id="id_organizacii" name="naimenovanie_organizacii" placeholder="- Выберите организацию -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_organizacii'] ?? $naimenovanie_organizacii) ?>">
                        <input type="hidden" name="id_organizacii" class="id-organizacii" value="<?= htmlspecialchars($_POST['id_organizacii'] ?? $id_organizacii) ?>">
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
                            <th class="col-edinitsa">Ед</th>
                            <th class="col-kolichestvo">Кол-во</th>
                            <th class="col-cena">Цена</th>
                            <th class="col-nds">НДС</th>
                            <th class="col-summa-stavka">Сумма НДС</th>
                            <th class="col-summa">Сумма</th>
                            <th class="col-delivery-date">Планируемая дата поставки</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="tovaryBody">
                        <?php if (!empty($_POST['tovary'])): ?>
                            <?php foreach ($_POST['tovary'] as $index => $submitted_item): ?>
                                <tr class="tovar-row">
                                    <td class="col-num"><?= $index + 1 ?></td>
                                    <td class="col-tovar">
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="tovary[<?= $index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off" value="<?= htmlspecialchars($submitted_item['naimenovanie_tovara'] ?? '') ?>">
                                            <input type="hidden" name="tovary[<?= $index ?>][id_tovara]" class="id_tovara" value="<?= htmlspecialchars($submitted_item['id_tovara'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="col-edinitsa">
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="tovary[<?= $index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($submitted_item['naimenovanie_edinitsii'] ?? '') ?>">
                                            <input type="hidden" name="tovary[<?= $index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($submitted_item['id_edinitsii'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="col-kolichestvo"><input class="form-control" type="text" name="tovary[<?= $index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['kolichestvo'] ?? '') ?>"></td>
                                    <td class="col-cena"><input class="form-control" type="text" name="tovary[<?= $index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['cena'] ?? '') ?>"></td>
                                    <td class="col-nds">
                                        <select class="form-control" name="tovary[<?= $index ?>][nds_id]">
                                            <option value="">--</option>
                                            <?php foreach ($stavki_nds as $nds): ?>
                                                <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($submitted_item['nds_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[<?= $index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['summa_stavka'] ?? '') ?>"></td>
                                    <td class="col-summa"><input class="form-control" type="text" name="tovary[<?= $index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['summa'] ?? '') ?>"></td>
                                    <td class="col-delivery-date"><input class="form-control" type="date" name="tovary[<?= $index ?>][planiruemaya_data_postavki]" value="<?= htmlspecialchars($submitted_item['planiruemaya_data_postavki'] ?? '') ?>" required></td>
                                    <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (!empty($line_items)): ?>
                            <?php foreach ($line_items as $index => $item): ?>
                                <tr class="tovar-row">
                                    <td class="col-num"><?= $index + 1 ?></td>
                                    <td class="col-tovar">
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="tovary[<?= $index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off" value="<?= htmlspecialchars($item['naimenovanie_tovara']) ?>">
                                            <input type="hidden" name="tovary[<?= $index ?>][id_tovara]" class="id_tovara" value="<?= $item['id_tovary_i_uslugi'] ?>">
                                        </div>
                                    </td>
                                    <td class="col-edinitsa">
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="tovary[<?= $index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($item['naimenovanie_edinitsii']) ?>">
                                            <input type="hidden" name="tovary[<?= $index ?>][id_edinitsii]" class="id-edinitsii" value="<?= $item['id_edinicy_izmereniya'] ?>">
                                        </div>
                                    </td>
                                    <td class="col-kolichestvo"><input class="form-control" type="text" name="tovary[<?= $index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= $item['kolichestvo'] ?>"></td>
                                    <td class="col-cena"><input class="form-control" type="text" name="tovary[<?= $index ?>][cena]" placeholder="0" autocomplete="off" value="<?= $item['cena'] ?>"></td>
                                    <td class="col-nds">
                                        <select class="form-control" name="tovary[<?= $index ?>][nds_id]">
                                            <option value="">--</option>
                                            <?php foreach ($stavki_nds as $nds): ?>
                                                <option value="<?= $nds['id'] ?>" <?= $item['id_stavka_nds'] == $nds['id'] ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[<?= $index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= $item['summa_nds'] ?>"></td>
                                    <td class="col-summa"><input class="form-control" type="text" name="tovary[<?= $index ?>][summa]" placeholder="0" autocomplete="off" value="<?= $item['summa'] ?>"></td>
                                    <td class="col-delivery-date"><input class="form-control" type="date" name="tovary[<?= $index ?>][planiruemaya_data_postavki]" value="<?= $item['planiruemaya_data_postavki'] ?>" required></td>
                                    <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                                </tr>
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
                                <td class="col-delivery-date"><input class="form-control" type="date" name="tovary[0][planiruemaya_data_postavki]" required></td>
                                <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                                </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <h2 style="margin-top: 30px;"></h2>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="usloviya_otgruzki">Условия отгрузки</label>
                        <div id="usloviya_otgruzki"></div>
                        <input type="hidden" name="usloviya_otgruzki" id="usloviya_otgruzki_hidden">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="usloviya_oplaty">Условия оплаты</label>
                        <div id="usloviya_oplaty"></div>
                        <input type="hidden" name="usloviya_oplaty" id="usloviya_oplaty_hidden">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="inye_usloviya">Иные условия</label>
                        <div id="inye_usloviya"></div>
                        <input type="hidden" name="inye_usloviya" id="inye_usloviya_hidden">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_sotrudnika">Ответственный</label>
                        <input class="form-control" type="text" id="id_sotrudnika" name="naimmenovanie_sotrudnika" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimmenovanie_sotrudnika'] ?? $naimmenovanie_sotrudnika) ?>">
                        <input type="hidden" name="id_sotrudnika" class="sotrudniki-id" value="<?= htmlspecialchars($_POST['id_sotrudnika'] ?? $id_sotrudnika) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="podpisant_postavshchika_dolzhnost">Подписант поставщика должность</label>
                        <input class="form-control" type="text" id="podpisant_postavshchika_dolzhnost" name="podpisant_postavshchika_dolzhnost" placeholder="Введите должность..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['podpisant_postavshchika_dolzhnost'] ?? $podpisant_postavshchika_dolzhnost) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="podpisant_postavshchika_fio">Подписант поставщика ФИО</label>
                        <input class="form-control" type="text" id="podpisant_postavshchika_fio" name="podpisant_postavshchika_fio" placeholder="Введите ФИО..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['podpisant_postavshchika_fio'] ?? $podpisant_postavshchika_fio) ?>">
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                         <div class="btn-group" role="group" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            Сохранить
                        </button>
                        <a href="javascript:history.back()" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option><?php foreach ($stavki_nds as $nds): ?><option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option><?php endforeach; ?>';
        </script>
        
        <script>
            
            const formConfig = {
                columns: [
                    { key: 'tovar', label: 'Товар', type: 'autocomplete' },
                    { key: 'edinitsa', label: 'Ед', type: 'autocomplete' },
                    { key: 'kolichestvo', label: 'Кол-во', type: 'text' },
                    { key: 'cena', label: 'Цена', type: 'text' },
                    { key: 'nds_id', label: 'НДС', type: 'select' },
                    { key: 'planiruemaya_data_postavki', label: 'Планируемая дата поставки', type: 'date' }
                ]
            };
            
            let unitsData = <?php echo json_encode($units); ?>;
        </script>
         <script>
            
            document.getElementById('documentForm').addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    const target = event.target;
                    
                    if (target.closest('#tablitsaTovarov')) {
                        event.preventDefault();
                    }
                }
            });
        </script>
        <script>
            let isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;
        </script>
        
        <script src="/js/add_product.js"></script>
        <script src="/js/spec_autocomplete.js"></script>
        
        <!-- Summernote JS -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
         <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs4.min.js"></script>
        
        <script>
            $(document).ready(function() {
                let initialContent1 = <?php echo json_encode($_POST['usloviya_otgruzki'] ?? $usloviya_otgruzki); ?>;
                let initialContent2 = <?php echo json_encode($_POST['usloviya_oplaty'] ?? $usloviya_oplaty); ?>;
                let initialContent3 = <?php echo json_encode($_POST['inye_usloviya'] ?? $inye_usloviya); ?>;
                
                
                $('#usloviya_otgruzki').summernote({
                    height: 100,
                    placeholder: 'Введите условия отгрузки...',
                    tabsize: 2,
                     toolbar: [
                         ['style', ['bold','underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                    ]
                });
                
                $('#usloviya_oplaty').summernote({
                    height: 100,
                    placeholder: 'Введите условия оплаты...',
                    tabsize: 2,
                     toolbar: [
                        ['style', ['bold','underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                    ]
                });
                
                $('#inye_usloviya').summernote({
                    height: 100,
                    placeholder: 'Введите иные условия...',
                    tabsize: 2,
                     toolbar: [
                        ['style', ['bold','underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                    ]
                });
                
                
                if (initialContent1) {
                    $('#usloviya_otgruzki').summernote('code', initialContent1);
                }
                if (initialContent2) {
                    $('#usloviya_oplaty').summernote('code', initialContent2);
                }
                if (initialContent3) {
                    $('#inye_usloviya').summernote('code', initialContent3);
                }
                
                
                $('#documentForm').on('submit', function() {
                    let content1 = $('#usloviya_otgruzki').summernote('code');
                    $('#usloviya_otgruzki_hidden').val(content1);
                    
                    let content2 = $('#usloviya_oplaty').summernote('code');
                    $('#usloviya_oplaty_hidden').val(content2);
                    
                    let content3 = $('#inye_usloviya').summernote('code');
                    $('#inye_usloviya_hidden').val(content3);
                });
            });
        </script>
<?php include '../footer.php'; ?>
