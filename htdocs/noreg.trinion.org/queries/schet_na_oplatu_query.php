<?php

function getOrdersCount($mysqli) {
    $query = "SELECT COUNT(*) as total FROM scheta_na_oplatu_pokupatelyam";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getAllschetov($mysqli, $limit, $offset) {
    $query = "
        SELECT 
            sp.id,
            sp.data_dokumenta,
            sp.nomer,
            k.naimenovanie AS vendor_name,
            o.naimenovanie AS organization_name,
            u.user_name AS responsible_name
        FROM  scheta_na_oplatu_pokupatelyam sp
        LEFT JOIN kontragenti k ON sp.id_kontragenti_pokupatel = k.id
        LEFT JOIN organizacii o ON sp.id_organizacii = o.id
        LEFT JOIN users u ON sp.id_otvetstvennyj = u.user_id
        ORDER BY sp.data_dokumenta DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $orders;
}

function fetchSchetHeader($mysqli, $id) {
    $query = "
        SELECT 
            sp.id,
            sp.data_dokumenta,
            sp.nomer,
            sp.id_kontragenti_pokupatel,
            sp.id_organizacii,
            sp.id_otvetstvennyj,
            sp.utverzhden,
            sp.Id_raschetnye_scheta_kontragenti_pokupatel,
            sp.Id_raschetnye_scheta_organizacii,
            k.naimenovanie AS vendor_name,
            k.INN AS vendor_inn,
            k.KPP AS vendor_kpp,
            o.naimenovanie AS organization_name,
            o.INN AS organization_inn,
            o.KPP AS organization_kpp,
            u.user_name AS responsible_name,
            rs1.naimenovanie AS schet_pokupatelya_naimenovanie,
            rs2.naimenovanie AS schet_postavschika_naimenovanie,
            rs2.naimenovanie_banka AS bank_name,
            rs2.BIK_banka AS bik_bank,
            rs2.nomer_korrespondentskogo_scheta AS correspondent_account,
            rs2.nomer AS account_number
        FROM  scheta_na_oplatu_pokupatelyam sp
        LEFT JOIN kontragenti k ON sp.id_kontragenti_pokupatel = k.id
        LEFT JOIN organizacii o ON sp.id_organizacii = o.id
        LEFT JOIN users u ON sp.id_otvetstvennyj = u.user_id
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


function fetchSchetLineItems($mysqli, $id) {
    $query = "
        SELECT 
            sd.id,
            sd.id_dokumenta,
            sd.id_tovary_i_uslugi,
            t.naimenovanie AS product_name,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS unit_name,
            sd.id_sklada,
            s.naimenovanie AS warehouse_name,
            sd.kolichestvo AS quantity,
            sd.cena AS unit_price,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds AS nds_amount,
            sd.summa AS total_amount
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi t ON sd.id_tovary_i_uslugi = t.id
        LEFT JOIN edinicy_izmereniya e ON sd.id_edinicy_izmereniya = e.id
        LEFT JOIN sklady s ON sd.id_sklada = s.id
        LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
        WHERE sd.id_dokumenta = ?
        ORDER BY sd.id ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $items;
}


function createSchetDocument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        // Validate required fields
        if (empty($data['schet_date']) || empty($data['schet_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        // Insert order header
        $query = "
            INSERT INTO scheta_na_oplatu_pokupatelyam (
                data_dokumenta,
                nomer,
                id_kontragenti_pokupatel,
                id_organizacii,
                id_otvetstvennyj,
                utverzhden,
                Id_raschetnye_scheta_kontragenti_pokupatel,
                Id_raschetnye_scheta_organizacii
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = isset($data['utverzhden']) && $data['utverzhden'] == 1 ? 1 : 0;
        $schet_pokupatelya_id = !empty($data['schet_pokupatelya_id']) ? $data['schet_pokupatelya_id'] : null;
        $schet_postavschika_id = !empty($data['schet_postavschika_id']) ? $data['schet_postavschika_id'] : null;
        
        $stmt->bind_param(
            'ssiiiiii',
            $data['schet_date'],
            $data['schet_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $utverzhden,
            $schet_pokupatelya_id,
            $schet_postavschika_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $zakaz_id = $mysqli->insert_id;
        $stmt->close();
        
        // Insert line items
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    id_sklada,
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
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            
            $stmt->bind_param(
                'iiiiididd',
                $zakaz_id,
                $product_id,
                $unit_id,
                $warehouse_id,
                $product['quantity'],
                $unit_price,
                $nds_id,
                $nds_amount,
                $total_amount
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

/**
 * Update existing order document
 */
function updateSchetDocument($mysqli, $id, $data) {
    try {
        $mysqli->begin_transaction();
        
        // Validate required fields
        if (empty($data['schet_date']) || empty($data['schet_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
        // Update order header
        $query = "
            UPDATE   scheta_na_oplatu_pokupatelyam SET
                data_dokumenta = ?,
                nomer = ?,
                id_kontragenti_pokupatel = ?,
                id_organizacii = ?,
                id_otvetstvennyj = ?,
                utverzhden = ?,
                Id_raschetnye_scheta_kontragenti_pokupatel = ?,
                Id_raschetnye_scheta_organizacii = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = isset($data['utverzhden']) && $data['utverzhden'] == 1 ? 1 : 0;
        $schet_pokupatelya_id = !empty($data['schet_pokupatelya_id']) ? $data['schet_pokupatelya_id'] : null;
        $schet_postavschika_id = !empty($data['schet_postavschika_id']) ? $data['schet_postavschika_id'] : null;
        
        $stmt->bind_param(
            'ssiiiiiii',
            $data['schet_date'],
            $data['schet_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $utverzhden,
            $schet_pokupatelya_id,
            $schet_postavschika_id,
            $id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при обновлении заказа: ' . $stmt->error);
        }
        
        $stmt->close();
        
        // Delete existing line items
        $delete_query = "DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // Insert updated line items
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $line_query = "
                INSERT INTO stroki_dokumentov (
                    id_dokumenta,
                    id_tovary_i_uslugi,
                    id_edinicy_izmereniya,
                    id_sklada,
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
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            
            $stmt->bind_param(
                'iiiiididdd',
                $id,
                $product_id,
                $unit_id,
                $warehouse_id,
                $product['quantity'],
                $unit_price,
                $nds_id,
                $nds_amount,
                $total_amount
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

/**
 * Delete an order and its line items
 */
function deleteOrderDocument($mysqli, $zakaz_id) {
    try {
        $mysqli->begin_transaction();
        
        // Delete line items first (foreign key dependency)
        $delete_items_query = "DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?";
        $stmt = $mysqli->prepare($delete_items_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления строк: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $zakaz_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении строк товара: ' . $stmt->error);
        }
        $stmt->close();
        
        // Delete the order header
        $delete_order_query = "DELETE FROM scheta_na_oplatu_pokupatelyam WHERE id = ?";
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