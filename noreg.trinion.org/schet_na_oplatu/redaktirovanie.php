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
require '../queries/schet_na_oplatu_query.php';
require '../queries/zakaz_pokupatelya_query.php';
require '../queries/zakaz_query.php';

if (!function_exists('getTovaryZakazasostatkom')) {
    require_once __DIR__ . '/../queries/id_index_helper.php';
}


$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$id = $is_edit ? intval($_GET['id']) : null;


$zakaz_id = isset($_GET['zakaz_id']) ? intval($_GET['zakaz_id']) : (isset($_POST['zakaz_id']) ? intval($_POST['zakaz_id']) : null);


$page_title = $is_edit ? 'Редактировать cчет на оплату' : 'Новый cчет на оплату';
$data_vypuska = date('Y-m-d');
$naimenovanie_postavschika = '';
$id_postavschika = '';
$naimenovanie_organizacii = '';
$id_organizacii = '';
$naimenovanie_otvetstvennogo = '';
$id_otvetstvennogo = '';
$schet_pokupatelya_id = '';
$schet_pokupatelya_naimenovanie = '';
$schet_postavschika_id = '';
$schet_postavschika_naimenovanie = '';
$utverzhden = '';
$invoice_type = 'supplier';
$document = null;
$line_items = [];


if ($zakaz_id && !$is_edit) {

    $istochnik_zakaza = 'buyer'; 
    
    
    $supplier_verif_query = "SELECT id FROM zakazy_postavshchikam WHERE id = ?";
    $supplier_stmt = $mysqli->prepare($supplier_verif_query);
    if ($supplier_stmt) {
        $supplier_stmt->bind_param('i', $zakaz_id);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        if ($supplier_result->num_rows > 0) {
            $istochnik_zakaza = 'supplier';
        }
        $supplier_stmt->close();
    }
    
    
    if ($istochnik_zakaza === 'supplier') {
        $zakaz = fetchZakazHeader($mysqli, $zakaz_id); 
    } else {
        $zakaz = getZakazHeader($mysqli, $zakaz_id); 
    }
    
    if ($zakaz) {
        $data_vypuska = date('Y-m-d');
        $naimenovanie_postavschika = $zakaz['naimenovanie_postavschika'] ?? '';
        $naimenovanie_organizacii = $zakaz['naimenovanie_organizacii'] ?? '';
        $naimenovanie_otvetstvennogo = $zakaz['naimenovanie_otvetstvennogo'] ?? '';
        $id_otvetstvennogo = $zakaz['id_otvetstvennyj'] ?? '';
        
        
        if ($istochnik_zakaza === 'supplier') {
            
            $id_postavschika = $zakaz['id_kontragenti_postavshchik'] ?? '';
            $id_organizacii = $zakaz['id_kontragenti_pokupatel'] ?? '';
            $invoice_type = 'supplier'; 
        } else {
        
            $id_postavschika = $zakaz['id_kontragenti_pokupatel'] ?? '';
            $id_organizacii = $zakaz['id_kontragenti_postavshik'] ?? '';
            $invoice_type = 'buyer'; 
        }
        
        if ($istochnik_zakaza === 'supplier') {
            $zakaz_line_items = getZakazStrokiItems($mysqli, $zakaz['id_index']);
        } else {
            $zakaz_line_items = getZakazStrokiItemsPokupatieliu($mysqli, $zakaz['id_index']);
        }
        $line_items = $zakaz_line_items;
    }
}

