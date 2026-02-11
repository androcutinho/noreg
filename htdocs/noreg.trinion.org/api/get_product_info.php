<?php

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product_id']);
    exit;
}

$product_id = intval($_GET['product_id']);

$mysqli = require '../config/database.php';

$sql = "SELECT id, naimenovanie, poserijnyj_uchet FROM tovary_i_uslugi WHERE id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

echo json_encode([
    'id' => $product['id'],
    'name' => $product['naimenovanie'],
    'poserijnyj_uchet' => $product['poserijnyj_uchet'] !== null ? (int)$product['poserijnyj_uchet'] : null
]);

?>
