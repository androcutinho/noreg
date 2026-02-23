<?php
header('Content-Type: application/json');

require_once 'db_connection.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $query = "SELECT id, naimenovanie FROM kontragenti WHERE naimenovanie LIKE ? LIMIT 10";
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $searchTerm = '%' . $q . '%';
    $stmt->bind_param('s', $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'naimenovanie' => $row['naimenovanie']
        ];
    }
    
    echo json_encode($data);
    $stmt->close();
} catch (Exception $e) {
    error_log("Vendors search error: " . $e->getMessage());
    echo json_encode([]);
}

$mysqli->close();
?>
