<?php

function getSravnenieVsekhTovarov($mysqli) {
    
    $query = "
        SELECT DISTINCT
            t.id,
            t.naimenovanie as naimenovanie_tovara
        FROM tovary_i_uslugi t
        LEFT JOIN vetis_ostatki v ON v.id_tovary_i_uslugi = t.id
        LEFT JOIN vetis_tovary_i_uslugi vto ON vto.id_tovary_i_uslugi = t.id
        LEFT JOIN noreg_unf_nomenklatura n ON n.id_vetis = vto.vetis_guid
        WHERE v.id_tovary_i_uslugi IS NOT NULL OR n.id_vetis IS NOT NULL
        ORDER BY t.id
    ";
    
    $result = $mysqli->query($query);
    if (!$result) {
        return [
            'success' => false,
            'error' => 'Не удалось получить список товаров: ' . $mysqli->error
        ];
    }
    
    $tovary = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($tovary)) {
        return [
            'success' => true,
            'tovary' => []
        ];
    }
    
    foreach ($tovary as &$tovar) {
        $sravnenie = getRasnitsaItems($mysqli, $tovar['id']);
        if ($sravnenie['success']) {
            $tovar['vetis_data'] = $sravnenie['vetis_data'];
            $tovar['noreg_unf_data'] = $sravnenie['noreg_unf_data'];
        } else {
            $tovar['vetis_data'] = [];
            $tovar['noreg_unf_data'] = [];
        }
    }
    unset($tovar);
    
    return [
        'success' => true,
        'tovary' => $tovary
    ];
}

function getRasnitsaItems($mysqli, $id_tovara) {
    
    $vetis_query = "
        SELECT
            t.naimenovanie as naimenovanie_tovara_vetis,
            v.ostatok as ostatok_vetis
        FROM tovary_i_uslugi t
        LEFT JOIN vetis_ostatki v ON v.id_tovary_i_uslugi = t.id
        WHERE t.id = ?
    ";
    
    $stmt_vetis = $mysqli->prepare($vetis_query);
    if (!$stmt_vetis) {
        return [
            'success' => false,
            'error' => 'Не удалось подготовить заявление (Vetis): ' . $mysqli->error
        ];
    }
    
    $stmt_vetis->bind_param('i', $id_tovara);
    
    if (!$stmt_vetis->execute()) {
        return [
            'success' => false,
            'error' => 'Не удалось выполнить заявление (Vetis): ' . $stmt_vetis->error
        ];
    }
    
    $vetis_result = $stmt_vetis->get_result();
    $vetis_data = $vetis_result->fetch_all(MYSQLI_ASSOC);
    $stmt_vetis->close();
    
    
    $noreg_unf_query = "
        SELECT
            n.naimenovanie as naimenovanie_tovara_1s,
            o.ostatok as ostatok_1s
        FROM noreg_unf_nomenklatura n
        LEFT JOIN noreg_unf_ostatki o ON o.guid_1c = n.guid_1c
        LEFT JOIN vetis_tovary_i_uslugi vto ON vto.vetis_guid = n.id_vetis
        WHERE vto.id_tovary_i_uslugi = ?
    ";
    
    $stmt_noreg = $mysqli->prepare($noreg_unf_query);
    if (!$stmt_noreg) {
        return [
            'success' => false,
            'error' => 'Не удалось подготовить заявление (1C): ' . $mysqli->error
        ];
    }
    
    $stmt_noreg->bind_param('i', $id_tovara);
    
    if (!$stmt_noreg->execute()) {
        return [
            'success' => false,
            'error' => 'Не удалось выполнить заявление (1C): ' . $stmt_noreg->error
        ];
    }
    
    $noreg_result = $stmt_noreg->get_result();
    $noreg_unf_data = $noreg_result->fetch_all(MYSQLI_ASSOC);
    $stmt_noreg->close();
    
    return [
        'success' => true,
        'vetis_data' => $vetis_data,
        'noreg_unf_data' => $noreg_unf_data
    ];
}