<?php

require_once 'id_index_helper.php';

function getSummaSchetov($mysqli, $type = 'pokupatel') {
    $where = "(sp.zakryt = 0 OR sp.zakryt IS NULL)";
    
    if ($type === 'postavschik') {
        $where .= " AND sp.ot_postavshchika = 1";
    } else {
        $where .= " AND sp.pokupatelya = 1";
    }
    
    $query = "SELECT COUNT(*) as total FROM scheta_na_oplatu sp WHERE " . $where;
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getAllschetov($mysqli, $limit, $offset, $type = 'pokupatel') {
    $query = "
        SELECT 
            sp.id,
            sp.data_dokumenta,
            sp.nomer,
            k.naimenovanie AS naimenovanie_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo
        FROM  scheta_na_oplatu sp
        LEFT JOIN kontragenti k ON sp.id_kontragenti_pokupatel = k.id
        LEFT JOIN kontragenti o ON sp.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki s ON sp.id_otvetstvennyj = s.id
        WHERE (sp.zakryt = 0 OR sp.zakryt IS NULL)";
    
    if ($type === 'postavschik') {
        $query .= " AND sp.ot_postavshchika = 1";
    } else {
        $query .= " AND sp.pokupatelya = 1";
    }
    
    $query .= " ORDER BY sp.data_dokumenta DESC
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

function fetchSchetHeader($mysqli, $id) {
    $query = "
        SELECT 
            sp.id,
            sp.id_index,
            sp.data_dokumenta,
            sp.nomer,
            sp.id_kontragenti_pokupatel,
            sp.id_kontragenti_postavshik,
            sp.id_otvetstvennyj,
            sp.utverzhden,
            sp.zakryt,
            sp.Id_raschetnye_scheta_kontragenti_pokupatel,
            sp.Id_raschetnye_scheta_organizacii,
            sp.ot_postavshchika,
            sp.pokupatelya,
            k.naimenovanie AS naimenovanie_postavschika,
            k.INN AS inn_postavschika,
            k.KPP AS kpp_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            o.INN AS inn_organizacii,
            o.KPP AS kpp_organizacii,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo,
            rs1.naimenovanie AS schet_pokupatelya_naimenovanie,
            rs1.naimenovanie_banka AS bank_name1,
            rs1.BIK_banka AS bik_bank1,
            rs1.nomer_korrespondentskogo_scheta AS correspondent_account1,
            rs1.nomer AS account_number1,
            rs2.naimenovanie AS schet_postavschika_naimenovanie,
            rs2.naimenovanie_banka AS bank_name,
            rs2.BIK_banka AS bik_bank,
            rs2.nomer_korrespondentskogo_scheta AS correspondent_account,
            rs2.nomer AS account_number
        FROM  scheta_na_oplatu sp
        LEFT JOIN kontragenti k ON sp.id_kontragenti_pokupatel = k.id
        LEFT JOIN kontragenti o ON sp.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki s ON sp.id_otvetstvennyj = s.id
        LEFT JOIN raschetnye_scheta rs1 ON sp.Id_raschetnye_scheta_kontragenti_pokupatel = rs1.id
        LEFT JOIN raschetnye_scheta rs2 ON sp.Id_raschetnye_scheta_organizacii = rs2.id
        
        WHERE sp.id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    
    return $document;
}


function getSchetStrokiItems($mysqli, $id_index) {
    $query = "
        SELECT 
            sd.id,
            sd.id_index,
            sd.id_tovary_i_uslugi,
            t.naimenovanie AS naimenovanie_tovara,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS naimenovanie_edinitsii,
            sd.kolichestvo AS kolichestvo,
            sd.cena AS ed_cena,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds AS obshchaya_summa,
            sd.summa AS obshchaya_summa
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi t ON sd.id_tovary_i_uslugi = t.id
        LEFT JOIN edinicy_izmereniya e ON sd.id_edinicy_izmereniya = e.id
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


function sozdatSchetDokument($mysqli, $data, $zakaz_pokupatelya_id = null) {
    try {
        $mysqli->begin_transaction();
        
    
        if (empty($data['schet_date']) || 
            empty($data['id_postavschika']) || empty($data['id_organizacii']) || 
            empty($data['id_otvetstvennogo']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
       
        $id_index = getNextIdIndex($mysqli);
        
        if (!$zakaz_pokupatelya_id && !empty($data['zakaz_id'])) {
            $zakaz_pokupatelya_id = intval($data['zakaz_id']);
        }
        
        
        $query = "
            INSERT INTO scheta_na_oplatu (
                data_dokumenta,
                id_kontragenti_pokupatel,
                id_kontragenti_postavshik,
                id_otvetstvennyj,
                utverzhden,
                Id_raschetnye_scheta_kontragenti_pokupatel,
                Id_raschetnye_scheta_organizacii,
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
        $schet_pokupatelya_id = !empty($data['schet_pokupatelya_id']) ? $data['schet_pokupatelya_id'] : null;
        $schet_postavschika_id = !empty($data['schet_postavschika_id']) ? $data['schet_postavschika_id'] : null;
        
        $invoice_type = isset($data['invoice_type']) ? $data['invoice_type'] : 'supplier';
        $ot_postavshchika = ($invoice_type === 'supplier') ? 1 : 0;
        $pokupatelya = ($invoice_type === 'buyer') ? 1 : 0;
        
        $stmt->bind_param(
            'siiiiiiiii',
            $data['schet_date'],
            $data['id_postavschika'],
            $data['id_organizacii'],
            $data['id_otvetstvennogo'],
            $utverzhden,
            $schet_pokupatelya_id,
            $schet_postavschika_id,
            $ot_postavshchika,
            $pokupatelya,
            $id_index
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $schet_id = $mysqli->insert_id;
        $stmt->close();
        
        
        $update_nomer_query = "UPDATE scheta_na_oplatu SET nomer = id WHERE id = ?";
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
            $obshchaya_summa = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $obshchaya_summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
            $ed_cena = !empty($tovar['cena']) ? $tovar['cena'] : 0;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            
            $stmt->bind_param(
                'iiiidiidd',
                $schet_id,
                $id_index,
                $id_tovara,
                $id_edinitsii,
                $tovar['kolichestvo'],
                $ed_cena,
                $nds_id,
                $obshchaya_summa,
                $obshchaya_summa
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Ошибка при сохранении строки товара: ' . $stmt->error);
            }
            
            $stmt->close();
        }
        
        
        if ($zakaz_pokupatelya_id) {
            
            $is_supplier_invoice = ($ot_postavshchika == 1);
            $order_table = $is_supplier_invoice ? 'zakazy_postavshchikam' : 'zakazy_pokupatelei';
            $order_field = $is_supplier_invoice ? 'id_scheta_na_oplatu_postavshchikam' : 'id_scheta_na_oplatu_pokupatelyam';
            
            
            $verify_query = "SELECT id FROM $order_table WHERE id = ?";
            $verify_stmt = $mysqli->prepare($verify_query);
            if ($verify_stmt) {
                $verify_stmt->bind_param('i', $zakaz_pokupatelya_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $verify_stmt->close();
                
                if ($verify_result->num_rows > 0) {
                    
                    $select_query = "SELECT $order_field FROM $order_table WHERE id = ?";
                    $stmt = $mysqli->prepare($select_query);
                    if (!$stmt) {
                        throw new Exception('Ошибка при подготовке запроса выборки: ' . $mysqli->error);
                    }
                    
                    $stmt->bind_param('i', $zakaz_pokupatelya_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing = $result->fetch_assoc();
                    $stmt->close();
                    
                    $schet_ids = [];
                    if (!empty($existing[$order_field])) {
                        $existing_ids = json_decode($existing[$order_field], true);
                        if (is_array($existing_ids)) {
                            $schet_ids = $existing_ids;
                        } else {
                            $schet_ids = [$existing_ids];
                        }
                    }
                    
                    $invoice_ref = ['id' => $schet_id, 'type' => 'invoice'];
                    $already_exists = false;
                    foreach ($schet_ids as $ref) {
                        if (is_array($ref) && $ref['id'] == $schet_id && $ref['type'] == 'invoice') {
                            $already_exists = true;
                            break;
                        }
                    }
                    if (!$already_exists) {
                        $schet_ids[] = $invoice_ref;
                    }
                    
                    $update_query = "UPDATE $order_table SET $order_field = ? WHERE id = ?";
                    $stmt = $mysqli->prepare($update_query);
                    if (!$stmt) {
                        throw new Exception('Ошибка при подготовке запроса обновления заказа: ' . $mysqli->error);
                    }
                    
                    $schet_ids_json = json_encode($schet_ids);
                    $stmt->bind_param('si', $schet_ids_json, $zakaz_pokupatelya_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Ошибка при обновлении ссылки заказа: ' . $stmt->error);
                    }
                    $stmt->close();
                }
            }
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


function obnovitSchetDokument($mysqli, $id, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['schet_date']) || 
            empty($data['id_postavschika']) || empty($data['id_organizacii']) || 
            empty($data['id_otvetstvennogo']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
       
        $get_index_query = "SELECT id_index FROM scheta_na_oplatu WHERE id = ?";
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
            UPDATE scheta_na_oplatu SET
                data_dokumenta = ?,
                id_kontragenti_pokupatel = ?,
                id_kontragenti_postavshik = ?,
                id_otvetstvennyj = ?,
                Id_raschetnye_scheta_kontragenti_pokupatel = ?,
                Id_raschetnye_scheta_organizacii = ?,
                ot_postavshchika = ?,
                pokupatelya = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $schet_pokupatelya_id = !empty($data['schet_pokupatelya_id']) ? $data['schet_pokupatelya_id'] : null;
        $schet_postavschika_id = !empty($data['schet_postavschika_id']) ? $data['schet_postavschika_id'] : null;
        
        $invoice_type = isset($data['invoice_type']) ? $data['invoice_type'] : 'supplier';
        $ot_postavshchika = ($invoice_type === 'supplier') ? 1 : 0;
        $pokupatelya = ($invoice_type === 'buyer') ? 1 : 0;
        
        $stmt->bind_param(
            'siiiiiiii',
            $data['schet_date'],
            $data['id_postavschika'],
            $data['id_organizacii'],
            $data['id_otvetstvennogo'],
            $schet_pokupatelya_id,
            $schet_postavschika_id,
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
            $obshchaya_summa = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $obshchaya_summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
            $ed_cena = !empty($tovar['cena']) ? $tovar['cena'] : 0;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            
            $stmt->bind_param(
                'iiiiddidd',
                $id,
                $id_index,
                $id_tovara,
                $id_edinitsii,
                $tovar['kolichestvo'],
                $ed_cena,
                $nds_id,
                $obshchaya_summa,
                $obshchaya_summa
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Ошибка при сохранении строки товара: ' . $stmt->error);
            }
            
            $stmt->close();
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


function deleteSchetDocument($mysqli, $id) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_index_query = "SELECT id_index FROM scheta_na_oplatu WHERE id = ?";
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
        
        
        $delete_order_query = "DELETE FROM scheta_na_oplatu WHERE id = ?";
        $stmt = $mysqli->prepare($delete_order_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления заказа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении заказа: ' . $stmt->error);
        }
        $stmt->close();
        
        

        $select_orders_query = "SELECT id FROM zakazy_pokupatelei WHERE id_scheta_na_oplatu_pokupatelyam LIKE ?";
        $stmt = $mysqli->prepare($select_orders_query);
        if ($stmt) {
            $search_pattern = '%"' . $id . '"%';
            $stmt->bind_param('s', $search_pattern);
            $stmt->execute();
            $orders_result = $stmt->get_result();
            $stmt->close();
            
            while ($zakaz = $orders_result->fetch_assoc()) {
                $order_id = $zakaz['id'];
                
                $get_refs_query = "SELECT id_scheta_na_oplatu_pokupatelyam FROM zakazy_pokupatelei WHERE id = ?";
                $stmt = $mysqli->prepare($get_refs_query);
                if ($stmt) {
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $refs_result = $stmt->get_result();
                    $info_zakaza = $refs_result->fetch_assoc();
                    $stmt->close();
                    
                    if ($info_zakaza && !empty($info_zakaza['id_scheta_na_oplatu_pokupatelyam'])) {
                        $schet_ids = json_decode($info_zakaza['id_scheta_na_oplatu_pokupatelyam'], true);
                        if (is_array($schet_ids)) {
                            $schet_ids = array_filter($schet_ids, function($val) use ($id) {
                                
                                if (is_array($val)) {
                                    return !($val['id'] == $id && $val['type'] == 'invoice');
                                }
                                return $val != $id;
                            });
                            $schet_ids = array_values($schet_ids);
                        }
                        
                        $update_query = "UPDATE zakazy_pokupatelei SET id_scheta_na_oplatu_pokupatelyam = ? WHERE id = ?";
                        $stmt = $mysqli->prepare($update_query);
                        if ($stmt) {
                            $schet_ids_json = !empty($schet_ids) ? json_encode($schet_ids) : null;
                            $stmt->bind_param('si', $schet_ids_json, $order_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }
        
        
        $select_supplier_query = "SELECT id FROM zakazy_postavshchikam WHERE id_scheta_na_oplatu_postavshchikam LIKE ?";
        $stmt = $mysqli->prepare($select_supplier_query);
        if ($stmt) {
            $search_pattern = '%"' . $id . '"%';
            $stmt->bind_param('s', $search_pattern);
            $stmt->execute();
            $supplier_result = $stmt->get_result();
            $stmt->close();
            
            while ($supplier_order = $supplier_result->fetch_assoc()) {
                $supplier_order_id = $supplier_order['id'];
                
                $get_refs_query = "SELECT id_scheta_na_oplatu_postavshchikam FROM zakazy_postavshchikam WHERE id = ?";
                $stmt = $mysqli->prepare($get_refs_query);
                if ($stmt) {
                    $stmt->bind_param('i', $supplier_order_id);
                    $stmt->execute();
                    $refs_result = $stmt->get_result();
                    $info_zakaza = $refs_result->fetch_assoc();
                    $stmt->close();
                    
                    if ($info_zakaza && !empty($info_zakaza['id_scheta_na_oplatu_postavshchikam'])) {
                        $schet_ids = json_decode($info_zakaza['id_scheta_na_oplatu_postavshchikam'], true);
                        if (is_array($schet_ids)) {
                            $schet_ids = array_filter($schet_ids, function($val) use ($id) {
                                
                                if (is_array($val)) {
                                    return !($val['id'] == $id && $val['type'] == 'invoice');
                                }
                                return $val != $id;
                            });
                            $schet_ids = array_values($schet_ids);
                        }
                        
                        $update_query = "UPDATE zakazy_postavshchikam SET id_scheta_na_oplatu_postavshchikam = ? WHERE id = ?";
                        $stmt = $mysqli->prepare($update_query);
                        if ($stmt) {
                            $schet_ids_json = !empty($schet_ids) ? json_encode($schet_ids) : null;
                            $stmt->bind_param('si', $schet_ids_json, $supplier_order_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
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

?>