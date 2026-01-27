<?php

function fetchAllProducts($mysqli, $warehouse_id = null, $limit = 8, $offset = 0) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        ps.naimenovanie as vendor,
        u.user_name as responsible,
        SUM(sd.cena_postupleniya * sd.kolichestvo_postupleniya) as total_price
    FROM postupleniya_tovarov pt
    LEFT JOIN postavshchiki ps ON pt.id_postavshchika = ps.id
    LEFT JOIN users u ON pt.id_otvetstvennyj = u.user_id
    LEFT JOIN stroki_dokumentov sd ON pt.id = sd.id_dokumenta";
    
    if ($warehouse_id !== null) {
        $sql .= " WHERE pt.id_sklada = ?";
    }
    
    $sql .= " GROUP BY pt.id ORDER BY pt.id DESC LIMIT ? OFFSET ?";
    
    if ($warehouse_id !== null) {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("iii", $warehouse_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $products = array();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

function getProductsCount($mysqli, $warehouse_id = null) {
    $sql = "SELECT COUNT(DISTINCT pt.id) as total FROM postupleniya_tovarov pt";
    
    if ($warehouse_id !== null) {
        $sql .= " WHERE pt.id_sklada = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $warehouse_id);
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


function fetchAllWarehouses($mysqli) {
    $sql = "SELECT id, naimenovanie FROM sklady ORDER BY naimenovanie ASC";
    $result = $mysqli->query($sql);
    $warehouses = array();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
    
    return $warehouses;
}

?>
