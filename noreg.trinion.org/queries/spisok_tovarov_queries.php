<?php

function fetchAllProductsWithSeries($mysqli, $search = '', $limit = 8, $offset = 0) {
    $sql = "SELECT 
        ti.id,
        ti.naimenovanie as product_name
    FROM tovary_i_uslugi ti
    WHERE 1=1";
    
    if (!empty($search)) {
        $sql .= " AND ti.naimenovanie LIKE ?";
    }
    
    $sql .= " GROUP BY ti.id
    ORDER BY ti.id ASC
    LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bind_param("sii", $search_param, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;

}

function getProductsCount($mysqli, $search = '') {
    $sql = "SELECT COUNT(DISTINCT ti.id) as total FROM tovary_i_uslugi ti
    WHERE 1=1";
    
    if (!empty($search)) {
        $sql .= " AND ti.naimenovanie LIKE ?";
        $stmt = $mysqli->prepare($sql);
        $search_param = '%' . $search . '%';
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $mysqli->query($sql);
    }
    
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    
    return 0;
}

function getTovarById($mysqli, $id) {
    $sql = "SELECT id, naimenovanie,poserijnyj_uchet FROM  tovary_i_uslugi  WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}
function insertTovar($mysqli, $name, $poserijnyj_uchet) {
    $sql = "INSERT INTO  tovary_i_uslugi (naimenovanie, poserijnyj_uchet) VALUES (?, ?)";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('si', $name, $poserijnyj_uchet);
    
    if (!$stmt->execute()) {
        return false;
    }
    
    return $mysqli->insert_id;
}

function updateTovar($mysqli, $id, $name, $poserijnyj_uchet) {
     $sql = "UPDATE  tovary_i_uslugi  SET naimenovanie = ?, poserijnyj_uchet = ? WHERE id = ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param('sii', $name, $poserijnyj_uchet, $id);
    
    return $stmt->execute();
}

function TovarNameExists($mysqli, $name, $exclude_id = null) {
    $sql = "SELECT id FROM  tovary_i_uslugi  WHERE naimenovanie = ?";
    
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
