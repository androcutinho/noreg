<?php

require_once __DIR__ . '/../config/database_config.php';
require_once __DIR__ . '/id_index_helper.php';
require_once __DIR__ . '/entity_helpers.php';


function fetchSchetDataForPayment($mysqli, $schet_id) {
    $sql = "SELECT 
        sno.id,
        sno.data_dokumenta,
        sno.id_kontragenti_pokupatel,
        sno.id_kontragenti_postavshik,
        sno.pokupatelya,
        sno.ot_postavshchika,
        sno.id_index,
        kon_pokup.naimenovanie as vendor_name,
        kon_pokup.id as vendor_id,
        kon_post.naimenovanie as organization_name,
        kon_post.id as organization_id
    FROM scheta_na_oplatu sno
    LEFT JOIN kontragenti kon_pokup ON sno.id_kontragenti_pokupatel = kon_pokup.id
    LEFT JOIN kontragenti kon_post ON sno.id_kontragenti_postavshik = kon_post.id
    WHERE sno.id = ?";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }

    $stmt->bind_param("i", $schet_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}


function fetchSchetLineItemsForPayment($mysqli, $id_index) {
    $sql = "SELECT 
        sd.id,
        sd." . COL_LINE_PRODUCT_ID . " as product_id,
        ti.naimenovanie as product_name,
        sd." . COL_LINE_QUANTITY . " as quantity,
        sd." . COL_LINE_PRICE . " as unit_price,
        sd." . COL_LINE_NDS_ID . " as nds_id,
        sn.stavka_nds as vat_rate,
        sd." . COL_LINE_SUMMA . " as total_amount,
        sd." . COL_LINE_NDS_AMOUNT . " as nds_amount
    FROM " . stroki_dokumentov . " sd
    LEFT JOIN tovary_i_uslugi ti ON sd." . COL_LINE_PRODUCT_ID . " = ti.id
    LEFT JOIN stavki_nds sn ON sd." . COL_LINE_NDS_ID . " = sn.id
    WHERE sd.id_index = ?
    ORDER BY sd.id ASC";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return [];
    }

    $stmt->bind_param("i", $id_index);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = [];

    while ($row = $result->fetch_assoc()) {
        $line_items[] = $row;
    }
    
    $stmt->close();
    return $line_items;
}


