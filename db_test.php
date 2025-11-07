<?php
// Simple database test
require_once 'backend/config/db.php';

try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection works!";
    echo "<br>Host: sql107.infinityfree.com";
    echo "<br>Database: if0_40203219_Okaris";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>