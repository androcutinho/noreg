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
require '../queries/postuplenie_queries.php';


$is_edit = isset($_GET['id_tovara']) && !empty($_GET['id_tovara']);
$id_tovara = $is_edit ? intval($_GET['id_tovara']) : null;


$page_title = $is_edit ? 'Редактировать поступление товара' : 'Новое поступление товара';
$data_vypuska = date('Y-m-d');
$naimenovanie_postavschika = '';
$naimenovanie_tovara = '';
$product_guid = '';
$volume = '';
$naimenovanie_edinitsii = '';
$prod_date = date('Y-m-d');
$exp_date = date('Y-m-d');
$naimenovanie_organizacii = '';
$id_organizacii = '';
$naimenovanie_sklada = '';
$id_sklada = '';
$id_postavschika = '';
$naimenovanie_otvetstvennogo = '';
$id_otvetstvennogo = '';
$document = null;
$line_items = [];
$vetis_data_loaded = false;
$vetis_error = '';


if ($is_edit) {
    $document = getDokumentHeader($mysqli, $id_tovara);
    
    if (!$document) {
        die("Документ не найден.");
    }
    
    $line_items = getStrokiDokumentovItems($mysqli, $document['id_index']);
    $data_vypuska = $document['data_dokumenta'];
    $naimenovanie_postavschika = $document['naimenovanie_postavschika'] ?? '';
    $naimenovanie_organizacii = $document['org_name'] ?? '';
    $naimenovanie_sklada = $document['naimenovanie_sklada'] ?? '';
    $naimenovanie_otvetstvennogo = $document['naimenovanie_otvetstvennogo'] ?? '';
    $id_sklada = $document['id_sklada'] ?? '';
    $id_organizacii = $document['id_organizacii'] ?? '';
    $id_postavschika = $document['id_postavschika'] ?? '';
    $id_otvetstvennogo = $document['id_otvetstvennogo'] ?? '';
} else {
    
    $uuid = isset($_GET['uuid']) ? $_GET['uuid'] : null;
    
    if (!empty($uuid)) {
        require_once(__DIR__ . '/../api/vetis_service.php');
        
        $data = getVetisDokument($uuid);
        
        if (!$data['success']) {
            $vetis_error = 'Ошибка загрузки данных VETIS: ' . htmlspecialchars($data['error']);
        } else {
            extract($data);
            $vetis_data_loaded = true;
        }
    }
}


$stavki_nds = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $stavki_nds = $nds_result->fetch_all(MYSQLI_ASSOC);
}


