<?php

/**
 * Delete an arrival document and all associated line items
 * @param mysqli $mysqli Database connection
 * @param int $document_id Document ID from postupleniya_tovarov
 * @return array Array with 'success' bool and 'message' string
 */
function deleteArrivalDocument($mysqli, $document_id) {
    try {
        $mysqli->begin_transaction();
        
        // First, verify the document exists
        $verify_sql = "SELECT id FROM " . TABLE_ARRIVALS . " WHERE id = ?";
        $verify_stmt = $mysqli->stmt_init();
        
        if (!$verify_stmt->prepare($verify_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $verify_stmt->bind_param("i", $document_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $document = $result->fetch_assoc();
        
        if (!$document) {
            throw new Exception("Документ не найден.");
        }
        
        // Delete all line items associated with this document
        $delete_lines_sql = "DELETE FROM " . TABLE_DOCUMENT_LINES . " WHERE " . COL_LINE_DOCUMENT_ID . " = ?";
        $delete_lines_stmt = $mysqli->stmt_init();
        
        if (!$delete_lines_stmt->prepare($delete_lines_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $delete_lines_stmt->bind_param("i", $document_id);
        
        if (!$delete_lines_stmt->execute()) {
            throw new Exception("Error al eliminar líneas del documento: " . $mysqli->error);
        }
        
        // Delete the document itself
        $delete_doc_sql = "DELETE FROM " . TABLE_ARRIVALS . " WHERE id = ?";
        $delete_doc_stmt = $mysqli->stmt_init();
        
        if (!$delete_doc_stmt->prepare($delete_doc_sql)) {
            throw new Exception("SQL error: " . $mysqli->error);
        }
        
        $delete_doc_stmt->bind_param("i", $document_id);
        
        if (!$delete_doc_stmt->execute()) {
            throw new Exception("Error al eliminar el documento: " . $mysqli->error);
        }
        
        $mysqli->commit();
        return array('success' => true, 'message' => 'Documento eliminado correctamente');
    } catch (Exception $e) {
        $mysqli->rollback();
        return array('success' => false, 'message' => $e->getMessage());
    }
}

/**
 * Handle the complete deletion process for a document
 * Verifies session, checks document_id parameter, and deletes the document
 * Redirects on success or displays error message
 */
function handleDeleteDocument() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: log_in.php');
        exit;
    }

    require 'config/database_config.php';
    
    if (!isset($mysqli)) {
        $mysqli = require 'config/database.php';
    }

    // Check if document_id is provided
    if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
        header('Location: admin_page.php');
        exit;
    }

    $document_id = intval($_GET['product_id']);

    // Delete the arrival document and all associated line items
    $result = deleteArrivalDocument($mysqli, $document_id);

    if ($result['success']) {
        // Redirect to admin page after successful deletion
        header('Location: admin_page.php');
        exit;
    } else {
        die("Error: " . $result['message']);
    }
}

?>
