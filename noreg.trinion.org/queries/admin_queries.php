<?php

function fetchAllProducts($mysqli, $warehouse_id = null, $limit = 8, $offset = 0) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        ps.naimenovanie as vendor,
        CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) as responsible,
        SUM(sd.cena * sd.kolichestvo) as total_price
    FROM postupleniya_tovarov pt
    LEFT JOIN kontragenti ps ON pt.id_kontragenti_postavshik = ps.id
    LEFT JOIN sotrudniki s ON pt.id_otvetstvennyj = s.id
    LEFT JOIN stroki_dokumentov sd ON pt.id = sd.id_dokumenta";
    
    if ($warehouse_id !== null) {
        $sql .= " WHERE pt.id_sklada = ? AND (pt.zakryt = 0 OR pt.zakryt IS NULL)";
    } else {
        $sql .= " WHERE (pt.zakryt = 0 OR pt.zakryt IS NULL)";
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
        $sql .= " WHERE pt.id_sklada = ? AND (pt.zakryt = 0 OR pt.zakryt IS NULL)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $warehouse_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " WHERE (pt.zakryt = 0 OR pt.zakryt IS NULL)";
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
