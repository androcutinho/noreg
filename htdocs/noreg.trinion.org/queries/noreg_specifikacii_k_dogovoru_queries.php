<?php

// Get cutoff date
$cutoff_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get specification ID from parameter
$spec_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fetch specification header data with organizations
$spec_info = null;
if ($spec_id) {
    $stmt_spec = $mysqli->prepare("
        SELECT
            nsk.id,
            nsk.nomer_specifikacii,
            nsk.nomer_dogovora,
            nsk.data_dogovora,
            nsk.gorod,
            nsk.preambula,
            nsk.usloviya_otgruzki,
            nsk.usloviya_oplaty,
            nsk.inye_usloviya,
            nsk.id_organizacii,
            nsk.id_kontragenti,
            nsk.id_sotrudniki,
            nsk.podpisant_postavshchika_dolzhnost,
            nsk.podpisant_postavshchika_fio,
            nsk.utverzhden,
            org.polnoe_naimenovanie_organizacii AS org_full_name,
            org.sokrashchyonnoe_naimenovanie AS org_short_name,
            org.OGRN AS org_ogrn,
            org.INN AS org_inn,
            org.KPP AS org_kpp,
            org.pochtovyj_adress AS org_address,
            org.v_lice_dlya_documentov AS org_representative,
            kon.polnoe_naimenovanie_organizacii AS kon_full_name,
            kon.sokrashchyonnoe_naimenovanie AS kon_short_name,
            kon.OGRN AS kon_ogrn,
            kon.INN AS kon_inn,
            kon.KPP AS kon_kpp,
            kon.pochtovyj_adress AS kon_address,
            kon.v_lice_dlya_documentov AS kon_representative,
            sr.dolgnost AS sotrudnik_dolgnost,
            sr.familiya AS sotrudnik_familiya,
            sr.imya AS sotrudnik_imya,
            sr.otchestvo AS sotrudnik_otchestvo
        FROM noreg_specifikacii_k_dogovoru nsk
        LEFT JOIN organizacii org ON nsk.id_organizacii = org.id
        LEFT JOIN kontragenti kon ON nsk.id_kontragenti = kon.id
        LEFT JOIN sotrudniki sr ON nsk.id_sotrudniki = sr.id
        WHERE nsk.id = ?
    ");
    $stmt_spec->bind_param('i', $spec_id);
    $stmt_spec->execute();
    $spec_result = $stmt_spec->get_result();
    $spec_info = $spec_result->fetch_assoc();
    $stmt_spec->close();
}

// Fetch data
$stmt = $mysqli->prepare("
    SELECT
        sd.id AS line_id,
        p.naimenovanie AS product_name,
        sd.planiruemaya_data_postavki AS planned_date,
        sd.kolichestvo AS quantity,
        u.naimenovanie AS unit_name,
        sd.cena AS price,
        sd.summa AS amount,
        sd.summa_nds  AS summa_nds,
        sn.stavka_nds AS stavka
    FROM stroki_dokumentov sd
    JOIN tovary_i_uslugi p ON sd.id_tovary_i_uslugi = p.id
    JOIN edinicy_izmereniya u ON sd.id_edinicy_izmereniya = u.id
    JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
    WHERE sd.id_dokumenta = ?
    ORDER BY YEAR(sd.planiruemaya_data_postavki), MONTH(sd.planiruemaya_data_postavki), p.naimenovanie
");
$stmt->bind_param('i', $spec_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $month = strftime('%B %Y', strtotime($row['planned_date']));
    $items[$month][] = $row;
}

// Function to get total count of specifications
function getSpecificationsCount($mysqli) {
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM noreg_specifikacii_k_dogovoru");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}

// Function to fetch all specifications with pagination
function getAllSpecifications($mysqli, $limit = 8, $offset = 0) {
    $stmt = $mysqli->prepare("
        SELECT
            nsk.id,
            nsk.nomer_specifikacii,
            nsk.data_dogovora,
            org.sokrashchyonnoe_naimenovanie AS org_short_name,
            kon.sokrashchyonnoe_naimenovanie AS kon_short_name,
            CONCAT_WS(' ', COALESCE(sr.familiya, NULL), COALESCE(sr.imya, NULL), COALESCE(sr.otchestvo, NULL)) AS employee_name
        FROM noreg_specifikacii_k_dogovoru nsk
        LEFT JOIN organizacii org ON nsk.id_organizacii = org.id
        LEFT JOIN kontragenti kon ON nsk.id_kontragenti = kon.id
        LEFT JOIN sotrudniki sr ON nsk.id_sotrudniki = sr.id
        ORDER BY nsk.data_dogovora DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $specifications = [];
    while ($row = $result->fetch_assoc()) {
        $specifications[] = $row;
    }
    $stmt->close();
    return $specifications;
}
?>
