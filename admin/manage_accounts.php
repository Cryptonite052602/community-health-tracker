<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Handle patient search AJAX request (for manage_accounts.php)
if (isset($_GET['search_patients']) && isset($_GET['term'])) {
    $term = trim($_GET['term']);
    
    if (strlen($term) >= 2) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    full_name,
                    DATE_FORMAT(date_of_birth, '%M %d, %Y') as date_of_birth,
                    age,
                    gender,
                    sitio,
                    contact,
                    civil_status,
                    DATE_FORMAT(last_checkup, '%M %d, %Y') as last_checkup
                FROM sitio1_patients 
                WHERE full_name LIKE ? 
                AND user_id IS NULL
                AND deleted_at IS NULL
                ORDER BY full_name ASC
                LIMIT 10
            ");
            
            $searchTerm = '%' . $term . '%';
            $stmt->execute([$searchTerm]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($patients);
            exit();
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Handle manual linking request
if (isset($_GET['link_resident'])) {
    $residentId = intval($_GET['resident_id']);
    $patientId = intval($_GET['patient_id']);
    
    try {
        $result = manuallyLinkToPatientRecord($pdo, $residentId, $patientId);
        $_SESSION['message'] = $result;
        $_SESSION['message_type'] = 'success';
        header('Location: manage_accounts.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error linking: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header('Location: manage_accounts.php');
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle staff password change with current password verification
    if (isset($_POST['change_staff_password'])) {
        $staffId = intval($_POST['staff_id']);
        $currentPassword = trim($_POST['current_password']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['message'] = 'All password fields are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['message'] = 'New passwords do not match.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        try {
            // Get staff current password
            $stmt = $pdo->prepare("SELECT id, password FROM sitio1_staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                $_SESSION['message'] = 'Staff not found.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $staff['password'])) {
                $_SESSION['message'] = 'Current password is incorrect.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE sitio1_staff SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $staffId]);
            
            $_SESSION['message'] = 'Staff password changed successfully!';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error changing password: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
    // Handle resident password change with current password verification
    elseif (isset($_POST['change_resident_password'])) {
        $residentId = intval($_POST['resident_id']);
        $currentPassword = trim($_POST['current_password']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['message'] = 'All password fields are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['message'] = 'New passwords do not match.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        try {
            // Get resident current password
            $stmt = $pdo->prepare("SELECT id, password FROM sitio1_users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$residentId]);
            $resident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resident) {
                $_SESSION['message'] = 'Resident not found.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $resident['password'])) {
                $_SESSION['message'] = 'Current password is incorrect.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE sitio1_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $residentId]);
            
            $_SESSION['message'] = 'Resident password changed successfully!';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error changing password: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
    // Handle staff account creation
    elseif (isset($_POST['create_staff'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $fullName = trim($_POST['full_name']);
        $position = trim($_POST['position'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        
        if (!empty($username) && !empty($password) && !empty($fullName)) {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM sitio1_staff WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['message'] = 'Username already exists.';
                    $_SESSION['message_type'] = 'error';
                    header('Location: manage_accounts.php');
                    exit();
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO sitio1_staff (username, password, full_name, position, specialization, license_number, created_by, status, is_active) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1)");
                $stmt->execute([$username, $hashedPassword, $fullName, $position, $specialization, $license_number, $_SESSION['user_id']]);
                
                $_SESSION['message'] = 'Staff account created successfully! Password: ' . htmlspecialchars($password);
                $_SESSION['message_type'] = 'success';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        } else {
            $_SESSION['message'] = 'Please fill in all required fields.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    } 
    // Handle resident account creation (SIMPLIFIED - NO AUTOMATIC PATIENT RECORD CREATION)
    elseif (isset($_POST['create_resident'])) {
        // 🔒 CORE FIELDS
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        // 📱 OPTIONAL FIELDS
        $phone = trim($_POST['phone'] ?? '');
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $sitio = trim($_POST['sitio'] ?? '');
        
        // Validate required fields
        if (empty($fullName) || empty($email) || empty($password)) {
            $_SESSION['message'] = 'Full name, email and password are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = 'Please enter a valid email address.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        // Generate username if not provided
        if (empty($username)) {
            $username = strtok($email, '@');
            $baseUsername = $username;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare("SELECT id FROM sitio1_users WHERE username = ?");
                $stmt->execute([$username]);
                if (!$stmt->fetch()) {
                    break;
                }
                $username = $baseUsername . $counter;
                $counter++;
            }
        }
        
        // Validate date if provided
        $age = 0;
        if (!empty($dateOfBirth)) {
            $dobTimestamp = strtotime($dateOfBirth);
            if (!$dobTimestamp) {
                $_SESSION['message'] = 'Please enter a valid date of birth.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            $age = date('Y') - date('Y', $dobTimestamp);
            if (date('md', $dobTimestamp) > date('md')) {
                $age--;
            }
            
            if ($age < 0 || $age > 120) {
                $_SESSION['message'] = 'Please enter a valid date of birth (age must be between 0-120 years)';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        }
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM sitio1_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['message'] = 'Email already exists.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM sitio1_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $_SESSION['message'] = 'Username already exists.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Generate unique number
            if (!empty($sitio)) {
                $uniqueNumber = 'RES' . strtoupper(substr($sitio, 0, 3)) . date('Ym') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            } else {
                $uniqueNumber = 'RES' . date('Ymd') . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Generate Patient Record UID (will be used when linking later)
            $patientRecordUID = 'PAT-' . date('Ymd') . '-' . strtoupper(substr($fullName, 0, 3)) . '-' . mt_rand(1000, 9999);
            
            // Insert user WITHOUT linking to any patient record
            // Account will remain unlinked until admin manually links it
            $stmt = $pdo->prepare("INSERT INTO sitio1_users 
                (username, email, password, full_name, date_of_birth, age, gender, sitio, contact, 
                 approved, status, role, unique_number, verification_method, id_verified, 
                 verified_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', 'patient', ?, 
                        'manual_verification', 1, NOW(), NOW())");
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([
                $username, 
                $email, 
                $hashedPassword, 
                $fullName, 
                !empty($dateOfBirth) ? $dateOfBirth : null,
                $age,
                !empty($gender) ? $gender : null,
                !empty($sitio) ? $sitio : null,
                !empty($phone) ? $phone : null,
                $uniqueNumber
            ]);
            
            $residentUserId = $pdo->lastInsertId();
            
            // ✅ **NO PATIENT RECORD CREATION - ACCOUNT STANDS ALONE**
            // Patient record will be linked later via manual linking
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['message'] = 'Resident account created successfully! Password: ' . htmlspecialchars($password) . ' Account is ready for patient record linking.';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
            
        } catch (PDOException $e) {
            // Rollback on error
            $pdo->rollBack();
            $_SESSION['message'] = 'Error creating resident account: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
    // Handle resident password reset (admin reset without current password)
    elseif (isset($_POST['reset_resident_password'])) {
        $residentId = intval($_POST['resident_id']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        if (empty($newPassword)) {
            $_SESSION['message'] = 'New password is required.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['message'] = 'Passwords do not match.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
        
        try {
            // Verify resident exists
            $stmt = $pdo->prepare("SELECT id FROM sitio1_users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$residentId]);
            if (!$stmt->fetch()) {
                $_SESSION['message'] = 'Resident not found.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE sitio1_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $residentId]);
            
            $_SESSION['message'] = 'Resident password reset successfully! New password: ' . htmlspecialchars($newPassword);
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error resetting password: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
    // Handle resident status toggle
    elseif (isset($_POST['toggle_resident_status'])) {
        $residentId = intval($_POST['resident_id']);
        $action = $_POST['action'];
        
        if (in_array($action, ['approve', 'decline', 'suspend'])) {
            try {
                $newStatus = ($action === 'approve') ? 'approved' : ($action === 'decline' ? 'declined' : 'suspended');
                
                $stmt = $pdo->prepare("UPDATE sitio1_users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $residentId]);
                
                $_SESSION['message'] = 'Resident account ' . $action . 'd successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error updating resident: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        }
    }
    // Handle staff status toggle
    elseif (isset($_POST['toggle_staff_status'])) {
        $staffId = intval($_POST['staff_id']);
        $action = $_POST['action'];
        
        if (in_array($action, ['activate', 'deactivate'])) {
            try {
                $newStatus = ($action === 'activate') ? 'active' : 'inactive';
                $isActive = ($action === 'activate') ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE sitio1_staff SET status = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $isActive, $staffId]);
                
                $_SESSION['message'] = 'Staff account ' . $action . 'd successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error updating account: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        }
    } 
    // Handle staff deletion
    elseif (isset($_POST['hard_delete'])) {
        $staffId = intval($_POST['staff_id']);
        
        try {
            // Check for dependencies
            $dependencies = [];
            
            // Check appointments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_appointments WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $appointmentsCount = $stmt->fetchColumn();
            if ($appointmentsCount > 0) {
                $dependencies[] = "$appointmentsCount appointment(s)";
            }
            
            // Check announcements
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_announcements WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $announcementsCount = $stmt->fetchColumn();
            if ($announcementsCount > 0) {
                $dependencies[] = "$announcementsCount announcement(s)";
            }
            
            // Check consultations
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_consultations WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $consultationsCount = $stmt->fetchColumn();
            if ($consultationsCount > 0) {
                $dependencies[] = "$consultationsCount consultation(s)";
            }
            
            // Check patient records
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_patients WHERE added_by = ?");
            $stmt->execute([$staffId]);
            $patientsCount = $stmt->fetchColumn();
            if ($patientsCount > 0) {
                $dependencies[] = "$patientsCount patient record(s)";
            }
            
            // Check prescriptions
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_prescriptions WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $prescriptionsCount = $stmt->fetchColumn();
            if ($prescriptionsCount > 0) {
                $dependencies[] = "$prescriptionsCount prescription(s)";
            }
            
            // If dependencies exist, handle them
            if (!empty($dependencies)) {
                $deleteAction = $_POST['delete_action'] ?? 'reassign';
                $reassignTo = intval($_POST['reassign_to'] ?? 0);
                
                $pdo->beginTransaction();
                
                try {
                    if ($deleteAction === 'reassign' && $reassignTo > 0) {
                        // Reassign appointments
                        $stmt = $pdo->prepare("UPDATE sitio1_appointments SET staff_id = ? WHERE staff_id = ?");
                        $stmt->execute([$reassignTo, $staffId]);
                        
                        // Reassign consultations
                        $stmt = $pdo->prepare("UPDATE sitio1_consultations SET staff_id = ? WHERE staff_id = ?");
                        $stmt->execute([$reassignTo, $staffId]);
                        
                        // Reassign patient records
                        $stmt = $pdo->prepare("UPDATE sitio1_patients SET added_by = ? WHERE added_by = ?");
                        $stmt->execute([$reassignTo, $staffId]);
                        
                        // Reassign prescriptions
                        $stmt = $pdo->prepare("UPDATE sitio1_prescriptions SET staff_id = ? WHERE staff_id = ?");
                        $stmt->execute([$reassignTo, $staffId]);
                        
                        // Set announcements to NULL
                        $stmt = $pdo->prepare("UPDATE sitio1_announcements SET staff_id = NULL WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        
                        $_SESSION['message'] = 'Staff account deleted and records reassigned successfully!';
                    } else {
                        // Delete dependent records
                        $stmt = $pdo->prepare("DELETE FROM sitio1_appointments WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        
                        $stmt = $pdo->prepare("DELETE FROM sitio1_consultations WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        
                        $stmt = $pdo->prepare("UPDATE sitio1_patients SET added_by = NULL WHERE added_by = ?");
                        $stmt->execute([$staffId]);
                        
                        $stmt = $pdo->prepare("DELETE FROM sitio1_prescriptions WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        
                        $stmt = $pdo->prepare("UPDATE sitio1_announcements SET staff_id = NULL WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        
                        $_SESSION['message'] = 'Staff account and associated records deleted successfully!';
                    }
                    
                    // Delete staff account
                    $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
                    $stmt->execute([$staffId]);
                    
                    $pdo->commit();
                    $_SESSION['message_type'] = 'success';
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } else {
                // No dependencies, delete directly
                $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
                $stmt->execute([$staffId]);
                
                $_SESSION['message'] = 'Staff account deleted successfully!';
                $_SESSION['message_type'] = 'success';
            }
            
            header('Location: manage_accounts.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error deleting account: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
}

/**
 * Function to manually link resident account to patient records
 */
function manuallyLinkToPatientRecord($pdo, $residentUserId, $patientId, $patientRecordUID = null) {
    $resultMessage = '';
    
    try {
        // Verify patient exists and is not already linked
        $stmt = $pdo->prepare("SELECT id, full_name, user_id FROM sitio1_patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return '⚠️ Selected patient record not found.';
        }
        
        if ($patient['user_id'] !== null) {
            if ($patient['user_id'] == $residentUserId) {
                return 'ℹ️ Patient record already linked to this account.';
            }
            return '⚠️ Patient record already linked to another account.';
        }
        
        // Generate UID if provided
        if (!$patientRecordUID) {
            $patientRecordUID = 'PAT-' . date('Ymd') . '-' . strtoupper(substr($patient['full_name'], 0, 3)) . '-' . mt_rand(1000, 9999);
        }
        
        // Check if patient_record_uid column exists in patients table
        $stmt = $pdo->query("SHOW COLUMNS FROM sitio1_patients LIKE 'patient_record_uid'");
        $patientUidColumnExists = $stmt->fetch();
        
        if ($patientUidColumnExists) {
            // Link with UID
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET user_id = ?, patient_record_uid = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$residentUserId, $patientRecordUID, $patientId]);
        } else {
            // Link without UID
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET user_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$residentUserId, $patientId]);
        }
        
        // Check if patient_record_uid column exists in users table
        $stmt = $pdo->query("SHOW COLUMNS FROM sitio1_users LIKE 'patient_record_uid'");
        $userUidColumnExists = $stmt->fetch();
        
        if ($userUidColumnExists) {
            // Update user record with patient_record_uid
            $stmt = $pdo->prepare("UPDATE sitio1_users SET patient_record_uid = ? WHERE id = ?");
            $stmt->execute([$patientRecordUID, $residentUserId]);
        }
        
        $resultMessage = '✅ Manually linked to patient: ' . htmlspecialchars($patient['full_name']);
        if ($patientRecordUID) {
            $resultMessage .= ' (UID: ' . $patientRecordUID . ')';
        }
        
        return $resultMessage;
        
    } catch (PDOException $e) {
        return '⚠️ Manual linking failed: ' . $e->getMessage();
    }
}

/**
 * Function to get unlinked residents (accounts without patient records)
 */
function getUnlinkedResidents($pdo) {
    try {
        // Alternative query that doesn't use patient_record_uid
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM sitio1_users u
            LEFT JOIN sitio1_patients p ON u.id = p.user_id
            WHERE u.role = 'patient' 
            AND u.status = 'approved'
            AND p.id IS NULL
            ORDER BY u.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting unlinked residents: " . $e->getMessage());
        return [];
    }
}

/**
 * Function to get unlinked patient records (without user accounts)
 */
function getUnlinkedPatients($pdo) {
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM sitio1_patients p
        WHERE p.user_id IS NULL
        AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all staff accounts
$activeStaff = [];
$inactiveStaff = [];

// Get all resident accounts
$pendingResidents = [];
$approvedResidents = [];
$declinedResidents = [];
$unlinkedResidents = [];

// Get unlinked patient records
$unlinkedPatients = [];

// Get staff for reassignment
$allStaff = [];

try {
    // Active staff
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 1
                         ORDER BY s.created_at DESC");
    $activeStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inactive staff
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 0
                         ORDER BY s.created_at DESC");
    $inactiveStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // All active staff for reassignment
    $stmt = $pdo->query("SELECT id, full_name, username FROM sitio1_staff WHERE is_active = 1 ORDER BY full_name");
    $allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending residents
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE role = 'patient' AND status = 'pending' ORDER BY created_at DESC");
    $pendingResidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Approved residents
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE role = 'patient' AND status = 'approved' ORDER BY created_at DESC");
    $approvedResidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Declined residents
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE role = 'patient' AND status = 'declined' ORDER BY created_at DESC");
    $declinedResidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Unlinked residents
    $unlinkedResidents = getUnlinkedResidents($pdo);
    
    // Unlinked patient records
    $unlinkedPatients = getUnlinkedPatients($pdo);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Error loading data: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --warm-blue: #3b82f6;
            --warm-blue-light: #60a5fa;
            --warm-blue-dark: #1d4ed8;
            --warm-blue-bg: #f0f9ff;
            --warm-blue-border: #bae6fd;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        
        /* Header and Navigation */
        .main-header {
            background: linear-gradient(135deg, var(--warm-blue) 0%, var(--warm-blue-dark) 100%);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Card Design */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--warm-blue-border);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--warm-blue-bg) 0%, #e0f2fe 100%);
            border-bottom: 2px solid var(--warm-blue-border);
            padding: 1.5rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--warm-blue) 0%, var(--warm-blue-dark) 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--warm-blue-dark) 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            border: 1px solid var(--warm-blue-border);
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            background: transparent;
        }
        
        .tab-btn.active {
            background: var(--warm-blue-bg);
            color: var(--warm-blue);
            border-color: var(--warm-blue);
            font-weight: 600;
        }
        
        .tab-btn:hover:not(.active) {
            background: #f8fafc;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.375rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--warm-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            padding: 5px;
        }
        
        .password-toggle:hover {
            color: #374151;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-container .form-input {
            padding-right: 40px;
        }
        
        /* Account Cards */
        .account-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .account-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-color: var(--warm-blue-border);
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            border: 1px solid var(--warm-blue-border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card h3 {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .number {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--warm-blue);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--warm-blue-border);
        }
        
        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        /* Manual Linking Styles */
        .link-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f7fa 100%);
            border: 2px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .link-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .link-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.5rem;
        }
        
        .link-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .link-item:hover {
            background: #f0f9ff;
        }
        
        .link-item.selected {
            background: #dbeafe;
            border-left: 3px solid #3b82f6;
        }
        
        /* Message Modal - TOP RIGHT */
        .message-modal {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            height: 85px;
            transform: translateX(120%);
            transition: transform 0.3s ease-in-out;
            pointer-events: none;
        }
        
        .message-modal.show {
            transform: translateX(0);
            pointer-events: auto;
        }
        
        .message-content {
            background: white;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 2px solid transparent;
            animation: slideInRight 0.3s ease-out;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .message-content.success {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        }
        
        .message-content.error {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fef2f2 100%);
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .message-icon {
            font-size: 1.25rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        
        .message-icon.success {
            color: #10b981;
        }
        
        .message-icon.error {
            color: #ef4444;
        }
        
        .message-title {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        
        .message-title.success {
            color: #065f46;
        }
        
        .message-title.error {
            color: #991b1b;
        }
        
        .message-body {
            color: #4b5563;
            font-size: 0.875rem;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            max-height: 2.8em;
        }
        
        .message-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            z-index: 1;
        }
        
        .message-close:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #6b7280;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(120%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }
        
        /* Progress Bar */
        .message-progress {
            height: 3px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        .message-progress-bar {
            height: 100%;
            width: 100%;
            border-radius: 3px;
            transition: width 1s linear;
        }
        
        .message-progress-bar.success {
            background: #10b981;
        }
        
        .message-progress-bar.error {
            background: #ef4444;
        }
        
        /* Dynamic width based on message length */
        .message-modal.short { width: 300px; }
        .message-modal.medium { width: 350px; }
        .message-modal.long { width: 400px; }
        .message-modal.extra-long { width: 450px; }
        .message-modal.max { width: 500px; }
        
        /* Card Selection Styles */
        .resident-card.selected {
            border-color: #3b82f6 !important;
            background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        
        .patient-card.selected {
            border-color: #10b981 !important;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
            transform: translateY(-2px);
        }
        
        /* Button Styles */
        .btn-outline {
            background: transparent;
            border: 2px solid #d1d5db;
            color: #4b5563;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 100%);
        }
        
        .btn-primary:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .btn-primary:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }
        
        /* Animation for selection */
        .resident-card, .patient-card {
            transition: all 0.2s ease-in-out;
        }
        
        .resident-card:hover:not(.selected) {
            border-color: #93c5fd;
            transform: translateY(-1px);
        }
        
        .patient-card:hover:not(.selected) {
            border-color: #6ee7b7;
            transform: translateY(-1px);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            
            .tabs-container {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
                text-align: center;
            }
            
            .link-grid {
                grid-template-columns: 1fr;
            }
            
            .message-modal {
                width: calc(100% - 40px) !important;
                right: 20px;
                left: 20px;
                max-width: none;
            }
            
            .message-content {
                padding: 0.875rem 1rem;
            }
            
            .message-body {
                -webkit-line-clamp: 2;
                max-height: 2.8em;
            }
            
            .account-card {
                padding: 0.75rem;
            }
            
            #selected-items-panel {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Message Modal -->
    <?php if (isset($_SESSION['message'])): ?>
    <?php 
        $message = $_SESSION['message'];
        $messageType = $_SESSION['message_type'] ?? '';
        
        // Calculate message length for dynamic width
        $msgLength = strlen($message);
        if ($msgLength < 50) {
            $widthClass = 'short';
        } elseif ($msgLength < 80) {
            $widthClass = 'medium';
        } elseif ($msgLength < 120) {
            $widthClass = 'long';
        } elseif ($msgLength < 160) {
            $widthClass = 'extra-long';
        } else {
            $widthClass = 'max';
        }
    ?>
    <div id="messageModal" class="message-modal <?= $widthClass ?>">
        <div class="message-content <?= $messageType === 'error' ? 'error' : 'success' ?>">
            <button class="message-close" onclick="closeMessageModal()" aria-label="Close message">
                <i class="fas fa-times"></i>
            </button>
            <div class="message-header">
                <i class="message-icon <?= $messageType === 'error' ? 'error' : 'success' ?> fas <?= $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <span class="message-title <?= $messageType === 'error' ? 'error' : 'success' ?>">
                    <?= $messageType === 'error' ? 'Error' : 'Success' ?>
                </span>
            </div>
            <div class="message-body">
                <?= htmlspecialchars($message) ?>
            </div>
            <div class="message-progress">
                <div class="message-progress-bar <?= $messageType === 'error' ? 'error' : 'success' ?>" id="messageProgressBar"></div>
            </div>
        </div>
    </div>
    <?php 
        // Store message in JavaScript variable before clearing
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    ?>
    <?php endif; ?>
    
    <main class="container mx-auto px-4 py-8 mt-16">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Account Management</h1>
                    <p class="text-gray-600 mt-2">Manage staff and resident accounts with patient record linking</p>
                </div>
                <div class="flex gap-3">
                    <span class="badge badge-info">
                        <i class="fas fa-user-md"></i> Staff: <?= count($activeStaff) + count($inactiveStaff) ?>
                    </span>
                    <span class="badge badge-success">
                        <i class="fas fa-users"></i> Residents: <?= count($pendingResidents) + count($approvedResidents) + count($declinedResidents) ?>
                    </span>
                    <?php if (count($unlinkedResidents) > 0): ?>
                    <span class="badge badge-warning">
                        <i class="fas fa-unlink"></i> Unlinked: <?= count($unlinkedResidents) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="stat-card">
                    <h3>Active Staff</h3>
                    <div class="number"><?= count($activeStaff) ?></div>
                    <p class="text-sm text-green-600 mt-2">
                        <i class="fas fa-check-circle"></i> Operational
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Inactive Staff</h3>
                    <div class="number"><?= count($inactiveStaff) ?></div>
                    <p class="text-sm text-gray-500 mt-2">
                        <i class="fas fa-pause-circle"></i> Suspended
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Approved Residents</h3>
                    <div class="number"><?= count($approvedResidents) ?></div>
                    <p class="text-sm text-green-600 mt-2">
                        <i class="fas fa-check-circle"></i> Active
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Pending Residents</h3>
                    <div class="number"><?= count($pendingResidents) ?></div>
                    <p class="text-sm text-yellow-600 mt-2">
                        <i class="fas fa-clock"></i> Awaiting approval
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Unlinked Accounts</h3>
                    <div class="number"><?= count($unlinkedResidents) ?></div>
                    <p class="text-sm text-orange-600 mt-2">
                        <i class="fas fa-unlink"></i> Need patient record
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Tabs -->
        <div class="tabs-container mb-8">
            <div class="flex flex-wrap gap-2">
                <button class="tab-btn active" onclick="showSection('staff')" id="staff-tab">
                    <i class="fas fa-user-md mr-2"></i> Staff Management
                    <span class="badge badge-info"><?= count($activeStaff) + count($inactiveStaff) ?></span>
                </button>
                <button class="tab-btn" onclick="showSection('resident')" id="resident-tab">
                    <i class="fas fa-users mr-2"></i> Resident Management
                    <span class="badge badge-success"><?= count($pendingResidents) + count($approvedResidents) + count($declinedResidents) ?></span>
                </button>
                <?php if (count($unlinkedResidents) > 0 || count($unlinkedPatients) > 0): ?>
                <button class="tab-btn" onclick="showSection('linking')" id="linking-tab">
                    <i class="fas fa-link mr-2"></i> Manual Linking
                    <span class="badge badge-warning"><?= count($unlinkedResidents) + count($unlinkedPatients) ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Staff Management Section -->
        <section id="staff-section" class="fade-in space-y-10">
    
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-5">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-user-plus text-blue-600"></i>
                Create New Staff Account
            </h2>
            <p class="text-gray-500 text-sm mt-1">Add healthcare staff members to the system</p>
        </div>

        <div class="p-8">
            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Username <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" required 
                           class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" 
                           placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password <span class="text-rose-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="password" required 
                               id="staff-password"
                               class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all pr-10" 
                               placeholder="Enter password">
                        <button type="button" class="password-toggle" onclick="togglePassword('staff-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Password is visible to admin for reference</p>
                </div>
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" required 
                           class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" 
                           placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Position</label>
                    <input type="text" name="position" 
                           class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" 
                           placeholder="e.g., Nurse, Doctor">
                </div>
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Specialization</label>
                    <input type="text" name="specialization" 
                           class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" 
                           placeholder="e.g., Pediatrics">
                </div>
                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">License Number</label>
                    <input type="text" name="license_number" 
                           class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" 
                           placeholder="Enter license number">
                </div>

                <div class="md:col-span-2 flex justify-center pt-4">
                    <button type="submit" name="create_staff" class="px-10 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-full shadow-lg shadow-blue-100 transition-all transform hover:scale-105 flex items-center gap-2">
                        <i class="fas fa-user-plus"></i> Create Staff Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="flex justify-center">
        <div class="inline-flex p-1.5 bg-gray-100 rounded-full gap-1">
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all bg-white shadow-sm text-blue-600" onclick="showStaffTab('active')">
                Active Staff (<?= count($activeStaff) ?>)
            </button>
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all text-gray-500 hover:text-gray-900" onclick="showStaffTab('inactive')">
                Inactive Staff (<?= count($inactiveStaff) ?>)
            </button>
        </div>
    </div>

    <div id="active-staff-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (empty($activeStaff)): ?>
            <div class="col-span-full text-center py-16 bg-gray-50 rounded-[2rem] border-2 border-dashed border-gray-200">
                <i class="fas fa-user-md text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">No active staff accounts</h3>
                <p class="text-gray-500">Create your first staff account above</p>
            </div>
        <?php else: ?>
            <?php foreach ($activeStaff as $staff): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 hover:shadow-xl transition-all group">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($staff['full_name']) ?></h3>
                            <p class="text-blue-500 font-bold text-sm">@<?= htmlspecialchars($staff['username']) ?></p>
                        </div>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded-full tracking-widest">Active</span>
                    </div>
                    
                    <div class="space-y-3 mb-8 border-t border-b border-gray-50 py-6">
                        <?php if ($staff['position']): ?>
                            <div class="flex items-center text-sm font-medium text-gray-600">
                                <i class="fas fa-briefcase text-blue-400 mr-3 w-5"></i>
                                <?= htmlspecialchars($staff['position']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($staff['specialization']): ?>
                            <div class="flex items-center text-sm font-medium text-gray-600">
                                <i class="fas fa-stethoscope text-emerald-400 mr-3 w-5"></i>
                                <?= htmlspecialchars($staff['specialization']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center text-sm font-medium text-gray-400">
                            <i class="fas fa-calendar-alt mr-3 w-5"></i>
                            Added <?= date('M j, Y', strtotime($staff['created_at'])) ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button onclick="showChangeStaffPasswordModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['full_name']) ?>')"
                                class="px-6 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-full text-xs hover:bg-blue-600 hover:text-white transition-all">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                        <form method="POST" action="">
                            <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                            <input type="hidden" name="action" value="deactivate">
                            <button type="submit" name="toggle_staff_status" 
                                    class="px-6 py-2.5 bg-amber-50 text-amber-700 font-bold rounded-full text-xs hover:bg-amber-500 hover:text-white transition-all"
                                    onclick="return confirm('Deactivate this staff account?')">
                                <i class="fas fa-pause mr-2"></i> Deactivate
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="inactive-staff-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" style="display: none;">
        <?php if (empty($inactiveStaff)): ?>
            <div class="col-span-full text-center py-16 bg-gray-50 rounded-[2rem] border-2 border-dashed border-gray-200">
                <i class="fas fa-user-slash text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">No inactive staff accounts</h3>
                <p class="text-gray-500">All staff accounts are currently active</p>
            </div>
        <?php else: ?>
            <?php foreach ($inactiveStaff as $staff): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 group">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($staff['full_name']) ?></h3>
                            <p class="text-gray-500 font-bold text-sm">@<?= htmlspecialchars($staff['username']) ?></p>
                        </div>
                        <span class="px-3 py-1 bg-rose-50 text-rose-600 text-[10px] font-black uppercase rounded-full tracking-widest">Inactive</span>
                    </div>
                    
                    <div class="space-y-3 mb-8 border-t border-b border-gray-50 py-6">
                        <?php if ($staff['position']): ?>
                            <div class="flex items-center text-sm font-medium text-gray-500">
                                <i class="fas fa-briefcase text-gray-400 mr-3 w-5"></i>
                                <?= htmlspecialchars($staff['position']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center text-sm font-medium text-gray-400">
                            <i class="fas fa-calendar-alt mr-3 w-5"></i>
                            Added <?= date('M j, Y', strtotime($staff['created_at'])) ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button onclick="showChangeStaffPasswordModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['full_name']) ?>')"
                                class="px-6 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-full text-xs hover:bg-blue-600 hover:text-white transition-all">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                        <form method="POST" action="">
                            <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" name="toggle_staff_status" 
                                    class="px-6 py-2.5 bg-emerald-100 text-emerald-700 font-bold rounded-full text-xs hover:bg-emerald-600 hover:text-white transition-all"
                                    onclick="return confirm('Reactivate this account?')">
                                <i class="fas fa-play mr-2"></i> Activate
                            </button>
                        </form>
                        
                        <button onclick="showDeleteModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['full_name']) ?>')"
                                class="px-6 py-2.5 bg-rose-50 text-rose-600 font-bold rounded-full text-xs hover:bg-rose-600 hover:text-white transition-all">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

        <!-- Resident Management Section -->
        <section id="resident-section" class="fade-in space-y-10" style="display: none;">
    
    <div class="border-b border-gray-100 pb-6">
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Resident Management</h1>
        <p class="text-gray-500 mt-1">Create accounts and manage clinical linking.</p>
    </div>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-5">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-user-plus text-emerald-600"></i>
                Create New Resident Account
            </h2>
        </div>
        
        <div class="p-8">
            <form method="POST" action="" id="resident-form" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6">
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Full Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="full_name" required class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white outline-none transition-all" placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Email Address <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white outline-none transition-all" placeholder="juan@example.com">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Username</label>
                        <input type="text" name="username" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white outline-none transition-all" placeholder="Leave blank for auto-gen">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password <span class="text-rose-500">*</span></label>
                        <div class="password-container relative">
                            <input type="password" name="password" required 
                                   id="resident-password"
                                   class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white outline-none transition-all pr-10" 
                                   placeholder="••••••••">
                            <button type="button" class="password-toggle" onclick="togglePassword('resident-password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Password is visible to admin for reference</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Phone</label>
                        <input type="tel" name="phone" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date-of-birth" onchange="calculateAge()" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Gender</label>
                        <select name="gender" id="gender" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Sitio</label>
                    <select name="sitio" id="sitio" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                        <option value="">Select Sitio</option>
                        <option value="Proper Luz">Proper Luz</option>
                        <option value="Lower Luz">Lower Luz</option>
                        <option value="Upper Luz">Upper Luz</option>
                        <option value="Luz Proper">Luz Proper</option>
                        <option value="Luz Heights">Luz Heights</option>
                        <option value="Panganiban">Panganiban</option>
                        <option value="Balagtas">Balagtas</option>
                        <option value="Carbon">Carbon</option>
                        <option value="Others">Others</option>
                    </select>
                </div>

                <!-- Age display (hidden input for form) -->
                <input type="hidden" name="age_display" id="age">

                <!-- Information Note -->
                <div class="mb-8 bg-blue-50 border border-blue-200 rounded-2xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-blue-800">Important:</p>
                            <p class="text-sm text-blue-600 mt-1">
                                This account will be created without a patient record. 
                                To link this account to a patient record, go to the 
                                <span class="font-medium">"Manual Linking"</span> tab after creating the account.
                                Patient records are added separately by admin staff.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-center pt-4">
                    <button type="submit" name="create_resident" class="px-10 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-full shadow-lg shadow-emerald-200 transition-all transform hover:scale-105 flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i> Create Resident Account
                    </button>
                    <p class="text-xs text-gray-400 mt-4 uppercase tracking-widest font-bold">Role: Resident • Status: Approved</p>
                </div>
            </form>
        </div>
    </div>

    <div class="flex justify-center">
        <div class="inline-flex p-1.5 bg-gray-100 rounded-full gap-1">
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all bg-white shadow-sm text-emerald-600" onclick="showResidentTab('pending')">
                Pending (<?= count($pendingResidents) ?>)
            </button>
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all text-gray-500 hover:text-gray-900" onclick="showResidentTab('approved')">
                Approved (<?= count($approvedResidents) ?>)
            </button>
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all text-gray-500 hover:text-gray-900" onclick="showResidentTab('declined')">
                Declined (<?= count($declinedResidents) ?>)
            </button>
            <?php if (count($unlinkedResidents) > 0): ?>
            <button class="px-8 py-3 rounded-full text-sm font-bold transition-all text-gray-500 hover:text-gray-900" onclick="showResidentTab('unlinked')">
                Unlinked (<?= count($unlinkedResidents) ?>)
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Residents Tab -->
    <div id="pending-residents-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (empty($pendingResidents)): ?>
            <div class="col-span-full text-center py-16 bg-yellow-50 rounded-[2rem] border-2 border-dashed border-yellow-200">
                <i class="fas fa-clock text-5xl text-yellow-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">No pending resident accounts</h3>
                <p class="text-gray-500">All applications have been processed</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingResidents as $resident): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 hover:shadow-xl hover:shadow-gray-200/50 transition-all group">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                        <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($resident['full_name']) ?></h3>
                        <p class="text-emerald-500 font-bold text-sm uppercase tracking-tighter">@<?= htmlspecialchars($resident['username']) ?></p>
                    </div>
                    
                    <div class="space-y-3 mb-8 text-center border-t border-b border-gray-50 py-6">
                        <p class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-300"></i><?= htmlspecialchars($resident['email']) ?></p>
                        <p class="text-sm text-gray-500"><i class="fas fa-map-marker-alt mr-2 text-gray-300"></i><?= htmlspecialchars($resident['sitio'] ?: 'No Location') ?></p>
                        <?php if ($resident['age'] > 0): ?>
                            <p class="text-sm text-gray-500"><i class="fas fa-user mr-2 text-gray-300"></i>Age: <?= htmlspecialchars($resident['age']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <form method="POST" action="">
                            <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" name="toggle_resident_status" class="px-6 py-2.5 bg-emerald-100 text-emerald-700 font-bold rounded-full text-xs hover:bg-emerald-600 hover:text-white transition-all">
                                <i class="fas fa-check mr-2"></i> Approve Account
                            </button>
                        </form>
                        <form method="POST" action="">
                            <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" name="toggle_resident_status" class="px-6 py-2.5 bg-rose-50 text-rose-600 font-bold rounded-full text-xs hover:bg-rose-600 hover:text-white transition-all">
                                <i class="fas fa-times mr-2"></i> Decline
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Approved Residents Tab -->
    <div id="approved-residents-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" style="display: none;">
        <?php if (empty($approvedResidents)): ?>
            <div class="col-span-full text-center py-16 bg-green-50 rounded-[2rem] border-2 border-dashed border-green-200">
                <i class="fas fa-check-circle text-5xl text-green-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">No approved residents</h3>
                <p class="text-gray-500">Approve some pending accounts to see them here</p>
            </div>
        <?php else: ?>
            <?php foreach ($approvedResidents as $resident): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 hover:shadow-xl hover:shadow-gray-200/50 transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="text-center flex-1">
                            <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($resident['full_name']) ?></h3>
                            <p class="text-emerald-500 font-bold text-sm uppercase tracking-tighter">@<?= htmlspecialchars($resident['username']) ?></p>
                        </div>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded-full tracking-widest">Approved</span>
                    </div>
                    
                    <?php 
                    // Check if account has linked patient record
                    $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE user_id = ?");
                    $stmt->execute([$resident['id']]);
                    $hasPatientRecord = $stmt->fetch();
                    ?>
                    <?php if (!$hasPatientRecord): ?>
                    <span class="inline-block px-3 py-1 bg-amber-50 text-amber-600 text-[10px] font-black uppercase rounded-full tracking-widest mb-4">Unlinked</span>
                    <?php else: ?>
                    <span class="inline-block px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black uppercase rounded-full tracking-widest mb-4">Linked</span>
                    <?php endif; ?>
                    
                    <div class="space-y-3 mb-8 text-center border-t border-b border-gray-50 py-6">
                        <p class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-300"></i><?= htmlspecialchars($resident['email']) ?></p>
                        <?php if ($resident['contact']): ?>
                        <p class="text-sm text-gray-500"><i class="fas fa-phone mr-2 text-gray-300"></i><?= htmlspecialchars($resident['contact']) ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500"><i class="fas fa-id-card mr-2 text-gray-300"></i>ID: <?= htmlspecialchars($resident['unique_number']) ?></p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button onclick="showChangeResidentPasswordModal(<?= $resident['id'] ?>, '<?= htmlspecialchars($resident['full_name']) ?>')"
                                class="px-6 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-full text-xs hover:bg-blue-600 hover:text-white transition-all w-full">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                        <form method="POST" action="" class="w-full">
                            <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" name="toggle_resident_status" 
                                    class="px-6 py-2.5 bg-amber-50 text-amber-700 font-bold rounded-full text-xs hover:bg-amber-600 hover:text-white transition-all w-full"
                                    onclick="return confirm('Suspend this account?')">
                                <i class="fas fa-pause mr-2"></i> Suspend Account
                            </button>
                        </form>
                        <?php if (!$hasPatientRecord): ?>
                        <a href="?section=linking&focus_resident=<?= $resident['id'] ?>" 
                           class="px-6 py-2.5 bg-indigo-50 text-indigo-700 font-bold rounded-full text-xs hover:bg-indigo-600 hover:text-white transition-all w-full text-center">
                            <i class="fas fa-link mr-2"></i> Link Patient Record
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Declined Residents Tab -->
    <div id="declined-residents-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" style="display: none;">
        <?php if (empty($declinedResidents)): ?>
            <div class="col-span-full text-center py-16 bg-red-50 rounded-[2rem] border-2 border-dashed border-red-200">
                <i class="fas fa-times-circle text-5xl text-red-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">No declined residents</h3>
                <p class="text-gray-500">No resident applications have been declined</p>
            </div>
        <?php else: ?>
            <?php foreach ($declinedResidents as $resident): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 hover:shadow-xl hover:shadow-gray-200/50 transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="text-center flex-1">
                            <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($resident['full_name']) ?></h3>
                            <p class="text-gray-500 font-bold text-sm uppercase tracking-tighter">@<?= htmlspecialchars($resident['username']) ?></p>
                        </div>
                        <span class="px-3 py-1 bg-rose-50 text-rose-600 text-[10px] font-black uppercase rounded-full tracking-widest">Declined</span>
                    </div>
                    
                    <div class="space-y-3 mb-8 text-center border-t border-b border-gray-50 py-6">
                        <p class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-300"></i><?= htmlspecialchars($resident['email']) ?></p>
                        <p class="text-sm text-gray-500"><i class="fas fa-calendar-times mr-2 text-gray-300"></i>Declined: <?= date('M j, Y', strtotime($resident['updated_at'])) ?></p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button onclick="showChangeResidentPasswordModal(<?= $resident['id'] ?>, '<?= htmlspecialchars($resident['full_name']) ?>')"
                                class="px-6 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-full text-xs hover:bg-blue-600 hover:text-white transition-all w-full">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                        <form method="POST" action="" class="w-full">
                            <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" name="toggle_resident_status" 
                                    class="px-6 py-2.5 bg-emerald-100 text-emerald-700 font-bold rounded-full text-xs hover:bg-emerald-600 hover:text-white transition-all w-full"
                                    onclick="return confirm('Approve this declined account?')">
                                <i class="fas fa-check mr-2"></i> Approve Account
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Unlinked Residents Tab -->
    <div id="unlinked-residents-tab" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" style="display: none;">
        <?php if (empty($unlinkedResidents)): ?>
            <div class="col-span-full text-center py-16 bg-orange-50 rounded-[2rem] border-2 border-dashed border-orange-200">
                <i class="fas fa-unlink text-5xl text-orange-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-900">All accounts are linked!</h3>
                <p class="text-gray-500">All resident accounts have patient records linked</p>
            </div>
        <?php else: ?>
            <?php foreach ($unlinkedResidents as $resident): ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-8 hover:shadow-xl hover:shadow-gray-200/50 transition-all group">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                        <h3 class="font-black text-gray-900 text-xl leading-tight"><?= htmlspecialchars($resident['full_name']) ?></h3>
                        <p class="text-amber-500 font-bold text-sm uppercase tracking-tighter">@<?= htmlspecialchars($resident['username']) ?></p>
                    </div>
                    
                    <div class="space-y-3 mb-8 text-center border-t border-b border-gray-50 py-6">
                        <p class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-300"></i><?= htmlspecialchars($resident['email']) ?></p>
                        <?php if ($resident['age'] > 0): ?>
                        <p class="text-sm text-gray-500"><i class="fas fa-user mr-2 text-gray-300"></i>Age: <?= htmlspecialchars($resident['age']) ?></p>
                        <?php endif; ?>
                        <?php if ($resident['sitio']): ?>
                        <p class="text-sm text-gray-500"><i class="fas fa-map-marker-alt mr-2 text-gray-300"></i><?= htmlspecialchars($resident['sitio']) ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500"><i class="fas fa-calendar-plus mr-2 text-gray-300"></i>Created: <?= date('M j, Y', strtotime($resident['created_at'])) ?></p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button onclick="showChangeResidentPasswordModal(<?= $resident['id'] ?>, '<?= htmlspecialchars($resident['full_name']) ?>')"
                                class="px-6 py-2.5 bg-blue-50 text-blue-700 font-bold rounded-full text-xs hover:bg-blue-600 hover:text-white transition-all">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                        <a href="?section=linking&focus_resident=<?= $resident['id'] ?>" 
                           class="px-6 py-2.5 bg-indigo-50 text-indigo-700 font-bold rounded-full text-xs hover:bg-indigo-600 hover:text-white transition-all">
                            <i class="fas fa-link mr-2"></i> Link Patient Record
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

        <!-- Manual Linking Section -->
<section id="linking-section" class="fade-in" style="display: none;">
    <div class="card mb-8">
        <div class="card-header">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-link text-purple-600"></i>
                Manual Account-Patient Linking
            </h2>
            <p class="text-gray-600 text-sm mt-2">Link resident accounts to existing patient records</p>
        </div>
        
        <div class="p-6">
            <?php if (count($unlinkedResidents) === 0 && count($unlinkedPatients) === 0): ?>
                <div class="text-center py-12 bg-green-50 rounded-2xl">
                    <i class="fas fa-check-circle text-5xl text-green-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">All accounts are properly linked!</h3>
                    <p class="mt-1 text-sm text-gray-500">No manual linking needed at this time.</p>
                </div>
            <?php else: ?>
                <!-- Link Section -->
                <div class="mb-8">
                    <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-3">
                        <i class="fas fa-handshake text-blue-600 text-lg"></i> 
                        <span class="text-lg">Link Accounts to Patient Records</span>
                    </h3>
                    
                    <!-- Grid Layout for Horizontal Cards -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Unlinked Residents Column -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-700 text-base">
                                    <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                                    Unlinked Resident Accounts
                                </h4>
                                <span class="badge badge-info rounded-full px-3 py-1">
                                    <?= count($unlinkedResidents) ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="resident-grid">
                                <?php foreach ($unlinkedResidents as $resident): ?>
                                    <div class="account-card rounded-2xl border-2 border-gray-200 hover:border-blue-300 transition-all duration-200 cursor-pointer p-4 resident-card"
                                         data-resident-id="<?= $resident['id'] ?>"
                                         onclick="selectResidentCard(this, <?= $resident['id'] ?>, '<?= htmlspecialchars($resident['full_name']) ?>', '<?= htmlspecialchars($resident['email']) ?>', '<?= htmlspecialchars($resident['sitio'] ?? '') ?>', <?= $resident['age'] ?? 0 ?>)">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-user text-blue-500"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($resident['full_name']) ?></div>
                                                <div class="text-xs text-gray-500 truncate mt-1">
                                                    <?= htmlspecialchars($resident['email']) ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <?php if ($resident['sitio']): ?>
                                                        <span class="inline-flex items-center gap-1 text-xs text-gray-600 bg-gray-100 rounded-full px-2 py-1">
                                                            <i class="fas fa-map-marker-alt text-xs"></i>
                                                            <?= htmlspecialchars($resident['sitio']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($resident['age'] > 0): ?>
                                                        <span class="text-xs text-gray-600">• Age: <?= htmlspecialchars($resident['age']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Unlinked Patients Column -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-700 text-base">
                                    <i class="fas fa-file-medical text-green-500 mr-2"></i>
                                    Unlinked Patient Records
                                </h4>
                                <span class="badge badge-success rounded-full px-3 py-1">
                                    <?= count($unlinkedPatients) ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="patient-grid">
                                <?php foreach ($unlinkedPatients as $patient): ?>
                                    <div class="account-card rounded-2xl border-2 border-gray-200 hover:border-green-300 transition-all duration-200 cursor-pointer p-4 patient-card"
                                         data-patient-id="<?= $patient['id'] ?>"
                                         onclick="selectPatientCard(this, <?= $patient['id'] ?>, '<?= htmlspecialchars($patient['full_name']) ?>', <?= $patient['age'] ?? 0 ?>, '<?= htmlspecialchars($patient['gender'] ?? '') ?>', '<?= htmlspecialchars($patient['sitio'] ?? '') ?>')">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-file-medical text-green-500"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($patient['full_name']) ?></div>
                                                <div class="text-xs text-gray-500 truncate mt-1">
                                                    ID: <?= $patient['id'] ?>
                                                </div>
                                                <div class="flex flex-wrap gap-2 mt-2">
                                                    <?php if ($patient['age']): ?>
                                                        <span class="inline-flex items-center gap-1 text-xs text-gray-600 bg-gray-100 rounded-full px-2 py-1">
                                                            <i class="fas fa-birthday-cake text-xs"></i>
                                                            <?= htmlspecialchars($patient['age']) ?> yrs
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($patient['gender']): ?>
                                                        <span class="inline-flex items-center gap-1 text-xs text-gray-600 bg-gray-100 rounded-full px-2 py-1">
                                                            <i class="fas fa-venus-mars text-xs"></i>
                                                            <?= htmlspecialchars($patient['gender']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($patient['sitio']): ?>
                                                        <span class="inline-flex items-center gap-1 text-xs text-gray-600 bg-gray-100 rounded-full px-2 py-1">
                                                            <i class="fas fa-map-marker-alt text-xs"></i>
                                                            <?= htmlspecialchars($patient['sitio']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Items Panel -->
                    <div class="bg-gradient-to-r from-blue-50 to-green-50 border-2 border-blue-200 rounded-2xl p-5 mb-6 shadow-sm" id="selected-items-panel" style="display: none;">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                    <i class="fas fa-handshake text-blue-600 text-lg"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Ready to Link</h4>
                                    <p class="text-sm text-gray-600">Selected items for linking</p>
                                </div>
                            </div>
                            <button type="button" 
                                    class="btn btn-warning rounded-full px-4 py-2 text-sm hover:scale-105 transition-transform"
                                    onclick="clearLinkingSelection()"
                                    aria-label="Clear selection">
                                <i class="fas fa-times mr-2"></i> Clear Selection
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Selected Resident Card -->
                            <div class="bg-white rounded-xl border-2 border-blue-200 p-4" id="selected-resident-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-500"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h5 class="font-semibold text-gray-700 text-sm">Selected Resident</h5>
                                        <div class="text-gray-800 font-medium text-base" id="selected-resident-name">No resident selected</div>
                                    </div>
                                    <div class="badge badge-info rounded-full px-3 py-1 text-xs">
                                        Account
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500" id="selected-resident-details">
                                    Select a resident account from the left panel
                                </div>
                            </div>
                            
                            <!-- Selected Patient Card -->
                            <div class="bg-white rounded-xl border-2 border-green-200 p-4" id="selected-patient-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-file-medical text-green-500"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h5 class="font-semibold text-gray-700 text-sm">Selected Patient Record</h5>
                                        <div class="text-gray-800 font-medium text-base" id="selected-patient-name">No patient selected</div>
                                    </div>
                                    <div class="badge badge-success rounded-full px-3 py-1 text-xs">
                                        Record
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500" id="selected-patient-details">
                                    Select a patient record from the right panel
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <button type="button" 
                                class="btn btn-primary rounded-full px-8 py-4 text-base font-medium transition-all duration-200 flex items-center justify-center gap-3 w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                                id="link-action-button" 
                                onclick="performLinking()" 
                                disabled>
                            <i class="fas fa-link text-lg"></i>
                            <span id="link-button-text" class="text-lg">Select Both Items</span>
                        </button>
                        
                        <button type="button" 
                                class="btn btn-outline rounded-full px-6 py-3 text-sm font-medium border-2 border-gray-300 text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 w-full sm:w-auto"
                                onclick="clearLinkingSelection()">
                            <i class="fas fa-redo mr-2"></i> Reset Selection
                        </button>
                    </div>
                    
                    <input type="hidden" id="selected-resident-id" value="0">
                    <input type="hidden" id="selected-patient-id" value="0">
                </div>
                
                <!-- Information Section -->
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-2xl p-5 mt-8">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-info-circle text-yellow-500 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-yellow-800 mb-2">How This Works:</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-1 text-yellow-600 mt-1"></i>
                                    <p class="text-sm text-yellow-700">Create resident accounts (no patient records initially)</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-2 text-yellow-600 mt-1"></i>
                                    <p class="text-sm text-yellow-700">Add patient records separately through patient management</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-3 text-yellow-600 mt-1"></i>
                                    <p class="text-sm text-yellow-700">Link accounts to records here (one account = one record)</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-4 text-yellow-600 mt-1"></i>
                                    <p class="text-sm text-yellow-700">Residents can view medical history after linking</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                Delete Staff Account
            </h3>
            
            <p class="text-gray-600 mb-6" id="delete-message"></p>
            
            <form method="POST" action="" id="delete-form">
                <input type="hidden" name="staff_id" id="delete-staff-id">
                
                <div class="mb-6">
                    <label class="block font-medium text-gray-700 mb-3">Handle Dependent Records:</label>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-3 border rounded-lg hover:bg-blue-50 cursor-pointer">
                            <input type="radio" name="delete_action" value="reassign" checked class="mr-3">
                            <div>
                                <span class="font-medium">Reassign to another staff</span>
                                <select name="reassign_to" class="form-input mt-2" required>
                                    <option value="">Select staff member</option>
                                    <?php foreach ($allStaff as $staff): ?>
                                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-3 border rounded-lg hover:bg-red-50 cursor-pointer">
                            <input type="radio" name="delete_action" value="delete" class="mr-3">
                            <div>
                                <span class="font-medium text-red-600">Delete all associated records</span>
                                <p class="text-sm text-red-500 mt-1">Warning: This action cannot be undone</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-warning">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" name="hard_delete" class="btn btn-danger">
                        <i class="fas fa-trash mr-2"></i> Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Staff Password Modal -->
    <div id="changeStaffPasswordModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                <i class="fas fa-key text-blue-500"></i>
                Change Staff Password
            </h3>
            
            <form method="POST" action="" id="change-staff-password-form">
                <input type="hidden" name="staff_id" id="change-staff-id">
                
                <div class="form-group mb-6">
                    <label class="form-label">Staff Name</label>
                    <input type="text" id="change-staff-name" class="form-input bg-gray-50" readonly>
                </div>
                
                <div class="form-group mb-6">
                    <label class="form-label">Current Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="current_password" required 
                               id="staff-current-password"
                               class="form-input pr-10" 
                               placeholder="Enter current password"
                               aria-label="Current password">
                        <button type="button" class="password-toggle" onclick="togglePassword('staff-current-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group mb-6">
                    <label class="form-label">New Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="new_password" required 
                               id="staff-new-password"
                               class="form-input pr-10" 
                               placeholder="Enter new password"
                               aria-label="New password">
                        <button type="button" class="password-toggle" onclick="togglePassword('staff-new-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group mb-8">
                    <label class="form-label">Confirm New Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="confirm_password" required 
                               id="staff-confirm-password"
                               class="form-input pr-10" 
                               placeholder="Confirm new password"
                               aria-label="Confirm password">
                        <button type="button" class="password-toggle" onclick="togglePassword('staff-confirm-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeChangeStaffPasswordModal()" class="btn btn-warning">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" name="change_staff_password" class="btn btn-success">
                        <i class="fas fa-save mr-2"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Resident Password Modal -->
    <div id="changeResidentPasswordModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                <i class="fas fa-key text-blue-500"></i>
                Change Resident Password
            </h3>
            
            <form method="POST" action="" id="change-resident-password-form">
                <input type="hidden" name="resident_id" id="change-resident-id">
                
                <div class="form-group mb-6">
                    <label class="form-label">Resident Name</label>
                    <input type="text" id="change-resident-name" class="form-input bg-gray-50" readonly>
                </div>
                
                <div class="form-group mb-6">
                    <label class="form-label">Current Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="current_password" required 
                               id="resident-current-password"
                               class="form-input pr-10" 
                               placeholder="Enter current password"
                               aria-label="Current password">
                        <button type="button" class="password-toggle" onclick="togglePassword('resident-current-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group mb-6">
                    <label class="form-label">New Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="new_password" required 
                               id="resident-new-password"
                               class="form-input pr-10" 
                               placeholder="Enter new password"
                               aria-label="New password">
                        <button type="button" class="password-toggle" onclick="togglePassword('resident-new-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group mb-8">
                    <label class="form-label">Confirm New Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="confirm_password" required 
                               id="resident-confirm-password"
                               class="form-input pr-10" 
                               placeholder="Confirm new password"
                               aria-label="Confirm password">
                        <button type="button" class="password-toggle" onclick="togglePassword('resident-confirm-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeChangeResidentPasswordModal()" class="btn btn-warning">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" name="change_resident_password" class="btn btn-success">
                        <i class="fas fa-save mr-2"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal (Admin Reset - No Current Password) -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                <i class="fas fa-key text-blue-500"></i>
                Reset Resident Password
            </h3>
            
            <form method="POST" action="" id="reset-password-form">
                <input type="hidden" name="resident_id" id="reset-resident-id">
                
                <div class="form-group mb-6">
                    <label class="form-label">Resident Name</label>
                    <input type="text" id="reset-resident-name" class="form-input bg-gray-50" readonly>
                </div>
                
                <div class="form-group mb-6">
                    <label class="form-label">New Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="new_password" required 
                               id="reset-new-password"
                               class="form-input pr-10" 
                               placeholder="Enter new password"
                               aria-label="New password">
                        <button type="button" class="password-toggle" onclick="togglePassword('reset-new-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group mb-8">
                    <label class="form-label">Confirm Password <span class="text-red-500">*</span></label>
                    <div class="password-container relative">
                        <input type="password" name="confirm_password" required 
                               id="reset-confirm-password"
                               class="form-input pr-10" 
                               placeholder="Confirm new password"
                               aria-label="Confirm password">
                        <button type="button" class="password-toggle" onclick="togglePassword('reset-confirm-password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeResetModal()" class="btn btn-warning">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" name="reset_resident_password" class="btn btn-success">
                        <i class="fas fa-save mr-2"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== GLOBAL VARIABLES =====
        let selectedResidentId = 0;
        let selectedPatientId = 0;

        // ===== PASSWORD TOGGLE FUNCTION =====
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggleButton = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // Initialize password toggles on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Make staff password visible by default
            const staffPassword = document.getElementById('staff-password');
            if (staffPassword) {
                staffPassword.type = 'text';
            }
            
            // Make resident password visible by default
            const residentPassword = document.getElementById('resident-password');
            if (residentPassword) {
                residentPassword.type = 'text';
            }
        });

        // ===== MESSAGE MODAL =====
        function showMessageModal(message, type = 'success') {
            const modal = document.getElementById('messageModal');
            if (!modal) {
                // Create modal if it doesn't exist
                createMessageModal(message, type);
                return;
            }
            
            // Update content
            const content = modal.querySelector('.message-content');
            const icon = modal.querySelector('.message-icon');
            const title = modal.querySelector('.message-title');
            const body = modal.querySelector('.message-body');
            const progressBar = document.getElementById('messageProgressBar');
            
            content.className = 'message-content ' + type;
            icon.className = 'message-icon ' + type + ' fas ' + (type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle');
            title.className = 'message-title ' + type;
            title.textContent = type === 'error' ? 'Error' : 'Success';
            body.textContent = message;
            
            // Set dynamic width class based on message length
            const msgLength = message.length;
            let widthClass = 'short';
            if (msgLength < 50) widthClass = 'short';
            else if (msgLength < 80) widthClass = 'medium';
            else if (msgLength < 120) widthClass = 'long';
            else if (msgLength < 160) widthClass = 'extra-long';
            else widthClass = 'max';
            
            modal.className = 'message-modal ' + widthClass;
            
            // Reset progress bar
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.style.transition = 'none';
                void progressBar.offsetWidth; // Trigger reflow
                progressBar.style.transition = 'width 1s linear';
                progressBar.style.width = '0%';
            }
            
            // Show modal
            modal.classList.add('show');
            
            // Auto-hide after 1 second
            setTimeout(() => {
                closeMessageModal();
            }, 1000);
        }
        
        function createMessageModal(message, type) {
            const modal = document.createElement('div');
            modal.id = 'messageModal';
            modal.className = 'message-modal';
            
            // Calculate width class
            const msgLength = message.length;
            let widthClass = 'short';
            if (msgLength < 50) widthClass = 'short';
            else if (msgLength < 80) widthClass = 'medium';
            else if (msgLength < 120) widthClass = 'long';
            else if (msgLength < 160) widthClass = 'extra-long';
            else widthClass = 'max';
            
            modal.className = 'message-modal ' + widthClass;
            
            modal.innerHTML = `
                <div class="message-content ${type}">
                    <button class="message-close" onclick="closeMessageModal()" aria-label="Close message">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="message-header">
                        <i class="message-icon ${type} fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
                        <span class="message-title ${type}">
                            ${type === 'error' ? 'Error' : 'Success'}
                        </span>
                    </div>
                    <div class="message-body">
                        ${message}
                    </div>
                    <div class="message-progress">
                        <div class="message-progress-bar ${type}" id="messageProgressBar"></div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Reset progress bar
            const progressBar = document.getElementById('messageProgressBar');
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.style.transition = 'none';
                void progressBar.offsetWidth; // Trigger reflow
                progressBar.style.transition = 'width 1s linear';
                progressBar.style.width = '0%';
            }
            
            // Show modal
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
            
            // Auto-hide after 1 second
            setTimeout(() => {
                closeMessageModal();
            }, 1000);
        }
        
        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                modal.classList.remove('show');
                // Remove from DOM after animation
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                }, 300);
            }
        }
        
        // Show message on page load if there's a message in session
        <?php if (isset($message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                showMessageModal("<?= addslashes($message) ?>", "<?= $messageType ?>");
            }, 300);
        });
        <?php endif; ?>
        
        // ===== TAB MANAGEMENT =====
        function showSection(section) {
            // Hide all sections
            document.getElementById('staff-section').style.display = 'none';
            document.getElementById('resident-section').style.display = 'none';
            document.getElementById('linking-section').style.display = 'none';
            
            // Remove active class from all main tabs
            document.querySelectorAll('.tabs-container .tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
            
            // If switching to linking tab, check if we need to focus on a specific resident
            if (section === 'linking' && window.location.search.includes('focus_resident=')) {
                const urlParams = new URLSearchParams(window.location.search);
                const residentId = urlParams.get('focus_resident');
                if (residentId) {
                    setTimeout(() => {
                        const residentCard = document.querySelector(`[data-resident-id="${residentId}"]`);
                        if (residentCard) {
                            residentCard.click();
                            residentCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 300);
                }
            }
        }
        
        function showStaffTab(tab) {
            // Hide all staff tabs
            document.getElementById('active-staff-tab').style.display = 'none';
            document.getElementById('inactive-staff-tab').style.display = 'none';
            
            // Remove active class from all buttons
            document.querySelectorAll('#staff-section .tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tab + '-staff-tab').style.display = 'grid';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        function showResidentTab(tab) {
            // Hide all resident tabs
            const tabs = ['pending', 'approved', 'declined', 'unlinked'];
            tabs.forEach(t => {
                const element = document.getElementById(t + '-residents-tab');
                if (element) element.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('#resident-section .tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tab + '-residents-tab');
            if (selectedTab) selectedTab.style.display = 'grid';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // ===== AGE CALCULATION =====
        function calculateAge() {
            const dobInput = document.getElementById('date-of-birth');
            const ageInput = document.getElementById('age');
            
            if (!dobInput.value) {
                ageInput.value = '';
                return;
            }
            
            const dob = new Date(dobInput.value);
            const today = new Date();
            
            if (dob > today) {
                showMessageModal('Date of birth cannot be in the future', 'error');
                dobInput.value = '';
                ageInput.value = '';
                return;
            }
            
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < 0 || age > 120) {
                showMessageModal('Please enter a valid date of birth (age 0-120)', 'error');
                dobInput.value = '';
                ageInput.value = '';
                return;
            }
            
            ageInput.value = age;
        }
        
        // ===== MODAL FUNCTIONS =====
        function showDeleteModal(staffId, staffName) {
            document.getElementById('delete-staff-id').value = staffId;
            document.getElementById('delete-message').textContent = 
                `Are you sure you want to delete ${staffName}? This action will affect associated records.`;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.getElementById('delete-form').reset();
        }
        
        function showChangeStaffPasswordModal(staffId, staffName) {
            document.getElementById('change-staff-id').value = staffId;
            document.getElementById('change-staff-name').value = staffName;
            document.getElementById('changeStaffPasswordModal').classList.add('show');
        }
        
        function closeChangeStaffPasswordModal() {
            document.getElementById('changeStaffPasswordModal').classList.remove('show');
            document.getElementById('change-staff-password-form').reset();
        }
        
        function showChangeResidentPasswordModal(residentId, residentName) {
            document.getElementById('change-resident-id').value = residentId;
            document.getElementById('change-resident-name').value = residentName;
            document.getElementById('changeResidentPasswordModal').classList.add('show');
        }
        
        function closeChangeResidentPasswordModal() {
            document.getElementById('changeResidentPasswordModal').classList.remove('show');
            document.getElementById('change-resident-password-form').reset();
        }
        
        function showResetPasswordModal(residentId, residentName) {
            document.getElementById('reset-resident-id').value = residentId;
            document.getElementById('reset-resident-name').value = residentName;
            document.getElementById('resetPasswordModal').classList.add('show');
        }
        
        function closeResetModal() {
            document.getElementById('resetPasswordModal').classList.remove('show');
            document.getElementById('reset-password-form').reset();
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            const modals = [
                'deleteModal', 
                'changeStaffPasswordModal', 
                'changeResidentPasswordModal', 
                'resetPasswordModal'
            ];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && event.target === modal) {
                    if (modalId === 'deleteModal') closeDeleteModal();
                    if (modalId === 'changeStaffPasswordModal') closeChangeStaffPasswordModal();
                    if (modalId === 'changeResidentPasswordModal') closeChangeResidentPasswordModal();
                    if (modalId === 'resetPasswordModal') closeResetModal();
                }
            });
        }
        
        // ===== LINKING FUNCTIONS =====
        function selectResidentCard(element, residentId, residentName, email, sitio, age) {
            // Remove previous selection from resident cards
            document.querySelectorAll('.resident-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection
            element.classList.add('selected');
            selectedResidentId = residentId;
            document.getElementById('selected-resident-id').value = residentId;
            
            // Update display
            document.getElementById('selected-resident-name').textContent = residentName;
            document.getElementById('selected-resident-details').innerHTML = `
                <div class="mb-1"><i class="fas fa-envelope text-xs text-gray-400 mr-2"></i>${email || 'No email'}</div>
                <div class="mb-1"><i class="fas fa-map-marker-alt text-xs text-gray-400 mr-2"></i>${sitio || 'Not specified'}</div>
                <div><i class="fas fa-user text-xs text-gray-400 mr-2"></i>Age: ${age > 0 ? age : 'Not specified'}</div>
            `;
            
            // Show selected items panel
            document.getElementById('selected-items-panel').style.display = 'block';
            
            // Enable link button if both selected
            updateLinkButton();
        }
        
        function selectPatientCard(element, patientId, patientName, age, gender, sitio) {
            // Remove previous selection from patient cards
            document.querySelectorAll('.patient-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection
            element.classList.add('selected');
            selectedPatientId = patientId;
            document.getElementById('selected-patient-id').value = patientId;
            
            // Update display
            document.getElementById('selected-patient-name').textContent = patientName;
            document.getElementById('selected-patient-details').innerHTML = `
                <div class="mb-1"><i class="fas fa-id-badge text-xs text-gray-400 mr-2"></i>Record ID: ${patientId}</div>
                <div class="mb-1"><i class="fas fa-venus-mars text-xs text-gray-400 mr-2"></i>${gender || 'Not specified'}</div>
                <div><i class="fas fa-map-marker-alt text-xs text-gray-400 mr-2"></i>${sitio || 'Not specified'}</div>
                ${age > 0 ? '<div><i class="fas fa-birthday-cake text-xs text-gray-400 mr-2"></i>Age: ' + age + ' years</div>' : ''}
            `;
            
            // Show selected items panel
            document.getElementById('selected-items-panel').style.display = 'block';
            
            // Enable link button if both selected
            updateLinkButton();
        }
        
        function updateLinkButton() {
            const linkButton = document.getElementById('link-action-button');
            const linkButtonText = document.getElementById('link-button-text');
            
            if (selectedResidentId > 0 && selectedPatientId > 0) {
                linkButton.disabled = false;
                linkButtonText.textContent = 'Link Accounts Now';
                linkButton.classList.remove('opacity-60');
                linkButton.classList.add('shadow-lg', 'hover:shadow-xl');
            } else {
                linkButton.disabled = true;
                linkButtonText.textContent = 'Select Both Items';
                linkButton.classList.add('opacity-60');
                linkButton.classList.remove('shadow-lg', 'hover:shadow-xl');
            }
        }
        
        function clearLinkingSelection() {
            // Clear selections
            selectedResidentId = 0;
            selectedPatientId = 0;
            document.getElementById('selected-resident-id').value = '0';
            document.getElementById('selected-patient-id').value = '0';
            
            // Remove selection classes
            document.querySelectorAll('.resident-card, .patient-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Reset displays
            document.getElementById('selected-resident-name').textContent = 'No resident selected';
            document.getElementById('selected-resident-details').innerHTML = 'Select a resident account from the left panel';
            document.getElementById('selected-patient-name').textContent = 'No patient selected';
            document.getElementById('selected-patient-details').innerHTML = 'Select a patient record from the right panel';
            
            // Hide selected items panel
            document.getElementById('selected-items-panel').style.display = 'none';
            
            // Reset link button
            updateLinkButton();
        }
        
        function performLinking() {
            if (selectedResidentId === 0 || selectedPatientId === 0) {
                showMessageModal('Please select both a resident account and a patient record.', 'error');
                return;
            }
            
            if (confirm('Are you sure you want to link these accounts?\n\n✓ Resident will be able to view their medical history\n✓ One-to-one linking ensured\n✓ Cannot be undone without admin access')) {
                // Show loading
                const linkButton = document.getElementById('link-action-button');
                const linkButtonText = document.getElementById('link-button-text');
                linkButtonText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Linking...';
                linkButton.disabled = true;
                
                // Perform linking
                window.location.href = `?link_resident=1&resident_id=${selectedResidentId}&patient_id=${selectedPatientId}`;
            }
        }
        
        // ===== INITIALIZATION =====
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-generate username from email
            const emailInput = document.querySelector('input[name="email"]');
            const usernameInput = document.querySelector('input[name="username"]');
            
            if (emailInput && usernameInput) {
                emailInput.addEventListener('blur', function() {
                    if (!usernameInput.value && this.value) {
                        const username = this.value.split('@')[0];
                        usernameInput.value = username;
                    }
                });
            }
            
            // Check URL for section parameter
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            
            if (section === 'linking') {
                // Switch to linking tab
                const linkingTab = document.getElementById('linking-tab');
                if (linkingTab) {
                    showSection('linking');
                    document.getElementById('staff-tab').classList.remove('active');
                    document.getElementById('resident-tab').classList.remove('active');
                    linkingTab.classList.add('active');
                }
            }
        });
    </script>
</body>
</html>