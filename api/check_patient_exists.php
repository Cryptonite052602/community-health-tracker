<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isStaff()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$fullName = trim($_POST['full_name'] ?? '');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');
$patientId = $_POST['patient_id'] ?? null;
$action = $_POST['action'] ?? 'add'; // 'add' or 'edit'

if (empty($fullName) || empty($dateOfBirth)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    // Check if patient already exists
    $query = "SELECT id FROM sitio1_patients 
              WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(:full_name)) 
              AND date_of_birth = :date_of_birth 
              AND added_by = :added_by 
              AND deleted_at IS NULL";
    
    $params = [
        ':full_name' => $fullName,
        ':date_of_birth' => $dateOfBirth,
        ':added_by' => $_SESSION['user']['id']
    ];
    
    // If editing, exclude the current patient
    if ($action === 'edit' && $patientId) {
        $query .= " AND id != :exclude_id";
        $params[':exclude_id'] = $patientId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $exists = $stmt->rowCount() > 0;
    
    echo json_encode([
        'exists' => $exists,
        'message' => $exists ? 'Patient already exists' : 'Patient does not exist'
    ]);
    
} catch (PDOException $e) {
    error_log("Error checking patient existence: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}