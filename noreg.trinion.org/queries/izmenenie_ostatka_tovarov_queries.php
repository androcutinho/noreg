<?php

require_once __DIR__ . '/id_index_helper.php';
require_once __DIR__ . '/entity_helpers.php';


function getDokumentHeader($mysqli, $id) {
    $sql = "
        SELECT 
            d.id,
            d.id_index,
            d.nomer,
            d.data_dokumenta,
            d.id_sklada,
            d.id_otvetstvennyj,
            d.utverzhden,
            d.zakryt,
            sk.naimenovanie as naimenovanie_sklada,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) as naimenovanie_otvetstvennogo
        FROM izmenenie_ostatka_tovarov d
        LEFT JOIN sklady sk ON d.id_sklada = sk.id
        LEFT JOIN sotrudniki s ON d.id_otvetstvennyj = s.id
        WHERE d.id = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    
    return $document;
}


function getStrokiDokumentovItems($mysqli, $id_index) {
    $sql = "
        SELECT 
            sd.id,
            sd.id_index,
            sd.id_tovary_i_uslugi,
            ti.naimenovanie as naimenovanie_tovara,
            sd.id_serii,
            s.nomer as naimenovanie_serii,
            sd.kolichestvo,
            sd.ubavit,
            sd.pribavit
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi ti ON sd.id_tovary_i_uslugi = ti.id
        LEFT JOIN serii s ON sd.id_serii = s.id
        WHERE sd.id_index = ?
        ORDER BY sd.id ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_index);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    return $items;
}


function sozdatDokument($mysqli, $post_data) {
    try {
        $data_dokumenta = $post_data['product_date'] ?? date('Y-m-d');
        $id_sklada = intval($post_data['id_sklada'] ?? 0);
        $id_otvetstvennyj = intval($post_data['id_otvetstvennogo'] ?? 0);
        
        if (empty($id_sklada) || empty($id_otvetstvennyj)) {
            return [
                'success' => false,
                'error' => 'Требуется указать склад и ответственного'
            ];
        }
        
    
        $id_index = getNextIdIndex($mysqli);
        
        
        $insert_sql = "
            INSERT INTO izmenenie_ostatka_tovarov 
            (nomer, data_dokumenta, id_sklada, id_otvetstvennyj, id_index, utverzhden, zakryt)
            VALUES (NULL, ?, ?, ?, ?, 0, 0)
        ";
        
        $stmt = $mysqli->prepare($insert_sql);
        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Ошибка подготовки запроса: ' . $mysqli->error
            ];
        }
        
        $stmt->bind_param('siii', $data_dokumenta, $id_sklada, $id_otvetstvennyj, $id_index);
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'error' => 'Ошибка при вставке документа: ' . $stmt->error
            ];
        }
        
        $document_id = $mysqli->insert_id;
        $stmt->close();
        
        
        $update_nomer_sql = "UPDATE izmenenie_ostatka_tovarov SET nomer = ? WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_nomer_sql);
        $update_stmt->bind_param('ii', $document_id, $document_id);
        $update_stmt->execute();
        $update_stmt->close();
        

        if (!empty($post_data['tovary']) && is_array($post_data['tovary'])) {
            foreach ($post_data['tovary'] as $tovar_data) {
                if (empty($tovar_data['naimenovanie_tovara']) && empty($tovar_data['kolichestvo'])) {
                    continue;
                }
                
                
                $product_id = getOrCreateProduct(
                    $mysqli,
                    $tovar_data['id_tovara'] ?? '',
                    $tovar_data['naimenovanie_tovara'] ?? ''
                );
                
                
                $series_id = null;
                if (!empty($tovar_data['naimenovanie_serii'])) {
                    $series_id = getOrCreateSeries(
                        $mysqli,
                        $tovar_data['id_serii'] ?? '',
                        $tovar_data['naimenovanie_serii'] ?? '',
                        $product_id,
                        null,
                        null,
                        false
                    );
                }
                
                $kolichestvo = floatval($tovar_data['kolichestvo'] ?? 0);
                $ubavit = floatval($tovar_data['ubavit'] ?? 0);
                $pribavit = floatval($tovar_data['pribavit'] ?? 0);
                
                
                $stroki_id_index = getNextIdIndex($mysqli);
                
                
                $line_insert_sql = "
                    INSERT INTO stroki_dokumentov 
                    (id_index, id_tovary_i_uslugi, id_serii, kolichestvo, ubavit, pribavit)
                    VALUES (?, ?, ?, ?, ?, ?)
                ";
                
                $line_stmt = $mysqli->prepare($line_insert_sql);
                if (!$line_stmt) {
                    continue;
                }
                
                $line_stmt->bind_param(
                    'iiiddd',
                    $id_index,
                    $product_id,
                    $series_id,
                    $kolichestvo,
                    $ubavit,
                    $pribavit
                );
                $line_stmt->execute();
                $line_stmt->close();
            }
        }
        
        return [
            'success' => true,
            'id' => $document_id,
            'message' => 'Документ успешно создан'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Ошибка: ' . $e->getMessage()
        ];
    }
}

