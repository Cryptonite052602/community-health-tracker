<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];

// Get user information
$userInfo = [];
try {
    $stmt = $pdo->prepare("SELECT email, full_name, date_of_birth, created_at FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = 'Error fetching user information: ' . $e->getMessage();
}

// Use database values as primary source
$userEmail = $userInfo['email'] ?? $_SESSION['user']['email'] ?? 'Not provided';
$userFullName = $userInfo['full_name'] ?? $_SESSION['user']['full_name'] ?? 'Not provided';
$userDateOfBirth = $userInfo['date_of_birth'] ?? $_SESSION['user']['date_of_birth'] ?? null;
$userCreatedAt = $userInfo['created_at'] ?? $_SESSION['user']['created_at'] ?? date('Y-m-d');
$error = '';
$success = '';

// Get ALL patient info linked to this user from sitio1_patients
$allPatientInfo = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.user_id,
            sp.phic_no,
            sp.bhw_assigned,
            sp.family_no,
            sp.fourps_member,
            sp.full_name,
            sp.date_of_birth,
            sp.age,
            sp.address,
            sp.sitio,
            sp.disease,
            sp.contact,
            sp.last_checkup,
            sp.medical_history as patient_medical_history,
            sp.added_by,
            sp.created_at,
            sp.deleted_at,
            sp.restored_at,
            sp.gender as patient_gender,
            sp.consultation_type,
            sp.civil_status,
            sp.occupation,
            sp.consent_given,
            sp.consent_date,
            eip.height,
            eip.weight,
            eip.blood_type,
            eip.allergies,
            eip.medical_history as existing_medical_history,
            eip.current_medications,
            eip.family_history,
            eip.temperature,
            eip.blood_pressure,
            eip.immunization_record,
            eip.chronic_conditions
        FROM sitio1_patients sp
        LEFT JOIN existing_info_patients eip ON sp.id = eip.patient_id
        WHERE sp.user_id = ? 
        AND sp.deleted_at IS NULL
        ORDER BY sp.full_name ASC
    ");
    $stmt->execute([$userId]);
    $allPatientInfo = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = 'Error fetching patient information: ' . $e->getMessage();
}

