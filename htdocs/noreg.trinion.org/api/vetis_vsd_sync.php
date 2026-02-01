<?php


function syncDocumentsToDatabase($documents, $mysqli)
{
    if (!$documents || !is_array($documents)) {
        return [
            'success' => false,
            'error' => 'No documents provided for sync',
            'inserted' => 0,
            'updated' => 0
        ];
    }

    if (!isset($documents[0])) {
        $documents = [$documents];
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($documents as $index => $vet_doc) {
        try {
            
            $doc_uuid = $vet_doc['uuid'] ?? '';
            if (empty($doc_uuid)) {
                $errors[] = "Document $index: UUID is empty";
                continue;
            }
            
            $issue_date = $vet_doc['issueDate'] ?? '';
            $vet_dtype = $vet_doc['vetDType'] ?? '';
            $vet_dstatus = $vet_doc['vetDStatus'] ?? '';
            $last_update_date = $vet_doc['lastUpdateDate'] ?? '';
            
    
            $shipper_name = $vet_doc['enterprise'] ?? 'Не указано';
            $receiver_name = $vet_doc['consignee'] ?? 'Не указано';
            $product_name = $vet_doc['naimenovanie_tovara'] ?? '';
            
            $prod_date = $vet_doc['dateOfProduction'] ?? '';
            $exp_date = $vet_doc['expiryDate'] ?? '';
            
            
            if (empty($prod_date)) {
                $prod_date = NULL;
            }
            if (empty($exp_date)) {
                $exp_date = NULL;
            }

    
            $check_sql = "SELECT lastUpdateDate FROM vetis_vsd WHERE uuid = ?";
            $check_stmt = $mysqli->prepare($check_sql);
            if (!$check_stmt) {
                $errors[] = "Document $index ($doc_uuid): Prepare check failed - " . $mysqli->error;
                continue;
            }
            
            $check_stmt->bind_param('s', $doc_uuid);
            if (!$check_stmt->execute()) {
                $errors[] = "Document $index ($doc_uuid): Execute check failed - " . $check_stmt->error;
                $check_stmt->close();
                continue;
            }
            
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                
                $row = $result->fetch_assoc();
                $stored_last_update = $row['lastUpdateDate'];
                
                
                if (strcmp($last_update_date, $stored_last_update) > 0) {
                    $update_sql = "UPDATE vetis_vsd SET issueDate = ?, vetDType = ?, vetDStatus = ?, lastUpdateDate = ?, dateOfProduction = ?, expiryDate = ?, enterprise = ?, consignee = ?, naimenovanie_tovara = ? WHERE uuid = ?";
                    $update_stmt = $mysqli->prepare($update_sql);
                    if (!$update_stmt) {
                        $errors[] = "Document $index ($doc_uuid): Prepare update failed - " . $mysqli->error;
                        $check_stmt->close();
                        continue;
                    }
                    
                    
                    $update_stmt->bind_param('ssssssssss', $issue_date, $vet_dtype, $vet_dstatus, $last_update_date, $prod_date, $exp_date, $shipper_name, $receiver_name, $product_name, $doc_uuid);
                    if (!$update_stmt->execute()) {
                        $errors[] = "Document $index ($doc_uuid): Execute update failed - " . $update_stmt->error;
                    } else {
                        $updated++;
                    }
                    $update_stmt->close();
                } else {
                    $skipped++;
                }
            } else {
            
                $insert_sql = "INSERT INTO vetis_vsd (uuid, issueDate, vetDType, vetDStatus, lastUpdateDate, dateOfProduction, expiryDate, enterprise, consignee, naimenovanie_tovara) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $mysqli->prepare($insert_sql);
                if (!$insert_stmt) {
                    $errors[] = "Document $index ($doc_uuid): Prepare insert failed - " . $mysqli->error;
                    $check_stmt->close();
                    continue;
                }
                
                $insert_stmt->bind_param('ssssssssss', $doc_uuid, $issue_date, $vet_dtype, $vet_dstatus, $last_update_date, $prod_date, $exp_date, $shipper_name, $receiver_name, $product_name);
                if (!$insert_stmt->execute()) {
                    $errors[] = "Document $index ($doc_uuid): Execute insert failed - " . $insert_stmt->error;
                } else {
                    $inserted++;
                }
                $insert_stmt->close();
            }
            
            $check_stmt->close();

        } catch (Exception $e) {
            $errors[] = "Document $index: Exception - " . $e->getMessage();
        }
    }

    return [
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'total' => count($documents),
        'errors' => $errors
    ];
}
?>
