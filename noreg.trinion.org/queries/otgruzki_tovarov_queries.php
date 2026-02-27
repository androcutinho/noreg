<?php

require_once 'id_index_helper.php';


function resolveKontragenteId($mysqli, $id, $name) {
    if (!empty($id)) {
       
        $check_query = "SELECT id FROM kontragenti WHERE id = ?";
        $stmt = $mysqli->prepare($check_query);
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->fetch_assoc();
            $stmt->close();
            if ($exists) {
                return ['success' => true, 'id' => $id];
            }
        }
    }
    
    if (!empty($name)) {
       
        $lookup_query = "SELECT id FROM kontragenti WHERE naimenovanie = ? LIMIT 1";
        $stmt = $mysqli->prepare($lookup_query);
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            $found = $result->fetch_assoc();
            $stmt->close();
            if ($found) {
                return ['success' => true, 'id' => $found['id']];
            }
        }
    }
    
    return ['success' => false, 'error' => 'Контрагент не найден'];
}

function resolveUserId($mysqli, $id, $name) {
    if (!empty($id)) {
       
        $check_query = "SELECT id FROM sotrudniki WHERE id = ?";
        $stmt = $mysqli->prepare($check_query);
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->fetch_assoc();
            $stmt->close();
            if ($exists) {
                return ['success' => true, 'id' => $id];
            }
        }
    }
    
    if (!empty($name)) {
        
        $lookup_query = "SELECT id FROM sotrudniki WHERE CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) = ? LIMIT 1";
        $stmt = $mysqli->prepare($lookup_query);
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            $found = $result->fetch_assoc();
            $stmt->close();
            if ($found) {
                return ['success' => true, 'id' => $found['id']];
            }
        }
    }
    
    return ['success' => false, 'error' => 'Сотрудник не найден'];
}

