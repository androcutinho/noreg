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
        $allowed_tables = ['sklady', 'kontragenti', 'organizacii', 'tovary_i_uslugi', 'serii', 'users', 'sotrudniki', 'raschetnye_scheta'];
        $allowed_cols = ['naimenovanie', 'user_name', 'id', 'user_id', 'nomer', 'fio'];
        
        if (!in_array($table, $allowed_tables)) {
            throw new Exception('Invalid table: ' . $table);
        }
        
        if (!in_array($col, $allowed_cols)) {
            throw new Exception('Invalid column: ' . $col);
        }
        
        if (!in_array($id_col, $allowed_cols)) {
            throw new Exception('Invalid id column: ' . $id_col);
        }
        
        
        if ($table === 'sotrudniki' && $col === 'fio') {
            $sql = "SELECT `id` as id, CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) as name FROM `sotrudniki` 
                    WHERE CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) LIKE ? OR familiya LIKE ? OR imya LIKE ? OR otchestvo LIKE ?";
            $search_param = '%' . $search . '%';
        } else {
            
            $sql = "SELECT `{$id_col}` as id, `{$col}` as name FROM `{$table}` WHERE `{$col}` LIKE ?";
            $search_param = $search . "%";
            
            
            if ($table === 'serii') {
                $sql .= " AND (id_tovary_i_uslugi IS NULL OR id_tovary_i_uslugi = 0)";
            }
            
            // Filter kontragenti by nash_kontragent flag (for organizations or vendors)
            if ($table === 'kontragenti' && isset($_GET['nash_kontragent'])) {
                $nash_val = $_GET['nash_kontragent'];
                if ($nash_val == 1) {
                    $sql .= " AND nash_kontragent = 1";
                } elseif ($nash_val == 0) {
                    $sql .= " AND nash_kontragent != 1";
                }
            }
        }
        
        $sql .= " LIMIT 10";
        
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        

        if ($table === 'sotrudniki' && $col === 'fio') {
            if (!$stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param)) {
                throw new Exception('Bind param failed: ' . $stmt->error);
            }
        } else {
            if (!$stmt->bind_param("s", $search_param)) {
                throw new Exception('Bind param failed: ' . $stmt->error);
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Get result failed: ' . $stmt->error);
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        
        echo json_encode($data ? $data : []);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

?>

