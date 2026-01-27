<?php

function fetchDocumentHeader($mysqli, $document_id) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        pt.id_organizacii,
        pt.id_postavshchika,
        pt.id_sklada,
        pt.id_otvetstvennyj,
        org.naimenovanie as organization_name,
        org.id as organization_id,
        ps.naimenovanie as vendor_name,
        ps.id as vendor_id,
        sl.naimenovanie as warehouse_name,
        sl.id as warehouse_id,
        u.user_name as responsible_name,
        u.user_id as responsible_id
    FROM postupleniya_tovarov pt
    LEFT JOIN organizacii org ON pt.id_organizacii = org.id
    LEFT JOIN postavshchiki ps ON pt.id_postavshchika = ps.id
    LEFT JOIN sklady sl ON pt.id_sklada = sl.id
    LEFT JOIN users u ON pt.id_otvetstvennyj = u.user_id
    WHERE pt.id = ?";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }

    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}


function fetchDocumentLineItems($mysqli, $document_id) {
    $sql = "SELECT 
        sd.id,
        sd.id_tovary_i_uslugi as product_id,
        sd.id_serii,
        sd.id_edinicy_izmereniya,
        ti.naimenovanie as product_name,
        ser.id as seria_id,
        ser.nomer as seria_name,
        ser.data_izgotovleniya,
        ser.srok_godnosti,
        eu.naimenovanie as unit_name,
        sd.kolichestvo_postupleniya as quantity,
        sd.cena_postupleniya as unit_price,
        sd.id_stavka_nds as nds_id,
        sn.stavka_nds as vat_rate,
        (sd.cena_postupleniya * sd.kolichestvo_postupleniya) as total_amount
    FROM stroki_dokumentov sd
    LEFT JOIN tovary_i_uslugi ti ON sd.id_tovary_i_uslugi = ti.id
    LEFT JOIN serii ser ON ser.id = sd.id_serii AND ser.id_tovary_i_uslugi = sd.id_tovary_i_uslugi
    LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
    LEFT JOIN edinicy_izmereniya eu ON sd.id_edinicy_izmereniya = eu.id
    WHERE sd.id_dokumenta = ?
    ORDER BY sd.id ASC";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        die("SQL error: " . $mysqli->error);
    }

    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = array();

    while ($row = $result->fetch_assoc()) {
        $line_items[] = $row;
    }

    return $line_items;
}

function updateArrivalDocument($mysqli, $document_id, $data) {
    try {
        // Start transaction
        $mysqli->begin_transaction();
        
        // Update document header
        $doc_sql = "UPDATE postupleniya_tovarov SET 
            data_dokumenta = ?,
            id_sklada = ?,
            id_postavshchika = ?,
            id_organizacii = ?,
            id_otvetstvennyj = ?
        WHERE id = ?";
        
        $doc_stmt = $mysqli->stmt_init();
        if (!$doc_stmt->prepare($doc_sql)) {
            throw new Exception("Ошибка подготовки запроса документа: " . $mysqli->error);
        }
        
        $doc_stmt->bind_param(
            "siiiii",
            $data['product_date'],
            $data['warehouse_id'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $document_id
        );
        
        if (!$doc_stmt->execute()) {
            throw new Exception("Ошибка обновления документа: " . $doc_stmt->error);
        }
        
        // Delete old line items
        $delete_sql = "DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?";
        $delete_stmt = $mysqli->stmt_init();
        if (!$delete_stmt->prepare($delete_sql)) {
            throw new Exception("Ошибка подготовки удаления строк: " . $mysqli->error);
        }
        
        $delete_stmt->bind_param("i", $document_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("Ошибка удаления старых строк: " . $delete_stmt->error);
        }
        
        // Insert new line items
        $line_sql = "INSERT INTO stroki_dokumentov 
            (id_dokumenta, id_tovary_i_uslugi, cena_postupleniya, kolichestvo_postupleniya, id_stavka_nds, id_serii) 
        VALUES (?, ?, ?, ?, ?, ?)";
        
        $line_stmt = $mysqli->stmt_init();
        if (!$line_stmt->prepare($line_sql)) {
            throw new Exception("Ошибка подготовки вставки строк: " . $mysqli->error);
        }
        
        foreach ($data['products'] as $product) {
            if (empty($product['product_id']) || empty($product['quantity'])) {
                continue;
            }
            
            $product_id = intval($product['product_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $nds_id = !empty($product['nds_id']) ? intval($product['nds_id']) : null;
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            
            // Update serii table to set id_tovary_i_uslugi if provided
            if ($seria_id > 0) {
                $update_seria_sql = "UPDATE serii SET id_tovary_i_uslugi = ? WHERE id = ?";
                $update_seria_stmt = $mysqli->stmt_init();
                
                if (!$update_seria_stmt->prepare($update_seria_sql)) {
                    throw new Exception("Ошибка подготовки обновления серии: " . $mysqli->error);
                }
                
                $update_seria_stmt->bind_param("ii", $product_id, $seria_id);
                
                if (!$update_seria_stmt->execute()) {
                    throw new Exception("Ошибка обновления серии: " . $mysqli->error);
                }
            }
            
            $line_stmt->bind_param(
                "iiddii",
                $document_id,
                $product_id,
                $price,
                $quantity,
                $nds_id,
                $seria_id
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка вставки строки: " . $line_stmt->error);
            }
        }
        
        // Commit transaction
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Документ успешно обновлен'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        
        // Log error
        error_log("[UPDATE ARRIVAL] Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

?>
