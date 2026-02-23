<?php

header('Content-Type: application/json');

if (!isset($_GET['id_tovara'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id_tovara']);
    exit;
}

$id_tovara = intval($_GET['id_tovara']);

$mysqli = require '../config/database.php';

$sql = "SELECT id, naimenovanie, poserijnyj_uchet FROM tovary_i_uslugi WHERE id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $id_tovara);
$stmt->execute();
$result = $stmt->get_result();
$tovar = $result->fetch_assoc();

if (!$tovar) {
    http_response_code(404);
    echo json_encode(['error' => 'tovar not found']);
    exit;
}

echo json_encode([
    'id' => $tovar['id'],
    'name' => $tovar['naimenovanie'],
    'poserijnyj_uchet' => $tovar['poserijnyj_uchet'] !== null ? (int)$tovar['poserijnyj_uchet'] : null
]);

?>
