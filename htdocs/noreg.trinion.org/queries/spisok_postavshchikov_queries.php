<?php

function getPostavshchikiCount($mysqli, $search = '') {
    $sql = "SELECT COUNT(*) as count FROM postavshchiki";
    
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

function fetchAllPostavshchiki($mysqli, $search = '', $limit = 8, $offset = 0) {
    $sql = "SELECT id, naimenovanie FROM postavshchiki";
    
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
    $postavshchiki = [];
    
    while ($row = $result->fetch_assoc()) {
        $postavshchiki[] = $row;
    }
    
    return $postavshchiki;
}

function getPostavshchikById($mysqli, $id) {
    $sql = "SELECT id, naimenovanie FROM postavshchiki WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function insertPostavshchik($mysqli, $name) {
    $sql = "INSERT INTO postavshchiki (naimenovanie) VALUES (?)";
    
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

function updatePostavshchik($mysqli, $id, $name) {
    $sql = "UPDATE postavshchiki SET naimenovanie = ? WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('si', $name, $id);
    
    return $stmt->execute();
}

function postavshchikNameExists($mysqli, $name, $exclude_id = null) {
    $sql = "SELECT id FROM postavshchiki WHERE naimenovanie = ?";
    
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
