<?php

require_once __DIR__ . '/../config/database_config.php';
require_once __DIR__ . '/id_index_helper.php';
require_once __DIR__ . '/entity_helpers.php';


function sozdatPribytieDokument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        $sklad_id_input = isset($data['id_sklada']) ? $data['id_sklada'] : null;
        $naimenovanie_sklada_input = isset($data['naimenovanie_sklada']) ? $data['naimenovanie_sklada'] : null;
        $data['id_sklada'] = getOrCreateWarehouse($mysqli, $sklad_id_input, $naimenovanie_sklada_input);
        
        $id_postavschika_input = isset($data['id_postavschika']) ? $data['id_postavschika'] : null;
        $naimenovanie_postavschika_input = isset($data['naimenovanie_postavschika']) ? $data['naimenovanie_postavschika'] : null;
        $data['id_postavschika'] = getOrCreateVendor($mysqli, $id_postavschika_input, $naimenovanie_postavschika_input);
        
        $org_id_input = isset($data['id_organizacii']) ? $data['id_organizacii'] : null;
        $org_name_input = isset($data['naimenovanie_organizacii']) ? $data['naimenovanie_organizacii'] : null;
        $data['id_organizacii'] = getOrCreateOrganization($mysqli, $org_id_input, $org_name_input);
        
        $id_sklada = intval($data['id_sklada']);
        $id_organizacii = intval($data['id_organizacii']);
        $id_otvetstvennogo = intval($data['id_otvetstvennogo']);
        $id_postavschika = intval($data['id_postavschika']);
        
        $datetime = $data['product_date'];
        $datetime = str_replace('T', ' ', $datetime) . ':00';
        
        $utverzhden = 0;
        
        $id_index = getNextIdIndex($mysqli);
        
        $arrival_sql = "INSERT INTO postupleniya_tovarov (id_kontragenti_postavshik, id_kontragenti_pokupatel, id_sklada, id_otvetstvennyj, data_dokumenta, utverzhden, id_index) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $arrival_stmt = $mysqli->stmt_init();
        
        if (!$arrival_stmt->prepare($arrival_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $arrival_stmt->bind_param(
            "iiiisii",
            $id_postavschika,
            $id_organizacii,
            $id_sklada,
            $id_otvetstvennogo,
            $datetime,
            $utverzhden,
            $id_index
        );
        
        if (!$arrival_stmt->execute()) {
            throw new Exception("Ошибка при создании документа поступления: " . $mysqli->error);
        }
        
        $document_id = $mysqli->insert_id;
        
        
        $tovary_data = $data['tovary'];
        foreach ($tovary_data as $tovar) {
            
            if (empty($tovar['cena']) || empty($tovar['kolichestvo']) || empty($tovar['nds_id'])) {
                continue; 
            }
            
            
            $prod_id_input = isset($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $prod_name_input = isset($tovar['naimenovanie_tovara']) ? $tovar['naimenovanie_tovara'] : null;
            $tovar['id_tovara'] = getOrCreateProduct($mysqli, $prod_id_input, $prod_name_input);
            
            if (empty($tovar['id_tovara'])) {
                continue;
            }
            
            $id_edinitsii_input = isset($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            $naimenovanie_edinitsii_input = isset($tovar['naimenovanie_edinitsii']) ? $tovar['naimenovanie_edinitsii'] : null;
            $tovar['id_edinitsii'] = getOrCreateUnit($mysqli, $id_edinitsii_input, $naimenovanie_edinitsii_input);
            
            $prod_date = !empty($data['data_izgotovleniya']) ? $data['data_izgotovleniya'] : null;
            $exp_date = !empty($data['srok_godnosti']) ? $data['srok_godnosti'] : null;
            
            $id_serii_input = isset($tovar['id_serii']) ? $tovar['id_serii'] : null;
            $naimenovanie_serii_input = isset($tovar['naimenovanie_serii']) ? $tovar['naimenovanie_serii'] : null;
            $tovar['id_serii'] = getOrCreateSeries($mysqli, $id_serii_input, $naimenovanie_serii_input, $tovar['id_tovara'], $prod_date, $exp_date, true);
            
            $goods_id = intval($tovar['id_tovara']);
            $nds_id = intval($tovar['nds_id']);
            $cena = floatval($tovar['cena']);
            $kolichestvo = floatval($tovar['kolichestvo']);
            $summa = floatval($tovar['summa']);
            $summa_stavka = !empty($tovar['summa_stavka']) ? floatval($tovar['summa_stavka']) : 0;
            $id_serii = !empty($tovar['id_serii']) ? intval($tovar['id_serii']) : 0;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? intval($tovar['id_edinitsii']) : 0;
            
            
            if ($id_serii > 0) {
                updateSeriesData($mysqli, $id_serii, $goods_id, $prod_date, $exp_date);
            }
            
            
            $line_sql = "INSERT INTO stroki_dokumentov (id_dokumenta, id_index, id_tovary_i_uslugi, id_stavka_nds, cena, kolichestvo, summa, id_serii, id_edinicy_izmereniya, summa_nds) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error . " | SQL: " . $line_sql);
            }
            
            $line_stmt->bind_param(
                "iiiidddiid",
                $document_id,
                $id_index,
                $goods_id,
                $nds_id,
                $cena,
                $kolichestvo,
                $summa,
                $id_serii,
                $id_edinitsii,
                $summa_stavka
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


function getDokumentHeader($mysqli, $document_id) {
    $sql = "SELECT 
        pt.id,
        pt.data_dokumenta,
        pt.utverzhden,
        pt.zakryt,
        pt.id_kontragenti_pokupatel,
        pt.id_kontragenti_postavshik,
        pt.id_sklada,
        pt.id_otvetstvennyj,
        pt.id_index,
        kon.naimenovanie as org_name,
        kon.id as org_id,
        kon.inn as org_inn,
        kon.kpp as org_kpp,
        ps.naimenovanie as naimenovanie_postavschika,
        ps.id as id_postavschika,
        ps.inn as inn_postavschika,
        ps.kpp as kpp_postavschika,
        sl.naimenovanie as naimenovanie_sklada,
        sl.id as id_sklada,
        CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) as  naimenovanie_otvetstvennogo, 
        s.id as id_otvetstvennogo
    FROM postupleniya_tovarov pt
    LEFT JOIN kontragenti kon ON pt.id_kontragenti_pokupatel = kon.id
    LEFT JOIN kontragenti ps ON pt.id_kontragenti_postavshik = ps.id
    LEFT JOIN sklady sl ON pt.id_sklada = sl.id
    LEFT JOIN sotrudniki s ON pt.id_otvetstvennyj = s.id
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


function getStrokiDokumentovItems($mysqli, $id_index) {
    $sql = "SELECT 
        sd.id,
        sd.id_tovary_i_uslugi as id_tovara,
        sd.id_serii,
        sd.id_edinicy_izmereniya,
        ti.naimenovanie as naimenovanie_tovara,
        ser.id as id_serii,
        ser.nomer as naimenovanie_serii,
        ser.data_izgotovleniya,
        ser.srok_godnosti,
        eu.naimenovanie as naimenovanie_edinitsii,
        sd.kolichestvo,
        sd.cena as ed_cena,
        sd.id_stavka_nds  as nds_id,
        sn.stavka_nds as stavka_nds,
        sd.summa as obshchaya_summa,
        sd.summa_nds
    FROM stroki_dokumentov sd
    LEFT JOIN tovary_i_uslugi ti ON sd.id_tovary_i_uslugi = ti.id
    LEFT JOIN serii ser ON ser.id = sd.id_serii AND ser.id_tovary_i_uslugi = sd.id_tovary_i_uslugi
    LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
    LEFT JOIN edinicy_izmereniya eu ON sd.id_edinicy_izmereniya = eu.id
    WHERE sd.id_index = ?
    ORDER BY sd.id ASC";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        die("SQL error: " . $mysqli->error);
    }

    $stmt->bind_param("i", $id_index);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = array();

    while ($row = $result->fetch_assoc()) {
        $line_items[] = $row;
    }

    return $line_items;
}

function obnovitPribytieDokument($mysqli, $document_id, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        $document = getDokumentHeader($mysqli, $document_id);
        if (!$document) {
            throw new Exception("Документ не найден");
        }
        
        $was_approved = $document['utverzhden'] == 1;
        
        if ($was_approved) {
            $reverseResult = handleUtverzhdenChange($mysqli, $document_id, 0);
            if (!$reverseResult['success']) {
                throw new Exception($reverseResult['error']);
            }
        }
        
        $sklad_id_input = isset($data['id_sklada']) ? $data['id_sklada'] : null;
        $naimenovanie_sklada_input = isset($data['naimenovanie_sklada']) ? $data['naimenovanie_sklada'] : null;
        $data['id_sklada'] = getOrCreateWarehouse($mysqli, $sklad_id_input, $naimenovanie_sklada_input);
        
        $id_postavschika_input = isset($data['id_postavschika']) ? $data['id_postavschika'] : null;
        $naimenovanie_postavschika_input = isset($data['naimenovanie_postavschika']) ? $data['naimenovanie_postavschika'] : null;
        $data['id_postavschika'] = getOrCreateVendor($mysqli, $id_postavschika_input, $naimenovanie_postavschika_input);
        
        $org_id_input = isset($data['id_organizacii']) ? $data['id_organizacii'] : null;
        $org_name_input = isset($data['naimenovanie_organizacii']) ? $data['naimenovanie_organizacii'] : null;
        $data['id_organizacii'] = getOrCreateOrganization($mysqli, $org_id_input, $org_name_input);
        
        
        if (empty($data['id_otvetstvennogo'])) {
            throw new Exception("Пожалуйста, выберите ответственного из списка");
        }
        $id_otvetstvennogo = intval($data['id_otvetstvennogo']);
        
        $id_sklada = intval($data['id_sklada']);
        $id_organizacii = intval($data['id_organizacii']);
        $id_postavschika = intval($data['id_postavschika']);
        
        
        $doc_sql = "UPDATE postupleniya_tovarov SET 
            data_dokumenta = ?,
            id_sklada = ?,
            id_kontragenti_postavshik = ?,
            id_kontragenti_pokupatel = ?,
            id_otvetstvennyj = ?
        WHERE id = ?";
        
        $doc_stmt = $mysqli->stmt_init();
        if (!$doc_stmt->prepare($doc_sql)) {
            throw new Exception("Ошибка подготовки запроса документа: " . $mysqli->error);
        }
        
        $doc_stmt->bind_param(
            "siiiii",
            $data['product_date'],
            $data['id_sklada'],
            $data['id_postavschika'],
            $data['id_organizacii'],
            $id_otvetstvennogo,
            $document_id
        );
        
        if (!$doc_stmt->execute()) {
            throw new Exception("Ошибка обновления документа: " . $doc_stmt->error);
        }
        
        
        $get_index_query = "SELECT id_index FROM postupleniya_tovarov WHERE id = ?";
        $get_stmt = $mysqli->stmt_init();
        $get_stmt->prepare($get_index_query);
        $get_stmt->bind_param('i', $document_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $doc_data = $get_result->fetch_assoc();
        $get_stmt->close();
        
        if (!$doc_data) {
            throw new Exception("Документ больше не существует");
        }
        
        $id_index = $doc_data['id_index'];
        
        
        $delete_sql = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $delete_stmt = $mysqli->stmt_init();
        if (!$delete_stmt->prepare($delete_sql)) {
            throw new Exception("Ошибка подготовки удаления строк: " . $mysqli->error);
        }
        
        $delete_stmt->bind_param("i", $id_index);
        if (!$delete_stmt->execute()) {
            throw new Exception("Ошибка удаления старых строк: " . $delete_stmt->error);
        }
        
        
        $tovary_data = $data['tovary'];
        foreach ($tovary_data as $tovar) {
        
            if (empty($tovar['cena']) || empty($tovar['kolichestvo']) || empty($tovar['nds_id'])) {
                continue;
            }
            
            
            $prod_id_input = isset($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $prod_name_input = isset($tovar['naimenovanie_tovara']) ? $tovar['naimenovanie_tovara'] : null;
            $tovar['id_tovara'] = getOrCreateProduct($mysqli, $prod_id_input, $prod_name_input);
            
            if (empty($tovar['id_tovara'])) {
                continue;
            }
            
            $id_edinitsii_input = isset($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            $naimenovanie_edinitsii_input = isset($tovar['naimenovanie_edinitsii']) ? $tovar['naimenovanie_edinitsii'] : null;
            $tovar['id_edinitsii'] = getOrCreateUnit($mysqli, $id_edinitsii_input, $naimenovanie_edinitsii_input);
            
            $id_serii_input = isset($tovar['id_serii']) ? $tovar['id_serii'] : null;
            $naimenovanie_serii_input = isset($tovar['naimenovanie_serii']) ? $tovar['naimenovanie_serii'] : null;
            $tovar['id_serii'] = getOrCreateSeries($mysqli, $id_serii_input, $naimenovanie_serii_input, $tovar['id_tovara'], null, null, false);
            
            
            $goods_id = intval($tovar['id_tovara']);
            $nds_id = intval($tovar['nds_id']);
            $cena = floatval($tovar['cena']);
            $kolichestvo = floatval($tovar['kolichestvo']);
            $summa = floatval($tovar['summa']);
            $summa_stavka = !empty($tovar['summa_stavka']) ? floatval($tovar['summa_stavka']) : 0;
            $id_serii = !empty($tovar['id_serii']) ? intval($tovar['id_serii']) : 0;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? intval($tovar['id_edinitsii']) : 0;
            
            $line_sql = "INSERT INTO stroki_dokumentov (id_dokumenta, id_index, id_tovary_i_uslugi, id_stavka_nds, cena, kolichestvo, summa, id_serii, id_edinicy_izmereniya, summa_nds) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiiidddiid",
                $document_id,
                $id_index,
                $goods_id,
                $nds_id,
                $cena,
                $kolichestvo,
                $summa,
                $id_serii,
                $id_edinitsii,
                $summa_stavka
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки документа: " . $mysqli->error);
            }
        }
        

        if ($was_approved) {
            $reapproveResult = handleUtverzhdenChange($mysqli, $document_id, 1);
            if (!$reapproveResult['success']) {
                throw new Exception($reapproveResult['error']);
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


function udalitPribytieDokument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_index_query = "SELECT id_index FROM postupleniya_tovarov WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $id_index = $doc['id_index'];
        
        
        $delete_items_query = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $stmt = $mysqli->prepare($delete_items_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления строк: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $id_index);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении строк товара: ' . $stmt->error);
        }
        $stmt->close();
        
        
        $delete_doc_query = "DELETE FROM postupleniya_tovarov WHERE id = ?";
        $stmt = $mysqli->prepare($delete_doc_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления документа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $document_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении документа: ' . $stmt->error);
        }
        $stmt->close();
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Документ успешно удален'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}



function calculateTotals($line_items) {
    $subtotal = 0;
    $vat_total = 0;

    foreach ($line_items as $item) {
        $subtotal += floatval($item['obshchaya_summa']);
    }

    if (!empty($line_items)) {
        $first_item = $line_items[0];
        $stavka_nds = floatval($first_item['stavka_nds']);
        $vat_total = ($subtotal * $stavka_nds) / 100;
    }

    return array(
        'subtotal' => $subtotal,
        'vat_total' => $vat_total,
        'total_due' => $subtotal + $vat_total
    );
}


function handleUtverzhdenChange($mysqli, $document_id, $new_utverzhden_value) {
    try {
        
        $document = getDokumentHeader($mysqli, $document_id);
        if (!$document) {
            return [
                'success' => false,
                'error' => 'Документ не найден'
            ];
        }
        
        
        $line_items = getStrokiDokumentovItems($mysqli, $document['id_index']);
        $id_sklady = $document['id_sklada'];
        
        if ($new_utverzhden_value) {
            
            foreach ($line_items as $item) {
                $product_id = $item['id_tovara'];
                $series_id = $item['id_serii'];
                $series_id_null_check = $series_id;
                $quantity = floatval($item['kolichestvo'] ?? 0);
                
                if (!$product_id || $quantity <= 0) {
                    continue;
                }
                
                
                $check_sql = "
                    SELECT id, ostatok 
                    FROM ostatki_tovarov 
                    WHERE id_tovary_i_uslugi = ? 
                    AND id_sklady = ?
                    AND (id_serii = ? OR (? IS NULL AND id_serii IS NULL))
                ";
                
                $check_stmt = $mysqli->prepare($check_sql);
                if (!$check_stmt) {
                    return [
                        'success' => false,
                        'error' => 'Ошибка подготовки запроса проверки: ' . $mysqli->error
                    ];
                }
                
                $check_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $series_id_null_check);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existingEntry = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($existingEntry) {
                    
                    $new_ostatok = floatval($existingEntry['ostatok']) + $quantity;
                    $update_sql = "
                        UPDATE ostatki_tovarov 
                        SET ostatok = ? 
                        WHERE id = ?
                    ";
                    $update_stmt = $mysqli->prepare($update_sql);
                    $update_stmt->bind_param('di', $new_ostatok, $existingEntry['id']);
                    if (!$update_stmt->execute()) {
                        return [
                            'success' => false,
                            'error' => 'Ошибка при обновлении остатков: ' . $update_stmt->error
                        ];
                    }
                    $update_stmt->close();
                } else {
                    
                    $insert_sql = "
                        INSERT INTO ostatki_tovarov 
                        (id_tovary_i_uslugi, id_sklady, id_serii, ostatok)
                        VALUES (?, ?, ?, ?)
                    ";
                    $insert_stmt = $mysqli->prepare($insert_sql);
                    $insert_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $quantity);
                    if (!$insert_stmt->execute()) {
                        return [
                            'success' => false,
                            'error' => 'Ошибка при создании записи остатков: ' . $insert_stmt->error
                        ];
                    }
                    $insert_stmt->close();
                }
            }
        } else {
            
            foreach ($line_items as $item) {
                $product_id = $item['id_tovara'];
                $series_id = $item['id_serii'];
                $series_id_null_check = $series_id;
                $quantity = floatval($item['kolichestvo'] ?? 0);
                
                if (!$product_id || $quantity <= 0) {
                    continue;
                }
                
                
                $check_sql = "
                    SELECT id, ostatok 
                    FROM ostatki_tovarov 
                    WHERE id_tovary_i_uslugi = ? 
                    AND id_sklady = ?
                    AND (id_serii = ? OR (? IS NULL AND id_serii IS NULL))
                ";
                
                $check_stmt = $mysqli->prepare($check_sql);
                $check_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $series_id_null_check);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existingEntry = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($existingEntry) {
                    
                    $new_ostatok = floatval($existingEntry['ostatok']) - $quantity;
                    $update_sql = "
                        UPDATE ostatki_tovarov 
                        SET ostatok = ? 
                        WHERE id = ?
                    ";
                    $update_stmt = $mysqli->prepare($update_sql);
                    $update_stmt->bind_param('di', $new_ostatok, $existingEntry['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Статус документа обновлен'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Ошибка: ' . $e->getMessage()
        ];
    }
}

?>

