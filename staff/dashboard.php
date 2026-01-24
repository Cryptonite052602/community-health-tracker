
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Function to generate unique number
function generateUniqueNumber($pdo) {
    $prefix = 'CHT';
    $unique = false;
    $uniqueNumber = '';
    
    while (!$unique) {
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $uniqueNumber = $prefix . $randomNumber;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE unique_number = ?");
        $stmt->execute([$uniqueNumber]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $unique = true;
        }
    }
    
    return $uniqueNumber;
}

// Function to send email notification
function sendAccountStatusEmail($email, $status, $message = '', $uniqueNumber = '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabanagarchiel@gmail.com';
        $mail->Password   = 'qmdh ofnf bhfj wxsa';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('cabanagarchiel@gmail.com', 'Barangay Luz Health Center');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {

$mail->Subject = 'Barangay Luz Health Monitoring and Tracking System';
$mail->Body = '
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#ffffff; font-family: Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">

<!-- Header -->
<tr>
<td style="background-color:#2563eb; padding:30px; text-align:center;">
    <div style="font-size:26px; font-weight:bold; color:#ffffff;">
        <img src="/asssets/images/Luz.jpg" style="width: 100px; height: auto; margin-right: 10px;">
            Barangay Luz Health Monitoring and Tracking
    </div>
    <div style="margin-top:8px; font-size:16px; color:#dbeafe;">
        Account Approval Notice
    </div>
</td>
</tr>

<!-- Content -->
<tr>
<td style="padding:30px; color:#1f2937; font-size:15px; line-height:1.7;">

<p>Hello, </p>

<p>
We are happy to inform you that your registration has been
<strong style="color:#2563eb;">successfully approved</strong>.
Your account is now active and ready for use.
</p>

<!-- Unique Number -->
<div style="
    background-color:#eff6ff;
    border:1px solid #3b82f6;
    border-radius:8px;
    padding:16px;
    text-align:center;
    margin:25px 0;
">
    <div style="font-size:13px; color:#2563eb; margin-bottom:6px;">
        Your Unique Identification Number
    </div>
    <div style="font-size:22px; font-weight:bold; letter-spacing:1px; color:#1e3a8a;">
        ' . $uniqueNumber . '
    </div>
</div>

<p>
Please keep this number secure. It will be used for appointments,
medical records, and identity verification.
</p>

<ul style="padding-left:18px;">
    <li>Book healthcare appointments</li>
    <li>View medical history</li>
    <li>Receive health updates</li>
</ul>

<!-- Button -->
<div style="text-align:center; margin-top:30px;">
    <a href="https://your-health-portal.com/login"
       style="
        background-color:#3b82f6;
        color:#ffffff;
        text-decoration:none;
        padding:14px 28px;
        border-radius:6px;
        font-size:15px;
        display:inline-block;
       ">
        Access Your Account
    </a>
</div>

<p style="margin-top:30px;">
Thank you for trusting <strong>Barangay Luz Health Monitoring and Tracking Platform</strong> with your healthcare needs.
</p>

<p>
Warm regards,<br>
<strong>The Barangay Luz Health Center Team</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="
    background-color:#f8fafc;
    padding:20px;
    text-align:center;
    font-size:12px;
    color:#6b7280;
    border-top:1px solid #e5e7eb;
">
This is an automated message. Please do not reply.<br>
© ' . date('Y') . ' Barangay Luz Health Monitoring and Tracking System
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
';
}
 else {

$mail->Subject = 'Account Registration Update – Barangay Luz Health Monitoring and Tracking System';
$mail->Body = '
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#ffffff; font-family: Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px;">

<!-- Header -->
<tr>
<td style="background-color:#3b82f6; padding:30px; text-align:center;">
    <div style="font-size:26px; font-weight:bold; color:#ffffff;">
        🏥 Barangay Luz Health Monitoring and Tracking System
    </div>
    <div style="margin-top:8px; font-size:16px; color:#dbeafe;">
        Registration Status Update
    </div>
</td>
</tr>

<!-- Content -->
<tr>
<td style="padding:30px; color:#1f2937; font-size:15px; line-height:1.7;">

<p>Hello,</p>

<p>
Thank you for submitting your registration. After careful review,
we are unable to approve your account at this time.
</p>

<!-- Reason -->
<div style="
    background-color:#f8fafc;
    border-left:4px solid #3b82f6;
    padding:15px;
    margin:20px 0;
">
    <strong>Reason Provided:</strong><br>
    ' . htmlspecialchars($message) . '
</div>

<p>
You may reapply or contact our support team if you believe this decision
requires further review.
</p>

<div style="
    background-color:#eff6ff;
    padding:15px;
    border-radius:8px;
    margin:25px 0;
">
    <strong>Support Contact</strong><br>
    📞 (02) 8-123-4567<br>
    ✉️ support@communityhealthtracker.ph
</div>

<p>
We appreciate your understanding and interest in our services.
</p>

<p>
Sincerely,<br>
<strong>The Barangay Luz Health Monitoring and Tracking System Team</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="
    background-color:#f8fafc;
    padding:20px;
    text-align:center;
    font-size:12px;
    color:#6b7280;
    border-top:1px solid #e5e7eb;
">
This is an automated message. Please do not reply.<br>
© ' . date('Y') . ' Barangay Luz Health Monitoring and Tracking System
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
';
}


        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to automatically check ID type
function checkIdType($imagePath) {
    // Common keywords for different ID types (lowercase for matching)
    $idKeywords = [
        'senior' => ['senior', 'sc', 'senior citizen', 'senior-citizen', 'osc', 'office of senior citizen', 'senior card', 's.c.', 'sc id', 'senior citizen id'],
        'national' => ['national', 'phil', 'philippine', 'phil id', 'phil-id', 'philid', 'national id', 'philippine id', 'phil sys', 'philsys', 'phil. id', 'philippine national id', 'pnid'],
        'driver' => ['driver', 'license', 'dl', 'lto', 'land transportation', 'driver\'s license', 'driving license', 'driver license'],
        'voter' => ['voter', 'comelec', 'voter id', 'voter-id', 'voter\'s id', 'voters id', 'voter certification'],
        'passport' => ['passport', 'dfa', 'department of foreign affairs', 'philippine passport', 'passport id'],
        'umid' => ['umid', 'unified', 'multi-purpose', 'unified multi-purpose', 'unified id', 'multi-purpose id'],
        'sss' => ['sss', 'social security', 'social security system', 'sss id', 'sss card', 'sss number'],
        'gsis' => ['gsis', 'government service insurance system', 'gsis id', 'gsis card'],
        'tin' => ['tin', 'tax', 'taxpayer', 'taxpayer identification', 'tin id', 'tin card', 'tax identification'],
        'postal' => ['postal', 'post office', 'philpost', 'postal id', 'post office id'],
        'prc' => ['prc', 'professional', 'professional regulation', 'prc id', 'prc license', 'professional id'],
        'nbi' => ['nbi', 'national bureau', 'national bureau of investigation', 'nbi clearance', 'nbi id'],
        'birth' => ['birth', 'certificate', 'birth certificate', 'psa', 'civil registry', 'certificate of live birth'],
        'company' => ['company', 'employee', 'employee id', 'company id', 'office', 'work', 'office id', 'work id', 'employment id'],
        'student' => ['student', 'school', 'university', 'college', 'school id', 'student id', 'student card', 'university id']
    ];
    
    // Extract filename without extension and path
    $filename = strtolower(pathinfo($imagePath, PATHINFO_FILENAME));
    
    // Check if image path contains any ID keywords
    $lowerPath = strtolower($imagePath);
    
    foreach ($idKeywords as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($filename, $keyword) !== false || strpos($lowerPath, $keyword) !== false) {
                return ucfirst($type) . ' ID';
            }
        }
    }
    
    // If no specific type found, check for general ID indicators
    $generalIdIndicators = ['id', 'identification', 'card', 'certificate', 'license'];
    foreach ($generalIdIndicators as $indicator) {
        if (strpos($filename, $indicator) !== false || strpos($lowerPath, $indicator) !== false) {
            return 'Valid ID';
        }
    }
    
    return 'Unknown ID Type';
}

