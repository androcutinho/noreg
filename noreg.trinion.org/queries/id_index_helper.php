<?php

function getNextIdIndex($mysqli) {
    
    $query = "SELECT COALESCE(MAX(id_table_row), 0) + 1 as next_index FROM `index`";
    $result = $mysqli->query($query);
    if (!$result) {
        throw new Exception('Ошибка при получении следующего id_index: ' . $mysqli->error);
    }
    $row = $result->fetch_assoc();
    $next_index = intval($row['next_index']);
    
    $insert_query = "INSERT INTO `index` (id_table_row) VALUES (?)";
    $stmt = $mysqli->prepare($insert_query);
    if (!$stmt) {
        throw new Exception('Ошибка при подготовке запроса индекса: ' . $mysqli->error);
    }
    
    $stmt->bind_param('i', $next_index);
    if (!$stmt->execute()) {
        throw new Exception('Ошибка при добавлении в таблицу индексов: ' . $stmt->error);
    }
    $stmt->close();
    
    return $next_index;
}


function getTovaryZakazasostatkom($mysqli, $zakaz, $original_items, $document_type = 'shipment') {
    $result_items = [];
    
    if (empty($zakaz['id_scheta_na_oplatu_pokupatelyam'])) {
        foreach ($original_items as $item) {
            $item['kolichestvo_ostatka'] = floatval($item['quantity']);
            $item['summa_ostatka'] = floatval($item['total_amount']);
            $result_items[] = $item;
        }
        return $result_items;
    }
    
    $related_docs_json = $zakaz['id_scheta_na_oplatu_pokupatelyam'];
    $related_docs = json_decode($related_docs_json, true);
    
    if (!is_array($related_docs)) {
        $related_docs = !empty($related_docs_json) ? [$related_docs_json] : [];
    } elseif (!empty($related_docs) && is_array($related_docs[0]) && !isset($related_docs[0]['id'])) {
        $related_docs = $related_docs[0];
    }
    
    $doc_ids = [];
    $doc_types = [];
    foreach ($related_docs as $doc) {
        if (is_array($doc) && isset($doc['id'])) {
            $doc_ids[] = intval($doc['id']);
            $doc_types[] = $doc['type'] ?? 'unknown';
        } elseif (is_numeric($doc)) {
            $doc_ids[] = intval($doc);
            $doc_types[] = 'unknown';
        }
    }
    
    $filtered_doc_ids = [];
    $filtered_doc_types = [];
    foreach ($doc_ids as $index => $doc_id) {
        if (isset($doc_types[$index]) && $doc_types[$index] === $document_type) {
            $filtered_doc_ids[] = $doc_id;
            $filtered_doc_types[] = $doc_types[$index];
        }
    }
    
    if (!empty($filtered_doc_ids)) {
        $doc_ids = [reset($filtered_doc_ids)];
        $doc_types = [reset($filtered_doc_types)];
    } else {
        $doc_ids = [];
        $doc_types = [];
    }
    
    if (empty($doc_ids)) {
        foreach ($original_items as $item) {
            $item['kolichestvo_ostatka'] = floatval($item['quantity']);
            $item['summa_ostatka'] = floatval($item['total_amount']);
            $result_items[] = $item;
        }
        return $result_items;
    }
    
    $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
    $id_indexes = [];
    
    if (!empty($doc_types[0])) {
        if ($doc_types[0] === 'shipment') {
            $query_ship = "SELECT id_index FROM otgruzki_tovarov_pokupatelyam WHERE id IN ($placeholders)";
            $stmt_ship = $mysqli->prepare($query_ship);
            if ($stmt_ship) {
                $types = str_repeat('i', count($doc_ids));
                $stmt_ship->bind_param($types, ...$doc_ids);
                if ($stmt_ship->execute()) {
                    $res = $stmt_ship->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $id_indexes[] = intval($row['id_index']);
                    }
                }
                $stmt_ship->close();
            }
        } elseif ($doc_types[0] === 'invoice') {
            $query_inv = "SELECT id_index FROM scheta_na_oplatu WHERE id IN ($placeholders)";
            $stmt_inv = $mysqli->prepare($query_inv);
            if ($stmt_inv) {
                $types = str_repeat('i', count($doc_ids));
                $stmt_inv->bind_param($types, ...$doc_ids);
                if ($stmt_inv->execute()) {
                    $res = $stmt_inv->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $id_indexes[] = intval($row['id_index']);
                    }
                }
                $stmt_inv->close();
            }
        }
    }
    
    if (empty($id_indexes)) {
        foreach ($original_items as $item) {
            $item['kolichestvo_ostatka'] = floatval($item['quantity']);
            $item['summa_ostatka'] = floatval($item['total_amount']);
            $result_items[] = $item;
        }
        return $result_items;
    }
    
    $index_placeholders = implode(',', array_fill(0, count($id_indexes), '?'));
    $query = "
        SELECT 
            sd.id_index,
            sd.id_tovary_i_uslugi,
            SUM(sd.kolichestvo) as used_quantity,
            SUM(sd.summa) as used_summa
        FROM stroki_dokumentov sd
        WHERE sd.id_index IN ($index_placeholders)
        GROUP BY sd.id_index, sd.id_tovary_i_uslugi
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
    }
    
    $types = str_repeat('i', count($id_indexes));
    $stmt->bind_param($types, ...$id_indexes);
    if (!$stmt->execute()) {
        throw new Exception('Query execute error: ' . $stmt->error);
    }
    $used_result = $stmt->get_result();
    $stmt->close();
    
    $used_by_product = [];
    while ($row = $used_result->fetch_assoc()) {
        $product_id = $row['id_tovary_i_uslugi'];
        
        if (!isset($used_by_product[$product_id])) {
            $used_by_product[$product_id] = [
                'quantity' => 0,
                'summa' => 0
            ];
        }
        
        $used_by_product[$product_id]['quantity'] += floatval($row['used_quantity']);
        $used_by_product[$product_id]['summa'] += floatval($row['used_summa']);
    }
    
    foreach ($original_items as $item) {
        $product_id = $item['id_tovary_i_uslugi'];
        $used_quantity = isset($used_by_product[$product_id]) ? floatval($used_by_product[$product_id]['quantity']) : 0;
        $used_summa = isset($used_by_product[$product_id]) ? floatval($used_by_product[$product_id]['summa']) : 0;
        
        $item['kolichestvo_ostatka'] = max(0, floatval($item['quantity']) - $used_quantity);
        $item['summa_ostatka'] = max(0, floatval($item['total_amount']) - $used_summa);
        
        $result_items[] = $item;
    }
    
    return $result_items;
}

?>
