<?php

function deleteArrivalDocument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        
        // Get the id_index first
        $get_index_query = "SELECT id_index FROM postupleniya_tovarov WHERE id = ?";
        $stmt = $mysqli->prepare($get_index_query);
        $stmt->bind_param('i', $document_id);
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
        
        // Delete the document header
        $delete_doc_query = "DELETE FROM postupleniya_tovarov WHERE id = ?";
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
            'message' => 'Документ успешно удален'
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

?>
