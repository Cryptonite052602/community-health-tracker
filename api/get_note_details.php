<?php
// api/get_note_details.php
session_start();
require_once __DIR__ . '/../includes/auth.php';


redirectIfNotLoggedIn();

header('Content-Type: application/json');

$note_id = $_GET['id'] ?? 0;

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'Note ID is required']);
    exit;
}

try {
    // Get note details with creator info
    $stmt = $pdo->prepare("
        SELECT cn.*, u.full_name as created_by_name 
        FROM consultation_notes cn
        LEFT JOIN sitio1_users u ON cn.created_by = u.id
        WHERE cn.id = ? 
        AND EXISTS (
            SELECT 1 FROM sitio1_patients p 
            WHERE p.id = cn.patient_id AND p.added_by = ?
        )
    ");
    $stmt->execute([$note_id, $_SESSION['user']['id']]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($note) {
        echo json_encode(['success' => true, 'note' => $note]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Note not found or access denied']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching note: ' . $e->getMessage()]);
}