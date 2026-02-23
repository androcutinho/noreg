<?php

require_once 'id_index_helper.php';

function getKolichestvoZakazy($mysqli) {
    $query = "SELECT COUNT(*) as total FROM zakazy_postavshchikam WHERE zakryt = 0 OR zakryt IS NULL";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getVceZakazyPokupatieliu($mysqli, $limit, $offset) {
    $query = "
        SELECT 
            zp.id,
            zp.data_dokumenta,
            zp.nomer,
            k.naimenovanie AS naimenovanie_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo
        FROM  zakazy_pokupatelei zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_pokupatel = k.id
        LEFT JOIN kontragenti o ON zp.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki s ON zp.id_otvetstvennyj = s.id
        WHERE zp.zakryt = 0 OR zp.zakryt IS NULL
        ORDER BY zp.data_dokumenta DESC
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

function getZakazHeader($mysqli, $zakaz_id) {
    $query = "
        SELECT 
            zp.id,
            zp.id_index,
            zp.data_dokumenta,
            zp.nomer,
            zp.id_kontragenti_pokupatel,
            zp.id_kontragenti_postavshik,
            zp.id_otvetstvennyj,
            zp.utverzhden,
            zp.zakryt,
            zp.id_sklada,
            zp.id_scheta_na_oplatu_pokupatelyam,
            sl.id AS id_sklada,
            sl.naimenovanie AS naimenovanie_sklada,
            k.naimenovanie AS naimenovanie_postavschika,
            k.INN AS inn_postavschika,
            k.KPP AS kpp_postavschika,
            o.naimenovanie AS naimenovanie_organizacii,
            o.INN AS inn_organizacii,
            o.KPP AS kpp_organizacii,
            CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo
        FROM zakazy_pokupatelei zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_pokupatel = k.id
        LEFT JOIN kontragenti o ON zp.id_kontragenti_postavshik = o.id
        LEFT JOIN sotrudniki s ON zp.id_otvetstvennyj = s.id
        LEFT JOIN sklady sl ON zp.id_sklada = sl.id
        
        WHERE zp.id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $zakaz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    
    return $document;
}


function getZakazStrokiItemsPokupatieliu($mysqli, $id_index) {
    $query = "
        SELECT 
            sd.id,
            sd.id_index,
            sd.id_tovary_i_uslugi,
            t.naimenovanie AS naimenovanie_tovara,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS naimenovanie_edinitsii,
            sd.id_sklada AS id_sklada,
            s.naimenovanie AS naimenovanie_sklada,
            sd.kolichestvo AS kolichestvo,
            sd.cena AS ed_cena,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds AS obshchaya_summa,
            sd.summa AS obshchaya_summa
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi t ON sd.id_tovary_i_uslugi = t.id
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


function sozdatZakazDokument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['data_zakaza']) || empty($data['nomer_zakaza']) || 
            empty($data['id_postavschika']) || empty($data['id_organizacii']) || 
            empty($data['id_otvetstvennogo']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        
        $id_index = getNextIdIndex($mysqli);
        
        
        $query = "
            INSERT INTO zakazy_pokupatelei (
                data_dokumenta,
                nomer,
                id_kontragenti_pokupatel,
                id_kontragenti_postavshik,
                id_otvetstvennyj,
                utverzhden,
                id_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = 0;
        
        $stmt->bind_param(
            'ssiiiii',
            $data['data_zakaza'],
            $data['nomer_zakaza'],
            $data['id_postavschika'],
            $data['id_organizacii'],
            $data['id_otvetstvennogo'],
            $utverzhden,
            $id_index
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $zakaz_id = $mysqli->insert_id;
        $stmt->close();
        
        // Insert line items with id_index
        foreach ($data['tovary'] as $index => $tovar) {
            if (empty($tovar['naimenovanie_tovara']) || empty($tovar['kolichestvo'])) {
                continue;
            }
            
            $nds_id = !empty($tovar['nds_id']) ? $tovar['nds_id'] : null;
            $obshchaya_summa = !empty($tovar['summa_stavka']) ? $tovar['summa_stavka'] : 0;
            $obshchaya_summa = !empty($tovar['summa']) ? $tovar['summa'] : 0;
            $ed_cena = !empty($tovar['cena']) ? $tovar['cena'] : 0;
            $id_sklada = !empty($tovar['id_sklada']) ? $tovar['id_sklada'] : null;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    id_sklada,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            
            $stmt->bind_param(
                'iiiiiddidd',
                $zakaz_id,
                $id_index,
                $id_tovara,
                $id_edinitsii,
                $id_sklada,
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
            'zakaz_id' => $zakaz_id
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function obnovitZakazDokumentPokupatieliu($mysqli, $zakaz_id, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['data_zakaza']) || empty($data['nomer_zakaza']) || 
            empty($data['id_postavschika']) || empty($data['id_organizacii']) || 
            empty($data['id_otvetstvennogo']) || empty($data['tovary'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
        
        $get_index_query = "SELECT id_index FROM zakazy_pokupatelei WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $zakaz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $id_index = $doc['id_index'];
        
        
        $query = "
            UPDATE  zakazy_pokupatelei SET
                data_dokumenta = ?,
                nomer = ?,
                id_kontragenti_pokupatel = ?,
                id_kontragenti_postavshik = ?,
                id_otvetstvennyj = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $stmt->bind_param(
            'ssiiii',
            $data['data_zakaza'],
            $data['nomer_zakaza'],
            $data['id_postavschika'],
            $data['id_organizacii'],
            $data['id_otvetstvennogo'],
            $zakaz_id
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
            $id_sklada = !empty($tovar['id_sklada']) ? $tovar['id_sklada'] : null;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_index,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    id_sklada,
                    kolichestvo,
                    cena,
                    id_stavka_nds,
                    summa_nds,
                    summa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $id_tovara = !empty($tovar['id_tovara']) ? $tovar['id_tovara'] : null;
            $id_edinitsii = !empty($tovar['id_edinitsii']) ? $tovar['id_edinitsii'] : null;
            
            $stmt->bind_param(
                'iiiiidiidd',
                $zakaz_id,
                $id_index,
                $id_tovara,
                $id_edinitsii,
                $id_sklada,
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
            'zakaz_id' => $zakaz_id
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function udalitZakazDokument($mysqli, $zakaz_id) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_index_query = "SELECT id_index FROM zakazy_pokupatelei WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $zakaz_id);
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
        
        
        $delete_order_query = "DELETE FROM zakazy_pokupatelei WHERE id = ?";
        $stmt = $mysqli->prepare($delete_order_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления заказа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $zakaz_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении заказа: ' . $stmt->error);
        }
        $stmt->close();
        
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