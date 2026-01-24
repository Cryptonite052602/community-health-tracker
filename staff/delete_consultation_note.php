<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

if (isset($_GET['note_id'])) {
    try {
        $note_id = $_GET['note_id'];
        $user_id = $_SESSION['user']['id'] ?? null;
        
        // Verify the note belongs to a patient of this staff
        $stmt = $pdo->prepare("
            SELECT cn.id 
            FROM consultation_notes cn
            INNER JOIN sitio1_patients p ON cn.patient_id = p.id
            WHERE cn.id = ? AND p.added_by = ?
        ");
        $stmt->execute([$note_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Delete the note
            $stmt = $pdo->prepare("DELETE FROM consultation_notes WHERE id = ?");
            $stmt->execute([$note_id]);
            
            if ($stmt->rowCount() > 0) {
                echo "Consultation note deleted successfully!";
            } else {
                echo "Error: Note could not be deleted.";
            }
        } else {
            echo "Error: Note not found or you don't have permission to delete it.";
        }
        
    } catch (PDOException $e) {
        error_log("Database error deleting note: " . $e->getMessage());
        echo "Error deleting note: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("Error deleting note: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Error: Invalid request. Note ID required.";
}
?>