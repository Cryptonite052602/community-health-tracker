<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

// Initialize variables
$stats = [
    'total_active_staff' => 0,
    'total_inactive_staff' => 0,
    'total_approved_residents' => 0,
    'total_pending_residents' => 0,
    'total_declined_residents' => 0,
    'total_unlinked_residents' => 0,
    'total_patients' => 0,
    'total_unlinked_patients' => 0,
    'linked_accounts_count' => 0,
    'recent_patients' => []
];

$error_message = null;

// Handle patient deletion
if (isset($_GET['delete_patient']) && isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    $patientId = $_GET['delete_patient'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get patient data before deletion for archive - ONLY from sitio1_patients
        $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patientData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patientData) {
            // Check if deleted_patients table exists, if not create it
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'deleted_patients'");
                if ($tableCheck->rowCount() == 0) {
                    // Create deleted_patients table that matches sitio1_patients structure but adds deleted_by
                    $pdo->exec("
                        CREATE TABLE deleted_patients (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            original_id INT NOT NULL,
                            user_id INT DEFAULT NULL,
                            bhw_assigned VARCHAR(100) DEFAULT NULL,
                            family_no VARCHAR(50) DEFAULT NULL,
                            fourps_member ENUM('Yes','No') DEFAULT 'No',
                            full_name VARCHAR(100) NOT NULL,
                            date_of_birth DATE DEFAULT NULL,
                            age INT DEFAULT NULL,
                            address TEXT DEFAULT NULL,
                            sitio VARCHAR(255) DEFAULT NULL,
                            disease VARCHAR(255) DEFAULT NULL,
                            contact VARCHAR(20) DEFAULT NULL,
                            last_checkup DATE DEFAULT NULL,
                            medical_history TEXT DEFAULT NULL,
                            added_by INT DEFAULT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            restored_at TIMESTAMP NULL DEFAULT NULL,
                            gender VARCHAR(10) DEFAULT NULL,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            consultation_type VARCHAR(20) DEFAULT 'onsite',
                            civil_status VARCHAR(20) DEFAULT NULL,
                            occupation VARCHAR(100) DEFAULT NULL,
                            consent_given TINYINT(1) DEFAULT 0,
                            consent_date DATETIME DEFAULT NULL,
                            patient_record_uid VARCHAR(50) DEFAULT NULL,
                            deleted_by INT NOT NULL
                        )
                    ");
                }
                
                // Check the actual structure of deleted_patients table
                $stmt = $pdo->query("DESCRIBE deleted_patients");
                $deletedColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                // Prepare data for insertion - only include columns that exist in both tables
                $columns = ['original_id'];
                $placeholders = ['?'];
                $values = [$patientData['id']];
                
                // Map columns from patientData to deleted_patients table
                $columnMappings = [
                    'user_id' => 'user_id',
                    'bhw_assigned' => 'bhw_assigned',
                    'family_no' => 'family_no',
                    'fourps_member' => 'fourps_member',
                    'full_name' => 'full_name',
                    'date_of_birth' => 'date_of_birth',
                    'age' => 'age',
                    'address' => 'address',
                    'sitio' => 'sitio',
                    'disease' => 'disease',
                    'contact' => 'contact',
                    'last_checkup' => 'last_checkup',
                    'medical_history' => 'medical_history',
                    'added_by' => 'added_by',
                    'gender' => 'gender',
                    'civil_status' => 'civil_status',
                    'occupation' => 'occupation',
                    'consent_given' => 'consent_given',
                    'consent_date' => 'consent_date',
                    'patient_record_uid' => 'patient_record_uid'
                ];
                
                foreach ($columnMappings as $sourceCol => $destCol) {
                    if (isset($patientData[$sourceCol]) && in_array($destCol, $deletedColumns)) {
                        $columns[] = $destCol;
                        $placeholders[] = "?";
                        $values[] = $patientData[$sourceCol];
                    }
                }
                
                // Add deleted_by
                $columns[] = 'deleted_by';
                $placeholders[] = "?";
                $values[] = $_SESSION['user']['id'];
                
                // Build and execute insert query
                $insertQuery = "INSERT INTO deleted_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute($values);
                
            } catch (Exception $e) {
                // If table creation fails, create a simpler version
                error_log("Table error: " . $e->getMessage());
                
                // Try to create a simpler table
                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS deleted_patients (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            original_id INT NOT NULL,
                            full_name VARCHAR(100) NOT NULL,
                            age INT DEFAULT NULL,
                            gender VARCHAR(10) DEFAULT NULL,
                            address TEXT DEFAULT NULL,
                            sitio VARCHAR(255) DEFAULT NULL,
                            contact VARCHAR(20) DEFAULT NULL,
                            added_by INT DEFAULT NULL,
                            deleted_by INT NOT NULL,
                            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert basic patient data
                    $stmt = $pdo->prepare("
                        INSERT INTO deleted_patients 
                        (original_id, full_name, age, gender, address, sitio, contact, added_by, deleted_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $patientData['id'],
                        $patientData['full_name'] ?? '',
                        $patientData['age'] ?? null,
                        $patientData['gender'] ?? null,
                        $patientData['address'] ?? null,
                        $patientData['sitio'] ?? null,
                        $patientData['contact'] ?? null,
                        $patientData['added_by'] ?? null,
                        $_SESSION['user']['id']
                    ]);
                } catch (Exception $simpleError) {
                    error_log("Simple table creation error: " . $simpleError->getMessage());
                    // If even simple creation fails, just do soft delete without archiving
                }
            }
            
            // Soft delete from main table
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$patientId]);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Patient record has been moved to archive successfully!';
            header('Location: admin_dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error deleting patient record: ' . $e->getMessage();
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle permanent deletion (skip archive)
if (isset($_GET['permanent_delete']) && isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    $patientId = $_GET['permanent_delete'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete from existing_info_patients if table exists
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'existing_info_patients'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("DELETE FROM existing_info_patients WHERE patient_id = ?");
                $stmt->execute([$patientId]);
            }
        } catch (Exception $e) {
            error_log("Error deleting from existing_info_patients: " . $e->getMessage());
        }
        
        // Delete from main patients table
        $stmt = $pdo->prepare("DELETE FROM sitio1_patients WHERE id = ?");
        $stmt->execute([$patientId]);
        
        $pdo->commit();
        $_SESSION['success_message'] = 'Patient record permanently deleted!';
        header('Location: admin_dashboard.php');
        exit();
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error permanently deleting patient record: ' . $e->getMessage();
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle patient update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_patient') {
    $response = ['success' => false, 'message' => '', 'errors' => []];
    
    try {
        $patientId = $_POST['patient_id'] ?? null;
        if (!$patientId) {
            throw new Exception("Patient ID is required");
        }
        
        // Validate required fields
        $requiredFields = ['full_name', 'age', 'gender', 'sitio', 'contact'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $response['errors'][$field] = "This field is required";
            }
        }
        
        if (!empty($response['errors'])) {
            $response['message'] = 'Please fill in all required fields';
            echo json_encode($response);
            exit;
        }
        
        // Prepare update data
        $updateFields = [
            'full_name' => $_POST['full_name'],
            'age' => $_POST['age'],
            'gender' => $_POST['gender'],
            'sitio' => $_POST['sitio'],
            'contact' => $_POST['contact'],
            'address' => $_POST['address'] ?? null,
            'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
            'civil_status' => $_POST['civil_status'] ?? null,
            'occupation' => $_POST['occupation'] ?? null,
            'disease' => $_POST['disease'] ?? null,
            'last_checkup' => !empty($_POST['last_checkup']) ? $_POST['last_checkup'] : null,
            'medical_history' => $_POST['medical_history'] ?? null,
            'bhw_assigned' => $_POST['bhw_assigned'] ?? null,
            'family_no' => $_POST['family_no'] ?? null,
            'fourps_member' => $_POST['fourps_member'] ?? 'No',
            'consent_given' => isset($_POST['consent_given']) ? 1 : 0,
            'consent_date' => !empty($_POST['consent_date']) ? $_POST['consent_date'] : null
        ];
        
        // Build the update query
        $setClauses = [];
        $params = [];
        
        foreach ($updateFields as $field => $value) {
            $setClauses[] = "$field = ?";
            $params[] = $value;
        }
        
        $params[] = $patientId;
        
        $updateQuery = "UPDATE sitio1_patients SET " . implode(", ", $setClauses) . " WHERE id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute($params);
        
        $response['success'] = true;
        $response['message'] = 'Patient record updated successfully!';
        
    } catch (Exception $e) {
        $response['message'] = 'Error updating patient: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get patient details for viewing/editing
if (isset($_GET['get_patient']) && is_numeric($_GET['get_patient'])) {
    try {
        $patientId = $_GET['get_patient'];
        $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            echo json_encode(['success' => true, 'patient' => $patient]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Patient not found']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching patient data']);
        exit;
    }
}