// Get consultation notes/visits for ALL patients linked to this user
$allConsultationNotes = [];
try {
    if (!empty($allPatientInfo)) {
        $patientIds = array_column($allPatientInfo, 'id');
        $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT 
                cn.*,
                s.full_name as doctor_name
            FROM consultation_notes cn
            LEFT JOIN sitio1_staff s ON cn.created_by = s.id
            WHERE cn.patient_id IN ($placeholders)
            ORDER BY cn.consultation_date DESC
        ");
        $stmt->execute($patientIds);
        $allConsultationNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Not critical if this fails - consultation notes table might not exist
}

// Group consultation notes by patient for display
$consultationNotesByPatient = [];
foreach ($allConsultationNotes as $note) {
    $patientId = $note['patient_id'];
    if (!isset($consultationNotesByPatient[$patientId])) {
        // Find patient info
        $patientInfo = null;
        foreach ($allPatientInfo as $patient) {
            if ($patient['id'] == $patientId) {
                $patientInfo = $patient;
                break;
            }
        }
        $consultationNotesByPatient[$patientId] = [
            'patient_info' => $patientInfo,
            'notes' => []
        ];
    }
    $consultationNotesByPatient[$patientId]['notes'][] = $note;
}

// Get total count of consultation notes
$totalConsultationNotes = count($allConsultationNotes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Health Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --warm-blue: #3b82f6;
            --warm-blue-light: #dbeafe;
            --warm-blue-dark: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --border-light: #e5e7eb;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .btn-primary {
            background-color: white;
            border: 2px solid var(--warm-blue);
            color: var(--warm-blue);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--warm-blue-light);
            transform: translateY(-2px);
        }

        .nav-pill {
            background-color: white;
            border: 2px solid var(--warm-blue);
            color: var(--warm-blue);
            transition: all 0.3s ease;
        }

        .nav-pill.active {
            background-color: var(--warm-blue);
            color: white;
        }

        .nav-pill:hover:not(.active) {
            background-color: var(--warm-blue-light);
        }

        .info-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
        }

        .consultation-note-item {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--warm-blue);
        }

        .patient-section {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .patient-header {
            background: linear-gradient(135deg, var(--warm-blue-light) 0%, #e0f2fe 100%);
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-light);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .section-title {
            position: relative;
            padding-left: 1rem;
        }

        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background: var(--warm-blue);
            border-radius: 4px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .patient-badge {
            background-color: var(--warm-blue-light);
            color: var(--warm-blue-dark);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .no-records-message {
            background-color: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
        }

        .health-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .badge-phic {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-4ps {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-bhw {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-family {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div class="mb-6 md:mb-0">
                <h1 class="text-3xl font-bold text-gray-800">My Health Profile</h1>
                <p class="text-gray-600 mt-2">View your medical history and health records</p>
            </div>
            <div class="flex items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-500 text-2xl"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-sm text-gray-500">Email: <?= htmlspecialchars($userEmail) ?></p>
                    <p class="text-sm text-gray-500">
                        Linked Patients: <?= count($allPatientInfo) ?> 
                        • Total Consultations: <?= $totalConsultationNotes ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-2xl mb-6 flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .font-poppins {
        font-family: 'Poppins', sans-serif;
    }
</style>

<div class="bg-white rounded-2xl shadow-sm p-4 mb-8 flex flex-wrap border border-gray-200 font-poppins">
    <button class="nav-pill active flex items-center mr-3 mb-2 px-4 py-2 rounded-full transition-all" data-tab="records">
        <i class="fas fa-file-medical mr-2 text-sm"></i> 
        <span class="text-sm font-medium">Health Records</span>
        <span class="bg-blue-100 text-blue-800 text-xs font-semibold ml-2 px-3 py-0.5 rounded-full">
            <?= $totalConsultationNotes ?>
        </span>
    </button>

    <button class="nav-pill flex items-center mr-3 mb-2 px-4 py-2 rounded-full transition-all" data-tab="patients">
        <i class="fas fa-users mr-2 text-sm"></i> 
        <span class="text-sm font-medium">Patient Profiles</span>
        <span class="bg-blue-100 text-blue-800 text-xs font-semibold ml-2 px-3 py-0.5 rounded-full">
            <?= count($allPatientInfo) ?>
        </span>
    </button>

    <button class="nav-pill flex items-center mr-3 mb-2 px-4 py-2 rounded-full transition-all" data-tab="medical">
        <i class="fas fa-heartbeat mr-2 text-sm"></i> 
        <span class="text-sm font-medium">Medical Info</span>
    </button>
</div>

        <!-- Health Records Tab (Consultation Notes) -->
        <div id="records" class="tab-content active">
            <?php if (empty($allPatientInfo)): ?>
                <div class="no-records-message">
                    <i class="fas fa-user-times text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Patient Records Linked</h3>
                    <p class="text-gray-500 max-w-md mx-auto mb-6">Your account is not linked to any patient records yet.</p>
                </div>
            <?php elseif (empty($allConsultationNotes)): ?>
                <div class="space-y-6">
                    <?php foreach ($allPatientInfo as $patientIndex => $patient): ?>
                        <div class="patient-section">
                            <div class="patient-header">
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center mr-4 border-2 border-blue-200">
                                            <i class="fas fa-user text-blue-500"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></h3>
                                            <div class="flex items-center mt-1 space-x-3">
                                                <?php if (!empty($patient['age'])): ?>
                                                    <span class="text-sm text-gray-600">Age: <?= htmlspecialchars($patient['age']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($patient['patient_gender'])): ?>
                                                    <span class="text-sm text-gray-600">Gender: <?= htmlspecialchars($patient['patient_gender']) ?></span>
                                                <?php endif; ?>
                                                <span class="patient-badge">No consultations yet</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Last checkup: <?= !empty($patient['last_checkup']) ? date('M d, Y', strtotime($patient['last_checkup'])) : 'Never' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="text-center py-8">
                                    <i class="fas fa-file-medical-alt text-4xl text-gray-300 mb-4"></i>
                                    <h4 class="text-lg font-medium text-gray-600 mb-2">No Consultation Records</h4>
                                    <p class="text-gray-500">No consultation notes found for <?= htmlspecialchars($patient['full_name']) ?>.</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($consultationNotesByPatient as $patientId => $patientData): 
                        $patient = $patientData['patient_info'];
                        $patientNotes = $patientData['notes'];
                    ?>
                        <div class="patient-section">
                            <div class="patient-header">
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center mr-4 border-2 border-blue-200">
                                            <i class="fas fa-user text-blue-500"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></h3>
                                            <div class="flex items-center mt-1 space-x-3">
                                                <?php if (!empty($patient['age'])): ?>
                                                    <span class="text-sm text-gray-600">Age: <?= htmlspecialchars($patient['age']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($patient['patient_gender'])): ?>
                                                    <span class="text-sm text-gray-600">Gender: <?= htmlspecialchars($patient['patient_gender']) ?></span>
                                                <?php endif; ?>
                                                <span class="patient-badge">
                                                    <?= count($patientNotes) ?> consultation<?= count($patientNotes) !== 1 ? 's' : '' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Last consultation: <?= !empty($patientNotes[0]['consultation_date']) ? date('M d, Y', strtotime($patientNotes[0]['consultation_date'])) : 'No consultations' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <?php foreach ($patientNotes as $note): ?>
                                        <div class="consultation-note-item p-6">
                                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                                <div class="flex items-center mb-4 md:mb-0">
                                                    <div class="w-10 h-10 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                                                        <i class="fas fa-calendar-check text-blue-500"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-gray-800">Consultation on <?= date('M d, Y', strtotime($note['consultation_date'] ?? 'now')) ?></h3>
                                                        <p class="text-gray-500 text-sm">
                                                            <?php if (!empty($note['doctor_name'])): ?>
                                                                With Dr. <?= htmlspecialchars($note['doctor_name']) ?>
                                                            <?php else: ?>
                                                                Consultation with healthcare staff
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <button onclick="viewConsultationNote(<?= htmlspecialchars(json_encode($note)) ?>)" 
                                                            class="btn-primary flex items-center px-4 py-2 rounded-full text-sm">
                                                        <i class="fas fa-eye mr-2"></i> View
                                                    </button>
                                                    <button onclick="printConsultationNote(<?= htmlspecialchars(json_encode($note)) ?>)" 
                                                            class="btn-primary flex items-center px-4 py-2 rounded-full text-sm">
                                                        <i class="fas fa-print mr-2"></i> Print
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="bg-gray-50 p-4 rounded-lg">
                                                <h4 class="font-semibold text-gray-700 mb-2 text-sm">Consultation Notes</h4>
                                                <p class="text-gray-700 text-sm">
                                                    <?= !empty($note['note']) 
                                                        ? (strlen($note['note']) > 200 
                                                            ? htmlspecialchars(substr($note['note'], 0, 200)) . '...' 
                                                            : htmlspecialchars($note['note'])) 
                                                        : 'No notes recorded' ?>
                                                </p>
                                                
                                                <?php if (!empty($note['next_consultation_date'])): ?>
                                                <div class="mt-3 pt-3 border-t border-gray-200">
                                                    <p class="text-sm text-green-600">
                                                        <i class="fas fa-calendar-alt mr-2"></i>
                                                        Next appointment: <?= date('M d, Y', strtotime($note['next_consultation_date'])) ?>
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Section -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-2xl border border-blue-200">
                        <p class="text-blue-600 font-semibold text-sm">Linked Patients</p>
                        <p class="text-3xl font-bold text-blue-800 mt-2"><?= count($allPatientInfo) ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-2xl border border-green-200">
                        <p class="text-green-600 font-semibold text-sm">Total Consultations</p>
                        <p class="text-3xl font-bold text-green-800 mt-2"><?= $totalConsultationNotes ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-2xl border border-purple-200">
                        <p class="text-purple-600 font-semibold text-sm">Account Status</p>
                        <p class="text-lg font-bold text-purple-800 mt-2">Active</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Patient Profiles Tab -->
        <div id="patients" class="tab-content">
            <?php if (empty($allPatientInfo)): ?>
                <div class="no-records-message">
                    <i class="fas fa-user-times text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Patient Profiles</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Your account is not linked to any patient records.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($allPatientInfo as $patientIndex => $patient): 
                        // Count consultation notes for this patient
                        $patientNoteCount = 0;
                        foreach ($allConsultationNotes as $note) {
                            if ($note['patient_id'] == $patient['id']) {
                                $patientNoteCount++;
                            }
                        }
                    ?>
                        <div class="info-card p-6">
                            <!-- Patient Header -->
                            <div class="flex items-start mb-6">
                                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-blue-500 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></h3>
                                    <div class="flex flex-wrap items-center mt-2 gap-2">
                                        <?php if (!empty($patient['age'])): ?>
                                            <span class="text-sm text-gray-600">Age: <?= htmlspecialchars($patient['age']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['patient_gender'])): ?>
                                            <span class="text-sm text-gray-600">Gender: <?= htmlspecialchars($patient['patient_gender']) ?></span>
                                        <?php endif; ?>
                                        <span class="patient-badge">
                                            <?= $patientNoteCount ?> consultation<?= $patientNoteCount !== 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Health Badges -->
                            <div class="mb-6">
                                <?php if (!empty($patient['phic_no'])): ?>
                                    <span class="health-badge badge-phic">
                                        <i class="fas fa-id-card mr-1"></i> PHIC: <?= htmlspecialchars($patient['phic_no']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['fourps_member']) && $patient['fourps_member'] == 'Yes'): ?>
                                    <span class="health-badge badge-4ps">
                                        <i class="fas fa-users mr-1"></i> 4P's Member
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['bhw_assigned'])): ?>
                                    <span class="health-badge badge-bhw">
                                        <i class="fas fa-user-nurse mr-1"></i> BHW: <?= htmlspecialchars($patient['bhw_assigned']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['family_no'])): ?>
                                    <span class="health-badge badge-family">
                                        <i class="fas fa-home mr-1"></i> Family: <?= htmlspecialchars($patient['family_no']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Patient Details -->
                            <div class="space-y-4">
                                <!-- Contact Information -->
                                <div class="border-t border-gray-100 pt-4">
                                    <h4 class="font-semibold text-gray-700 mb-3 text-sm">Contact Information</h4>
                                    <div class="space-y-2">
                                        <?php if (!empty($patient['sitio'])): ?>
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-map-marker-alt text-gray-400 mr-3 w-5"></i>
                                                <span class="text-gray-600"><?= htmlspecialchars($patient['sitio']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['address'])): ?>
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-home text-gray-400 mr-3 w-5"></i>
                                                <span class="text-gray-600"><?= htmlspecialchars($patient['address']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['contact'])): ?>
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-phone text-gray-400 mr-3 w-5"></i>
                                                <span class="text-gray-600"><?= htmlspecialchars($patient['contact']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Personal Information -->
                                <div class="border-t border-gray-100 pt-4">
                                    <h4 class="font-semibold text-gray-700 mb-3 text-sm">Personal Information</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <?php if (!empty($patient['date_of_birth'])): ?>
                                            <div>
                                                <p class="text-gray-500 text-xs mb-1">Date of Birth</p>
                                                <p class="text-gray-800 text-sm"><?= date('M d, Y', strtotime($patient['date_of_birth'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['civil_status'])): ?>
                                            <div>
                                                <p class="text-gray-500 text-xs mb-1">Civil Status</p>
                                                <p class="text-gray-800 text-sm"><?= htmlspecialchars($patient['civil_status']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['occupation'])): ?>
                                            <div class="col-span-2">
                                                <p class="text-gray-500 text-xs mb-1">Occupation</p>
                                                <p class="text-gray-800 text-sm"><?= htmlspecialchars($patient['occupation']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Health Information -->
                                <div class="border-t border-gray-100 pt-4">
                                    <h4 class="font-semibold text-gray-700 mb-3 text-sm">Health Information</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <?php if (!empty($patient['disease'])): ?>
                                            <div class="col-span-2">
                                                <p class="text-gray-500 text-xs mb-1">Disease/Condition</p>
                                                <p class="text-gray-800 text-sm"><?= htmlspecialchars($patient['disease']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['last_checkup'])): ?>
                                            <div class="col-span-2">
                                                <p class="text-gray-500 text-xs mb-1">Last Check-up</p>
                                                <p class="text-gray-800 text-sm"><?= date('M d, Y', strtotime($patient['last_checkup'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['consultation_type'])): ?>
                                            <div>
                                                <p class="text-gray-500 text-xs mb-1">Consultation Type</p>
                                                <p class="text-gray-800 text-sm"><?= htmlspecialchars(ucfirst($patient['consultation_type'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Record Status -->
                                <div class="border-t border-gray-100 pt-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-gray-500 text-xs mb-1">Record Status</p>
                                            <p class="text-green-600 text-sm font-medium">
                                                <?= empty($patient['deleted_at']) ? 'Active' : 'Archived' ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-gray-500 text-xs mb-1">Linked Since</p>
                                            <p class="text-gray-800 text-sm"><?= date('M d, Y', strtotime($patient['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Medical Information Tab -->
        <div id="medical" class="tab-content">
            <?php if (empty($allPatientInfo)): ?>
                <div class="no-records-message">
                    <i class="fas fa-user-times text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Medical Information</h3>
                    <p class="text-gray-500 max-w-md mx-auto">No patient records linked to view medical information.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($allPatientInfo as $patientIndex => $patient): 
                        // Skip if no medical info
                        if (empty($patient['height']) && empty($patient['weight']) && empty($patient['blood_type']) 
                            && empty($patient['allergies']) && empty($patient['current_medications'])) {
                            continue;
                        }
                    ?>
                        <div class="info-card p-6">
                            <!-- Patient Header -->
                            <div class="flex items-center mb-6">
                                <div class="w-10 h-10 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-blue-500"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></h3>
                                    <div class="flex items-center mt-1 space-x-3">
                                        <?php if (!empty($patient['age'])): ?>
                                            <span class="text-sm text-gray-600">Age: <?= htmlspecialchars($patient['age']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['patient_gender'])): ?>
                                            <span class="text-sm text-gray-600">Gender: <?= htmlspecialchars($patient['patient_gender']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Vital Statistics -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-4 text-lg">Vital Statistics</h4>
                                    
                                    <div class="space-y-4">
                                        <?php if (!empty($patient['blood_type'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">Blood Type</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($patient['blood_type']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['height'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">Height</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($patient['height']) ?> cm</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['weight'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">Weight</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($patient['weight']) ?> kg</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['height']) && !empty($patient['weight'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">BMI</span>
                                            <span class="font-medium text-gray-800">
                                                <?php 
                                                    $height = $patient['height'] / 100;
                                                    $weight = $patient['weight'];
                                                    $bmi = $weight / ($height * $height);
                                                    echo number_format($bmi, 1);
                                                ?>
                                                <span class="text-sm text-gray-500 ml-2">
                                                    (<?= $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese')) ?>)
                                                </span>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['temperature'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">Temperature</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($patient['temperature']) ?> °C</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['blood_pressure'])): ?>
                                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                            <span class="text-gray-600">Blood Pressure</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($patient['blood_pressure']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Medical Details -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-4 text-lg">Medical Details</h4>
                                    
                                    <div class="space-y-4">
                                        <?php if (!empty($patient['allergies'])): ?>
                                        <div>
                                            <p class="text-gray-600 text-sm font-medium mb-1">Allergies</p>
                                            <p class="text-gray-800 bg-red-50 p-3 rounded-lg text-sm"><?= nl2br(htmlspecialchars($patient['allergies'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['current_medications'])): ?>
                                        <div>
                                            <p class="text-gray-600 text-sm font-medium mb-1">Current Medications</p>
                                            <p class="text-gray-800 bg-yellow-50 p-3 rounded-lg text-sm"><?= nl2br(htmlspecialchars($patient['current_medications'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['chronic_conditions'])): ?>
                                        <div>
                                            <p class="text-gray-600 text-sm font-medium mb-1">Chronic Conditions</p>
                                            <p class="text-gray-800 bg-purple-50 p-3 rounded-lg text-sm"><?= nl2br(htmlspecialchars($patient['chronic_conditions'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['immunization_record'])): ?>
                                        <div>
                                            <p class="text-gray-600 text-sm font-medium mb-1">Immunization Record</p>
                                            <p class="text-gray-800 bg-green-50 p-3 rounded-lg text-sm"><?= nl2br(htmlspecialchars($patient['immunization_record'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Medical History (Full Width) -->
                                <div class="lg:col-span-2">
                                    <?php if (!empty($patient['patient_medical_history']) || !empty($patient['existing_medical_history'])): ?>
                                    <div class="mb-6">
                                        <h4 class="font-semibold text-gray-700 mb-2 text-lg">Medical History</h4>
                                        <p class="text-gray-800 bg-blue-50 p-4 rounded-lg">
                                            <?= nl2br(htmlspecialchars($patient['patient_medical_history'] ?? $patient['existing_medical_history'] ?? '')) ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patient['family_history'])): ?>
                                    <div>
                                        <h4 class="font-semibold text-gray-700 mb-2 text-lg">Family Medical History</h4>
                                        <p class="text-gray-800 bg-green-50 p-4 rounded-lg"><?= nl2br(htmlspecialchars($patient['family_history'])) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php 
                    // Check if any patient had medical info
                    $hasMedicalInfo = false;
                    foreach ($allPatientInfo as $patient) {
                        if (!empty($patient['height']) || !empty($patient['weight']) || !empty($patient['blood_type']) 
                            || !empty($patient['allergies']) || !empty($patient['current_medications'])) {
                            $hasMedicalInfo = true;
                            break;
                        }
                    }
                    
                    if (!$hasMedicalInfo): ?>
                    <div class="no-records-message">
                        <i class="fas fa-heartbeat text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Medical Information</h3>
                        <p class="text-gray-500 max-w-md mx-auto">No detailed medical information recorded for linked patients.</p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for viewing consultation notes -->
    <div id="consultationModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <div id="modalContent"></div>
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="printCurrentConsultationNote()" class="btn-primary flex items-center px-4 py-2 rounded-full">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <button onclick="closeModal()" class="bg-gray-200 text-gray-700 flex items-center px-4 py-2 rounded-full hover:bg-gray-300">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.nav-pill').forEach(button => {
            button.addEventListener('click', () => {
                // Update tabs
                document.querySelectorAll('.nav-pill').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                
                // Show selected tab content
                const tabId = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabId).classList.add('active');
            });
        });

        let currentConsultationNote = null;

        // View Consultation Note Function
        function viewConsultationNote(note) {
            currentConsultationNote = note;
            const modal = document.getElementById('consultationModal');
            const modalContent = document.getElementById('modalContent');
            
            const consultationDate = new Date(note.consultation_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            const content = `
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Consultation Record</h2>
                <p class="text-gray-600 mb-6">Date: ${consultationDate}</p>
                
                ${note.doctor_name ? `
                <div class="bg-blue-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">Healthcare Provider</h3>
                    <p class="text-gray-700">Dr. ${note.doctor_name}</p>
                </div>
                ` : ''}
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Consultation Notes</h3>
                    <p class="text-gray-700 whitespace-pre-wrap">${note.note || 'No notes recorded'}</p>
                </div>
                
                ${note.next_consultation_date ? `
                <div class="bg-green-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold text-green-800 mb-2">Next Appointment</h3>
                    <p class="text-gray-700">${new Date(note.next_consultation_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
                ` : ''}
                
                <div class="text-sm text-gray-500 mt-6 pt-6 border-t border-gray-200">
                    <p><i class="fas fa-calendar-alt mr-2"></i> Consultation Date: ${consultationDate}</p>
                    <p><i class="fas fa-clock mr-2"></i> Recorded on: ${new Date(note.created_at).toLocaleString()}</p>
                </div>
            `;
            
            modalContent.innerHTML = content;
            modal.classList.add('active');
            
            // Prevent body scrolling when modal is open
            document.body.style.overflow = 'hidden';
        }

        // Close Modal Function
        function closeModal() {
            const modal = document.getElementById('consultationModal');
            modal.classList.remove('active');
            
            // Restore body scrolling
            document.body.style.overflow = 'auto';
        }

        // Print Current Consultation Note from Modal
        function printCurrentConsultationNote() {
            if (currentConsultationNote) {
                printConsultationNote(currentConsultationNote);
            }
        }

        // Print Consultation Note Function
        function printConsultationNote(note) {
            const printWindow = window.open('', '_blank');
            const consultationDate = new Date(note.consultation_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            const content = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Consultation Record - ${consultationDate}</title>
                    <style>
                        * { margin: 0; padding: 0; }
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 8.5in; margin: 0 auto; padding: 40px; }
                        header { border-bottom: 3px solid #3b82f6; margin-bottom: 30px; padding-bottom: 20px; }
                        h1 { color: #3b82f6; font-size: 28px; margin-bottom: 5px; }
                        .consultation-date { color: #666; font-size: 14px; }
                        .doctor { background: #f0f9ff; padding: 15px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #3b82f6; }
                        .section { margin-bottom: 25px; }
                        .section-title { color: #1f2937; font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
                        .section-content { color: #4b5563; line-height: 1.6; white-space: pre-wrap; }
                        footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #999; font-size: 12px; }
                        @media print { 
                            body { margin: 0; padding: 0; }
                            .container { padding: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <header>
                            <h1>Consultation Record</h1>
                            <div class="consultation-date">Date: ${consultationDate}</div>
                        </header>
                        
                        ${note.doctor_name ? `
                        <div class="doctor">
                            <strong>Healthcare Provider:</strong><br>
                            Dr. ${note.doctor_name}
                        </div>
                        ` : ''}
                        
                        <div class="section">
                            <div class="section-title">📋 Consultation Notes</div>
                            <div class="section-content">${note.note || 'No notes recorded'}</div>
                        </div>
                        
                        ${note.next_consultation_date ? `
                        <div class="section">
                            <div class="section-title">📅 Next Appointment</div>
                            <div class="section-content">${new Date(note.next_consultation_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                        ` : ''}
                        
                        <footer>
                            <p>This is an official consultation record from the Community Health Tracker System.</p>
                            <p>Printed on: ${new Date().toLocaleString()}</p>
                        </footer>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(content);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }

        // Close modal when clicking outside
        document.getElementById('consultationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>