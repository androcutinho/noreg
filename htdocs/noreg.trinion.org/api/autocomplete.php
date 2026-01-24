<?php

session_start();

if (!isset($mysqli)) {
    $mysqli = require __DIR__ . '/../config/database.php';
}

// Handle autocomplete search requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search']) && isset($_GET['table'])) {
    header('Content-Type: application/json');
    
    try {
        $search = $_GET['search'];
        $table = $_GET['table'];
        $col = $_GET['col'] ?? 'naimenovanie';
        $id_col = $_GET['id'] ?? 'id';
        
        // Validate table and column names (whitelist for safety)
        $allowed_tables = ['sklady', 'postavshchiki', 'organizacii', 'tovary_i_uslugi', 'serii', 'users'];
        $allowed_cols = ['naimenovanie', 'user_name', 'id', 'user_id', 'nomer'];
        
        if (!in_array($table, $allowed_tables)) {
            throw new Exception('Invalid table: ' . $table);
        }
        
        if (!in_array($col, $allowed_cols)) {
            throw new Exception('Invalid column: ' . $col);
        }
        
        if (!in_array($id_col, $allowed_cols)) {
            throw new Exception('Invalid id column: ' . $id_col);
        }
        
        // Escape identifiers using backticks
        $sql = "SELECT `{$id_col}` as id, `{$col}` as name FROM `{$table}` WHERE `{$col}` LIKE ?";
        
        // If searching serii table, exclude entries that already have an id_tovary_i_uslugi assigned
        if ($table === 'serii') {
            $sql .= " AND (id_tovary_i_uslugi IS NULL OR id_tovary_i_uslugi = 0)";
        }
        
        $sql .= " LIMIT 10";
        
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        
        $search_param = $search . "%";
        if (!$stmt->bind_param("s", $search_param)) {
            throw new Exception('Bind param failed: ' . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Get result failed: ' . $stmt->error);
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        // Return data as JSON
        echo json_encode($data ? $data : []);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

?>