function getOtgruzkiCount($mysqli, $type = 'pokupatel') {
    if ($type === 'postavschik') {
        $query = "SELECT COUNT(*) as total FROM otgruzki_tovarov_pokupatelyam WHERE (ot_postavshchika = 1 OR ot_postavshchika IS TRUE) AND (zakryt = 0 OR zakryt IS NULL)";
    } else {
        $query = "SELECT COUNT(*) as total FROM otgruzki_tovarov_pokupatelyam WHERE (pokupatelya = 1 OR pokupatelya IS TRUE) AND (zakryt = 0 OR zakryt IS NULL)";
    }
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getAllOtgruzki($mysqli, $limit, $offset, $type = 'pokupatel') {
    if ($type === 'postavschik') {
        $where = "WHERE (op.ot_postavshchika = 1 OR op.ot_postavshchika IS TRUE) AND (op.zakryt = 0 OR op.zakryt IS NULL)";
    } else {
        $where = "WHERE (op.pokupatelya = 1 OR op.pokupatelya IS TRUE) AND (op.zakryt = 0 OR op.zakryt IS NULL)";
    }
    
    $query = "
        SELECT 
            op.id,
            op.data_dokumenta,
            op.nomer,
            k.naimenovanie AS naimenovanie_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo
        FROM  otgruzki_tovarov_pokupatelyam op
        LEFT JOIN kontragenti k ON op.id_kontragenti_pokupatel = k.id
        LEFT JOIN kontragenti o ON op.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki s ON op.id_otvetstvennyj = s.id
        $where
        ORDER BY op.data_dokumenta DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $zakazy = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $zakazy;
}

function fetchOtgruzkiHeader($mysqli, $id) {
    $query = "
        SELECT 
            op.id,
            op.id_index,
            op.data_dokumenta,
            op.nomer,
            op.id_kontragenti_pokupatel,
            op.id_kontragenti_postavshik,
            op.id_otvetstvennyj,
            op.id_sklada,
            op.id_zakazy_pokupatelei,
            op.utverzhden,
            op.zakryt,
            op.ot_postavshchika,
            op.pokupatelya,
            s.naimenovanie AS naimenovanie_sklada,
            k.naimenovanie AS naimenovanie_postavschika,
            k.INN AS inn_postavschika,
            k.KPP AS kpp_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            o.INN AS inn_organizacii,
            o.KPP AS kpp_organizacii,
            CONCAT(COALESCE(sr.familiya, ''), ' ', COALESCE(sr.imya, ''), ' ', COALESCE(sr.otchestvo, '')) AS naimenovanie_otvetstvennogo,
            COALESCE(zp.nomer, zs.nomer) AS customer_order_nomer
        FROM  otgruzki_tovarov_pokupatelyam op
        LEFT JOIN sklady s ON op.id_sklada = s.id
        LEFT JOIN kontragenti k ON op.id_kontragenti_pokupatel  = k.id
        LEFT JOIN kontragenti o ON op.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki sr ON op.id_otvetstvennyj = sr.id
        LEFT JOIN zakazy_pokupatelei zp ON op.id_zakazy_pokupatelei = zp.id
        LEFT JOIN zakazy_postavshchikam zs ON op.id_zakazy_pokupatelei = zs.id
        
        WHERE op.id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    
    return $document;
}


function fetchOtgruzkiLineItems($mysqli, $id_index) {
    $query = "
        SELECT 
            sd.id,
            sd.id_index,
            sd.id_tovary_i_uslugi,
            t.naimenovanie AS naimenovanie_tovara,
            sd.id_serii,
            ser.nomer AS naimenovanie_serii,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS naimenovanie_edinitsii,
            sd.id_sklada AS id_sklada,
            s.naimenovanie AS naimenovanie_sklada,
            sd.kolichestvo AS kolichestvo,
            sd.cena AS ed_cena,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds,
            sd.summa
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi t ON sd.id_tovary_i_uslugi = t.id
        LEFT JOIN serii ser ON ser.id = sd.id_serii AND ser.id_tovary_i_uslugi = sd.id_tovary_i_uslugi
        LEFT JOIN edinicy_izmereniya e ON sd.id_edinicy_izmereniya = e.id
        LEFT JOIN sklady s ON sd.id_sklada = s.id
        LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
        WHERE sd.id_index = ?
        ORDER BY sd.id ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $id_index);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $items;
}


