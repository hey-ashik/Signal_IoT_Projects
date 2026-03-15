<?php
/**
 * ESP IoT Cloud Control Platform
 * Logout Handler
 */

require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDB();
        $logStmt = $pdo->prepare("INSERT INTO user_activity (user_id, action, ip_address) VALUES (?, 'logout', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch(Exception $e) { /* Ignore if table doesn't exist yet */ }
}

session_destroy();
header('Location: index.php');
exit;
?>
