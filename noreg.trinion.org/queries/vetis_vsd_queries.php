 <?php

function getDocumentsCount($mysqli, $enterprise_guid = '', $document_type = '') {
    $sql = "SELECT COUNT(*) as count FROM vetis_vsd WHERE (zakryt=0 OR zakryt IS NULL)";
    $types = [];
    $values = [];
    
    if (!empty($enterprise_guid)) {
        $sql .= " AND enterprise_guid = ?";
        $types[] = "s";
        $values[] = $enterprise_guid;
    }
    
    if (!empty($document_type)) {
        $sql .= " AND vetDType = ?";
        $types[] = "s";
        $values[] = $document_type;
    }
    
    if (!empty($types)) {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            die("SQL error: " . $mysqli->error);
        }
        
        $type_string = implode('', $types);
        $stmt->bind_param($type_string, ...$values);
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

function fetchAllDocuments($mysqli, $enterprise_guid = '', $limit = 8, $offset = 0, $document_type = '') {
    $sql = "SELECT 
        id,
        uuid,
        issueDate,
        vetDType,
        vetDStatus,
        lastUpdateDate,
        dateOfProduction,
        expiryDate,
        enterprise,
        consignee,
        id_tovary_i_uslugi,
        zakryt
    FROM vetis_vsd WHERE (zakryt=0 OR zakryt IS NULL)";
    
    $types = [];
    $values = [];
    
    if (!empty($enterprise_guid)) {
        $sql .= " AND enterprise_guid = ?";
        $types[] = "s";
        $values[] = $enterprise_guid;
    }
    
    if (!empty($document_type)) {
        $sql .= " AND vetDType = ?";
        $types[] = "s";
        $values[] = $document_type;
    }
    
    $sql .= " ORDER BY lastUpdateDate DESC LIMIT ? OFFSET ?";
    $types[] = "i";
    $types[] = "i";
    $values[] = $limit;
    $values[] = $offset;
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        die("SQL error: " . $mysqli->error);
    }
    
    $type_string = implode('', $types);
    $stmt->bind_param($type_string, ...$values);
    
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
