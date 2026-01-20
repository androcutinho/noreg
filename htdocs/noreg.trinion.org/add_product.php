<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

// Check if user has admin permissions
$sql = "SELECT user_role FROM users WHERE user_id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$logged_in_user = $result->fetch_assoc();

// If user doesn't exist or doesn't have admin role, deny access
if (!$logged_in_user || !$logged_in_user['user_role']) {
    die("Доступ запрещен. Вам нужны права администратора для доступа к этой странице.");
}

// Fetch warehouses
$warehouses = array();
$warehouse_sql = "SELECT id, naimenovanie FROM sklady ORDER BY naimenovanie";
$warehouse_result = $mysqli->query($warehouse_sql);
if ($warehouse_result) {
    while ($row = $warehouse_result->fetch_assoc()) {
        $warehouses[] = $row;
    }
} else {
    error_log("Warehouse query error: " . $mysqli->error);
}

// Fetch organizations
$organizations = array();
$org_sql = "SELECT id, naimenovanie FROM organizacii ORDER BY naimenovanie";
$org_result = $mysqli->query($org_sql);
if ($org_result) {
    while ($row = $org_result->fetch_assoc()) {
        $organizations[] = $row;
    }
} else {
    error_log("Organization query error: " . $mysqli->error);
}

// Fetch users (for responsible person)
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

// Fetch НДС rates
$nds_rates = array();
$nds_sql = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds";
$nds_result = $mysqli->query($nds_sql);
if ($nds_result) {
    while ($row = $nds_result->fetch_assoc()) {
        $nds_rates[] = $row;
    }
} else {
    error_log("НДС rates query error: " . $mysqli->error);
}

// Fetch vendors
$vendors = array();
$vendor_sql = "SELECT id, naimenovanie FROM postavshchiki ORDER BY naimenovanie";
$vendor_result = $mysqli->query($vendor_sql);
if ($vendor_result) {
    while ($row = $vendor_result->fetch_assoc()) {
        $vendors[] = $row;
    }
} else {
    error_log("Vendors query error: " . $mysqli->error);
}

