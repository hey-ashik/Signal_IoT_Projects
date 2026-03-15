<?php
/**
 * ESP IoT Cloud Control Platform
 * Database Setup Script
 * 
 * Run this ONCE to create all required database tables.
 * DELETE this file after setup for security!
 */

require_once 'config.php';

try {
    $pdo = getDB();
    
    // Create Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_api_key (api_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create Projects table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            project_name VARCHAR(50) NOT NULL,
            project_slug VARCHAR(50) NOT NULL,
            description TEXT,
            device_token VARCHAR(64) NOT NULL UNIQUE,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_project (user_id, project_slug),
            INDEX idx_device_token (device_token),
            INDEX idx_project_slug (project_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create Devices (GPIO Pins) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            pin_name VARCHAR(50) NOT NULL,
            gpio_pin INT NOT NULL,
            pin_type ENUM('output', 'input', 'graph') DEFAULT 'output',
            current_state TINYINT(1) DEFAULT 0,
            icon VARCHAR(50) DEFAULT 'lightbulb',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_project_pin (project_id, gpio_pin),
            INDEX idx_project_id (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create Device Logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS device_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            device_id INT,
            action VARCHAR(100) NOT NULL,
            value VARCHAR(255),
            source ENUM('web', 'esp', 'api') DEFAULT 'web',
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
            INDEX idx_project_log (project_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create ESP Heartbeat table (track online/offline)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS esp_heartbeat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL UNIQUE,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            firmware_version VARCHAR(20),
            wifi_ssid VARCHAR(50),
            rssi INT,
            free_heap INT,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "<!DOCTYPE html><html><head><title>Setup Complete</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #1e293b; border-radius: 16px; padding: 40px; max-width: 500px; 
                box-shadow: 0 25px 50px rgba(0,0,0,0.5); text-align: center; }
        h1 { color: #22c55e; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        a { color: #3b82f6; text-decoration: none; }
        .warning { background: #7c2d12; color: #fed7aa; padding: 15px; border-radius: 8px; margin-top: 20px; }
    </style></head><body>
    <div class='card'>
        <div class='icon'>✅</div>
        <h1>Setup Complete!</h1>
        <p>All database tables have been created successfully.</p>
        <div class='warning'>
            <strong>⚠️ IMPORTANT:</strong> Delete this file (setup.php) from your server for security!
        </div>
        <p style='margin-top:20px'><a href='index.php'>Go to Homepage →</a></p>
    </div></body></html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html><html><head><title>Setup Error</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #1e293b; border-radius: 16px; padding: 40px; max-width: 600px; 
                box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        h1 { color: #ef4444; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
    </style></head><body>
    <div class='card'>
        <h1>❌ Setup Failed</h1>
        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your database credentials in <code>config.php</code></p>
    </div></body></html>";
}
?>
