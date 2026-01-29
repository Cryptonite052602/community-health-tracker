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

$userEmail = $userInfo['email'] ?? $_SESSION['user']['email'] ?? 'Not provided';
$userFullName = $userInfo['full_name'] ?? $_SESSION['user']['full_name'] ?? 'Not provided';
$userDateOfBirth = $userInfo['date_of_birth'] ?? $_SESSION['user']['date_of_birth'] ?? null;
$userCreatedAt = $userInfo['created_at'] ?? $_SESSION['user']['created_at'] ?? date('Y-m-d');
$error = '';
$success = '';

// Get ALL patient info linked to this user
$allPatientInfo = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.full_name,
            sp.date_of_birth,
            sp.age,
            sp.gender,
            sp.sitio,
            sp.disease,
            sp.last_checkup,
            sp.phic_no,
            sp.fourps_member,
            sp.bhw_assigned,
            sp.contact,
            sp.created_at,
            eip.blood_type,
            eip.height,
            eip.weight,
            eip.allergies,
            eip.current_medications,
            eip.blood_pressure,
            eip.temperature
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

// Get consultation notes
$allConsultationNotes = [];
if (!empty($allPatientInfo)) {
    try {
        $patientIds = array_column($allPatientInfo, 'id');
        $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT 
                cn.*,
                cn.doctor_name as doctor_name
            FROM consultation_notes cn
            WHERE cn.patient_id IN ($placeholders)
            ORDER BY cn.consultation_date DESC
        ");
        $stmt->execute($patientIds);
        $allConsultationNotes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // Consultation notes might not exist - not critical
    }
}