// Function to check if ID is valid for verification (Senior Citizen or National ID)
function isIdValidForVerification($idType) {
    $validTypes = ['Senior ID', 'National ID'];
    
    foreach ($validTypes as $validType) {
        if (stripos($idType, $validType) !== false) {
            return true;
        }
    }
    
    return false;
}

// Get stats for dashboard
$stats = [
    'total_patients' => 0,
    'consultations' => 0,
    'unapproved_users' => 0
];

// Staff ID
$staffId = $_SESSION['user']['id'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_user'])) {
        $userId = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        // Get user details first
        try {
            $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception("User not found.");
            }
            
            if (in_array($action, ['approve', 'decline'])) {
                if ($action === 'approve') {
                    // Generate unique number
                    $uniqueNumber = generateUniqueNumber($pdo);
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = TRUE, unique_number = ?, status = 'approved' WHERE id = ?");
                    $stmt->execute([$uniqueNumber, $userId]);
                    
                    // Send approval email
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'approved', '', $uniqueNumber);
                    }
                    
                    $_SESSION['success_message'] = 'Resident Account Approved Successfully!<br>Patient ID: <strong>' . $uniqueNumber . '</strong>';
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=account-management&success=true");
                    exit();
                } else {
                    $declineReason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = FALSE, status = 'declined' WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Send decline email with reason
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'declined', $declineReason);
                    }
                    
                    $_SESSION['success_message'] = 'Account Declined Successfully!';
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=account-management&success=true");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = 'Error processing user: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get active tab from URL parameter
$activeTab = $_GET['tab'] ?? 'analytics';

