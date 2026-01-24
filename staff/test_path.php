<?php
echo "Test file is working!<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Server document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// Test database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

global $pdo;
if ($pdo) {
    echo "Database connection: OK<br>";
    
    // Test consultation_notes table
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'consultation_notes'");
        if ($stmt->fetch()) {
            echo "consultation_notes table: EXISTS<br>";
            
            // Check structure
            $stmt = $pdo->query("DESCRIBE consultation_notes");
            echo "Table structure:<br>";
            while ($row = $stmt->fetch()) {
                echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
            }
        } else {
            echo "consultation_notes table: NOT FOUND<br>";
        }
    } catch (Exception $e) {
        echo "Error checking table: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Database connection: FAILED<br>";
}
?>