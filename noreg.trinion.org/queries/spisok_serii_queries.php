<?php

function fetchSeriesByProductId($mysqli, $product_id) {
    $sql = "SELECT 
        s.id,
        s.nomer,
        s.data_izgotovleniya,
        s.srok_godnosti,
        ti.naimenovanie as product_name
    FROM serii s
    LEFT JOIN tovary_i_uslugi ti ON s.id_tovary_i_uslugi = ti.id
    WHERE s.id_tovary_i_uslugi = ?
    ORDER BY s.nomer ASC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $series = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $series[] = $row;
        }
    }
    
    return $series;
}

function updateSeriesData($mysqli, $seria_id, $data_izgotovleniya, $srok_godnosti) {
    $sql = "UPDATE serii 
            SET data_izgotovleniya = ?,
                srok_godnosti = ?
            WHERE id = ?";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        return array('success' => false, 'error' => 'Ошибка подготовки запроса: ' . $mysqli->error);
    }
    
    // Handle NULL values for dates
    $data_izgotovleniya = !empty($data_izgotovleniya) ? $data_izgotovleniya : null;
    $srok_godnosti = !empty($srok_godnosti) ? $srok_godnosti : null;
    
    $stmt->bind_param("ssi", $data_izgotovleniya, $srok_godnosti, $seria_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        return array('success' => true);
    } else {
        return array('success' => false, 'error' => 'Ошибка при обновлении данных');
    }
}

?>
