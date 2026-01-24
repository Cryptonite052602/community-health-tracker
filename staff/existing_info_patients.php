<?php
ob_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

$message = '';
$error = '';

// Check if columns exist in the database
$civilStatusExists = false;
$occupationExists = false;
$sitioExists = false;
$dateOfBirthExists = false;
$phicNoExists = false;
$bhwAssignedExists = false;
$familyNoExists = false;
$fourpsMemberExists = false;

try {
    // Check if civil_status column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'civil_status'");
    $stmt->execute();
    $civilStatusExists = $stmt->rowCount() > 0;

    // Check if occupation column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'occupation'");
    $stmt->execute();
    $occupationExists = $stmt->rowCount() > 0;

    // Check if sitio column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'sitio'");
    $stmt->execute();
    $sitioExists = $stmt->rowCount() > 0;

    // Check if date_of_birth column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'date_of_birth'");
    $stmt->execute();
    $dateOfBirthExists = $stmt->rowCount() > 0;

    // Check if new columns exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'phic_no'");
    $stmt->execute();
    $phicNoExists = $stmt->rowCount() > 0;

    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'bhw_assigned'");
    $stmt->execute();
    $bhwAssignedExists = $stmt->rowCount() > 0;

    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'family_no'");
    $stmt->execute();
    $familyNoExists = $stmt->rowCount() > 0;

    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'fourps_member'");
    $stmt->execute();
    $fourpsMemberExists = $stmt->rowCount() > 0;

    // Add new columns if they don't exist
    if (!$phicNoExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN phic_no VARCHAR(20) NULL AFTER occupation");
    }
    
    if (!$bhwAssignedExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN bhw_assigned VARCHAR(100) NULL AFTER phic_no");
    }
    
    if (!$familyNoExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN family_no VARCHAR(50) NULL AFTER bhw_assigned");
    }
    
    if (!$fourpsMemberExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN fourps_member ENUM('Yes', 'No') DEFAULT 'No' AFTER family_no");
    }

    // If date_of_birth column doesn't exist, add it
    if (!$dateOfBirthExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN date_of_birth DATE NULL AFTER full_name");
    }

    // Check if consultation_notes table exists, if not create it
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'consultation_notes'");
    $stmt->execute();
    $consultationNotesTableExists = $stmt->rowCount() > 0;

    if (!$consultationNotesTableExists) {
        // Create consultation_notes table
        $createTableQuery = "CREATE TABLE consultation_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            note TEXT NOT NULL,
            consultation_date DATE NOT NULL,
            next_consultation_date DATE NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES sitio1_patients(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES sitio1_users(id) ON DELETE CASCADE,
            INDEX idx_patient_id (patient_id),
            INDEX idx_created_by (created_by)
        )";

        $pdo->exec($createTableQuery);
    }

    // Check if deleted_patients table exists, if not create it
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'deleted_patients'");
    $stmt->execute();
    $deletedPatientsTableExists = $stmt->rowCount() > 0;

    if (!$deletedPatientsTableExists) {
        // Create deleted_patients table with ALL columns that might exist in sitio1_patients
        $createTableQuery = "CREATE TABLE deleted_patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            date_of_birth DATE NULL,
            age INT,
            gender VARCHAR(50),
            address TEXT,
            contact VARCHAR(100),
            last_checkup DATE,
            added_by INT,
            user_id INT,
            deleted_by INT,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sitio VARCHAR(255) NULL,
            civil_status VARCHAR(100) NULL,
            occupation VARCHAR(255) NULL,
            phic_no VARCHAR(20) NULL,
            bhw_assigned VARCHAR(100) NULL,
            family_no VARCHAR(50) NULL,
            fourps_member ENUM('Yes', 'No') DEFAULT 'No',
            consent_given TINYINT(1) DEFAULT 1,
            consent_date TIMESTAMP NULL,
            deleted_reason VARCHAR(500) NULL
        )";

        $pdo->exec($createTableQuery);
    }
} catch (PDOException $e) {
    // If we can't check columns, assume they don't exist
    $civilStatusExists = false;
    $occupationExists = false;
    $sitioExists = false;
    $dateOfBirthExists = false;
    $phicNoExists = false;
    $bhwAssignedExists = false;
    $familyNoExists = false;
    $fourpsMemberExists = false;
}

// Handle form submission for editing health info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
    $required = ['patient_id', 'height', 'weight', 'blood_type'];
    $missing = array();

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }

    // Get patient gender from database if not provided in form
    $gender = $_POST['gender'];
    if (empty($gender)) {
        try {
            $stmt = $pdo->prepare("SELECT gender FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$_POST['patient_id']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient && !empty($patient['gender'])) {
                $gender = $patient['gender'];
            }
        } catch (PDOException $e) {
            $missing[] = 'gender';
        }
    }

    if (!empty($missing)) {
        $error = "Please fill in all required fields: " . implode(', ', str_replace('_', ' ', $missing));
    } else {
        try {
            $patient_id = $_POST['patient_id'];
            $height = $_POST['height'];
            $weight = $_POST['weight'];
            $blood_type = $_POST['blood_type'];
            $temperature = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
            $blood_pressure = !empty($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null;
            $allergies = !empty($_POST['allergies']) ? $_POST['allergies'] : null;
            $medical_history = !empty($_POST['medical_history']) ? $_POST['medical_history'] : null;
            $current_medications = !empty($_POST['current_medications']) ? $_POST['current_medications'] : null;
            $family_history = !empty($_POST['family_history']) ? $_POST['family_history'] : null;
            $immunization_record = !empty($_POST['immunization_record']) ? $_POST['immunization_record'] : null;
            $chronic_conditions = !empty($_POST['chronic_conditions']) ? $_POST['chronic_conditions'] : null;
            $phic_no = !empty($_POST['phic_no']) ? $_POST['phic_no'] : null;
            $bhw_assigned = !empty($_POST['bhw_assigned']) ? $_POST['bhw_assigned'] : null;
            $family_no = !empty($_POST['family_no']) ? $_POST['family_no'] : null;
            $fourps_member = !empty($_POST['fourps_member']) ? $_POST['fourps_member'] : 'No';

            // Check if record exists
            $stmt = $pdo->prepare("SELECT id FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);

            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                    gender = ?, height = ?, weight = ?, blood_type = ?, temperature = ?, 
                    blood_pressure = ?, allergies = ?, medical_history = ?, 
                    current_medications = ?, family_history = ?, immunization_record = ?,
                    chronic_conditions = ?, updated_at = NOW()
                    WHERE patient_id = ?");
                $stmt->execute([
                    $gender,
                    $height,
                    $weight,
                    $blood_type,
                    $temperature,
                    $blood_pressure,
                    $allergies,
                    $medical_history,
                    $current_medications,
                    $family_history,
                    $immunization_record,
                    $chronic_conditions,
                    $patient_id
                ]);

                // Update the patient table with new fields
                $updatePatientQuery = "UPDATE sitio1_patients SET gender = ?, phic_no = ?, bhw_assigned = ?, family_no = ?, fourps_member = ? WHERE id = ?";
                $stmt = $pdo->prepare($updatePatientQuery);
                $stmt->execute([$gender, $phic_no, $bhw_assigned, $family_no, $fourps_member, $patient_id]);

                $message = "Patient health information updated successfully!";
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type, temperature,
                    blood_pressure, allergies, medical_history, current_medications, 
                    family_history, immunization_record, chronic_conditions)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $patient_id,
                    $gender,
                    $height,
                    $weight,
                    $blood_type,
                    $temperature,
                    $blood_pressure,
                    $allergies,
                    $medical_history,
                    $current_medications,
                    $family_history,
                    $immunization_record,
                    $chronic_conditions
                ]);

                // Update the patient table with new fields
                $updatePatientQuery = "UPDATE sitio1_patients SET gender = ?, phic_no = ?, bhw_assigned = ?, family_no = ?, fourps_member = ? WHERE id = ?";
                $stmt = $pdo->prepare($updatePatientQuery);
                $stmt->execute([$gender, $phic_no, $bhw_assigned, $family_no, $fourps_member, $patient_id]);

                $message = "Patient health information saved successfully!";
            }

        } catch (PDOException $e) {
            $error = "Error saving patient health information: " . $e->getMessage();
        }
    }
}

// Handle form submission for adding new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $fullName = trim($_POST['full_name']);
    $dateOfBirth = trim($_POST['date_of_birth']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $civil_status = trim($_POST['civil_status']);
    $occupation = trim($_POST['occupation']);
    $address = trim($_POST['address']);
    $sitio = trim($_POST['sitio']);
    $contact = trim($_POST['contact']);
    $lastCheckup = trim($_POST['last_checkup']);
    $phic_no = trim($_POST['phic_no']);
    $bhw_assigned = trim($_POST['bhw_assigned']);
    $family_no = trim($_POST['family_no']);
    $fourps_member = trim($_POST['fourps_member']);
    $consent_given = 1;
    $userId = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;

    // Medical information
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $blood_pressure = trim($_POST['blood_pressure']);
    $bloodType = trim($_POST['blood_type']);
    $allergies = trim($_POST['allergies']);
    $medicalHistory = trim($_POST['medical_history']);
    $currentMedications = trim($_POST['current_medications']);
    $familyHistory = trim($_POST['family_history']);
    $immunizationRecord = trim($_POST['immunization_record']);
    $chronicConditions = trim($_POST['chronic_conditions']);

    if (!empty($fullName) && !empty($dateOfBirth)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Build dynamic INSERT query based on available columns
            $columns = ["full_name", "date_of_birth", "age", "gender", "address", "contact", "last_checkup", "consent_given", "consent_date", "added_by", "user_id"];
            $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "NOW()", "?", "?"];
            $values = [$fullName, $dateOfBirth, $age, $gender, $address, $contact, $lastCheckup, $consent_given, $_SESSION['user']['id'], $userId];

            if ($sitioExists) {
                $columns[] = "sitio";
                $placeholders[] = "?";
                $values[] = $sitio;
            }

            if ($civilStatusExists) {
                $columns[] = "civil_status";
                $placeholders[] = "?";
                $values[] = $civil_status;
            }

            if ($occupationExists) {
                $columns[] = "occupation";
                $placeholders[] = "?";
                $values[] = $occupation;
            }

            if ($phicNoExists) {
                $columns[] = "phic_no";
                $placeholders[] = "?";
                $values[] = $phic_no;
            }

            if ($bhwAssignedExists) {
                $columns[] = "bhw_assigned";
                $placeholders[] = "?";
                $values[] = $bhw_assigned;
            }

            if ($familyNoExists) {
                $columns[] = "family_no";
                $placeholders[] = "?";
                $values[] = $family_no;
            }

            if ($fourpsMemberExists) {
                $columns[] = "fourps_member";
                $placeholders[] = "?";
                $values[] = $fourps_member;
            }

            $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

            // Insert into main patients table
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute($values);
            $patientId = $pdo->lastInsertId();

            // Insert into medical info table
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, temperature, blood_pressure, 
                blood_type, allergies, medical_history, current_medications, 
                family_history, immunization_record, chronic_conditions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId,
                $gender,
                $height,
                $weight,
                $temperature,
                $blood_pressure,
                $bloodType,
                $allergies,
                $medicalHistory,
                $currentMedications,
                $familyHistory,
                $immunizationRecord,
                $chronicConditions
            ]);

            $pdo->commit();

            $_SESSION['success_message'] = 'Patient record added successfully!';
            header('Location: existing_info_patients.php?tab=patients-tab');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding patient record: ' . $e->getMessage();
        }
    } else {
        $error = 'Full name and date of birth are required.';
    }
}

// Handle adding consultation note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consultation_note'])) {
    $patient_id = $_POST['patient_id'];
    $note = trim($_POST['note']);
    $consultation_date = $_POST['consultation_date'];
    $next_consultation_date = !empty($_POST['next_consultation_date']) ? $_POST['next_consultation_date'] : null;
    
    if (!empty($patient_id) && !empty($note) && !empty($consultation_date)) {
        try {
            // Verify patient belongs to current staff member
            $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
            $stmt->execute([$patient_id, $_SESSION['user']['id']]);
            
            if (!$stmt->fetch()) {
                $error = "Patient not found or access denied!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO consultation_notes 
                    (patient_id, note, consultation_date, next_consultation_date, created_by) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $patient_id,
                    $note,
                    $consultation_date,
                    $next_consultation_date,
                    $_SESSION['user']['id']
                ]);
                
                $message = "Consultation note added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error adding consultation note: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields for consultation note.";
    }
}

// Handle PDF Export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    $selectedPatients = isset($_POST['selected_patients']) ? $_POST['selected_patients'] : [];

    if (empty($selectedPatients)) {
        $error = 'Please select at least one patient to export.';
    } else {
        try {
            // Prepare placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($selectedPatients), '?'));

            // COMPREHENSIVE QUERY: Get ALL fields from both tables
            $query = "SELECT 
                p.*,
                e.*,
                CASE 
                    WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                    ELSE 'Regular Patient'
                END as patient_type,
                u.email as user_email,
                u.unique_number
            FROM sitio1_patients p
            LEFT JOIN existing_info_patients e ON p.id = e.patient_id
            LEFT JOIN sitio1_users u ON p.user_id = u.id
            WHERE p.id IN ($placeholders) AND p.added_by = ? AND p.deleted_at IS NULL
            ORDER BY p.full_name ASC";

            $params = array_merge($selectedPatients, [$_SESSION['user']['id']]);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($patients)) {
                $error = "No patients found for export.";
            } else {
                // Store patients in session for PDF generation
                $_SESSION['pdf_export_data'] = $patients;
                
                // Redirect to PDF generator
                header('Location: generate_pdf.php');
                exit();
            }

        } catch (Exception $e) {
            $error = "Error exporting selected patients: " . $e->getMessage();
        }
    }
}