function createPaymentDocument($mysqli, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        if (empty($data['schet_id'])) {
            throw new Exception('Требуется указать счет-фактуру');
        }
        
        $schet_id = intval($data['schet_id']);
        
        
        $schet_data = fetchSchetDataForPayment($mysqli, $schet_id);
        if (!$schet_data) {
            throw new Exception('Счет-фактура не найдена');
        }
        
        
        $id_index = getNextIdIndex($mysqli);
        
        
        $vhodyashchij = 0;
        $iskhodyashchij = 0;
        
        if (!empty($schet_data['pokupatelya'])) {
            $vhodyashchij = 1;
        } elseif (!empty($schet_data['ot_postavshchika'])) {
            $iskhodyashchij = 1;
        }
        
        
        $line_items = fetchSchetLineItemsForPayment($mysqli, $schet_data['id_index']);
        $total_summa = 0;
        
        foreach ($line_items as $item) {
            $total_summa += floatval($item['total_amount']) + floatval($item['nds_amount']);
        }
        
        
        $data_dokumenta = $data['schet_date'] ?? $schet_data['data_dokumenta'];
        $data_dokumenta = str_replace('T', ' ', $data_dokumenta);
        if (strlen($data_dokumenta) === 10) { 
            $data_dokumenta .= ' 00:00:00';
        }
        
        $vendor_id = intval($schet_data['vendor_id']);
        $organization_id = intval($schet_data['organization_id']);
        $total_summa = floatval($total_summa);
        
    
        $payment_sql = "INSERT INTO platezhi (
            data_dokumenta, 
            id_kontragenti_platelshik, 
            id_kontragenti_poluchatel, 
            nomer, 
            vhodyashchij, 
            iskhodyashchij, 
            summa, 
            id_index
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $payment_stmt = $mysqli->stmt_init();
        if (!$payment_stmt->prepare($payment_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $payment_stmt->bind_param(
            "siiiiiid",
            $data_dokumenta,
            $vendor_id,
            $organization_id,
            $schet_id,
            $vhodyashchij,
            $iskhodyashchij,
            $total_summa,
            $id_index
        );
        
        if (!$payment_stmt->execute()) {
            throw new Exception("Ошибка при создании документа платежа: " . $mysqli->error);
        }
        
        $document_id = $mysqli->insert_id;
        $payment_stmt->close();
        
        
        foreach ($line_items as $item) {
            $nds_id = intval($item['nds_id']);
            $summa = floatval($item['total_amount']);
            $summa_nds = floatval($item['nds_amount']);
            
            $line_sql = "INSERT INTO stroki_platezhej (
                id_dokumenta, 
                id_stavka_nds, 
                summa, 
                summa_nds
            ) VALUES (?, ?, ?, ?)";
            
            $line_stmt = $mysqli->stmt_init();
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iidd",
                $schet_id,
                $nds_id,
                $summa,
                $summa_nds
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки платежа: " . $mysqli->error);
            }
            
            $line_stmt->close();
        }
        
        $mysqli->commit();
        return ['success' => true, 'document_id' => $document_id];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updatePaymentDocument($mysqli, $document_id, $data) {
    try {
        $mysqli->begin_transaction();
        
        
        $get_doc_query = "SELECT id_index, nomer FROM platezhi WHERE id = ?";
        $get_stmt = $mysqli->stmt_init();
        if (!$get_stmt->prepare($get_doc_query)) {
            throw new Exception("Ошибка подготовки запроса: " . $mysqli->error);
        }
        
        $get_stmt->bind_param('i', $document_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $doc_data = $get_result->fetch_assoc();
        $get_stmt->close();
        
        if (!$doc_data) {
            throw new Exception('Документ платежа не найден');
        }
        
        $id_index = intval($doc_data['id_index']);
        $schet_id = intval($doc_data['nomer']);
        
        
        $schet_data = fetchSchetDataForPayment($mysqli, $schet_id);
        if (!$schet_data) {
            throw new Exception('Счет-фактура не найдена');
        }
        
        
        $data_dokumenta = $data['schet_date'] ?? date('Y-m-d H:i:s');
        $data_dokumenta = str_replace('T', ' ', $data_dokumenta);
        if (strlen($data_dokumenta) === 10) {
            $data_dokumenta .= ' 00:00:00';
        }
        
        $vendor_id = intval($schet_data['vendor_id']);
        $organization_id = intval($schet_data['organization_id']);
        $vhodyashchij = 0;
        $iskhodyashchij = 0;
        
        if (!empty($schet_data['pokupatelya'])) {
            $vhodyashchij = 1;
        } elseif (!empty($schet_data['ot_postavshchika'])) {
            $iskhodyashchij = 1;
        }
        
    
        $line_items = fetchSchetLineItemsForPayment($mysqli, $schet_data['id_index']);
        $total_summa = 0;
        
        foreach ($line_items as $item) {
            $total_summa += floatval($item['total_amount']) + floatval($item['nds_amount']);
        }
        
        $total_summa = floatval($total_summa);
        
    
        $update_sql = "UPDATE platezhi SET 
            data_dokumenta = ?,
            id_kontragenti_platelshik = ?,
            id_kontragenti_poluchatel = ?,
            vhodyashchij = ?,
            iskhodyashchij = ?,
            summa = ?
        WHERE id = ?";
        
        $update_stmt = $mysqli->stmt_init();
        if (!$update_stmt->prepare($update_sql)) {
            throw new Exception("Ошибка подготовки запроса: " . $mysqli->error);
        }
        
        $update_stmt->bind_param(
            "siiiidi",
            $data_dokumenta,
            $vendor_id,
            $organization_id,
            $vhodyashchij,
            $iskhodyashchij,
            $total_summa,
            $document_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Ошибка обновления документа: " . $update_stmt->error);
        }
        
        $update_stmt->close();
        
        
        $delete_sql = "DELETE FROM stroki_platezhej WHERE id_dokumenta = ?";
        $delete_stmt = $mysqli->stmt_init();
        if (!$delete_stmt->prepare($delete_sql)) {
            throw new Exception("Ошибка подготовки удаления: " . $mysqli->error);
        }
        
        $delete_stmt->bind_param('i', $schet_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("Ошибка удаления старых строк: " . $delete_stmt->error);
        }
        
        $delete_stmt->close();
        
    
        foreach ($line_items as $item) {
            $nds_id = intval($item['nds_id']);
            $summa = floatval($item['total_amount']);
            $summa_nds = floatval($item['nds_amount']);
            
            $line_sql = "INSERT INTO stroki_platezhej (
                id_dokumenta, 
                id_stavka_nds, 
                summa, 
                summa_nds
            ) VALUES (?, ?, ?, ?)";
            
            $line_stmt = $mysqli->stmt_init();
            if (!$line_stmt->prepare($line_sql)) {
                throw new Exception("SQL error: " . $mysqli->error);
            }
            
            $line_stmt->bind_param(
                "iidd",
                $schet_id,
                $nds_id,
                $summa,
                $summa_nds
            );
            
            if (!$line_stmt->execute()) {
                throw new Exception("Ошибка при добавлении строки платежа: " . $mysqli->error);
            }
            
            $line_stmt->close();
        }
        
        $mysqli->commit();
        
        return [
            'success' => true,
            'message' => 'Документ платежа успешно обновлен'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("[UPDATE PAYMENT] Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function deletePaymentDocument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        $get_query = "SELECT nomer FROM platezhi WHERE id = ?";
        $stmt = $mysqli->prepare($get_query);
        $stmt->bind_param('i', $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            throw new Exception('Документ не найден');
        }
        
        $schet_id = intval($doc['nomer']);
        
    
        $delete_items_query = "DELETE FROM stroki_platezhej WHERE id_dokumenta = ?";
        $stmt = $mysqli->prepare($delete_items_query);
        if (!$stmt) {
            throw new Exception('Ошибка подготовки запроса удаления строк: ' . $mysqli->error);
        }
        
        $stmt->bind_param('i', $schet_id);
        if (!$stmt->execute()) {
            throw new Exception('Ошибка при удалении строк платежа: ' . $stmt->error);
        }
        $stmt->close();
        
        $delete_doc_query = "DELETE FROM platezhi WHERE id = ?";
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
            'message' => 'Документ платежа успешно удален'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function fetchPaymentForDisplay($mysqli, $payment_id) {
    $sql = "SELECT 
        p.id,
        p.data_dokumenta,
        p.id_kontragenti_platelshik,
        p.id_kontragenti_poluchatel,
        p.nomer,
        p.vhodyashchij,
        p.iskhodyashchij,
        p.summa
    FROM platezhi p
    WHERE p.id = ?";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }

    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}

function fetchContractorInfo($mysqli, $contractor_id) {
    $sql = "SELECT 
        id,
        naimenovanie,
        inn,
        kpp,
        pochtovyj_adress
    FROM kontragenti
    WHERE id = ?";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }

    $stmt->bind_param("i", $contractor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}

function fetchPaymentLineItemsForDisplay($mysqli, $schet_id) {
    $sql = "SELECT 
        sp.id_dokumenta,
        sp.id_stavka_nds,
        sp.summa,
        sp.summa_nds,
        sno.data_dokumenta as schet_date
    FROM stroki_platezhej sp
    LEFT JOIN scheta_na_oplatu sno ON sp.id_dokumenta = sno.id
    WHERE sp.id_dokumenta = ?
    ORDER BY sp.id ASC";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return [];
    }

    $stmt->bind_param("i", $schet_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $line_items = [];

    while ($row = $result->fetch_assoc()) {
        $line_items[] = $row;
    }
    
    $stmt->close();
    return $line_items;
}

function fetchPaymentBankDetails($mysqli, $contractor_id) {
    $sql = "SELECT 
        rs.id,
        rs.naimenovanie_banka,
        rs.BIK_banka,
        rs.nomer_korrespondentskogo_scheta
    FROM raschetnye_scheta rs
    WHERE rs.id_kontragenti = ?
    LIMIT 1";

    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return null;
    }

    $stmt->bind_param("i", $contractor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}


function getPaymentsCount($mysqli, $type = 'vhodyashchij') {
    $is_incoming = ($type === 'vhodyashchij');
    
    if ($is_incoming) {
        $sql = "SELECT COUNT(*) as count FROM platezhi WHERE vhodyashchij = 1";
    } else {
        $sql = "SELECT COUNT(*) as count FROM platezhi WHERE iskhodyashchij = 1";
    }
    
    $result = $mysqli->query($sql);
    if (!$result) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return isset($row['count']) ? intval($row['count']) : 0;
}


function getAllPayments($mysqli, $limit, $offset, $type = 'vhodyashchij') {
    $is_incoming = ($type === 'vhodyashchij');
    
    $sql = "SELECT 
        p.id,
        p.nomer,
        p.data_dokumenta,
        p.summa,
        p.vhodyashchij,
        p.iskhodyashchij,
        k_payer.naimenovanie as payer_name,
        k_recipient.naimenovanie as recipient_name
    FROM platezhi p
    LEFT JOIN kontragenti k_payer ON p.id_kontragenti_platelshik = k_payer.id
    LEFT JOIN kontragenti k_recipient ON p.id_kontragenti_poluchatel = k_recipient.id
    WHERE " . ($is_incoming ? "p.vhodyashchij = 1" : "p.iskhodyashchij = 1") . "
    ORDER BY p.data_dokumenta DESC
    LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return [];
    }
    
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $payments;
}

function getPaymentsBySchetId($mysqli, $schet_id) {
    $sql = "SELECT DISTINCT
        p.id,
        p.nomer,
        p.data_dokumenta,
        p.summa,
        p.vhodyashchij,
        p.iskhodyashchij,
        k_payer.naimenovanie as payer_name,
        k_recipient.naimenovanie as recipient_name
    FROM platezhi p
    LEFT JOIN kontragenti k_payer ON p.id_kontragenti_platelshik = k_payer.id
    LEFT JOIN kontragenti k_recipient ON p.id_kontragenti_poluchatel = k_recipient.id
    INNER JOIN stroki_platezhej sp ON p.id = sp.id
    WHERE sp.id_dokumenta = ?
    ORDER BY p.data_dokumenta DESC";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($sql)) {
        return [];
    }
    
    $stmt->bind_param("i", $schet_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $payments;
}
?>