if ($is_edit) {
    $document = fetchSchetHeader($mysqli, $id);
    
    if (!$document) {
        die("Заказ не найден.");
    }
    
    $line_items = getSchetStrokiItems($mysqli, $document['id_index']);
    $data_vypuska = $document['data_dokumenta'];
    $naimenovanie_postavschika = $document['naimenovanie_postavschika'] ?? '';
    $id_postavschika = $document['id_kontragenti_pokupatel'] ?? '';
    $naimenovanie_organizacii = $document['naimenovanie_organizacii'] ?? '';
    $id_organizacii = $document['id_kontragenti_postavshik'] ?? '';
    $naimenovanie_otvetstvennogo = $document['naimenovanie_otvetstvennogo'] ?? '';
    $id_otvetstvennogo = $document['id_otvetstvennyj'] ?? '';
    $schet_pokupatelya_id = $document['Id_raschetnye_scheta_kontragenti_pokupatel'] ?? '';
    $schet_postavschika_id = $document['Id_raschetnye_scheta_organizacii'] ?? '';
    $schet_pokupatelya_naimenovanie = $document['schet_pokupatelya_naimenovanie'] ?? '';
    $schet_postavschika_naimenovanie = $document['schet_postavschika_naimenovanie'] ?? '';
    $utverzhden = $document['utverzhden'] ?? 0;
    
    
    if (!empty($document['pokupatelya'])) {
        $invoice_type = 'buyer';
    } elseif (!empty($document['ot_postavshchika'])) {
        $invoice_type = 'supplier';
    } else {
        $invoice_type = 'supplier'; 
    }
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
        'schet_date' => 'Требуется дата заказа',
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
    
    if (!$error && empty($_POST['id_organizacii'])) {
        $error = 'Пожалуйста, выберите организацию из списка';
    }
    
    if (!$error && empty($_POST['id_postavschika'])) {
        $error = 'Пожалуйста, выберите поставщика из списка';
    }
    
    if (!$error && empty($_POST['id_otvetstvennogo'])) {
        $error = 'Пожалуйста, выберите ответственного из списка';
    }
    
    if (!$error && (!isset($_POST['tovary']) || empty($_POST['tovary']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                $result = obnovitSchetDokument($mysqli, $id, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $id);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                $zakaz_id_for_create = $zakaz_id ?? ($_POST['zakaz_id'] ?? null);
                $result = sozdatSchetDokument($mysqli, $_POST, $zakaz_id_for_create);
                
                if ($result['success']) {
                    $redirect_type = isset($_POST['invoice_type']) && $_POST['invoice_type'] === 'buyer' ? 'pokupatel' : 'postavschik';
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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="schet_date">Дата</label>
                        <input class="form-control" type="date" id="schet_date" name="schet_date"
                        value="<?= htmlspecialchars($_POST['schet_date'] ?? $data_vypuska) ?>">
                    </div>
                    <div class="col-md-6 mb-3 mt-5">
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="invoice_type" value="supplier" <?= ($invoice_type === 'supplier') ? 'checked' : '' ?>>
                                  <span class="form-check-label">От поставщика</span>
                                </label>
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="invoice_type" value="buyer" <?= ($invoice_type === 'buyer') ? 'checked' : '' ?>>
                                  <span class="form-check-label">Покупателя</span>
                                </label>
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
                        <label class="form-label" for="schet_pokupatelya_id">Расчетный счет покупателя</label>
                        <input class="form-control" type="text" id="schet_pokupatelya_id" name="schet_pokupatelya_naimenovanie" placeholder="- Выберите расчетный счет покупателя -" autocomplete="off" 
                        value="<?= htmlspecialchars($_POST['schet_pokupatelya_naimenovanie'] ?? $schet_pokupatelya_naimenovanie) ?>">
                        <input type="hidden" name="schet_pokupatelya_id" class="schet-pokupatelya-id" value="<?= htmlspecialchars($_POST['schet_pokupatelya_id'] ?? $schet_pokupatelya_id) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="schet_postavschika_id">Расчетный счет поставщика</label>
                        <input class="form-control" type="text" id="schet_postavschika_id" name="schet_postavschika_naimenovanie" placeholder="- Выберите расчетный счет поставщика -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['schet_postavschika_naimenovanie'] ?? $schet_postavschika_naimenovanie) ?>">
                        <input type="hidden" name="schet_postavschika_id" class="schet-postavschika-id" value="<?= htmlspecialchars($_POST['schet_postavschika_id'] ?? $schet_postavschika_id) ?>">
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
                            <th>№</th>
                            <th>Товар</th>
                            <th>Ед</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>НДС</th>
                            <th>Сумма НДС</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <?php if (!empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="tovar-row">
                            <td><?= $row_index + 1 ?></td>
                            <td>
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($_POST['tovary'][$row_index]['naimenovanie_tovara'] ?? ($item['naimenovanie_tovara'] ?? '')) ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id_tovara" value="<?= htmlspecialchars($item['id_tovary_i_uslugi'] ?? '') ?>">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['naimenovanie_edinitsii'] ?? ($item['naimenovanie_edinitsii'] ?? '')) ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_edinitsii]" class="id-edinitsii" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['id_edinitsii'] ?? ($item['id_edinicy_izmereniya'] ?? '')) ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['kolichestvo'] ?? (isset($item['kolichestvo_ostatka']) && $item['kolichestvo_ostatka'] > 0 ? $item['kolichestvo_ostatka'] : (isset($item['kolichestvo']) ? $item['kolichestvo'] : '0'))) ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['cena'] ?? ($item['ed_cena'] ?? '')) ?>"></td>
                            <td>
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($item['id_stavka_nds'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['summa_stavka'] ?? ($item['obshchaya_summa'] ?? '')) ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['summa'] ?? (isset($item['summa_ostatka']) && $item['summa_ostatka'] > 0 ? $item['summa_ostatka'] : (isset($item['obshchaya_summa']) ? $item['obshchaya_summa'] : '0'))) ?>"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="tovar-row">
                            <td>1</td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_tovara]" class="id_tovara">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_edinitsii]" placeholder="Введите ед." autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_edinitsii]" class="id-edinitsii">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[0][kolichestvo]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="tovary[0][cena]" placeholder="0" autocomplete="off"></td>
                            <td>
                                <select class="form-control" name="tovary[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[0][summa_stavka]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="tovary[0][summa]" placeholder="0" autocomplete="off"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                         <div class="btn-gruppa" role="gruppa" aria-label="Basic example">
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
        
        <script>            // Form configuration for zakaz_postavschiku
            const formConfig = {
                columns: [
                    { key: 'tovar', label: 'Товар', type: 'autocomplete' },
                    { key: 'edinitsa', label: 'Ед', type: 'autocomplete' },
                    { key: 'kolichestvo', label: 'Кол-во', type: 'text' },
                    { key: 'cena', label: 'Цена', type: 'text' },
                    { key: 'nds_id', label: 'НДС', type: 'select' }
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
            
         
            document.addEventListener('DOMContentLoaded', function() {

                const vendorName = document.querySelector('input[name="naimenovanie_postavschika"]');
                const vendorIdField = document.querySelector('input[name="id_postavschika"][class="id-postavschika"]');
                if (vendorName && vendorIdField && vendorName.value && !vendorIdField.value) {
                    <?php if ($is_edit && $id_postavschika): ?>
                        vendorIdField.value = <?= json_encode($id_postavschika) ?>;
                    <?php endif; ?>
                }
                
            
                const orgName = document.querySelector('input[name="naimenovanie_organizacii"]');
                const orgIdField = document.querySelector('input[name="id_organizacii"][class="id-organizacii"]');
                if (orgName && orgIdField && orgName.value && !orgIdField.value) {
                    <?php if ($is_edit && $id_organizacii): ?>
                        orgIdField.value = <?= json_encode($id_organizacii) ?>;
                    <?php endif; ?>
                }
                
                
                const respName = document.querySelector('input[name="naimenovanie_otvetstvennogo"]');
                const respIdField = document.querySelector('input[name="id_otvetstvennogo"][class="id-otvetstvennogo"]');
                if (respName && respIdField && respName.value && !respIdField.value) {
                    <?php if ($is_edit && $id_otvetstvennogo): ?>
                        respIdField.value = <?= json_encode($id_otvetstvennogo) ?>;
                    <?php endif; ?>
                }
                
                /
                const schetPokupName = document.querySelector('input[name="schet_pokupatelya_naimenovanie"]');
                const schetPokupIdField = document.querySelector('input[name="schet_pokupatelya_id"][class="schet-pokupatelya-id"]');
                if (schetPokupName && schetPokupIdField && schetPokupName.value && !schetPokupIdField.value) {
                    <?php if ($is_edit && !empty($schet_pokupatelya_id)): ?>
                        schetPokupIdField.value = <?= json_encode($schet_pokupatelya_id) ?>;
                    <?php endif; ?>
                }
                
                
                const schetPostName = document.querySelector('input[name="schet_postavschika_naimenovanie"]');
                const schetPostIdField = document.querySelector('input[name="schet_postavschika_id"][class="schet-postavschika-id"]');
                if (schetPostName && schetPostIdField && schetPostName.value && !schetPostIdField.value) {
                    <?php if ($is_edit && !empty($schet_postavschika_id)): ?>
                        schetPostIdField.value = <?= json_encode($schet_postavschika_id) ?>;
                    <?php endif; ?>
                }
            });
        </script>
        <script src="../js/schet.js"></script>
        <script src="../js/add_product.js"></script>
</div>
<?php include '../footer.php'; ?>