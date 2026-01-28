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
        $mysqli->begin_transaction();
        
        // Auto-insert logic for warehouse
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
        
        // Auto-insert logic for vendor
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
        
        // Auto-insert logic for organization
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
            
            // Auto-insert logic for product
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
                    $existing_product = $check_result->fetch_assoc();
                    $product['product_id'] = $existing_product[COL_PRODUCT_ID];
                } else {
                    $insert_product_sql = "INSERT INTO " . TABLE_PRODUCTS . "(" . COL_PRODUCT_NAME . ") VALUES (?)";
                    $insert_product_stmt = $mysqli->stmt_init();
                    
                    if (!$insert_product_stmt->prepare($insert_product_sql)) {
                        throw new Exception("SQL error inserting product: " . $mysqli->error);
                    }
                    
                    $insert_product_stmt->bind_param("s", $product_name);
                    if (!$insert_product_stmt->execute()) {
                        throw new Exception("Error inserting new product: " . $mysqli->error);
                    }
                    
                    $product['product_id'] = $mysqli->insert_id;
                }
            }
            
            if (empty($product['product_id'])) {
                continue;
            }
            
            // Auto-insert logic for unit
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
            
            // Auto-insert logic for series
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
                    $existing_seria = $check_result->fetch_assoc();
                    $product['seria_id'] = $existing_seria[COL_SERIES_ID];
                } else {
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
            
            // UPDATE dates for any seria_id
            if (!empty($product['seria_id'])) {
                $seria_id_for_dates = intval($product['seria_id']);
                $data_izgotovleniya = !empty($data['data_izgotovleniya']) ? $data['data_izgotovleniya'] : null;
                $srok_godnosti = !empty($data['srok_godnosti']) ? $data['srok_godnosti'] : null;
                
                if ($data_izgotovleniya || $srok_godnosti) {
                    $update_seria_dates_sql = "UPDATE " . TABLE_SERIES . " SET data_izgotovleniya = ?, srok_godnosti = ? WHERE " . COL_SERIES_ID . " = ?";
                    $update_seria_dates_stmt = $mysqli->stmt_init();
                    
                    if (!$update_seria_dates_stmt->prepare($update_seria_dates_sql)) {
                        throw new Exception("SQL error updating series dates: " . $mysqli->error);
                    }
                
                    $update_seria_dates_stmt->bind_param("ssi", $data_izgotovleniya, $srok_godnosti, $seria_id_for_dates);
                    if (!$update_seria_dates_stmt->execute()) {
                        throw new Exception("Error updating series dates: " . $mysqli->error);
                    }
                }
            }
            
            // Insert line item with all fields
            $goods_id = intval($product['product_id']);
            $nds_id = intval($product['nds_id']);
            $price = floatval($product['price']);
            $quantity = floatval($product['quantity']);
            $seria_id = !empty($product['seria_id']) ? intval($product['seria_id']) : 0;
            $unit_id = !empty($product['unit_id']) ? intval($product['unit_id']) : 0;
            
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