// Get stats for dashboard with error handling
try {
    // Check if tables exist before querying
    $tables = ['sitio1_staff', 'sitio1_users', 'sitio1_patients'];
    
    foreach ($tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() == 0) {
            throw new Exception("Table '$table' does not exist in the database.");
        }
    }
    
    // Check if existing_info_patients table exists
    $check = $pdo->query("SHOW TABLES LIKE 'existing_info_patients'");
    $has_existing_info_table = $check->rowCount() > 0;
    
    // Check sitio1_staff table structure
    $staffColumns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM sitio1_staff");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            $staffColumns[] = $column['Field'];
        }
    } catch (Exception $e) {
        error_log("Error checking staff table structure: " . $e->getMessage());
    }
    
    // ACTIVE STAFF - Fixed query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_staff WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_active_staff'] = $result ? $result['count'] : 0;
    
    // INACTIVE STAFF
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_staff WHERE is_active = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_inactive_staff'] = $result ? $result['count'] : 0;
    
    // APPROVED RESIDENTS
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_users WHERE role = 'patient' AND status = 'approved'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_approved_residents'] = $result ? $result['count'] : 0;
    
    // PENDING RESIDENTS
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_users WHERE role = 'patient' AND status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_pending_residents'] = $result ? $result['count'] : 0;
    
    // DECLINED RESIDENTS
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_users WHERE role = 'patient' AND status = 'declined'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_declined_residents'] = $result ? $result['count'] : 0;
    
    // UNLINKED RESIDENTS
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM sitio1_users u
        LEFT JOIN sitio1_patients p ON u.id = p.user_id
        WHERE u.role = 'patient' 
        AND u.status = 'approved'
        AND p.id IS NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_unlinked_residents'] = $result ? $result['count'] : 0;
    
    // TOTAL PATIENTS
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_patients WHERE deleted_at IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_patients'] = $result ? $result['count'] : 0;
    
    // UNLINKED PATIENTS
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitio1_patients WHERE user_id IS NULL AND deleted_at IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_unlinked_patients'] = $result ? $result['count'] : 0;
    
    // LINKED ACCOUNTS COUNT
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM sitio1_users u
        INNER JOIN sitio1_patients p ON u.id = p.user_id
        WHERE u.role = 'patient' 
        AND u.status = 'approved'
        AND p.deleted_at IS NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['linked_accounts_count'] = $result ? $result['count'] : 0;
    
    // Get recent patients (last 20)
    // First, let's check what columns exist in sitio1_staff
    $staffNameField = 'username'; // Default assumption
    if (in_array('name', $staffColumns)) {
        $staffNameField = 'name';
    } elseif (in_array('full_name', $staffColumns)) {
        $staffNameField = 'full_name';
    } elseif (in_array('first_name', $staffColumns)) {
        $staffNameField = 'first_name';
    }
    
    // Build the query based on available columns
    $recentPatientsQuery = "
        SELECT 
            p.*,
            u.email as user_email,
            s.$staffNameField as added_by_name,
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Linked'
                ELSE 'Unlinked'
            END as linking_status
        FROM sitio1_patients p
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        LEFT JOIN sitio1_staff s ON p.added_by = s.id
        WHERE p.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($recentPatientsQuery);
    $stmt->execute();
    $stats['recent_patients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If the query failed with the first assumption, try a simpler query
    if ($stmt->errorCode() != '00000') {
        $recentPatientsQuery = "
            SELECT 
                p.*,
                u.email as user_email,
                s.id as added_by_id,
                CASE 
                    WHEN p.user_id IS NOT NULL THEN 'Linked'
                    ELSE 'Unlinked'
                END as linking_status
            FROM sitio1_patients p
            LEFT JOIN sitio1_users u ON p.user_id = u.id
            LEFT JOIN sitio1_staff s ON p.added_by = s.id
            WHERE p.deleted_at IS NULL
            ORDER BY p.created_at DESC
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($recentPatientsQuery);
        $stmt->execute();
        $stats['recent_patients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
    $_SESSION['error_message'] = "Unable to fetch dashboard statistics. Please check database connection.";
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error_message = $e->getMessage();
    $_SESSION['error_message'] = "System configuration error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3498db',
                        secondary: '#2c3e50',
                        success: '#2ecc71',
                        danger: '#e74c3c',
                        warning: '#f39c12',
                        info: '#17a2b8',
                        warmRed: '#fef2f2',
                        warmBlue: '#f0f9ff'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        * {
            font-family: 'Poppins', sans-serif !important;
        }

        /* Ensure Font Awesome icons are visible */
        .fas, .far, .fab {
            font-family: 'Font Awesome 6 Free' !important;
            font-weight: 900 !important;
            display: inline-block !important;
        }

        .far {
            font-weight: 400 !important;
        }
        
        /* Main container styling */
        .main-container {
            background-color: white;
            border: 1px solid #f0f9ff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
        }
        
        /* Success/Error message styling */
        .alert-success {
            background-color: #f0fdf4;
            border: 2px solid #bbf7d0;
            color: #065f46;
            border-radius: 8px;
        }
        
        .alert-error {
            background-color: #fef2f2;
            border: 2px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
        }
        
        /* Button Styles */
        .btn-primary { 
            background-color: white; 
            color: #3498db; 
            border: 2px solid #bae6fd; 
            border-radius: 30px; 
            padding: 12px 24px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            min-height: 55px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            text-decoration: none;
        }
        .btn-primary:hover { 
            background-color: #f0f9ff; 
            border-color: #3498db;
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }
        
        .btn-action { 
            background-color: white; 
            color: #2c3e50; 
            border: 2px solid #e2e8f0; 
            border-radius: 30px; 
            padding: 10px 20px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-action:hover { 
            background-color: #f8fafc; 
            border-color: #3498db;
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }
        
        .btn-view { 
            background-color: #3498db; 
            color: white; 
            border-radius: 30px; 
            padding: 8px 16px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 13px;
            border: 2px solid #3498db;
        }
        .btn-view:hover { 
            background-color: #2980b9; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }
        
        .btn-edit { 
            background-color: #2ecc71; 
            color: white; 
            border-radius: 30px; 
            padding: 8px 16px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 13px;
            border: 2px solid #2ecc71;
        }
        .btn-edit:hover { 
            background-color: #27ae60; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
        }
        
        .btn-delete { 
            background-color: #e74c3c; 
            color: white; 
            border-radius: 30px; 
            padding: 8px 16px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 13px;
            border: 2px solid #e74c3c;
        }
        .btn-delete:hover { 
            background-color: #c0392b; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
        }
        
        /* Stats card styling */
        .stats-card {
            background-color: white;
            border: 1px solid #f0f9ff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-color: #3498db;
        }
        
        /* Icon containers */
        .icon-container {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }
        
        .icon-container i {
            font-size: 28px !important;
            display: block !important;
        }
        
        /* Badge styling */
        .stats-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Custom notification animation */
        .custom-notification {
            animation: slideIn 0.3s ease-out;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
        }
        
        @keyframes slideIn { 
            from { transform: translateX(100%); opacity: 0; } 
            to { transform: translateX(0); opacity: 1; } 
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Progress bar */
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            overflow: hidden;
            margin-top: 12px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        /* Quick action cards */
        .quick-action-card {
            padding: 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid #f0f9ff;
            background: white;
            text-decoration: none !important;
            color: inherit;
            display: block;
        }
        
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-color: #3498db;
        }
        
        .quick-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }
        
        .quick-action-icon i {
            font-size: 24px !important;
            display: block !important;
        }
        
        /* Table styling */
        .patient-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .patient-table th, .patient-table td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid #e2e8f0; 
        }
        
        .patient-table th { 
            background-color: #f0f9ff; 
            color: #2c3e50; 
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 14px;
        }
        
        .patient-table tr:hover { 
            background-color: #f8fafc; 
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-linked {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-unlinked {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        /* Modal styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1002;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        /* Info cards in view modal */
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #4b5563;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #111827;
            font-size: 1rem;
        }
        
        .info-empty {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Dashboard Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div class="flex items-center">
        <!-- Icon (no background) -->
        <i class="fas fa-chart-network text-3xl font-bold text-primary mr-4"></i>

        <div>
            <h1 class="text-3xl font-bold text-secondary">
                Admin Dashboard
            </h1>
            <p class="text-gray-600 mt-1">
                Overview of system statistics and activities
            </p>
        </div>
    </div>
</div>

        
        <!-- Notification Area -->
        <div id="notificationArea"></div>
        
        <!-- Parent Container -->
<div class="flex w-full justify-between mb-8">

    <!-- Active Staff Card -->
    <div class="stats-card flex flex-col justify-between flex-1 min-w-0 mx-3">
        <div class="w-full">

            <div class="flex w-full gap-4 mb-6">
                <i class="fas fa-user-tie text-3xl font-bold text-blue-600 flex-shrink-0"></i>

                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-700">
                        Active Staff
                    </h3>
                    <p class="text-sm text-gray-500">
                        Currently active healthcare staff
                    </p>
                </div>
            </div>

            <div class="flex w-full justify-between items-end mb-4">
                <p class="text-3xl font-bold text-blue-600">
                    <?= $stats['total_active_staff'] ?>
                </p>

                <span class="stats-badge bg-blue-100 text-blue-800 whitespace-nowrap">
                    BHW Staff
                </span>
            </div>
        </div>

        <a href="manage_accounts.php?section=staff"
           class="btn-action w-full justify-center mt-2">
            Manage Staff
        </a>
    </div>

    <!-- Resident Accounts Card -->
    <div class="stats-card flex flex-col justify-between flex-1 min-w-0 mx-3">
        <div class="w-full">

            <div class="flex w-full gap-4 mb-6">
                <i class="fas fa-user-circle text-3xl font-bold text-green-600 flex-shrink-0"></i>

                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-700">
                        Resident Accounts
                    </h3>
                    <p class="text-sm text-gray-500">
                        Verified resident accounts
                    </p>
                </div>
            </div>

            <div class="flex w-full justify-between items-end mb-4">
                <p class="text-3xl font-bold text-green-600">
                    <?= $stats['total_approved_residents'] ?>
                </p>

                <span class="stats-badge bg-green-100 text-green-800 whitespace-nowrap">
                     Data View
                </span>
            </div>
        </div>

        <a href="manage_accounts.php?section=resident"
           class="btn-action w-full justify-center mt-2">
            View Residents
        </a>
    </div>

    <!-- Patient Records Card -->
    <div class="stats-card flex flex-col justify-between flex-1 min-w-0 mx-3">
        <div class="w-full">

            <div class="flex w-full gap-4 mb-6">
                <i class="fas fa-notes-medical text-3xl font-bold text-purple-600 flex-shrink-0"></i>

                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-700">
                        Patient Records
                    </h3>
                    <p class="text-sm text-gray-500">
                        Total patient records
                    </p>
                </div>
            </div>

            <div class="flex w-full justify-between items-end mb-4">
                <p class="text-3xl font-bold text-purple-600">
                    <?= $stats['total_patients'] ?>
                </p>

                <span class="stats-badge bg-purple-100 text-purple-800 whitespace-nowrap">
                     Records
                </span>
            </div>
        </div>

        <a href="existing_info_patients.php"
           class="btn-action w-full justify-center mt-2">
            View All Patients
        </a>
    </div>

</div>

        
        <!-- Patient Records Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Patient Records Table -->
            <div class="main-container lg:col-span-2">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
    <!-- Icon (no background) -->
    <i class="fas fa-notes-medical text-2xl font-bold text-primary mr-4"></i>

    <div>
        <h2 class="text-xl font-semibold text-secondary">
            Recent Patient Records
        </h2>
        <p class="text-gray-600 text-sm">
            Latest 20 patient entries
        </p>
    </div>
</div>

                        <a href="existing_info_patients.php" class="btn-primary">
                            <i class="fas fa-eye mr-2"></i>View All Patients
                        </a>
                    </div>
                </div>
                
                <?php if (empty($stats['recent_patients'])): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <div class="w-20 h-20 bg-white border-2 border-warmBlue rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-notes-medical text-primary text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No Patient Records Found</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by adding patient records to the system.</p>
                        <a href="add_patient.php" class="btn-primary mt-4 inline-block">
                            <i class="fas fa-plus mr-2"></i>Add First Patient
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="patient-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Added By</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_patients'] as $patient): ?>
                                    <tr>
                                        <td>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($patient['full_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($patient['sitio'] ?? 'N/A') ?></div>
                                            <?php if (!empty($patient['user_email'])): ?>
                                                <div class="text-xs text-gray-400"><?= htmlspecialchars($patient['user_email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                        <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if ($patient['linking_status'] == 'Linked'): ?>
                                                <span class="status-badge status-linked">Linked</span>
                                            <?php else: ?>
                                                <span class="status-badge status-unlinked">Unlinked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($patient['added_by_name'])): ?>
                                                <?= htmlspecialchars($patient['added_by_name']) ?>
                                            <?php elseif (!empty($patient['added_by_id'])): ?>
                                                Staff #<?= $patient['added_by_id'] ?>
                                            <?php else: ?>
                                                System
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($patient['created_at'])) ?>
                                            <div class="text-sm text-gray-500">
                                                <?= date('g:i A', strtotime($patient['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex space-x-2">
                                                <button onclick="showViewModal(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>')" 
                                                   class="btn-view">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </button>
                                                <button onclick="showEditModal(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>')" 
                                                   class="btn-edit">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </button>
                                                <button onclick="showDeleteModal(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>')" 
                                                   class="btn-delete">
                                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Stats Panel -->
            <div class="main-container p-6">
                <div class="flex items-center mb-6">
    <!-- Icon (no background) -->
    <i class="fas fa-table-list text-2xl font-bold text-primary mr-4"></i>

    <div>
        <h2 class="text-xl font-semibold text-secondary">
            Quick Statistics
        </h2>
        <p class="text-gray-600 text-sm">
            System metrics at a glance
        </p>
    </div>
</div>

                
                <div class="space-y-6">
                    <!-- Linked Accounts -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-success flex items-center justify-center">
                                <i class="fas fa-link text-white text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-success"><?= $stats['linked_accounts_count'] ?></div>
                                <div class="text-success text-sm font-medium">Linked Accounts</div>
                            </div>
                        </div>
                        <h3 class="font-semibold text-gray-700 mb-1">Resident-Patient Links</h3>
                        <p class="text-gray-600 text-sm">Accounts with medical records</p>
                    </div>
                    
                    <!-- Unlinked Stats -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-chain-broken text-amber-600"></i>
                                </div>
                                <div>
                                    <span class="text-gray-700 font-medium">Unlinked Accounts</span>
                                </div>
                            </div>
                            <span class="text-xl font-bold text-amber-600"><?= $stats['total_unlinked_residents'] ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-file-circle-xmark text-orange-600"></i>
                                </div>
                                <div>
                                    <span class="text-gray-700 font-medium">Unlinked Patients</span>
                                </div>
                            </div>
                            <span class="text-xl font-bold text-orange-600"><?= $stats['total_unlinked_patients'] ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-user-ban text-red-600"></i>
                                </div>
                                <div>
                                    <span class="text-gray-700 font-medium">Declined Residents</span>
                                </div>
                            </div>
                            <span class="text-xl font-bold text-red-600"><?= $stats['total_declined_residents'] ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-user-xmark text-gray-600"></i>
                                </div>
                                <div>
                                    <span class="text-gray-700 font-medium">Inactive Staff</span>
                                </div>
                            </div>
                            <span class="text-xl font-bold text-gray-800"><?= $stats['total_inactive_staff'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
<div class="main-container p-6">

    <!-- Section Header -->
    <div class="flex items-center mb-6 p-4 bg-white shadow rounded-lg">
        <!-- Icon -->
        <i class="fas fa-rocket text-3xl font-bold text-primary mr-4"></i>

        <div>
            <h2 class="text-xl font-semibold text-secondary">
                Quick Actions
            </h2>
            <p class="text-gray-600 text-sm">
                Access commonly used administrative tasks quickly
            </p>
        </div>
    </div>

    <!-- Actions Cards (Flex, responsive, auto-fit) -->
    <div class="flex flex-wrap justify-between gap-4 mt-4">

        <!-- Add Patient Action -->
        <a href="add_patient.php" class="quick-action-card flex-1 min-w-[260px] max-w-[350px] p-4 border rounded-lg shadow hover:shadow-lg transition">
            <div class="flex items-center mb-4">
                <i class="fas fa-user-plus text-2xl font-bold text-blue-600 mr-4"></i>

                <div>
                    <h3 class="font-semibold text-secondary text-lg">Add Patient</h3>
                    <p class="text-gray-500 text-sm">Create a new patient record quickly</p>
                </div>
            </div>
            <div class="flex items-center text-blue-600 font-medium mt-2">
                <span>Go to Add Patient</span>
                <i class="fas fa-chevron-right ml-2"></i>
            </div>
        </a>

        <!-- Link Accounts Action -->
        <a href="manage_accounts.php?section=linking" class="quick-action-card flex-1 min-w-[260px] max-w-[350px] p-4 border rounded-lg shadow hover:shadow-lg transition">
            <div class="flex items-center mb-4">
                <i class="fas fa-people-arrows text-2xl font-bold text-green-600 mr-4"></i>

                <div>
                    <h3 class="font-semibold text-secondary text-lg">Link Accounts</h3>
                    <p class="text-gray-500 text-sm">Link residents to patients efficiently</p>
                </div>
            </div>
            <div class="flex items-center text-green-600 font-medium mt-2">
                <span>Go to Manual Linking</span>
                <i class="fas fa-chevron-right ml-2"></i>
            </div>
        </a>

        <!-- View Archive Action -->
        <a href="deleted_patients.php" class="quick-action-card flex-1 min-w-[260px] max-w-[350px] p-4 border rounded-lg shadow hover:shadow-lg transition">
            <div class="flex items-center mb-4">
                <i class="fas fa-archive text-2xl font-bold text-purple-600 mr-4"></i>

                <div>
                    <h3 class="font-semibold text-secondary text-lg">View Archive</h3>
                    <p class="text-gray-500 text-sm">Restore deleted patient records</p>
                </div>
            </div>
            <div class="flex items-center text-purple-600 font-medium mt-2">
                <span>Go to Patient Archive</span>
                <i class="fas fa-chevron-right ml-2"></i>
            </div>
        </a>

    </div>
</div>

    </div>

    <!-- View Patient Modal -->
    <div id="viewModal" class="modal-overlay" onclick="hideViewModal()">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center mr-4">
                            <i class="fas fa-user-injured text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-secondary">Patient Details</h3>
                            <p class="text-gray-600" id="viewPatientName"></p>
                        </div>
                    </div>
                    <button onclick="hideViewModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <!-- Personal Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="info-card">
                            <div class="info-label">Full Name</div>
                            <div class="info-value" id="viewFullName"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Age</div>
                            <div class="info-value" id="viewAge"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Gender</div>
                            <div class="info-value" id="viewGender"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value" id="viewDateOfBirth"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Civil Status</div>
                            <div class="info-value" id="viewCivilStatus"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Occupation</div>
                            <div class="info-value" id="viewOccupation"></div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h4 class="font-semibold text-gray-700 mb-3">Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="info-card">
                            <div class="info-label">Address</div>
                            <div class="info-value" id="viewAddress"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Sitio</div>
                            <div class="info-value" id="viewSitio"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value" id="viewContact"></div>
                        </div>
                    </div>
                    
                    <!-- Medical Information -->
                    <h4 class="font-semibold text-gray-700 mb-3">Medical Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="info-card">
                            <div class="info-label">Disease/Condition</div>
                            <div class="info-value" id="viewDisease"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Last Checkup</div>
                            <div class="info-value" id="viewLastCheckup"></div>
                        </div>
                        
                        <div class="info-card md:col-span-2">
                            <div class="info-label">Medical History</div>
                            <div class="info-value" id="viewMedicalHistory"></div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <h4 class="font-semibold text-gray-700 mb-3">Additional Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="info-card">
                            <div class="info-label">BHW Assigned</div>
                            <div class="info-value" id="viewBhwAssigned"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Family Number</div>
                            <div class="info-value" id="viewFamilyNo"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">4Ps Member</div>
                            <div class="info-value" id="viewFourpsMember"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Consent Given</div>
                            <div class="info-value" id="viewConsentGiven"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Consent Date</div>
                            <div class="info-value" id="viewConsentDate"></div>
                        </div>
                    </div>
                    
                    <!-- Metadata -->
                    <h4 class="font-semibold text-gray-700 mb-3">Record Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="info-card">
                            <div class="info-label">Record Created</div>
                            <div class="info-value" id="viewCreatedAt"></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value" id="viewUpdatedAt"></div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button onclick="hideViewModal()" class="btn-action">
                        Close
                    </button>
                    <button onclick="viewToEdit()" class="btn-edit">
                        <i class="fas fa-edit mr-2"></i>Edit Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editModal" class="modal-overlay" onclick="hideEditModal()">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-xl bg-success flex items-center justify-center mr-4">
                            <i class="fas fa-edit text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-secondary">Edit Patient Record</h3>
                            <p class="text-gray-600" id="editModalPatientName">Update patient information</p>
                        </div>
                    </div>
                    <button onclick="hideEditModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="editPatientForm" class="space-y-4">
                    <input type="hidden" id="editPatientId" name="patient_id">
                    <input type="hidden" name="action" value="update_patient">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Personal Information -->
                        <div class="col-span-2">
                            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">Personal Information</h4>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editFullName">Full Name *</label>
                            <input type="text" id="editFullName" name="full_name" class="form-control" required>
                            <div class="error-message" id="errorFullName"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editAge">Age *</label>
                            <input type="number" id="editAge" name="age" class="form-control" min="0" max="120" required>
                            <div class="error-message" id="errorAge"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editGender">Gender *</label>
                            <select id="editGender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="error-message" id="errorGender"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editDateOfBirth">Date of Birth</label>
                            <input type="date" id="editDateOfBirth" name="date_of_birth" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editCivilStatus">Civil Status</label>
                            <select id="editCivilStatus" name="civil_status" class="form-control">
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editOccupation">Occupation</label>
                            <input type="text" id="editOccupation" name="occupation" class="form-control">
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-span-2 mt-4">
                            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">Contact Information</h4>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editAddress">Address</label>
                            <textarea id="editAddress" name="address" class="form-control form-textarea"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editSitio">Sitio *</label>
                            <input type="text" id="editSitio" name="sitio" class="form-control" required>
                            <div class="error-message" id="errorSitio"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editContact">Contact Number *</label>
                            <input type="tel" id="editContact" name="contact" class="form-control" required>
                            <div class="error-message" id="errorContact"></div>
                        </div>
                        
                        <!-- Medical Information -->
                        <div class="col-span-2 mt-4">
                            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">Medical Information</h4>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editDisease">Disease/Condition</label>
                            <input type="text" id="editDisease" name="disease" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editLastCheckup">Last Checkup Date</label>
                            <input type="date" id="editLastCheckup" name="last_checkup" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editMedicalHistory">Medical History</label>
                            <textarea id="editMedicalHistory" name="medical_history" class="form-control form-textarea"></textarea>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="col-span-2 mt-4">
                            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">Additional Information</h4>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editBhwAssigned">BHW Assigned</label>
                            <input type="text" id="editBhwAssigned" name="bhw_assigned" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editFamilyNo">Family Number</label>
                            <input type="text" id="editFamilyNo" name="family_no" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editFourpsMember">4Ps Member</label>
                            <select id="editFourpsMember" name="fourps_member" class="form-control">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        
                        <div class="form-group col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="editConsentGiven" name="consent_given" class="w-4 h-4 text-primary rounded">
                                <label for="editConsentGiven" class="ml-2 text-gray-700">Consent Given</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="editConsentDate">Consent Date</label>
                            <input type="datetime-local" id="editConsentDate" name="consent_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                        <button type="button" onclick="hideEditModal()" class="btn-action">
                            Cancel
                        </button>
                        <button type="submit" class="btn-edit">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay" onclick="hideDeleteModal()">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="p-6">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-secondary">Delete Patient Record</h3>
                        <p class="text-gray-600">This action cannot be undone</p>
                    </div>
                </div>
                
                <div class="bg-warmRed border-2 border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-700" id="patientNameDisplay"></p>
                    <p class="text-sm text-red-600 mt-2">All associated data will be removed from the system.</p>
                </div>
                
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="font-semibold text-blue-800 mb-2">Consider Archiving Instead</h4>
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Archiving moves the record to the deleted patients archive where it can be restored later if needed.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button onclick="archivePatient()" class="btn-action w-full justify-center bg-yellow-100 border-yellow-300 text-yellow-800 hover:bg-yellow-200">
                        <i class="fas fa-archive mr-2"></i>Archive (Move to Trash)
                    </button>
                    <button onclick="permanentDelete()" class="btn-delete w-full justify-center">
                        <i class="fas fa-trash-alt mr-2"></i>Permanent Delete
                    </button>
                </div>
                
                <div class="mt-6 text-center">
                    <button onclick="hideDeleteModal()" class="text-gray-600 hover:text-gray-800 font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="loading-spinner">
        <div class="w-16 h-16 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <script>
        // Global variables for modals
        let currentPatientId = null;
        let currentPatientName = '';
        let currentPatientData = null;
        
        // Show notification function
        function showNotification(type, message, duration = 5000) {
            const notificationArea = document.getElementById('notificationArea');
            
            const notification = document.createElement('div');
            notification.className = `custom-notification alert-${type} px-4 py-3 rounded mb-3 flex items-center`;
            
            const icon = type === 'error' ? 'fa-exclamation-circle' :
                       type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            notificationArea.appendChild(notification);
            
            // Auto-remove after duration
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, duration);
        }
        
        // Show view patient modal
        function showViewModal(patientId, patientName) {
            currentPatientId = patientId;
            currentPatientName = patientName;
            
            // Show loading
            showLoading();
            
            // Fetch patient data
            fetch(`?get_patient=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    
                    if (data.success && data.patient) {
                        currentPatientData = data.patient;
                        const patient = data.patient;
                        
                        // Set modal title
                        document.getElementById('viewPatientName').textContent = patient.full_name;
                        
                        // Format and display patient data
                        document.getElementById('viewFullName').textContent = patient.full_name || 'Not specified';
                        document.getElementById('viewAge').textContent = patient.age ? `${patient.age} years` : 'Not specified';
                        document.getElementById('viewGender').textContent = patient.gender || 'Not specified';
                        document.getElementById('viewDateOfBirth').textContent = formatDate(patient.date_of_birth);
                        document.getElementById('viewCivilStatus').textContent = patient.civil_status || 'Not specified';
                        document.getElementById('viewOccupation').textContent = patient.occupation || 'Not specified';
                        document.getElementById('viewAddress').textContent = patient.address || 'Not specified';
                        document.getElementById('viewSitio').textContent = patient.sitio || 'Not specified';
                        document.getElementById('viewContact').textContent = patient.contact || 'Not specified';
                        document.getElementById('viewDisease').textContent = patient.disease || 'Not specified';
                        document.getElementById('viewLastCheckup').textContent = formatDate(patient.last_checkup);
                        document.getElementById('viewMedicalHistory').textContent = patient.medical_history || 'Not specified';
                        document.getElementById('viewBhwAssigned').textContent = patient.bhw_assigned || 'Not specified';
                        document.getElementById('viewFamilyNo').textContent = patient.family_no || 'Not specified';
                        document.getElementById('viewFourpsMember').textContent = patient.fourps_member || 'No';
                        document.getElementById('viewConsentGiven').textContent = patient.consent_given == 1 ? 'Yes' : 'No';
                        document.getElementById('viewConsentDate').textContent = formatDateTime(patient.consent_date);
                        document.getElementById('viewCreatedAt').textContent = formatDateTime(patient.created_at);
                        document.getElementById('viewUpdatedAt').textContent = formatDateTime(patient.updated_at);
                        
                        // Show modal
                        document.getElementById('viewModal').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        showNotification('error', 'Failed to load patient data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    hideLoading();
                    showNotification('error', 'Error loading patient data: ' + error.message);
                });
        }
        
        // Hide view modal
        function hideViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Switch from view to edit modal
        function viewToEdit() {
            hideViewModal();
            showEditModal(currentPatientId, currentPatientName);
        }
        
        // Show edit patient modal
        function showEditModal(patientId, patientName) {
            currentPatientId = patientId;
            currentPatientName = patientName;
            
            // Set modal title
            document.getElementById('editModalPatientName').textContent = `Editing: ${patientName}`;
            
            // Show loading
            showLoading();
            
            // Fetch patient data
            fetch(`?get_patient=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    
                    if (data.success && data.patient) {
                        const patient = data.patient;
                        
                        // Populate form fields
                        document.getElementById('editPatientId').value = patient.id;
                        document.getElementById('editFullName').value = patient.full_name || '';
                        document.getElementById('editAge').value = patient.age || '';
                        document.getElementById('editGender').value = patient.gender || '';
                        document.getElementById('editDateOfBirth').value = patient.date_of_birth || '';
                        document.getElementById('editCivilStatus').value = patient.civil_status || '';
                        document.getElementById('editOccupation').value = patient.occupation || '';
                        document.getElementById('editAddress').value = patient.address || '';
                        document.getElementById('editSitio').value = patient.sitio || '';
                        document.getElementById('editContact').value = patient.contact || '';
                        document.getElementById('editDisease').value = patient.disease || '';
                        document.getElementById('editLastCheckup').value = patient.last_checkup || '';
                        document.getElementById('editMedicalHistory').value = patient.medical_history || '';
                        document.getElementById('editBhwAssigned').value = patient.bhw_assigned || '';
                        document.getElementById('editFamilyNo').value = patient.family_no || '';
                        document.getElementById('editFourpsMember').value = patient.fourps_member || 'No';
                        document.getElementById('editConsentGiven').checked = patient.consent_given == 1;
                        document.getElementById('editConsentDate').value = patient.consent_date ? patient.consent_date.replace(' ', 'T') : '';
                        
                        // Show modal
                        document.getElementById('editModal').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        showNotification('error', 'Failed to load patient data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    hideLoading();
                    showNotification('error', 'Error loading patient data: ' + error.message);
                });
        }
        
        // Hide edit modal
        function hideEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            clearFormErrors();
        }
        
        // Clear form errors
        function clearFormErrors() {
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(el => el.textContent = '');
        }
        
        // Handle edit form submission
        document.getElementById('editPatientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous errors
            clearFormErrors();
            
            // Validate form
            let isValid = true;
            const requiredFields = ['full_name', 'age', 'gender', 'sitio', 'contact'];
            
            requiredFields.forEach(field => {
                const input = document.getElementById(`edit${field.charAt(0).toUpperCase() + field.slice(1)}`);
                const errorElement = document.getElementById(`error${field.charAt(0).toUpperCase() + field.slice(1)}`);
                
                if (!input.value.trim()) {
                    errorElement.textContent = 'This field is required';
                    isValid = false;
                }
            });
            
            if (!isValid) {
                showNotification('error', 'Please fill in all required fields');
                return;
            }
            
            // Show loading
            showLoading();
            
            // Prepare form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    // Show success notification
                    showNotification('success', data.message);
                    
                    // Close modal and reload page after delay
                    setTimeout(() => {
                        hideEditModal();
                        location.reload();
                    }, 1500);
                } else {
                    // Show errors
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const errorElement = document.getElementById(`error${field.charAt(0).toUpperCase() + field.slice(1)}`);
                            if (errorElement) {
                                errorElement.textContent = data.errors[field];
                            }
                        });
                    }
                    
                    // Show error notification
                    showNotification('error', data.message || 'Failed to update patient');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('error', 'Network error: ' + error.message);
            });
        });
        
        // Show delete confirmation modal
        function showDeleteModal(patientId, patientName) {
            currentPatientId = patientId;
            currentPatientName = patientName;
            
            document.getElementById('patientNameDisplay').textContent = 
                `Are you sure you want to delete "${patientName}"?`;
            
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Hide delete modal
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentPatientId = null;
            currentPatientName = '';
        }
        
        // Show loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        // Hide loading spinner
        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        // Archive patient (soft delete)
        function archivePatient() {
            if (!currentPatientId) return;
            
            if (confirm(`Archive "${currentPatientName}"?\n\nThe record will be moved to the archive and can be restored later.`)) {
                showLoading();
                window.location.href = `?delete_patient=${currentPatientId}&confirm=true`;
            }
        }
        
        // Permanent delete
        function permanentDelete() {
            if (!currentPatientId) return;
            
            if (confirm(`PERMANENTLY DELETE "${currentPatientName}"?\n\nTHIS ACTION CANNOT BE UNDONE!\n\nAll patient data will be permanently erased from the system.`)) {
                showLoading();
                window.location.href = `?permanent_delete=${currentPatientId}&confirm=true`;
            }
        }
        
        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return 'Not specified';
            
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid date';
            
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        // Format datetime for display
        function formatDateTime(datetimeString) {
            if (!datetimeString) return 'Not specified';
            
            const date = new Date(datetimeString);
            if (isNaN(date.getTime())) return 'Invalid date';
            
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show any PHP session messages
            <?php if (isset($_SESSION['success_message'])): ?>
                showNotification('success', '<?= addslashes($_SESSION['success_message']) ?>');
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                showNotification('error', '<?= addslashes($_SESSION['error_message']) ?>');
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            // Animate stats cards on page load
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideViewModal();
                hideEditModal();
                hideDeleteModal();
            }
        });
    </script>
    
</body>
</html>