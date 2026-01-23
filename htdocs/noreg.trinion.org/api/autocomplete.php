<?php

session_start();

$mysqli = require __DIR__ . '/../database.php';

// Handle autocomplete search requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search']) && isset($_GET['table'])) {
    header('Content-Type: application/json');
    
    $search = $_GET['search'];
    $table = $_GET['table'];
    $col = $_GET['col'] ?? 'naimenovanie';
    $id_col = $_GET['id'] ?? 'id';
    
    // Validate table and column names (whitelist for safety)
    $allowed_tables = ['sklady', 'postavshchiki', 'organizacii', 'tovary_i_uslugi', 'serii', 'users'];
    $allowed_cols = ['naimenovanie', 'user_name', 'id', 'user_id'];
    
    if (!in_array($table, $allowed_tables)) {
        echo json_encode(['error' => 'Invalid table: ' . $table]);
        exit;
    }
    
    if (!in_array($col, $allowed_cols)) {
        echo json_encode(['error' => 'Invalid column: ' . $col]);
        exit;
    }
    
    if (!in_array($id_col, $allowed_cols)) {
        echo json_encode(['error' => 'Invalid id column: ' . $id_col]);
        exit;
    }
    
    // Escape identifiers using backticks
    $sql = "SELECT `{$id_col}` as id, `{$col}` as name FROM `{$table}` WHERE `{$col}` LIKE ? LIMIT 10";
    
    $stmt = $mysqli->stmt_init();
    
    if (!$stmt->prepare($sql)) {
        echo json_encode(['error' => 'Prepare failed: ' . $mysqli->error]);
        exit;
    }
    
    $search_param = $search . "%";
    $stmt->bind_param("s", $search_param);
    
    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        echo json_encode(['error' => 'Get result failed: ' . $stmt->error]);
        exit;
    }
    
    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data);
    exit;
}

?>

