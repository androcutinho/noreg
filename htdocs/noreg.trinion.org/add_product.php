<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

//функция для получения данных таблицы
function fetchTableData($mysqli, $table, $idCol, $nameCol, $orderBy = null, $extraCondition = null) {
    $sql = "SELECT {$idCol}, {$nameCol} FROM {$table}";
    if ($extraCondition) {
        $sql .= " WHERE {$extraCondition}";
    }
    if ($orderBy) {
        $sql .= " ORDER BY {$orderBy}";
    }
    
    $result = $mysqli->query($sql);
    $data = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        error_log("Query error for {$table}: " . $mysqli->error);
    }
    
    return $data;
}


function handleSearch($mysqli, $table, $searchCol, $returnCols, $limit = 10) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }
    
    $searchKey = 'search_' . str_replace('_', '', strtolower($table));
    if (!isset($_GET[$searchKey])) {
        return;
    }
    
    header('Content-Type: application/json');
    $search_term = $_GET[$searchKey];
    
    $sql = "SELECT {$returnCols} FROM {$table} WHERE {$searchCol} LIKE ? ORDER BY {$searchCol} LIMIT {$limit}";
    $stmt = $mysqli->stmt_init();
    
    if ($stmt->prepare($sql)) {
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
    exit;
}


handleSearch($mysqli, 'tovary_i_uslugi', 'naimenovanie', 'id, naimenovanie', 20);

