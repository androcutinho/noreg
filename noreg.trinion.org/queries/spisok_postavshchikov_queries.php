<?php

function getkontagentiCount($mysqli, $search = '') {
    $sql = "SELECT COUNT(*) as count FROM kontragenti";
    
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

function fetchAllkontagenti($mysqli, $search = '', $limit = 8, $offset = 0) {
    $sql = "SELECT id, naimenovanie FROM kontragenti";
    
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
    $kontragenti = [];
    
    while ($row = $result->fetch_assoc()) {
        $kontragenti[] = $row;
    }
    
    return $kontragenti;
}

function getPostavshchikById($mysqli, $id) {
    $sql = "SELECT id, naimenovanie, INN, KPP, yuridicheskij_adress, pochtovyj_adress, OGRN, polnoe_naimenovanie_organizacii, sokrashchyonnoe_naimenovanie, v_lice_dlya_documentov, postavshchik, pokupatel FROM kontragenti WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function insertPostavshchik($mysqli, $data) {
    $sql = "INSERT INTO kontragenti (naimenovanie, INN, KPP, yuridicheskij_adress, pochtovyj_adress, OGRN, polnoe_naimenovanie_organizacii, sokrashchyonnoe_naimenovanie, v_lice_dlya_documentov, postavshchik, pokupatel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('sssssssssii', 
        $data['naimenovanie'],
        $data['INN'],
        $data['KPP'],
        $data['yuridicheskij_adress'],
        $data['pochtovyj_adress'],
        $data['OGRN'],
        $data['polnoe_naimenovanie_organizacii'],
        $data['sokrashchyonnoe_naimenovanie'],
        $data['v_lice_dlya_documentov'],
        $data['postavshchik'],
        $data['pokupatel']
    );
    
    if (!$stmt->execute()) {
        return false;
    }
    
    return $mysqli->insert_id;
}

function updatePostavshchik($mysqli, $id, $data) {
    $sql = "UPDATE kontragenti SET naimenovanie = ?, INN = ?, KPP = ?, yuridicheskij_adress = ?, pochtovyj_adress = ?, OGRN = ?, polnoe_naimenovanie_organizacii = ?, sokrashchyonnoe_naimenovanie = ?, v_lice_dlya_documentov = ?, postavshchik = ?, pokupatel = ? WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('sssssssssiii', 
        $data['naimenovanie'],
        $data['INN'],
        $data['KPP'],
        $data['yuridicheskij_adress'],
        $data['pochtovyj_adress'],
        $data['OGRN'],
        $data['polnoe_naimenovanie_organizacii'],
        $data['sokrashchyonnoe_naimenovanie'],
        $data['v_lice_dlya_documentov'],
        $data['postavshchik'],
        $data['pokupatel'],
        $id
    );
    
    return $stmt->execute();
}

function postavshchikNameExists($mysqli, $name, $exclude_id = null) {
    $sql = "SELECT id FROM kontragenti WHERE naimenovanie = ?";
    
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
