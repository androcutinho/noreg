<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$mysqli = require '../config/database.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 1) {
    echo json_encode(['contractors' => []]);
    exit;
}

$search_term = '%' . $query . '%';

$sql = "SELECT 
    id,
    naimenovanie
FROM kontragenti
WHERE naimenovanie LIKE ?
ORDER BY naimenovanie ASC
LIMIT 10";

$stmt = $mysqli->stmt_init();
if (!$stmt->prepare($sql)) {
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param('s', $search_term);
$stmt->execute();
$result = $stmt->get_result();

$contractors = [];
while ($row = $result->fetch_assoc()) {
    $contractors[] = [
        'id' => $row['id'],
        'naimenovanie' => $row['naimenovanie']
    ];
}

$stmt->close();

echo json_encode(['contractors' => $contractors]);
?>
