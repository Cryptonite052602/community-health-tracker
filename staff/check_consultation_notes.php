<?php
// api/check_consultation_notes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Content-Type: application/json');
    echo json_encode(['hasNotes' => false, 'noteCount' => 0]);
    exit();
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
        echo json_encode(['hasNotes' => false, 'noteCount' => 0]);
        exit();
    }
    
    $patient_id = intval($_GET['patient_id']);
    $user_id = $_SESSION['user']['id'];
    
    // Verify patient belongs to current user
    $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
    $stmt->execute([$patient_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['hasNotes' => false, 'noteCount' => 0]);
        exit();
    }
    
    // Count consultation notes
    $stmt = $pdo->prepare("SELECT COUNT(*) as note_count FROM consultation_notes WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $noteCount = $result['note_count'] ?? 0;
    
    echo json_encode([
        'hasNotes' => $noteCount > 0,
        'noteCount' => $noteCount
    ]);
    
} catch (Exception $e) {
    error_log("Check notes error: " . $e->getMessage());
    echo json_encode(['hasNotes' => false, 'noteCount' => 0]);
}
?>