// Fetch products
$products_list = array();
$products_sql = "SELECT id, naimenovanie FROM tovary_i_uslugi ORDER BY naimenovanie";
$products_result = $mysqli->query($products_sql);
if ($products_result) {
    while ($row = $products_result->fetch_assoc()) {
        $products_list[] = $row;
    }
} else {
    error_log("Products query error: " . $mysqli->error);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Validation
    if (empty($_POST['product_date'])) {
        $error = 'Требуется дата документа';
    } elseif (empty($_POST['warehouse_id'])) {
        $error = 'Требуется выбрать склад';
    } elseif (empty($_POST['organization_id'])) {
        $error = 'Требуется выбрать организацию';
    } elseif (empty($_POST['vendor_id'])) {
        $error = 'Требуется выбрать поставщика';
    } elseif (empty($_POST['responsible_id'])) {
        $error = 'Требуется выбрать ответственного';
    } elseif (!isset($_POST['products']) || empty($_POST['products'])) {
        $error = 'Требуется добавить хотя бы один товар';
    } else {
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Get IDs from the form
            $warehouse_id = intval($_POST['warehouse_id']);
            $organization_id = intval($_POST['organization_id']);
            $responsible_id = intval($_POST['responsible_id']);
            $vendor_id = intval($_POST['vendor_id']);
            
            // Insert main document into postupleniya_tovarov table
            $arrival_sql = "INSERT INTO postupleniya_tovarov(id_postavshchika, id_organizacii, id_sklada, id_otvetstvennyj, data_dokumenta) VALUES (?, ?, ?, ?, ?)";
            $arrival_stmt = $mysqli->stmt_init();
            
            if (!$arrival_stmt->prepare($arrival_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $arrival_stmt->bind_param(
                "iiiii",
                $vendor_id,
                $organization_id,
                $warehouse_id,
                $responsible_id,
                $_POST['product_date']
            );
            
            if (!$arrival_stmt->execute()) {
                throw new Exception("Ошибка при создании документа поступления: " . $mysqli->error);
            }
            
            $document_id = $mysqli->insert_id;
            
            // Process each product in the table
            $products_data = $_POST['products'];
            foreach ($products_data as $product) {
                if (empty($product['product_id']) || empty($product['price']) || empty($product['quantity']) || empty($product['nds_id'])) {
                    continue; // Skip empty rows
                }
                
                $goods_id = intval($product['product_id']);
                $nds_id = intval($product['nds_id']);
                $price = floatval($product['price']);
                $quantity = floatval($product['quantity']);
                
                // Insert line item into stroki_dokumentov table
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
            
            // Commit transaction
            $mysqli->commit();
            $success = true;
            $_POST = array(); // Clear form
            $error = 'Документ поступления успешно создан!';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $mysqli->rollback();
            $error = $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Добавить продукт</title>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
        
    </head>
    <body>
        <h1>Добавить продукт</h1>
        
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
                <h2>Информация о документе</h2>
                
                <div class="form-row">
                    <div>
                        <label for="product_date">Дата документа:</label>
                        <input type="date" id="product_date" name="product_date" required
                        value="<?= htmlspecialchars($_POST['product_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div>
                        <label for="warehouse_id">Склад:</label>
                        <select id="warehouse_id" name="warehouse_id" required>
                            <option value="">Выберите склад</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= $warehouse['id'] ?>" <?= ($_POST['warehouse_id'] ?? '') == $warehouse['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($warehouse['naimenovanie']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="organization_id">Организация:</label>
                        <select id="organization_id" name="organization_id" required>
                            <option value="">Выберите организацию</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?= $org['id'] ?>" <?= ($_POST['organization_id'] ?? '') == $org['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org['naimenovanie']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="vendor_id">Поставщик:</label>
                        <select id="vendor_id" name="vendor_id" required>
                            <option value="">Выберите поставщика</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" <?= ($_POST['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['naimenovanie']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div>
                        <label for="responsible_id">Ответственный:</label>
                        <select id="responsible_id" name="responsible_id" required>
                            <option value="">Выберите ответственного</option>
                            <?php foreach ($users_list as $user): ?>
                                <option value="<?= $user['user_id'] ?>" <?= ($_POST['responsible_id'] ?? '') == $user['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['user_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h2 style="margin-top: 30px;">Товары</h2>
                
                <table class="products-table" id="productsTable">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>ТОВАР</th>
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
                                <select name="products[0][product_id]">
                                    <option value="">-- Выберите товар --</option>
                                    <?php foreach ($products_list as $prod): ?>
                                        <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['naimenovanie']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="products[0][price]" placeholder="0"></td>
                            <td><input type="text" name="products[0][quantity]" placeholder="0"></td>
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

                <button type="button" class="add-row-btn" onclick="addRow()">+ row</button>

                <div style="margin-top: 20px;">
                    <button type="submit">Создать документ</button>
                    <a href="admin_page.php" class="btn">Отмена</a>
                </div>
            </form>
        </div>

        <script>
            function addRow() {
                const tbody = document.getElementById('productsBody');
                const rowCount = tbody.rows.length;
                const newRow = document.createElement('tr');
                newRow.className = 'product-row';
                
                let productOptions = '<option value="">-- Выберите товар --</option>';
                <?php foreach ($products_list as $prod): ?>
                    productOptions += '<option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['naimenovanie']) ?></option>';
                <?php endforeach; ?>
                
                let ndsOptions = '<option value="">--</option>';
                <?php foreach ($nds_rates as $nds): ?>
                    ndsOptions += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
                <?php endforeach; ?>
                
                newRow.innerHTML = `
                    <td>${rowCount + 1}</td>
                    <td><select name="products[${rowCount}][product_id]">${productOptions}</select></td>
                    <td><input type="text" name="products[${rowCount}][price]" placeholder="0"></td>
                    <td><input type="text" name="products[${rowCount}][quantity]" placeholder="0"></td>
                    <td>pcs</td>
                    <td><select name="products[${rowCount}][nds_id]">${ndsOptions}</select></td>
                    <td><button type="button" class="delete-row" onclick="deleteRow(this)">🗑</button></td>
                `;
                
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
                    const inputs = row.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        const name = input.name;
                        if (name) {
                            input.name = name.replace(/\[\d+\]/, `[${index}]`);
                        }
                    });
                });
            }
        </script>

    </body>
</html>
