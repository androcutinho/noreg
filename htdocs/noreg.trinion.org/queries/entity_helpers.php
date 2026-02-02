<?php

function getOrCreateEntity($mysqli, $table_name, $id_column, $name_column, $entity_name) {
    $entity_name = trim($entity_name);
    
    // Check if entity exists
    $check_sql = "SELECT " . $id_column . " FROM " . $table_name . " WHERE " . $name_column . " = ?";
    $check_stmt = $mysqli->stmt_init();
    
    if (!$check_stmt->prepare($check_sql)) {
        throw new Exception("SQL error checking entity in $table_name: " . $mysqli->error);
    }
    
    $check_stmt->bind_param("s", $entity_name);
    if (!$check_stmt->execute()) {
        throw new Exception("Error executing check in $table_name: " . $mysqli->error);
    }
    
    $check_result = $check_stmt->get_result();
    
    // Entity exists, return existing ID
    if ($check_result->num_rows > 0) {
        $existing = $check_result->fetch_assoc();
        return $existing[$id_column];
    }
    
    // Entity doesn't exist, create it
    $insert_sql = "INSERT INTO " . $table_name . "(" . $name_column . ") VALUES (?)";
    $insert_stmt = $mysqli->stmt_init();
    
    if (!$insert_stmt->prepare($insert_sql)) {
        throw new Exception("SQL error inserting into $table_name: " . $mysqli->error);
    }
    
    $insert_stmt->bind_param("s", $entity_name);
    if (!$insert_stmt->execute()) {
        throw new Exception("Error inserting into $table_name: " . $mysqli->error);
    }
    
    return $mysqli->insert_id;
}

/**
 * Get warehouse ID or create new warehouse
 */
function getOrCreateWarehouse($mysqli, $warehouse_id, $warehouse_name) {
    if (!empty($warehouse_id)) {
        return intval($warehouse_id);
    }
    
    if (empty($warehouse_name)) {
        throw new Exception("Warehouse name is required when warehouse_id is not provided");
    }
    
    return getOrCreateEntity($mysqli, TABLE_WAREHOUSES, COL_WAREHOUSE_ID, COL_WAREHOUSE_NAME, $warehouse_name);
}

/**
 * Get vendor ID or create new vendor
 */
function getOrCreateVendor($mysqli, $vendor_id, $vendor_name) {
    if (!empty($vendor_id)) {
        return intval($vendor_id);
    }
    
    if (empty($vendor_name)) {
        throw new Exception("Vendor name is required when vendor_id is not provided");
    }
    
    return getOrCreateEntity($mysqli, TABLE_VENDORS, COL_VENDOR_ID, COL_VENDOR_NAME, $vendor_name);
}

/**
 * Get organization ID or create new organization
 */
function getOrCreateOrganization($mysqli, $organization_id, $organization_name) {
    if (!empty($organization_id)) {
        return intval($organization_id);
    }
    
    if (empty($organization_name)) {
        throw new Exception("Organization name is required when organization_id is not provided");
    }
    
    return getOrCreateEntity($mysqli, TABLE_ORGANIZATIONS, COL_ORG_ID, COL_ORG_NAME, $organization_name);
}

/**
 * Get product ID or create new product
 */
function getOrCreateProduct($mysqli, $product_id, $product_name) {
    if (!empty($product_id)) {
        return intval($product_id);
    }
    
    if (empty($product_name)) {
        throw new Exception("Product name is required when product_id is not provided");
    }
    
    return getOrCreateEntity($mysqli, TABLE_PRODUCTS, COL_PRODUCT_ID, COL_PRODUCT_NAME, $product_name);
}

/**
 * Get unit ID or create new unit
 */
function getOrCreateUnit($mysqli, $unit_id, $unit_name) {
    if (!empty($unit_id)) {
        return intval($unit_id);
    }
    
    if (empty($unit_name)) {
        return 0; // Unit is optional
    }
    
    return getOrCreateEntity($mysqli, TABLE_UNITS, COL_UNIT_ID, COL_UNIT_NAME, $unit_name);
}


function getOrCreateSeries($mysqli, $seria_id, $seria_name, $product_id, $prod_date = null, $exp_date = null, $insert_dates = true) {
    if (!empty($seria_id)) {
        return intval($seria_id);
    }
    
    if (empty($seria_name)) {
        return 0; // Series is optional
    }
    
    $seria_name = trim($seria_name);
    
    // Check if series exists
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
    
    // Series exists, return existing ID
    if ($check_result->num_rows > 0) {
        $existing_seria = $check_result->fetch_assoc();
        return $existing_seria[COL_SERIES_ID];
    }
    
    // Series doesn't exist, create it
    $product_id_for_seria = intval($product_id);
    
    if ($insert_dates) {
        // For creation: include dates
        $insert_seria_sql = "INSERT INTO " . TABLE_SERIES . "(nomer, " . COL_SERIES_PRODUCT_ID . ", data_izgotovleniya, srok_godnosti) VALUES (?, ?, ?, ?)";
        $insert_seria_stmt = $mysqli->stmt_init();
        
        if (!$insert_seria_stmt->prepare($insert_seria_sql)) {
            throw new Exception("SQL error inserting series: " . $mysqli->error);
        }
        
        $insert_seria_stmt->bind_param("siss", $seria_name, $product_id_for_seria, $prod_date, $exp_date);
    } else {
        // For updates: don't include dates in creation
        $insert_seria_sql = "INSERT INTO " . TABLE_SERIES . "(nomer, " . COL_SERIES_PRODUCT_ID . ") VALUES (?, ?)";
        $insert_seria_stmt = $mysqli->stmt_init();
        
        if (!$insert_seria_stmt->prepare($insert_seria_sql)) {
            throw new Exception("SQL error inserting series: " . $mysqli->error);
        }
        
        $insert_seria_stmt->bind_param("si", $seria_name, $product_id_for_seria);
    }
    
    if (!$insert_seria_stmt->execute()) {
        throw new Exception("Error inserting new series: " . $mysqli->error);
    }
    
    return $mysqli->insert_id;
}

/**
 * Update series with product ID and dates
 */
function updateSeriesData($mysqli, $seria_id, $product_id, $prod_date = null, $exp_date = null) {
    if ($seria_id <= 0) {
        return;
    }
    
    $goods_id = intval($product_id);
    $update_seria_sql = "UPDATE " . TABLE_SERIES . " SET " . COL_SERIES_PRODUCT_ID . " = ?, data_izgotovleniya = ?, srok_godnosti = ? WHERE " . COL_SERIES_ID . " = ?";
    $update_seria_stmt = $mysqli->stmt_init();
    
    if (!$update_seria_stmt->prepare($update_seria_sql)) {
        throw new Exception("SQL error updating series: " . $mysqli->error);
    }
    
    $update_seria_stmt->bind_param("issi", $goods_id, $prod_date, $exp_date, $seria_id);
    
    if (!$update_seria_stmt->execute()) {
        throw new Exception("Error updating series: " . $mysqli->error);
    }
}

?>