function obnovitDokument($mysqli, $id, $post_data) {
    try {
    
        $document = getDokumentHeader($mysqli, $id);
        if (!$document) {
            return [
                'success' => false,
                'error' => 'Документ не найден'
            ];
        }
        
        $data_dokumenta = $post_data['product_date'] ?? $document['data_dokumenta'];
        $id_sklada = intval($post_data['id_sklada'] ?? $document['id_sklada']);
        $id_otvetstvennyj = intval($post_data['id_otvetstvennogo'] ?? $document['id_otvetstvennyj']);
        
        if (empty($id_sklada) || empty($id_otvetstvennyj)) {
            return [
                'success' => false,
                'error' => 'Требуется указать склад и ответственного'
            ];
        }
        
        
        $was_approved = $document['utverzhden'] == 1;
        if ($was_approved) {
            $reverseResult = handleUtverzhdenChange($mysqli, $id, 0);
            if (!$reverseResult['success']) {
                return $reverseResult;
            }
        }
        
        $update_sql = "
            UPDATE izmenenie_ostatka_tovarov 
            SET data_dokumenta = ?, id_sklada = ?, id_otvetstvennyj = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($update_sql);
        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Ошибка подготовки запроса: ' . $mysqli->error
            ];
        }
        
        $stmt->bind_param('siii', $data_dokumenta, $id_sklada, $id_otvetstvennyj, $id);
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'error' => 'Ошибка при обновлении документа: ' . $stmt->error
            ];
        }
        $stmt->close();
        
        
        $delete_sql = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $delete_stmt = $mysqli->prepare($delete_sql);
        $delete_stmt->bind_param('i', $document['id_index']);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        
        if (!empty($post_data['tovary']) && is_array($post_data['tovary'])) {
            foreach ($post_data['tovary'] as $tovar_data) {
                if (empty($tovar_data['naimenovanie_tovara']) && empty($tovar_data['kolichestvo'])) {
                    continue;
                }
                
                
                $product_id = getOrCreateProduct(
                    $mysqli,
                    $tovar_data['id_tovara'] ?? '',
                    $tovar_data['naimenovanie_tovara'] ?? ''
                );
                
                
                $series_id = null;
                if (!empty($tovar_data['naimenovanie_serii'])) {
                    $series_id = getOrCreateSeries(
                        $mysqli,
                        $tovar_data['id_serii'] ?? '',
                        $tovar_data['naimenovanie_serii'] ?? '',
                        $product_id,
                        null,
                        null,
                        false
                    );
                }
                
                $kolichestvo = floatval($tovar_data['kolichestvo'] ?? 0);
                $ubavit = floatval($tovar_data['ubavit'] ?? 0);
                $pribavit = floatval($tovar_data['pribavit'] ?? 0);
                
                
                $line_insert_sql = "
                    INSERT INTO stroki_dokumentov 
                    (id_index, id_tovary_i_uslugi, id_serii, kolichestvo, ubavit, pribavit)
                    VALUES (?, ?, ?, ?, ?, ?)
                ";
                
                $line_stmt = $mysqli->prepare($line_insert_sql);
                if (!$line_stmt) {
                    continue;
                }
                
                $line_stmt->bind_param(
                    'iiiddd',
                    $document['id_index'],
                    $product_id,
                    $series_id,
                    $kolichestvo,
                    $ubavit,
                    $pribavit
                );
                $line_stmt->execute();
                $line_stmt->close();
            }
        }
        
        
        if ($was_approved) {
            $reapproveResult = handleUtverzhdenChange($mysqli, $id, 1);
            if (!$reapproveResult['success']) {
                return $reapproveResult;
            }
        }
        
        return [
            'success' => true,
            'id' => $id,
            'message' => 'Документ успешно обновлен'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Ошибка: ' . $e->getMessage()
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
        
        if ($new_utverzhden_value) {
            
            foreach ($line_items as $item) {
                $product_id = $item['id_tovary_i_uslugi'];
                $series_id = $item['id_serii'];
                $id_sklady = $document['id_sklada'];
                
                if (!$product_id) {
                    continue;
                }
                
                
                
                $quantity_change = floatval($item['kolichestvo'] ?? 0);
                if (!empty($item['ubavit']) && floatval($item['ubavit']) > 0) {
                    $quantity_change -= floatval($item['ubavit']);
                }
                if (!empty($item['pribavit']) && floatval($item['pribavit']) > 0) {
                    $quantity_change += floatval($item['pribavit']);
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
                
                $check_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $series_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existingEntry = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($existingEntry) {
            
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
                    
                    $insert_sql = "
                        INSERT INTO ostatki_tovarov 
                        (id_tovary_i_uslugi, id_sklady, id_serii, ostatok)
                        VALUES (?, ?, ?, ?)
                    ";
                    $insert_stmt = $mysqli->prepare($insert_sql);
                    $insert_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $quantity_change);
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
                $product_id = $item['id_tovary_i_uslugi'];
                $series_id = $item['id_serii'];
                $id_sklady = $document['id_sklada'];
                
                if (!$product_id) {
                    continue;
                }
                
        
                
                $quantity_change = floatval($item['kolichestvo'] ?? 0);
                if (!empty($item['ubavit']) && floatval($item['ubavit']) > 0) {
                    $quantity_change -= floatval($item['ubavit']);
                }
                if (!empty($item['pribavit']) && floatval($item['pribavit']) > 0) {
                    $quantity_change += floatval($item['pribavit']);
                }
                
                
                $check_sql = "
                    SELECT id, ostatok 
                    FROM ostatki_tovarov 
                    WHERE id_tovary_i_uslugi = ? 
                    AND id_sklady = ?
                    AND (id_serii = ? OR (? IS NULL AND id_serii IS NULL))
                ";
                
                $check_stmt = $mysqli->prepare($check_sql);
                $check_stmt->bind_param('iiii', $product_id, $id_sklady, $series_id, $series_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existingEntry = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($existingEntry) {
                
                    $new_ostatok = floatval($existingEntry['ostatok']) - $quantity_change;
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



function getKolichestvoDok($mysqli) {
    $sql = "SELECT COUNT(*) as count FROM izmenenie_ostatka_tovarov";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}


function getVceDok($mysqli, $limit, $offset) {
    $sql = "
        SELECT 
            d.id,
            d.nomer,
            d.data_dokumenta,
            sk.naimenovanie as naimenovanie_sklada,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) as naimenovanie_otvetstvennogo,
            d.utverzhden
        FROM izmenenie_ostatka_tovarov d
        LEFT JOIN sklady sk ON d.id_sklada = sk.id
        LEFT JOIN sotrudniki s ON d.id_otvetstvennyj = s.id
        ORDER BY d.data_dokumenta DESC, d.id DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
    
    return $documents;
}

?>
