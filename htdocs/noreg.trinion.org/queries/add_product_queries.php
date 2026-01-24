<?php

/**
 * Create a new arrival document with line items
 * @param mysqli $mysqli Database connection
 * @param array $data Document data
 * @return array Array with 'success' bool and 'document_id' or 'error' message
 */
function createArrivalDocument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        $warehouse_id = intval($data['warehouse_id']);
        $organization_id = intval($data['organization_id']);
        $responsible_id = intval($data['responsible_id']);
        $vendor_id = intval($data['vendor_id']);
        
        // Преобразовать datetime-local в формат MySQL datetime
        $datetime = $data['product_date'];
        $datetime = str_replace('T', ' ', $datetime) . ':00';
        
        // Insert arrival document
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
        
        // Insert line items
        $products_data = $data['products'];
        foreach ($products_data as $product) {
            if (empty($product['product_id']) || empty($product['price']) || empty($product['quantity']) || empty($product['nds_id'])) {
                continue; 
            }
            
            $goods_id = intval($product['product_id']);
            $nds_id = intval($product['nds_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            
            // Update series if provided
            if ($seria_id > 0) {
                $update_seria_sql = "UPDATE " . TABLE_SERIES . " SET " . COL_SERIES_PRODUCT_ID . " = ? WHERE " . COL_SERIES_ID . " = ?";
                $update_seria_stmt = $mysqli->stmt_init();
                
                if (!$update_seria_stmt->prepare($update_seria_sql)) {
                    throw new Exception("SQL error al preparar UPDATE serii: " . $mysqli->error);
                }
                
                $update_seria_stmt->bind_param("ii", $goods_id, $seria_id);
                
                if (!$update_seria_stmt->execute()) {
                    throw new Exception("Error al actualizar serii: " . $mysqli->error);
                }
            }
            
            // Insert line item
            $line_sql = "INSERT INTO " . TABLE_DOCUMENT_LINES . "(" . COL_LINE_DOCUMENT_ID . ", " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ") VALUES (?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiidd",
                $document_id,
                $goods_id,
                $nds_id,
                $price,
                $quantity
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
