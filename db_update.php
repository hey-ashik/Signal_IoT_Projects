<?php
require_once 'config.php';
try {
    $pdo = getDB();
    $pdo->exec("ALTER TABLE devices MODIFY pin_type ENUM('output', 'input', 'graph') DEFAULT 'output'");
    echo "<h1>Database updated successfully!</h1><p>You can now use Graph Widgets.</p>";
} catch (Exception $e) {
    echo "<h1>Error updating database:</h1><p>" . $e->getMessage() . "</p>";
}
?>
