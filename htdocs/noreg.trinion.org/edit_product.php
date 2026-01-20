<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

// Check if product_id is provided
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("ID продукта не предоставлен.");
}

$product_id = intval($_GET['product_id']);

// Fetch product details
$sql = "SELECT * FROM product WHERE product_id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    die("Продукт не найден.");
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Validation
    if (empty($_POST['product_name'])) {
        $error = 'Требуется название продукта';
    } elseif (empty($_POST['product_price']) || $_POST['product_price'] < 0) {
        $error = 'Требуется действительная цена продукта';
    } elseif (empty($_POST['product_amount']) || $_POST['product_amount'] < 0) {
        $error = 'Требуется действительное количество продукта';
    } elseif (empty($_POST['product_date'])) {
        $error = 'Требуется дата продукта';
    } elseif (empty($_POST['warehouse'])) {
        $error = 'Требуется склад';
    } elseif (empty($_POST['organization'])) {
        $error = 'Требуется организация';
    } elseif (empty($_POST['vendor'])) {
        $error = 'Требуется поставщик';
    } else {
        // Update product
        $sql = "UPDATE product SET product_name = ?, product_price = ?, product_amount = ?, product_ndc = ?, product_date = ?, warehouse = ?, organization = ?, vendor = ?, product_responsible = ? WHERE product_id = ?";
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            $error = "SQL error: " . $mysqli->error;
        } else {
            $product_ndc = isset($_POST['product_ndc']) ? $_POST['product_ndc'] : '0%';
            $product_responsible = !empty($_POST['product_responsible']) ? $_POST['product_responsible'] : '';
            $product_price = (float) $_POST['product_price'];
            $product_amount = (int) $_POST['product_amount'];
            
            $stmt->bind_param(
                "sdissssi",
                $_POST['product_name'],
                $product_price,
                $product_amount,
                $product_ndc,
                $_POST['product_date'],
                $_POST['warehouse'],
                $_POST['organization'],
                $_POST['vendor'],
                $product_responsible,
                $product_id
            );
            
            if ($stmt->execute()) {
                $success = true;
                $error = 'Продукт успешно обновлен!';
                // Refresh product data
                $stmt = $mysqli->stmt_init();
                $stmt->prepare("SELECT * FROM product WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
            } else {
                $error = "Ошибка при обновлении продукта: " . $mysqli->error;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Редактировать продукт</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
            body {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .error {
                color: red;
                margin-bottom: 20px;
                padding: 10px;
                background-color: #ffe6e6;
                border-radius: 4px;
            }
            .success {
                color: green;
                margin-bottom: 20px;
                padding: 10px;
                background-color: #e6ffe6;
                border-radius: 4px;
            }
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .form-row.full {
                grid-template-columns: 1fr;
            }
            button {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            button:hover {
                background-color: #0056b3;
            }
            a.btn {
                display: inline-block;
                background-color: #6c757d;
                color: white;
                padding: 10px 20px;
                margin-left: 10px;
                border-radius: 4px;
                text-decoration: none;
            }
            a.btn:hover {
                background-color: #5a6268;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Редактировать продукт #<?= htmlspecialchars($product['product_id']) ?></h1>
            <a href="view_product_details.php?product_id=<?= htmlspecialchars($product['product_id']) ?>" class="btn">← Назад</a>
        </div>
        
        <?php if ($error): ?>
            <div class="<?php echo $success ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div>
                    <label for="product_name">Название продукта:</label>
                    <input type="text" id="product_name" name="product_name" required
                    value="<?= htmlspecialchars($_POST['product_name'] ?? $product['product_name']) ?>">
                </div>

                <div>
                    <label for="product_price">Цена продукта:</label>
                    <input type="number" id="product_price" name="product_price" step="0.01" required
                    value="<?= htmlspecialchars($_POST['product_price'] ?? $product['product_price']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="product_amount">Количество:</label>
                    <input type="number" id="product_amount" name="product_amount" step="0.01" required
                    value="<?= htmlspecialchars($_POST['product_amount'] ?? $product['product_amount']) ?>">
                </div>

                <div>
                    <label for="product_ndc">НДС:</label>
                    <select id="product_ndc" name="product_ndc">
                        <option value="0%" <?= ($_POST['product_ndc'] ?? $product['product_ndc']) == '0%' ? 'selected' : '' ?>>0%</option>
                        <option value="10%" <?= ($_POST['product_ndc'] ?? $product['product_ndc']) == '10%' ? 'selected' : '' ?>>10%</option>
                        <option value="20%" <?= ($_POST['product_ndc'] ?? $product['product_ndc']) == '20%' ? 'selected' : '' ?>>20%</option>
                        <option value="without VAT" <?= ($_POST['product_ndc'] ?? $product['product_ndc']) == 'without VAT' ? 'selected' : '' ?>>Без НДС</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="product_date">Дата:</label>
                    <input type="date" id="product_date" name="product_date" required
                    value="<?= htmlspecialchars($_POST['product_date'] ?? $product['product_date']) ?>">
                </div>

                <div>
                    <label for="warehouse">Склад:</label>
                    <input type="text" id="warehouse" name="warehouse" required
                    value="<?= htmlspecialchars($_POST['warehouse'] ?? $product['warehouse']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="organization">Организация:</label>
                    <input type="text" id="organization" name="organization" required
                    value="<?= htmlspecialchars($_POST['organization'] ?? $product['organization']) ?>">
                </div>

                <div>
                    <label for="vendor">Поставщик:</label>
                    <input type="text" id="vendor" name="vendor" required
                    value="<?= htmlspecialchars($_POST['vendor'] ?? $product['vendor']) ?>">
                </div>
            </div>

            <div class="form-row full">
                <div>
                    <label for="product_responsible">Ответственный:</label>
                    <input type="text" id="product_responsible" name="product_responsible"
                    value="<?= htmlspecialchars($_POST['product_responsible'] ?? $product['product_responsible']) ?>">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit">Обновить продукт</button>
                <a href="view_product_details.php?product_id=<?= htmlspecialchars($product['product_id']) ?>" class="btn">Отмена</a>
            </div>
        </form>

    </body>
</html>