// Handle manual export POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_manual'])) {
    $selectedPatients = isset($_POST['selected_patients']) ? $_POST['selected_patients'] : [];

    if (empty($selectedPatients)) {
        $error = 'Please select at least one patient to export.';
    } else {
        try {
            // Prepare placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($selectedPatients), '?'));

            // COMPREHENSIVE QUERY: Get ALL fields from both tables
            $query = "SELECT 
                p.*,
                e.*,
                CASE 
                    WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                    ELSE 'Regular Patient'
                END as patient_type,
                u.email as user_email,
                u.unique_number
            FROM sitio1_patients p
            LEFT JOIN existing_info_patients e ON p.id = e.patient_id
            LEFT JOIN sitio1_users u ON p.user_id = u.id
            WHERE p.id IN ($placeholders) AND p.added_by = ? AND p.deleted_at IS NULL
            ORDER BY p.full_name ASC";

            $params = array_merge($selectedPatients, [$_SESSION['user']['id']]);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Set filename with current date and time
            $filename = 'Barangay_Luz_Manual_Export_' . date('Y-m-d_His') . '.xls';

            // Clean output
            ob_clean();

            // Output professional Excel format
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            // Generate professional Excel file
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<style>';
            echo 'table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }';
            echo 'th { background-color: #12AF03; color: white; font-weight: bold; padding: 12px; text-align: left; border: 1px solid #ddd; }';
            echo 'td { padding: 10px; border: 1px solid #ddd; vertical-align: top; }';
            echo '.header-row { background-color: #2c3e50; color: white; font-size: 14pt; font-weight: bold; }';
            echo '.info-row { background-color: #f0f9ff; }';
            echo '.selected-count { background-color: #d1fae5; font-weight: bold; }';
            echo '</style>';
            echo '</head>';
            echo '<body>';
            
            // Header
            echo '<table border="1">';
            echo '<tr class="header-row">';
            echo '<td colspan="15" style="text-align: center; padding: 15px;">BARANGAY LUZ HEALTH CENTER - MANUAL PATIENT EXPORT</td>';
            echo '</tr>';
            echo '<tr class="info-row">';
            echo '<td colspan="5">Export Date:</td>';
            echo '<td colspan="5">' . date('F j, Y') . '</td>';
            echo '<td colspan="5">Export Time:</td>';
            echo '<td colspan="5">' . date('h:i A') . '</td>';
            echo '</tr>';
            echo '<tr class="info-row">';
            echo '<td colspan="5">Generated By:</td>';
            echo '<td colspan="5">' . $_SESSION['user']['full_name'] . '</td>';
            echo '<td colspan="5">Total Selected:</td>';
            echo '<td colspan="5" class="selected-count">' . count($selectedPatients) . ' patients</td>';
            echo '</tr>';
            echo '<tr class="selected-count">';
            echo '<td colspan="15" style="text-align: center; padding: 10px;">';
            echo '✅ SUCCESSFUL MANUAL EXPORT - ' . count($selectedPatients) . ' PATIENTS SELECTED';
            echo '</td>';
            echo '</tr>';
            echo '<tr><td colspan="15">&nbsp;</td></tr>';
            
            // Main data table
            echo '<tr>';
            echo '<th>No.</th>';
            echo '<th>Patient ID</th>';
            echo '<th>Full Name</th>';
            echo '<th>Date of Birth</th>';
            echo '<th>Age</th>';
            echo '<th>Gender</th>';
            echo '<th>Sitio</th>';
            echo '<th>Civil Status</th>';
            echo '<th>Occupation</th>';
            echo '<th>Contact</th>';
            echo '<th>Blood Type</th>';
            echo '<th>Height (cm)</th>';
            echo '<th>Weight (kg)</th>';
            echo '<th>Last Checkup</th>';
            echo '<th>Patient Type</th>';
            echo '</tr>';
            
            $counter = 1;
            foreach ($patients as $patient) {
                echo '<tr>';
                echo '<td>' . $counter++ . '</td>';
                echo '<td>' . ($patient['id'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['full_name'] ?? '') . '</td>';
                echo '<td>' . (!empty($patient['date_of_birth']) ? date('Y-m-d', strtotime($patient['date_of_birth'])) : '') . '</td>';
                echo '<td>' . ($patient['age'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['gender'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['sitio'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['civil_status'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['occupation'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($patient['contact'] ?? '') . '</td>';
                echo '<td style="font-weight: bold; color: #e74c3c;">' . htmlspecialchars($patient['blood_type'] ?? '') . '</td>';
                echo '<td>' . ($patient['height'] ?? '') . '</td>';
                echo '<td>' . ($patient['weight'] ?? '') . '</td>';
                echo '<td>' . (!empty($patient['last_checkup']) ? date('Y-m-d', strtotime($patient['last_checkup'])) : '') . '</td>';
                echo '<td>' . ($patient['patient_type'] ?? '') . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // Summary section
            echo '<br><br>';
            echo '<table border="1">';
            echo '<tr class="header-row">';
            echo '<td colspan="3" style="text-align: center; padding: 10px;">EXPORT SUMMARY</td>';
            echo '</tr>';
            echo '<tr class="info-row">';
            echo '<td><strong>Export Type:</strong></td>';
            echo '<td colspan="2">Manual Patient Selection</td>';
            echo '</tr>';
            echo '<tr class="info-row">';
            echo '<td><strong>Selection Criteria:</strong></td>';
            echo '<td colspan="2">Manually selected by staff member</td>';
            echo '</tr>';
            echo '<tr class="info-row">';
            echo '<td><strong>Data Included:</strong></td>';
            echo '<td colspan="2">Complete patient health records with medical information</td>';
            echo '</tr>';
            echo '</table>';
            
            // Footer
            echo '<br><br>';
            echo '<table border="0">';
            echo '<tr>';
            echo '<td style="font-size: 9pt; color: #666; padding-top: 20px; border-top: 2px solid #12AF03;">';
            echo '<strong>EXPORT COMPLETE:</strong> This export contains ' . count($patients) . ' patient records selected manually.<br>';
            echo 'File generated: ' . $filename . '<br>';
            echo '© ' . date('Y') . ' Barangay Luz Health Center. Confidential patient information.';
            echo '</td>';
            echo '</tr>';
            echo '</table>';
            
            echo '</body></html>';

            // Exit immediately
            exit();

        } catch (Exception $e) {
            $error = "Error exporting selected patients: " . $e->getMessage();
        }
    }
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    try {
        $patientType = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'all';
        $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
        $searchBy = isset($_GET['search_by']) ? trim($_GET['search_by']) : 'name';

        // COMPREHENSIVE QUERY for All Records export
        $query = "SELECT 
            p.*,
            e.*,
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                ELSE 'Regular Patient'
            END as patient_type,
            u.email as user_email,
            u.unique_number
        FROM sitio1_patients p
        LEFT JOIN existing_info_patients e ON p.id = e.patient_id
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.added_by = ? AND p.deleted_at IS NULL";

        // Add search filters if search term exists
        $params = [$_SESSION['user']['id']];
        if (!empty($searchTerm)) {
            if ($searchBy === 'unique_number') {
                $query .= " AND EXISTS (
                    SELECT 1 FROM sitio1_users u 
                    WHERE u.id = p.user_id AND u.unique_number LIKE ?
                )";
                $params[] = "%$searchTerm%";
            } else {
                $query .= " AND p.full_name LIKE ?";
                $params[] = "%$searchTerm%";
            }
        }

        // Add patient type filter
        if ($patientType == 'registered') {
            $query .= " AND p.user_id IS NOT NULL";
        } elseif ($patientType == 'regular') {
            $query .= " AND p.user_id IS NULL";
        }

        $query .= " ORDER BY p.full_name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set filename
        $filename = 'Barangay_Luz_Health_Center_Patient_Records_' . date('Y-m-d');
        if ($patientType == 'registered') {
            $filename = 'Registered_Patients_Export_' . date('Y-m-d');
        } elseif ($patientType == 'regular') {
            $filename = 'Regular_Patients_Export_' . date('Y-m-d');
        }
        if (!empty($searchTerm)) {
            $filename .= '_search_' . substr($searchTerm, 0, 20);
        }
        $filename .= '.xls';

        // Clean output
        ob_clean();

        // Output Excel content with professional design
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Generate professional Excel file with styling
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }';
        echo 'th { background-color: #3498db; color: white; font-weight: bold; padding: 12px; text-align: left; border: 1px solid #ddd; }';
        echo 'td { padding: 10px; border: 1px solid #ddd; vertical-align: top; }';
        echo '.header-row { background-color: #2c3e50; color: white; font-size: 14pt; font-weight: bold; }';
        echo '.section-header { background-color: #f8f9fa; color: #2c3e50; font-weight: bold; font-size: 12pt; }';
        echo '.info-row { background-color: #f0f9ff; }';
        echo '.summary-row { background-color: #e8f5e8; font-weight: bold; }';
        echo '.date-cell { mso-number-format:"Short Date"; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // Header section
        echo '<table border="1">';
        echo '<tr class="header-row">';
        echo '<td colspan="12" style="text-align: center; padding: 15px;">BARANGAY LUZ HEALTH CENTER - PATIENT RECORDS EXPORT</td>';
        echo '</tr>';
        echo '<tr class="section-header">';
        echo '<td colspan="12">Export Information</td>';
        echo '</tr>';
        echo '<tr class="info-row">';
        echo '<td colspan="3">Generated On:</td>';
        echo '<td colspan="3">' . date('F j, Y h:i A') . '</td>';
        echo '<td colspan="3">Generated By:</td>';
        echo '<td colspan="3">' . $_SESSION['user']['full_name'] . '</td>';
        echo '</tr>';
        echo '<tr class="info-row">';
        echo '<td colspan="3">Total Records:</td>';
        echo '<td colspan="3">' . count($patients) . '</td>';
        echo '<td colspan="3">Export Type:</td>';
        echo '<td colspan="3">' . ucfirst($patientType) . ' Patients</td>';
        echo '</tr>';
        echo '<tr><td colspan="12">&nbsp;</td></tr>';
        
        // Column headers
        echo '<tr>';
        echo '<th>No.</th>';
        echo '<th>Patient ID</th>';
        echo '<th>Full Name</th>';
        echo '<th>Date of Birth</th>';
        echo '<th>Age</th>';
        echo '<th>Gender</th>';
        echo '<th>Contact</th>';
        echo '<th>Address</th>';
        echo '<th>Sitio</th>';
        echo '<th>Blood Type</th>';
        echo '<th>Last Checkup</th>';
        echo '<th>Patient Type</th>';
        echo '</tr>';
        
        // Data rows
        $counter = 1;
        foreach ($patients as $patient) {
            echo '<tr>';
            echo '<td>' . $counter++ . '</td>';
            echo '<td>' . ($patient['id'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($patient['full_name'] ?? '') . '</td>';
            echo '<td class="date-cell">' . (!empty($patient['date_of_birth']) ? $patient['date_of_birth'] : '') . '</td>';
            echo '<td>' . ($patient['age'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($patient['gender'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($patient['contact'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($patient['address'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($patient['sitio'] ?? '') . '</td>';
            echo '<td style="font-weight: bold; color: #e74c3c;">' . htmlspecialchars($patient['blood_type'] ?? 'N/A') . '</td>';
            echo '<td class="date-cell">' . (!empty($patient['last_checkup']) ? $patient['last_checkup'] : '') . '</td>';
            echo '<td>' . ($patient['patient_type'] ?? 'Regular') . '</td>';
            echo '</tr>';
        }
        
        // Summary row
        echo '<tr class="summary-row">';
        echo '<td colspan="6">Total Patients:</td>';
        echo '<td colspan="6">' . count($patients) . '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Additional detailed information sheet
        echo '<br><br><br>';
        echo '<table border="1">';
        echo '<tr class="header-row">';
        echo '<td colspan="8" style="text-align: center; padding: 15px;">DETAILED MEDICAL INFORMATION</td>';
        echo '</tr>';
        echo '<tr class="section-header">';
        echo '<td>Patient Name</td>';
        echo '<td>Height (cm)</td>';
        echo '<td>Weight (kg)</td>';
        echo '<td>BMI</td>';
        echo '<td>Blood Pressure</td>';
        echo '<td>Temperature</td>';
        echo '<td>Allergies</td>';
        echo '<td>Chronic Conditions</td>';
        echo '</tr>';
        
        foreach ($patients as $patient) {
            $height = $patient['height'] ?? 0;
            $weight = $patient['weight'] ?? 0;
            $bmi = ($height > 0 && $weight > 0) ? number_format($weight / (($height/100) * ($height/100)), 2) : 'N/A';
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($patient['full_name'] ?? '') . '</td>';
            echo '<td>' . ($height ?: 'N/A') . '</td>';
            echo '<td>' . ($weight ?: 'N/A') . '</td>';
            echo '<td>' . $bmi . '</td>';
            echo '<td>' . htmlspecialchars($patient['blood_pressure'] ?? 'N/A') . '</td>';
            echo '<td>' . ($patient['temperature'] ? $patient['temperature'] . '°C' : 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars(substr($patient['allergies'] ?? 'None', 0, 50)) . '</td>';
            echo '<td>' . htmlspecialchars(substr($patient['chronic_conditions'] ?? 'None', 0, 50)) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Footer
        echo '<br><br>';
        echo '<table border="0">';
        echo '<tr>';
        echo '<td colspan="4" style="font-size: 9pt; color: #666; padding-top: 20px; border-top: 1px solid #ddd;">';
        echo '<strong>CONFIDENTIALITY NOTICE:</strong> This document contains confidential patient health information. ';
        echo 'Unauthorized disclosure, copying, or distribution is prohibited.<br>';
        echo '© ' . date('Y') . ' Barangay Luz Health Center. All rights reserved.';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '</body></html>';

        // Exit immediately to prevent any other output
        exit();

    } catch (Exception $e) {
        $error = "Error exporting to Excel: " . $e->getMessage();
        error_log("Excel Export Error: " . $e->getMessage());
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle patient deletion
if (isset($_GET['delete_patient'])) {
    $patientId = $_GET['delete_patient'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get patient data including user_id
        $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ? AND added_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            // Get column information from deleted_patients table
            $stmt = $pdo->prepare("SHOW COLUMNS FROM deleted_patients");
            $stmt->execute();
            $deletedTableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Filter columns that exist in both source and destination
            $columns = [];
            $placeholders = [];
            $values = [];

            foreach ($patient as $column => $value) {
                if ($column === 'id') {
                    $columns[] = 'original_id';
                    $placeholders[] = '?';
                    $values[] = $value;
                    continue;
                }

                if (
                    in_array($column, $deletedTableColumns) &&
                    !in_array($column, ['id', 'deleted_at'])
                ) {
                    $columns[] = $column;
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }

            // Add deleted_by column
            $columns[] = 'deleted_by';
            $placeholders[] = '?';
            $values[] = $_SESSION['user']['id'];

            $insertQuery = "INSERT INTO deleted_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

            // Insert into deleted_patients table
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute($values);

            // Delete from main table
            $stmt = $pdo->prepare("DELETE FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$patientId]);

            // Delete health info
            $stmt = $pdo->prepare("DELETE FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patientId]);

            $pdo->commit();

            $_SESSION['success_message'] = 'Patient record moved to archive successfully!';
            header('Location: existing_info_patients.php');
            exit();
        } else {
            $error = 'Patient not found!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error deleting patient record: ' . $e->getMessage();
    }
}

// Handle patient restoration
if (isset($_GET['restore_patient'])) {
    $patientId = $_GET['restore_patient'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get archived patient data including ALL medical info
        $stmt = $pdo->prepare("
            SELECT 
                dp.*,
                eip.gender as health_gender,
                eip.height,
                eip.weight,
                eip.temperature,
                eip.blood_pressure,
                eip.blood_type,
                eip.allergies,
                eip.medical_history,
                eip.current_medications,
                eip.family_history,
                eip.immunization_record,
                eip.chronic_conditions,
                eip.updated_at as health_updated
            FROM deleted_patients dp
            LEFT JOIN existing_info_patients eip ON dp.original_id = eip.patient_id
            WHERE dp.original_id = ? AND dp.deleted_by = ?
        ");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $archivedPatient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($archivedPatient) {
            // Check if patient already exists in main table
            $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
            $stmt->execute([$patientId, $_SESSION['user']['id']]);
            $existingPatient = $stmt->fetch();

            if ($existingPatient) {
                $error = 'This patient already exists in the active records!';
            } else {
                // Get column information from sitio1_patients table
                $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients");
                $stmt->execute();
                $mainTableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Prepare data for restoration
                $columns = [];
                $placeholders = [];
                $values = [];

                // Map archived data to main table columns
                foreach ($archivedPatient as $column => $value) {
                    // Skip columns that don't exist in sitio1_patients
                    if (!in_array($column, $mainTableColumns)) {
                        continue;
                    }

                    // Skip metadata columns from deleted_patients
                    if (in_array($column, ['deleted_by', 'deleted_at', 'id', 'created_at'])) {
                        continue;
                    }

                    // Map original_id back to id
                    if ($column === 'original_id') {
                        $columns[] = 'id';
                        $placeholders[] = "?";
                        $values[] = $value;
                        continue;
                    }

                    $columns[] = $column;
                    $placeholders[] = "?";
                    $values[] = $value;
                }

                // Add restored timestamp
                $columns[] = 'restored_at';
                $placeholders[] = "NOW()";

                $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

                // Restore to main patients table with original ID
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute($values);

                // Restore medical info if it exists
                if (!empty($archivedPatient['health_gender'])) {
                    $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                        (patient_id, gender, height, weight, temperature, blood_pressure, 
                         blood_type, allergies, medical_history, current_medications, 
                         family_history, immunization_record, chronic_conditions, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                            gender = VALUES(gender),
                            height = VALUES(height),
                            weight = VALUES(weight),
                            temperature = VALUES(temperature),
                            blood_pressure = VALUES(blood_pressure),
                            blood_type = VALUES(blood_type),
                            allergies = VALUES(allergies),
                            medical_history = VALUES(medical_history),
                            current_medications = VALUES(current_medications),
                            family_history = VALUES(family_history),
                            immunization_record = VALUES(immunization_record),
                            chronic_conditions = VALUES(chronic_conditions),
                            updated_at = VALUES(updated_at)");

                    $stmt->execute([
                        $patientId,
                        $archivedPatient['health_gender'],
                        $archivedPatient['height'] ?? null,
                        $archivedPatient['weight'] ?? null,
                        $archivedPatient['temperature'] ?? null,
                        $archivedPatient['blood_pressure'] ?? null,
                        $archivedPatient['blood_type'] ?? null,
                        $archivedPatient['allergies'] ?? null,
                        $archivedPatient['medical_history'] ?? null,
                        $archivedPatient['current_medications'] ?? null,
                        $archivedPatient['family_history'] ?? null,
                        $archivedPatient['immunization_record'] ?? null,
                        $archivedPatient['chronic_conditions'] ?? null,
                        $archivedPatient['health_updated'] ?? date('Y-m-d H:i:s')
                    ]);
                }

                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE original_id = ?");
                $stmt->execute([$patientId]);

                $pdo->commit();

                $_SESSION['success_message'] = 'Patient record restored successfully! All data has been recovered.';
                header('Location: deleted_patients.php');
                exit();
            }
        } else {
            $error = 'Archived patient not found!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error restoring patient record: ' . $e->getMessage();
    }
}

// Handle converting user to patient
if (isset($_GET['convert_to_patient'])) {
    $userId = $_GET['convert_to_patient'];

    try {
        // Get user details including gender and sitio
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ? AND approved = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if user already exists as a patient
            $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE user_id = ? AND added_by = ?");
            $stmt->execute([$userId, $_SESSION['user']['id']]);
            $existingPatient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingPatient) {
                $error = 'This user is already registered as a patient.';
            } else {
                // Start transaction
                $pdo->beginTransaction();

                // Get gender from user table
                $userGender = '';
                if (!empty($user['gender'])) {
                    $userGender = $user['gender'];
                    if ($userGender === 'male')
                        $userGender = 'Male';
                    if ($userGender === 'female')
                        $userGender = 'Female';
                    if ($userGender === 'other')
                        $userGender = 'Other';
                }

                // Build dynamic INSERT query for conversion based on available columns
                $columns = ["full_name", "date_of_birth", "age", "gender", "address", "contact", "added_by", "user_id", "consent_given", "consent_date"];
                $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "1", "NOW()"];
                $values = [
                    $user['full_name'],
                    $user['date_of_birth'],
                    $user['age'],
                    $userGender,
                    $user['address'],
                    $user['contact'],
                    $_SESSION['user']['id'],
                    $userId
                ];

                // Add optional columns only if they exist in the user data
                if ($sitioExists && isset($user['sitio'])) {
                    $columns[] = "sitio";
                    $placeholders[] = "?";
                    $values[] = $user['sitio'];
                }

                if ($civilStatusExists && isset($user['civil_status'])) {
                    $columns[] = "civil_status";
                    $placeholders[] = "?";
                    $values[] = $user['civil_status'];
                }

                if ($occupationExists && isset($user['occupation'])) {
                    $columns[] = "occupation";
                    $placeholders[] = "?";
                    $values[] = $user['occupation'];
                }

                if ($phicNoExists && isset($user['phic_no'])) {
                    $columns[] = "phic_no";
                    $placeholders[] = "?";
                    $values[] = $user['phic_no'];
                }

                if ($bhwAssignedExists && isset($user['bhw_assigned'])) {
                    $columns[] = "bhw_assigned";
                    $placeholders[] = "?";
                    $values[] = $user['bhw_assigned'];
                }

                if ($familyNoExists && isset($user['family_no'])) {
                    $columns[] = "family_no";
                    $placeholders[] = "?";
                    $values[] = $user['family_no'];
                }

                if ($fourpsMemberExists && isset($user['fourps_member'])) {
                    $columns[] = "fourps_member";
                    $placeholders[] = "?";
                    $values[] = $user['fourps_member'];
                }

                $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

                // Insert into main patients table with gender from user
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute($values);
                $patientId = $pdo->lastInsertId();

                // Insert medical info with gender from user
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type) 
                    VALUES (?, ?, 0, 0, '')");
                $stmt->execute([$patientId, $userGender]);

                $pdo->commit();

                $_SESSION['success_message'] = 'User converted to patient successfully!';
                header('Location: existing_info_patients.php?tab=patients-tab');
                exit();
            }
        } else {
            $error = 'User not found or not approved!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error converting user to patient: ' . $e->getMessage();
    }
}

// Get search term if exists
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchBy = isset($_GET['search_by']) ? trim($_GET['search_by']) : 'name';

// Get patient type filter
$patientTypeFilter = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'all';

// Check if manual selection mode is active
$manualSelectMode = isset($_GET['manual_select']) && $_GET['manual_select'] == 'true';

// Get patient ID if selected
$selectedPatientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_POST['patient_id']) ? $_POST['patient_id'] : '');

// Get list of patients matching search
$patients = [];
$searchedUsers = [];
if (!empty($searchTerm)) {
    try {
        if ($searchBy === 'unique_number') {
            $query = "SELECT u.id, u.full_name, u.email, u.date_of_birth, u.age, u.gender,
                             u.civil_status, u.occupation, u.address, u.sitio, u.contact, 
                             u.unique_number, 'user' as type
                      FROM sitio1_users u 
                      WHERE u.approved = 1 AND u.unique_number LIKE ? 
                      ORDER BY u.full_name LIMIT 10";

            $stmt = $pdo->prepare($query);
            $stmt->execute(["%$searchTerm%"]);
            $searchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $selectQuery = "SELECT p.id, p.full_name, p.date_of_birth, p.age, 
                                   p.gender, p.sitio, p.civil_status, p.occupation,
                                   p.phic_no, p.bhw_assigned, p.family_no, p.fourps_member,
                                   e.blood_type, e.height, e.weight, e.temperature, e.blood_pressure
                            FROM sitio1_patients p 
                            LEFT JOIN existing_info_patients e ON p.id = e.patient_id 
                            WHERE p.added_by = ? AND p.deleted_at IS NULL AND p.full_name LIKE ? 
                            ORDER BY p.full_name LIMIT 10";

            $stmt = $pdo->prepare($selectQuery);
            $stmt->execute([$_SESSION['user']['id'], "%$searchTerm%"]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching patients: " . $e->getMessage();
    }
}

// Check if we're viewing all records
$viewAll = isset($_GET['view_all']) && $_GET['view_all'] == 'true';

// Pagination setup
$recordsPerPage = 5;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Get total count of patients based on filter
try {
    $countQuery = "SELECT COUNT(*) as total FROM sitio1_patients p 
                   WHERE p.added_by = ? AND p.deleted_at IS NULL";

    if ($patientTypeFilter == 'registered') {
        $countQuery .= " AND p.user_id IS NOT NULL";
    } elseif ($patientTypeFilter == 'regular') {
        $countQuery .= " AND p.user_id IS NULL";
    }

    $stmt = $pdo->prepare($countQuery);
    $stmt->execute([$_SESSION['user']['id']]);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
} catch (PDOException $e) {
    $error = "Error counting patient records: " . $e->getMessage();
    $totalRecords = 0;
    $totalPages = 1;
}

// Get all patients with their medical info for the patient list with pagination or all records
try {
    $selectQuery = "SELECT 
            p.id,
            p.full_name,
            COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth,
            p.age,
            COALESCE(e.gender, p.gender) as gender,
            p.sitio,
            p.civil_status,
            p.occupation,
            p.phic_no,
            p.bhw_assigned,
            p.family_no,
            p.fourps_member,
            p.user_id,
            e.blood_type,
            e.height, e.weight, e.temperature, e.blood_pressure,
            e.allergies, e.immunization_record, e.chronic_conditions,
            e.medical_history, e.current_medications, e.family_history,
            u.unique_number,
            u.email as user_email,
            u.sitio as user_sitio,
            u.civil_status as user_civil_status,
            u.occupation as user_occupation,
            u.gender as user_gender,
            u.date_of_birth as user_date_of_birth,
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                ELSE 'Regular Patient'
            END as patient_type
        FROM sitio1_patients p
        LEFT JOIN existing_info_patients e ON p.id = e.patient_id
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.added_by = ? AND p.deleted_at IS NULL";

    if ($patientTypeFilter == 'registered') {
        $selectQuery .= " AND p.user_id IS NOT NULL";
    } elseif ($patientTypeFilter == 'regular') {
        $selectQuery .= " AND p.user_id IS NULL";
    }

    $selectQuery .= " ORDER BY p.created_at DESC";

    if (!$viewAll && !$manualSelectMode) {
        $selectQuery .= " LIMIT ? OFFSET ?";
    }

    $stmt = $pdo->prepare($selectQuery);

    if ($viewAll || $manualSelectMode) {
        $stmt->execute([$_SESSION['user']['id']]);
    } else {
        $stmt->bindParam(1, $_SESSION['user']['id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $recordsPerPage, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $allPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching patient records: " . $e->getMessage();
}

// Get existing health info if patient is selected
if (!empty($selectedPatientId)) {
    try {
        $stmt = $pdo->prepare("SELECT 
            p.*, 
            u.unique_number, 
            u.email as user_email,
            CASE 
                WHEN u.gender = 'male' THEN 'Male'
                WHEN u.gender = 'female' THEN 'Female'
                WHEN u.gender = 'other' THEN 'Other'
                ELSE u.gender
            END as user_gender
          FROM sitio1_patients p 
          LEFT JOIN sitio1_users u ON p.user_id = u.id
          WHERE p.id = ? AND p.added_by = ?");
        $stmt->execute([$selectedPatientId, $_SESSION['user']['id']]);
        $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient_details) {
            $error = "Patient not found!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$selectedPatientId]);
            $health_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($health_info && empty($health_info['gender']) && !empty($patient_details['gender'])) {
                $health_info['gender'] = $patient_details['gender'];
            } elseif (!$health_info && !empty($patient_details['gender'])) {
                $health_info = ['gender' => $patient_details['gender']];
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as note_count FROM consultation_notes WHERE patient_id = ?");
            $stmt->execute([$selectedPatientId]);
            $note_count = $stmt->fetch(PDO::FETCH_ASSOC)['note_count'];
        }
    } catch (PDOException $e) {
        $error = "Error fetching health information: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Health Records - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/asssets/css/normalize.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body,
        .tab-btn,
        .patient-card,
        .modal,
        .patient-table,
        .btn-view,
        .btn-archive,
        .btn-add-patient,
        .btn-primary,
        .btn-success,
        .btn-gray,
        .btn-print,
        .btn-edit,
        .btn-save-medical,
        .pagination-btn,
        .btn-view-all,
        .btn-back-to-pagination,
        #modalContent input,
        #modalContent select,
        #modalContent textarea,
        .search-input,
        .search-select {
            font-family: 'Poppins', sans-serif !important;
        }

        .form-section-title-modal {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 700 !important;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .patient-card {
            transition: all 0.3s ease;
        }

        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .modal {
            transition: opacity 0.3s ease;
        }

        .required-field::after {
            content: " *";
            color: #e74c3c;
        }

        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-table th,
        .patient-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .patient-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #2c3e50;
        }

        .patient-table tr:hover {
            background-color: #f1f5f9;
        }

        .patient-id {
            font-weight: bold;
            color: #3498db;
        }

        .user-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .regular-badge {
            background-color: #f0fdf4;
            color: #065f46;
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-export {
            background-color: #12AF03;
            color: #ffffffff;
            border-radius: 30px;
            padding: 15px 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-export:hover {
            background-color: #12AF03;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .manual-export-controls {
            background-color: #f0f9ff;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .checkbox-column {
            width: 50px;
            text-align: center;
        }

        .select-all-checkbox {
            margin-right: 10px;
        }

        .patient-type-filter {
            border: 2px solid #55b2f0ff;
            background-color: white;
            border-radius: 10px;
            padding: 12px 20px;
            min-height: 55px;
            font-size: 16px;
            width: 100%;
            max-width: 355px;
        }

        .patient-type-filter:focus {
            border-color: #84c0e9ff;
            box-shadow: 0 0 0 3px #8acdfaff;
            outline: none;
        }

        .btn-view {
            background-color: #3498db;
            color: #ffffffff;
            border: 2px solid #3498db;
            border-radius: 30px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view:hover {
            background-color: #50a4dbff;
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        .btn-archive {
            background-color: #e74c3c;
            color: white;
            border-radius: 30px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-archive:hover {
            background-color: #d86154ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
        }

        .btn-add-patient {
            background-color: #2ecc71;
            color: #ffffffff;
            border: 2px solid #2ecc71;
            border-radius: 50px;
            padding: 17px 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add-patient:hover {
            background-color: #42d37eff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
        }

        .btn-primary {
            background-color: #3498db;
            color: #ffffffff;
            border-radius: 30px;
            padding: 15px 30px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 55px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .btn-primary:hover {
            background-color: #55a3d8ff;
            color: #ffffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        .btn-success {
            background-color: white;
            color: #2ecc71;
            border: 2px solid #2ecc71;
            border-radius: 8px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 55px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .btn-success:hover {
            background-color: #f0fdf4;
            border-color: #2ecc71;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
        }

        .btn-gray {
            background-color: white;
            color: #36a9dfff;
            border: 2px solid #36a9dfff;
            border-radius: 30px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 55px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .btn-gray:hover {
            background-color: #f9fafb;
            border-color: #36a9dfff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.15);
        }

        .btn-print {
            background-color: #3498db;
            color: #ffffffff;
            border-radius: 30px;
            padding: 14px 30px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            width: auto;
            margin: 8px 0;
        }

        .btn-print:hover {
            background-color: #2563EB;
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        .btn-edit {
            background-color: white;
            color: #f39c12;
            border: 2px solid #f39c12;
            border-radius: 8px;
            padding: 14px 28px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            width: 355px;
            margin: 8px 0;
        }

        .btn-edit:hover {
            background-color: #fef3c7;
            border-color: #f39c12;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.15);
        }

        .btn-save-medical {
            background-color: #50a4dbff;
            color: #ffffffff;
            border-radius: 30px;
            padding: 14px 28px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            line-height: 2px;
            width: auto;
            margin: 8px 0;
        }

        .btn-save-medical:hover {
            background-color: #59ace4ff;
            transform: translateY(-2px);
        }

        .btn-add-note {
            background-color: #f39c12;
            color: white;
            border-radius: 30px;
            padding: 14px 28px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            line-height: 2px;
            width: auto;
            margin: 8px 0;
        }

        .btn-add-note:hover {
            background-color: #f59e1aff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.15);
        }

        .btn-view-notes {
            background-color: #9b59b6;
            color: white;
            border-radius: 30px;
            padding: 14px 28px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            line-height: 2px;
            width: auto;
            margin: 8px 0;
        }

        .btn-view-notes:hover {
            background-color: #a56cc1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.15);
        }

        #viewModal {
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }

        #viewModal>div {
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        #viewModal[style*="display: flex"]>div {
            transform: scale(1);
        }

        #viewModal ::-webkit-scrollbar {
            width: 8px;
        }

        #viewModal ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        #viewModal ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        #viewModal ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        #modalContent input:not([type="checkbox"]):not([type="radio"]),
        #modalContent select,
        #modalContent textarea {
            min-height: 48px;
            font-size: 16px;
        }

        #modalContent .grid {
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            #viewModal>div {
                margin: 1rem;
                max-height: calc(100vh - 2rem);
            }
        }

        @media (max-width: 768px) {
            #viewModal>div {
                margin: 0.5rem;
                max-height: calc(100vh - 1rem);
            }

            #viewModal .p-8 {
                padding: 1.5rem;
            }
        }

        .custom-notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .visit-type-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .visit-type-checkup {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .visit-type-consultation {
            background-color: #fef3c7;
            color: #92400e;
        }

        .visit-type-emergency {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .visit-type-followup {
            background-color: #d1fae5;
            color: #065f46;
        }

        .readonly-field {
            background-color: #f9fafb;
            cursor: not-allowed;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem 0;
            border-top: 1px solid #e2e8f0;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.8rem;
            flex-grow: 1;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 30px;
            background-color: white;
            border: 1px solid #3498db;
            color: #4b5563;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .pagination-btn:hover {
            background-color: #f0f9ff;
            border-color: #3498db;
            color: #374151;
        }

        .pagination-btn.active {
            background-color: white;
            color: #3498db;
            border: 2px solid #3498db;
            font-weight: 600;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.disabled:hover {
            background-color: white;
            color: #4b5563;
            border-color: #3498db;
            opacity: 0.5;
        }

        .pagination-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-view-all {
            background-color: white;
            color: #3498db;
            border: 2px solid #3498db;
            border-radius: 30px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            min-height: 55px;
            font-size: 16px;
        }

        .btn-view-all:hover {
            background-color: #ffffffff;
            border: 2px solid #479ed8ff;
            transform: translateY(-2px);
        }

        .btn-back-to-pagination {
            background-color: white;
            color: #3498db;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 12px 24px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            min-height: 55px;
            font-size: 16px;
            margin-bottom: 1rem;
        }

        .btn-back-to-pagination:hover {
            background-color: #f0f9ff;
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        .scrollable-table-container {
            max-height: 70vh;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .scrollable-table-container table {
            margin-bottom: 0;
        }

        .field-empty {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
        }

        .field-filled {
            background-color: #f0f9ff !important;
            border-color: #bae6fd !important;
        }

        .btn-disabled {
            background-color: #f9fafb !important;
            color: #9ca3af !important;
            border-color: #e5e7eb !important;
            cursor: not-allowed !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-disabled:hover {
            background-color: #f9fafb !important;
            color: #9ca3af !important;
            border-color: #e5e7eb !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .form-input-modal {
            border-radius: 8px !important;
            padding: 16px 20px !important;
            border: 1px solid #85ccfb !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            min-height: 52px;
            background-color: white;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .form-input-modal:focus {
            outline: none;
            border-color: #3498db !important;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .form-select-modal {
            border-radius: 8px !important;
            padding: 16px 20px !important;
            border: 1px solid #85ccfb !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 20px center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 50px;
            min-height: 52px;
            background-color: white;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .form-select-modal:focus {
            outline: none;
            border-color: #3498db !important;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .form-textarea-modal {
            border-radius: 8px !important;
            padding: 16px 20px !important;
            border: 1px solid #85ccfb !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            resize: vertical;
            min-height: 120px;
            background-color: white;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .form-textarea-modal:focus {
            outline: none;
            border-color: #3498db !important;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .form-label-modal {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .form-section-title-modal {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f0f9ff;
            display: flex;
            align-items: center;
        }

        .form-section-title-modal i {
            margin-right: 10px;
            font-size: 1.1em;
            color: #3498db;
        }

        #addPatientModal {
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }

        #addPatientModal>div {
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        #addPatientModal[style*="display: flex"]>div {
            transform: scale(1);
        }

        .modal-header {
            background-color: white;
            border-bottom: 2px solid #f0f9ff;
        }

        .modal-grid-gap {
            gap: 1.25rem !important;
        }

        .tab-btn {
            position: relative;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #3498db;
            border-bottom-color: #3498db;
            background-color: #f0f9ff;
        }

        .tab-btn:hover:not(.active) {
            color: #3498db;
            background-color: #f8fafc;
        }

        .search-input {
            border: 2px solid #55b2f0ff !important;
            background-color: white !important;
            transition: all 0.3s ease;
            border-radius: 10px !important;
            padding: 16px 20px 16px 55px !important;
            min-height: 55px !important;
            font-size: 16px;
            width: 355px !important;
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .search-input:focus {
            border-color: #84c0e9ff !important;
            box-shadow: 0 0 0 3px #8acdfaff;
        }

        .search-select {
            border: 2px solid #55b2f0ff !important;
            background-color: white !important;
            transition: all 0.3s ease;
            border-radius: 10px !important;
            padding: 16px 20px !important;
            min-height: 55px !important;
            font-size: 16px;
            width: 355px !important;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233498db' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 20px center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 50px;

        }

        .search-select:focus {
            border-color: #84c0e9ff !important;
            box-shadow: 0 0 0 3px #8acdfaff;
        }

        .search-icon-container {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            pointer-events: none;
        }

        .patient-table th {
            background-color: #f0f9ff;
            color: #2c3e50;
            border-bottom: 2px solid #e2e8f0;
        }

        .patient-table tr:hover {
            background-color: #f8fafc;
        }

        .main-container {
            background-color: white;
            border: 1px solid #f0f9ff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .section-bg {
            background-color: white;
            border: 1px solid #f0f9ff;
            border-radius: 12px;
        }

        .alert-success {
            background-color: #f0fdf4;
            border: 2px solid #bbf7d0;
            color: #065f46;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 2px solid #fecaca;
            color: #b91c1c;
        }

        .search-form-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }

        .search-field-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .search-form-container {
                flex-direction: row;
                align-items: flex-end;
                gap: 1.5rem;
            }

            .search-field-group {
                width: auto;
            }
        }

        * {
            --tw-border-opacity: 1 !important;
        }

        .btn-success,
        .btn-print,
        .btn-edit,
        .btn-view-all,
        .btn-back-to-pagination,
        .search-input,
        .search-select {
            border-style: solid !important;
        }

        .btn-archive:hover,
        .btn-success:hover,
        .btn-edit:hover,
        .btn-back-to-pagination:hover,
        .search-input:focus,
        .search-select:focus {
            border-color: inherit !important;
        }

        .export-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            min-width: 220px;
            z-index: 100;
            margin-top: 5px;
        }

        .export-options.show {
            display: block;
        }

        .export-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            border: none;
            background: none;
            color: #374151;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }

        .export-option:last-child {
            border-bottom: none;
        }

        .export-option:hover {
            background-color: #f0f9ff;
            color: #3498db;
        }

        .export-option i {
            margin-right: 8px;
            width: 20px;
        }

        .export-btn-wrapper {
            position: relative;
            display: inline-block;
        }

        .patient-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .manual-selection-header {
            background-color: #f0f9ff;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .manual-export-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .consultation-note-item {
            background-color: white;
            border-left: 4px solid #3498db;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .consultation-note-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .note-badge {
            background-color: #e74c3c;
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .consultation-note-date {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .consultation-note-content {
            color: #374151;
            line-height: 1.5;
            margin: 10px 0;
        }

        .consultation-note-next-date {
            font-size: 12px;
            color: #10b981;
            margin-top: 8px;
            padding: 4px 8px;
            background-color: #d1fae5;
            border-radius: 4px;
            display: inline-block;
        }

        .loading-notes {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #consultationNoteModal input[type="date"],
        #consultationNoteModal textarea {
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            font-size: 16px;
        }

        #consultationNoteModal input[type="date"]:focus,
        #consultationNoteModal textarea:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .consultation-note-item {
            background-color: white;
            border-left: 4px solid #3498db;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .consultation-note-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .note-preview {
            color: #666;
            line-height: 1.5;
            margin: 10px 0;
            font-size: 14px;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .note-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .note-action-btn {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .note-action-btn.view {
            background-color: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .note-action-btn.view:hover {
            background-color: #bae6fd;
        }

        .note-action-btn.use {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .note-action-btn.use:hover {
            background-color: #a7f3d0;
        }

        .note-meta {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f3f4f6;
        }
        
        /* Consultation Notes Section Styles */
        .consultation-notes-section {
            margin-bottom: 2rem;
            background: white;
            border-radius: 12px;
            border: 2px solid #f0f9ff;
            overflow: hidden;
        }

        .consultation-notes-header {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 1.5rem 2rem;
            border-bottom: 2px solid #bae6fd;
        }

        .notes-count-badge {
            background: #3498db;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            margin-left: 0.5rem;
        }

        .notes-history-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .note-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .note-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.1);
            border-color: #3498db;
        }

        .note-date {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1rem;
        }

        .note-next-date {
            color: #10b981;
            font-size: 0.875rem;
            background: #d1fae5;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            margin-top: 0.5rem;
        }

        .note-preview {
            color: #4b5563;
            line-height: 1.5;
            margin: 0.75rem 0;
            font-size: 0.875rem;
            max-height: 4.5em;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .note-meta {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f3f4f6;
        }

        .note-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-view-note {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view-note:hover {
            background: #bae6fd;
        }

        .btn-use-note {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-use-note:hover {
            background: #a7f3d0;
        }

        /* Loading spinner for notes */
        .loading-notes {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-pdf {
            background-color: #e74c3c;
            color: white;
            border-radius: 30px;
            padding: 15px 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-pdf:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
        }
        /* Horizontal Notes Display */
.horizontal-notes-container {
    display: flex;
    overflow-x: auto;
    padding: 1rem 0.5rem;
    gap: 1rem;
    scrollbar-width: thin;
    scrollbar-color: #3498db #f0f9ff;
}

.horizontal-notes-container::-webkit-scrollbar {
    height: 8px;
}

.horizontal-notes-container::-webkit-scrollbar-track {
    background: #f0f9ff;
    border-radius: 4px;
}

.horizontal-notes-container::-webkit-scrollbar-thumb {
    background: #3498db;
    border-radius: 4px;
}

.note-card {
    flex: 0 0 300px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    min-height: 250px;
    max-height: 350px;
}

.note-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(52, 152, 219, 0.15);
    border-color: #3498db;
}

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    border-bottom: 2px solid #f0f9ff;
    padding-bottom: 0.75rem;
}

.note-date {
    font-weight: 600;
    color: #2c3e50;
    font-size: 1rem;
}

.note-badge {
    background: #3498db;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
}

.note-content {
    flex: 1;
    overflow: hidden;
    position: relative;
    margin-bottom: 1rem;
}

.note-text {
    color: #4b5563;
    line-height: 1.5;
    font-size: 0.875rem;
    max-height: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 8;
    -webkit-box-orient: vertical;
}

.note-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
}

.btn-view-note {
    flex: 1;
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    padding: 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.btn-view-note:hover {
    background: #bae6fd;
}

.btn-use-note {
    flex: 1;
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    padding: 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.btn-use-note:hover {
    background: #a7f3d0;
}

/* Empty State */
.empty-notes {
    text-align: center;
    padding: 3rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    width: 100%;
}

.empty-notes i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-notes p {
    color: #64748b;
    margin-bottom: 1.5rem;
}
.btn-pdf {
    background-color: #e74c3c;
    color: white;
    border-radius: 30px;
    padding: 15px 25px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e74c3c;
}

.btn-pdf:hover {
    background-color: #c0392b;
    border-color: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
}

.manual-export-controls {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 100%);
    border: 2px solid #3498db;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
}

.manual-export-form {
    background: white;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.selected-count-display {
    background-color: #d1fae5;
    color: #065f46;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    border: 2px solid #10b981;
}
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-1">
        <h1 class="text-3xl font-semibold mb-6 text-secondary">Resident Patient Records</h1>

        <?php if ($message): ?>
            <div id="successMessage" class="alert-success px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="main-container rounded-lg shadow-sm mb-8">
            <div class="flex border-b border-gray-200">
                <button
                    class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition"
                    data-tab="patients-tab">
                    <i class="fas fa-list mr-2"></i>Patient Records
                </button>
                <button
                    class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition"
                    data-tab="add-tab">
                    <i class="fas fa-plus-circle mr-2"></i>Add New Patient
                </button>
            </div>

            <!-- Patients Tab -->
            <div id="patients-tab" class="tab-content p-6 active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-secondary">Patient Records</h2>
                    <div class="flex gap-4">
                        <!-- Export Button with Dropdown -->
                        <div class="export-btn-wrapper">
                            <button onclick="toggleExportOptions()" class="btn-export inline-flex items-center">
                                <i class="fas fa-download mr-2"></i>Export
                            </button>
                            <div id="exportOptions" class="export-options">
                                <button type="button" onclick="exportAllRecords('excel')" class="export-option">
                                    <i class="fas fa-file-excel mr-2 text-success"></i>Excel - All Records
                                </button>
                                <button type="button" onclick="exportAllRecords('pdf')" class="export-option">
                                    <i class="fas fa-file-pdf mr-2 text-danger"></i>PDF - All Records
                                </button>
                                <button type="button" onclick="enableManualSelection()" class="export-option">
                                    <i class="fas fa-user-check mr-2 text-primary"></i>Manual by Patient
                                </button>
                            </div>
                        </div>
                        <a href="deleted_patients.php" class="btn-gray inline-flex items-center">
                            <i class="fas fa-archive mr-2"></i>View Archive
                        </a>
                    </div>
                </div>

                <!-- Manual Export Controls (Hidden by default) -->
<?php if ($manualSelectMode): ?>
    <div class="manual-export-controls mb-6">
        <form method="POST" action="" id="manualExportForm" class="manual-export-form">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h4 class="text-lg font-semibold text-secondary mb-2">
                        <i class="fas fa-user-check mr-2 text-primary"></i>
                        Select Patients for Export
                    </h4>
                    <p class="text-sm text-gray-600">Check the patients you want to include in the export.
                    <br><span class="text-primary font-medium">Selected: <span id="selectedCount">0</span> patients</span></p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center">
                        <input type="checkbox" id="selectAllPatients"
                            class="patient-checkbox select-all-checkbox" onchange="toggleAllPatients(this)">
                        <label for="selectAllPatients" class="ml-2 text-sm font-medium text-gray-700">Select
                            All</label>
                    </div>
                    <button type="button" onclick="disableManualSelection()" class="btn-gray px-4 py-2">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" onclick="confirmManualExport('excel')" class="btn-export px-4 py-2">
                        <i class="fas fa-file-excel mr-2"></i>Export as Excel
                    </button>
                    <button type="button" onclick="confirmManualExport('pdf')" class="btn-pdf px-4 py-2">
                        <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

                <!-- Search Form -->
                <form method="get" action="" class="mb-6 section-bg p-6">
                    <input type="hidden" name="tab" value="patients-tab">
                    <?php if ($viewAll): ?>
                        <input type="hidden" name="view_all" value="true">
                    <?php endif; ?>
                    <?php if ($manualSelectMode): ?>
                        <input type="hidden" name="manual_select" value="true">
                    <?php endif; ?>

                    <div class="search-form-container">
                        

                        <!-- Search Term Field with icon inside input -->
                        <div class="search-field-group flex-grow">
                            <label for="search" class="block text-gray-700 mb-2 font-medium">
                                Search Term
                            </label>

                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass
                                          absolute left-7 top-1/2 -translate-y-1/2
                                          text-gray-500 pointer-events-none z-10"></i>

                                <input type="text" id="search" name="search"
                                    value="<?= htmlspecialchars($searchTerm) ?>"
                                    placeholder="<?= $searchBy === 'unique_number' ? 'Enter Patients Name...' : 'Search patients by name...' ?>"
                                    class="search-input w-full pl-11 pr-4 py-2 rounded-lg border
                                              focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Search Buttons -->
                        <div class="search-field-group flex flex-col sm:flex-row gap-2 mt-2 sm:mt-0">
                            <?php if (empty($searchTerm)): ?>
                                <button type="submit" class="btn-primary min-w-[120px]">
                                    <i class="fas fa-search mr-2"></i> Search
                                </button>
                            <?php else: ?>
                                <a href="existing_info_patients.php<?= $manualSelectMode ? '?manual_select=true&tab=patients-tab' : '?tab=patients-tab' ?>"
                                    class="btn-gray min-w-[120px] text-center">
                                    <i class="fas fa-times mr-2"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <?php if (!empty($searchTerm)): ?>
                    <div class="section-bg overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-secondary">Search Results for
                                "<?= htmlspecialchars($searchTerm) ?>"</h3>
                        </div>

                        <?php if (empty($patients) && empty($searchedUsers)): ?>
                            <div class="p-6 text-center">
                                <i class="fas fa-search text-3xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500">No patients or users found matching your search.</p>
                            </div>
                        <?php else: ?>
                            <!-- Display Patients Search Results -->
                            <?php if (!empty($patients)): ?>
                                <div class="p-4">
                                    <h4 class="text-md font-medium text-secondary mb-3">Patient Records</h4>
                                    <div class="overflow-x-auto">
                                        <table class="patient-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Date of Birth</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <?php if ($sitioExists): ?>
                                                        <th>Sitio</th>
                                                    <?php endif; ?>
                                                    <?php if ($civilStatusExists): ?>
                                                        <th>Civil Status</th>
                                                    <?php endif; ?>
                                                    <?php if ($occupationExists): ?>
                                                        <th>Occupation</th>
                                                    <?php endif; ?>
                                                    <th>Type</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($patients as $index => $patient): ?>
                                                    <tr>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['date_of_birth'])) {
                                                                echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['gender'])) {
                                                                if ($patient['gender'] === 'male')
                                                                    echo 'Male';
                                                                elseif ($patient['gender'] === 'female')
                                                                    echo 'Female';
                                                                else
                                                                    echo htmlspecialchars($patient['gender']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <?php if ($sitioExists): ?>
                                                            <td><?= htmlspecialchars($patient['sitio'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <?php if ($civilStatusExists): ?>
                                                            <td><?= htmlspecialchars($patient['civil_status'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <?php if ($occupationExists): ?>
                                                            <td><?= htmlspecialchars($patient['occupation'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <span class="text-gray-500">Regular Patient</span>
                                                        </td>
                                                        <td>
                                                            <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                                class="btn-view inline-flex items-center mr-2">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <a href="?delete_patient=<?= $patient['id'] ?>"
                                                                class="btn-archive inline-flex items-center"
                                                                onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                                <i class="fas fa-trash-alt mr-1"></i> Archive
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Display Users Search Results -->
                            <?php if (!empty($searchedUsers)): ?>
                                <div class="p-4 border-t border-gray-200">
                                    <h4 class="text-md font-medium text-secondary mb-3">Registered Users</h4>
                                    <div class="overflow-x-auto">
                                        <table class="patient-table">
                                            <thead>
                                                <tr>
                                                    <?php if ($manualSelectMode): ?>
                                                        <th class="checkbox-column">
                                                            <input type="checkbox" id="selectAll" class="patient-checkbox"
                                                                onchange="toggleAllSelection(this)">
                                                        </th>
                                                    <?php endif; ?>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Date of Birth</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <?php if ($sitioExists): ?>
                                                        <th>Sitio</th>
                                                    <?php endif; ?>
                                                    <?php if ($civilStatusExists): ?>
                                                        <th>Civil Status</th>
                                                    <?php endif; ?>
                                                    <?php if ($occupationExists): ?>
                                                        <th>Occupation</th>
                                                    <?php endif; ?>
                                                    <th>Type</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($allPatients as $index => $patient): ?>
                                                    <tr>
                                                        <?php if ($manualSelectMode): ?>
                                                            <td class="checkbox-column">
                                                                <input type="checkbox" name="selected_patients[]"
                                                                    value="<?= $patient['id'] ?>"
                                                                    class="patient-checkbox patient-select"
                                                                    onchange="updateSelectedCount()">
                                                            </td>
                                                        <?php endif; ?>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['date_of_birth'])) {
                                                                echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['gender'])) {
                                                                echo htmlspecialchars($patient['gender']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <?php if ($sitioExists): ?>
                                                            <td>
                                                                <?php
                                                                if (!empty($patient['sitio'])) {
                                                                    echo htmlspecialchars($patient['sitio']);
                                                                } elseif (!empty($patient['user_sitio'])) {
                                                                    echo htmlspecialchars($patient['user_sitio']);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                        <?php endif; ?>
                                                        <?php if ($civilStatusExists): ?>
                                                            <td>
                                                                <?php
                                                                if (!empty($patient['civil_status'])) {
                                                                    echo htmlspecialchars($patient['civil_status']);
                                                                } elseif (!empty($patient['user_civil_status'])) {
                                                                    echo htmlspecialchars($patient['user_civil_status']);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                        <?php endif; ?>
                                                        <?php if ($occupationExists): ?>
                                                            <td>
                                                                <?php
                                                                if (!empty($patient['occupation'])) {
                                                                    echo htmlspecialchars($patient['occupation']);
                                                                } elseif (!empty($patient['user_occupation'])) {
                                                                    echo htmlspecialchars($patient['user_occupation']);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <?php if ($patient['patient_type'] === 'Registered Patient'): ?>
                                                                <span class="user-badge">Registered Patient</span>
                                                            <?php else: ?>
                                                                <span class="regular-badge">Regular Patient</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                                class="btn-view inline-flex items-center mr-2">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <?php if (!$manualSelectMode): ?>
                                                                <a href="?delete_patient=<?= $patient['id'] ?>"
                                                                    class="btn-archive inline-flex items-center"
                                                                    onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                                    <i class="fas fa-trash-alt mr-1"></i> Archive
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($searchTerm)): ?>
                    <div class="section-bg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-secondary">
                                    <?= $viewAll ? 'All Patient Records' : ($manualSelectMode ? 'Select Patients for Export' : 'Patient Records') ?>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php if ($viewAll): ?>
                                        Showing all <?= count($allPatients) ?> records
                                    <?php elseif ($manualSelectMode): ?>
                                        Select patients to include in export
                                    <?php else: ?>
                                        Showing <?= count($allPatients) ?> of <?= $totalRecords ?> records
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Patient Type Filter -->
                                <form method="get" action="" class="flex items-center gap-2">
                                    <input type="hidden" name="tab" value="patients-tab">
                                    <?php if ($viewAll): ?>
                                        <input type="hidden" name="view_all" value="true">
                                    <?php endif; ?>
                                    <?php if ($manualSelectMode): ?>
                                        <input type="hidden" name="manual_select" value="true">
                                    <?php endif; ?>
                                    <select name="patient_type" onchange="this.form.submit()" class="patient-type-filter">
                                        <option value="all" <?= $patientTypeFilter === 'all' ? 'selected' : '' ?>>All Patient
                                            Types</option>
                                        <option value="registered" <?= $patientTypeFilter === 'registered' ? 'selected' : '' ?>>Registered Patient</option>
                                        <option value="regular" <?= $patientTypeFilter === 'regular' ? 'selected' : '' ?>>
                                            Regular Patient</option>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($allPatients)): ?>
                            <div class="text-center py-12 bg-gray-50 rounded-lg">
                                <i class="fa-solid fa-bed text-6xl mb-4 text-gray-300"></i>
                                <h3 class="text-lg font-medium text-gray-900">No patients found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by adding a new patient.</p>
                                <div class="mt-6">
                                    <button data-tab="add-tab" class="tab-trigger btn-primary inline-flex items-center">
                                        <i class="fas fa-plus-circle mr-2"></i>Add Patient
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($viewAll || $manualSelectMode): ?>
                                <div class="p-4">
                                    <?php if (!$manualSelectMode): ?>
                                        <a href="existing_info_patients.php?tab=patients-tab"
                                            class="btn-back-to-pagination inline-flex items-center">
                                            <i class="fas fa-arrow-left mr-2"></i>Back to Pagination View
                                        </a>
                                    <?php endif; ?>
                                    <div class="scrollable-table-container">
                                        <form method="POST" action="" id="patientSelectionForm">
                                            <table class="patient-table">
                                                <thead>
                                                    <tr>
                                                        <?php if ($manualSelectMode): ?>
                                                            <th class="checkbox-column">
                                                                <input type="checkbox" id="selectAll" class="patient-checkbox"
                                                                    onchange="toggleAllSelection(this)">
                                                            </th>
                                                        <?php endif; ?>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Date of Birth</th>
                                                        <th>Age</th>
                                                        <th>Gender</th>
                                                        <?php if ($sitioExists): ?>
                                                            <th>Sitio</th>
                                                        <?php endif; ?>
                                                        <?php if ($civilStatusExists): ?>
                                                            <th>Civil Status</th>
                                                        <?php endif; ?>
                                                        <?php if ($occupationExists): ?>
                                                            <th>Occupation</th>
                                                        <?php endif; ?>
                                                        <th>PHIC No.</th>
                                                        <th>BHW</th>
                                                        <th>4P's</th>
                                                        <th>Blood Type</th>
                                                        <th>Type</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($allPatients as $index => $patient): ?>
                                                        <tr>
                                                            <?php if ($manualSelectMode): ?>
                                                                <td class="checkbox-column">
                                                                    <input type="checkbox" name="selected_patients[]"
                                                                        value="<?= $patient['id'] ?>"
                                                                        class="patient-checkbox patient-select"
                                                                        onchange="updateSelectedCount()">
                                                                </td>
                                                            <?php endif; ?>
                                                            <td class="patient-id"><?= $index + 1 ?></td>
                                                            <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                            <td>
                                                                <?php
                                                                if (!empty($patient['date_of_birth'])) {
                                                                    echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                            <td>
                                                                <?php
                                                                if (!empty($patient['gender'])) {
                                                                    echo htmlspecialchars($patient['gender']);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                            <?php if ($sitioExists): ?>
                                                                <td>
                                                                    <?php
                                                                    if (!empty($patient['sitio'])) {
                                                                        echo htmlspecialchars($patient['sitio']);
                                                                    } elseif (!empty($patient['user_sitio'])) {
                                                                        echo htmlspecialchars($patient['user_sitio']);
                                                                    } else {
                                                                        echo 'N/A';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <?php if ($civilStatusExists): ?>
                                                                <td>
                                                                    <?php
                                                                    if (!empty($patient['civil_status'])) {
                                                                        echo htmlspecialchars($patient['civil_status']);
                                                                    } elseif (!empty($patient['user_civil_status'])) {
                                                                        echo htmlspecialchars($patient['user_civil_status']);
                                                                    } else {
                                                                        echo 'N/A';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <?php if ($occupationExists): ?>
                                                                <td>
                                                                    <?php
                                                                    if (!empty($patient['occupation'])) {
                                                                        echo htmlspecialchars($patient['occupation']);
                                                                    } elseif (!empty($patient['user_occupation'])) {
                                                                        echo htmlspecialchars($patient['user_occupation']);
                                                                    } else {
                                                                        echo 'N/A';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td><?= htmlspecialchars($patient['phic_no'] ?? 'N/A') ?></td>
                                                            <td><?= htmlspecialchars($patient['bhw_assigned'] ?? 'N/A') ?></td>
                                                            <td><?= htmlspecialchars($patient['fourps_member'] ?? 'N/A') ?></td>
                                                            <td class="font-semibold text-primary">
                                                                <?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($patient['patient_type'] === 'Registered Patient'): ?>
                                                                    <span class="user-badge">Registered Patient</span>
                                                                <?php else: ?>
                                                                    <span class="regular-badge">Regular Patient</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                                    class="btn-view inline-flex items-center mr-2">
                                                                    <i class="fas fa-eye mr-1"></i> View
                                                                </button>
                                                                <?php if (!$manualSelectMode): ?>
                                                                    <a href="?delete_patient=<?= $patient['id'] ?>"
                                                                        class="btn-archive inline-flex items-center"
                                                                        onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                                        <i class="fas fa-trash-alt mr-1"></i> Archive
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="patient-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Date of Birth</th>
                                                <th>Age</th>
                                                <th>Gender</th>
                                                <?php if ($sitioExists): ?>
                                                    <th>Sitio</th>
                                                <?php endif; ?>
                                                <?php if ($civilStatusExists): ?>
                                                    <th>Civil Status</th>
                                                <?php endif; ?>
                                                <?php if ($occupationExists): ?>
                                                    <th>Occupation</th>
                                                <?php endif; ?>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allPatients as $index => $patient): ?>
                                                <tr>
                                                    <td class="patient-id"><?= $offset + $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                    <td>
                                                        <?php
                                                        if (!empty($patient['date_of_birth'])) {
                                                            echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                    <td>
                                                        <?php
                                                        if (!empty($patient['gender'])) {
                                                            echo htmlspecialchars($patient['gender']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php if ($sitioExists): ?>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['sitio'])) {
                                                                echo htmlspecialchars($patient['sitio']);
                                                            } elseif (!empty($patient['user_sitio'])) {
                                                                echo htmlspecialchars($patient['user_sitio']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <?php if ($civilStatusExists): ?>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['civil_status'])) {
                                                                echo htmlspecialchars($patient['civil_status']);
                                                            } elseif (!empty($patient['user_civil_status'])) {
                                                                echo htmlspecialchars($patient['user_civil_status']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <?php if ($occupationExists): ?>
                                                        <td>
                                                            <?php
                                                            if (!empty($patient['occupation'])) {
                                                                echo htmlspecialchars($patient['occupation']);
                                                            } elseif (!empty($patient['user_occupation'])) {
                                                                echo htmlspecialchars($patient['user_occupation']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <?php if ($patient['patient_type'] === 'Registered Patient'): ?>
                                                            <span class="user-badge">Registered Patient</span>
                                                        <?php else: ?>
                                                            <span class="regular-badge">Regular Patient</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                            class="btn-view inline-flex items-center mr-2">
                                                            <i class="fas fa-eye mr-1"></i> View
                                                        </button>
                                                        <a href="?delete_patient=<?= $patient['id'] ?>"
                                                            class="btn-archive inline-flex items-center"
                                                            onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                            <i class="fas fa-trash-alt mr-1"></i> Archive
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Enhanced Pagination Container -->
                                <div class="pagination-container">
                                    <div class="pagination">
                                        <!-- Previous Button -->
                                        <a href="?tab=patients-tab&page=<?= $currentPage - 1 ?>&patient_type=<?= $patientTypeFilter ?>"
                                            class="pagination-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>

                                        <!-- Page Numbers -->
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)): ?>
                                                <a href="?tab=patients-tab&page=<?= $i ?>&patient_type=<?= $patientTypeFilter ?>"
                                                    class="pagination-btn <?= $i == $currentPage ? 'active' : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php elseif ($i == $currentPage - 2 || $i == $currentPage + 2): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <!-- Next Button -->
                                        <a href="?tab=patients-tab&page=<?= $currentPage + 1 ?>&patient_type=<?= $patientTypeFilter ?>"
                                            class="pagination-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>

                                    <div class="pagination-actions">
                                        <a href="?tab=patients-tab&view_all=true&patient_type=<?= $patientTypeFilter ?>"
                                            class="btn-view-all">
                                            <i class="fas fa-list mr-2"></i>View All Patients
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Patient Tab -->
            <div id="add-tab" class="tab-content p-6">
                <div class="text-center py-12">
                    <div class="max-w-md mx-auto">
                        <div class="section-bg p-8 mb-8">
                            <div class="w-20 h-20 bg-white flex items-center justify-center mx-auto mb-6">
                                <i class="fa-solid fa-fill text-8xl text-gray-300"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-secondary mb-4">Register New Patient</h2>
                            <p class="text-gray-600 mb-8">
                                Add a new patient record to the system. Fill out all required information including
                                personal details and medical history.
                            </p>
                            <button onclick="openAddPatientModal()"
                                class="btn-primary px-8 py-4 round-full text-lg font-semibold">
                                <i class="fas fa-plus-circle mr-3"></i>Add New Patient
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Wider Modal for Viewing Patient Info -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal"
        style="display: none;">
        <div
            class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col border-2 border-primary">
            <!-- Sticky Header -->
            <div class="sticky top-0 z-20 bg-[#2563EB] px-10 py-6 flex items-center">
                <h3 class="text-2xl font-medium flex justify-center text-center w-full items-center text-white">
                    <span class="text-white">Patient Health Information</span>
                </h3>

                <button onclick="closeViewModal()"
                    class="border-2 border-white text-white hover:bg-white/20 rounded-full w-8 h-8 flex items-center justify-center transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="px-16 bg-gray-50 flex-1 overflow-y-auto">
                <div id="modalContent" class="min-h-[500px]">
                    <!-- Content will be loaded via AJAX -->
                    <div class="flex justify-center items-center py-20">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-5xl text-primary mb-4"></i>
                            <p class="text-lg text-gray-600 font-medium">Loading patient data...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer -->
            <div class="p-8 border-t border-gray-200 bg-white rounded-b-2xl sticky bottom-0">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex flex-col items-start">
                        <span class="flex items-center text-center gap-3 text-md text-gray-500 bg-gray-100 px-8 py-5 rounded-full">
                            <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M16.6667 33.3333C25.8717 33.3333 33.3333 25.8717 33.3333 16.6667C33.3333 7.46167 25.8717 0 16.6667 0C7.46167 0 0 7.46167 0 16.6667C0 25.8717 7.46167 33.3333 16.6667 33.3333ZM19.1667 9.58333C19.1667 10.3569 18.8594 11.0987 18.3124 11.6457C17.7654 12.1927 17.0235 12.5 16.25 12.5C15.4765 12.5 14.7346 12.1927 14.1876 11.6457C13.6406 11.0987 13.3333 10.3569 13.3333 9.58333C13.3333 8.80978 13.6406 8.06792 14.1876 7.52094C14.7346 6.97396 15.4765 6.66667 16.25 6.66667C17.0235 6.66667 17.7654 6.97396 18.3124 7.52094C18.8594 8.06792 19.1667 8.80978 19.1667 9.58333ZM17.6008 14.87C17.8264 15.0227 18.0111 15.2283 18.1388 15.4689C18.2665 15.7094 18.3333 15.9776 18.3333 16.25V22.72L19.9117 21.9308L21.4033 24.9117L17.4117 26.9075C17.1576 27.0345 16.8752 27.0944 16.5915 27.0816C16.3077 27.0688 16.0319 26.9836 15.7903 26.8343C15.5487 26.6849 15.3493 26.4763 15.2109 26.2282C15.0726 25.9801 15 25.7007 15 25.4167V18.7117L13.655 19.25L12.4167 16.155L16.0475 14.7025C16.3003 14.6013 16.5741 14.5635 16.8449 14.5926C17.1157 14.6216 17.3752 14.7174 17.6008 14.87Z"
                                    fill="black" fill-opacity="0.25" />
                            </svg>
                            View and edit patient information
                        </span>
                    </div>
                    <div class="flex space-x-4">
                        <div class="flex flex-col items-center mt-2">
                            <button onclick="printPatientRecord()" class="btn-export text-lg px-8 py-3 font-semibold">
                                <i class="fas fa-print mr-3"></i>Print Patient Records
                            </button>
                        </div>
                        <div class="flex flex-col items-center">
                            <button id="noteButton" onclick="openConsultationNoteModal()" class="btn-add-note px-8 py-5 text-lg font-semibold">
                                <i class="fas fa-sticky-note mr-2"></i>Add Note
                            </button>
                        </div>
                        <div class="flex flex-col items-center">
                            <button 
                                id="saveMedicalBtn"
                                type="button"
                                onclick="saveMedicalInformation()"
                                class="btn-save-medical px-8 py-5 text-lg font-semibold"
                            >
                                <i class="fas fa-save mr-2"></i>Save Medical Information
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Note Modal -->
    <div id="consultationNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal"
        style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Sticky Header -->
            <div class="sticky top-0 z-20 bg-[#f39c12] px-10 py-6 flex items-center">
                <h3 class="text-2xl font-medium flex justify-center text-center w-full items-center text-white">
                    <i class="fas fa-sticky-note mr-3"></i>
                    <span class="text-white" id="consultationNoteTitle">Add Consultation Note</span>
                </h3>
                <button onclick="closeConsultationNoteModal()"
                    class="border-2 border-white text-white hover:bg-white/20 rounded-full w-8 h-8 flex items-center justify-center transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="px-10 py-6 bg-gray-50 flex-1 overflow-y-auto">
                <div id="consultationNoteContent">
                    <!-- Add Note Form -->
                    <form id="addNoteForm" method="POST" action="">
                        <input type="hidden" name="patient_id" id="notePatientId" value="">
                        
                        <div class="space-y-6">
                            <div>
                                <label for="consultation_date" class="block text-gray-700 mb-2 font-medium">
                                    Consultation Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="consultation_date" name="consultation_date" 
                                       value="<?= date('Y-m-d') ?>"
                                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                       required>
                            </div>
                            
                            <div>
                                <label for="next_consultation_date" class="block text-gray-700 mb-2 font-medium">
                                    Next Consultation Date
                                </label>
                                <input type="date" id="next_consultation_date" name="next_consultation_date" 
                                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            
                            <div>
                                <label for="note" class="block text-gray-700 mb-2 font-medium">
                                    Consultation Note <span class="text-red-500">*</span>
                                </label>
                                <textarea id="note" name="note" rows="8" 
                                          class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                          placeholder="Enter consultation notes, observations, recommendations, and treatment plans..."
                                          required></textarea>
                            </div>
                        </div>
                    </form>
                    
                    <!-- View Notes Content -->
                    <div id="viewNotesContent" style="display: none;">
                        <div class="space-y-4" id="consultationNotesList">
                            <!-- Notes will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-gray-200 bg-white">
                <div class="flex justify-between items-center">
                    <button type="button" onclick="closeConsultationNoteModal()" 
                            class="btn-gray px-6 py-3">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    
                    <!-- Add Note Button (shown when in Add mode) -->
                    <div id="addNoteActions">
                        <button type="button" onclick="saveConsultationNote()" 
                                class="btn-add-note px-6 py-3">
                            <i class="fas fa-save mr-2"></i>Save Note
                        </button>
                    </div>
                    
                    <!-- View Note Actions (shown when in View mode) -->
                    <div id="viewNoteActions" style="display: none;">
                        <button type="button" onclick="switchToAddNote()" 
                                class="btn-add-note px-6 py-3">
                            <i class="fas fa-plus mr-2"></i>Add New Note
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 modal"
        style="display:none;">

        <div class="bg-white rounded-lg shadow-2xl w-full max-w-7xl h-[92vh] overflow-hidden flex flex-col">
            <!-- ================= HEADER ================= -->
            <div class="sticky top-0 z-20 bg-[#2563EB] px-10 py-6 flex items-center">
                <h3 class="text-xl font-medium flex gap-3 text-center w-full items-center text-white">
                    <svg width="36" height="36" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <mask id="mask0_989_9772" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0"
                            width="44" height="44">
                            <path
                                d="M38.8125 1H4.4375C2.53902 1 1 2.53902 1 4.4375V38.8125C1 40.711 2.53902 42.25 4.4375 42.25H38.8125C40.711 42.25 42.25 40.711 42.25 38.8125V4.4375C42.25 2.53902 40.711 1 38.8125 1Z"
                                fill="white" stroke="white" stroke-width="2" stroke-linejoin="round" />
                            <path d="M21.6247 12.4585V30.7918M12.458 21.6252H30.7913" stroke="black" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </mask>
                        <g mask="url(#mask0_989_9772)">
                            <path d="M-5.875 -5.875H49.125V49.125H-5.875V-5.875Z" fill="white" />
                        </g>
                    </svg>
                    Registration For New Patient
                </h3>
                <button onclick="closeAddPatientModal()"
                    class="w-8 h-8 flex items-center justify-center transition">
                    <i class="fas fa-times text-3xl text-white"></i>
                </button>
            </div>

            <!-- ================= CONTENT ================= -->
            <div class="flex-1 overflow-y-auto px-16">
                <form method="POST" action="" id="patientForm" enctype="multipart/form-data">

                    <!-- ================= PERSONAL INFORMATION ================= -->
                    <div class="bg-white my-10">
                        <h3
                            class="text-2xl font-normal border-b border-black-100 py-6 text-[#2563EB] mb-6 gap-4 flex items-center">
                            <svg width="42" height="38" viewBox="0 0 42 38" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M0 2.06875C0.00381259 1.52162 0.222709 0.997953 0.609402 0.61087C0.996095 0.223787 1.51954 0.00436385 2.06667 0H39.6C40.7417 0 41.6667 0.927083 41.6667 2.06875V35.4312C41.6629 35.9784 41.444 36.502 41.0573 36.8891C40.6706 37.2762 40.1471 37.4956 39.6 37.5H2.06667C1.51836 37.4994 0.992702 37.2812 0.605186 36.8933C0.217671 36.5054 -2.78032e-07 35.9796 0 35.4312V2.06875ZM8.33333 25V29.1667H33.3333V25H8.33333ZM8.33333 8.33333V20.8333H20.8333V8.33333H8.33333ZM25 8.33333V12.5H33.3333V8.33333H25ZM25 16.6667V20.8333H33.3333V16.6667H25ZM12.5 12.5H16.6667V16.6667H12.5V12.5Z"
                                    fill="#2563EB" />
                            </svg>
                            Personal Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="modal_full_name" class="block text-sm font-medium mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_full_name" name="full_name" placeholder="Enter Full Name"
                                    required class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_date_of_birth" class="block text-sm font-medium mb-2">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="modal_date_of_birth" name="date_of_birth" required
                                    max="<?= date('Y-m-d') ?>"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_age" class="block text-sm font-medium mb-2">
                                    Age (Auto-calculated)
                                </label>
                                <input type="number" id="modal_age" name="age" placeholder="0" readonly
                                    class="form-input-modal w-full rounded-xl bg-[#F0F0F0] border border-blue-200 px-4 py-3 cursor-not-allowed">
                            </div>

                            <div>
                                <label for="modal_gender" class="block text-sm font-medium mb-2">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="modal_gender" name="gender" required
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <?php if ($civilStatusExists): ?>
                                <div>
                                    <label for="modal_civil_status" class="block text-sm font-medium mb-2">
                                        Civil Status <span class="text-red-500">*</span>
                                    </label>
                                    <select id="modal_civil_status" name="civil_status"
                                        class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                        <option value="">Select Status</option>
                                        <option>Single</option>
                                        <option>Married</option>
                                        <option>Widowed</option>
                                        <option>Separated</option>
                                        <option>Divorced</option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($occupationExists): ?>
                                <div>
                                    <label for="modal_occupation" class="block text-sm font-medium mb-2">
                                        Occupation
                                    </label>
                                    <input type="text" id="modal_occupation" name="occupation"
                                        placeholder="Enter Occupation"
                                        class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <label for="modal_phic_no" class="block text-sm font-medium mb-2">
                                    PHIC No.
                                </label>
                                <input type="text" id="modal_phic_no" name="phic_no"
                                    placeholder="Enter PHIC Number"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_bhw_assigned" class="block text-sm font-medium mb-2">
                                    BHW Assigned
                                </label>
                                <input type="text" id="modal_bhw_assigned" name="bhw_assigned"
                                    placeholder="Enter BHW Name"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_family_no" class="block text-sm font-medium mb-2">
                                    Family No.
                                </label>
                                <input type="text" id="modal_family_no" name="family_no"
                                    placeholder="Enter Family Number"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_fourps_member" class="block text-sm font-medium mb-2">
                                    4P's Member
                                </label>
                                <select id="modal_fourps_member" name="fourps_member"
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            
                            <?php if ($sitioExists): ?>
                                <div>
                                    <label for="modal_sitio" class="block text-sm font-medium mb-2">
                                        Sitio <span class="text-red-500">*</span>
                                    </label>
                                    <select id="modal_sitio" name="sitio"
                                        class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                        <option value="">Select Sitio</option>
                                        <option value="Proper Luz">Proper Luz</option>
                                        <option value="Lower Luz">Lower Luz</option>
                                        <option value="Upper Luz">Upper Luz</option>
                                        <option value="Luz Proper">Luz Proper</option>
                                        <option value="Luz Heights">Luz Heights</option>
                                        <option value="Panganiban">Panganiban</option>
                                        <option value="Balagtas">Balagtas</option>
                                        <option value="Carbon">Carbon</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label for="modal_address" class="block text-sm font-medium mb-2">
                                    Complete Address <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_address" name="address"
                                    placeholder="Enter Complete Address"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>
                            <div>
                                <label for="modal_contact" class="block text-sm font-medium mb-2">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_contact" name="contact" placeholder="Enter Contact Number"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>
                        </div>

                    </div>

                    <!-- ================= MEDICAL INFORMATION ================= -->
                    <div class="bg-white">
                        <h3
                            class="text-2xl border-b border-black-100 font-normal text-blue-700 gap-4 py-6 mb-6 flex items-center">
                            <svg width="42" height="42" viewBox="0 0 42 42" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M14.5833 26.9104V28.125C14.5833 30.6114 15.5711 32.996 17.3292 34.7541C19.0874 36.5123 21.4719 37.5 23.9583 37.5C26.4447 37.5 28.8293 36.5123 30.5875 34.7541C32.3456 32.996 33.3333 30.6114 33.3333 28.125V24.6458C31.9427 24.1544 30.7706 23.1871 30.0243 21.915C29.2779 20.6429 29.0052 19.1479 29.2546 17.6942C29.5039 16.2405 30.2591 14.9218 31.3867 13.9711C32.5144 13.0204 33.9418 12.499 35.4167 12.499C36.8916 12.499 38.319 13.0204 39.4466 13.9711C40.5742 14.9218 41.3295 16.2405 41.5788 17.6942C41.8281 19.1479 41.5555 20.6429 40.8091 21.915C40.0627 23.1871 38.8906 24.1544 37.5 24.6458V28.125C37.5 31.7165 36.0733 35.1608 33.5337 37.7004C30.9942 40.24 27.5498 41.6667 23.9583 41.6667C20.3669 41.6667 16.9225 40.24 14.3829 37.7004C11.8434 35.1608 10.4167 31.7165 10.4167 28.125V26.9104C7.50365 26.418 4.85919 24.9098 2.95235 22.6532C1.04551 20.3967 -0.000452952 17.5377 1.47146e-07 14.5833V4.16667C1.47146e-07 3.0616 0.438987 2.00179 1.22039 1.22039C2.00179 0.438987 3.0616 0 4.16667 0L6.25 0C6.80253 0 7.33244 0.219493 7.72314 0.610194C8.11384 1.00089 8.33333 1.5308 8.33333 2.08333C8.33333 2.63587 8.11384 3.16577 7.72314 3.55647C7.33244 3.94717 6.80253 4.16667 6.25 4.16667H4.16667V14.5833C4.16667 16.7935 5.04464 18.9131 6.60744 20.4759C8.17025 22.0387 10.2899 22.9167 12.5 22.9167C14.7101 22.9167 16.8298 22.0387 18.3926 20.4759C19.9554 18.9131 20.8333 16.7935 20.8333 14.5833V4.16667H18.75C18.1975 4.16667 17.6676 3.94717 17.2769 3.55647C16.8862 3.16577 16.6667 2.63587 16.6667 2.08333C16.6667 1.5308 16.8862 1.00089 17.2769 0.610194C17.6676 0.219493 18.1975 0 18.75 0L20.8333 0C21.9384 0 22.9982 0.438987 23.7796 1.22039C24.561 2.00179 25 3.0616 25 4.16667V14.5833C25.0005 17.5377 23.9545 20.3967 22.0477 22.6532C20.1408 24.9098 17.4963 26.418 14.5833 26.9104ZM35.4167 20.8333C35.9692 20.8333 36.4991 20.6138 36.8898 20.2231C37.2805 19.8324 37.5 19.3025 37.5 18.75C37.5 18.1975 37.2805 17.6676 36.8898 17.2769C36.4991 16.8862 35.9692 16.6667 35.4167 16.6667C34.8641 16.6667 34.3342 16.8862 33.9435 17.2769C33.5528 17.6676 33.3333 18.1975 33.3333 18.75C33.3333 19.3025 33.5528 19.8324 33.9435 20.2231C34.3342 20.6138 34.8641 20.8333 35.4167 20.8333Z"
                                    fill="#2563EB" />
                            </svg>
                            Medical Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                            <div>
                                <label for="modal_height" class="block text-sm font-medium mb-2">
                                    Height (cm) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="modal_height" name="height" placeholder="0.0" required
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_weight" class="block text-sm font-medium mb-2">
                                    Weight (kg) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="modal_weight" name="weight" placeholder="0.0" required
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_temperature" class="block text-sm font-medium mb-2">
                                    Temperature (°C)
                                </label>
                                <input type="number" id="modal_temperature" name="temperature" placeholder="0"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_blood_pressure" class="block text-sm font-medium mb-2">
                                    Blood Pressure
                                </label>
                                <input type="text" id="modal_blood_pressure" name="blood_pressure" placeholder="120/80"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_blood_type" class="block text-sm font-medium mb-2">
                                    Blood Type <span class="text-red-500">*</span>
                                </label>
                                <select id="modal_blood_type" name="blood_type" required
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                    <option value="">Select Blood Type</option>
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option>O+</option>
                                    <option>O-</option>
                                    <option>Unknown</option>
                                </select>
                            </div>

                            <div>
                                <label for="modal_last_checkup" class="block text-sm font-medium mb-2">
                                    Last Check-up Date
                                </label>
                                <input type="date" id="modal_last_checkup" name="last_checkup"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 mb-5">
                            <div class="flex flex-col gap-2">
                                <label for="modal_allergies" class="text-gray-700 font-medium">Allergies</label>
                                <textarea id="modal_allergies" name="allergies" rows="3"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Food, drug, environmental allergies..."></textarea>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="modal_current_medications" class="text-gray-700 font-medium">Current
                                    Medications</label>
                                <textarea id="modal_current_medications" name="current_medications" rows="3"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Medications with dosage and frequency..."></textarea>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="modal_immunization_record" class="text-gray-700 font-medium">Immunization
                                    Record</label>
                                <textarea id="modal_immunization_record" name="immunization_record" rows="3"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Provide immunization history or recent vaccines"></textarea>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="modal_chronic_conditions" class="text-gray-700 font-medium">Chronic
                                    Conditions</label>
                                <textarea id="modal_chronic_conditions" name="chronic_conditions" rows="3"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Hypertension, diabetes, asthma, etc..."></textarea>
                            </div>
                        </div>

                        <div class="flex flex-col gap-6 mb-3">

                            <div class="flex flex-col gap-2">
                                <label for="modal_medical_history" class="text-gray-700 font-medium">
                                    Medical History
                                </label>
                                <textarea id="modal_medical_history" name="medical_history" rows="4"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Past illnesses, surgeries, hospitalizations, chronic conditions..."></textarea>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="modal_family_history" class="text-gray-700 font-medium">
                                    Family Medical History
                                </label>
                                <textarea id="modal_family_history" name="family_history" rows="4"
                                    class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                    placeholder="Family history of diseases (parents, siblings)..."></textarea>
                            </div>
                        </div>

                    </div>

                    <input type="hidden" name="add_patient" value="1">
                    <input type="hidden" name="consent_given" value="1">
                </form>
            </div>

            <!-- ================= FOOTER ================= -->
            <div class="sticky bottom-0 bg-white border-t border-blue-100 px-10 py-6">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <span
                        class="flex items-center text-center gap-3 text-md text-gray-500 bg-gray-100 px-8 py-5 rounded-full">
                        <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M16.6667 33.3333C25.8717 33.3333 33.3333 25.8717 33.3333 16.6667C33.3333 7.46167 25.8717 0 16.6667 0C7.46167 0 0 7.46167 0 16.6667C0 25.8717 7.46167 33.3333 16.6667 33.3333ZM19.1667 9.58333C19.1667 10.3569 18.8594 11.0987 18.3124 11.6457C17.7654 12.1927 17.0235 12.5 16.25 12.5C15.4765 12.5 14.7346 12.1927 14.1876 11.6457C13.6406 11.0987 13.3333 10.3569 13.3333 9.58333C13.3333 8.80978 13.6406 8.06792 14.1876 7.52094C14.7346 6.97396 15.4765 6.66667 16.25 6.66667C17.0235 6.66667 17.7654 6.97396 18.3124 7.52094C18.8594 8.06792 19.1667 8.80978 19.1667 9.58333ZM17.6008 14.87C17.8264 15.0227 18.0111 15.2283 18.1388 15.4689C18.2665 15.7094 18.3333 15.9776 18.3333 16.25V22.72L19.9117 21.9308L21.4033 24.9117L17.4117 26.9075C17.1576 27.0345 16.8752 27.0944 16.5915 27.0816C16.3077 27.0688 16.0319 26.9836 15.7903 26.8343C15.5487 26.6849 15.3493 26.4763 15.2109 26.2282C15.0726 25.9801 15 25.7007 15 25.4167V18.7117L13.655 19.25L12.4167 16.155L16.0475 14.7025C16.3003 14.6013 16.5741 14.5635 16.8449 14.5926C17.1157 14.6216 17.3752 14.7174 17.6008 14.87Z"
                                fill="black" fill-opacity="0.25" />
                        </svg>
                        Fields marked with * are required
                    </span>

                    <div class="flex gap-3">
                        <button type="button" onclick="clearAddPatientForm()"
                            class="flex px-6 py-4 text-center items-center gap-3 rounded-full border border-[#2563EB] text-[#2563EB] hover:bg-gray-200 font-medium">
                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M14.781 13.7198C14.8507 13.7895 14.906 13.8722 14.9437 13.9632C14.9814 14.0543 15.0008 14.1519 15.0008 14.2504C15.0008 14.349 14.9814 14.4465 14.9437 14.5376C14.906 14.6286 14.8507 14.7114 14.781 14.781C14.7114 14.8507 14.6286 14.906 14.5376 14.9437C14.4465 14.9814 14.349 15.0008 14.2504 15.0008C14.1519 15.0008 14.0543 14.9814 13.9632 14.9437C13.8722 14.906 13.7895 14.8507 13.7198 14.781L7.50042 8.56073L1.28104 14.781C1.14031 14.9218 0.94944 15.0008 0.750417 15.0008C0.551394 15.0008 0.360523 14.9218 0.219792 14.781C0.0790615 14.6403 3.92322e-09 14.4494 0 14.2504C-3.92322e-09 14.0514 0.0790615 13.8605 0.219792 13.7198L6.4401 7.50042L0.219792 1.28104C0.0790615 1.14031 0 0.94944 0 0.750417C0 0.551394 0.0790615 0.360523 0.219792 0.219792C0.360523 0.0790615 0.551394 0 0.750417 0C0.94944 0 1.14031 0.0790615 1.28104 0.219792L7.50042 6.4401L13.7198 0.219792C13.8605 0.0790615 14.0514 -3.92322e-09 14.2504 0C14.4494 3.92322e-09 14.6403 0.0790615 14.781 0.219792C14.9218 0.360523 15.0008 0.551394 15.0008 0.750417C15.0008 0.94944 14.9218 1.14031 14.781 1.28104L8.56073 7.50042L14.781 13.7198Z"
                                    fill="#2563EB" />
                            </svg>

                            Clear Form
                        </button>
                        <button type="submit" name="add_patient" form="patientForm"
                            class="flex items-center text-center gap-3 px-8 py-4 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-medium shadow">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21.3112 2.689C21.1225 2.5005 20.8871 2.36569 20.629 2.29846C20.371 2.23122 20.0997 2.234 19.843 2.3065H19.829L1.83461 7.7665C1.54248 7.85069 1.28283 8.02166 1.09007 8.25676C0.897302 8.49185 0.780525 8.77997 0.75521 9.08294C0.729895 9.3859 0.797238 9.6894 0.948314 9.95323C1.09939 10.2171 1.32707 10.4287 1.60117 10.5602L9.56242 14.4377L13.4343 22.3943C13.5547 22.6513 13.7462 22.8685 13.9861 23.0201C14.226 23.1718 14.5042 23.2517 14.788 23.2502C14.8312 23.2502 14.8743 23.2484 14.9174 23.2446C15.2201 23.2201 15.5081 23.1036 15.7427 22.9107C15.9773 22.7178 16.1473 22.4578 16.2299 22.1656L21.6862 4.17119C21.6862 4.1665 21.6862 4.16181 21.6862 4.15712C21.7596 3.90115 21.7636 3.63024 21.6977 3.37223C21.6318 3.11421 21.4984 2.8784 21.3112 2.689ZM14.7965 21.7362L14.7918 21.7493V21.7427L11.0362 14.0271L15.5362 9.52712C15.6709 9.38533 15.7449 9.19651 15.7424 9.00094C15.7399 8.80537 15.6611 8.61852 15.5228 8.48022C15.3845 8.34191 15.1976 8.26311 15.002 8.26061C14.8065 8.2581 14.6177 8.3321 14.4759 8.46681L9.97586 12.9668L2.25742 9.21119H2.25086H2.26399L20.2499 3.75025L14.7965 21.7362Z"
                                    fill="white" />
                            </svg>
                            Register Patient
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Consultation Notes Variables
        let currentPatientId = null;
        let hasNotes = false;
        let noteCount = 0;

        // Age calculation function
        function calculateAge(dateOfBirth, ageInput) {
            if (!dateOfBirth) {
                ageInput.value = '';
                return;
            }
            
            const dob = new Date(dateOfBirth);
            const today = new Date();
            
            // Calculate age
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            // Adjust age if birthday hasn't occurred yet this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            // Validate that date is not in the future
            if (dob > today) {
                showNotification('error', 'Date of birth cannot be in the future!');
                document.getElementById('modal_date_of_birth').value = '';
                ageInput.value = '';
                return;
            }
            
            // Validate reasonable age (0-120 years)
            if (age < 0 || age > 120) {
                showNotification('error', 'Please enter a valid date of birth (age must be between 0-120 years)');
                document.getElementById('modal_date_of_birth').value = '';
                ageInput.value = '';
                return;
            }
            
            ageInput.value = age;
        }

        // Enhanced Consultation Note Functions
        function openConsultationNoteModal() {
            // Get patient ID from the view modal
            const patientIdInput = document.querySelector('#modalContent input[name="patient_id"]');
            if (!patientIdInput) {
                showNotification('error', 'Unable to get patient information.');
                return;
            }
            
            currentPatientId = patientIdInput.value;
            document.getElementById('notePatientId').value = currentPatientId;
            
            // Check if patient has existing notes
            checkConsultationNotes(currentPatientId);
            
            // Show modal
            const modal = document.getElementById('consultationNoteModal');
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
        }

        function closeConsultationNoteModal() {
            const modal = document.getElementById('consultationNoteModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
                resetConsultationNoteModal();
            }, 300);
        }

        // Update other functions to use correct paths
        function checkConsultationNotes(patientId) {
            fetch(`../api/check_consultation_notes.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    hasNotes = data.hasNotes;
                    noteCount = data.noteCount || 0;
                    
                    if (hasNotes) {
                        // Also reload the inline notes section
                        loadConsultationNotesInline(patientId);
                    }
                    
                    updateNoteButtonCount(noteCount);
                })
                .catch(error => {
                    console.error('Error checking notes:', error);
                });
        }

        function loadConsultationNotes(patientId) {
            const notesList = document.getElementById('consultationNotesList');
            notesList.innerHTML = `
                <div class="loading-notes">
                    <div class="loading-spinner"></div>
                    <p class="ml-3 text-gray-600">Loading consultation notes...</p>
                </div>
            `;
            
            fetch(`../api/get_consultation_notes.php?patient_id=${patientId}`)
                .then(response => response.text())
                .then(html => {
                    notesList.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading notes:', error);
                    notesList.innerHTML = 
                        '<div class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">' +
                            '<i class="fas fa-exclamation-triangle text-2xl mb-3 text-gray-400"></i>' +
                            '<p>Error loading consultation notes.</p>' +
                        '</div>';
                });
        }

        function updateNoteButtonCount(count) {
            const noteButton = document.getElementById('noteButton');
            if (noteButton) {
                if (count > 0) {
                    // Create or update badge
                    let badge = noteButton.querySelector('.note-badge');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'note-badge';
                        noteButton.appendChild(badge);
                    }
                    badge.textContent = count;
                    
                    // Update button text and style
                    noteButton.innerHTML = '<i class="fas fa-sticky-note mr-2"></i>View Doctors Note ';
                    noteButton.appendChild(badge);
                    noteButton.classList.remove('btn-add-note');
                    noteButton.classList.add('btn-view-notes');
                } else {
                    // Remove badge if exists
                    const badge = noteButton.querySelector('.note-badge');
                    if (badge) {
                        badge.remove();
                    }
                    
                    // Reset button to Add Note
                    noteButton.innerHTML = '<i class="fas fa-sticky-note mr-2"></i>Add Note';
                    noteButton.classList.remove('btn-view-notes');
                    noteButton.classList.add('btn-add-note');
                }
            }
        }

        function switchToViewNotes() {
            document.getElementById('consultationNoteTitle').innerHTML = 
                `<i class="fas fa-sticky-note mr-2"></i>Consultation Notes (${noteCount})`;
            document.getElementById('addNoteForm').style.display = 'none';
            document.getElementById('viewNotesContent').style.display = 'block';
            document.getElementById('addNoteActions').style.display = 'none';
            document.getElementById('viewNoteActions').style.display = 'block';
        }

        function switchToAddNote() {
            document.getElementById('consultationNoteTitle').innerHTML = 
                '<i class="fas fa-plus-circle mr-2"></i>Add Consultation Note';
            document.getElementById('addNoteForm').style.display = 'block';
            document.getElementById('viewNotesContent').style.display = 'none';
            document.getElementById('addNoteActions').style.display = 'block';
            document.getElementById('viewNoteActions').style.display = 'none';
            
            // Reset form with current date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('consultation_date').value = today;
            document.getElementById('next_consultation_date').value = '';
            document.getElementById('note').value = '';
        }

        function resetConsultationNoteModal() {
            // Reset to Add Note mode by default
            switchToAddNote();
            hasNotes = false;
            noteCount = 0;
            currentPatientId = null;
        }

        function saveConsultationNote() {
    // Get form values
    const patientId = document.getElementById('notePatientId').value;
    const note = document.getElementById('note').value.trim();
    const consultationDate = document.getElementById('consultation_date').value;
    const nextDate = document.getElementById('next_consultation_date').value;
    
    if (!patientId || !note || !consultationDate) {
        showNotification('error', 'Please fill in all required fields.');
        return;
    }
    
    const formData = new FormData();
    formData.append('patient_id', patientId);
    formData.append('note', note);
    formData.append('consultation_date', consultationDate);
    if (nextDate) {
        formData.append('next_consultation_date', nextDate);
    }
    formData.append('add_consultation_note', '1');
    
    // Show loading
    const saveBtn = document.querySelector('#addNoteActions button');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    saveBtn.disabled = true;
    
    fetch('existing_info_patients.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('successfully') || result.includes('Consultation note added')) {
            showNotification('success', 'Consultation note added successfully!');
            
            // Clear form
            document.getElementById('note').value = '';
            
            // Close consultation note modal
            closeConsultationNoteModal();
            
            // Refresh notes and patient data in the main modal
            setTimeout(() => {
                loadConsultationNotesInline(patientId);
                checkConsultationNotes(patientId);
                
                // Optional: Refresh the entire patient data
                if (typeof reloadPatientData === 'function') {
                    reloadPatientData(patientId);
                }
            }, 500);
            
        } else {
            showNotification('error', 'Error saving consultation note. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Network error. Check console for details.');
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

        // Export functionality
        function toggleExportOptions() {
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.toggle('show');
        }

        // Export all records based on current filter
        function exportAllRecords(format) {
            // Close dropdown
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.remove('show');

            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Get current patient type filter
            const patientTypeSelect = document.querySelector('select[name="patient_type"]');
            const currentPatientType = patientTypeSelect ? patientTypeSelect.value : 'all';

            // Build export URL
            let url = `existing_info_patients.php?export=${format}&patient_type=${currentPatientType}`;

            // Add current search parameters
            const tab = urlParams.get('tab');
            const search = urlParams.get('search');
            const searchBy = urlParams.get('search_by');

            if (tab) url += `&tab=${tab}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (searchBy) url += `&search_by=${searchBy}`;

            // Show loading message
            const typeLabels = {
                'all': 'All Patients',
                'registered': 'Registered Patients',
                'regular': 'Regular Patients'
            };
            const formatLabels = {
                'excel': 'Excel',
                'pdf': 'PDF'
            };
            showNotification('info', `Exporting ${typeLabels[currentPatientType] || 'All Patients'} as ${formatLabels[format] || format}...`);

            // Open export URL in new tab
            const exportWindow = window.open(url, '_blank');

            // Check if popup was blocked
            if (!exportWindow || exportWindow.closed || typeof exportWindow.closed == 'undefined') {
                showNotification('error', 'Pop-up blocked! Please allow pop-ups for this site to export.');

                // Alternative: Use form submission
                const form = document.createElement('form');
                form.method = 'GET';
                form.action = url;
                form.target = '_blank';
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        // Enable manual selection mode
        function enableManualSelection() {
            // Close dropdown
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.remove('show');

            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Build URL for manual selection mode
            let url = 'existing_info_patients.php?tab=patients-tab&manual_select=true';

            // Add patient type filter if exists
            const patientType = urlParams.get('patient_type');
            if (patientType) {
                url += `&patient_type=${patientType}`;
            }

            // Redirect to manual selection mode
            window.location.href = url;
        }

        // Disable manual selection mode
        function disableManualSelection() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Build URL to return to normal view
            let url = 'existing_info_patients.php?tab=patients-tab';

            // Add patient type filter if exists
            const patientType = urlParams.get('patient_type');
            if (patientType) {
                url += `&patient_type=${patientType}`;
            }

            // Redirect to normal view
            window.location.href = url;
        }

        // Toggle all checkboxes in manual selection
        function toggleAllSelection(checkbox) {
            const checkboxes = document.querySelectorAll('.patient-select');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        // Toggle all patients for export
        function toggleAllPatients(checkbox) {
            const checkboxes = document.querySelectorAll('.patient-select');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        // Update selected count display
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.patient-select:checked');
            const countElement = document.getElementById('selectedCount');
            if (countElement) {
                countElement.textContent = checkboxes.length;
            }
        }

        // Close export dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const exportBtn = document.querySelector('.btn-export');
            const exportOptions = document.getElementById('exportOptions');

            if (exportBtn && !exportBtn.contains(event.target) &&
                exportOptions && !exportOptions.contains(event.target)) {
                exportOptions.classList.remove('show');
            }
        });

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', function () {
            updateSelectedCount();
            
            // Age calculation for Add Patient modal
            const dobInput = document.getElementById('modal_date_of_birth');
            const ageInput = document.getElementById('modal_age');
            
            if (dobInput && ageInput) {
                dobInput.addEventListener('change', function() {
                    calculateAge(this.value, ageInput);
                });
                
                // Calculate age on page load if date is already set
                if (dobInput.value) {
                    calculateAge(dobInput.value, ageInput);
                }
            }
        });

        // Modal functions
        function openAddPatientModal() {
            const modal = document.getElementById('addPatientModal');
            modal.style.display = 'flex';
            modal.style.opacity = '0';

            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
            
            // Reset age field
            const ageInput = document.getElementById('modal_age');
            if (ageInput) {
                ageInput.value = '';
            }
        }

        function closeAddPatientModal() {
            const modal = document.getElementById('addPatientModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function clearAddPatientForm() {
            const form = document.getElementById('patientForm');
            if (form) {
                form.reset();

                // Reset all form field states
                const modalFormFields = document.querySelectorAll('.modal-form-field');
                modalFormFields.forEach(field => {
                    field.classList.remove('field-filled', 'field-empty');
                });

                // Reset age field
                const ageInput = document.getElementById('modal_age');
                if (ageInput) {
                    ageInput.value = '';
                }

                // Re-check form validity
                setTimeout(() => {
                    checkModalFormValidity();
                }, 100);
            }
        }

        // Enhanced modal functions for viewing patient info
        function openViewModal(patientId) {
            // Show loading state
            document.getElementById('modalContent').innerHTML = `
                <div class="flex justify-center items-center py-20">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-5xl text-primary mb-4"></i>
                        <p class="text-lg text-gray-600 font-medium">Loading patient data...</p>
                        <p class="text-sm text-gray-500 mt-2">Please wait while we retrieve the information</p>
                    </div>
                </div>
            `;

            // Show modal with smooth animation
            const modal = document.getElementById('viewModal');
            modal.style.display = 'flex';
            modal.style.opacity = '0';

            // Animate modal appearance
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);

            // Load patient data via AJAX
            fetch(`./get_patient_data.php?id=${patientId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    
                    // Add consultation notes section at the top
                    addConsultationNotesSection(patientId);
                    
                    // Add custom styling
                    const modalContent = document.getElementById('modalContent');
                    const forms = modalContent.querySelectorAll('form');
                    forms.forEach(form => {
                        form.classList.add('w-full', 'max-w-full');
                        
                        const containers = form.querySelectorAll('.grid, .flex');
                        containers.forEach(container => {
                            container.classList.add('w-full');
                        });
                        
                        const inputs = form.querySelectorAll('input, select, textarea');
                        inputs.forEach(input => {
                            if (!input.classList.contains('readonly-field')) {
                                input.classList.add('text-lg', 'px-4', 'py-3');
                            }
                        });
                    });
                    
                    setupMedicalForm();
                    checkConsultationNotes(patientId);
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-12 bg-red-50 rounded-xl border-2 border-red-200">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                            <h3 class="text-xl font-semibold text-red-700 mb-2">Error Loading Patient Data</h3>
                            <p class="text-red-600 mb-4">Unable to load patient information. Please try again.</p>
                            <button onclick="openViewModal(${patientId})" class="btn-primary px-6 py-3">
                                <i class="fas fa-redo mr-2"></i>Retry
                            </button>
                        </div>
                    `;
                });
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Function to collect all medical data and submit
        function saveMedicalInformation() {
            const healthInfoForm = document.getElementById('healthInfoForm');
            
            if (!healthInfoForm) {
                showNotification('error', 'Medical form not found. Please reload the page.');
                return;
            }
            
            // Validate required fields
            const requiredFields = healthInfoForm.querySelectorAll('[required]');
            let isValid = true;
            let missingFields = [];
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    missingFields.push(field.name.replace('_', ' '));
                    field.classList.add('field-empty');
                    field.classList.remove('field-filled');
                } else {
                    field.classList.add('field-filled');
                    field.classList.remove('field-empty');
                }
            });
            
            if (!isValid) {
                showNotification('error', `Please fill in all required fields: ${missingFields.join(', ')}`);
                
                // Scroll to first missing field
                const firstMissing = healthInfoForm.querySelector('.field-empty');
                if (firstMissing) {
                    firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstMissing.focus();
                }
                return;
            }
            
            // Collect all form data
            const formData = new FormData(healthInfoForm);
            
            // Add all medical fields explicitly
            const medicalFields = [
                'height', 'weight', 'blood_type', 'temperature',
                'blood_pressure', 'allergies', 'medical_history',
                'current_medications', 'family_history',
                'immunization_record', 'chronic_conditions', 'gender',
                'phic_no', 'bhw_assigned', 'family_no', 'fourps_member'
            ];
            
            medicalFields.forEach(field => {
                const element = healthInfoForm.querySelector(`[name="${field}"]`);
                if (element) {
                    formData.set(field, element.value);
                }
            });
            
            // Show loading state
            const saveBtn = document.getElementById('saveMedicalBtn');
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            saveBtn.disabled = true;
            
            // Submit the form via AJAX
            fetch(healthInfoForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                // Check if successful (you might need to adjust this based on your response)
                if (result.includes('successfully') || result.includes('Success') || result.includes('saved')) {
                    showNotification('success', 'Medical information saved successfully!');
                    
                    // Optionally reload the modal to show updated data
                    const patientId = formData.get('patient_id');
                    if (patientId) {
                        setTimeout(() => {
                            // Close and reopen modal to refresh data
                            closeViewModal();
                            setTimeout(() => {
                                openViewModal(patientId);
                            }, 500);
                        }, 2000);
                    }
                } else {
                    showNotification('error', 'Error saving medical information. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Network error: ' + error.message);
            })
            .finally(() => {
                // Restore button
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
            });
        }

        // Updated setupMedicalForm function (simplified)
        function setupMedicalForm() {
            // This function is now only for setup, not for submission
            const healthInfoForm = document.getElementById('healthInfoForm');
            
            if (healthInfoForm) {
                // Add input validation styling
                const inputs = healthInfoForm.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim()) {
                            this.classList.add('field-filled');
                            this.classList.remove('field-empty');
                        } else if (this.hasAttribute('required')) {
                            this.classList.add('field-empty');
                            this.classList.remove('field-filled');
                        }
                    });
                    
                    input.addEventListener('blur', function() {
                        if (this.hasAttribute('required') && !this.value.trim()) {
                            this.classList.add('field-empty');
                            this.classList.remove('field-filled');
                        }
                    });
                });
            }
        }

        // Print Patient Record Function
        function printPatientRecord() {
            const patientId = getPatientId();
            if (patientId) {
                // Open the print patient page in a new window
                const printWindow = window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank', 'width=1200,height=800');
                if (printWindow) {
                    printWindow.focus();
                    // Listen for the window to load and trigger print
                    printWindow.onload = function () {
                        // Give a small delay for everything to load
                        setTimeout(() => {
                            printWindow.print();
                        }, 1000);
                    };
                } else {
                    showNotification('error', 'Please allow pop-ups for this site to print');
                }
            } else {
                showNotification('error', 'No patient selected for printing');
            }
        }

        function getPatientId() {
            const selectors = [
                '#healthInfoForm input[name="patient_id"]',
                'input[name="patient_id"]',
                '[name="patient_id"]',
                '#patient_id',
                '.patient-id-input'
            ];

            for (const selector of selectors) {
                const element = document.querySelector(selector);
                if (element && element.value) {
                    return element.value;
                }
            }

            const modalContent = document.getElementById('modalContent');
            if (modalContent) {
                const hiddenInputs = modalContent.querySelectorAll('input[type="hidden"]');
                for (const input of hiddenInputs) {
                    if (input.name === 'patient_id' && input.value) {
                        return input.value;
                    }
                }
            }

            return null;
        }

        function showNotification(type, message) {
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());

            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-6 right-6 z-50 px-6 py-4 rounded-xl shadow-lg border-2 ${type === 'error' ? 'alert-error' :
                type === 'success' ? 'alert-success' :
                    'bg-blue-100 text-blue-800 border-blue-200'
                }`;

            const icon = type === 'error' ? 'fa-exclamation-circle' :
                type === 'success' ? 'fa-check-circle' : 'fa-info-circle';

            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3 text-xl"></i>
                    <span class="font-semibold">${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Enhanced modal close on outside click
        window.onclick = function (event) {
            const viewModal = document.getElementById('viewModal');
            const addPatientModal = document.getElementById('addPatientModal');
            const consultationNoteModal = document.getElementById('consultationNoteModal');

            if (event.target === viewModal) {
                closeViewModal();
            }
            if (event.target === addPatientModal) {
                closeAddPatientModal();
            }
            if (event.target === consultationNoteModal) {
                closeConsultationNoteModal();
            }
        };

        // Add keyboard support for modals
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeViewModal();
                closeAddPatientModal();
                closeConsultationNoteModal();
            }
        });

        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                var successMessage = document.getElementById('successMessage');
                var errorMessage = document.querySelector('.alert-error');

                if (successMessage) {
                    successMessage.style.display = 'none';
                }

                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }
            }, 3000);
        });

        // Tab functionality
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            const tabTriggers = document.querySelectorAll('.tab-trigger');

            // Handle tab button clicks
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');

                    // Update active tab button
                    tabButtons.forEach(btn => btn.classList.remove('border-primary', 'text-primary', 'active'));
                    button.classList.add('border-primary', 'text-primary', 'active');

                    // Show active tab content
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');

                    // Update URL with tab parameter
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });

            // Handle external tab triggers
            tabTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const tabId = trigger.getAttribute('data-tab');

                    // Update active tab button
                    tabButtons.forEach(btn => {
                        if (btn.getAttribute('data-tab') === tabId) {
                            btn.classList.add('border-primary', 'text-primary', 'active');
                        } else {
                            btn.classList.remove('border-primary', 'text-primary', 'active');
                        }
                    });

                    // Show active tab content
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');

                    // Update URL with tab parameter
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });

            // Check if URL has tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.querySelector(`.tab-btn[data-tab="${tabParam}"]`);
                if (tabButton) tabButton.click();
            }
        });

        // Clear search on page refresh
        if (window.history.replaceState && !window.location.search.includes('search=')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Ensure buttons have proper styling
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.btn-view, .btn-archive, .btn-add-patient, .btn-primary, .btn-success, .btn-gray, .btn-print, .btn-edit, .btn-save-medical, .btn-add-note, .btn-view-notes, .btn-view-all, .btn-back-to-pagination, .pagination-btn, .btn-pdf');
            buttons.forEach(button => {
                button.style.borderStyle = 'solid';
            });
        });

        // Update the addConsultationNotesSection function to include horizontal layout
function addConsultationNotesSection(patientId) {
    // Find the form to insert notes section before it
    const healthInfoForm = document.getElementById('healthInfoForm');
    if (!healthInfoForm) return;
    
    // Create consultation notes section container
    const notesSection = document.createElement('div');
    notesSection.className = 'bg-white rounded-xl border border-blue-200 shadow-sm mb-8 overflow-hidden';
    notesSection.innerHTML = `
        <div class="bg-blue-50 px-8 py-6 border-b border-blue-100">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-secondary flex items-center gap-3">
                    <i class="fas fa-sticky-note text-primary"></i>
                    Consultation Notes History
                    <span id="notesCountBadge" class="bg-primary text-white text-sm px-3 py-1 rounded-full">0 notes</span>
                </h3>
                <button onclick="openConsultationNoteModal()" 
                        class="btn-add-note px-4 py-2 text-sm font-medium">
                    <i class="fas fa-plus mr-2"></i>Add New Note
                </button>
            </div>
            <p class="text-gray-600 mt-2 text-sm">View past consultations and add new notes for this patient.</p>
        </div>
        <div id="notesHistoryContainer" class="p-6">
            <div class="horizontal-notes-container">
                <div class="text-center py-8 w-full">
                    <div class="loading-notes">
                        <div class="loading-spinner"></div>
                        <p class="ml-3 text-gray-600">Loading consultation notes...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insert the notes section before the health info form
    healthInfoForm.parentNode.insertBefore(notesSection, healthInfoForm);
    
    // Load the notes immediately
    loadConsultationNotesInline(patientId);
}

        // Function to load consultation notes inline in the main modal
function loadConsultationNotesInline(patientId) {
    const container = document.getElementById('notesHistoryContainer');
    if (!container) return;
    
    fetch(`./get_consultation_notes.php?patient_id=${patientId}&inline=true`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            
            // Update the notes count badge
            const notesCount = container.querySelectorAll('.note-card').length;
            const notesCountBadge = document.getElementById('notesCountBadge');
            if (notesCountBadge) {
                notesCountBadge.textContent = `${notesCount} note${notesCount !== 1 ? 's' : ''}`;
            }
        })
        .catch(error => {
            console.error('Error loading notes:', error);
            container.innerHTML = `
                <div class="empty-notes">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Unable to load consultation notes. Please try again.</p>
                    <button onclick="loadConsultationNotesInline(${patientId})" 
                            class="btn-primary px-4 py-2 text-sm">
                        <i class="fas fa-redo mr-1"></i> Retry
                    </button>
                </div>
            `;
        });
}

        // Function to view note details
        function viewNoteDetails(noteId) {
            fetch(`../api/get_note_details.php?id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show note details in a modal or alert
                        const note = data.note;
                        const noteHtml = `
                            <div class="bg-white p-6 rounded-lg max-w-2xl">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800">Consultation Note Details</h4>
                                        <p class="text-sm text-gray-500">${formatDate(note.consultation_date)}</p>
                                    </div>
                                    <button onclick="closeNoteDetails()" 
                                            class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Note:</label>
                                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                            ${note.note.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                    
                                    ${note.next_consultation_date ? `
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Next Consultation:</label>
                                        <div class="text-primary font-medium">
                                            ${formatDate(note.next_consultation_date)}
                                        </div>
                                    </div>
                                    ` : ''}
                                    
                                    <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-200">
                                        Created by: ${note.created_by_name || 'Staff'} 
                                        on ${formatDateTime(note.created_at)}
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button onclick="closeNoteDetails()" 
                                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg">
                                        Close
                                    </button>
                                    <button onclick="addSimilarNote(${noteId})" 
                                            class="px-4 py-2 bg-primary text-white hover:bg-primary-dark rounded-lg">
                                        <i class="fas fa-copy mr-1"></i> Use as Template
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Show in a modal
                        showCustomModal(noteHtml, 'Note Details');
                    }
                })
                .catch(error => {
                    console.error('Error loading note details:', error);
                    showNotification('error', 'Unable to load note details.');
                });
        }

        // Function to show note details in modal
        function showNoteDetailsModal(note) {
            const modalHtml = `
                <div class="bg-white p-6 rounded-lg">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800">Consultation Note Details</h4>
                            <p class="text-gray-500 mt-1">
                                <i class="fas fa-calendar-day mr-1"></i>
                                ${formatDate(note.consultation_date)}
                            </p>
                        </div>
                        <button onclick="closeNoteDetailsModal()" 
                                class="text-gray-400 hover:text-gray-600 text-xl">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Consultation Notes:</h5>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-line">
                                ${note.note}
                            </div>
                        </div>
                        
                        ${note.next_consultation_date ? `
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h5 class="text-sm font-medium text-green-700 mb-1">
                                <i class="fas fa-calendar-check mr-1"></i>
                                Next Consultation Date:
                            </h5>
                            <p class="text-green-800 font-medium">${formatDate(note.next_consultation_date)}</p>
                        </div>
                        ` : ''}
                        
                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-user-md mr-2"></i>
                                <span>Recorded by: ${note.created_by_name || 'Staff Member'}</span>
                                <span class="mx-2">•</span>
                                <i class="fas fa-clock mr-2"></i>
                                <span>${formatDateTime(note.created_at)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-end space-x-3">
                        <button onclick="closeNoteDetailsModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg">
                            Close
                        </button>
                        <button onclick="useNoteAsTemplate(${note.id})" 
                                class="px-4 py-2 bg-primary text-white hover:bg-primary-dark rounded-lg">
                            <i class="fas fa-copy mr-1"></i> Use as Template
                        </button>
                    </div>
                </div>
            `;
            
            // Create or update modal
            let modal = document.getElementById('noteDetailsModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'noteDetailsModal';
                modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[9999]';
                modal.style.display = 'none';
                document.body.appendChild(modal);
            }
            
            modal.innerHTML = `
                <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                    ${modalHtml}
                </div>
            `;
            
            // Show modal
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
        }

        function closeNoteDetailsModal() {
            const modal = document.getElementById('noteDetailsModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        // Helper functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatDateTime(dateTimeString) {
            const date = new Date(dateTimeString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Function to add a new note based on an existing one
        function addSimilarNote(noteId) {
            fetch(`../api/get_note_details.php?id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const note = data.note;
                        
                        // Open consultation note modal with template data
                        openConsultationNoteModal();
                        
                        // Fill the form with template data (after a short delay to ensure modal is open)
                        setTimeout(() => {
                            const noteTextarea = document.getElementById('note');
                            if (noteTextarea) {
                                noteTextarea.value = `Based on previous consultation on ${formatDate(note.consultation_date)}:\n\n${note.note}`;
                            }
                            
                            // Set today's date as consultation date
                            const today = new Date().toISOString().split('T')[0];
                            const dateInput = document.getElementById('consultation_date');
                            if (dateInput) dateInput.value = today;
                            
                            // Focus on the textarea
                            if (noteTextarea) noteTextarea.focus();
                        }, 300);
                    }
                })
                .catch(error => {
                    console.error('Error loading note for template:', error);
                    showNotification('error', 'Unable to load note for template.');
                });
        }

        function showCustomModal(content, title) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('customModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'customModal';
                modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[9999] modal';
                modal.style.display = 'none';
                modal.innerHTML = `
                    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                        <div id="customModalHeader" class="sticky top-0 bg-primary px-6 py-4 text-white flex justify-between items-center">
                            <h3 class="text-lg font-semibold"></h3>
                            <button onclick="closeCustomModal()" class="text-white hover:text-gray-200">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="customModalContent" class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            
            // Set content and title
            document.getElementById('customModalContent').innerHTML = content;
            document.querySelector('#customModalHeader h3').textContent = title;
            
            // Show modal
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
        }

        function closeCustomModal() {
            const modal = document.getElementById('customModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        function closeNoteDetails() {
            closeCustomModal();
        }
    </script>
</body>
</html>