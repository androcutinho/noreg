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

function getProductsWithSeriesCount($mysqli, $search = '') {
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

?>
