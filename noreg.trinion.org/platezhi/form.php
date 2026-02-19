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
require '../queries/platezhi_queries.php';


$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$is_from_schet = isset($_GET['schet_id']) && !empty($_GET['schet_id']);

$id = $is_edit ? intval($_GET['id']) : null;
$schet_id = $is_from_schet ? intval($_GET['schet_id']) : null;

$page_title = $is_edit ? 'Редактировать платеж' : 'Новый платеж';
$date_issued = date('Y-m-d\TH:i');
$vendor_name = '';
$vendor_id = '';
$organization_name = '';
$organization_id = '';
$document = null;
$line_items = [];
$schet_type_vhodyashchij = false;
$schet_type_iskhodyashchij = false;


if ($is_edit) {

    $payment_sql = "SELECT 
        id,
        data_dokumenta,
        id_kontragenti_platelshik,
        id_kontragenti_poluchatel,
        nomer,
        vhodyashchij,
        iskhodyashchij,
        summa,
        id_index
    FROM platezhi WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if ($stmt->prepare($payment_sql)) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment_doc = $result->fetch_assoc();
        $stmt->close();
        
        if ($payment_doc) {
            // Get vendor info
            $vendor_sql = "SELECT id, naimenovanie FROM kontragenti WHERE id = ?";
            $v_stmt = $mysqli->stmt_init();
            if ($v_stmt->prepare($vendor_sql)) {
                $v_stmt->bind_param('i', $payment_doc['id_kontragenti_platelshik']);
                $v_stmt->execute();
                $v_result = $v_stmt->get_result();
                $vendor_row = $v_result->fetch_assoc();
                if ($vendor_row) {
                    $vendor_id = $vendor_row['id'];
                    $vendor_name = $vendor_row['naimenovanie'];
                }
                $v_stmt->close();
            }
            
            // Get organization info
            $org_sql = "SELECT id, naimenovanie FROM kontragenti WHERE id = ?";
            $o_stmt = $mysqli->stmt_init();
            if ($o_stmt->prepare($org_sql)) {
                $o_stmt->bind_param('i', $payment_doc['id_kontragenti_poluchatel']);
                $o_stmt->execute();
                $o_result = $o_stmt->get_result();
                $org_row = $o_result->fetch_assoc();
                if ($org_row) {
                    $organization_id = $org_row['id'];
                    $organization_name = $org_row['naimenovanie'];
                }
                $o_stmt->close();
            }
            
            // Get line items
            $line_sql = "SELECT 
                id,
                id_dokumenta,
                id_stavka_nds,
                summa,
                summa_nds
            FROM stroki_platezhej WHERE id_dokumenta = ?";
            
            $l_stmt = $mysqli->stmt_init();
            if ($l_stmt->prepare($line_sql)) {
                $l_stmt->bind_param('i', $payment_doc['nomer']);
                $l_stmt->execute();
                $l_result = $l_stmt->get_result();
                
                while ($line_row = $l_result->fetch_assoc()) {
                    $line_items[] = $line_row;
                }
                $l_stmt->close();
            }
            
            $date_issued = date('Y-m-d\TH:i');
            $schet_type_vhodyashchij = (bool)$payment_doc['vhodyashchij'];
            $schet_type_iskhodyashchij = (bool)$payment_doc['iskhodyashchij'];
        }
    }
} elseif ($is_from_schet) {

    $schet_data = fetchSchetDataForPayment($mysqli, $schet_id);
    
    if ($schet_data) {
        $vendor_id = $schet_data['vendor_id'];
        $vendor_name = $schet_data['vendor_name'];
        $organization_id = $schet_data['organization_id'];
        $organization_name = $schet_data['organization_name'];
        $date_issued = date('Y-m-d');
        
        
        $line_items = fetchSchetLineItemsForPayment($mysqli, $schet_data['id_index']);
        
        
        $schet_type_vhodyashchij = !empty($schet_data['pokupatelya']);
        $schet_type_iskhodyashchij = !empty($schet_data['ot_postavshchika']);
    } else {
        die("Счет-фактура не найдена.");
    }
}

