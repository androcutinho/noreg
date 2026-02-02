<?php

require_once __DIR__ . '/entity_helpers.php';

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
        sd.summa_postupleniya as total_amount
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
        $mysqli->begin_transaction();
        
        // Get or create warehouse, vendor, organization
        $warehouse_id_input = isset($data['warehouse_id']) ? $data['warehouse_id'] : null;
        $warehouse_name_input = isset($data['warehouse_name']) ? $data['warehouse_name'] : null;
        $data['warehouse_id'] = getOrCreateWarehouse($mysqli, $warehouse_id_input, $warehouse_name_input);
        
        $vendor_id_input = isset($data['vendor_id']) ? $data['vendor_id'] : null;
        $vendor_name_input = isset($data['vendor_name']) ? $data['vendor_name'] : null;
        $data['vendor_id'] = getOrCreateVendor($mysqli, $vendor_id_input, $vendor_name_input);
        
        $org_id_input = isset($data['organization_id']) ? $data['organization_id'] : null;
        $org_name_input = isset($data['organization_name']) ? $data['organization_name'] : null;
        $data['organization_id'] = getOrCreateOrganization($mysqli, $org_id_input, $org_name_input);
        
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
        
        // Process products
        $products_data = $data['products'];
        foreach ($products_data as $product) {
            // Validate required fields
            if (empty($product['price']) || empty($product['quantity']) || empty($product['nds_id'])) {
                continue;
            }
            
            // Get or create product, unit, series
            $prod_id_input = isset($product['product_id']) ? $product['product_id'] : null;
            $prod_name_input = isset($product['product_name']) ? $product['product_name'] : null;
            $product['product_id'] = getOrCreateProduct($mysqli, $prod_id_input, $prod_name_input);
            
            if (empty($product['product_id'])) {
                continue;
            }
            
            $unit_id_input = isset($product['unit_id']) ? $product['unit_id'] : null;
            $unit_name_input = isset($product['unit_name']) ? $product['unit_name'] : null;
            $product['unit_id'] = getOrCreateUnit($mysqli, $unit_id_input, $unit_name_input);
            
            $seria_id_input = isset($product['seria_id']) ? $product['seria_id'] : null;
            $seria_name_input = isset($product['seria_name']) ? $product['seria_name'] : null;
            $product['seria_id'] = getOrCreateSeries($mysqli, $seria_id_input, $seria_name_input, $product['product_id'], null, null, false);
            
            // Insert line item with all fields
            $goods_id = intval($product['product_id']);
            $nds_id = intval($product['nds_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $summa = floatval($product['summa']);
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
            $line_sql = "INSERT INTO " . TABLE_DOCUMENT_LINES . "(" . COL_LINE_DOCUMENT_ID . ", " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ", " . COL_LINE_SUMMA . ", id_serii, " . COL_LINE_UNIT_ID . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiidddii",
                $document_id,
                $goods_id,
                $nds_id,
                $price,
                $quantity,
                $summa,
                $seria_id,
                $unit_id
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки документа: " . $mysqli->error);
            }
        }
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Документ успешно обновлен'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        
        error_log("[UPDATE ARRIVAL] Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

?>
