<?php

require_once 'id_index_helper.php';

/**
 * Get count of all orders
 */
function getZakazCount($mysqli) {
    $query = "SELECT COUNT(*) as total FROM zakazy_postavshchikam WHERE zakryt = 0 OR zakryt IS NULL";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

/**
 * Get all orders with pagination
 */
function getAllZakazy($mysqli, $limit, $offset) {
    $query = "
        SELECT 
            zp.id,
            zp.data_dokumenta,
            zp.nomer,
            k.naimenovanie AS vendor_name,
            o.naimenovanie AS organization_name,
            u.user_name AS responsible_name
        FROM zakazy_postavshchikam zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_postavshchik = k.id
        LEFT JOIN kontragenti o ON zp.id_kontragenti_pokupatel = o.id
        LEFT JOIN users u ON zp.id_otvetstvennyj = u.user_id
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
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $orders;
}

function fetchZakazHeader($mysqli, $zakaz_id) {
    $query = "
        SELECT 
            zp.id,
            zp.id_index,
            zp.data_dokumenta,
            zp.nomer,
            zp.id_kontragenti_postavshchik,
            zp.id_kontragenti_pokupatel,
            zp.id_otvetstvennyj,
            zp.utverzhden,
            zp.zakryt,
            zp.id_scheta_na_oplatu_postavshchikam,
            k.naimenovanie AS vendor_name,
            k.INN AS vendor_inn,
            k.KPP AS vendor_kpp,
            o.naimenovanie AS organization_name,
            o.INN AS organization_inn,
            o.KPP AS organization_kpp,
            u.user_name AS responsible_name
        FROM zakazy_postavshchikam zp
        LEFT JOIN kontragenti k ON zp.id_kontragenti_postavshchik = k.id
        LEFT JOIN kontragenti o ON zp.id_kontragenti_pokupatel = o.id
        LEFT JOIN users u ON zp.id_otvetstvennyj = u.user_id
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


function fetchZakazLineItems($mysqli, $id_index) {
    $query = "
        SELECT 
            sd.id,
            sd.id_index,
            sd.id_tovary_i_uslugi,
            t.naimenovanie AS product_name,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS unit_name,
            sd.kolichestvo AS quantity,
            sd.cena AS unit_price,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds AS nds_amount,
            sd.summa AS total_amount,
            sd.id_sklada,
            sk.naimenovanie AS warehouse_name
        FROM stroki_dokumentov sd
        LEFT JOIN tovary_i_uslugi t ON sd.id_tovary_i_uslugi = t.id
        LEFT JOIN edinicy_izmereniya e ON sd.id_edinicy_izmereniya = e.id
        LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
        LEFT JOIN sklady sk ON sd.id_sklada = sk.id
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


function createZakazDocument($mysqli, $data, $document_id = null) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['order_date']) || empty($data['order_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        // Get next id_index
        $id_index = getNextIdIndex($mysqli);
        
        // Insert order header
        $query = "
            INSERT INTO zakazy_postavshchikam (
                data_dokumenta,
                nomer,
                id_kontragenti_postavshchik,
                id_kontragenti_pokupatel,
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
            $data['order_date'],
            $data['order_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $utverzhden,
            $id_index
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $zakaz_id = $mysqli->insert_id;
        $stmt->close();
        
        
        if ($document_id) {
            $linked_docs = [['id' => $document_id, 'type' => 'invoice']];
            $linked_docs_json = json_encode($linked_docs);
            
            $link_query = "UPDATE zakazy_postavshchikam SET id_scheta_na_oplatu_postavshchikam = ? WHERE id = ?";
            $link_stmt = $mysqli->prepare($link_query);
            if (!$link_stmt) {
                throw new Exception('Ошибка при связывании документов: ' . $mysqli->error);
            }
            
            $link_stmt->bind_param('si', $linked_docs_json, $zakaz_id);
            if (!$link_stmt->execute()) {
                throw new Exception('Ошибка при сохранении связи документов: ' . $link_stmt->error);
            }
            $link_stmt->close();
        }
        
        // Insert line items
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            
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
                    summa,
                    id_sklada
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $stmt->bind_param(
                'iiiidiiddi',
                $zakaz_id,
                $id_index,
                $product_id,
                $unit_id,
                $product['quantity'],
                $unit_price,
                $nds_id,
                $nds_amount,
                $total_amount,
                $warehouse_id
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


function updateZakazDocument($mysqli, $zakaz_id, $data, $document_id = null) {
    try {
        $mysqli->begin_transaction();
        
        // Validate required fields
        if (empty($data['order_date']) || empty($data['order_number']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
        // Get existing id_index
        $get_index_query = "SELECT id_index FROM zakazy_postavshchikam WHERE id = ?";
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
        
        // Update order header
        $query = "
            UPDATE zakazy_postavshchikam SET
                data_dokumenta = ?,
                nomer = ?,
                id_kontragenti_postavshchik = ?,
                id_kontragenti_pokupatel = ?,
                id_otvetstvennyj = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $stmt->bind_param(
            'ssiiii',
            $data['order_date'],
            $data['order_number'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $zakaz_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при обновлении заказа: ' . $stmt->error);
        }
        
        $stmt->close();
        
    
        if ($document_id) {
            $select_query = "SELECT id_scheta_na_oplatu_postavshchikam FROM zakazy_postavshchikam WHERE id = ?";
            $doc_stmt = $mysqli->prepare($select_query);
            if (!$doc_stmt) {
                throw new Exception('Ошибка при получении связанных документов: ' . $mysqli->error);
            }
            
            $doc_stmt->bind_param('i', $zakaz_id);
            $doc_stmt->execute();
            $result = $doc_stmt->get_result();
            $existing = $result->fetch_assoc();
            $doc_stmt->close();
            
            $doc_ids = [];
            if (!empty($existing['id_scheta_na_oplatu_postavshchikam'])) {
                $existing_data = json_decode($existing['id_scheta_na_oplatu_postavshchikam'], true);
                $doc_ids = is_array($existing_data) ? $existing_data : [];
            }
            
            $invoice_ref = ['id' => $document_id, 'type' => 'invoice'];
            $already_exists = false;
            foreach ($doc_ids as $ref) {
                if (is_array($ref) && isset($ref['id']) && $ref['id'] == $document_id) {
                    $already_exists = true;
                    break;
                }
            }
            if (!$already_exists) {
                $doc_ids[] = $invoice_ref;
            }
            
            $linked_docs_json = json_encode($doc_ids);
            $link_query = "UPDATE zakazy_postavshchikam SET id_scheta_na_oplatu_postavshchikam = ? WHERE id = ?";
            $link_stmt = $mysqli->prepare($link_query);
            if (!$link_stmt) {
                throw new Exception('Ошибка при связывании документов: ' . $mysqli->error);
            }
            
            $link_stmt->bind_param('si', $linked_docs_json, $zakaz_id);
            if (!$link_stmt->execute()) {
                throw new Exception('Ошибка при сохранении связи документов: ' . $link_stmt->error);
            }
            $link_stmt->close();
        }
        
        // Delete existing line items using id_index
        $delete_query = "DELETE FROM stroki_dokumentov WHERE id_index = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param('i', $id_index);
        $stmt->execute();
        $stmt->close();
        
        // Insert updated line items with id_index
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            
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
                    summa,
                    id_sklada
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($line_query);
            if (!$stmt) {
                throw new Exception('Ошибка подготовки запроса строки: ' . $mysqli->error);
            }
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $stmt->bind_param(
                'iiiidiiddi',
                $zakaz_id,
                $id_index,
                $product_id,
                $unit_id,
                $product['quantity'],
                $unit_price,
                $nds_id,
                $nds_amount,
                $total_amount,
                $warehouse_id
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


function deleteZakazDocument($mysqli, $zakaz_id) {
    try {
        $mysqli->begin_transaction();
        
        // Get the id_index first
        $get_index_query = "SELECT id_index FROM zakazy_postavshchikam WHERE id = ?";
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
        
        // Delete line items using id_index
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
        
        // Delete the order header
        $delete_order_query = "DELETE FROM zakazy_postavshchikam WHERE id = ?";
        $stmt = $mysqli->prepare($delete_order_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления заказа: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $zakaz_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении заказа: ' . $stmt->error);
        }
        $stmt->close();
        
        
        $select_related_query = "SELECT id FROM scheta_na_oplatu WHERE id_scheta_na_oplatu_postavshchikam LIKE ?";
        $rel_stmt = $mysqli->prepare($select_related_query);
        if ($rel_stmt) {
            $search_pattern = '%"id":' . $zakaz_id . '%';
            $rel_stmt->bind_param('s', $search_pattern);
            $rel_stmt->execute();
            $result = $rel_stmt->get_result();
            
            while ($related = $result->fetch_assoc()) {
                $related_id = $related['id'];
                $get_data_query = "SELECT id_scheta_na_oplatu_postavshchikam FROM scheta_na_oplatu WHERE id = ?";
                $get_stmt = $mysqli->prepare($get_data_query);
                $get_stmt->bind_param('i', $related_id);
                $get_stmt->execute();
                $data_result = $get_stmt->get_result();
                $data_row = $data_result->fetch_assoc();
                $get_stmt->close();
                
                if (!empty($data_row['id_scheta_na_oplatu_postavshchikam'])) {
                    $current_data = json_decode($data_row['id_scheta_na_oplatu_postavshchikam'], true);
                    if (is_array($current_data)) {
                        $updated_data = array_filter($current_data, function($item) use ($zakaz_id) {
                            if (is_array($item) && isset($item['id'])) {
                                return $item['id'] != $zakaz_id;
                            }
                            return $item != $zakaz_id;
                        });
                        
                        $updated_json = json_encode($updated_data);
                        $update_query = "UPDATE scheta_na_oplatu SET id_scheta_na_oplatu_postavshchikam = ? WHERE id = ?";
                        $upd_stmt = $mysqli->prepare($update_query);
                        if ($upd_stmt) {
                            $upd_stmt->bind_param('si', $updated_json, $related_id);
                            $upd_stmt->execute();
                            $upd_stmt->close();
                        }
                    }
                }
            }
            $rel_stmt->close();
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
