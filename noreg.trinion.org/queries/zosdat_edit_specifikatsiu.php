<?php

require_once 'id_index_helper.php';


function loadOrderDataForSpecification($mysqli, $zakaz_id, $ot_postavshchika = false) {
    $zakaz_id = intval($zakaz_id);
    $data = [
        'nomer_zakaza' => null,
        'data_dogovora' => date('Y-m-d'),
        'nomer_dogovora' => '',
        'id_organizacii' => '',
        'naimenovanie_organizacii' => '',
        'id_kontragenta' => '',
        'naimenovanie_kontragenta' => '',
        'id_sotrudnika' => '',
        'naimmenovanie_sotrudnika' => '',
        'line_items' => []
    ];
    
    if ($ot_postavshchika) {
        $zakaz = fetchZakazHeader($mysqli, $zakaz_id);
    } else {
        $zakaz = getZakazHeader($mysqli, $zakaz_id);
    }
    
    if (!$zakaz) return $data;
    
    $data['nomer_zakaza'] = $zakaz['nomer'];
    $data['data_dogovora'] = $zakaz['data_dokumenta'] ?? date('Y-m-d');
    $data['nomer_dogovora'] = $zakaz['nomer'] ?? '';
    $data['id_organizacii'] = $ot_postavshchika ? ($zakaz['id_kontragenti_postavshchik'] ?? '') : ($zakaz['id_kontragenti_postavshik'] ?? '');
    $data['id_kontragenta'] = $ot_postavshchika ? ($zakaz['id_kontragenti_pokupatel'] ?? '') : ($zakaz['id_kontragenti_pokupatel'] ?? '');
    $data['id_sotrudnika'] = $zakaz['id_otvetstvennyj'] ?? '';
    
    
    $org_query = $mysqli->query("SELECT naimenovanie FROM kontragenti WHERE id = " . intval($data['id_organizacii']));
    if ($org = $org_query->fetch_assoc()) {
        $data['naimenovanie_organizacii'] = $org['naimenovanie'];
    }
    
    
    $vendor_query = $mysqli->query("SELECT naimenovanie FROM kontragenti WHERE id = " . intval($data['id_kontragenta']));
    if ($vendor = $vendor_query->fetch_assoc()) {
        $data['naimenovanie_kontragenta'] = $vendor['naimenovanie'];
    }
    
    
    $sotrudnik_query = $mysqli->query("SELECT CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) as fio FROM sotrudniki WHERE id = " . intval($data['id_sotrudnika']));
    if ($sotrudnik = $sotrudnik_query->fetch_assoc()) {
        $data['naimmenovanie_sotrudnika'] = trim($sotrudnik['fio']);
    }

    $zakaz_line_items = $ot_postavshchika ? getZakazStrokiItems($mysqli, $zakaz['id_index']) : getZakazStrokiItems($mysqli, $zakaz['id_index']);
    if ($zakaz_line_items) {
        foreach ($zakaz_line_items as $item) {
            $line_item = [
                'naimenovanie_tovara' => $item['naimenovanie_tovara'] ?? '',
                'id_tovary_i_uslugi' => $item['id_tovary_i_uslugi'] ?? '',
                'naimenovanie_edinitsii' => $item['naimenovanie_edinitsii'] ?? '',
                'id_edinicy_izmereniya' => $item['id_edinicy_izmereniya'] ?? '',
                'kolichestvo' => $item['kolichestvo'] ?? '',
                'cena' => $item['ed_cena'] ?? '',
                'id_stavka_nds' => $item['id_stavka_nds'] ?? '',
                'summa_nds' => $item['summa_nds'] ?? '',
                'summa' => $item['summa'] ?? '',
                'planiruemaya_data_postavki' => ''
            ];
            $data['line_items'][] = $line_item;
        }
    }
    
    return $data;
}


function getAllNdsRates($mysqli) {
    $stavki_nds = [];
    $nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
    $nds_result = $mysqli->query($nds_query);
    if ($nds_result) {
        $stavki_nds = $nds_result->fetch_all(MYSQLI_ASSOC);
    }
    return $stavki_nds;
}


function getAllUnits($mysqli) {
    $units = [];
    $units_query = "SELECT id, naimenovanie FROM edinicy_izmereniya ORDER BY naimenovanie ASC";
    $units_result = $mysqli->query($units_query);
    if ($units_result) {
        $units = $units_result->fetch_all(MYSQLI_ASSOC);
    }
    return $units;
}


