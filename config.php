<?php
/**
 * ESP IoT Cloud Control Platform
 * Database Configuration
 * 
 * UPDATE THESE VALUES with your cPanel MySQL credentials
 */

// Database Configuration
define('DB_HOST', 'localhost'); // Usually 'localhost' on cPanel
define('DB_NAME', 'ashikone_espiot'); // Create this in cPanel > MySQL Databases
define('DB_USER', 'ashikone_espuser'); // Create this in cPanel > MySQL Databases  
define('DB_PASS', 'Ashik@21032001'); // Set when creating DB user in cPanel

// Site Configuration  
define('SITE_URL', 'https://esp.ashikone.com'); // Your subdomain URL
define('SITE_NAME', 'Miko');

// Security
define('SECRET_KEY', 'aX9kP2mQ7vR4sT8wB5nJ3cF6hL1yD0eG4uZ9xW2rK7pM5tN');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Error Reporting - ALWAYS keep display_errors OFF in production!
// PHP warnings/notices leak into JSON responses and break the API.
error_reporting(E_ALL);
ini_set('display_errors', 0); // Never show errors in output
ini_set('log_errors', 1); // Log errors to server error_log instead

// Timezone
date_default_timezone_set('Asia/Dhaka');

/**
 * Get Database Connection
 */
function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
                );
            // Set MySQL timezone to match PHP timezone (Asia/Dhaka = UTC+6)
            $pdo->exec("SET time_zone = '+06:00'");
        }
        catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

/**
 * Generate a secure random token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * JSON Response helper
 */
function jsonResponse($data, $code = 200)
{
    // Discard any PHP warnings/notices that may have been output
    if (ob_get_level())
        ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Token');
    echo json_encode($data);
    exit;
}

/**
 * Check if user is logged in
 */
function requireLogin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            return -1;
        }
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    return $_SESSION['user_id'];
}
?>