// Get data for dashboard and analytics
try {
    // Basic stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_patients WHERE added_by = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $stats['total_patients'] = $stmt->fetchColumn();

    // Get total consultations
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_consultations");
    $stats['consultations'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $stats['unapproved_users'] = $stmt->fetchColumn();
    
    // Analytics data for charts
    $analytics = [];
    
    // Initialize all keys with default values
    $analytics['appointments_total'] = 0;
    $analytics['completed_appointments'] = 0;
    $analytics['cancelled_appointments'] = 0;
    $analytics['missed_appointments'] = 0;
    
    // Total registered patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient'");
    $analytics['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Approved patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient' AND approved = TRUE");
    $analytics['approved_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Regular patients (patients with more than 1 appointment)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM sitio1_users u 
        JOIN user_appointments ua ON u.id = ua.user_id 
        WHERE u.role = 'patient' 
        GROUP BY u.id 
        HAVING COUNT(ua.id) > 1
    ");
    $regularPatientsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analytics['regular_patients'] = count($regularPatientsResult);
    
    // Appointment status distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ?
        GROUP BY status
    ");
    $stmt->execute([$staffId]);
    $appointmentStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analytics['appointment_status'] = $appointmentStatusData;
    
    // Calculate appointment counts
    foreach ($appointmentStatusData as $item) {
        if ($item['status'] === 'completed') {
            $analytics['completed_appointments'] = $item['count'];
        } elseif ($item['status'] === 'cancelled') {
            $analytics['cancelled_appointments'] = $item['count'];
        } elseif ($item['status'] === 'missed') {
            $analytics['missed_appointments'] = $item['count'];
        }
    }
    
    // Monthly appointments trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(ua.created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ? AND ua.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(ua.created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$staffId]);
    $analytics['monthly_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Patient registration trend (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM sitio1_users 
        WHERE role = 'patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $analytics['patient_registration_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total appointments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ?
    ");
    $stmt->execute([$staffId]);
    $appointmentsTotalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $analytics['appointments_total'] = $appointmentsTotalResult['total'] ?? 0;
    
    // Calculate completion rate safely
    $analytics['completion_rate'] = $analytics['appointments_total'] > 0 ? 
        round(($analytics['completed_appointments'] / $analytics['appointments_total']) * 100) : 0;
    
    $analytics['cancellation_rate'] = $analytics['appointments_total'] > 0 ? 
        round(($analytics['cancelled_appointments'] / $analytics['appointments_total']) * 100) : 0;
    
    $analytics['missed_rate'] = $analytics['appointments_total'] > 0 ? 
        round(($analytics['missed_appointments'] / $analytics['appointments_total']) * 100) : 0;
    
    // Get unapproved users with pagination and automatically check ID type
    $usersPerPage = 5;
    $currentPage = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;
    $offset = ($currentPage - 1) * $usersPerPage;
    
    // Get total count for pagination
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $totalUnapprovedUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUnapprovedUsers / $usersPerPage);
    
    // Get paginated unapproved users
    $stmt = $pdo->prepare("
        SELECT *, 
               CASE 
                   WHEN id_image_path IS NOT NULL AND id_image_path != '' THEN 
                       CASE 
                           WHEN id_image_path LIKE 'http%' THEN id_image_path
                           WHEN id_image_path LIKE '/%' THEN id_image_path
                           ELSE CONCAT('../', id_image_path)
                       END
                   ELSE NULL 
               END as display_image_path
        FROM sitio1_users 
        WHERE approved = FALSE AND (status IS NULL OR status != 'declined') 
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $usersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $unapprovedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Automatically check ID type for each user
    foreach ($unapprovedUsers as &$user) {
        if (!empty($user['id_image_path'])) {
            $user['id_type'] = checkIdType($user['id_image_path']);
            $user['is_valid_id'] = isIdValidForVerification($user['id_type']);
        } else {
            $user['id_type'] = 'No ID Uploaded';
            $user['is_valid_id'] = false;
        }
    }
    unset($user); // Break the reference
    
} catch (PDOException $e) {
    $error = 'Error fetching data: ' . $e->getMessage();
    // Initialize analytics array with default values on error
    $analytics = [
        'appointments_total' => 0,
        'completed_appointments' => 0,
        'cancelled_appointments' => 0,
        'missed_appointments' => 0,
        'total_patients' => 0,
        'approved_patients' => 0,
        'regular_patients' => 0,
        'appointment_status' => [],
        'monthly_trend' => [],
        'patient_registration_trend' => [],
        'completion_rate' => 0,
        'cancellation_rate' => 0,
        'missed_rate' => 0
    ];
}

// Pagination configuration
$recordsPerPage = 5;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/asssets/css/normalize.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Modal Styles - Centered and Consistent */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
            position: relative;
            margin: auto;
        }
        
        .modal-overlay.active .modal-container {
            transform: translateY(0);
            opacity: 1;
        }
        
        .modal-header {
            padding: 24px 24px 0 24px;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 0 24px 24px 24px;
        }
        
        /* Action Modal - Centered Success/Error Messages */
        .action-modal {
            max-width: 400px;
        }
        
        .action-modal .modal-body {
            text-align: center;
            padding: 40px 24px;
        }
        
        .action-modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }
        
        .action-modal-success .action-modal-icon {
            background: #d1fae5;
            color: #059669;
        }
        
        .action-modal-error .action-modal-icon {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .action-modal-info .action-modal-icon {
            background: #dbeafe;
            color: #3b82f6;
        }
        
        .action-modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .action-modal-message {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        /* Other styles remain the same as before */
        .fixed {
            position: fixed;
        }
        .inset-0 {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
        }
        .hidden {
            display: none;
        }
        .z-50 {
            z-index: 50;
        }
        .tab-active {
            border-bottom: 2px solid #3C96E1;
            color: #3C96E1;
        }
        .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.8rem;
            height: 1.8rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0 0.6rem;
            margin-left: 0.5rem;
        }
        .action-button {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }
        .action-button:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
        }
        .action-button:active {
            transform: translateY(-1px) !important;
        }
        
        /* Button disabled state */
        .button-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
            background-color: #9ca3af !important;
        }
        .button-disabled:hover {
            transform: none !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }

        /* Updated button styles for Edit and Delete */
        .btn-edit {
            background-color: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-edit:hover {
            background-color: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        .btn-delete {
            background-color: #ef4444 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3) !important;
        }

        .btn-delete:hover {
            background-color: #dc2626 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4) !important;
        }

        /* Updated button style for View Details */
        .btn-view-details {
            background-color: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 16px 24px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-view-details:hover {
            background-color: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 8px;
        }

        .pagination-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            border-radius: 9999px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            text-decoration: none;
        }

        .pagination-button:hover {
            background: #3C96E1;
            color: white;
            border-color: #3C96E1;
            transform: translateY(-1px);
        }

        .pagination-button.active {
            background: #3C96E1;
            color: white;
            border-color: #3C96E1;
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f3f4f6;
            color: #9ca3af;
        }

        .pagination-button.disabled:hover {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #d1d5db;
            transform: none;
        }

        /* Enhanced Tab Button Styles with Blue Theme */
        .nav-tab-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-right: 12px;
            margin-bottom: 8px;
            background: #3C96E1;
            color: white;
        }

        .nav-tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3);
            background: #2a7bc8;
        }

        .nav-tab-button.active {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
        }

        .nav-tab-button i {
            margin-right: 8px;
            font-size: 18px;
        }

        .nav-tab-button .count-badge {
            margin-left: 8px;
            font-size: 0.8rem;
            min-width: 1.6rem;
            height: 1.6rem;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .nav-tab-button.active .count-badge {
            background: #3C96E1;
            color: white;
        }

        /* Account Approvals Tab Button */
        .tab-account-management {
            background: #3C96E1;
            color: white;
        }

        .tab-account-management:hover {
            background: #2a7bc8;
        }

        .tab-account-management.active {
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
        }

        /* Analytics Dashboard Tab Button */
        .tab-analytics {
            background: #3C96E1;
            color: white;
        }

        .tab-analytics:hover {
            background: #2a7bc8;
        }

        .tab-analytics.active {
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
        }

        /* Blue action buttons */
        .btn-blue {
            background: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-blue:hover {
            background: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        .btn-blue:active {
            transform: translateY(-1px) !important;
        }

        /* New Approve and Decline Button Styles with White Background */
        .btn-approve-white {
            background: white !important;
            color: #3C96E1 !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: 2px solid #3C96E1 !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.1) !important;
        }

        .btn-approve-white:hover {
            background: #3C96E1 !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-decline-white {
            background: white !important;
            color: #EF4444 !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: 2px solid #EF4444 !important;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1) !important;
        }

        .btn-decline-white:hover {
            background: #EF4444 !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3) !important;
        }

        /* Success button in blue theme */
        .btn-success-blue {
            background: #48BB78 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(72, 187, 120, 0.3) !important;
        }

        .btn-success-blue:hover {
            background: #38A169 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(72, 187, 120, 0.4) !important;
        }

        /* Warning button in blue theme */
        .btn-warning-blue {
            background: #ED8936 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(237, 137, 54, 0.3) !important;
        }

        .btn-warning-blue:hover {
            background: #DD6B20 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(237, 137, 54, 0.4) !important;
        }

        /* Danger button in blue theme */
        .btn-danger-blue {
            background: #F56565 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(245, 101, 101, 0.3) !important;
        }

        .btn-danger-blue:hover {
            background: #E53E3E !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(245, 101, 101, 0.4) !important;
        }

        /* Enhanced Chart Container Styles */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            height: 400px;
            position: relative;
            overflow: hidden;
        }

        .chart-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 8px;
            color: #3C96E1;
        }

        /* Analytics grid layout improvements */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Chart canvas responsive sizing */
        .chart-container canvas {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
        }

        /* Analytics grid layout */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .analytics-value {
            font-size: 32px;
            font-weight: 700;
            color: #3C96E1;
            margin: 8px 0;
        }

        .analytics-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .analytics-trend {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .trend-up {
            background: #dcfce7;
            color: #166534;
        }

        .trend-down {
            background: #fecaca;
            color: #991b1b;
        }

        .trend-neutral {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        /* Improved user details modal layout */
        .info-label {
            font-weight: 700 !important;
            color: #374151 !important;
            font-size: 14px !important;
        }
        
        .info-value {
            font-weight: 500 !important;
            color: #4b5563 !important;
            font-size: 14px !important;
        }
        
        /* AJAX Loader */
        .ajax-loader {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3C96E1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* User details modal specific styles */
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .detail-section h4 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            flex: 1;
        }
        
        .detail-value {
            color: #2d3748;
            flex: 2;
            text-align: right;
        }
        
        .id-preview-container {
            max-height: 200px;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .id-preview-container img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        
        /* Chart responsive sizing */
        #monthlyActivityChart,
        #patientRegistrationChart,
        #serviceCompletionChart {
            display: block !important;
            width: 100% !important;
            height: 300px !important;
            min-height: 300px !important;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 300px;
            min-height: 300px;
        }

        .chart-container canvas {
            display: block !important;
            width: 100% !important;
            height: 300px !important;
            min-height: 300px !important;
        }

        /* Help modal styles */
        .help-icon {
            background: none;
            border: none;
            cursor: pointer;
        }

        .help-icon:hover {
            opacity: 0.8;
        }

        /* Modal styles for help modal */
        .modal-container.max-w-4xl {
            max-width: 800px;
        }
    </style>
</head>
<body class="bg-gray-100">

<!-- AJAX Loader -->
<div id="ajaxLoader" class="ajax-loader">
    <div class="spinner"></div>
    <p>Loading analytics...</p>
</div>

<div class="container mx-auto px-4 py-6">
    <!-- Dashboard Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            Admin Dashboard
        </h1>
        <!-- Help Button -->
        <button onclick="openHelpModal()" class="help-icon p-2 rounded-full transition">
            <i class="fa-solid fa-circle-info text-4xl text-blue-600"></i>
        </button>
    </div>

    <!-- Help/Guide Modal -->
<div id="helpModal" class="modal-overlay hidden">
    <div class="modal-container max-w-4xl">
        <!-- Header -->
        <div class="modal-header bg-blue-600 text-white p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-2xl font-bold mb-2">Staff Dashboard Guide</h3>
                    <p class="text-blue-100">Barangay Luz Health Center • User Manual</p>
                </div>
                <button onclick="closeHelpModal()" class="text-white hover:text-blue-200 p-2 rounded-full hover:bg-white/10 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Body -->
        <div class="modal-body bg-white">
            <!-- Welcome Message -->
            <div class="p-6 border-b border-gray-100">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-5 rounded">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-blue-600 text-xl mt-0.5 flex-shrink-0"></i>
                        <div>
                            <p class="text-gray-800"><strong>Welcome to the Community Health Tracker Staff Dashboard!</strong> This guide will help you understand how to use all the features available to you as a staff member.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Guide Sections -->
            <div class="p-6 space-y=6">
                <!-- Account Approvals -->
                <div class="border border-gray-200 rounded-lg p-6 hover:border-blue-300 transition">
                    <div class="flex items-center mb-4 gap-4">
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-lg flex-shrink-0">
                            <i class="fas fa-user-check text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800 mb-1">Account Approvals</h4>
                            <p class="text-gray-600">Review and approve new patient registrations for system access.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Verify details</span>
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Set access levels</span>
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Send notifications</span>
                    </div>
                </div>
                
                <!-- Analytics Dashboard -->
                <div class="border border-gray-200 rounded-lg p-6 hover:border-blue-300 transition">
                    <div class="flex items-center mb-4 gap-4">
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-lg flex-shrink-0">
                            <i class="fas fa-chart-bar text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800 mb-1">Analytics Dashboard</h4>
                            <p class="text-gray-600">View comprehensive analytics and insights about patients, appointments, consultations, and system usage.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Patient statistics</span>
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Service trends</span>
                        <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-sm">✓ Usage reports</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="modal-footer bg-gray-50 border-t border-gray-200 p-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2 text-gray-600 text-sm">
                    <i class="fas fa-question-circle text-blue-500"></i>
                    <span>Need assistance? Contact support@brgyluzcebucity.com</span>
                </div>
                <button onclick="closeHelpModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition w-full sm:w-auto">
                    Got It, Continue Working
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-user-injured text-2xl text-blue-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Your Patients</h3>
                    <p class="text-3xl font-bold text-blue-600"><?= $stats['total_patients'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-file-medical text-2xl text-yellow-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Consultations</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?= $stats['consultations'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-calendar-check text-2xl text-green-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Services</h3>
                    <p class="text-3xl font-bold text-green-600"><?= number_format($analytics['appointments_total']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-user-clock text-2xl text-red-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Unapproved Users</h3>
                    <p class="text-3xl font-bold text-red-600"><?= $stats['unapproved_users'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mb-6">
        <div class="flex flex-wrap items-center" id="dashboardTabs" role="tablist">
            <button class="nav-tab-button tab-analytics <?= $activeTab === 'analytics' ? 'active' : '' ?>" 
                    id="analytics-tab" data-tabs-target="#analytics" type="button" role="tab" aria-controls="analytics" aria-selected="<?= $activeTab === 'analytics' ? 'true' : 'false' ?>">
                <i class="fas fa-chart-bar"></i>
                Analytics Dashboard
            </button>
            
            <button class="nav-tab-button tab-account-management <?= $activeTab === 'account-management' ? 'active' : '' ?>" 
                    id="account-tab" data-tabs-target="#account-management" type="button" role="tab" aria-controls="account-management" aria-selected="<?= $activeTab === 'account-management' ? 'true' : 'false' ?>">
                <i class="fas fa-user-check"></i>
                Account Approvals
                <span class="count-badge"><?= $stats['unapproved_users'] ?></span>
            </button>
        </div>
    </div>
    
    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Account Management Section -->
        <div class="<?= $activeTab === 'account-management' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="account-management" role="tabpanel" aria-labelledby="account-tab">
            <h2 class="text-xl font-semibold mb-4 text-blue-700">Patient Account Approvals</h2>
            
            <?php if (empty($unapprovedUsers)): ?>
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-gray-600">No pending patient approvals.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Full Name</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date Registered</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">ID Status</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unapprovedUsers as $user): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['username']) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <?php if (!empty($user['id_type'])): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $user['is_valid_id'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $user['is_valid_id'] ? 'Valid ID' : 'Invalid ID' ?>
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1"><?= $user['id_type'] ?></div>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                No ID Uploaded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <button onclick="openUserDetailsModal(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                                class="btn-view-details">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination mt-6">
                        <!-- Previous Button -->
                        <?php if ($currentPage > 1): ?>
                            <a href="?tab=account-management&user_page=<?= $currentPage - 1 ?>" class="pagination-button">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-button disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="pagination-button active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?tab=account-management&user_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?tab=account-management&user_page=<?= $currentPage + 1 ?>" class="pagination-button">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-button disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Analytics Dashboard Section -->
        <div class="<?= $activeTab === 'analytics' ? '' : 'hidden' ?> p-6 bg-white rounded-lg border border-gray-200" id="analytics" role="tabpanel" aria-labelledby="analytics-tab">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold mb-6 text-blue-700">Analytics Dashboard</h2>
                <button onclick="refreshAnalytics()" class="btn-blue flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Data
                </button>
            </div>
            
            <!-- Overview Cards -->
            <div id="analyticsCards" class="analytics-grid mb-8">
                <div class="analytics-card">
                    <div class="flex items-center mb-3">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <i class="fas fa-user-injured text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                    </div>
                    <div class="analytics-value"><?= number_format($analytics['total_patients']) ?></div>
                    <div class="analytics-label">Registered in the system</div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Approved Patients</span>
                            <span class="text-sm font-semibold text-green-600"><?= number_format($analytics['approved_patients']) ?></span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm text-gray-500">Regular Patients</span>
                            <span class="text-sm font-semibold text-blue-600"><?= number_format($analytics['regular_patients']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="flex items-center mb-3">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Services</h3>
                    </div>
                    <div class="analytics-value"><?= number_format($analytics['appointments_total']) ?></div>
                    <div class="analytics-label">Total services processed</div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Completed</span>
                            <span class="text-sm font-semibold text-green-600">
                                <?= number_format($analytics['completed_appointments']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm text-gray-500">Consultations</span>
                            <span class="text-sm font-semibold text-blue-600">
                                <?= number_format($stats['consultations']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="flex items-center mb-3">
                        <div class="p-2 bg-purple-100 rounded-lg mr-3">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Monthly Activity</h3>
                    </div>
                    <div class="analytics-value">
                        <?php 
                            $lastMonthCount = 0;
                            if (!empty($analytics['monthly_trend'])) {
                                $lastMonth = end($analytics['monthly_trend']);
                                $lastMonthCount = $lastMonth['count'];
                            }
                            echo number_format($lastMonthCount);
                        ?>
                    </div>
                    <div class="analytics-label">Services last month</div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">6-month avg</span>
                            <span class="text-sm font-semibold text-purple-600">
                                <?php 
                                    $avg = !empty($analytics['monthly_trend']) ? 
                                        array_sum(array_map(function($item) { return $item['count']; }, $analytics['monthly_trend'])) / count($analytics['monthly_trend']) : 0;
                                    echo number_format($avg, 1);
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm text-gray-500">Peak month</span>
                            <span class="text-sm font-semibold text-orange-600">
                                <?php 
                                    $peak = 0;
                                    if (!empty($analytics['monthly_trend'])) {
                                        $peak = max(array_map(function($item) { return $item['count']; }, $analytics['monthly_trend']));
                                    }
                                    echo number_format($peak);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="flex items-center mb-3">
                        <div class="p-2 bg-orange-100 rounded-lg mr-3">
                            <i class="fas fa-tachometer-alt text-orange-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Service Completion</h3>
                    </div>
                    <div class="analytics-value">
                        <?= $analytics['completion_rate'] ?>%
                    </div>
                    <div class="analytics-label">Services successfully completed</div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Cancellation Rate</span>
                            <span class="text-sm font-semibold text-red-600">
                                <?= $analytics['cancellation_rate'] ?>%
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm text-gray-500">No-show Rate</span>
                            <span class="text-sm font-semibold text-yellow-600">
                                <?= $analytics['missed_rate'] ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Grid - REMOVED CONSULTATION TYPE CHART -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Activity Trend -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Monthly Activity Trend
                    </h3>
                    <div class="chart-wrapper">
                        <canvas id="monthlyActivityChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Patient Registration Trend -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-user-plus"></i>
                        Patient Registration Trend
                    </h3>
                    <div class="chart-wrapper">
                        <canvas id="patientRegistrationChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Service Completion Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-tasks"></i>
                        Service Performance
                    </h3>
                    <div class="chart-wrapper">
                        <canvas id="serviceCompletionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Action Success/Error Modals -->
<div id="successModal" class="modal-overlay hidden">
    <div class="modal-container action-modal action-modal-success">
        <div class="modal-body">
            <div class="action-modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="action-modal-title" id="successModalTitle">Success</h3>
            <div class="action-modal-message" id="successModalMessage"></div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center">
                <button type="button" onclick="closeSuccessModal()" 
                        class="px-8 py-3 bg-green-600 text-white rounded-full hover:bg-green-700 transition font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<div id="errorModal" class="modal-overlay hidden">
    <div class="modal-container action-modal action-modal-error">
        <div class="modal-body">
            <div class="action-modal-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h3 class="action-modal-title" id="errorModalTitle">Error</h3>
            <div class="action-modal-message" id="errorModalMessage"></div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center">
                <button type="button" onclick="closeErrorModal()" 
                        class="px-8 py-3 bg-red-600 text-white rounded-full hover:bg-red-700 transition font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="modal-overlay hidden">
    <div class="modal-container modal-desktop">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-900">Patient Registration Details</h3>
                <button type="button" onclick="closeUserDetailsModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="modal-body">
            <div class="horizontal-user-details">
                <!-- Personal Information -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                        <i class="fas fa-user mr-2"></i> Personal Information
                    </h4>
                    <div class="user-details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Full Name:</span>
                            <span class="detail-value" id="userFullName">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Username:</span>
                            <span class="detail-value" id="userUsername">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value" id="userEmail">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact Number:</span>
                            <span class="detail-value" id="userContact">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value" id="userGender">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age:</span>
                            <span class="detail-value" id="userAge">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Civil Status:</span>
                            <span class="detail-value" id="userCivilStatus">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Occupation:</span>
                            <span class="detail-value" id="userOccupation">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date of Birth:</span>
                            <span class="detail-value" id="userDateOfBirth">N/A</span>
                        </div>
                    </div>
                </div>

                <!-- Address & Account Information -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                        <i class="fas fa-map-marker-alt mr-2"></i> Address Information
                    </h4>
                    <div class="user-details-grid">
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="detail-label">Complete Address:</span>
                            <span class="detail-value" id="userAddress">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sitio:</span>
                            <span class="detail-value" id="userSitio">N/A</span>
                        </div>
                    </div>

                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4 mt-6">
                        <i class="fas fa-user-circle mr-2"></i> Account Information
                    </h4>
                    <div class="user-details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Account Status:</span>
                            <span class="detail-value" id="userStatus">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Approved:</span>
                            <span class="detail-value" id="userApproved">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">User Role:</span>
                            <span class="detail-value" id="userRole">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Verification Method:</span>
                            <span class="detail-value" id="userVerificationMethod">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Verified:</span>
                            <span class="detail-value" id="userIdVerified">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Verification Consent:</span>
                            <span class="detail-value" id="userVerificationConsent">N/A</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ID Verification Section -->
            <div class="detail-section mt-6">
                <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                    <i class="fas fa-id-card mr-2"></i> ID Verification
                </h4>
                <div class="user-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">ID Type:</span>
                        <span class="detail-value" id="userIdType">N/A</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ID Status:</span>
                        <span class="detail-value" id="userIdValidationStatus">N/A</span>
                    </div>
                </div>

                <!-- ID Image -->
                <div class="mt-6">
                    <h5 class="font-semibold text-gray-700 mb-3">ID Document</h5>
                    
                    <div id="idImageSection" class="hidden">
                        <div class="id-preview-container">
                            <img id="userIdImage" src="" alt="ID Image" class="w-full h-auto">
                        </div>
                        
                        <div class="flex gap-3 mt-3">
                            <a id="userIdImageLink" href="#" target="_blank" 
                               class="btn-blue">
                                <i class="fas fa-external-link-alt mr-2"></i> View Original
                            </a>
                            <button onclick="openImageModal()" class="btn-blue">
                                <i class="fas fa-search mr-2"></i> Zoom Preview
                            </button>
                        </div>
                    </div>
                    
                    <div id="noIdImageSection" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                            <span class="text-yellow-700">No ID image uploaded</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline & Notes -->
            <div class="detail-section mt-6">
                <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                    <i class="fas fa-history mr-2"></i> Registration Timeline
                </h4>
                <div class="user-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Registered Date:</span>
                        <span class="detail-value" id="userRegisteredDate">N/A</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Updated:</span>
                        <span class="detail-value" id="userUpdatedDate">N/A</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Verified At:</span>
                        <span class="detail-value" id="userVerifiedAt">N/A</span>
                    </div>
                </div>

                <div id="verificationNotesSection" class="hidden mt-6">
                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                        <i class="fas fa-sticky-note mr-2"></i> Verification Notes
                    </h4>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-gray-700" id="userVerificationNotes">No verification notes available.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <div class="flex justify-end space-x-3">
                <button onclick="openApproveConfirmationModal()" class="btn-success-blue">
                    <i class="fas fa-check mr-2"></i> Approve Registration
                </button>
                <button onclick="openDeclineModalFromDetails()" class="btn-danger-blue">
                    <i class="fas fa-times mr-2"></i> Decline Registration
                </button>
                <button type="button" onclick="closeUserDetailsModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div id="approveConfirmationModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-body">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirm Resident Account Approval</h3>
            <p class="text-gray-600 text-center mb-4">
                Are you sure you want to approve this user account? This action will generate a unique patient number and grant full system access.
            </p>
            <div class="bg-blue-50 p-3 rounded-lg mb-6">
                <p class="text-sm text-blue-700 font-medium text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    An approval email with the unique patient number will be sent to the user.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center space-x-3">
                <button type="button" onclick="closeApproveConfirmationModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <form method="POST" action="" class="inline" id="finalApproveForm">
                    <input type="hidden" name="user_id" id="finalApproveUserId">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" name="approve_user" class="btn-success-blue">
                        <i class="fas fa-check mr-1"></i> Confirm Approval
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Decline Modal -->
<div id="declineModal" class="modal-overlay hidden">
    <div class="modal-container max-w-2xl">
        <div class="modal-header">
            <div class="flex items-center mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 text-center mb-2">Decline User Account</h3>
            <p class="text-gray-600 text-center">Please provide a reason for declining this user registration.</p>
        </div>
        
        <div class="modal-body">
            <form id="declineForm" method="POST" action="">
                <input type="hidden" name="user_id" id="declineUserId">
                <input type="hidden" name="action" value="decline">
                
                <div class="mb-6">
                    <label for="decline_reason" class="block text-gray-700 text-sm font-semibold mb-3">Reason for Declination *</label>
                    <textarea id="decline_reason" name="decline_reason" rows="6" 
                              class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                              placeholder="Please provide a detailed reason for declining this user account. This will be included in the notification email sent to the user..."
                              required></textarea>
                    <p class="text-xs text-gray-500 mt-2">This reason will be sent to the user via email.</p>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-semibold text-red-800">Important Notice</h4>
                            <p class="text-sm text-red-700 mt-1">
                                Declining this account will prevent the user from accessing the system. They will receive an email notification with the reason provided above.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeclineModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel
                </button>
                <button type="submit" form="declineForm" name="approve_user" class="btn-danger-blue">
                    <i class="fas fa-ban mr-2"></i> Confirm Decline
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Zoom Modal -->
<div id="imageModal" class="modal-overlay hidden">
    <div class="modal-container max-w-4xl">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">ID Document Preview</h3>
            <button type="button" onclick="closeImageModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="flex justify-center">
                <img id="zoomedUserIdImage" src="" alt="Zoomed ID Image" class="max-w-full h-auto rounded-lg">
            </div>
        </div>
        <div class="modal-footer">
            <div class="text-center">
                <a id="zoomedUserIdImageLink" href="#" target="_blank" 
                   class="btn-blue">
                    <i class="fas fa-external-link-alt mr-2"></i> Open in New Tab
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Global variable to store current user ID
let currentUserDetailsId = null;

// Enhanced Modal Management Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Enhanced Success/Error Modal Functions
function showSuccessModal(message) {
    document.getElementById('successModalTitle').textContent = 'Success';
    document.getElementById('successModalMessage').innerHTML = message;
    openModal('successModal');
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        closeSuccessModal();
    }, 5000);
}

function closeSuccessModal() {
    closeModal('successModal');
}

function showErrorModal(message) {
    document.getElementById('errorModalTitle').textContent = 'Error';
    document.getElementById('errorModalMessage').textContent = message;
    openModal('errorModal');
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        closeErrorModal();
    }, 5000);
}

function closeErrorModal() {
    closeModal('errorModal');
}

// User Details Modal functions
function openUserDetailsModal(user) {
    console.log('User data for modal:', user);
    
    currentUserDetailsId = user.id;
    
    // Set user data in the modal
    document.getElementById('userFullName').textContent = user.full_name || 'N/A';
    document.getElementById('userUsername').textContent = user.username || 'N/A';
    document.getElementById('userEmail').textContent = user.email || 'N/A';
    document.getElementById('userGender').textContent = user.gender ? user.gender.charAt(0).toUpperCase() + user.gender.slice(1) : 'N/A';
    document.getElementById('userAge').textContent = user.age || 'N/A';
    document.getElementById('userContact').textContent = user.contact || 'N/A';
    document.getElementById('userCivilStatus').textContent = user.civil_status || 'N/A';
    document.getElementById('userOccupation').textContent = user.occupation || 'N/A';
    document.getElementById('userDateOfBirth').textContent = user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
    
    document.getElementById('userAddress').textContent = user.address || 'N/A';
    document.getElementById('userSitio').textContent = user.sitio || 'N/A';
    
    const verificationMethod = user.verification_method ? 
        user.verification_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
    document.getElementById('userVerificationMethod').textContent = verificationMethod;
    
    const idVerifiedElement = document.getElementById('userIdVerified');
    if (user.id_verified === 1 || user.id_verified === true) {
        idVerifiedElement.textContent = 'Yes';
        idVerifiedElement.className = 'detail-value text-green-600';
    } else {
        idVerifiedElement.textContent = 'No';
        idVerifiedElement.className = 'detail-value text-red-600';
    }
    
    const consentElement = document.getElementById('userVerificationConsent');
    if (user.verification_consent === 1 || user.verification_consent === true) {
        consentElement.textContent = 'Yes';
        consentElement.className = 'detail-value text-green-600';
    } else {
        consentElement.textContent = 'No';
        consentElement.className = 'detail-value text-red-600';
    }
    
    // Handle ID image
    const idImageSection = document.getElementById('idImageSection');
    const noIdImageSection = document.getElementById('noIdImageSection');
    const idImage = document.getElementById('userIdImage');
    const idImageLink = document.getElementById('userIdImageLink');
    const zoomedIdImage = document.getElementById('zoomedUserIdImage');
    const zoomedIdImageLink = document.getElementById('zoomedUserIdImageLink');
    
    const imagePath = user.display_image_path || user.id_image_path;
    
    if (imagePath && imagePath.trim() !== '') {
        console.log('Displaying ID image from path:', imagePath);
        
        const testImage = new Image();
        testImage.onload = function() {
            idImage.src = imagePath;
            zoomedIdImage.src = imagePath;
            idImageLink.href = imagePath;
            zoomedIdImageLink.href = imagePath;
            
            idImageSection.classList.remove('hidden');
            noIdImageSection.classList.add('hidden');
        };
        
        testImage.onerror = function() {
            console.error('Failed to load ID image:', imagePath);
            idImageSection.classList.add('hidden');
            noIdImageSection.classList.remove('hidden');
        };
        
        testImage.src = imagePath;
        
    } else {
        console.log('No ID image available');
        idImageSection.classList.add('hidden');
        noIdImageSection.classList.remove('hidden');
    }
    
    // Display ID type and validation status
    const idTypeElement = document.getElementById('userIdType');
    const idValidationStatus = document.getElementById('userIdValidationStatus');
    
    if (user.id_type) {
        idTypeElement.textContent = user.id_type;
        
        const isValidId = user.is_valid_id;
        if (isValidId) {
            idValidationStatus.textContent = 'Valid ID';
            idValidationStatus.className = 'detail-value text-green-600';
        } else {
            idValidationStatus.textContent = 'Invalid ID Type';
            idValidationStatus.className = 'detail-value text-red-600';
        }
    } else {
        idTypeElement.textContent = 'No ID Uploaded';
        idValidationStatus.textContent = 'No ID Found';
        idValidationStatus.className = 'detail-value text-gray-600';
    }
    
    // Handle verification notes
    const verificationNotesSection = document.getElementById('verificationNotesSection');
    const verificationNotes = document.getElementById('userVerificationNotes');
    if (user.verification_notes && user.verification_notes.trim() !== '') {
        verificationNotesSection.classList.remove('hidden');
        verificationNotes.textContent = user.verification_notes;
    } else {
        verificationNotesSection.classList.add('hidden');
    }
    
    // Registration details
    document.getElementById('userRegisteredDate').textContent = user.created_at ? 
        new Date(user.created_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
    
    document.getElementById('userUpdatedDate').textContent = user.updated_at ? 
        new Date(user.updated_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
    
    document.getElementById('userVerifiedAt').textContent = user.verified_at ? 
        new Date(user.verified_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'Not verified';
    
    // Status information
    const statusElement = document.getElementById('userStatus');
    statusElement.textContent = user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Pending';
    statusElement.className = 'detail-value ' + 
        (user.status === 'approved' ? 'text-green-600' :
         user.status === 'pending' ? 'text-yellow-600' :
         user.status === 'declined' ? 'text-red-600' : 'text-yellow-600');
    
    const approvedElement = document.getElementById('userApproved');
    if (user.approved === 1 || user.approved === true) {
        approvedElement.textContent = 'Yes';
        approvedElement.className = 'detail-value text-green-600';
    } else {
        approvedElement.textContent = 'No';
        approvedElement.className = 'detail-value text-red-600';
    }
    
    document.getElementById('userRole').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'Patient';
    
    openModal('userDetailsModal');
}

function closeUserDetailsModal() {
    closeModal('userDetailsModal');
}

// Approve Confirmation Modal functions
function openApproveConfirmationModal() {
    document.getElementById('finalApproveUserId').value = currentUserDetailsId;
    openModal('approveConfirmationModal');
}

function closeApproveConfirmationModal() {
    closeModal('approveConfirmationModal');
}

// Decline Modal functions
function openDeclineModalFromDetails() {
    closeUserDetailsModal();
    setTimeout(() => {
        openDeclineModal(currentUserDetailsId);
    }, 100);
}

function openDeclineModal(userId) {
    document.getElementById('declineUserId').value = userId;
    document.getElementById('decline_reason').value = '';
    openModal('declineModal');
}

function closeDeclineModal() {
    closeModal('declineModal');
}

// Image Modal functions
function openImageModal() {
    openModal('imageModal');
}

function closeImageModal() {
    closeModal('imageModal');
}

// Help modal functions
function openHelpModal() {
    openModal('helpModal');
}

function closeHelpModal() {
    closeModal('helpModal');
}

// Tab functionality
function switchTab(tabId) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-content > div').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    document.getElementById(tabId).classList.remove('hidden');
    
    document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
        tabBtn.classList.remove('active');
    });
    
    const activeTabBtn = document.querySelector(`#dashboardTabs button[data-tabs-target="#${tabId}"]`);
    activeTabBtn.classList.add('active');
    
    // Initialize charts when switching to analytics tab
    if (tabId === 'analytics') {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            initializeCharts();
        }, 100);
    }
}

// Initialize tabs and charts
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'analytics';
    
    switchTab(activeTab);
    
    document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
        tabBtn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
            switchTab(targetTab);
        });
    });
    
    // ALWAYS initialize charts if on analytics tab
    if (activeTab === 'analytics') {
        // Small delay to ensure all DOM elements are ready
        setTimeout(() => {
            initializeCharts();
        }, 200);
    }
    
    // Show success/error messages
    <?php if ($success): ?>
        showSuccessModal('<?= addslashes($success) ?>');
    <?php endif; ?>
    
    <?php if ($error): ?>
        showErrorModal('<?= addslashes($error) ?>');
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['userDetailsModal', 'approveConfirmationModal', 'declineModal', 'imageModal', 
                    'successModal', 'errorModal', 'helpModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && event.target === modal) {
            closeModal(modalId);
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal-overlay.active');
        openModals.forEach(modal => {
            const modalId = modal.id;
            closeModal(modalId);
        });
    }
});

