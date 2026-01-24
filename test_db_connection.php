<?php
// Test database connection and table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if consultation_notes table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'consultation_notes'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ consultation_notes table exists</p>";
        
        // Show table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE consultation_notes");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>✗ consultation_notes table does not exist</p>";
    }
    
    // Test insert
    echo "<h3>Test Insert:</h3>";
    $test_sql = "INSERT INTO consultation_notes 
                (patient_id, note, consultation_date, created_by, created_at) 
                VALUES (1, 'Test note', CURDATE(), 1, NOW())";
    
    try {
        $pdo->exec($test_sql);
        $last_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✓ Test insert successful (ID: $last_id)</p>";
        
        // Clean up test data
        $pdo->exec("DELETE FROM consultation_notes WHERE id = $last_id");
        echo "<p style='color: blue;'>✓ Test data cleaned up</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Test insert failed: " . $e->getMessage() . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
?>