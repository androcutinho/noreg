<?php

// Fetch existing specification data for editing
function fetchSpecificationData($mysqli, $spec_id) {
    $stmt = $mysqli->prepare("
        SELECT 
            nsk.id,
            nsk.data_dogovora,
            nsk.nomer_specifikacii,
            nsk.gorod,
            nsk.id_organizacii,
            nsk.id_kontragenti,
            nsk.id_sotrudniki,
            nsk.planiruemaya_data_postavki,
            nsk.usloviya_otgruzki,
            nsk.usloviya_oplaty,
            nsk.inye_usloviya,
            nsk.podpisant_postavshchika_dolzhnost,
            nsk.podpisant_postavshchika_fio,
            org.naimenovanie AS org_name,
            kon.naimenovanie AS kon_name,
            CONCAT(sr.familiya, ' ', sr.imya, ' ', sr.otchestvo) AS sotrudnik_name
        FROM noreg_specifikacii_k_dogovoru nsk
        LEFT JOIN organizacii org ON nsk.id_organizacii = org.id
        LEFT JOIN kontragenti kon ON nsk.id_kontragenti = kon.id
        LEFT JOIN sotrudniki sr ON nsk.id_sotrudniki = sr.id
        WHERE nsk.id = ?
    ");
    $stmt->bind_param('i', $spec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $spec_info = $result->fetch_assoc();
    $stmt->close();
    
    return $spec_info;
}

// Fetch line items for specification
function fetchSpecificationLineItems($mysqli, $spec_id) {
    $stmt = $mysqli->prepare("
        SELECT 
            sd.id AS line_id,
            sd.id_tovary_i_uslugi AS product_id,
            sd.id_serii AS seria_id,
            sd.id_edinicy_izmeneniya AS unit_id,
            sd.id_stavka_nds AS nds_id,
            sd.kolichestvo AS quantity,
            sd.cena AS price,
            sd.summa_nds AS nds_amount,
            sd.summa AS total_amount,
            p.naimenovanie AS product_name,
            ser.nomer AS seria_name,
            u.naimenovanie AS unit_name,
            nds.stavka_nds AS nds_rate
        FROM stroki_dokumentov sd
        JOIN tovary_i_uslugi p ON sd.id_tovary_i_uslugi = p.id
        LEFT JOIN serii ser ON sd.id_serii = ser.id
        JOIN edinicy_izmeneniya u ON sd.id_edinicy_izmeneniya = u.id
        JOIN stavki_nds nds ON sd.id_stavka_nds = nds.id
        WHERE sd.id_dokumenta = ?
        ORDER BY sd.id
    ");
    $stmt->bind_param('i', $spec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $line_items;
}

// Fetch all NDS rates
function fetchNdsRates($mysqli) {
    $nds_rates = [];
    $nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
    $nds_result = $mysqli->query($nds_query);
    if ($nds_result) {
        $nds_rates = $nds_result->fetch_all(MYSQLI_ASSOC);
    }
    return $nds_rates;
}

// Fetch all units
function fetchAllUnits($mysqli) {
    $units = [];
    $units_query = "SELECT id, naimenovanie FROM edinicy_izmeneniya ORDER BY naimenovanie ASC";
    $units_result = $mysqli->query($units_query);
    if ($units_result) {
        $units = $units_result->fetch_all(MYSQLI_ASSOC);
    }
    return $units;
}

// Create new specification
function createSpecification($mysqli, $data) {
    $stmt = $mysqli->prepare("
        INSERT INTO noreg_specifikacii_k_dogovoru 
        (data_dogovora, nomer_specifikacii, gorod, id_organizacii, id_kontragenti, planiruemaya_data_postavki, usloviya_otgruzki, usloviya_oplaty, inye_usloviya, id_sotrudniki, podpisant_postavshchika_dolzhnost, podpisant_postavshchika_fio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssiiisssiss',
        $data['data_dogovora'],
        $data['nomer_specifikacii'],
        $data['gorod'],
        $data['organization_id'],
        $data['kontragenti_id'],
        $data['planiruemaya_data_postavki'],
        $data['usloviya_otgruzki'],
        $data['usloviya_oplaty'],
        $data['inye_usloviya'],
        $data['sotrudniki_id'],
        $data['podpisant_postavshchika_dolzhnost'],
        $data['podpisant_postavshchika_fio']
    );
    
    if ($stmt->execute()) {
        $spec_id = $mysqli->insert_id;
        $stmt->close();
        return ['success' => true, 'id' => $spec_id];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'error' => 'Ошибка при создании спецификации: ' . $error];
    }
}

// Update existing specification
function updateSpecification($mysqli, $spec_id, $data) {
    $stmt = $mysqli->prepare("
        UPDATE noreg_specifikacii_k_dogovoru 
        SET data_dogovora = ?,
            nomer_specifikacii = ?,
            gorod = ?,
            id_organizacii = ?,
            id_kontragenti = ?,
            planiruemaya_data_postavki = ?,
            usloviya_otgruzki = ?,
            usloviya_oplaty = ?,
            inye_usloviya = ?,
            id_sotrudniki = ?,
            podpisant_postavshchika_dolzhnost = ?,
            podpisant_postavshchika_fio = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'sssiiisssissi',
        $data['data_dogovora'],
        $data['nomer_specifikacii'],
        $data['gorod'],
        $data['organization_id'],
        $data['kontragenti_id'],
        $data['planiruemaya_data_postavki'],
        $data['usloviya_otgruzki'],
        $data['usloviya_oplaty'],
        $data['inye_usloviya'],
        $data['sotrudniki_id'],
        $data['podpisant_postavshchika_dolzhnost'],
        $data['podpisant_postavshchika_fio'],
        $spec_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'error' => 'Ошибка при сохранении спецификации: ' . $error];
    }
}

// Delete line items for specification
function deleteSpecificationLineItems($mysqli, $spec_id) {
    $stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?");
    $stmt->bind_param('i', $spec_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Insert line item
function insertSpecificationLineItem($mysqli, $doc_id, $product) {
    $product_id = intval($product['product_id']);
    $seria_id = intval($product['seria_id'] ?? 0);
    $unit_id = intval($product['unit_id']);
    $nds_id = intval($product['nds_id']);
    $quantity = floatval($product['quantity'] ?? 0);
    $price = floatval($product['price'] ?? 0);
    $nds_amount = floatval($product['summa_stavka'] ?? 0);
    $total_amount = floatval($product['summa'] ?? 0);
    
    $line_stmt = $mysqli->prepare("
        INSERT INTO stroki_dokumentov 
        (id_dokumenta, id_tovary_i_uslugi, id_serii, id_edinicy_izmeneniya, id_stavka_nds, kolichestvo, cena, summa_nds, summa)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $line_stmt->bind_param(
        'iiiiidddd',
        $doc_id,
        $product_id,
        $seria_id,
        $unit_id,
        $nds_id,
        $quantity,
        $price,
        $nds_amount,
        $total_amount
    );
    
    if ($line_stmt->execute()) {
        $line_stmt->close();
        return ['success' => true];
    } else {
        $error = $line_stmt->error;
        $line_stmt->close();
        return ['success' => false, 'error' => 'Ошибка при добавлении товара: ' . $error];
    }
}

?>
