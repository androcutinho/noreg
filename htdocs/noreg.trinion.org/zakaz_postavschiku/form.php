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
require '../queries/zakaz_query.php';


$is_edit = isset($_GET['zakaz_id']) && !empty($_GET['zakaz_id']);
$zakaz_id = $is_edit ? intval($_GET['zakaz_id']) : null;


$page_title = $is_edit ? 'Редактировать заказ поставщику' : 'Новый заказ поставщику';
$date_issued = date('Y-m-d');
$order_number = '';
$vendor_name = '';
$vendor_id = '';
$organization_name = '';
$organization_id = '';
$responsible_name = '';
$responsible_id = '';
$document = null;
$line_items = [];

// Load existing document if editing
if ($is_edit) {
    $document = fetchOrderHeader($mysqli, $zakaz_id);
    
    if (!$document) {
        die("Заказ не найден.");
    }
    
    $line_items = fetchOrderLineItems($mysqli, $zakaz_id);
    $date_issued = $document['data_dokumenta'];
    $order_number = $document['nomer'] ?? '';
    $vendor_name = $document['vendor_name'] ?? '';
    $vendor_id = $document['id_postavshchika'] ?? '';
    $organization_name = $document['organization_name'] ?? '';
    $organization_id = $document['id_organizacii'] ?? '';
    $responsible_name = $document['responsible_name'] ?? '';
    $responsible_id = $document['id_otvetstvennyj'] ?? '';
}

// Fetch NDS rates
$nds_rates = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $nds_rates = $nds_result->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validation
    $validations = array(
        'order_date' => 'Требуется дата заказа',
        'order_number' => 'Требуется указать номер заказа',
        'organization_name' => 'Требуется указать организацию',
        'vendor_name' => 'Требуется указать поставщика',
        'responsible_name' => 'Требуется указать ответственного'
    );
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
   
    if (!$error && empty($_POST['organization_id'])) {
        $error = 'Пожалуйста, выберите организацию из списка';
    }
    
    if (!$error && empty($_POST['vendor_id'])) {
        $error = 'Пожалуйста, выберите поставщика из списка';
    }
    
    if (!$error && empty($_POST['responsible_id'])) {
        $error = 'Пожалуйста, выберите ответственного из списка';
    }
    
    if (!$error && (!isset($_POST['products']) || empty($_POST['products']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                // Update existing document
                $result = updateOrderDocument($mysqli, $zakaz_id, $_POST);
                
                if ($result['success']) {
                    header("Location: zakaz.php?zakaz_id=" . $zakaz_id);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                // Create new document
                $result = createOrderDocument($mysqli, $_POST);
                
                if ($result['success']) {
                    header("Location: index.php");
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
        <?php if ($is_edit): ?>
            #<?= htmlspecialchars($zakaz_id) ?>
        <?php endif; ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">   
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="order_date">Дата заказа</label>
                        <input class="form-control" type="date" id="order_date" name="order_date"
                        value="<?= htmlspecialchars($_POST['order_date'] ?? $date_issued) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="order_number">№ заказа</label>
                        <input class="form-control" type="text" id="order_number" name="order_number" placeholder="Введите номер заказа..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['order_number'] ?? $order_number) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="vendor_id">Поставщик</label>
                        <input class="form-control" type="text" id="vendor_id" name="vendor_name" placeholder="- Выберите поставщика -" autocomplete="off" 
                        value="<?= htmlspecialchars($_POST['vendor_name'] ?? $vendor_name) ?>">
                        <input type="hidden" name="vendor_id" class="vendor-id" value="<?= htmlspecialchars($_POST['vendor_id'] ?? $vendor_id) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="organization_id">Организация</label>
                        <input class="form-control" type="text" id="organization_id" name="organization_name" placeholder="- Выберите организацию -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['organization_name'] ?? $organization_name) ?>">
                        <input type="hidden" name="organization_id" class="organization-id" value="<?= htmlspecialchars($_POST['organization_id'] ?? $organization_id) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="responsible_id">Ответственный</label>
                        <input type="text" class="form-control" id="responsible_id" name="responsible_name" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['responsible_name'] ?? $responsible_name) ?>">
                        <input type="hidden" name="responsible_id" class="responsible-id" value="<?= htmlspecialchars($_POST['responsible_id'] ?? $responsible_id) ?>">
                    </div>
                </div>

                <h2 style="margin-top: 30px;"></h2>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="productsTable">
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
                        <?php if ($is_edit && !empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="product-row">
                            <td><?= $row_index + 1 ?></td>
                            <td>
                                <div class="search-container">
                                    <input class="form-control" type="text" name="products[<?= $row_index ?>][product_name]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($_POST['products'][$row_index]['product_name'] ?? ($item['product_name'] ?? '')) ?>">
                                    <input type="hidden" name="products[<?= $row_index ?>][product_id]" class="product-id" value="<?= htmlspecialchars($item['id_tovary_i_uslugi'] ?? '') ?>">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[<?= $row_index ?>][unit_name]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['unit_name'] ?? ($item['unit_name'] ?? '')) ?>">
                                    <input type="hidden" name="products[<?= $row_index ?>][unit_id]" class="unit-id" value="<?= htmlspecialchars($_POST['products'][$row_index]['unit_id'] ?? ($item['id_edinicy_izmenereniya'] ?? '')) ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][quantity]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['quantity'] ?? ($item['quantity'] ?? '')) ?>"></td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][price]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['price'] ?? ($item['unit_price'] ?? '')) ?>"></td>
                            <td>
                                <select class="form-control" name="products[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($item['id_stavka_nds'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['summa_stavka'] ?? ($item['nds_amount'] ?? '')) ?>"></td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['summa'] ?? ($item['total_amount'] ?? '')) ?>"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="product-row">
                            <td>1</td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[0][product_name]" placeholder="Введите товар..." autocomplete="off">
                                    <input type="hidden" name="products[0][product_id]" class="product-id">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[0][unit_name]" placeholder="Введите ед." autocomplete="off">
                                    <input type="hidden" name="products[0][unit_id]" class="unit-id">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="products[0][quantity]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="products[0][price]" placeholder="0" autocomplete="off"></td>
                            <td>
                                <select class="form-control" name="products[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="products[0][summa_stavka]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="products[0][summa]" placeholder="0" autocomplete="off"></td>
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
                         <div class="btn-group" role="group" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            <?= $is_edit ? 'Сохранить' : 'Сохранить' ?>
                        </button>
                        <a href="index.php" class="btn">Отмена</a>
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
                    { key: 'product', label: 'Товар', type: 'autocomplete' },
                    { key: 'unit', label: 'Ед', type: 'autocomplete' },
                    { key: 'quantity', label: 'Кол-во', type: 'text' },
                    { key: 'price', label: 'Цена', type: 'text' },
                    { key: 'nds_id', label: 'НДС', type: 'select' }
                ]
            };
                        let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($nds_rates as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
            
            // Load units data for autocomplete
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
