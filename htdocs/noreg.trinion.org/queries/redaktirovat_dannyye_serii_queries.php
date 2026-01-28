<?php

function fetchSeriesById($mysqli, $seria_id) {
    $sql = "SELECT 
        s.id,
        s.nomer,
        s.data_izgotovleniya,
        s.srok_godnosti,
        s.id_tovary_i_uslugi,
        ti.naimenovanie as product_name
    FROM serii s
    LEFT JOIN tovary_i_uslugi ti ON s.id_tovary_i_uslugi = ti.id
    WHERE s.id = ?";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $seria_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function getOrCreateProduct($mysqli, $product_name) {
    // First check if product exists
    $sql = "SELECT id FROM tovary_i_uslugi WHERE naimenovanie = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }
    
    // Create new product if it doesn't exist
    $sql = "INSERT INTO tovary_i_uslugi (naimenovanie) VALUES (?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    
    return $mysqli->insert_id;
}

function updateSeriesWithProduct($mysqli, $seria_id, $product_id, $data_izgotovleniya, $srok_godnosti) {
    $sql = "UPDATE serii 
            SET id_tovary_i_uslugi = ?,
                data_izgotovleniya = ?,
                srok_godnosti = ?
            WHERE id = ?";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        return array('success' => false, 'error' => 'Ошибка подготовки запроса: ' . $mysqli->error);
    }
    
    // Handle NULL values for dates
    $data_izgotovleniya = !empty($data_izgotovleniya) ? $data_izgotovleniya : null;
    $srok_godnosti = !empty($srok_godnosti) ? $srok_godnosti : null;
    
    $stmt->bind_param("issi", $product_id, $data_izgotovleniya, $srok_godnosti, $seria_id);
    $stmt->execute();
    
    if ($stmt->affected_rows >= 0) {
        return array('success' => true);
    } else {
        return array('success' => false, 'error' => 'Ошибка при обновлении данных');
    }
}

?>