// Refresh analytics function
function refreshAnalytics() {
    const loader = document.getElementById('ajaxLoader');
    loader.style.display = 'block';
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Chart initialization - REMOVED CONSULTATION TYPE CHART
function initializeCharts() {
    console.log('Initializing charts...');
    
    // Check if chart elements exist
    const monthlyActivityCanvas = document.getElementById('monthlyActivityChart');
    const patientRegistrationCanvas = document.getElementById('patientRegistrationChart');
    const serviceCompletionCanvas = document.getElementById('serviceCompletionChart');
    
    // Destroy existing charts if they exist
    Chart.getChart(monthlyActivityCanvas)?.destroy();
    Chart.getChart(patientRegistrationCanvas)?.destroy();
    Chart.getChart(serviceCompletionCanvas)?.destroy();
    
    // 1. Monthly Activity Trend Chart
    try {
        const monthlyActivityCtx = monthlyActivityCanvas.getContext('2d');
        const monthlyData = <?= json_encode($analytics['monthly_trend']) ?>;
        const monthlyLabels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const monthlyValues = monthlyData.map(item => item.count);
        
        const monthlyActivityChart = new Chart(monthlyActivityCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Services',
                    data: monthlyValues,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false, color: 'rgba(229, 231, 235, 0.5)' },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
        console.log('Monthly Activity Chart initialized');
    } catch (error) {
        console.error('Error initializing monthly activity chart:', error);
    }
    
    // 2. Patient Registration Trend Chart
    try {
        const patientRegCtx = patientRegistrationCanvas.getContext('2d');
        const patientData = <?= json_encode($analytics['patient_registration_trend']) ?>;
        const patientLabels = patientData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const patientValues = patientData.map(item => item.count);
        
        const patientRegChart = new Chart(patientRegCtx, {
            type: 'line',
            data: {
                labels: patientLabels,
                datasets: [{
                    label: 'New Patients',
                    data: patientValues,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10B981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false, color: 'rgba(229, 231, 235, 0.5)' },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
        console.log('Patient Registration Chart initialized');
    } catch (error) {
        console.error('Error initializing patient registration chart:', error);
    }
    
    // 3. Service Completion Chart
    try {
        const serviceCompletionCtx = serviceCompletionCanvas.getContext('2d');
        const totalServices = <?= $analytics['appointments_total'] ?>;
        const completedServices = <?= $analytics['completed_appointments'] ?>;
        const completionRate = <?= $analytics['completion_rate'] ?>;
        
        // Create service completion chart
        const serviceCompletionChart = new Chart(serviceCompletionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{
                    data: [completionRate, 100 - completionRate],
                    backgroundColor: ['#10B981', '#E5E7EB'],
                    borderWidth: 0,
                    hoverBackgroundColor: ['#059669', '#D1D5DB']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
        
        // Add completion rate text overlay
        const chartContainer = serviceCompletionCanvas.parentElement;
        chartContainer.style.position = 'relative';
        
        // Remove existing text overlay if it exists
        const existingOverlay = chartContainer.querySelector('.chart-overlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }
        
        // Create new overlay
        const overlay = document.createElement('div');
        overlay.className = 'chart-overlay';
        overlay.style.position = 'absolute';
        overlay.style.top = '50%';
        overlay.style.left = '50%';
        overlay.style.transform = 'translate(-50%, -50%)';
        overlay.style.textAlign = 'center';
        overlay.innerHTML = `
            <div class="text-3xl font-bold text-gray-900">${completionRate}%</div>
            <div class="text-sm text-gray-600 mt-1">Completion Rate</div>
            <div class="text-xs text-gray-400 mt-2">${completedServices} of ${totalServices}</div>
        `;
        chartContainer.appendChild(overlay);
        
        console.log('Service Completion Chart initialized');
    } catch (error) {
        console.error('Error initializing service completion chart:', error);
    }
}
</script>
</body>
</html>