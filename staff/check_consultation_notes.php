<?php
// check_consultation_notes.php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

redirectIfNotLoggedIn();

if (!isset($_GET['patient_id'])) {
    echo json_encode(['success' => false, 'error' => 'Patient ID required']);
    exit;
}

$patientId = $_GET['patient_id'];

try {
    // Verify patient belongs to staff
    $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
    $stmt->execute([$patientId, $_SESSION['user']['id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Get note count
    $stmt = $pdo->prepare("SELECT COUNT(*) as note_count FROM consultation_notes WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $noteCount = $result['note_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'hasNotes' => $noteCount > 0,
        'noteCount' => $noteCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>