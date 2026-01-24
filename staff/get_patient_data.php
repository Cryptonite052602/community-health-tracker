<?php
// get_patient_data.php
session_start();
require_once __DIR__ . '/../includes/auth.php';


redirectIfNotLoggedIn();

if (!isset($_GET['id'])) {
    die('Patient ID is required');
}

$patientId = $_GET['id'];
$staffId = $_SESSION['user']['id'];

try {
    // Get patient basic information
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(u.full_name, 'N/A') as registered_by_name,
            u.email as registered_email,
            u.unique_number,
            COALESCE(u.gender, p.gender) as user_gender,
            u.date_of_birth as user_dob,
            u.address as user_address,
            u.contact as user_contact,
            u.sitio as user_sitio,
            u.civil_status as user_civil_status,
            u.occupation as user_occupation
        FROM sitio1_patients p
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.id = ? AND p.added_by = ? AND p.deleted_at IS NULL
    ");
    $stmt->execute([$patientId, $staffId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        die('Patient not found or access denied');
    }

    // Get health information
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Merge data
    $patientData = array_merge($patient, $healthInfo ?: []);

} catch (PDOException $e) {
    die('Error fetching patient data: ' . $e->getMessage());
}
?>

<!-- Patient Information Form -->
<form id="healthInfoForm" method="POST" action="existing_info_patients.php" class="space-y-8">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <input type="hidden" name="save_health_info" value="1">

    <!-- Personal Information Section -->
    <div class="bg-white p-8 rounded-2xl border-2 border-blue-100">
        <h4 class="form-section-title-modal text-2xl mb-6">
            <i class="fas fa-user-circle mr-3"></i>Personal Information
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Full Name -->
            <div>
                <label class="form-label-modal">Full Name</label>
                <input type="text" value="<?= htmlspecialchars($patientData['full_name'] ?? '') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Date of Birth -->
            <div>
                <label class="form-label-modal">Date of Birth</label>
                <input type="text" value="<?= !empty($patientData['date_of_birth']) ? date('F d, Y', strtotime($patientData['date_of_birth'])) : 'N/A' ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Age -->
            <div>
                <label class="form-label-modal">Age</label>
                <input type="text" value="<?= $patientData['age'] ?? 'N/A' ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Gender -->
            <div>
                <label class="form-label-modal required-field">Gender</label>
                <select name="gender" required class="form-select-modal">
                    <option value="">Select Gender</option>
                    <option value="Male" <?= ($patientData['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($patientData['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($patientData['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Address -->
            <div>
                <label class="form-label-modal">Address</label>
                <input type="text" value="<?= htmlspecialchars($patientData['address'] ?? '') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Sitio -->
            <div>
                <label class="form-label-modal">Sitio</label>
                <input type="text" value="<?= htmlspecialchars($patientData['sitio'] ?? '') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Civil Status -->
            <div>
                <label class="form-label-modal">Civil Status</label>
                <input type="text" value="<?= htmlspecialchars($patientData['civil_status'] ?? 'N/A') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Occupation -->
            <div>
                <label class="form-label-modal">Occupation</label>
                <input type="text" value="<?= htmlspecialchars($patientData['occupation'] ?? 'N/A') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- Contact Number -->
            <div>
                <label class="form-label-modal">Contact Number</label>
                <input type="text" value="<?= htmlspecialchars($patientData['contact'] ?? '') ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <!-- PHIC No. -->
            <div>
                <label class="form-label-modal">PHIC Number</label>
                <input type="text" name="phic_no" value="<?= htmlspecialchars($patientData['phic_no'] ?? '') ?>" 
                       class="form-input-modal" placeholder="Enter PHIC Number">
            </div>

            <!-- BHW Assigned -->
            <div>
                <label class="form-label-modal">BHW Assigned</label>
                <input type="text" name="bhw_assigned" value="<?= htmlspecialchars($patientData['bhw_assigned'] ?? '') ?>" 
                       class="form-input-modal" placeholder="Enter BHW Name">
            </div>

            <!-- Family No. -->
            <div>
                <label class="form-label-modal">Family Number</label>
                <input type="text" name="family_no" value="<?= htmlspecialchars($patientData['family_no'] ?? '') ?>" 
                       class="form-input-modal" placeholder="Enter Family Number">
            </div>

            <!-- 4P's Member -->
            <div>
                <label class="form-label-modal">4P's Member</label>
                <select name="fourps_member" class="form-select-modal">
                    <option value="No" <?= ($patientData['fourps_member'] ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                    <option value="Yes" <?= ($patientData['fourps_member'] ?? 'No') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>

            <!-- Last Checkup -->
            <div>
                <label class="form-label-modal">Last Check-up Date</label>
                <input type="text" value="<?= !empty($patientData['last_checkup']) ? date('F d, Y', strtotime($patientData['last_checkup'])) : 'N/A' ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>
        </div>
    </div>

    <!-- Medical Information Section -->
    <div class="bg-white p-8 rounded-2xl border-2 border-blue-100">
        <h4 class="form-section-title-modal text-2xl mb-6">
            <i class="fas fa-heartbeat mr-3"></i>Medical Information
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Height -->
            <div>
                <label class="form-label-modal required-field">Height (cm)</label>
                <input type="number" name="height" value="<?= $patientData['height'] ?? '' ?>" 
                       required step="0.1" min="0" class="form-input-modal" placeholder="Enter height">
            </div>

            <!-- Weight -->
            <div>
                <label class="form-label-modal required-field">Weight (kg)</label>
                <input type="number" name="weight" value="<?= $patientData['weight'] ?? '' ?>" 
                       required step="0.1" min="0" class="form-input-modal" placeholder="Enter weight">
            </div>

            <!-- Temperature -->
            <div>
                <label class="form-label-modal">Temperature (°C)</label>
                <input type="number" name="temperature" value="<?= $patientData['temperature'] ?? '' ?>" 
                       step="0.1" class="form-input-modal" placeholder="Enter temperature">
            </div>

            <!-- Blood Pressure -->
            <div>
                <label class="form-label-modal">Blood Pressure</label>
                <input type="text" name="blood_pressure" value="<?= htmlspecialchars($patientData['blood_pressure'] ?? '') ?>" 
                       class="form-input-modal" placeholder="e.g., 120/80">
            </div>

            <!-- Blood Type -->
            <div>
                <label class="form-label-modal required-field">Blood Type</label>
                <select name="blood_type" required class="form-select-modal">
                    <option value="">Select Blood Type</option>
                    <option value="A+" <?= ($patientData['blood_type'] ?? '') == 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A-" <?= ($patientData['blood_type'] ?? '') == 'A-' ? 'selected' : '' ?>>A-</option>
                    <option value="B+" <?= ($patientData['blood_type'] ?? '') == 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B-" <?= ($patientData['blood_type'] ?? '') == 'B-' ? 'selected' : '' ?>>B-</option>
                    <option value="AB+" <?= ($patientData['blood_type'] ?? '') == 'AB+' ? 'selected' : '' ?>>AB+</option>
                    <option value="AB-" <?= ($patientData['blood_type'] ?? '') == 'AB-' ? 'selected' : '' ?>>AB-</option>
                    <option value="O+" <?= ($patientData['blood_type'] ?? '') == 'O+' ? 'selected' : '' ?>>O+</option>
                    <option value="O-" <?= ($patientData['blood_type'] ?? '') == 'O-' ? 'selected' : '' ?>>O-</option>
                    <option value="Unknown" <?= ($patientData['blood_type'] ?? '') == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                </select>
            </div>
        </div>

        <!-- Medical History Textareas -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="form-label-modal">Allergies</label>
                <textarea name="allergies" rows="3" class="form-textarea-modal" 
                          placeholder="List any allergies..."><?= htmlspecialchars($patientData['allergies'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="form-label-modal">Current Medications</label>
                <textarea name="current_medications" rows="3" class="form-textarea-modal" 
                          placeholder="List current medications..."><?= htmlspecialchars($patientData['current_medications'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="form-label-modal">Immunization Record</label>
                <textarea name="immunization_record" rows="3" class="form-textarea-modal" 
                          placeholder="Immunization history..."><?= htmlspecialchars($patientData['immunization_record'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="form-label-modal">Chronic Conditions</label>
                <textarea name="chronic_conditions" rows="3" class="form-textarea-modal" 
                          placeholder="Chronic health conditions..."><?= htmlspecialchars($patientData['chronic_conditions'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="mt-6">
            <label class="form-label-modal">Medical History</label>
            <textarea name="medical_history" rows="4" class="form-textarea-modal" 
                      placeholder="Detailed medical history..."><?= htmlspecialchars($patientData['medical_history'] ?? '') ?></textarea>
        </div>

        <div class="mt-6">
            <label class="form-label-modal">Family Medical History</label>
            <textarea name="family_history" rows="4" class="form-textarea-modal" 
                      placeholder="Family medical history..."><?= htmlspecialchars($patientData['family_history'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="bg-white p-8 rounded-2xl border-2 border-blue-100">
        <h4 class="form-section-title-modal text-2xl mb-6">
            <i class="fas fa-info-circle mr-3"></i>Additional Information
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="form-label-modal">Patient Type</label>
                <input type="text" value="<?= !empty($patientData['user_id']) ? 'Registered Patient' : 'Regular Patient' ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <div>
                <label class="form-label-modal">Date Added</label>
                <input type="text" value="<?= !empty($patientData['created_at']) ? date('F d, Y h:i A', strtotime($patientData['created_at'])) : 'N/A' ?>" 
                       class="form-input-modal readonly-field" readonly>
            </div>

            <?php if (!empty($patientData['user_id'])): ?>
                <div>
                    <label class="form-label-modal">Unique Number</label>
                    <input type="text" value="<?= htmlspecialchars($patientData['unique_number'] ?? '') ?>" 
                           class="form-input-modal readonly-field" readonly>
                </div>

                <div>
                    <label class="form-label-modal">Registered Email</label>
                    <input type="text" value="<?= htmlspecialchars($patientData['registered_email'] ?? '') ?>" 
                           class="form-input-modal readonly-field" readonly>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>