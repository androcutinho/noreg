<?php

/**
 * Get series data by seria_id
 */
function getSeriaById($mysqli, $seria_id) {
    $stmt = $mysqli->prepare("SELECT id, id_tovary_i_uslugi, nomer, data_izgotovleniya, srok_godnosti FROM serii WHERE id = ?");
    $stmt->bind_param("i", $seria_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get product data by product_id (including GUID if exists)
 */
function getProductById($mysqli, $product_id) {
    $stmt = $mysqli->prepare("
        SELECT ti.id, ti.naimenovanie, COALESCE(vtu.vetis_guid, '') as vetis_guid
        FROM tovary_i_uslugi ti
        LEFT JOIN vetis_tovary_i_uslugi vtu ON ti.id = vtu.id_tovary_i_uslugi
        WHERE ti.id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Check if series number exists
 */
function seriesNumberExists($mysqli, $nomer) {
    $stmt = $mysqli->prepare("SELECT id FROM serii WHERE nomer = ?");
    $stmt->bind_param("s", $nomer);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Check if series exists for specific product
 */
function productSeriesExists($mysqli, $nomer, $product_id) {
    $stmt = $mysqli->prepare("SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?");
    $stmt->bind_param("si", $nomer, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Update series dates
 */
function updateSeriesDates($mysqli, $data_izgotovleniya, $srok_godnosti, $seria_id) {
    $stmt = $mysqli->prepare("UPDATE serii SET data_izgotovleniya = ?, srok_godnosti = ? WHERE id = ?");
    $stmt->bind_param("ssi", $data_izgotovleniya, $srok_godnosti, $seria_id);
    return $stmt->execute();
}

/**
 * Insert new series
 */
function insertSeries($mysqli, $product_id, $nomer, $data_izgotovleniya, $srok_godnosti) {
    $stmt = $mysqli->prepare("INSERT INTO serii (id_tovary_i_uslugi, nomer, data_izgotovleniya, srok_godnosti) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $product_id, $nomer, $data_izgotovleniya, $srok_godnosti);
    return $stmt->execute();
}

/**
 * Update series with new number and dates
 */
function updateSeries($mysqli, $nomer, $data_izgotovleniya, $srok_godnosti, $seria_id) {
    $stmt = $mysqli->prepare("UPDATE serii SET nomer = ?, data_izgotovleniya = ?, srok_godnosti = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nomer, $data_izgotovleniya, $srok_godnosti, $seria_id);
    return $stmt->execute();
}

/**
 * Save or update product GUID
 */
function saveProductGUID($mysqli, $product_id, $vetis_guid) {
    // Check if GUID record already exists
    $check_stmt = $mysqli->prepare("SELECT id_tovary_i_uslugi FROM vetis_tovary_i_uslugi WHERE id_tovary_i_uslugi = ?");
    $check_stmt->bind_param("i", $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing GUID
        $stmt = $mysqli->prepare("UPDATE vetis_tovary_i_uslugi SET vetis_guid = ? WHERE id_tovary_i_uslugi = ?");
        $stmt->bind_param("si", $vetis_guid, $product_id);
        return $stmt->execute();
    } else {
        // Insert new GUID record
        $stmt = $mysqli->prepare("INSERT INTO vetis_tovary_i_uslugi (id_tovary_i_uslugi, vetis_guid) VALUES (?, ?)");
        $stmt->bind_param("is", $product_id, $vetis_guid);
        return $stmt->execute();
    }
}

?>
