<?php

require_once 'id_index_helper.php';

function getOtgruzkiCount($mysqli) {
    $query = "SELECT COUNT(*) as total FROM otgruzki_tovarov_pokupatelyam WHERE zakryt = 0 OR zakryt IS NULL";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}


function getAllOtgruzki($mysqli, $limit, $offset) {
    $query = "
        SELECT 
            op.id,
            op.data_dokumenta,
            op.nomer,
            k.naimenovanie AS vendor_name,
            o.naimenovanie AS organization_name,
            u.user_name AS responsible_name
        FROM  otgruzki_tovarov_pokupatelyam op
        LEFT JOIN kontragenti k ON op.id_kontragenti = k.id
        LEFT JOIN organizacii o ON op.id_organizacii = o.id
        LEFT JOIN users u ON op.id_otvetstvennyj = u.user_id
        WHERE op.zakryt = 0 OR op.zakryt IS NULL
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
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $orders;
}

function fetchOtgruzkiHeader($mysqli, $id) {
    $query = "
        SELECT 
            op.id,
            op.id_index,
            op.data_dokumenta,
            op.nomer,
            op.id_kontragenti,
            op.id_organizacii,
            op.id_otvetstvennyj,
            op.id_sklada,
            op.id_zakazy_pokupatelei,
            op.utverzhden,
            op.zakryt,
            s.naimenovanie AS warehouse_name,
            k.naimenovanie AS vendor_name,
            k.INN AS vendor_inn,
            k.KPP AS vendor_kpp,
            o.naimenovanie AS organization_name,
            o.INN AS organization_inn,
            o.KPP AS organization_kpp,
            u.user_name AS responsible_name,
            zp.nomer AS customer_order_nomer
        FROM  otgruzki_tovarov_pokupatelyam op
        LEFT JOIN sklady s ON op.id_sklada = s.id
        LEFT JOIN kontragenti k ON op.id_kontragenti = k.id
        LEFT JOIN organizacii o ON op.id_organizacii = o.id
        LEFT JOIN users u ON op.id_otvetstvennyj = u.user_id
        LEFT JOIN zakazy_pokupatelei zp ON op.id_zakazy_pokupatelei = zp.id
        
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
            t.naimenovanie AS product_name,
            sd.id_serii,
            ser.nomer AS seria_name,
            sd.id_edinicy_izmereniya,
            e.naimenovanie AS unit_name,
            sd.id_sklada AS warehouse_id,
            s.naimenovanie AS warehouse_name,
            sd.kolichestvo AS quantity,
            sd.cena AS unit_price,
            sd.id_stavka_nds,
            sn.stavka_nds,
            sd.summa_nds AS nds_amount,
            sd.summa AS total_amount
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
        
    
        if (empty($data['otgruzki_date']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для создания заказа');
        }
        
        $id_index = getNextIdIndex($mysqli);
        
        if (!$zakaz_pokupatelya_id && !empty($data['zakaz_id'])) {
            $zakaz_pokupatelya_id = intval($data['zakaz_id']);
        }
        
        
        $query = "
            INSERT INTO  otgruzki_tovarov_pokupatelyam (
                data_dokumenta,
                id_kontragenti,
                id_organizacii,
                id_otvetstvennyj,
                id_sklada,
                id_zakazy_pokupatelei,
                utverzhden,
                id_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        $utverzhden = 0;
        $warehouse_id = !empty($data['warehouse_id']) ? $data['warehouse_id'] : null;
        
        $stmt->bind_param(
            'siiiiiii',
            $data['otgruzki_date'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $warehouse_id,
            $zakaz_pokupatelya_id,
            $utverzhden,
            $id_index
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при сохранении заказа: ' . $stmt->error);
        }
        
        $schet_id = $mysqli->insert_id;
        $stmt->close();
        
        // Set nomer = id
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
        
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            $seria_id = !empty($product['seria_id']) ? $product['seria_id'] : null;
            $seria_name = !empty($product['seria_name']) ? $product['seria_name'] : null;
            
            // Handle series: if seria_name provided but no seria_id, check if it exists or create it
            if ($seria_name && !$seria_id && $product_id) {
                // Check if series with this name already exists for this product
                $check_seria = "SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?";
                $stmt_check = $mysqli->prepare($check_seria);
                if ($stmt_check) {
                    $stmt_check->bind_param('si', $seria_name, $product_id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing_seria = $result_check->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing_seria) {
                        $seria_id = $existing_seria['id'];
                    } else {
                        // Create new series
                        $insert_seria = "INSERT INTO serii (nomer, id_tovary_i_uslugi) VALUES (?, ?)";
                        $stmt_seria = $mysqli->prepare($insert_seria);
                        if ($stmt_seria) {
                            $stmt_seria->bind_param('si', $seria_name, $product_id);
                            if ($stmt_seria->execute()) {
                                $seria_id = $mysqli->insert_id;
                            }
                            $stmt_seria->close();
                        }
                    }
                }
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
                $product_id,
                $seria_id,
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
        
        
        if ($zakaz_pokupatelya_id) {
            
            $select_query = "SELECT id_scheta_na_oplatu_pokupatelyam FROM zakazy_pokupatelei WHERE id = ?";
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
            if (!empty($existing['id_scheta_na_oplatu_pokupatelyam'])) {
                $existing_ids = json_decode($existing['id_scheta_na_oplatu_pokupatelyam'], true);
                if (is_array($existing_ids)) {
                    $schet_ids = $existing_ids;
                } else {
                    $schet_ids = [$existing_ids];
                }
            }
            
            
            $shipment_ref = ['id' => $schet_id, 'type' => 'shipment'];
            $already_exists = false;
            foreach ($schet_ids as $ref) {
                if (is_array($ref) && $ref['id'] == $schet_id && $ref['type'] == 'shipment') {
                    $already_exists = true;
                    break;
                }
            }
            if (!$already_exists) {
                $schet_ids[] = $shipment_ref;
            }
            
        
            $update_query = "UPDATE zakazy_pokupatelei SET id_scheta_na_oplatu_pokupatelyam = ? WHERE id = ?";
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
        
        if (empty($data['otgruzki_date']) || 
            empty($data['vendor_id']) || empty($data['organization_id']) || 
            empty($data['responsible_id']) || empty($data['products'])) {
            throw new Exception('Недостаточно данных для обновления заказа');
        }
        
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
                id_kontragenti = ?,
                id_organizacii = ?,
                id_otvetstvennyj = ?,
                id_sklada = ?,
                id_zakazy_pokupatelei = ?
            WHERE id = ?
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса: ' . $mysqli->error);
        }
        
        
        $warehouse_id = !empty($data['warehouse_id']) ? $data['warehouse_id'] : null;
        $zakaz_id = !empty($data['zakaz_id']) ? intval($data['zakaz_id']) : null;
        
        $stmt->bind_param(
            'siiiiii',
            $data['otgruzki_date'],
            $data['vendor_id'],
            $data['organization_id'],
            $data['responsible_id'],
            $warehouse_id,
            $zakaz_id,
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
        
        
        foreach ($data['products'] as $index => $product) {
            if (empty($product['product_name']) || empty($product['quantity'])) {
                continue;
            }
            
            $nds_id = !empty($product['nds_id']) ? $product['nds_id'] : null;
            $nds_amount = !empty($product['summa_stavka']) ? $product['summa_stavka'] : 0;
            $total_amount = !empty($product['summa']) ? $product['summa'] : 0;
            $unit_price = !empty($product['price']) ? $product['price'] : 0;
            $warehouse_id = !empty($product['warehouse_id']) ? $product['warehouse_id'] : null;
            
            $product_id = !empty($product['product_id']) ? $product['product_id'] : null;
            $unit_id = !empty($product['unit_id']) ? $product['unit_id'] : null;
            $seria_id = !empty($product['seria_id']) ? $product['seria_id'] : null;
            $seria_name = !empty($product['seria_name']) ? $product['seria_name'] : null;
            
            // Handle series: if seria_name provided but no seria_id, check if it exists or create it
            if ($seria_name && !$seria_id && $product_id) {
                // Check if series with this name already exists for this product
                $check_seria = "SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?";
                $stmt_check = $mysqli->prepare($check_seria);
                if ($stmt_check) {
                    $stmt_check->bind_param('si', $seria_name, $product_id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing_seria = $result_check->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing_seria) {
                        $seria_id = $existing_seria['id'];
                    } else {
                        // Create new series
                        $insert_seria = "INSERT INTO serii (nomer, id_tovary_i_uslugi) VALUES (?, ?)";
                        $stmt_seria = $mysqli->prepare($insert_seria);
                        if ($stmt_seria) {
                            $stmt_seria->bind_param('si', $seria_name, $product_id);
                            if ($stmt_seria->execute()) {
                                $seria_id = $mysqli->insert_id;
                            }
                            $stmt_seria->close();
                        }
                    }
                }
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
                $product_id,
                $seria_id,
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
        
        
        if ($zakaz_pokupatelya_id) {
            $select_query = "SELECT id_scheta_na_oplatu_pokupatelyam FROM zakazy_pokupatelei WHERE id = ?";
            $stmt = $mysqli->prepare($select_query);
            if ($stmt) {
                $stmt->bind_param('i', $zakaz_pokupatelya_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();
                
                if ($existing && !empty($existing['id_scheta_na_oplatu_pokupatelyam'])) {
                    $schet_ids = json_decode($existing['id_scheta_na_oplatu_pokupatelyam'], true);
                    if (is_array($schet_ids)) {
                        $schet_ids = array_filter($schet_ids, function($val) use ($id) {
                            
                            if (is_array($val)) {
                                return !($val['id'] == $id && $val['type'] == 'shipment');
                            }
                            return $val != $id;
                        });
                        $schet_ids = array_values($schet_ids);
                    }
                    
                    $update_query = "UPDATE zakazy_pokupatelei SET id_scheta_na_oplatu_pokupatelyam = ? WHERE id = ?";
                    $stmt = $mysqli->prepare($update_query);
                    if ($stmt) {
                        $schet_ids_json = !empty($schet_ids) ? json_encode($schet_ids) : null;
                        $stmt->bind_param('si', $schet_ids_json, $zakaz_pokupatelya_id);
                        $stmt->execute();
                        $stmt->close();
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