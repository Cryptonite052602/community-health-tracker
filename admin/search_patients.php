<?php
// search_patients.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$term = trim($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

global $pdo;

try {
    // Search for patients by name, showing account status
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.full_name,
            DATE_FORMAT(p.date_of_birth, '%M %d, %Y') as date_of_birth,
            p.age,
            p.gender,
            p.sitio,
            p.contact,
            p.civil_status,
            DATE_FORMAT(p.last_checkup, '%M %d, %Y') as last_checkup,
            p.user_id,
            u.id as has_account,
            u.email as account_email,
            u.username as account_username,
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Has Account'
                ELSE 'No Account'
            END as account_status
        FROM sitio1_patients p
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.full_name LIKE ? 
        ORDER BY p.full_name ASC
        LIMIT 15
    ");
    
    $searchTerm = '%' . $term . '%';
    $stmt->execute([$searchTerm]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return as JSON
    echo json_encode($patients);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>