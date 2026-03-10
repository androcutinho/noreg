<?php

session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/database_queries.php';
require '../queries/peremeshchenie_tovara_mezhdu_skladami_queries.php';


$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$id = $is_edit ? intval($_GET['id']) : null;
$source_postuplenie_id = isset($_GET['source_postuplenie_id']) ? intval($_GET['source_postuplenie_id']) : null;

$page_title = $is_edit ? 'Редактировать перемещение товара' : ($source_otgruzka_id ? 'Новое перемещение товара' : 'Новое перемещение товара');
$data_vypuska = date('Y-m-d');
$naimenovanie_tovara = '';
$naimenovanie_edinitsii = '';
$naimenovanie_otvetstvennogo = '';
$id_otvetstvennogo = '';
$naimenovanie_sklada_poluchatel = '';
$id_sklada_poluchatel = '';
$naimenovanie_sklada_postavshchik = '';
$id_sklada_postavshchik = '';
$tip_dokumenta = '';
$postuplenie= '';
$otgruzka= '';
$document = null;
$line_items = [];


if ($is_edit) {
    $document = getDokumentHeader($mysqli, $id);
    
    if (!$document) {
        die("Документ не найден.");
    }
    
    $line_items = getStrokiDokumentovItems($mysqli, $document['id_index']);
    $data_vypuska = $document['data_dokumenta'];
    $tip_dokumenta = $document['tip_dokumenta'] ?? '';
    $naimenovanie_sklada_poluchatel = $document['naimenovanie_sklada_poluchatel'] ?? '';
    $naimenovanie_sklada_postavshchik = $document['naimenovanie_sklada_postavshchik'] ?? '';
    $naimenovanie_otvetstvennogo = $document['naimenovanie_otvetstvennogo'] ?? '';
    $id_sklada = $document['id_sklada'] ?? '';
    $id_sklada_poluchatel = $document['id_sklada_poluchatel'] ?? '';
    $id_sklada_postavshchik = $document['id_sklada_postavshchik'] ?? '';
    $id_otvetstvennogo = $document['id_otvetstvennogo'] ?? '';

    if (!empty($document['otgruzka'])) {
        $tip_dokumenta = 'otgruzka';
    } elseif (!empty($document['postuplenie'])) {
        $tip_dokumenta = 'postuplenie';}
} 
elseif ($source_postuplenie_id) {
    $source_document = getDokumentHeader($mysqli, $source_postuplenie_id);
    
    if (!$source_document || !$source_document['postuplenie']) {
        die("Исходящий документ не найден.");
    }
    
    $line_items = getStrokiDokumentovItems($mysqli, $source_document['id_index']);
    $data_vypuska = date('Y-m-d');
    $tip_dokumenta = 'otgruzka'; 
    $naimenovanie_sklada_poluchatel = $source_document['naimenovanie_sklada_poluchatel'] ?? '';
    $naimenovanie_sklada_postavshchik = $source_document['naimenovanie_sklada_postavshchik'] ?? '';
    $naimenovanie_otvetstvennogo = $source_document['naimenovanie_otvetstvennogo'] ?? '';
    $id_sklada_poluchatel = $source_document['id_sklada_poluchatel'] ?? '';
    $id_sklada_postavshchik = $source_document['id_sklada_postavshchik'] ?? '';
    $id_otvetstvennogo = $source_document['id_otvetstvennogo'] ?? '';
}
else {
    if (!empty($_GET['tip_dokumenta']) && in_array($_GET['tip_dokumenta'], ['postuplenie', 'otgruzka'])) {
        $tip_dokumenta = $_GET['tip_dokumenta'];
    }
}


$units = [];
if (!$is_edit) {
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
        'data_vypuska' => 'Требуется дата документа',
        'id_sklada_poluchatel' => 'Требуется указать склад получатель',
        'id_sklada_postavshchik' => 'Требуется указать склад поставщик',
        'id_otvetstvennogo' => 'Требуется указать ответственного'
    );
    
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
            }
        }
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                
                $result = obnovitPeremeshchenieDokument($mysqli, $id, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $id);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                
                $result = sozdatPeremeshchenieDokument($mysqli, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $result['document_id']);
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



<div class="container-fluid mt-5">
    <h2 class="card-title mt-2 mb-3 fs-1">
        <?= htmlspecialchars($page_title) ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">
                <?php if ($source_postuplenie_id): ?>
                    <input type="hidden" name="parent_postuplenie_id" value="<?= htmlspecialchars($source_postuplenie_id) ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="data_vypuska">Дата поступления документа</label>
                        <input class="form-control" type="date" id="data_vypuska" name="data_vypuska"
                        value="<?= htmlspecialchars($_POST['data_vypuska'] ?? $data_vypuska) ?>">
                    </div>
                    <div class="col-md-6 mb-3 mt-5">
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="tip_dokumenta" value="postuplenie" <?= ($tip_dokumenta === 'postuplenie') ? 'checked' : '' ?>>
                                  <span class="form-check-label">Поступление</span>
                                </label>
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="tip_dokumenta" value="otgruzka" <?= ($tip_dokumenta === 'otgruzka') ? 'checked' : '' ?>>
                                  <span class="form-check-label">Отгрузка</span>
                                </label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_sklada_poluchatel">Склад получатель</label>
                        <input type="text" class="form-control" id="id_sklada_poluchatel" name="naimenovanie_sklada_poluchatel" placeholder="- Выберите склад -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_sklada_poluchatel'] ?? $naimenovanie_sklada_poluchatel) ?>">
                        <input type="hidden" name="id_sklada_poluchatel" class="id-sklada-poluchatel" value="<?= htmlspecialchars($_POST['id_sklada_poluchatel'] ?? $id_sklada_poluchatel) ?>">
                    </div>

                   <div class="col-md-6 mb-3 position-relative">
                        <label class="form-label" for="id_sklada_postavshchik">Склад поставщик</label>
                        <input type="text" class="form-control" id="id_sklada_postavshchik" name="naimenovanie_sklada_postavshchik" placeholder="- Выберите склад -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_sklada_postavshchik'] ?? $naimenovanie_sklada_postavshchik) ?>">
                        <input type="hidden" name="id_sklada_postavshchik" class="id-sklada-postavshchik" value="<?= htmlspecialchars($_POST['id_sklada_postavshchik'] ?? $id_sklada_postavshchik) ?>">
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
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php elseif (($is_edit || $source_postuplenie_id) && !empty($line_items)): ?>
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
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
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
                    { key: 'kolichestvo', label: 'Кол-во', type: 'text' }
                ]
            };
           
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