<?php

require_once __DIR__ . '/../config/database_config.php';
require_once __DIR__ . '/id_index_helper.php';
require_once __DIR__ . '/entity_helpers.php';

// CREATE ARRIVAL DOCUMENT
function createArrivalDocument($mysqli, $data) {
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
        
        $warehouse_id = intval($data['warehouse_id']);
        $organization_id = intval($data['organization_id']);
        $responsible_id = intval($data['responsible_id']);
        $vendor_id = intval($data['vendor_id']);
        
        $datetime = $data['product_date'];
        $datetime = str_replace('T', ' ', $datetime) . ':00';
        
        $utverzhden = 0;
        
        $id_index = getNextIdIndex($mysqli);
        
        $arrival_sql = "INSERT INTO " . postupleniya_tovarov . "(" . COL_ARRIVAL_VENDOR_ID . ", " . COL_ARRIVAL_ORG_ID . ", " . COL_ARRIVAL_WAREHOUSE_ID . ", " . COL_ARRIVAL_RESPONSIBLE_ID . ", " . COL_ARRIVAL_DATE . ", utverzhden, id_index) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $arrival_stmt = $mysqli->stmt_init();
        
        if (!$arrival_stmt->prepare($arrival_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $arrival_stmt->bind_param(
            "iiiisii",
            $vendor_id,
            $organization_id,
            $warehouse_id,
            $responsible_id,
            $datetime,
            $utverzhden,
            $id_index
        );
        
        if (!$arrival_stmt->execute()) {
            throw new Exception("Ошибка при создании документа поступления: " . $mysqli->error);
        }
        
        $document_id = $mysqli->insert_id;
        
        
        $products_data = $data['products'];
        foreach ($products_data as $product) {
            // Validate required fields (price, quantity, nds are always required)
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
            
            $prod_date = !empty($data['data_izgotovleniya']) ? $data['data_izgotovleniya'] : null;
            $exp_date = !empty($data['srok_godnosti']) ? $data['srok_godnosti'] : null;
            
            $seria_id_input = isset($product['seria_id']) ? $product['seria_id'] : null;
            $seria_name_input = isset($product['seria_name']) ? $product['seria_name'] : null;
            $product['seria_id'] = getOrCreateSeries($mysqli, $seria_id_input, $seria_name_input, $product['product_id'], $prod_date, $exp_date, true);
            
            $goods_id = intval($product['product_id']);
            $nds_id = intval($product['nds_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $summa = floatval($product['summa']);
            $summa_stavka = !empty($product['summa_stavka']) ? floatval($product['summa_stavka']) : 0;
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
            // Update series data if it exists
            if ($seria_id > 0) {
                updateSeriesData($mysqli, $seria_id, $goods_id, $prod_date, $exp_date);
            }
            
            // Insert line item with id_serii and id_edinicy_izmereniya
            $line_sql = "INSERT INTO " . stroki_dokumentov . "(" . COL_LINE_DOCUMENT_ID . ", id_index, " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ", " . COL_LINE_SUMMA . ", " . COL_LINE_SERIES_ID . ", " . COL_LINE_UNIT_ID . ", " . COL_LINE_NDS_AMOUNT . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error . " | SQL: " . $line_sql);
            }
            
            $line_stmt->bind_param(
                "iiiidddiid",
                $document_id,
                $id_index,
                $goods_id,
                $nds_id,
                $price,
                $quantity,
                $summa,
                $seria_id,
                $unit_id,
                $summa_stavka
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки документа: " . $mysqli->error);
            }
        }
        
        $mysqli->commit();
        return array('success' => true, 'document_id' => $document_id);
    } catch (Exception $e) {
        $mysqli->rollback();
        return array('success' => false, 'error' => $e->getMessage());
    }
}

// FETCH DOCUMENT HEADER (for edit and view)
function fetchDocumentHeader($mysqli, $document_id) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        pt.utverzhden,
        pt.zakryt,
        pt.id_organizacii,
        pt.id_postavshchika,
        pt.id_sklada,
        pt.id_otvetstvennyj,
        pt.id_index,
        org.naimenovanie as org_name,
        org.id as organization_id,
        org.inn as org_inn,
        org.kpp as org_kpp,
        ps.naimenovanie as vendor_name,
        ps.id as vendor_id,
        ps.inn as vendor_inn,
        ps.kpp as vendor_kpp,
        sl.naimenovanie as warehouse_name,
        sl.id as warehouse_id,
        u.user_name as responsible_name,
        u.user_id as responsible_id
    FROM postupleniya_tovarov pt
    LEFT JOIN organizacii org ON pt.id_organizacii = org.id
    LEFT JOIN kontragenti ps ON pt.id_postavshchika = ps.id
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

// FETCH DOCUMENT LINE ITEMS (for edit and view)
function fetchDocumentLineItems($mysqli, $id_index) {
    $sql = "SELECT 
        sd.id,
        sd." . COL_LINE_PRODUCT_ID . " as product_id,
        sd." . COL_LINE_SERIES_ID . ",
        sd." . COL_LINE_UNIT_ID . ",
        ti.naimenovanie as product_name,
        ser.id as seria_id,
        ser." . COL_SERIES_NUMBER . " as seria_name,
        ser." . data_izgotovleniya . ",
        ser." . srok_godnosti . ",
        eu.naimenovanie as unit_name,
        sd." . COL_LINE_QUANTITY . " as quantity,
        sd." . COL_LINE_PRICE . " as unit_price,
        sd." . COL_LINE_NDS_ID . " as nds_id,
        sn.stavka_nds as vat_rate,
        sd." . COL_LINE_SUMMA . " as total_amount,
        sd." . COL_LINE_NDS_AMOUNT . " as nds_amount
    FROM " . stroki_dokumentov . " sd
    LEFT JOIN tovary_i_uslugi ti ON sd." . COL_LINE_PRODUCT_ID . " = ti.id
    LEFT JOIN serii ser ON ser.id = sd." . COL_LINE_SERIES_ID . " AND ser." . serii_id_tovary_i_uslugi . " = sd." . COL_LINE_PRODUCT_ID . "
    LEFT JOIN stavki_nds sn ON sd." . COL_LINE_NDS_ID . " = sn.id
    LEFT JOIN edinicy_izmereniya eu ON sd." . COL_LINE_UNIT_ID . " = eu.id
    WHERE sd.id_index = ?
    ORDER BY sd.id ASC";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        die("SQL error: " . $mysqli->error);
    }

    $stmt->bind_param("i", $id_index);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = array();

    while ($row = $result->fetch_assoc()) {
        $line_items[] = $row;
    }

    return $line_items;
}

