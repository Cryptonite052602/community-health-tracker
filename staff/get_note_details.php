<?php
// staff/get_note_details.php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

redirectIfNotLoggedIn();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Note ID required']);
    exit;
}

$noteId = $_GET['id'];

try {
    // Get note details with patient verification
    $query = "SELECT cn.*, u.full_name as created_by_name, p.added_by
              FROM consultation_notes cn
              LEFT JOIN sitio1_users u ON cn.created_by = u.id
              LEFT JOIN sitio1_patients p ON cn.patient_id = p.id
              WHERE cn.id = ? AND p.added_by = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$noteId, $_SESSION['user']['id']]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($note) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'note' => $note
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Note not found or access denied']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>