// Calculate stats
$totalConsultationNotes = count($allConsultationNotes);
$totalPatients = count($allPatientInfo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Profile - Barangay Luz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .icon-xs { font-size: 0.75rem; }
        .icon-sm { font-size: 0.875rem; }
        .icon-base { font-size: 1rem; }
        .icon-lg { font-size: 1.125rem; }
        .icon-xl { font-size: 1.25rem; }
        .icon-2xl { font-size: 1.5rem; }
        .icon-3xl { font-size: 1.875rem; }
        .icon-4xl { font-size: 2.25rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans">
    <!-- Top Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex items-center">
                        <i class="fas fa-heartbeat text-blue-600 icon-xl mr-3"></i>
                        <span class="font-bold text-gray-900 text-lg">Barangay Luz Health</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($userFullName); ?></p>
                        <p class="text-xs text-gray-500">Patient Portal</p>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-user text-blue-600 icon-sm"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Health Profile</h1>
            <p class="text-gray-600">View and manage your medical records</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 icon-lg mr-3"></i>
                    <p class="text-red-800 font-bold"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center mr-4">
                        <i class="fas fa-users text-blue-600 icon-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Patients</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $totalPatients; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center mr-4">
                        <i class="fas fa-calendar-check text-green-600 icon-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Consultations</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $totalConsultationNotes; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center mr-4">
                        <i class="fas fa-heartbeat text-purple-600 icon-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Status</p>
                        <p class="text-xl font-bold text-green-600">Active</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="bg-white rounded-lg shadow mb-8">
            <!-- Tab Headers -->
            <div class="border-b">
                <div class="flex overflow-x-auto">
                    <button class="tab-header active px-6 py-4 font-bold text-blue-600 border-b-2 border-blue-600" data-tab="consultations">
                        <i class="fas fa-calendar-check icon-base mr-2"></i> Consultations
                    </button>
                    <button class="tab-header px-6 py-4 font-bold text-gray-600 hover:text-gray-900" data-tab="patients">
                        <i class="fas fa-user-injured icon-base mr-2"></i> Patients
                    </button>
                    <button class="tab-header px-6 py-4 font-bold text-gray-600 hover:text-gray-900" data-tab="medical">
                        <i class="fas fa-heart icon-base mr-2"></i> Medical Info
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Consultations Tab -->
                <div id="consultations" class="tab-content active">
                    <?php if (empty($allPatientInfo)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-md text-gray-400 icon-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No Patients Linked</h3>
                            <p class="text-gray-500 mb-6">Your account is not linked to any patient records.</p>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold" onclick="alert('Please contact the health center to link your account.')">
                                Contact Health Center
                            </button>
                        </div>
                    <?php elseif (empty($allConsultationNotes)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-file-medical-alt text-gray-400 icon-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No Consultations Yet</h3>
                            <p class="text-gray-500">Visit the health center for your first consultation.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php 
                            // Group consultations by patient
                            $consultationsByPatient = [];
                            foreach ($allConsultationNotes as $note) {
                                $patientId = $note['patient_id'];
                                if (!isset($consultationsByPatient[$patientId])) {
                                    // Find patient name
                                    $patientName = 'Unknown';
                                    foreach ($allPatientInfo as $patient) {
                                        if ($patient['id'] == $patientId) {
                                            $patientName = $patient['full_name'];
                                            break;
                                        }
                                    }
                                    $consultationsByPatient[$patientId] = [
                                        'patient_name' => $patientName,
                                        'notes' => []
                                    ];
                                }
                                $consultationsByPatient[$patientId]['notes'][] = $note;
                            }
                            
                            foreach ($consultationsByPatient as $patientData):
                            ?>
                                <div class="border rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="bg-gray-50 px-4 py-3 border-b">
                                        <div class="flex justify-between items-center">
                                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($patientData['patient_name']); ?></h3>
                                            <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded font-bold">
                                                <?php echo count($patientData['notes']); ?> consultation<?php echo count($patientData['notes']) !== 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="divide-y">
                                        <?php foreach ($patientData['notes'] as $note): ?>
                                            <div class="p-4 hover:bg-gray-50">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div>
                                                        <p class="font-bold text-gray-900">
                                                            <?php echo date('F j, Y', strtotime($note['consultation_date'] ?? 'now')); ?>
                                                        </p>
                                                        <?php if (!empty($note['doctor_name'])): ?>
                                                            <p class="text-sm text-gray-600 font-medium">
                                                                <i class="fas fa-user-md icon-sm mr-1"></i> <?php echo htmlspecialchars($note['doctor_name']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button onclick="viewConsultationNote(<?php echo htmlspecialchars(json_encode($note)); ?>)" 
                                                            class="text-sm text-blue-600 hover:text-blue-800 font-bold">
                                                        <i class="fas fa-eye icon-sm mr-1"></i> View Details
                                                    </button>
                                                </div>
                                                
                                                <p class="text-gray-700 text-sm mb-2">
                                                    <?php 
                                                    if (!empty($note['note'])) {
                                                        if (strlen($note['note']) > 120) {
                                                            echo htmlspecialchars(substr($note['note'], 0, 120)) . '...';
                                                        } else {
                                                            echo htmlspecialchars($note['note']);
                                                        }
                                                    } else {
                                                        echo 'No detailed notes recorded.';
                                                    }
                                                    ?>
                                                </p>
                                                
                                                <?php if (!empty($note['next_consultation_date'])): ?>
                                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                                        <p class="text-sm text-green-600 font-bold">
                                                            <i class="fas fa-calendar-alt icon-sm mr-1"></i>
                                                            Next appointment: <?php echo date('M j, Y', strtotime($note['next_consultation_date'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Patients Tab -->
                <div id="patients" class="tab-content hidden">
                    <?php if (empty($allPatientInfo)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-times text-gray-400 icon-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No Patient Profiles</h3>
                            <p class="text-gray-500">Contact the health center to link patient records.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($allPatientInfo as $patient): 
                                // Count consultations for this patient
                                $patientNoteCount = 0;
                                foreach ($allConsultationNotes as $note) {
                                    if ($note['patient_id'] == $patient['id']) {
                                        $patientNoteCount++;
                                    }
                                }
                            ?>
                                <div class="border rounded-lg p-5 hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-blue-600 icon-lg"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                                                <div class="flex items-center space-x-2 mt-1">
                                                    <?php if (!empty($patient['age'])): ?>
                                                        <span class="text-sm text-gray-600 font-medium">Age: <?php echo htmlspecialchars($patient['age']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($patient['gender'])): ?>
                                                        <span class="text-sm text-gray-600 font-medium">• <?php echo htmlspecialchars($patient['gender']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded font-bold">
                                            <?php echo $patientNoteCount; ?> consult<?php echo $patientNoteCount !== 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-3 mb-4">
                                        <?php if (!empty($patient['sitio'])): ?>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-map-marker-alt icon-sm mr-2 text-gray-400"></i>
                                                <span class="font-medium"><?php echo htmlspecialchars($patient['sitio']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['contact'])): ?>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-phone icon-sm mr-2 text-gray-400"></i>
                                                <span class="font-medium"><?php echo htmlspecialchars($patient['contact']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['disease'])): ?>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-stethoscope icon-sm mr-2 text-gray-400"></i>
                                                <span class="font-medium"><?php echo htmlspecialchars($patient['disease']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex flex-wrap gap-2">
                                        <?php if (!empty($patient['phic_no'])): ?>
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-bold">
                                                <i class="fas fa-id-card icon-xs mr-1"></i> PHIC Member
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['fourps_member']) && $patient['fourps_member'] == 'Yes'): ?>
                                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded font-bold">
                                                <i class="fas fa-users icon-xs mr-1"></i> 4P's Member
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($patient['last_checkup'])): ?>
                                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded font-bold">
                                                <i class="fas fa-calendar icon-xs mr-1"></i> Last: <?php echo date('M j, Y', strtotime($patient['last_checkup'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Medical Info Tab -->
                <div id="medical" class="tab-content hidden">
                    <?php if (empty($allPatientInfo)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-heartbeat text-gray-400 icon-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No Medical Information</h3>
                            <p class="text-gray-500">No patient records linked.</p>
                        </div>
                    <?php else: 
                        $anyPatientHasMedicalInfo = false;
                        foreach ($allPatientInfo as $patient) {
                            if (!empty($patient['blood_type']) || !empty($patient['height']) || 
                                !empty($patient['weight']) || !empty($patient['allergies']) || 
                                !empty($patient['current_medications'])) {
                                $anyPatientHasMedicalInfo = true;
                                break;
                            }
                        }
                        
                        if (!$anyPatientHasMedicalInfo): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-heartbeat text-gray-400 icon-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No Medical Information</h3>
                            <p class="text-gray-500">Medical information will be available after your first consultation.</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($allPatientInfo as $patient): 
                                // Check if patient has any medical info
                                $hasMedicalInfo = !empty($patient['blood_type']) || !empty($patient['height']) || 
                                                 !empty($patient['weight']) || !empty($patient['allergies']) || 
                                                 !empty($patient['current_medications']);
                                
                                if (!$hasMedicalInfo) continue;
                            ?>
                                <div class="border rounded-lg p-5 hover:shadow-md transition-shadow">
                                    <h3 class="font-bold text-gray-900 text-lg mb-4"><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Vital Signs -->
                                        <div>
                                            <h4 class="font-bold text-gray-700 mb-3 text-sm">Vital Signs</h4>
                                            <div class="space-y-3">
                                                <?php if (!empty($patient['blood_type'])): ?>
                                                    <div class="flex justify-between items-center py-2 border-b">
                                                        <span class="text-gray-600 font-medium">
                                                            <i class="fas fa-tint icon-sm mr-2 text-red-400"></i>Blood Type
                                                        </span>
                                                        <span class="font-bold"><?php echo htmlspecialchars($patient['blood_type']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($patient['height']) && !empty($patient['weight'])): ?>
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <p class="text-sm text-gray-500 mb-1 font-medium">
                                                                <i class="fas fa-ruler-vertical icon-sm mr-1"></i> Height
                                                            </p>
                                                            <p class="font-bold"><?php echo htmlspecialchars($patient['height']); ?> cm</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-sm text-gray-500 mb-1 font-medium">
                                                                <i class="fas fa-weight icon-sm mr-1"></i> Weight
                                                            </p>
                                                            <p class="font-bold"><?php echo htmlspecialchars($patient['weight']); ?> kg</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php 
                                                    $height = !empty($patient['height']) ? $patient['height'] / 100 : 0;
                                                    $weight = !empty($patient['weight']) ? $patient['weight'] : 0;
                                                    $bmi = $height > 0 ? $weight / ($height * $height) : 0;
                                                    if ($bmi > 0): 
                                                    ?>
                                                        <div class="flex justify-between items-center py-2 border-b">
                                                            <span class="text-gray-600 font-medium">
                                                                <i class="fas fa-calculator icon-sm mr-2"></i>BMI
                                                            </span>
                                                            <span class="font-bold">
                                                                <?php echo number_format($bmi, 1); ?>
                                                                <span class="text-sm font-normal ml-2 text-gray-500">
                                                                    (<?php 
                                                                    if ($bmi < 18.5) {
                                                                        echo 'Underweight';
                                                                    } elseif ($bmi < 25) {
                                                                        echo 'Normal';
                                                                    } elseif ($bmi < 30) {
                                                                        echo 'Overweight';
                                                                    } else {
                                                                        echo 'Obese';
                                                                    }
                                                                    ?>)
                                                                </span>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($patient['blood_pressure'])): ?>
                                                    <div class="flex justify-between items-center py-2 border-b">
                                                        <span class="text-gray-600 font-medium">
                                                            <i class="fas fa-heartbeat icon-sm mr-2 text-red-400"></i>Blood Pressure
                                                        </span>
                                                        <span class="font-bold"><?php echo htmlspecialchars($patient['blood_pressure']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($patient['temperature'])): ?>
                                                    <div class="flex justify-between items-center py-2 border-b">
                                                        <span class="text-gray-600 font-medium">
                                                            <i class="fas fa-thermometer-half icon-sm mr-2 text-orange-400"></i>Temperature
                                                        </span>
                                                        <span class="font-bold"><?php echo htmlspecialchars($patient['temperature']); ?>°C</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Medical Details -->
                                        <div>
                                            <h4 class="font-bold text-gray-700 mb-3 text-sm">Medical Details</h4>
                                            <div class="space-y-4">
                                                <?php if (!empty($patient['allergies'])): ?>
                                                    <div>
                                                        <p class="text-sm font-bold text-gray-700 mb-2">
                                                            <i class="fas fa-exclamation-triangle icon-sm mr-2 text-yellow-500"></i>Allergies
                                                        </p>
                                                        <p class="text-sm text-gray-600 bg-red-50 p-3 rounded border-l-4 border-red-400">
                                                            <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($patient['current_medications'])): ?>
                                                    <div>
                                                        <p class="text-sm font-bold text-gray-700 mb-2">
                                                            <i class="fas fa-pills icon-sm mr-2 text-blue-500"></i>Current Medications
                                                        </p>
                                                        <p class="text-sm text-gray-600 bg-blue-50 p-3 rounded border-l-4 border-blue-400">
                                                            <?php echo nl2br(htmlspecialchars($patient['current_medications'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Modal -->
    <div id="consultationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900" id="modalTitle"></h3>
                        <p class="text-gray-600 text-sm font-medium" id="modalSubtitle"></p>
                    </div>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times icon-lg"></i>
                    </button>
                </div>
                
                <div class="space-y-4" id="modalBody"></div>
                
                <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button onclick="printConsultation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold">
                        <i class="fas fa-print icon-base mr-2"></i> Print
                    </button>
                    <button onclick="closeModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-bold">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-12 border-t bg-white">
        <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="text-center text-gray-500 text-sm">
                <p class="font-bold">Barangay Luz Health Center • Patient Portal</p>
                <p class="mt-1">© <?php echo date('Y'); ?> All rights reserved</p>
            </div>
        </div>
    </footer>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-header').forEach(button => {
            button.addEventListener('click', function() {
                // Update active tab button
                document.querySelectorAll('.tab-header').forEach(btn => {
                    btn.classList.remove('active', 'text-blue-600', 'border-blue-600');
                    btn.classList.add('text-gray-600');
                });
                this.classList.add('active', 'text-blue-600', 'border-blue-600');
                this.classList.remove('text-gray-600');
                
                // Show selected tab content
                const tabId = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                });
                const tabContent = document.getElementById(tabId);
                if (tabContent) {
                    tabContent.classList.remove('hidden');
                    tabContent.classList.add('active');
                }
            });
        });

        // Modal functions
        let currentConsultation = null;

        function viewConsultationNote(note) {
            currentConsultation = note;
            const modal = document.getElementById('consultationModal');
            const consultationDate = new Date(note.consultation_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            document.getElementById('modalTitle').textContent = 'Consultation - ' + consultationDate;
            document.getElementById('modalSubtitle').innerHTML = note.doctor_name 
                ? '<i class="fas fa-user-md icon-sm mr-1"></i> With Dr. ' + note.doctor_name 
                : 'Healthcare consultation';
            
            let bodyContent = '';
            
            if (note.note) {
                bodyContent += `
                    <div>
                        <p class="text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-notes-medical icon-sm mr-2"></i>Consultation Notes
                        </p>
                        <div class="bg-gray-50 p-4 rounded border-l-4 border-blue-400">
                            <p class="text-gray-700 whitespace-pre-wrap font-medium">${note.note}</p>
                        </div>
                    </div>
                `;
            } else {
                bodyContent += `
                    <div>
                        <p class="text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-notes-medical icon-sm mr-2"></i>Consultation Notes
                        </p>
                        <div class="bg-gray-50 p-4 rounded border-l-4 border-blue-400">
                            <p class="text-gray-700 font-medium">No detailed notes recorded.</p>
                        </div>
                    </div>
                `;
            }
            
            if (note.next_consultation_date) {
                const nextDate = new Date(note.next_consultation_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                bodyContent += `
                    <div class="p-4 bg-green-50 rounded border border-green-100">
                        <p class="text-sm font-bold text-green-700 mb-1">
                            <i class="fas fa-calendar-alt icon-sm mr-2"></i>Next Appointment
                        </p>
                        <p class="text-green-600 font-bold">${nextDate}</p>
                    </div>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = bodyContent;
            modal.classList.remove('hidden');
            
            // Prevent scrolling on body
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('consultationModal');
            modal.classList.add('hidden');
            
            // Restore scrolling
            document.body.style.overflow = 'auto';
        }

        function printConsultation() {
            if (!currentConsultation) return;
            
            const printWindow = window.open('', '_blank');
            const consultationDate = new Date(currentConsultation.consultation_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const content = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Consultation Record</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h1 { color: #2563eb; margin-bottom: 5px; font-weight: bold; }
                        .section { margin-bottom: 20px; }
                        .section-title { color: #374151; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
                        .notes { background: #f9fafb; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2563eb; }
                        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; font-weight: bold; }
                        @media print {
                            body { margin: 0; padding: 10px; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Consultation Record</h1>
                        <p><strong>Barangay Luz Health Center</strong></p>
                        <p><strong>Date:</strong> ${consultationDate}</p>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Healthcare Provider</div>
                        <p><strong>${currentConsultation.doctor_name ? 'Dr. ' + currentConsultation.doctor_name : 'Healthcare Staff'}</strong></p>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Consultation Notes</div>
                        <div class="notes"><strong>${currentConsultation.note || 'No detailed notes recorded.'}</strong></div>
                    </div>
                    
                    ${currentConsultation.next_consultation_date ? `
                    <div class="section">
                        <div class="section-title">Next Appointment</div>
                        <p><strong>${new Date(currentConsultation.next_consultation_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}</strong></p>
                    </div>
                    ` : ''}
                    
                    <div class="footer">
                        <p>Printed on: ${new Date().toLocaleString()}</p>
                        <p>This is an official record from Barangay Luz Health Center</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(content);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 100);
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