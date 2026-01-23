<?php

session_start();

$page_title = 'Новое поступление товара';

$mysqli = require 'database.php';
require 'config/database_config.php';
require 'queries/database_queries.php';

$nds_rates = fetchTableData($mysqli, TABLE_NDS_RATES, COL_NDS_ID, COL_NDS_RATE, COL_NDS_RATE);

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Массив валидации
    $validations = array(
        'product_date' => 'Требуется дата документа',
        'warehouse_id' => 'Требуется выбрать склад',
        'organization_id' => 'Требуется выбрать организацию',
        'vendor_id' => 'Требуется выбрать поставщика',
        'responsible_id' => 'Требуется выбрать ответственного'
    );
    
    // Проверить обязательные поля
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
    // Проверить товары
    if (!$error && (!isset($_POST['products']) || empty($_POST['products']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error) {
        
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            $mysqli->begin_transaction();
            
            try {
            $warehouse_id = intval($_POST['warehouse_id']);
            $organization_id = intval($_POST['organization_id']);
            $responsible_id = intval($_POST['responsible_id']);
            $vendor_id = intval($_POST['vendor_id']);
            
            // Преобразовать datetime-local в формат MySQL datetime
            $datetime = $_POST['product_date'];
            
            $datetime = str_replace('T', ' ', $datetime) . ':00';
            
            
            $arrival_sql = "INSERT INTO " . TABLE_ARRIVALS . "(" . COL_ARRIVAL_VENDOR_ID . ", " . COL_ARRIVAL_ORG_ID . ", " . COL_ARRIVAL_WAREHOUSE_ID . ", " . COL_ARRIVAL_RESPONSIBLE_ID . ", " . COL_ARRIVAL_DATE . ") VALUES (?, ?, ?, ?, ?)";
            $arrival_stmt = $mysqli->stmt_init();
            
            if (!$arrival_stmt->prepare($arrival_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $arrival_stmt->bind_param(
                "iiiis",
                $vendor_id,
                $organization_id,
                $warehouse_id,
                $responsible_id,
                $datetime
            );
            
            if (!$arrival_stmt->execute()) {
                throw new Exception("Ошибка при создании документа поступления: " . $mysqli->error);
            }
            
            $document_id = $mysqli->insert_id;
            
            
            $products_data = $_POST['products'];
            foreach ($products_data as $product) {
                if (empty($product['product_id']) || empty($product['price']) || empty($product['quantity']) || empty($product['nds_id'])) {
                    continue; 
                }
                
                $goods_id = intval($product['product_id']);
                $nds_id = intval($product['nds_id']);
                $price = floatval($product['price']);
                $quantity = floatval($product['quantity']);
                $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
                
                
                if ($seria_id > 0) {
                    $update_seria_sql = "UPDATE " . TABLE_SERIES . " SET " . COL_SERIES_PRODUCT_ID . " = ? WHERE " . COL_SERIES_ID . " = ?";
                    $update_seria_stmt = $mysqli->stmt_init();
                    
                    if (!$update_seria_stmt->prepare($update_seria_sql)) {
                        throw new Exception("SQL error al preparar UPDATE serii: " . $mysqli->error);
                    }
                    
                    $update_seria_stmt->bind_param("ii", $goods_id, $seria_id);
                    
                    if (!$update_seria_stmt->execute()) {
                        throw new Exception("Error al actualizar serii: " . $mysqli->error);
                    }
                }
                
                
                $line_sql = "INSERT INTO " . TABLE_DOCUMENT_LINES . "(" . COL_LINE_DOCUMENT_ID . ", " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ") VALUES (?, ?, ?, ?, ?)";
                $line_stmt = $mysqli->stmt_init();
                
                if (!$line_stmt->prepare($line_sql)) {
                    throw new Exception("SQL error: " . $mysqli->error);
                }
                
                $line_stmt->bind_param(
                    "iiidd",
                    $document_id,
                    $goods_id,
                    $nds_id,
                    $price,
                    $quantity
                );
                
                if (!$line_stmt->execute()) {
                    throw new Exception("Ошибка при добавлении строки документа: " . $mysqli->error);
                }
            }
            
            
            $mysqli->commit();
            $success = true;
            $_POST = array(); 
            $error = 'Документ поступления успешно создан!';
            
            } catch (Exception $e) {
                $mysqli->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

include 'header.php';
?>
<link rel="stylesheet" href="css/add_product.css">

<?php if ($error): ?>
    <div class="<?php echo $success ? 'success' : 'error'; ?>">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <h2>Новое поступление товара</h2>
            <form method="POST" id="documentForm">   
                <div class="form-row">
                    <div class="mb-3">
                        <label class="form-label" for="product_date">Дата поступления документа</label>
                        <input class="form-control" type="datetime-local" id="product_date" name="product_date" required
                        value="<?= htmlspecialchars($_POST['product_date'] ?? date('Y-m-d\TH:i')) ?>">
                    </div>

                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="warehouse_id">Склад</label>
                        <input type="text" class="form-control" id="warehouse_id" name="warehouse_name" placeholder="- Выберите склад -" autocomplete="off" required>
                        <input type="hidden" name="warehouse_id" class="warehouse-id">
                    </div>
                </div>

                <div class="form-row">
                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="vendor_id">Поставщик</label>
                        <input class="form-control" type="text" id="vendor_id" name="vendor_name" placeholder="- Выберите поставщика -" autocomplete="off" required>
                        <input type="hidden" name="vendor_id" class="vendor-id">
                    </div>

                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="organization_id">Организация</label>
                        <input class="form-control" type="text" id="organization_id" name="organization_name" placeholder="- Выберите организацию -" autocomplete="off" required>
                        <input type="hidden" name="organization_id" class="organization-id">
                    </div>
                </div>

                <div class="form-row">
                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="responsible_id">Ответственный</label>
                        <input type="text" class="form-control" id="responsible_id" name="responsible_name" placeholder="- Выберите ответственного -" autocomplete="off" required>
                        <input type="hidden" name="responsible_id" class="responsible-id">
                    </div>
                </div>

                <h2 style="margin-top: 30px;"></h2>
                
                <div class="products-table-wrapper">
                <table class="table table-selectable card-table table-vcenter text-nowrap datatable" id="productsTable">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>ТОВАР</th>
                            <th>СЕРИЯ</th>
                            <th>ЦЕНА</th>
                            <th>КОЛ-ВО</th>
                            <th>ЕД</th>
                            <th>НДС</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <tr class="product-row">
                            <td>1</td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input type="text" name="products[0][product_name]" placeholder="Введите товар..." autocomplete="off">
                                    <input type="hidden" name="products[0][product_id]" class="product-id">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input type="text" name="products[0][seria_name]" placeholder="Введите серию..." autocomplete="off">
                                    <input type="hidden" name="products[0][seria_id]" class="seria-id">
                                </div>
                            </td>
                            <td><input type="text" name="products[0][price]" placeholder="0" autocomplete="off"></td>
                            <td><input type="text" name="products[0][quantity]" placeholder="0" autocomplete="off"></td>
                            <td>шт</td>
                            <td>
                                <select name="products[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <button type="button" class="btn" onclick="addRow()">+ строка</button>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="admin_page.php" class="btn">Отмена</a>
                </div>
            </form>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($nds_rates as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
        </script>
        <script src="js/add_product.js"></script>

<?php include 'footer.php'; ?>