$nds_rates = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $nds_rates = $nds_result->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($schet_id) && !$is_edit) {
        $error = 'Требуется указать счет-фактуру';
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                $result = updatePaymentDocument($mysqli, $id, $_POST + ['schet_id' => $schet_id ?? null]);
                
                if ($result['success']) {
                    $redirect_type = (!empty($_POST['iskhodyashchij']) && $_POST['iskhodyashchij'] == '1') ? 'iskhodyashchij' : 'vhodyashchij';
                    header("Location: index.php?type=" . $redirect_type);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                $result = createPaymentDocument($mysqli, $_POST + ['schet_id' => $schet_id]);
                
                if ($result['success']) {
                    $redirect_type = (!empty($_POST['iskhodyashchij']) && $_POST['iskhodyashchij'] == '1') ? 'iskhodyashchij' : 'vhodyashchij';
                    header("Location: index.php?type=" . $redirect_type);
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
            #<?= htmlspecialchars($id) ?>
        <?php endif; ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">
                <input type="hidden" name="schet_id" value="<?= htmlspecialchars($schet_id ?? '') ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="schet_date">Дата и время платежа</label>
                        <input class="form-control" type="datetime-local" id="schet_date" name="schet_date"
                        value="<?= htmlspecialchars($_POST['schet_date'] ?? ($date_issued ?: date('Y-m-d\TH:i'))) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="vendor_id">Плательщик (Покупатель)</label>
                        <input class="form-control" type="text" id="vendor_id" name="vendor_name" 
                        value="<?= htmlspecialchars($vendor_name) ?>">
                        <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($vendor_id) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="organization_id">Получатель (Поставщик)</label>
                        <input class="form-control" type="text" id="organization_id" name="organization_name" 
                        value="<?= htmlspecialchars($organization_name) ?>">
                        <input type="hidden" name="organization_id" value="<?= htmlspecialchars($organization_id) ?>">
                    </div>
                </div>

                <input type="hidden" name="vhodyashchij" value="<?= $schet_type_vhodyashchij ? '1' : '0' ?>">
                <input type="hidden" name="iskhodyashchij" value="<?= $schet_type_iskhodyashchij ? '1' : '0' ?>">

                <h3 style="margin-top: 30px; margin-bottom: 15px;"></h3>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="productsTable">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Товар</th>
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
                        <tr class="product-row">
                            <td><?= $row_index + 1 ?></td>
                            <td>
                                <div class="search-container">
                                    <input class="form-control" type="text" name="products[<?= $row_index ?>][product_name]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($_POST['products'][$row_index]['product_name'] ?? ($item['product_name'] ?? '')) ?>">
                                    <input type="hidden" name="products[<?= $row_index ?>][product_id]" class="product-id" value="<?= htmlspecialchars($item['product_id'] ?? '') ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][quantity]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['quantity'] ?? ($item['quantity'] ?? '0')) ?>"></td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][price]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['price'] ?? ($item['unit_price'] ?? '0')) ?>"></td>
                            <td>
                                <select class="form-control" name="products[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($_POST['products'][$row_index]['nds_id'] ?? ($item['nds_id'] ?? ''))) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['summa_stavka'] ?? ($item['nds_amount'] ?? '0')) ?>"></td>
                            <td><input class="form-control" type="text" name="products[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['products'][$row_index]['summa'] ?? ($item['total_amount'] ?? '0')) ?>"></td>
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

                <div class="row" style="margin-top: 40px;">
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
        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($nds_rates as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
            
            function addRow() {
                const table = document.getElementById('productsBody');
                const rowCount = table.querySelectorAll('.product-row').length;
                const rowIndex = rowCount;
                
                const newRow = document.createElement('tr');
                newRow.className = 'product-row';
                newRow.innerHTML = `
                    <td>${rowIndex + 1}</td>
                    <td>
                        <div class="search-container" style="position: relative;">
                            <input class="form-control" type="text" name="products[${rowIndex}][product_name]" placeholder="Введите товар..." autocomplete="off">
                            <input type="hidden" name="products[${rowIndex}][product_id]" class="product-id">
                        </div>
                    </td>
                    <td><input class="form-control" type="text" name="products[${rowIndex}][quantity]" placeholder="0" autocomplete="off"></td>
                    <td><input class="form-control" type="text" name="products[${rowIndex}][price]" placeholder="0" autocomplete="off"></td>
                    <td>
                        <select class="form-control" name="products[${rowIndex}][nds_id]">
                            ${ndsOptionsTemplate}
                        </select>
                    </td>
                    <td><input class="form-control" type="text" name="products[${rowIndex}][summa_stavka]" placeholder="0" autocomplete="off"></td>
                    <td><input class="form-control" type="text" name="products[${rowIndex}][summa]" placeholder="0" autocomplete="off"></td>
                    <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                `;
                
                table.appendChild(newRow);
                updateRowNumbers();
            }
            
            function deleteRow(icon) {
                icon.closest('tr').remove();
                updateRowNumbers();
            }
            
            function updateRowNumbers() {
                const rows = document.querySelectorAll('#productsBody .product-row');
                rows.forEach((row, index) => {
                    row.cells[0].textContent = index + 1;
                });
            }
        </script>
</div>
<?php include '../footer.php'; ?>