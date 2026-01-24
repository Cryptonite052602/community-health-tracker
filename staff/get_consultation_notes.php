<?php
// get_consultation_notes.php
session_start();
require_once __DIR__ . '/../includes/auth.php';


redirectIfNotLoggedIn();

if (!isset($_GET['patient_id'])) {
    die('Patient ID is required');
}

$patientId = $_GET['patient_id'];
$staffId = $_SESSION['user']['id'];
$inline = isset($_GET['inline']) && $_GET['inline'] == 'true';

try {
    // Verify patient belongs to staff
    $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
    $stmt->execute([$patientId, $staffId]);
    
    if (!$stmt->fetch()) {
        die('Access denied');
    }
    
    // Get consultation notes
    $stmt = $pdo->prepare("
        SELECT cn.*, su.full_name as created_by_name 
        FROM consultation_notes cn
        LEFT JOIN sitio1_users su ON cn.created_by = su.id
        WHERE cn.patient_id = ? 
        ORDER BY cn.consultation_date DESC, cn.created_at DESC
    ");
    $stmt->execute([$patientId]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notes)) {
        if ($inline) {
            echo '<div class="empty-notes">
                    <i class="fas fa-sticky-note"></i>
                    <p>No consultation notes found.</p>
                    <p class="text-sm text-gray-500 mt-2">Add your first consultation note for this patient.</p>
                  </div>';
        } else {
            echo '<tr><td colspan="4" class="text-center py-8 text-gray-500">No consultation notes found.</td></tr>';
        }
        exit;
    }
    
    if ($inline) {
        echo '<div class="horizontal-notes-container">';
        foreach ($notes as $note) {
            $noteDate = date('M d, Y', strtotime($note['consultation_date']));
            $createdAt = date('M d, Y h:i A', strtotime($note['created_at']));
            $notePreview = htmlspecialchars(substr($note['note'], 0, 200)) . (strlen($note['note']) > 200 ? '...' : '');
            
            echo '<div class="note-card">
                    <div class="note-header">
                        <div>
                            <div class="note-date">' . $noteDate . '</div>
                            <div class="text-xs text-gray-500 mt-1">By: ' . htmlspecialchars($note['created_by_name'] ?? 'Staff') . '</div>
                        </div>
                        <span class="note-badge">Note</span>
                    </div>
                    
                    <div class="note-content">
                        <div class="note-text">' . nl2br($notePreview) . '</div>
                    </div>';
            
            if (!empty($note['next_consultation_date'])) {
                $nextDate = date('M d, Y', strtotime($note['next_consultation_date']));
                echo '<div class="text-xs text-green-600 mb-3">
                        <i class="fas fa-calendar-check mr-1"></i>
                        Next: ' . $nextDate . '
                      </div>';
            }
            
            echo '<div class="note-actions">
                    <button onclick="viewNoteDetails(' . $note['id'] . ')" class="btn-view-note">
                        <i class="fas fa-eye mr-1"></i> View
                    </button>
                    <button onclick="useNoteAsTemplate(' . $note['id'] . ')" class="btn-use-note">
                        <i class="fas fa-copy mr-1"></i> Use
                    </button>
                  </div>
                </div>';
        }
        echo '</div>';
    } else {
        // Return JSON for other uses
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'notes' => $notes]);
    }
    
} catch (PDOException $e) {
    if ($inline) {
        echo '<div class="empty-notes">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Error loading consultation notes.</p>
                <p class="text-sm text-gray-500 mt-2">' . htmlspecialchars($e->getMessage()) . '</p>
              </div>';
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>