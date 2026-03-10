<?php

require_once __DIR__ . '/../config/database_config.php';
require_once __DIR__ . '/id_index_helper.php';
require_once __DIR__ . '/entity_helpers.php';
require_once __DIR__ . '/database_queries.php'; 


function sozdatPeremeshchenieDokument($mysqli, $info) {
    try {
        $mysqli->begin_transaction();
        
        $id_sklada_poluchatel = intval($info['id_sklada_poluchatel']);
        $id_sklada_postavshchik = intval($info['id_sklada_postavshchik']);
        $id_otvetstvennogo = intval($info['id_otvetstvennogo']);
        
        $datetime = $info['data_vypuska'];
        $datetime = str_replace('T', ' ', $datetime) . ':00';
        
        $utverzhden = 0;

        $tip_dokumenta = isset($info['tip_dokumenta']) ? $info['tip_dokumenta'] : 'postuplenie';
        $postuplenie = ($tip_dokumenta === 'postuplenie') ? 1 : 0;
        $otgruzka = ($tip_dokumenta === 'otgruzka') ? 1 : 0;
        
        $id_index = getNextIdIndex($mysqli);
        
        $arrival_sql = "INSERT INTO peremeshchenie_tovara_mezhdu_skladami (id_sklad_poluchatel, id_sklad_postavshik, id_otvetstvennyj, data_dokumenta, postuplenie, otgruzka, utverzhden, id_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $arrival_stmt = $mysqli->stmt_init();
        
        if (!$arrival_stmt->prepare($arrival_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $arrival_stmt->bind_param(
            "iiisiiii",
            $id_sklada_poluchatel,
            $id_sklada_postavshchik,
            $id_otvetstvennogo,
            $datetime,
            $postuplenie,
            $otgruzka,
            $utverzhden,
            $id_index
        );
        
        if (!$arrival_stmt->execute()) {
            throw new Exception("Ошибка при создании документа перемещения: " . $mysqli->error);
        }
        
        $document_id = $mysqli->insert_id;
        
        
        $update_sql = "UPDATE peremeshchenie_tovara_mezhdu_skladami SET nomer = id WHERE id = ?";
        $update_stmt = $mysqli->stmt_init();
        
        if (!$update_stmt->prepare($update_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $update_stmt->bind_param("i", $document_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Ошибка при обновлении номера документа: " . $mysqli->error);
        }
        
        
        $tovary_data = $info['tovary'];
        foreach ($tovary_data as $tovar) {
            
            if (empty($tovar['kolichestvo'])) {
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
            $tovar['id_serii'] = getOrCreateSeries($mysqli, $id_serii_input, $naimenovanie_serii_input, $tovar['id_tovara'], $prod_date, $exp_date, true);
            
            $goods_id = intval($tovar['id_tovara']);
            $kolichestvo = floatval($tovar['kolichestvo']);
            $id_serii = !empty($tovar['id_serii']) ? intval($tovar['id_serii']) : 0;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? intval($tovar['id_edinitsii']) : 0;
            
            
            if ($id_serii > 0) {
                updateSeriesData($mysqli, $id_serii, $goods_id, $prod_date, $exp_date);
            }
            
            
            $line_sql = "INSERT INTO stroki_dokumentov (id_dokumenta, id_index, id_tovary_i_uslugi, kolichestvo, id_serii, id_edinicy_izmereniya) VALUES (?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error . " | SQL: " . $line_sql);
            }
            
            $line_stmt->bind_param(
                "iiidii",
                $document_id,
                $id_index,
                $goods_id,
                $kolichestvo,
                $id_serii,
                $id_edinitsii
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки документа: " . $mysqli->error);
            }
        }

    
        $parent_postuplenie_id = isset($info['parent_postuplenie_id']) ? intval($info['parent_postuplenie_id']) : null;
        if ($parent_postuplenie_id) {
            $link_result = linkDocumentsByIndex($mysqli, $parent_postuplenie_id, $document_id, 'peremeshchenie_tovara_mezhdu_skladami', 'peremeshchenie_tovara_mezhdu_skladami');
            if (!$link_result['success']) {
                throw new Exception('Ошибка при связывании документов: ' . ($link_result['error'] ?? 'Неизвестная ошибка'));
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
        pt.nomer,
        pt.id_sklad_poluchatel,
        pt.id_sklad_postavshik,
        pt.id_otvetstvennyj,
        pt.id_index,
        pt.postuplenie,
        pt.otgruzka,
        sl_poluchatel.naimenovanie as naimenovanie_sklada_poluchatel,
        sl_poluchatel.id as id_sklada_poluchatel,
        sl_postavshik.naimenovanie as naimenovanie_sklada_postavshchik,
        sl_postavshik.id as id_sklada_postavshchik,
        CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) as naimenovanie_otvetstvennogo, 
        s.id as id_otvetstvennogo
    FROM peremeshchenie_tovara_mezhdu_skladami pt
    LEFT JOIN sklady sl_poluchatel ON pt.id_sklad_poluchatel = sl_poluchatel.id
    LEFT JOIN sklady sl_postavshik ON pt.id_sklad_postavshik = sl_postavshik.id
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
        sd.id_index,
        sd.id_tovary_i_uslugi as id_tovara,
        sd.id_serii,
        sd.id_edinicy_izmereniya,
        ti.naimenovanie as naimenovanie_tovara,
        ser.nomer as naimenovanie_serii,
        eu.naimenovanie as naimenovanie_edinitsii,
        sd.kolichestvo
    FROM stroki_dokumentov sd
    LEFT JOIN tovary_i_uslugi ti ON sd.id_tovary_i_uslugi = ti.id
    LEFT JOIN serii ser ON ser.id = sd.id_serii AND ser.id_tovary_i_uslugi = sd.id_tovary_i_uslugi
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

function obnovitPeremeshchenieDokument($mysqli, $document_id, $data) {
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
        
        
        $id_sklada_poluchatel = intval($data['id_sklada_poluchatel'] ?? 0);
        $id_sklada_postavshchik = intval($data['id_sklada_postavshchik'] ?? 0);
        
        if ($id_sklada_poluchatel <= 0 || $id_sklada_postavshchik <= 0) {
            throw new Exception("Требуется указать оба склада");
        }
        
        if (empty($data['id_otvetstvennogo'])) {
            throw new Exception("Требуется указать ответственного");
        }
        $id_otvetstvennogo = intval($data['id_otvetstvennogo']);
        
        $data_dokumenta = $data['data_vypuska'] ?? date('Y-m-d');
        $data_dokumenta = str_replace('T', ' ', $data_dokumenta);
        if (strpos($data_dokumenta, ':') === false) {
            $data_dokumenta .= ' 00:00:00';
        }
        
        $tip_dokumenta = isset($data['tip_dokumenta']) ? $data['tip_dokumenta'] : 'postuplenie';
        $postuplenie = ($tip_dokumenta === 'postuplenie') ? 1 : 0;
        $otgruzka = ($tip_dokumenta === 'otgruzka') ? 1 : 0;
        
        
        $doc_sql = "UPDATE peremeshchenie_tovara_mezhdu_skladami SET 
            data_dokumenta = ?,
            id_sklad_poluchatel = ?,
            id_sklad_postavshik = ?,
            id_otvetstvennyj = ?,
            postuplenie = ?,
            otgruzka = ?
        WHERE id = ?";
        
        $doc_stmt = $mysqli->stmt_init();
        if (!$doc_stmt->prepare($doc_sql)) {
            throw new Exception("Ошибка подготовки запроса документа: " . $mysqli->error);
        }
        
        $doc_stmt->bind_param(
            "siiiiii",
            $data_dokumenta,
            $id_sklada_poluchatel,
            $id_sklada_postavshchik,
            $id_otvetstvennogo,
            $postuplenie,
            $otgruzka,
            $document_id
        );
        
        if (!$doc_stmt->execute()) {
            throw new Exception("Ошибка обновления документа: " . $doc_stmt->error);
        }
        
        
        $get_index_query = "SELECT id_index FROM peremeshchenie_tovara_mezhdu_skladami WHERE id = ?";
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
        
        
        $tovary_data = $data['tovary'] ?? [];
        foreach ($tovary_data as $tovar) {
            
            if (empty($tovar['kolichestvo'])) {
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
            $kolichestvo = floatval($tovar['kolichestvo']);
            $id_serii = !empty($tovar['id_serii']) ? intval($tovar['id_serii']) : 0;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? intval($tovar['id_edinitsii']) : 0;
            
          
            $line_sql = "INSERT INTO stroki_dokumentov (id_dokumenta, id_index, id_tovary_i_uslugi, kolichestvo, id_serii, id_edinicy_izmereniya) VALUES (?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iiidii",
                $document_id,
                $id_index,
                $goods_id,
                $kolichestvo,
                $id_serii,
                $id_edinitsii
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
        
        error_log("[UPDATE TRANSFER] Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

 function udalitPeremeshchenieDokument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_index_query = "SELECT id_index FROM peremeshchenie_tovara_mezhdu_skladami WHERE id = ?";
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
        
        
        $delete_doc_query = "DELETE FROM peremeshchenie_tovara_mezhdu_skladami WHERE id = ?";
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
        
        
        $is_postuplenie = $document['postuplenie'] == 1;
        $is_otgruzka = $document['otgruzka'] == 1;
        
       
        $id_sklady = $is_postuplenie ? $document['id_sklad_poluchatel'] : $document['id_sklad_postavshik'];
        
       
        $should_add = ($is_postuplenie && $new_utverzhden_value) || ($is_otgruzka && !$new_utverzhden_value);
        
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
                
                $quantity_change = $should_add ? $quantity : -$quantity;
                $new_ostatok = floatval($existingEntry['ostatok']) + $quantity_change;
                
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
                
                if ($should_add) {
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

function  getKolichestvoPeremeshchenie($mysqli, $type = null) {
    if ($type === 'postuplenie') {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM peremeshchenie_tovara_mezhdu_skladami  WHERE (zakryt = 0 OR zakryt IS NULL) AND postuplenie = 1");
    } elseif ($type === 'otgruzka') {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM peremeshchenie_tovara_mezhdu_skladami  WHERE (zakryt = 0 OR zakryt IS NULL) AND otgruzka = 1");
    } else {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM peremeshchenie_tovara_mezhdu_skladami  WHERE zakryt = 0 OR zakryt IS NULL");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}

function getAllPeremeshchenie($mysqli, $limit = 8, $offset = 0, $type = null) {
    $where_clause = "(ptm.zakryt = 0 OR ptm.zakryt IS NULL)";
    
    if ($type === 'postuplenie') {
        $where_clause .= " AND ptm.postuplenie = 1";
    } elseif ($type === 'otgruzka') {
        $where_clause .= " AND ptm.otgruzka = 1";
    }
    
    $stmt = $mysqli->prepare("
        SELECT
            ptm.id,
            ptm.nomer,
            ptm.data_dokumenta,
            sl_poluchatel.naimenovanie as naimenovanie_sklada_poluchatel,
            sl_poluchatel.id as id_sklada_poluchatel,
            sl_postavshik.naimenovanie as naimenovanie_sklada_postavshchik,
            sl_postavshik.id as id_sklada_postavshchik,
            CONCAT_WS(' ', COALESCE(sr.familiya, NULL), COALESCE(sr.imya, NULL), COALESCE(sr.otchestvo, NULL)) AS employee_name
        FROM peremeshchenie_tovara_mezhdu_skladami  ptm
        LEFT JOIN sklady sl_poluchatel ON ptm.id_sklad_poluchatel = sl_poluchatel.id
        LEFT JOIN sklady sl_postavshik ON ptm.id_sklad_postavshik = sl_postavshik.id
        LEFT JOIN sotrudniki sr ON ptm.id_otvetstvennyj  = sr.id
        WHERE " . $where_clause . "
        ORDER BY ptm.data_dokumenta DESC
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


function sozdatOtgruzkaIzPostuplenie($mysqli, $postuplenie_id) {
    try {
        $mysqli->begin_transaction();
        
        
        $source_doc = getDokumentHeader($mysqli, $postuplenie_id);
        if (!$source_doc || !$source_doc['postuplenie']) {
            throw new Exception("Поступление документ не найден");
        }
        
        
        $line_items = getStrokiDokumentovItems($mysqli, $source_doc['id_index']);
        
        
        $id_index = getNextIdIndex($mysqli);
        $arrival_sql = "INSERT INTO peremeshchenie_tovara_mezhdu_skladami 
            (id_sklad_poluchatel, id_sklad_postavshik, id_otvetstvennyj, data_dokumenta, postuplenie, otgruzka, utverzhden, id_index) 
            VALUES (?, ?, ?, ?, 0, 1, 0, ?)";
        
        $stmt = $mysqli->stmt_init();
        $stmt->prepare($arrival_sql);
        $datetime = date('Y-m-d H:i:s');
        $stmt->bind_param(
            "iiisi",
            $source_doc['id_sklad_poluchatel'], 
            $source_doc['id_sklad_postavshchik'], 
            $source_doc['id_otvetstvennogo'],
            $datetime,
            $id_index
        );
        $stmt->execute();
        $arrival_id = $mysqli->insert_id;
        
        $update_sql = "UPDATE peremeshchenie_tovara_mezhdu_skladami SET nomer = id WHERE id = ?";
        $update_stmt = $mysqli->stmt_init();
        $update_stmt->prepare($update_sql);
        $update_stmt->bind_param("i", $arrival_id);
        $update_stmt->execute();
        
        foreach ($line_items as $item) {
            $line_sql = "INSERT INTO stroki_dokumentov (id_dokumenta, id_index, id_tovary_i_uslugi, kolichestvo, id_serii, id_edinicy_izmereniya) VALUES (?, ?, ?, ?, ?, ?)";
            $line_stmt = $mysqli->stmt_init();
            $line_stmt->prepare($line_sql);
            $line_stmt->bind_param("iiidii", $arrival_id, $id_index, $item['id_tovara'], $item['kolichestvo'], $item['id_serii'], $item['id_edinicy_izmereniya']);
            $line_stmt->execute();
        }
        
        $mysqli->commit();
        return ['success' => true, 'document_id' => $arrival_id];
    } catch (Exception $e) {
        $mysqli->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>