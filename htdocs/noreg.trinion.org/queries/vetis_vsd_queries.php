 <?php

function getDocumentsCount($mysqli, $search_term = '') {
    $sql = "SELECT COUNT(*) as count FROM vetis_vsd";
    
    if (!empty($search_term)) {
        $search_term = '%' . $search_term . '%';
        $sql .= " WHERE uuid LIKE ? OR vetDType LIKE ? OR vetDStatus LIKE ? OR enterprise LIKE ? OR consignee LIKE ?";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            die("SQL error: " . $mysqli->error);
        }
        
        $stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'];
    }
    
    $result = $mysqli->query($sql);
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

function fetchAllDocuments($mysqli, $search_term = '', $limit = 8, $offset = 0) {
    $sql = "SELECT 
        uuid,
        issueDate,
        vetDType,
        vetDStatus,
        lastUpdateDate,
        dateOfProduction,
        expiryDate,
        enterprise,
        consignee,
        id_tovary_i_uslugi
    FROM vetis_vsd";
    
    if (!empty($search_term)) {
        $search_term = '%' . $search_term . '%';
        $sql .= " WHERE uuid LIKE ? OR vetDType LIKE ? OR vetDStatus LIKE ? OR enterprise LIKE ? OR consignee LIKE ?";
    }
    
    $sql .= " ORDER BY lastUpdateDate DESC LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        die("SQL error: " . $mysqli->error);
    }
    
    if (!empty($search_term)) {
        $stmt->bind_param("sssssii", $search_term, $search_term, $search_term, $search_term, $search_term, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    $stmt->close();
    
    return $documents;
}

function getDocumentTypeLabel($type_code) {
    $types = [
        'INCOMING' => 'Входящий ВСД',
        'OUTGOING' => 'Исходящий ВСД',
        'PRODUCTIVE' => 'Производственный ВСД',
        'RETURNABLE' => 'Возвратный ВСД',
        'TRANSPORT' => 'Транспортный ВСД',
    ];
    
    return isset($types[$type_code]) ? $types[$type_code] : $type_code;
}

function getDocumentStatusLabel($status_code) {
    $statuses = [
        'CONFIRMED' => 'Оформлен',
        'WITHDRAWN' => 'Аннулирован',
        'UTILIZED' => 'Погашен',
        'FINALIZED' => 'Закрыт',
    ];
    
    return isset($statuses[$status_code]) ? $statuses[$status_code] : $status_code;
}

function hasProductSeries($mysqli, $id_tovary_i_uslugi) {
    if (empty($id_tovary_i_uslugi)) {
        return false;
    }
    
    $sql = "SELECT COUNT(*) as count FROM serii WHERE id_tovary_i_uslugi = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $id_tovary_i_uslugi);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

?>