// Handle series search - only show series not associated with any product
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['searchserii'])) {
    header('Content-Type: application/json');
    $search_term = $_GET['searchserii'];
    
    $sql = "SELECT id, nomer FROM serii WHERE nomer LIKE ? AND (id_tovary_i_uslugi IS NULL OR id_tovary_i_uslugi = 0) ORDER BY nomer LIMIT 20";
    $stmt = $mysqli->stmt_init();
    
    if ($stmt->prepare($sql)) {
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
    exit;
}


$warehouses = fetchTableData($mysqli, 'sklady', 'id', 'naimenovanie', 'naimenovanie');
$organizations = fetchTableData($mysqli, 'organizacii', 'id', 'naimenovanie', 'naimenovanie');
$nds_rates = fetchTableData($mysqli, 'stavki_nds', 'id', 'stavka_nds', 'stavka_nds');
$vendors = fetchTableData($mysqli, 'postavshchiki', 'id', 'naimenovanie', 'naimenovanie');
$products_list = fetchTableData($mysqli, 'tovary_i_uslugi', 'id', 'naimenovanie', 'naimenovanie');
$series_list = fetchTableData($mysqli, 'serii', 'id', 'nomer', 'nomer');

// Получить пользователей 
$users_list = array();
$users_sql = "SELECT user_id, user_name FROM users WHERE user_id != ? ORDER BY user_name";
$users_stmt = $mysqli->stmt_init();
if ($users_stmt->prepare($users_sql)) {
    $users_stmt->bind_param("i", $_SESSION['user_id']);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    while ($row = $users_result->fetch_assoc()) {
        $users_list[] = $row;
    }
} else {
    error_log("Users query error: " . $mysqli->error);
}

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
        
        $sql = "SELECT user_role FROM users WHERE user_id = ?";
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            die("Ошибка SQL: " . $mysqli->error);
        }
        
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $logged_in_user = $result->fetch_assoc();
        

        if (!$logged_in_user || !$logged_in_user['user_role']) {
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
            
            
            $arrival_sql = "INSERT INTO postupleniya_tovarov(id_postavshchika, id_organizacii, id_sklada, id_otvetstvennyj, data_dokumenta) VALUES (?, ?, ?, ?, ?)";
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
                    $update_seria_sql = "UPDATE serii SET id_tovary_i_uslugi = ? WHERE id = ?";
                    $update_seria_stmt = $mysqli->stmt_init();
                    
                    if (!$update_seria_stmt->prepare($update_seria_sql)) {
                        throw new Exception("SQL error al preparar UPDATE serii: " . $mysqli->error);
                    }
                    
                    $update_seria_stmt->bind_param("ii", $goods_id, $seria_id);
                    
                    if (!$update_seria_stmt->execute()) {
                        throw new Exception("Error al actualizar serii: " . $mysqli->error);
                    }
                }
                
                
                $line_sql = "INSERT INTO stroki_dokumentov(id_dokumenta, id_tovary_i_uslugi, id_stavka_nds, cena_postupleniya, kolichestvo_postupleniya) VALUES (?, ?, ?, ?, ?)";
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

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Добавить продукт</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
        <style>
            .products-table-wrapper {
                max-width: 90%;
                margin: 20px auto;
                overflow-x: auto;
            }
            .products-table-wrapper table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid #ddd;
            }
            .products-table-wrapper table thead th,
            .products-table-wrapper table tbody td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .products-table-wrapper table thead th {
                background-color: #f5f5f5;
                font-weight: 600;
            }
            .search-container {
                position: relative;
            }
            .form-container {
                max-width: 90%;
                margin: 20px auto;
            }
        </style>
    </head>
    <body>
        <h1>Новое поступление товара</h1>
        
        <?php if ($error): ?>
            <div class="<?php echo $success ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($warehouses) || empty($organizations) || empty($users_list) || empty($nds_rates) || empty($vendors) || empty($products_list)): ?>
            <div class="error">
                <strong>Внимание:</strong> 
                <?php if (empty($warehouses)): ?>
                    В таблице "Склады" нет данных.<br>
                <?php endif; ?>
                <?php if (empty($organizations)): ?>
                    В таблице "Организации" нет данных.<br>
                <?php endif; ?>
                <?php if (empty($nds_rates)): ?>
                    В таблице "Ставки НДС" нет данных.<br>
                <?php endif; ?>
                <?php if (empty($vendors)): ?>
                    В таблице "Поставщики" нет данных.<br>
                <?php endif; ?>
                <?php if (empty($products_list)): ?>
                    В таблице "Товары" нет данных.<br>
                <?php endif; ?>
                <?php if (empty($users_list)): ?>
                    Нет доступных пользователей.<br>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" id="documentForm">   
                <div class="form-row">
                    <div>
                        <label for="product_date">Дата и время документа:</label>
                        <input type="datetime-local" id="product_date" name="product_date" required
                        value="<?= htmlspecialchars($_POST['product_date'] ?? date('Y-m-d\TH:i')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="warehouse_id">Склад:</label>
                        <input type="text" list="warehouses-list" id="warehouse_id" name="warehouse_name" placeholder="Выберите склад" required>
                        <input type="hidden" name="warehouse_id" class="warehouse-id">
                    </div> 
                </div>

                <div class="form-row">
                    <div class="mb-3">
                        <label class="form-label" for="organization_id">Организация:</label>
                        <input type="text" list="organizations-list" id="organization_id" name="organization_name" placeholder="Выберите организацию" required>
                        <input type="hidden" name="organization_id" class="organization-id">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="vendor_id">Поставщик:</label>
                        <input type="text" list="vendors-list" id="vendor_id" name="vendor_name" placeholder="Выберите поставщика" required>
                        <input type="hidden" name="vendor_id" class="vendor-id">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="mb-3">
                        <label class="form-label" for="responsible_id">Ответственный:</label>
                        <input type="text" list="users-list" id="responsible_id" name="responsible_name" placeholder="Выберите ответственного" required>
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
                            <th>UNIT</th>
                            <th>НДС</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <tr class="product-row">
                            <td>1</td>
                            <td>
                                <div class="search-container">
                                    <input type="text" list="products-list" name="products[0][product_name]" placeholder="Введите товар...">
                                    <input type="hidden" name="products[0][product_id]" class="product-id">
                                </div>
                            </td>
                            <td>
                                <div class="search-container">
                                    <input type="text" list="series-list" name="products[0][seria_name]" placeholder="Введите серию...">
                                    <input type="hidden" name="products[0][seria_id]" class="seria-id">
                                </div>
                            </td>
                            <td><input type="text" name="products[0][price]" placeholder="0" autocomplete="off"></td>
                            <td><input type="text" name="products[0][quantity]" placeholder="0" autocomplete="off"></td>
                            <td>pcs</td>
                            <td>
                                <select name="products[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button type="button" class="delete-row" onclick="deleteRow(this)">🗑</button></td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <button type="button" class="add-row-btn" onclick="addRow()">+ row</button>

                <div style="margin-top: 20px;">
                    <button type="submit">Создать документ</button>
                    <a href="admin_page.php" class="btn">Отмена</a>
                </div>
            </form>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        <datalist id="warehouses-list">
            <?php foreach ($warehouses as $warehouse): ?>
                <option value="<?= htmlspecialchars($warehouse['naimenovanie']) ?>" data-id="<?= $warehouse['id'] ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="organizations-list">
            <?php foreach ($organizations as $org): ?>
                <option value="<?= htmlspecialchars($org['naimenovanie']) ?>" data-id="<?= $org['id'] ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="vendors-list">
            <?php foreach ($vendors as $vendor): ?>
                <option value="<?= htmlspecialchars($vendor['naimenovanie']) ?>" data-id="<?= $vendor['id'] ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="users-list">
            <?php foreach ($users_list as $user): ?>
                <option value="<?= htmlspecialchars($user['user_name']) ?>" data-id="<?= $user['user_id'] ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="products-list">
            <?php foreach ($products_list as $prod): ?>
                <option value="<?= htmlspecialchars($prod['naimenovanie']) ?>" data-id="<?= $prod['id'] ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="series-list">
            <?php foreach ($series_list as $seria): ?>
                <option value="<?= htmlspecialchars($seria['nomer']) ?>" data-id="<?= $seria['id'] ?>">
            <?php endforeach; ?>
        </datalist>
        
        <script>
            const fieldMappings = {
                'warehouse_name': 'warehouse_id',
                'organization_name': 'organization_id',
                'vendor_name': 'vendor_id',
                'responsible_name': 'responsible_id'
            };
            
            // Шаблон опций НДС
            let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($nds_rates as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
            
            
            document.addEventListener('change', function(e) {
                if (!e.target.list) return;
                
                const selectedOption = Array.from(e.target.list.options).find(opt => opt.value === e.target.value);
                if (!selectedOption || !selectedOption.dataset.id) {
                    // Validar si es un campo de serie y el valor no está vacío
                    if (e.target.name.includes('[seria_name]') && e.target.value.trim() !== '') {
                        alert('Выберите другой номер серии. "Введенный код уже связан с продуктом"');
                        e.target.value = '';
                        e.target.closest('.search-container').querySelector('.seria-id').value = '';
                    }
                    return;
                }
                
                const id = selectedOption.dataset.id;
                
                // Обработать основные поля формы
                if (fieldMappings[e.target.name]) {
                    document.querySelector(`input[name="${fieldMappings[e.target.name]}"]`).value = id;
                }
                // Обработать поля строки товара
                else if (e.target.name.includes('[product_name]')) {
                    e.target.closest('.search-container').querySelector('.product-id').value = id;
                }
                else if (e.target.name.includes('[seria_name]')) {
                    e.target.closest('.search-container').querySelector('.seria-id').value = id;
                }
            });

            function createRowTemplate(rowIndex) {
                return `
                    <td>${rowIndex + 1}</td>
                    <td>
                        <div class="search-container">
                            <input type="text" list="products-list" name="products[${rowIndex}][product_name]" placeholder="Введите товар...">
                            <input type="hidden" name="products[${rowIndex}][product_id]" class="product-id">
                        </div>
                    </td>
                    <td>
                        <div class="search-container">
                            <input type="text" list="series-list" name="products[${rowIndex}][seria_name]" placeholder="Введите серию...">
                            <input type="hidden" name="products[${rowIndex}][seria_id]" class="seria-id">
                        </div>
                    </td>
                    <td><input type="text" name="products[${rowIndex}][price]" placeholder="0" autocomplete="off"></td>
                    <td><input type="text" name="products[${rowIndex}][quantity]" placeholder="0" autocomplete="off"></td>
                    <td>pcs</td>
                    <td><select name="products[${rowIndex}][nds_id]">${ndsOptionsTemplate}</select></td>
                    <td><button type="button" class="delete-row" onclick="deleteRow(this)">🗑</button></td>
                `;
            }

            function addRow() {
                const tbody = document.getElementById('productsBody');
                const rowCount = tbody.rows.length;
                const newRow = document.createElement('tr');
                newRow.className = 'product-row';
                newRow.innerHTML = createRowTemplate(rowCount);
                tbody.appendChild(newRow);
                updateRowNumbers();
            }

            function deleteRow(button) {
                const tbody = document.getElementById('productsBody');
                if (tbody.rows.length > 1) {
                    button.closest('tr').remove();
                    updateRowNumbers();
                } else {
                    alert('Должна остаться хотя бы одна строка!');
                }
            }

            function updateRowNumbers() {
                const tbody = document.getElementById('productsBody');
                const rows = tbody.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    row.querySelector('td:first-child').textContent = index + 1;
                    row.querySelectorAll('input, select').forEach(input => {
                        if (input.name) {
                            input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
                        }
                    });
                });
            }
        </script>
    </body>
</html>
