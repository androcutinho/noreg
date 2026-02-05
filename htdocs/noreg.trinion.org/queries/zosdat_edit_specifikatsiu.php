<?php

// Fetch all NDS rates
function getAllNdsRates($mysqli) {
    $nds_rates = [];
    $nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
    $nds_result = $mysqli->query($nds_query);
    if ($nds_result) {
        $nds_rates = $nds_result->fetch_all(MYSQLI_ASSOC);
    }
    return $nds_rates;
}

// Fetch all units
function getAllUnits($mysqli) {
    $units = [];
    $units_query = "SELECT id, naimenovanie FROM edinicy_izmereniya ORDER BY naimenovanie ASC";
    $units_result = $mysqli->query($units_query);
    if ($units_result) {
        $units = $units_result->fetch_all(MYSQLI_ASSOC);
    }
    return $units;
}

// Create new specification
function createSpecification($mysqli, $data) {
    $data_dogovora = $data['data_dogovora'];
    $nomer_specifikacii = $data['nomer_specifikacii'];
    $gorod = $data['gorod'];
    $nomer_dogovora = $data['nomer_dogovora'];
    $organization_id = intval($data['organization_id']);
    $kontragenti_id = intval($data['kontragenti_id']);
    $usloviya_otgruzki = $data['usloviya_otgruzki'];
    $usloviya_oplaty = $data['usloviya_oplaty'];
    $inye_usloviya = $data['inye_usloviya'];
    $sotrudniki_id = intval($data['sotrudniki_id']);
    $podpisant_postavshchika_dolzhnost = $data['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $data['podpisant_postavshchika_fio'];
    
    $stmt = $mysqli->prepare("
        INSERT INTO noreg_specifikacii_k_dogovoru 
        (data_dogovora, nomer_specifikacii, gorod, nomer_dogovora, id_organizacii, id_kontragenti, usloviya_otgruzki, usloviya_oplaty, inye_usloviya, id_sotrudniki, podpisant_postavshchika_dolzhnost, podpisant_postavshchika_fio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssssiisssiss',
        $data_dogovora,
        $nomer_specifikacii,
        $gorod,
        $nomer_dogovora,
        $organization_id,
        $kontragenti_id,
        $usloviya_otgruzki,
        $usloviya_oplaty,
        $inye_usloviya,
        $sotrudniki_id,
        $podpisant_postavshchika_dolzhnost,
        $podpisant_postavshchika_fio
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при создании спецификации: ' . $stmt->error];
    }
    
    $doc_id = $mysqli->insert_id;
    $stmt->close();
    
    return ['success' => true, 'id' => $doc_id];
}

// Create line item
function createLineItem($mysqli, $doc_id, $product) {
    $product_id = intval($product['product_id']);
    $unit_id = intval($product['unit_id']);
    $nds_id = intval($product['nds_id']);
    $quantity = floatval($product['quantity'] ?? 0);
    $price = floatval($product['price'] ?? 0);
    $nds_amount = floatval($product['summa_stavka'] ?? 0);
    $total_amount = floatval($product['summa'] ?? 0);
    $planiruemaya_data_postavki = $product['planiruemaya_data_postavki'] ?? null;
    $seria_id = null;
    
    $stmt = $mysqli->prepare("
        INSERT INTO stroki_dokumentov 
        (id_dokumenta, id_tovary_i_uslugi, id_serii, id_edinicy_izmereniya, id_stavka_nds, kolichestvo, cena, summa_nds, summa, planiruemaya_data_postavki)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iiiiidddds',
        $doc_id,
        $product_id,
        $seria_id,
        $unit_id,
        $nds_id,
        $quantity,
        $price,
        $nds_amount,
        $total_amount,
        $planiruemaya_data_postavki
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при добавлении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}

// Create all line items for specification
function createSpecificationLineItems($mysqli, $doc_id, $products) {
    foreach ($products as $product) {
        if (empty($product['product_id'])) continue;
        
        $result = createLineItem($mysqli, $doc_id, $product);
        if (!$result['success']) {
            return $result;
        }
    }
    
    return ['success' => true];
}

// Get specification by ID
function getSpecificationById($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    
    $stmt = $mysqli->prepare("
        SELECT id, data_dogovora, nomer_specifikacii, gorod, nomer_dogovora, 
               id_organizacii, id_kontragenti, usloviya_otgruzki, usloviya_oplaty, 
               inye_usloviya, id_sotrudniki, podpisant_postavshchika_dolzhnost, 
               podpisant_postavshchika_fio
        FROM noreg_specifikacii_k_dogovoru
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $spec_id);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при получении спецификации: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $spec = $result->fetch_assoc();
    $stmt->close();
    
    if (!$spec) {
        return ['success' => false, 'error' => 'Спецификация не найдена'];
    }
    
    return ['success' => true, 'data' => $spec];
}

// Get line items for specification
function getSpecificationLineItems($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    
    $stmt = $mysqli->prepare("
        SELECT id, id_tovary_i_uslugi, id_serii, id_edinicy_izmereniya, id_stavka_nds, 
               kolichestvo, cena, summa_nds, summa, planiruemaya_data_postavki
        FROM stroki_dokumentov
        WHERE id_dokumenta = ?
        ORDER BY id ASC
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $spec_id);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при получении товаров: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get product and series names for each item
    foreach ($items as &$item) {
        // Get product name
        $product_result = $mysqli->query("SELECT naimenovanie FROM tovary_i_uslugi WHERE id = " . intval($item['id_tovary_i_uslugi']));
        if ($product_result && $product = $product_result->fetch_assoc()) {
            $item['product_name'] = $product['naimenovanie'];
        } else {
            $item['product_name'] = '';
        }
        
        // Get series name
        if ($item['id_serii'] > 0) {
            $series_result = $mysqli->query("SELECT nomer FROM serii WHERE id = " . intval($item['id_serii']));
            if ($series_result && $series = $series_result->fetch_assoc()) {
                $item['seria_name'] = $series['nomer'];
            } else {
                $item['seria_name'] = '';
            }
        } else {
            $item['seria_name'] = '';
        }
        
        // Get unit name
        $unit_result = $mysqli->query("SELECT naimenovanie FROM edinicy_izmereniya WHERE id = " . intval($item['id_edinicy_izmereniya']));
        if ($unit_result && $unit = $unit_result->fetch_assoc()) {
            $item['unit_name'] = $unit['naimenovanie'];
        } else {
            $item['unit_name'] = '';
        }
    }
    
    return ['success' => true, 'data' => $items];
}

// Update specification header
function updateSpecification($mysqli, $spec_id, $data) {
    $spec_id = intval($spec_id);
    $data_dogovora = $data['data_dogovora'];
    $nomer_specifikacii = $data['nomer_specifikacii'];
    $gorod = $data['gorod'];
    $nomer_dogovora = $data['nomer_dogovora'];
    $organization_id = intval($data['organization_id']);
    $kontragenti_id = intval($data['kontragenti_id']);
    $usloviya_otgruzki = $data['usloviya_otgruzki'];
    $usloviya_oplaty = $data['usloviya_oplaty'];
    $inye_usloviya = $data['inye_usloviya'];
    $sotrudniki_id = intval($data['sotrudniki_id']);
    $podpisant_postavshchika_dolzhnost = $data['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $data['podpisant_postavshchika_fio'];
    
    $stmt = $mysqli->prepare("
        UPDATE noreg_specifikacii_k_dogovoru
        SET data_dogovora = ?, nomer_specifikacii = ?, gorod = ?, nomer_dogovora = ?, 
            id_organizacii = ?, id_kontragenti = ?, usloviya_otgruzki = ?, usloviya_oplaty = ?, 
            inye_usloviya = ?, id_sotrudniki = ?, podpisant_postavshchika_dolzhnost = ?, 
            podpisant_postavshchika_fio = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param(
        'ssssiisssissi',
        $data_dogovora,
        $nomer_specifikacii,
        $gorod,
        $nomer_dogovora,
        $organization_id,
        $kontragenti_id,
        $usloviya_otgruzki,
        $usloviya_oplaty,
        $inye_usloviya,
        $sotrudniki_id,
        $podpisant_postavshchika_dolzhnost,
        $podpisant_postavshchika_fio,
        $spec_id
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при обновлении спецификации: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}

// Delete line item
function deleteLineItem($mysqli, $line_item_id) {
    $line_item_id = intval($line_item_id);
    
    $stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id = ?");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $line_item_id);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при удалении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}

// Update line item
function updateLineItem($mysqli, $line_item_id, $product) {
    $line_item_id = intval($line_item_id);
    $product_id = intval($product['product_id']);
    $unit_id = intval($product['unit_id']);
    $nds_id = intval($product['nds_id']);
    $quantity = floatval($product['quantity'] ?? 0);
    $price = floatval($product['price'] ?? 0);
    $nds_amount = floatval($product['summa_stavka'] ?? 0);
    $total_amount = floatval($product['summa'] ?? 0);
    $planiruemaya_data_postavki = $product['planiruemaya_data_postavki'] ?? null;
    $seria_id = null;
    
    $stmt = $mysqli->prepare("
        UPDATE stroki_dokumentov
        SET id_tovary_i_uslugi = ?, id_serii = ?, id_edinicy_izmereniya = ?, id_stavka_nds = ?, 
            kolichestvo = ?, cena = ?, summa_nds = ?, summa = ?, planiruemaya_data_postavki = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param(
        'iiiiidddsi',
        $product_id,
        $seria_id,
        $unit_id,
        $nds_id,
        $quantity,
        $price,
        $nds_amount,
        $total_amount,
        $planiruemaya_data_postavki,
        $line_item_id
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при обновлении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}

// Update all line items for specification (replaces existing ones)
function updateSpecificationLineItems($mysqli, $spec_id, $products) {
    // First, delete all existing line items
    $delete_stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?");
    if (!$delete_stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса удаления: ' . $mysqli->error];
    }
    
    $spec_id_int = intval($spec_id);
    $delete_stmt->bind_param('i', $spec_id_int);
    
    if (!$delete_stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при удалении товаров: ' . $delete_stmt->error];
    }
    
    $delete_stmt->close();
    
    // Then insert the new line items
    foreach ($products as $product) {
        if (empty($product['product_id'])) continue;
        
        $result = createLineItem($mysqli, $spec_id, $product);
        if (!$result['success']) {
            return $result;
        }
    }
    
    return ['success' => true];
}

// Delete specification and all its line items
function deleteSpecification($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Delete all line items first
        $delete_items_stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?");
        if (!$delete_items_stmt) {
            throw new Exception('Ошибка при подготовке запроса удаления товаров: ' . $mysqli->error);
        }
        $delete_items_stmt->bind_param('i', $spec_id);
        if (!$delete_items_stmt->execute()) {
            throw new Exception('Ошибка при удалении товаров: ' . $delete_items_stmt->error);
        }
        $delete_items_stmt->close();
        
        // Then delete the specification header
        $delete_spec_stmt = $mysqli->prepare("DELETE FROM noreg_specifikacii_k_dogovoru WHERE id = ?");
        if (!$delete_spec_stmt) {
            throw new Exception('Ошибка при подготовке запроса удаления спецификации: ' . $mysqli->error);
        }
        $delete_spec_stmt->bind_param('i', $spec_id);
        if (!$delete_spec_stmt->execute()) {
            throw new Exception('Ошибка при удалении спецификации: ' . $delete_spec_stmt->error);
        }
        $delete_spec_stmt->close();
        
        // Commit transaction
        $mysqli->commit();
        
        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $mysqli->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>