// UPDATE ARRIVAL DOCUMENT
function updateArrivalDocument($mysqli, $document_id, $data) {
    try {
        $mysqli->begin_transaction();
        
        // Get or create warehouse, vendor, organization - use same logic as create
        $warehouse_id_input = isset($data['warehouse_id']) ? $data['warehouse_id'] : null;
        $warehouse_name_input = isset($data['warehouse_name']) ? $data['warehouse_name'] : null;
        $data['warehouse_id'] = getOrCreateWarehouse($mysqli, $warehouse_id_input, $warehouse_name_input);
        
        $vendor_id_input = isset($data['vendor_id']) ? $data['vendor_id'] : null;
        $vendor_name_input = isset($data['vendor_name']) ? $data['vendor_name'] : null;
        $data['vendor_id'] = getOrCreateVendor($mysqli, $vendor_id_input, $vendor_name_input);
        
        $org_id_input = isset($data['organization_id']) ? $data['organization_id'] : null;
        $org_name_input = isset($data['organization_name']) ? $data['organization_name'] : null;
        $data['organization_id'] = getOrCreateOrganization($mysqli, $org_id_input, $org_name_input);
        
        // Get responsible ID (must exist, don't create)
        if (empty($data['responsible_id'])) {
            throw new Exception("Пожалуйста, выберите ответственного из списка");
        }
        $responsible_id = intval($data['responsible_id']);
        
        $warehouse_id = intval($data['warehouse_id']);
        $organization_id = intval($data['organization_id']);
        $vendor_id = intval($data['vendor_id']);
        
        // Update document header
        $doc_sql = "UPDATE " . postupleniya_tovarov . " SET 
            " . COL_ARRIVAL_DATE . " = ?,
            " . COL_ARRIVAL_WAREHOUSE_ID . " = ?,
            " . COL_ARRIVAL_VENDOR_ID . " = ?,
            " . COL_ARRIVAL_ORG_ID . " = ?,
            " . COL_ARRIVAL_RESPONSIBLE_ID . " = ?
        WHERE " . COL_ARRIVAL_ID . " = ?";
        
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
            $responsible_id,
            $document_id
        );
        
        if (!$doc_stmt->execute()) {
            throw new Exception("Ошибка обновления документа: " . $doc_stmt->error);
        }
        
        // Get the id_index from the document
        $get_index_query = "SELECT id_index FROM postupleniya_tovarov WHERE id = ?";
        $get_stmt = $mysqli->stmt_init();
        $get_stmt->prepare($get_index_query);
        $get_stmt->bind_param('i', $document_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $doc_data = $get_result->fetch_assoc();
        $get_stmt->close();
        
        if (!$doc_data) {
            throw new Exception("Документ больше не существует");
        }
        
        $id_index = $doc_data['id_index'];
        
        // Delete old line items by id_index
        $delete_sql = "DELETE FROM " . stroki_dokumentov . " WHERE id_index = ?";
        $delete_stmt = $mysqli->stmt_init();
        if (!$delete_stmt->prepare($delete_sql)) {
            throw new Exception("Ошибка подготовки удаления строк: " . $mysqli->error);
        }
        
        $delete_stmt->bind_param("i", $id_index);
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
            $summa_stavka = !empty($product['summa_stavka']) ? floatval($product['summa_stavka']) : 0;
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
            $line_sql = "INSERT INTO " . stroki_dokumentov . "(" . COL_LINE_DOCUMENT_ID . ", id_index, " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ", " . COL_LINE_SUMMA . ", " . COL_LINE_SERIES_ID . ", " . COL_LINE_UNIT_ID . ", " . COL_LINE_NDS_AMOUNT . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiiidddiid",
                $document_id,
                $id_index,
                $goods_id,
                $nds_id,
                $price,
                $quantity,
                $summa,
                $seria_id,
                $unit_id,
                $summa_stavka
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

// DELETE ARRIVAL DOCUMENT
function deleteArrivalDocument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        
        // Get the id_index first
        $get_index_query = "SELECT id_index FROM postupleniya_tovarov WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $id_index = $doc['id_index'];
        
        // Delete line items using id_index
        $delete_items_query = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $stmt = $mysqli->prepare($delete_items_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления строк: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $id_index);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении строк товара: ' . $stmt->error);
        }
        $stmt->close();
        
        // Delete the document header
        $delete_doc_query = "DELETE FROM postupleniya_tovarov WHERE id = ?";
        $stmt = $mysqli->prepare($delete_doc_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления документа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $document_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении документа: ' . $stmt->error);
        }
        $stmt->close();
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Документ успешно удален'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// CALCULATE TOTALS (for view)
function calculateTotals($line_items) {
    $subtotal = 0;
    $vat_total = 0;

    foreach ($line_items as $item) {
        $subtotal += floatval($item['total_amount']);
    }

    // Calculate VAT if items exist
    if (!empty($line_items)) {
        $first_item = $line_items[0];
        $vat_rate = floatval($first_item['vat_rate']);
        $vat_total = ($subtotal * $vat_rate) / 100;
    }

    return array(
        'subtotal' => $subtotal,
        'vat_total' => $vat_total,
        'total_due' => $subtotal + $vat_total
    );
}

?>
