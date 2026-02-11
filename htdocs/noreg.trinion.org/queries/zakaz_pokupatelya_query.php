<?php

function getOrdersCount($mysqli) {
    $query = "SELECT COUNT(*) as total FROM zakazy_postavshchikam";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getAllOrders($mysqli, $limit, $offset) {
    $query = "
        SELECT 
            zp.id,
            zp.data_dokumenta,
            zp.nomer,
            k.naimenovanie AS vendor_name,
            o.naimenovanie AS organization_name,
            u.user_name AS responsible_name
        FROM  zakazy_pokupatelei zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_pokupatel = k.id
        LEFT JOIN organizacii o ON zp.id_organizacii = o.id
        LEFT JOIN users u ON zp.id_otvetstvennyj = u.user_id
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
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $orders;
}

function fetchOrderHeader($mysqli, $zakaz_id) {
    $query = "
        SELECT 
            zp.id,
            zp.data_dokumenta,
            zp.nomer,
            zp.id_kontragenti_pokupatel,
            zp.id_organizacii,
            zp.id_otvetstvennyj,
            zp.utverzhden,
            zp.id_sklada,
            sl.id AS warehouse_id,
            sl.naimenovanie AS warehouse_name,
            k.naimenovanie AS vendor_name,
            k.INN AS vendor_inn,
            k.KPP AS vendor_kpp,
            o.naimenovanie AS organization_name,
            o.INN AS organization_inn,
            o.KPP AS organization_kpp,
            u.user_name AS responsible_name
        FROM zakazy_pokupatelei zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_pokupatel = k.id
        LEFT JOIN organizacii o ON zp.id_organizacii = o.id
        LEFT JOIN users u ON zp.id_otvetstvennyj = u.user_id
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


function fetchOrderLineItems($mysqli, $zakaz_id) {
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
    $stmt->bind_param('i', $zakaz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $items;
}


function createOrderDocument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        // Validate required fields
        if (empty($data['order_date']) || empty($data['order_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        // Insert order header
        $query = "
            INSERT INTO zakazy_pokupatelei (
                data_dokumenta,
                nomer,
                id_kontragenti_pokupatel,
                id_organizacii,
                id_otvetstvennyj,
                utverzhden
            ) VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = isset($data['utverzhden']) && $data['utverzhden'] == 1 ? 1 : 0;
        
        $stmt->bind_param(
            'ssiiii',
            $data['order_date'],
            $data['order_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $utverzhden
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

/**
 * Update existing order document
 */
function updateOrderDocument($mysqli, $zakaz_id, $data) {
    try {
        $mysqli->begin_transaction();
        
        // Validate required fields
        if (empty($data['order_date']) || empty($data['order_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
        // Update order header
        $query = "
            UPDATE  zakazy_pokupatelei SET
                data_dokumenta = ?,
                nomer = ?,
                id_kontragenti_pokupatel = ?,
                id_organizacii = ?,
                id_otvetstvennyj = ?,
                utverzhden = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = isset($data['utverzhden']) && $data['utverzhden'] == 1 ? 1 : 0;
        
        $stmt->bind_param(
            'ssiiii',
            $data['order_date'],
            $data['order_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $utverzhden,
            $zakaz_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при обновлении заказа: ' . $stmt->error);
        }
        
        $stmt->close();
        
        // Delete existing line items
        $delete_query = "DELETE FROM stroki_dokumentov WHERE id_dokumenta = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param('i', $zakaz_id);
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