$units = [];
if (!$is_edit && !$vetis_data_loaded) {
    $units_query = "SELECT id, naimenovanie FROM edinicy_izmereniya ORDER BY naimenovanie ASC";
    $units_result = $mysqli->query($units_query);
    if ($units_result) {
        $units = $units_result->fetch_all(MYSQLI_ASSOC);
    }
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  
    $validations = array(
        'product_date' => 'Требуется дата документа',
        'naimenovanie_sklada' => 'Требуется указать склад',
        'naimenovanie_organizacii' => 'Требуется указать организацию',
        'naimenovanie_postavschika' => 'Требуется указать поставщика',
        'naimenovanie_otvetstvennogo' => 'Требуется указать ответственного'
    );
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
   
    if (!$error && empty($_POST['id_otvetstvennogo'])) {
        $error = 'Пожалуйста, выберите ответственного из списка пользователей';
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
                
                $result = obnovitPribytieDokument($mysqli, $id_tovara, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id_tovara=" . $id_tovara);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                
                $result = sozdatPribytieDokument($mysqli, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id_tovara=" . $result['document_id']);
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

<?php if ($vetis_error): ?>
    <div class="alert alert-warning" role="alert">
        <?= $vetis_error ?>
    </div>
<?php endif; ?>


<div class="container-fluid mt-5">
    <h2 class="card-title mt-2 mb-3 fs-1">
        <?= htmlspecialchars($page_title) ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">   
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="product_date">Дата поступления документа</label>
                        <input class="form-control" type="date" id="product_date" name="product_date"
                        value="<?= htmlspecialchars($_POST['product_date'] ?? $data_vypuska) ?>">
                    </div>

                    <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_sklada">Склад</label>
                        <input type="text" class="form-control" id="id_sklada" name="naimenovanie_sklada" placeholder="- Выберите склад -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_sklada'] ?? $naimenovanie_sklada) ?>">
                        <input type="hidden" name="id_sklada" class="id-sklada" value="<?= htmlspecialchars($_POST['id_sklada'] ?? $id_sklada) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_postavschika">Поставщик</label>
                        <input class="form-control" type="text" id="id_postavschika" name="naimenovanie_postavschika" placeholder="- Выберите поставщика -" autocomplete="off" 
                        value="<?= htmlspecialchars($_POST['naimenovanie_postavschika'] ?? $naimenovanie_postavschika) ?>">
                        <input type="hidden" name="id_postavschika" class="id-postavschika" value="<?= htmlspecialchars($_POST['id_postavschika'] ?? $id_postavschika) ?>">
                    </div>

                    <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_organizacii">Организация</label>
                        <input class="form-control" type="text" id="id_organizacii" name="naimenovanie_organizacii" placeholder="- Выберите организацию -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_organizacii'] ?? $naimenovanie_organizacii) ?>">
                        <input type="hidden" name="id_organizacii" class="id-organizacii" value="<?= htmlspecialchars($_POST['id_organizacii'] ?? $id_organizacii) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_otvetstvennogo">Ответственный</label>
                        <input type="text" class="form-control" id="id_otvetstvennogo" name="naimenovanie_otvetstvennogo" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_otvetstvennogo'] ?? $naimenovanie_otvetstvennogo) ?>">
                        <input type="hidden" name="id_otvetstvennogo" class="id-otvetstvennogo" value="<?= htmlspecialchars($_POST['id_otvetstvennogo'] ?? $id_otvetstvennogo) ?>">
                    </div>
                </div>

                
                <div class="card mt-3">
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
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id-tovara" value="<?= htmlspecialchars($submitted_item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($submitted_item['naimenovanie_serii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_serii]" class="id-serii" value="<?= htmlspecialchars($submitted_item['id_serii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($submitted_item['naimenovanie_edinitsii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($submitted_item['id_edinitsii'] ?? '') ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['kolichestvo'] ?? '') ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['cena'] ?? '') ?>"></td>
                            <td>
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($submitted_item['nds_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" readonly value="<?= htmlspecialchars($submitted_item['summa_stavka'] ?? '') ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" readonly value="<?= htmlspecialchars($submitted_item['summa'] ?? '') ?>"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php elseif ($is_edit && !empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="tovar-row">
                            <td class="col-num"><?= $row_index + 1 ?></td>
                            <td class="col-tovar">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id-tovara" value="<?= htmlspecialchars($item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($item['naimenovanie_serii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_serii]" class="id-serii" value="<?= htmlspecialchars($item['id_serii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container position-relative">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($item['id_edinitsii'] ?? '') ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['kolichestvo'] ?? '') ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['ed_cena'] ?? '') ?>"></td>
                            <td>
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($item['nds_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" readonly value="<?= htmlspecialchars($item['summa_nds'] ?? '') ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" readonly value="<?= htmlspecialchars($item['obshchaya_summa'] ?? '') ?>"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="tovar-row">
                            <td class="col-num">1</td>
                            <td class="col-tovar">
                                <div class="search-container position-relative">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($naimenovanie_tovara) ?>">
                                    <input type="hidden" name="tovary[0][id_tovara]" class="id-tovara">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container position-relative">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($vetis_data_loaded ? substr($product_guid, 0, 36) : '') ?>">
                                    <input type="hidden" name="tovary[0][id_serii]" class="id-serii">
                                </div>
                            </td>
                            <td class="col-edinitsa">
                                <div class="search-container position-relative">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($naimenovanie_edinitsii) ?>">
                                    <input type="hidden" name="tovary[0][id_edinitsii]" class="id-edinitsii">
                                </div>
                            </td>
                            <td class="col-kolichestvo"><input class="form-control" type="text" name="tovary[0][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($volume) ?>"></td>
                            <td class="col-cena"><input class="form-control" type="text" name="tovary[0][cena]" placeholder="0" autocomplete="off"></td>
                            <td class="col-nds">
                                <select class="form-control" name="tovary[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-summa-stavka"><input class="form-control" type="text" name="tovary[0][summa_stavka]" placeholder="0" autocomplete="off" readonly></td>
                            <td class="col-summa"><input class="form-control" type="text" name="tovary[0][summa]" placeholder="0" autocomplete="off" readonly></td>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <?php if ($vetis_data_loaded): ?>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-3 position-relative">
                            <label class="form-label" for="data_izgotovleniya">Дата изготовления</label>
                            <input class="form-control" type="date" id="data_izgotovleniya" name="data_izgotovleniya" autocomplete="off" value="<?= htmlspecialchars($prod_date) ?>">
                        </div>

                        <div class="col-md-6 mb-3 position-relative">
                            <label class="form-label" for="srok_godnosti">Срок годности</label>
                            <input class="form-control" type="date" id="srok_godnosti" name="srok_godnosti" autocomplete="off" value="<?= htmlspecialchars($exp_date) ?>">
                        </div>    
                    </div>
                <?php endif; ?>
                
                <div class="row mt-2">
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
                    { key: 'summa_stavka', label: 'Сумма НДС', type: 'readonly' },
                    { key: 'summa', label: 'Сумма', type: 'readonly' }
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
        <script src="../js/add_product.js"></script>
</div>
<?php include '../footer.php'; ?>
