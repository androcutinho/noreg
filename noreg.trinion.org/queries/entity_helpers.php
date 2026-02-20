<?php

require_once __DIR__ . '/../config/database_config.php';

// Get or create warehouse
function getOrCreateWarehouse($mysqli, $warehouse_id_input, $warehouse_name_input) {
    if (!empty($warehouse_id_input)) {
        return $warehouse_id_input;
    }
    
    if (empty($warehouse_name_input)) {
        throw new Exception("Warehouse name is required");
    }
    
    // Check if warehouse exists
    $stmt = $mysqli->prepare("SELECT id FROM sklady WHERE naimenovanie = ?");
    $stmt->bind_param("s", $warehouse_name_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new warehouse
    $insert_stmt = $mysqli->prepare("INSERT INTO sklady (naimenovanie) VALUES (?)");
    $insert_stmt->bind_param("s", $warehouse_name_input);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating warehouse: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Get or create vendor (kontragenti)
function getOrCreateVendor($mysqli, $vendor_id_input, $vendor_name_input) {
    if (!empty($vendor_id_input)) {
        return $vendor_id_input;
    }
    
    if (empty($vendor_name_input)) {
        throw new Exception("Vendor name is required");
    }
    
    // Check if vendor exists
    $stmt = $mysqli->prepare("SELECT id FROM kontragenti WHERE naimenovanie = ? AND nash_kontragent = 0");
    $stmt->bind_param("s", $vendor_name_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new vendor
    $insert_stmt = $mysqli->prepare("INSERT INTO kontragenti (naimenovanie, nash_kontragent) VALUES (?, 0)");
    $insert_stmt->bind_param("s", $vendor_name_input);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating vendor: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Get or create organization (kontragenti with nash_kontragent = 1)
function getOrCreateOrganization($mysqli, $org_id_input, $org_name_input) {
    if (!empty($org_id_input)) {
        return $org_id_input;
    }
    
    if (empty($org_name_input)) {
        throw new Exception("Organization name is required");
    }
    
    // Check if organization exists (must have nash_kontragent = 1)
    $stmt = $mysqli->prepare("SELECT id FROM kontragenti WHERE naimenovanie = ? AND nash_kontragent = 1");
    $stmt->bind_param("s", $org_name_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new organization with nash_kontragent = 1
    $insert_stmt = $mysqli->prepare("INSERT INTO kontragenti (naimenovanie, nash_kontragent) VALUES (?, 1)");
    $insert_stmt->bind_param("s", $org_name_input);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating organization: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Get or create product
function getOrCreateProduct($mysqli, $product_id_input, $product_name_input) {
    if (!empty($product_id_input)) {
        return $product_id_input;
    }
    
    if (empty($product_name_input)) {
        throw new Exception("Product name is required");
    }
    
    // Check if product exists
    $stmt = $mysqli->prepare("SELECT id FROM tovary_i_uslugi WHERE naimenovanie = ?");
    $stmt->bind_param("s", $product_name_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new product
    $insert_stmt = $mysqli->prepare("INSERT INTO tovary_i_uslugi (naimenovanie) VALUES (?)");
    $insert_stmt->bind_param("s", $product_name_input);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating product: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Get or create unit
function getOrCreateUnit($mysqli, $unit_id_input, $unit_name_input) {
    if (!empty($unit_id_input)) {
        return $unit_id_input;
    }
    
    if (empty($unit_name_input)) {
        return null;
    }
    
    // Check if unit exists
    $stmt = $mysqli->prepare("SELECT id FROM edinicy_izmereniya WHERE naimenovanie = ?");
    $stmt->bind_param("s", $unit_name_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new unit
    $insert_stmt = $mysqli->prepare("INSERT INTO edinicy_izmereniya (naimenovanie) VALUES (?)");
    $insert_stmt->bind_param("s", $unit_name_input);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating unit: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Get or create series
function getOrCreateSeries($mysqli, $seria_id_input, $seria_name_input, $product_id, $prod_date, $exp_date, $should_update = false) {
    if (!empty($seria_id_input)) {
        return $seria_id_input;
    }
    
    if (empty($seria_name_input)) {
        return null;
    }
    
    // Check if series exists for this product
    if (!empty($product_id)) {
        $stmt = $mysqli->prepare("SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?");
        $stmt->bind_param("si", $seria_name_input, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($should_update && ($prod_date || $exp_date)) {
                updateSeriesData($mysqli, $row['id'], $product_id, $prod_date, $exp_date);
            }
            return $row['id'];
        }
    }
    
    // Create new series
    $insert_stmt = $mysqli->prepare("INSERT INTO serii (nomer, id_tovary_i_uslugi, data_izgotovleniya, srok_godnosti) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("siss", $seria_name_input, $product_id, $prod_date, $exp_date);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating series: " . $insert_stmt->error);
    }
    
    return $mysqli->insert_id;
}

// Update series data
function updateSeriesData($mysqli, $seria_id, $product_id, $prod_date, $exp_date) {
    $update_stmt = $mysqli->prepare("UPDATE serii SET data_izgotovleniya = ?, srok_godnosti = ? WHERE id = ? AND id_tovary_i_uslugi = ?");
    $update_stmt->bind_param("ssii", $prod_date, $exp_date, $seria_id, $product_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Error updating series: " . $update_stmt->error);
    }
}

?>
