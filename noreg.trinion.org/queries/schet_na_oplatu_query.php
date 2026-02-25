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
            sd.summa_nds,
            sd.summa
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
            $summa_nds = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
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
            $order_table = ($invoice_type === 'supplier') ? 'zakazy_postavshchikam' : 'zakazy_pokupatelei';
            linkDocumentsByIndex($mysqli, $zakaz_pokupatelya_id, $schet_id, 'scheta_na_oplatu', $order_table);
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
            $summa_nds = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
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
                $summa_nds,
                $summa
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

?>