<?php

require_once __DIR__ . '/entity_helpers.php';

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
        
        
        $arrival_sql = "INSERT INTO " . TABLE_ARRIVALS . "(" . COL_ARRIVAL_VENDOR_ID . ", " . COL_ARRIVAL_ORG_ID . ", " . COL_ARRIVAL_WAREHOUSE_ID . ", " . COL_ARRIVAL_RESPONSIBLE_ID . ", " . COL_ARRIVAL_DATE . ") VALUES (?, ?, ?, ?, ?)";
        $arrival_stmt = $mysqli->stmt_init();
        
        if (!$arrival_stmt->prepare($arrival_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $arrival_stmt->bind_param(
            "iiiis",
            $vendor_id,
            $organization_id,
            $warehouse_id,
            $responsible_id,
            $datetime
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
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
            // Update series data if it exists
            if ($seria_id > 0) {
                updateSeriesData($mysqli, $seria_id, $goods_id, $prod_date, $exp_date);
            }
            
            // Insert line item with id_serii and id_edinicy_izmereniya
            $line_sql = "INSERT INTO " . TABLE_DOCUMENT_LINES . "(" . COL_LINE_DOCUMENT_ID . ", " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ", " . COL_LINE_SUMMA . ", id_serii, " . COL_LINE_UNIT_ID . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error . " | SQL: " . $line_sql);
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
        return array('success' => true, 'document_id' => $document_id);
    } catch (Exception $e) {
        $mysqli->rollback();
        return array('success' => false, 'error' => $e->getMessage());
    }
}

?>
