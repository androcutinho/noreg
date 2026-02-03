<?php

function getWarehousesCount($mysqli, $search = '') {
    $sql = "SELECT COUNT(*) as count FROM sklady";
    
    if (!empty($search)) {
        $sql .= " WHERE naimenovanie LIKE ?";
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return 0;
    }
    
    if (!empty($search)) {
        $search_term = '%' . $search . '%';
        $stmt->bind_param('s', $search_term);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

function fetchAllWarehouses($mysqli, $search = '', $limit = 8, $offset = 0) {
    $sql = "SELECT id, naimenovanie FROM sklady";
    
    if (!empty($search)) {
        $sql .= " WHERE naimenovanie LIKE ?";
    }
    
    $sql .= " ORDER BY naimenovanie ASC LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return [];
    }
    
    if (!empty($search)) {
        $search_term = '%' . $search . '%';
        $stmt->bind_param('sii', $search_term, $limit, $offset);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $warehouses = [];
    
    while ($row = $result->fetch_assoc()) {
        $warehouses[] = $row;
    }
    
    return $warehouses;
}

function getSkladById($mysqli, $id) {
    $sql = "SELECT id, naimenovanie FROM sklady WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function insertSklad($mysqli, $name) {
    $sql = "INSERT INTO sklady (naimenovanie) VALUES (?)";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('s', $name);
    
    if (!$stmt->execute()) {
        return false;
    }
    
    return $mysqli->insert_id;
}

function updateSklad($mysqli, $id, $name) {
    $sql = "UPDATE sklady SET naimenovanie = ? WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('si', $name, $id);
    
    return $stmt->execute();
}

function skladNameExists($mysqli, $name, $exclude_id = null) {
    $sql = "SELECT id FROM sklady WHERE naimenovanie = ?";
    
    if ($exclude_id !== null) {
        $sql .= " AND id != ?";
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    if ($exclude_id !== null) {
        $stmt->bind_param('si', $name, $exclude_id);
    } else {
        $stmt->bind_param('s', $name);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() !== null;
}

?>
