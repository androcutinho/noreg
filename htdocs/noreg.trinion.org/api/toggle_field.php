<?php

session_start();


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['table_name']) || !isset($data['document_id']) || !isset($data['field_name']) || !isset($data['value'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$table_name = $data['table_name'];
$document_id = intval($data['document_id']);
$field_name = $data['field_name'];
$value = (bool)$data['value'];


$allowed_tables = [
    'postupleniya_tovarov',
    'scheta_na_oplatu_pokupatelyam',
    'zakazy_pokupatelei',
    'zakaz_postavschiku',
    'noreg_specifikacii_k_dogovoru',
    'zakazy_postavshchikam',
    'otgruzki_tovarov_pokupatelyam'
];

if (!in_array($table_name, $allowed_tables)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid table name']);
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/database_queries.php';

// Update the field
$result = updateDocumentField($mysqli, $table_name, $field_name, $document_id, $value);

header('Content-Type: application/json');
echo json_encode($result);
exit;

?>