function createOtgruzkiDocument($mysqli, $data, $zakaz_pokupatelya_id = null) {
    try {
        $mysqli->begin_transaction();
        
    
        if (empty($data['otgruzki_date']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        
        $vendor_result = resolveKontragenteId($mysqli, $data['id_postavschika'] ?? null, $data['naimenovanie_postavschika'] ?? null);
        if (!$vendor_result['success']) {
            throw new Exception('Покупатель не найден');
        }
        $id_postavschika = $vendor_result['id'];
        
        
        $org_result = resolveKontragenteId($mysqli, $data['id_organizacii'] ?? null, $data['naimenovanie_organizacii'] ?? null);
        if (!$org_result['success']) {
            throw new Exception('Организация поставщика не найдена');
        }
        $id_organizacii = $org_result['id'];
        
        
        $resp_result = resolveUserId($mysqli, $data['id_otvetstvennogo'] ?? null, $data['naimenovanie_otvetstvennogo'] ?? null);
        if (!$resp_result['success']) {
            throw new Exception('Ответственный не найден');
        }
        $id_otvetstvennogo = $resp_result['id'];
        
        $id_index = getNextIdIndex($mysqli);
        
        if (!$zakaz_pokupatelya_id && !empty($data['zakaz_id'])) {
            $zakaz_pokupatelya_id = intval($data['zakaz_id']);
        }
        
        
        $query = "
            INSERT INTO  otgruzki_tovarov_pokupatelyam (
                data_dokumenta,
                id_kontragenti_pokupatel,
                id_kontragenti_postavshik,
                id_otvetstvennyj,
                id_sklada,
                id_zakazy_pokupatelei,
                utverzhden,
                ot_postavshchika,
                pokupatelya,
                id_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = 0;
        $id_sklada = !empty($data['id_sklada']) ? $data['id_sklada'] : null;
        $ot_postavshchika = !empty($data['ot_postavshchika']) ? 1 : 0;
        $pokupatelya = !empty($data['pokupatelya']) ? 1 : 0;
        
        $stmt->bind_param(
            'siiiiiiiii',
            $data['otgruzki_date'],
            $id_postavschika,
            $id_organizacii,
            $id_otvetstvennogo,
            $id_sklada,
            $zakaz_pokupatelya_id,
            $utverzhden,
            $ot_postavshchika,
            $pokupatelya,
            $id_index
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $schet_id = $mysqli->insert_id;
        $stmt->close();
        
        
        $update_nomer_query = "UPDATE  otgruzki_tovarov_pokupatelyam SET nomer = id WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_nomer_query);
        if (!$update_stmt) {
            throw new Exception('Ошибка при обновлении номера: ' . $mysqli->error);
        }
        $update_stmt->bind_param('i', $schet_id);
        if (!$update_stmt->execute()) {
            throw new Exception('Ошибка при установке номера: ' . $update_stmt->error);
        }
        $update_stmt->close();
        
        foreach ($data['tovary'] as $index => $tovar) {
            if (empty($tovar['naimenovanie_tovara']) || empty($tovar['kolichestvo'])) {
                continue;
            }
            
            $nds_id = !empty($tovar['nds_id']) ? $tovar['nds_id'] : null;
            $summa_nds = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
            $ed_cena = !empty($tovar['cena']) ? $tovar['cena'] : 0;
            $id_sklada = !empty($tovar['id_sklada']) ? $tovar['id_sklada'] : null;
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            $id_serii = !empty($tovar['id_serii']) ? $tovar['id_serii'] : null;
            $naimenovanie_serii = !empty($tovar['naimenovanie_serii']) ? $tovar['naimenovanie_serii'] : null;
            
           
            if (!empty($naimenovanie_serii) && $id_tovara) {
               
                $check_seria = "SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?";
                $stmt_check = $mysqli->prepare($check_seria);
                if ($stmt_check) {
                    $stmt_check->bind_param('si', $naimenovanie_serii, $id_tovara);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing_seria = $result_check->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing_seria) {
                        $id_serii = $existing_seria['id'];
                    } else {
                        $insert_seria = "INSERT INTO serii (nomer, id_tovary_i_uslugi) VALUES (?, ?)";
                        $stmt_seria = $mysqli->prepare($insert_seria);
                        if ($stmt_seria) {
                            $stmt_seria->bind_param('si', $naimenovanie_serii, $id_tovara);
                            if ($stmt_seria->execute()) {
                                $id_serii = $mysqli->insert_id;
                            }
                            $stmt_seria->close();
                        }
                    }
                }
            } else if (empty($naimenovanie_serii)) {
                $id_serii = null;
            }
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_serii,
                    id_edinicy_izmereniya,
                    id_sklada,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $stmt->bind_param(
                'iiiiiidiidd',
                $schet_id,
                $id_index,
                $id_tovara,
                $id_serii,
                $id_edinitsii,
                $id_sklada,
                $tovar['kolichestvo'],
                $ed_cena,
                $nds_id,
                $summa_nds,
                $summa
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Ошибка при сохранении строки товара: ' . $stmt->error);
            }
            
            $stmt->close();
        }
        
        
        
       
        if ($zakaz_pokupatelya_id) {
            require_once 'database_queries.php';
            $order_table = $ot_postavshchika ? 'zakazy_postavshchikam' : 'zakazy_pokupatelei';
            linkDocumentsByIndex($mysqli, $zakaz_pokupatelya_id, $schet_id, 'otgruzki_tovarov_pokupatelyam', $order_table);
        }
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'id' => $schet_id,
            'id_index' => $id_index
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function updateOtgruzkiDocument($mysqli, $id, $data) {
    try {
        $mysqli->begin_transaction();
        
    
        $document = fetchOtgruzkiHeader($mysqli, $id);
        if (!$document) {
            throw new Exception('Документ не найден');
        }
        
        $was_approved = $document['utverzhden'] == 1;
        
        
        if ($was_approved) {
            $reverseResult = handleUtverzhdenChange($mysqli, $id, 0);
            if (!$reverseResult['success']) {
                throw new Exception($reverseResult['error']);
            }
        }
        
        if (empty($data['otgruzki_date']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
    
        $vendor_result = resolveKontragenteId($mysqli, $data['id_postavschika'] ?? null, $data['naimenovanie_postavschika'] ?? null);
        if (!$vendor_result['success']) {
            throw new Exception('Покупатель не найден');
        }
        $id_postavschika = $vendor_result['id'];
        
        
        $org_result = resolveKontragenteId($mysqli, $data['id_organizacii'] ?? null, $data['naimenovanie_organizacii'] ?? null);
        if (!$org_result['success']) {
            throw new Exception('Организация поставщика не найдена');
        }
        $id_organizacii = $org_result['id'];
        

        $resp_result = resolveUserId($mysqli, $data['id_otvetstvennogo'] ?? null, $data['naimenovanie_otvetstvennogo'] ?? null);
        if (!$resp_result['success']) {
            throw new Exception('Ответственный не найден');
        }
        $id_otvetstvennogo = $resp_result['id'];
        
        $get_index_query = "SELECT id_index FROM  otgruzki_tovarov_pokupatelyam WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $id_index = $doc['id_index'];
        
        
        $query = "
            UPDATE  otgruzki_tovarov_pokupatelyam SET
                data_dokumenta = ?,
                id_kontragenti_pokupatel = ?,
                id_kontragenti_postavshik = ?,
                id_otvetstvennyj = ?,
                id_sklada = ?,
                id_zakazy_pokupatelei = ?,
                ot_postavshchika = ?,
                pokupatelya = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        
        $id_sklada = !empty($data['id_sklada']) ? $data['id_sklada'] : null;
        $zakaz_id = !empty($data['zakaz_id']) ? intval($data['zakaz_id']) : null;
        $ot_postavshchika = !empty($data['ot_postavshchika']) ? 1 : $document['ot_postavshchika'];
        $pokupatelya = !empty($data['pokupatelya']) ? 1 : $document['pokupatelya'];
        
        $stmt->bind_param(
            'siiiiiiii',
            $data['otgruzki_date'],
            $id_postavschika,
            $id_organizacii,
            $id_otvetstvennogo,
            $id_sklada,
            $zakaz_id,
            $ot_postavshchika,
            $pokupatelya,
            $id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при обновлении заказа: ' . $stmt->error);
        }
        
        $stmt->close();
        
        
        $delete_query = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param('i', $id_index);
        $stmt->execute();
        $stmt->close();
        
        
        foreach ($data['tovary'] as $index => $tovar) {
            if (empty($tovar['naimenovanie_tovara']) || empty($tovar['kolichestvo'])) {
                continue;
            }
            
            $nds_id = !empty($tovar['nds_id']) ? $tovar['nds_id'] : null;
            $summa_nds = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
            $ed_cena = !empty($tovar['cena']) ? $tovar['cena'] : 0;
            $id_sklada = !empty($tovar['id_sklada']) ? $tovar['id_sklada'] : null;
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            $id_serii = !empty($tovar['id_serii']) ? $tovar['id_serii'] : null;
            $naimenovanie_serii = !empty($tovar['naimenovanie_serii']) ? $tovar['naimenovanie_serii'] : null;
            
           
            if (!empty($naimenovanie_serii) && $id_tovara) {
               
                $check_seria = "SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?";
                $stmt_check = $mysqli->prepare($check_seria);
                if ($stmt_check) {
                    $stmt_check->bind_param('si', $naimenovanie_serii, $id_tovara);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing_seria = $result_check->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing_seria) {
                        $id_serii = $existing_seria['id'];
                    } else {
                        
                        $insert_seria = "INSERT INTO serii (nomer, id_tovary_i_uslugi) VALUES (?, ?)";
                        $stmt_seria = $mysqli->prepare($insert_seria);
                        if ($stmt_seria) {
                            $stmt_seria->bind_param('si', $naimenovanie_serii, $id_tovara);
                            if ($stmt_seria->execute()) {
                                $id_serii = $mysqli->insert_id;
                            }
                            $stmt_seria->close();
                        }
                    }
                }
            } else if (empty($naimenovanie_serii)) {
                
                $id_serii = null;
            }
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_serii,
                    id_edinicy_izmereniya,
                    id_sklada,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $stmt->bind_param(
                'iiiiiiddidd',
                $id,
                $id_index,
                $id_tovara,
                $id_serii,
                $id_edinitsii,
                $id_sklada,
                $tovar['kolichestvo'],
                $ed_cena,
                $nds_id,
                $summa_nds,
                $summa
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Ошибка при сохранении строки товара: ' . $stmt->error);
            }
            
            $stmt->close();
        }
        
        
        if ($was_approved) {
            $reapproveResult = handleUtverzhdenChange($mysqli, $id, 1);
            if (!$reapproveResult['success']) {
                throw new Exception($reapproveResult['error']);
            }
        }
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'id' => $id
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function deleteOtgruzkiDocument($mysqli, $id) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_index_query = "SELECT id_index, id_zakazy_pokupatelei FROM  otgruzki_tovarov_pokupatelyam WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $id_index = $doc['id_index'];
        $zakaz_pokupatelya_id = $doc['id_zakazy_pokupatelei'];
        
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
        
        $delete_order_query = "DELETE FROM otgruzki_tovarov_pokupatelyam WHERE id = ?";
        $stmt = $mysqli->prepare($delete_order_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления заказа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении заказа: ' . $stmt->error);
        }
        $stmt->close();
        
        
        $delete_relationships_query = "DELETE FROM svyazi_dokumentov WHERE index_osnovannyj = ?";
        $stmt = $mysqli->prepare($delete_relationships_query);
        if ($stmt) {
            $stmt->bind_param('i', $id_index);
            $stmt->execute();
            $stmt->close();
        }
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Заказ успешно удален'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function handleUtverzhdenChange($mysqli, $document_id, $new_utverzhden_value) {
    try {
        
        $document = fetchOtgruzkiHeader($mysqli, $document_id);
        if (!$document) {
            return [
                'success' => false,
                'error' => 'Документ не найден'
            ];
        }
        
        
        $line_items = fetchOtgruzkiLineItems($mysqli, $document['id_index']);
        
        if ($new_utverzhden_value) {
            
            foreach ($line_items as $item) {
                $product_id = $item['id_tovary_i_uslugi'];
                $series_id = $item['id_serii'];
                $quantity = floatval($item['kolichestvo'] ?? 0);
                $id_sklady = $item['id_sklada'];
                $series_id_null_check = $series_id;
                
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
                    
                    $new_ostatok = floatval($existingEntry['ostatok']) - $quantity;
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
                }
            }
        } else {
            
            foreach ($line_items as $item) {
                $product_id = $item['id_tovary_i_uslugi'];
                $series_id = $item['id_serii'];
                $quantity = floatval($item['kolichestvo'] ?? 0);
                $id_sklady = $item['id_sklada'];
                $series_id_null_check = $series_id;
                
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
                    
                    $new_ostatok = floatval($existingEntry['ostatok']) + $quantity;
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
