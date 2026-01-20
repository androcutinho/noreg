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

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Детали продукта</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
            body {
                max-width: 900px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #ccc;
                padding-bottom: 15px;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .back-btn {
                background-color: #6c757d;
                color: white;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
            }
            .back-btn:hover {
                background-color: #5a6268;
            }
            .btn {
                background-color: #0066cc;
                color: white;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                margin-right: 10px;
                display: inline-block;
            }
            .btn:hover {
                background-color: #0052a3;
            }
            .btn-edit {
                background-color: #28a745;
            }
            .btn-edit:hover {
                background-color: #218838;
            }
            .btn-delete {
                background-color: #dc3545;
            }
            .btn-delete:hover {
                background-color: #c82333;
            }
            .product-info {
                margin-bottom: 30px;
            }
            .info-row {
                display: grid;
                grid-template-columns: 200px 1fr;
                gap: 20px;
                margin-bottom: 15px;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            .info-label {
                font-weight: bold;
                color: #333;
            }
            .info-value {
                color: #666;
                word-break: break-word;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            table th, table td {
                border: 1px solid #ccc;
                padding: 10px;
                text-align: left;
            }
            table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .summary-section {
                background-color: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin-top: 20px;
            }
            .summary-row {
                display: grid;
                grid-template-columns: auto 1fr;
                gap: 20px;
                margin-bottom: 10px;
                text-align: right;
            }
            .summary-label {
                text-align: left;
                font-weight: bold;
            }
            .summary-value {
                text-align: right;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Продукт № <?= htmlspecialchars($product['product_id']) ?> - <?= htmlspecialchars($product['product_date']) ?></h1>
            <div>
                <a href="edit_product.php?product_id=<?= htmlspecialchars($product['product_id']) ?>" class="btn btn-edit">✎ </a>
                <button onclick="deleteProduct(<?= htmlspecialchars($product['product_id']) ?>)" class="btn btn-delete">🗑 </button>
                <a href="admin_page.php" class="back-btn">← Назад</a>
            </div>
        </div>

        <div class="product-info">
            <div class="info-row">
                <div class="info-label">Поставщик:</div>
                <div class="info-value"><?= htmlspecialchars($product['vendor']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Организация:</div>
                <div class="info-value"><?= htmlspecialchars($product['organization']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Склад:</div>
                <div class="info-value"><?= htmlspecialchars($product['warehouse']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Ответственный:</div>
                <div class="info-value"><?= htmlspecialchars($product['product_responsible']) ?></div>
            </div>
        </div>

        <h3>Продукты</h3>
        <table>
            <thead>
                <tr>
                    <th>№</th>
                    <th>Описание продукта</th>
                    <th>Количество</th>
                    <th>Единица</th>
                    <th>НДС</th>
                    <th>Цена</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                    <td><?= htmlspecialchars($product['product_amount']) ?></td>
                    <td>шт</td>
                    <td><?= htmlspecialchars($product['product_ndc']) ?></td>
                    <td><?= htmlspecialchars(number_format($product['product_price'], 2, '.', '')) ?></td>
                    <td><?= htmlspecialchars(number_format($product['product_price'] * $product['product_amount'], 2, '.', '')) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary-section">
            <div class="summary-row">
                <div class="summary-label">Всего товаров: 1, сумма:</div>
                <div class="summary-value"><?= htmlspecialchars(number_format($product['product_price'] * $product['product_amount'], 2, '.', '')) ?> руб.</div>
            </div>

            <div class="summary-row">
                <div class="summary-label">Сумма:</div>
                <div class="summary-value"><?= htmlspecialchars(number_format($product['product_price'] * $product['product_amount'], 2, '.', '')) ?></div>
            </div>

            <div class="summary-row">
                <div class="summary-label">НДС:</div>
                <div class="summary-value">0.00</div>
            </div>

            <div class="summary-row">
                <div class="summary-label"><strong>Итого:</strong></div>
                <div class="summary-value"><strong><?= htmlspecialchars(number_format($product['product_price'] * $product['product_amount'], 2, '.', '')) ?></strong></div>
            </div>
        </div>

        <div style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <div>
                    <p>Поставщик ___________________</p>
                    <p style="margin-top: 5px;">м.п.</p>
                </div>
                <div>
                    <p>Организация ___________________</p>
                    <p style="margin-top: 5px;">м.п.</p>
                </div>
            </div>
        </div>

        <script>
            function deleteProduct(productId) {
                if (confirm('Вы уверены, что хотите удалить этот продукт? Это действие нельзя отменить.')) {
                    window.location.href = 'delete_product.php?product_id=' + productId;
                }
            }
        </script>

    </body>
</html>
