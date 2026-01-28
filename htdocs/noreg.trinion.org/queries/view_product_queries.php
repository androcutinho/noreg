<?php

/**
 * View Product Details - Database Queries
 */

function fetchDocumentHeader($mysqli, $document_id) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        org.naimenovanie as organization,
        ps.naimenovanie as vendor,
        u.user_name as responsible
    FROM postupleniya_tovarov pt
    LEFT JOIN organizacii org ON pt.id_organizacii = org.id
    LEFT JOIN postavshchiki ps ON pt.id_postavshchika = ps.id
    LEFT JOIN users u ON pt.id_otvetstvennyj = u.user_id
    WHERE pt.id = ?";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        die("SQL error: " . $mysqli->error);
    }

    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();

    return $document;
}

function fetchDocumentLineItems($mysqli, $document_id) {
    $sql = "SELECT 
        sd.id,
        ti.naimenovanie as product_name,
        ser.nomer as seria_name,
        ser.data_izgotovleniya,
        ser.srok_godnosti,
        sd.kolichestvo_postupleniya as quantity,
        sd.cena_postupleniya as unit_price,
        eu.naimenovanie as unit_name,
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

function calculateTotals($line_items) {
    $subtotal = 0;
    $vat_total = 0;

    foreach ($line_items as $item) {
        $subtotal += $item['total_amount'];
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