function createSpecification($mysqli, $data, $nomer_from_order = null, $zakaz_id = null, $ot_postavshchika = false, $pokupatelya = false) {
    $data_dogovora = $data['data_dogovora'];
    $gorod = $data['gorod'];
    $nomer_dogovora = $data['nomer_dogovora'];
    $id_organizacii = intval($data['id_organizacii']);
    $id_kontragenta = intval($data['id_kontragenta']);
    $usloviya_otgruzki = $data['usloviya_otgruzki'];
    $usloviya_oplaty = $data['usloviya_oplaty'];
    $inye_usloviya = $data['inye_usloviya'];
    $id_sotrudnika = intval($data['id_sotrudnika']);
    $podpisant_postavshchika_dolzhnost = $data['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $data['podpisant_postavshchika_fio'];
    $utverzhden = 0;
    $id_index = getNextIdIndex($mysqli);
    $dlya_zakaza_pokupatelya = $pokupatelya ? 1 : 0;
    $dlya_zakaza_postavshiku = $ot_postavshchika ? 1 : 0;
    
    
    $id_zakazy_pokupatelei = ($pokupatelya && $zakaz_id) ? intval($zakaz_id) : null;
    $id_zakazy_postavshchikam = ($ot_postavshchika && $zakaz_id) ? intval($zakaz_id) : null;
    
    $stmt = $mysqli->prepare("
        INSERT INTO noreg_specifikacii_k_zakazam  
        (data_dogovora, nomer_specifikacii, gorod, nomer_dogovora, id_kontragenti_postavshik, id_kontragenti_pokupatel, usloviya_otgruzki, usloviya_oplaty, inye_usloviya, id_sotrudniki, podpisant_postavshchika_dolzhnost, podpisant_postavshchika_fio, id_index, dlya_zakaza_pokupatelya, dlya_zakaza_postavshiku, id_zakazy_pokupatelei, id_zakazy_postavshchikam)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssiisssissiiiii',
        $data_dogovora,
        $gorod,
        $nomer_dogovora,
        $id_organizacii,
        $id_kontragenta,
        $usloviya_otgruzki,
        $usloviya_oplaty,
        $inye_usloviya,
        $id_sotrudnika,
        $podpisant_postavshchika_dolzhnost,
        $podpisant_postavshchika_fio,
        $id_index,
        $dlya_zakaza_pokupatelya,
        $dlya_zakaza_postavshiku,
        $id_zakazy_pokupatelei,
        $id_zakazy_postavshchikam
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при создании спецификации: ' . $stmt->error];
    }
    
    $doc_id = $mysqli->insert_id;
    $stmt->close();
    
    $update_stmt = $mysqli->prepare("UPDATE noreg_specifikacii_k_zakazam  SET nomer_specifikacii = ? WHERE id = ?");
    $update_stmt->bind_param('ii', $doc_id, $doc_id);
    
    if (!$update_stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при установке номера спецификации: ' . $update_stmt->error];
    }
    
    $update_stmt->close();
    
    if (!empty($zakaz_id)) {
        $order_table = $ot_postavshchika ? 'zakazy_postavshchikam' : 'zakazy_pokupatelei';
        linkDocumentsByIndex($mysqli, $zakaz_id, $doc_id, 'noreg_specifikacii_k_zakazam', $order_table);
    }
    
    return ['success' => true, 'id' => $doc_id];
}


function createLineItem($mysqli, $doc_id, $tovar, $id_index) {
    $id_tovara = intval($tovar['id_tovara']);
    $id_edinitsii = intval($tovar['id_edinitsii']);
    $nds_id = intval($tovar['nds_id']);
    $kolichestvo = floatval($tovar['kolichestvo'] ?? 0);
    $cena = floatval($tovar['cena'] ?? 0);
    $summa_nds = floatval($tovar['summa_stavka'] ?? 0);
    $summa = floatval($tovar['summa'] ?? 0);
    $planiruemaya_data_postavki = $tovar['planiruemaya_data_postavki'] ?? null;
    $seria_id = null;
    
    $stmt = $mysqli->prepare("
        INSERT INTO stroki_dokumentov 
        (id_dokumenta, id_index, id_tovary_i_uslugi, id_serii, id_edinicy_izmereniya, id_stavka_nds, kolichestvo, cena, summa_nds, summa, planiruemaya_data_postavki)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iiiiiidddds',
        $doc_id,
        $id_index,
        $id_tovara,
        $seria_id,
        $id_edinitsii,
        $nds_id,
        $kolichestvo,
        $cena,
        $summa_nds,
        $summa,
        $planiruemaya_data_postavki
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при добавлении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}


function createSpecificationLineItems($mysqli, $doc_id, $tovary, $id_index) {
    foreach ($tovary as $tovar) {
        if (empty($tovar['id_tovara'])) continue;
        
        $result = createLineItem($mysqli, $doc_id, $tovar, $id_index);
        if (!$result['success']) {
            return $result;
        }
    }
    
    return ['success' => true];
}


function getSpecificationById($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    
    $stmt = $mysqli->prepare("
        SELECT id, data_dogovora, nomer_specifikacii, gorod, nomer_dogovora, 
               id_kontragenti_postavshik, id_kontragenti_pokupatel, usloviya_otgruzki, usloviya_oplaty, 
               inye_usloviya, id_sotrudniki, podpisant_postavshchika_dolzhnost, 
               podpisant_postavshchika_fio, utverzhden, id_index
        FROM noreg_specifikacii_k_zakazam 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $spec_id);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при получении спецификации: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $spec = $result->fetch_assoc();
    $stmt->close();
    
    if (!$spec) {
        return ['success' => false, 'error' => 'Спецификация не найдена'];
    }
    
    return ['success' => true, 'data' => $spec];
}


function fetchSpecificationHeader($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    
    $stmt = $mysqli->prepare("
        SELECT nsk.id, 
        nsk.nomer_specifikacii as nomer, 
        nsk.data_dogovora AS data_dokumenta, 
        nsk.utverzhden,
        CONCAT(COALESCE(so.familiya, ''), ' ', COALESCE(so.imya, ''), ' ', COALESCE(so.otchestvo, '')) AS naimenovanie_otvetstvennogo
        FROM noreg_specifikacii_k_zakazam  nsk
        LEFT JOIN sotrudniki so ON nsk.id_sotrudniki = so.id
        WHERE nsk.id = ?
    ");
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $spec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $spec = $result->fetch_assoc();
    $stmt->close();
    
    return $spec;
}


function getSpecificationLineItems($mysqli, $id_index) {
    $id_index = intval($id_index);
    
    $stmt = $mysqli->prepare("
        SELECT id, id_tovary_i_uslugi, id_serii, id_edinicy_izmereniya, id_stavka_nds, 
               kolichestvo, cena, summa_nds, summa, planiruemaya_data_postavki
        FROM stroki_dokumentov
        WHERE id_index = ?
        ORDER BY id ASC
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $id_index);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при получении товаров: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    

    foreach ($items as &$item) {
        
        $product_result = $mysqli->query("SELECT naimenovanie FROM tovary_i_uslugi WHERE id = " . intval($item['id_tovary_i_uslugi']));
        if ($product_result && $tovar = $product_result->fetch_assoc()) {
            $item['naimenovanie_tovara'] = $tovar['naimenovanie'];
        } else {
            $item['naimenovanie_tovara'] = '';
        }
        
        
        if ($item['id_serii'] > 0) {
            $series_result = $mysqli->query("SELECT nomer FROM serii WHERE id = " . intval($item['id_serii']));
            if ($series_result && $series = $series_result->fetch_assoc()) {
                $item['seria_name'] = $series['nomer'];
            } else {
                $item['seria_name'] = '';
            }
        } else {
            $item['seria_name'] = '';
        }
        
        
        $unit_result = $mysqli->query("SELECT naimenovanie FROM edinicy_izmereniya WHERE id = " . intval($item['id_edinicy_izmereniya']));
        if ($unit_result && $edinitsa = $unit_result->fetch_assoc()) {
            $item['naimenovanie_edinitsii'] = $edinitsa['naimenovanie'];
        } else {
            $item['naimenovanie_edinitsii'] = '';
        }
    }
    
    return ['success' => true, 'data' => $items];
}


function updateSpecification($mysqli, $spec_id, $data) {
    $spec_id = intval($spec_id);
    $data_dogovora = $data['data_dogovora'];
    $gorod = $data['gorod'];
    $nomer_dogovora = $data['nomer_dogovora'];
    $id_organizacii = intval($data['id_organizacii']);
    $id_kontragenta = intval($data['id_kontragenta']);
    $usloviya_otgruzki = $data['usloviya_otgruzki'];
    $usloviya_oplaty = $data['usloviya_oplaty'];
    $inye_usloviya = $data['inye_usloviya'];
    $id_sotrudnika = intval($data['id_sotrudnika']);
    $podpisant_postavshchika_dolzhnost = $data['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $data['podpisant_postavshchika_fio'];
    
    $stmt = $mysqli->prepare("
        UPDATE noreg_specifikacii_k_zakazam 
        SET data_dogovora = ?, gorod = ?, nomer_dogovora = ?, 
            id_kontragenti_postavshik = ?,  id_kontragenti_pokupatel = ?, usloviya_otgruzki = ?, usloviya_oplaty = ?, 
            inye_usloviya = ?, id_sotrudniki = ?, podpisant_postavshchika_dolzhnost = ?, 
            podpisant_postavshchika_fio = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param(
        'sssiisssissi',
        $data_dogovora,
        $gorod,
        $nomer_dogovora,
        $id_organizacii,
        $id_kontragenta,
        $usloviya_otgruzki,
        $usloviya_oplaty,
        $inye_usloviya,
        $id_sotrudnika,
        $podpisant_postavshchika_dolzhnost,
        $podpisant_postavshchika_fio,
        $spec_id
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при обновлении спецификации: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}


function deleteLineItem($mysqli, $line_item_id) {
    $line_item_id = intval($line_item_id);
    
    $stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id = ?");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param('i', $line_item_id);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при удалении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}


function updateLineItem($mysqli, $line_item_id, $tovar) {
    $line_item_id = intval($line_item_id);
    $id_tovara = intval($tovar['id_tovara']);
    $id_edinitsii = intval($tovar['id_edinitsii']);
    $nds_id = intval($tovar['nds_id']);
    $kolichestvo = floatval($tovar['kolichestvo'] ?? 0);
    $cena = floatval($tovar['cena'] ?? 0);
    $summa_nds = floatval($tovar['summa_stavka'] ?? 0);
    $summa = floatval($tovar['summa'] ?? 0);
    $planiruemaya_data_postavki = $tovar['planiruemaya_data_postavki'] ?? null;
    $seria_id = null;
    
    $stmt = $mysqli->prepare("
        UPDATE stroki_dokumentov
        SET id_tovary_i_uslugi = ?, id_serii = ?, id_edinicy_izmereniya = ?, id_stavka_nds = ?, 
            kolichestvo = ?, cena = ?, summa_nds = ?, summa = ?, planiruemaya_data_postavki = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса: ' . $mysqli->error];
    }
    
    $stmt->bind_param(
        'iiiiidddsi',
        $id_tovara,
        $seria_id,
        $id_edinitsii,
        $nds_id,
        $kolichestvo,
        $cena,
        $summa_nds,
        $summa,
        $planiruemaya_data_postavki,
        $line_item_id
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при обновлении товара: ' . $stmt->error];
    }
    
    $stmt->close();
    return ['success' => true];
}

function updateSpecificationLineItems($mysqli, $spec_id, $tovary) {
    
    $get_index_query = "SELECT id_index FROM noreg_specifikacii_k_zakazam  WHERE id = ?";
    $stmt = $mysqli->prepare($get_index_query);
    $stmt->bind_param('i', $spec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    $stmt->close();
    
    if (!$doc) {
        return ['success' => false, 'error' => 'Спецификация не найдена'];
    }
    
    $id_index = $doc['id_index'];
    
    
    $delete_stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id_index = ?");
    if (!$delete_stmt) {
        return ['success' => false, 'error' => 'Ошибка при подготовке запроса удаления: ' . $mysqli->error];
    }
    
    $delete_stmt->bind_param('i', $id_index);
    
    if (!$delete_stmt->execute()) {
        return ['success' => false, 'error' => 'Ошибка при удалении товаров: ' . $delete_stmt->error];
    }
    
    $delete_stmt->close();
    
    
    foreach ($tovary as $tovar) {
        if (empty($tovar['id_tovara'])) continue;
        
        $result = createLineItem($mysqli, $spec_id, $tovar, $id_index);
        if (!$result['success']) {
            return $result;
        }
    }
    
    return ['success' => true];
}


function deleteSpecification($mysqli, $spec_id) {
    $spec_id = intval($spec_id);
    $mysqli->begin_transaction();
    
    try {
        $get_index_query = "SELECT id_index FROM noreg_specifikacii_k_zakazam  WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $spec_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Спецификация не найдена');
        }
        
        $id_index = $doc['id_index'];
        
        
        $delete_items_stmt = $mysqli->prepare("DELETE FROM stroki_dokumentov WHERE id_index = ?");
        if (!$delete_items_stmt) {
            throw new Exception('Ошибка при подготовке запроса удаления товаров: ' . $mysqli->error);
        }
        $delete_items_stmt->bind_param('i', $id_index);
        if (!$delete_items_stmt->execute()) {
            throw new Exception('Ошибка при удалении товаров: ' . $delete_items_stmt->error);
        }
        $delete_items_stmt->close();
        
        
        $delete_spec_stmt = $mysqli->prepare("DELETE FROM noreg_specifikacii_k_zakazam  WHERE id = ?");
        if (!$delete_spec_stmt) {
            throw new Exception('Ошибка при подготовке запроса удаления спецификации: ' . $mysqli->error);
        }
        $delete_spec_stmt->bind_param('i', $spec_id);
        if (!$delete_spec_stmt->execute()) {
            throw new Exception('Ошибка при удалении спецификации: ' . $delete_spec_stmt->error);
        }
        $delete_spec_stmt->close();
        
        
        $mysqli->commit();
        
        return ['success' => true];
    } catch (Exception $e) {
    
        $mysqli->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>
