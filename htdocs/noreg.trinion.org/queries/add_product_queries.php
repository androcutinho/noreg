<?php

function createArrivalDocument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['warehouse_id']) && !empty($data['warehouse_name'])) {
            $warehouse_name = trim($data['warehouse_name']);
            $check_warehouse_sql = "SELECT " . COL_WAREHOUSE_ID . " FROM " . TABLE_WAREHOUSES . " WHERE " . COL_WAREHOUSE_NAME . " = ?";
            $check_warehouse_stmt = $mysqli->stmt_init();
            
            if (!$check_warehouse_stmt->prepare($check_warehouse_sql)) {
                throw new Exception("SQL error checking warehouse: " . $mysqli->error);
            }
            
            $check_warehouse_stmt->bind_param("s", $warehouse_name);
            if (!$check_warehouse_stmt->execute()) {
                throw new Exception("Error executing warehouse check: " . $mysqli->error);
            }
            
            $check_result = $check_warehouse_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_warehouse = $check_result->fetch_assoc();
                $data['warehouse_id'] = $existing_warehouse[COL_WAREHOUSE_ID];
            } else {
                $insert_warehouse_sql = "INSERT INTO " . TABLE_WAREHOUSES . "(" . COL_WAREHOUSE_NAME . ") VALUES (?)";
                $insert_warehouse_stmt = $mysqli->stmt_init();
                
                if (!$insert_warehouse_stmt->prepare($insert_warehouse_sql)) {
                    throw new Exception("SQL error inserting warehouse: " . $mysqli->error);
                }
                
                $insert_warehouse_stmt->bind_param("s", $warehouse_name);
                if (!$insert_warehouse_stmt->execute()) {
                    throw new Exception("Error inserting new warehouse: " . $mysqli->error);
                }
                
                $data['warehouse_id'] = $mysqli->insert_id;
            }
        }
        
        
        if (empty($data['vendor_id']) && !empty($data['vendor_name'])) {
            $vendor_name = trim($data['vendor_name']);
            $check_vendor_sql = "SELECT " . COL_VENDOR_ID . " FROM " . TABLE_VENDORS . " WHERE " . COL_VENDOR_NAME . " = ?";
            $check_vendor_stmt = $mysqli->stmt_init();
            
            if (!$check_vendor_stmt->prepare($check_vendor_sql)) {
                throw new Exception("SQL error checking vendor: " . $mysqli->error);
            }
            
            $check_vendor_stmt->bind_param("s", $vendor_name);
            if (!$check_vendor_stmt->execute()) {
                throw new Exception("Error executing vendor check: " . $mysqli->error);
            }
            
            $check_result = $check_vendor_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_vendor = $check_result->fetch_assoc();
                $data['vendor_id'] = $existing_vendor[COL_VENDOR_ID];
            } else {
                $insert_vendor_sql = "INSERT INTO " . TABLE_VENDORS . "(" . COL_VENDOR_NAME . ") VALUES (?)";
                $insert_vendor_stmt = $mysqli->stmt_init();
                
                if (!$insert_vendor_stmt->prepare($insert_vendor_sql)) {
                    throw new Exception("SQL error inserting vendor: " . $mysqli->error);
                }
                
                $insert_vendor_stmt->bind_param("s", $vendor_name);
                if (!$insert_vendor_stmt->execute()) {
                    throw new Exception("Error inserting new vendor: " . $mysqli->error);
                }
                
                $data['vendor_id'] = $mysqli->insert_id;
            }
        }
        
        
        if (empty($data['organization_id']) && !empty($data['organization_name'])) {
            $organization_name = trim($data['organization_name']);
            $check_org_sql = "SELECT " . COL_ORG_ID . " FROM " . TABLE_ORGANIZATIONS . " WHERE " . COL_ORG_NAME . " = ?";
            $check_org_stmt = $mysqli->stmt_init();
            
            if (!$check_org_stmt->prepare($check_org_sql)) {
                throw new Exception("SQL error checking organization: " . $mysqli->error);
            }
            
            $check_org_stmt->bind_param("s", $organization_name);
            if (!$check_org_stmt->execute()) {
                throw new Exception("Error executing organization check: " . $mysqli->error);
            }
            
            $check_result = $check_org_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_org = $check_result->fetch_assoc();
                $data['organization_id'] = $existing_org[COL_ORG_ID];
            } else {
                $insert_org_sql = "INSERT INTO " . TABLE_ORGANIZATIONS . "(" . COL_ORG_NAME . ") VALUES (?)";
                $insert_org_stmt = $mysqli->stmt_init();
                
                if (!$insert_org_stmt->prepare($insert_org_sql)) {
                    throw new Exception("SQL error inserting organization: " . $mysqli->error);
                }
                
                $insert_org_stmt->bind_param("s", $organization_name);
                if (!$insert_org_stmt->execute()) {
                    throw new Exception("Error inserting new organization: " . $mysqli->error);
                }
                
                $data['organization_id'] = $mysqli->insert_id;
            }
        }
        
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
            
            
            if (empty($product['product_id']) && !empty($product['product_name'])) {
                
                $product_name = trim($product['product_name']);
                $check_product_sql = "SELECT " . COL_PRODUCT_ID . " FROM " . TABLE_PRODUCTS . " WHERE " . COL_PRODUCT_NAME . " = ?";
                $check_product_stmt = $mysqli->stmt_init();
                
                if (!$check_product_stmt->prepare($check_product_sql)) {
                    throw new Exception("SQL error checking product: " . $mysqli->error);
                }
                
                $check_product_stmt->bind_param("s", $product_name);
                if (!$check_product_stmt->execute()) {
                    throw new Exception("Error executing product check: " . $mysqli->error);
                }
                
                $check_result = $check_product_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Product exists, use existing ID
                    $existing_product = $check_result->fetch_assoc();
                    $product['product_id'] = $existing_product[COL_PRODUCT_ID];
                } else {
                    // Product doesn't exist, INSERT it and get the new ID
                    $insert_product_sql = "INSERT INTO " . TABLE_PRODUCTS . "(" . COL_PRODUCT_NAME . ") VALUES (?)";
                    $insert_product_stmt = $mysqli->stmt_init();
                    
                    if (!$insert_product_stmt->prepare($insert_product_sql)) {
                        throw new Exception("SQL error inserting product: " . $mysqli->error);
                    }
                    
                    $insert_product_stmt->bind_param("s", $product_name);
                    if (!$insert_product_stmt->execute()) {
                        throw new Exception("Error inserting new product: " . $mysqli->error);
                    }
                    
                    // Get the auto-increment ID
                    $product['product_id'] = $mysqli->insert_id;
                }
            }
            
            
            if (empty($product['product_id'])) {
                continue;
            }
            
            
            if (empty($product['unit_id']) && !empty($product['unit_name'])) {
                $unit_name = trim($product['unit_name']);
                $check_unit_sql = "SELECT " . COL_UNIT_ID . " FROM " . TABLE_UNITS . " WHERE " . COL_UNIT_NAME . " = ?";
                $check_unit_stmt = $mysqli->stmt_init();
                
                if (!$check_unit_stmt->prepare($check_unit_sql)) {
                    throw new Exception("SQL error checking unit: " . $mysqli->error);
                }
                
                $check_unit_stmt->bind_param("s", $unit_name);
                if (!$check_unit_stmt->execute()) {
                    throw new Exception("Error executing unit check: " . $mysqli->error);
                }
                
                $check_result = $check_unit_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $existing_unit = $check_result->fetch_assoc();
                    $product['unit_id'] = $existing_unit[COL_UNIT_ID];
                } else {
                    $insert_unit_sql = "INSERT INTO " . TABLE_UNITS . "(" . COL_UNIT_NAME . ") VALUES (?)";
                    $insert_unit_stmt = $mysqli->stmt_init();
                    
                    if (!$insert_unit_stmt->prepare($insert_unit_sql)) {
                        throw new Exception("SQL error inserting unit: " . $mysqli->error);
                    }
                    
                    $insert_unit_stmt->bind_param("s", $unit_name);
                    if (!$insert_unit_stmt->execute()) {
                        throw new Exception("Error inserting new unit: " . $mysqli->error);
                    }
                    
                    $product['unit_id'] = $mysqli->insert_id;
                }
            }
            
            
            if (empty($product['seria_id']) && !empty($product['seria_name'])) {
                $seria_name = trim($product['seria_name']);
                $check_seria_sql = "SELECT " . COL_SERIES_ID . " FROM " . TABLE_SERIES . " WHERE nomer = ?";
                $check_seria_stmt = $mysqli->stmt_init();
                
                if (!$check_seria_stmt->prepare($check_seria_sql)) {
                    throw new Exception("SQL error checking series: " . $mysqli->error);
                }
                
                $check_seria_stmt->bind_param("s", $seria_name);
                if (!$check_seria_stmt->execute()) {
                    throw new Exception("Error executing series check: " . $mysqli->error);
                }
                
                $check_result = $check_seria_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Series exists, use existing ID
                    $existing_seria = $check_result->fetch_assoc();
                    $product['seria_id'] = $existing_seria[COL_SERIES_ID];
                } else {
                    // Series doesn't exist, INSERT it
                    $product_id_for_seria = intval($product['product_id']);
                    
                    $insert_seria_sql = "INSERT INTO " . TABLE_SERIES . "(nomer, " . COL_SERIES_PRODUCT_ID . ") VALUES (?, ?)";
                    $insert_seria_stmt = $mysqli->stmt_init();
                    
                    if (!$insert_seria_stmt->prepare($insert_seria_sql)) {
                        throw new Exception("SQL error inserting series: " . $mysqli->error);
                    }
                    
                    $insert_seria_stmt->bind_param("si", $seria_name, $product_id_for_seria);
                    if (!$insert_seria_stmt->execute()) {
                        throw new Exception("Error inserting new series: " . $mysqli->error);
                    }
                    
                    $product['seria_id'] = $mysqli->insert_id;
                }
            }
            
            $goods_id = intval($product['product_id']);
            $nds_id = intval($product['nds_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
            
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
            
            // Insert line item with id_serii and id_edinicy_izmereniya
            $line_sql = "INSERT INTO " . TABLE_DOCUMENT_LINES . "(" . COL_LINE_DOCUMENT_ID . ", " . COL_LINE_PRODUCT_ID . ", " . COL_LINE_NDS_ID . ", " . COL_LINE_PRICE . ", " . COL_LINE_QUANTITY . ", id_serii, " . COL_LINE_UNIT_ID . ") VALUES (?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiiddii",
                $document_id,
                $goods_id,
                $nds_id,
                $price,
                $quantity,
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
