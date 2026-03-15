<?php
/**
 * ESP IoT Cloud Control Platform
 * API Endpoint for ESP Devices & Web Dashboard
 */

ob_start(); // Buffer all output to prevent PHP warnings from corrupting JSON
require_once 'config.php';

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Token');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $pdo = getDB();

    switch ($action) {

        // ==========================================
        // ESP DEVICE ENDPOINTS
        // ==========================================

        /**
         * ESP Heartbeat & Get Commands
         * ESP calls this every 2-5 seconds to report status and get pending commands
         * 
         * GET /api.php?action=poll&token=DEVICE_TOKEN
         */
        case 'poll':
            $token = isset($_GET['token']) ? $_GET['token'] : '';
            if (empty($token)) {
                jsonResponse(['error' => 'Device token required'], 401);
            }

            // Find project by token
            $stmt = $pdo->prepare("SELECT p.id, p.project_name FROM projects p WHERE p.device_token = ? AND p.is_active = 1");
            $stmt->execute([$token]);
            $project = $stmt->fetch();

            if (!$project) {
                jsonResponse(['error' => 'Invalid or inactive device token'], 401);
            }

            // Update heartbeat
            $stmt = $pdo->prepare("
                INSERT INTO esp_heartbeat (project_id, last_seen, ip_address, firmware_version, wifi_ssid, rssi, free_heap) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    last_seen = NOW(), 
                    ip_address = VALUES(ip_address),
                    firmware_version = VALUES(firmware_version),
                    wifi_ssid = VALUES(wifi_ssid),
                    rssi = VALUES(rssi),
                    free_heap = VALUES(free_heap)
            ");
            $stmt->execute([
                $project['id'],
                isset($_GET['ip']) ? $_GET['ip'] : $_SERVER['REMOTE_ADDR'],
                isset($_GET['fw']) ? $_GET['fw'] : '1.0',
                isset($_GET['ssid']) ? $_GET['ssid'] : '',
                isset($_GET['rssi']) ? (int)$_GET['rssi'] : 0,
                isset($_GET['heap']) ? (int)$_GET['heap'] : 0
            ]);

            // Get all device states for this project
            $stmt = $pdo->prepare("SELECT gpio_pin, current_state, pin_name, pin_type FROM devices WHERE project_id = ?");
            $stmt->execute([$project['id']]);
            $devices = $stmt->fetchAll();

            $states = [];
            foreach ($devices as $dev) {
                $states[$dev['gpio_pin']] = [
                    'state' => (int)$dev['current_state'],
                    'name' => $dev['pin_name'],
                    'type' => $dev['pin_type']
                ];
            }

            jsonResponse([
                'success' => true,
                'project' => $project['project_name'],
                'pins' => $states,
                'timestamp' => time()
            ]);
            break;

        /**
         * ESP Reports its pin states back to server (useful for Graph/Sensors)
         * 
         * POST /api.php?action=report&token=DEVICE_TOKEN
         * Body: {"pins": {"34": 1500, "35": 2048}}
         */
        case 'report':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            $token = isset($_GET['token']) ? $_GET['token'] : '';
            if (empty($token)) {
                jsonResponse(['error' => 'Device token required'], 401);
            }

            $stmt = $pdo->prepare("SELECT id FROM projects WHERE device_token = ? AND is_active = 1");
            $stmt->execute([$token]);
            $project = $stmt->fetch();

            if (!$project) {
                jsonResponse(['error' => 'Invalid device token'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            if (isset($body['pins']) && is_array($body['pins'])) {
                foreach ($body['pins'] as $pin => $state) {
                    $stmt = $pdo->prepare("UPDATE devices SET current_state = ? WHERE project_id = ? AND gpio_pin = ?");
                    $stmt->execute([(int)$state, $project['id'], (int)$pin]);

                    // Also get device_id for logging
                    $stmt = $pdo->prepare("SELECT id FROM devices WHERE project_id = ? AND gpio_pin = ?");
                    $stmt->execute([$project['id'], (int)$pin]);
                    $dev = $stmt->fetch();

                    if ($dev) {
                        // Log it for graphs (cleanup older than 1 hour to prevent DB explosion)
                        $pdo->exec("DELETE FROM device_logs WHERE action = 'sensor_read' AND created_at < NOW() - INTERVAL 1 HOUR");

                        $stmt = $pdo->prepare("
                            INSERT INTO device_logs (project_id, device_id, action, value, source, ip_address) 
                            VALUES (?, ?, 'sensor_read', ?, 'esp', ?)
                        ");
                        $stmt->execute([$project['id'], $dev['id'], (string)$state, $_SERVER['REMOTE_ADDR']]);
                    }
                }
            }

            jsonResponse(['success' => true, 'message' => 'States updated']);
            break;

        // ==========================================
        // WEB DASHBOARD ENDPOINTS
        // ==========================================

        /**
         * Toggle a device pin (from web dashboard)
         * 
         * POST /api.php?action=toggle
         * Body: {"project_id": 1, "device_id": 3, "state": 1}
         */
        case 'toggle':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $projectId = isset($body['project_id']) ? (int)$body['project_id'] : 0;
            $deviceId = isset($body['device_id']) ? (int)$body['device_id'] : 0;
            $state = isset($body['state']) ? (int)$body['state'] : 0;

            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Project not found'], 404);
            }

            // Update device state
            $stmt = $pdo->prepare("UPDATE devices SET current_state = ? WHERE id = ? AND project_id = ?");
            $stmt->execute([$state, $deviceId, $projectId]);

            // Log the action
            $stmt = $pdo->prepare("
                INSERT INTO device_logs (project_id, device_id, action, value, source, ip_address) 
                VALUES (?, ?, 'toggle', ?, 'web', ?)
            ");
            $stmt->execute([$projectId, $deviceId, $state ? 'ON' : 'OFF', $_SERVER['REMOTE_ADDR']]);

            jsonResponse(['success' => true, 'state' => $state]);
            break;

        /**
         * Get project devices & states (for web dashboard refresh)
         * 
         * GET /api.php?action=devices&project_id=1
         */
        case 'devices':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT p.*, h.last_seen, h.ip_address as esp_ip, h.wifi_ssid, h.rssi, h.free_heap,
                                   CASE WHEN h.last_seen IS NOT NULL AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(h.last_seen) < 30 THEN 1 ELSE 0 END as is_online
                                   FROM projects p 
                                   LEFT JOIN esp_heartbeat h ON p.id = h.project_id 
                                   WHERE p.id = ? AND p.user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);
            $project = $stmt->fetch();

            if (!$project) {
                jsonResponse(['error' => 'Project not found'], 404);
            }

            // Get devices
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE project_id = ? ORDER BY gpio_pin");
            $stmt->execute([$projectId]);
            $devices = $stmt->fetchAll();

            jsonResponse([
                'success' => true,
                'project' => [
                    'name' => $project['project_name'],
                    'token' => $project['device_token'],
                    'is_online' => (bool)$project['is_online'],
                    'last_seen' => $project['last_seen'],
                    'esp_ip' => $project['esp_ip'],
                    'wifi_ssid' => $project['wifi_ssid'],
                    'rssi' => $project['rssi']
                ],
                'devices' => $devices
            ]);
            break;

        /**
         * Add a new device/pin to a project
         * 
         * POST /api.php?action=add_device
         * Body: {"project_id": 1, "pin_name": "Living Room Light", "gpio_pin": 2, "pin_type": "output", "icon": "lightbulb"}
         */
        case 'add_device':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $projectId = isset($body['project_id']) ? (int)$body['project_id'] : 0;
            $pinName = isset($body['pin_name']) ? sanitize($body['pin_name']) : '';
            $gpioPin = isset($body['gpio_pin']) ? (int)$body['gpio_pin'] : 0;
            $pinType = isset($body['pin_type']) ? $body['pin_type'] : 'output';
            $icon = isset($body['icon']) ? sanitize($body['icon']) : 'lightbulb';

            if (empty($pinName) || $gpioPin < 0) {
                jsonResponse(['error' => 'Pin name and GPIO number required'], 400);
            }

            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Project not found'], 404);
            }

            // Check if pin already exists
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE project_id = ? AND gpio_pin = ?");
            $stmt->execute([$projectId, $gpioPin]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'GPIO pin already exists in this project'], 400);
            }

            // Add device
            $stmt = $pdo->prepare("INSERT INTO devices (project_id, pin_name, gpio_pin, pin_type, icon) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $pinName, $gpioPin, $pinType, $icon]);

            jsonResponse(['success' => true, 'device_id' => $pdo->lastInsertId()]);
            break;

        /**
         * Delete a device/pin
         * 
         * POST /api.php?action=delete_device
         * Body: {"project_id": 1, "device_id": 3}
         */
        case 'delete_device':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $projectId = isset($body['project_id']) ? (int)$body['project_id'] : 0;
            $deviceId = isset($body['device_id']) ? (int)$body['device_id'] : 0;

            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Project not found'], 404);
            }

            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND project_id = ?");
            $stmt->execute([$deviceId, $projectId]);

            jsonResponse(['success' => true]);
            break;

        /**
         * Create a new project
         * 
         * POST /api.php?action=create_project
         * Body: {"project_name": "My Home", "project_slug": "my-home", "description": "Home automation"}
         */
        case 'create_project':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $projectName = isset($body['project_name']) ? sanitize($body['project_name']) : '';
            $projectSlug = isset($body['project_slug']) ? preg_replace('/[^a-z0-9\-_]/', '', strtolower($body['project_slug'])) : '';
            $description = isset($body['description']) ? sanitize($body['description']) : '';

            if (empty($projectName) || empty($projectSlug)) {
                jsonResponse(['error' => 'Project name and folder name are required'], 400);
            }

            if (strlen($projectSlug) < 3 || strlen($projectSlug) > 50) {
                jsonResponse(['error' => 'Folder name must be 3-50 characters'], 400);
            }

            // Check if slug already exists for this user
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE user_id = ? AND project_slug = ?");
            $stmt->execute([$_SESSION['user_id'], $projectSlug]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'A project with this folder name already exists'], 400);
            }

            // Generate device token
            $deviceToken = generateToken(16);

            // Create project
            $stmt = $pdo->prepare("INSERT INTO projects (user_id, project_name, project_slug, description, device_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $projectName, $projectSlug, $description, $deviceToken]);
            $projectId = $pdo->lastInsertId();

            // Log
            $stmt = $pdo->prepare("INSERT INTO device_logs (project_id, action, value, source, ip_address) VALUES (?, 'project_created', ?, 'web', ?)");
            $stmt->execute([$projectId, $projectName, $_SERVER['REMOTE_ADDR']]);

            jsonResponse([
                'success' => true,
                'project_id' => $projectId,
                'device_token' => $deviceToken,
                'dashboard_url' => SITE_URL . '/projects/' . $projectSlug,
                'message' => 'Project created successfully!'
            ]);
            break;

        /**
         * Delete a project
         * 
         * POST /api.php?action=delete_project
         * Body: {"project_id": 1}
         */
        case 'delete_project':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $projectId = isset($body['project_id']) ? (int)$body['project_id'] : 0;

            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);

            if ($stmt->rowCount() > 0) {
                jsonResponse(['success' => true]);
            }
            else {
                jsonResponse(['error' => 'Project not found'], 404);
            }
            break;

        /**
         * Get historical graph data for a specific device pin
         * 
         * GET /api.php?action=graph_data&device_id=1
         */
        case 'graph_data':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;

            // Verify device belongs to user's project
            $stmt = $pdo->prepare("
                SELECT d.id FROM devices d
                JOIN projects p ON d.project_id = p.id
                WHERE d.id = ? AND p.user_id = ?
            ");
            $stmt->execute([$deviceId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Device not found'], 404);
            }

            // Fetch last 1-hour data
            $stmt = $pdo->prepare("
                SELECT value, created_at 
                FROM device_logs 
                WHERE device_id = ? AND action = 'sensor_read' 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$deviceId]);

            $data = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $data]);
            break;

        /**
         * Get activity logs
         * 
         * GET /api.php?action=logs&project_id=1
         */
        case 'logs':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Project not found'], 404);
            }

            $stmt = $pdo->prepare("
                SELECT l.*, d.pin_name, d.gpio_pin 
                FROM device_logs l 
                LEFT JOIN devices d ON l.device_id = d.id 
                WHERE l.project_id = ? 
                ORDER BY l.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$projectId]);

            jsonResponse(['success' => true, 'logs' => $stmt->fetchAll()]);
            break;

        /**
         * Compile Arduino code on the server
         * 
         * POST /api.php?action=compile_code
         * Body: {"code": "void setup()...", "board": "esp32"}
         */
        case 'compile_code':
            if ($method !== 'POST') {
                jsonResponse(['error' => 'POST method required'], 405);
            }

            session_start();
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['error' => 'Authentication required'], 401);
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $code = isset($body['code']) ? $body['code'] : '';
            $board = isset($body['board']) ? $body['board'] : 'esp32';

            if (empty(trim($code))) {
                jsonResponse(['error' => 'No code provided'], 400);
            }

            // Load compiler config
            $compilerConfigPath = __DIR__ . '/compiler-config.json';
            if (!file_exists($compilerConfigPath)) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Compiler not configured. Please run setup-compiler.php first.',
                    'setup_required' => true
                ], 500);
            }

            $compilerConfig = json_decode(file_get_contents($compilerConfigPath), true);
            $arduinoCli = $compilerConfig['arduino_cli_path'];
            $configFile = $compilerConfig['config_file'];
            $sketchDir = $compilerConfig['sketch_dir'];
            $outputDir = $compilerConfig['output_dir'];

            // Fix: Set HOME environment variable for cPanel (Arduino CLI requires it)
            $homeDir = isset($compilerConfig['home_dir']) ? $compilerConfig['home_dir'] : '/home/' . get_current_user();
            putenv("HOME=$homeDir");
            $_ENV['HOME'] = $homeDir;

            // Fix: Set TMPDIR to user's home tmp (cPanel /tmp has noexec, breaks shared libs)
            $tmpDir = $homeDir . '/tmp';
            @mkdir($tmpDir, 0755, true);
            putenv("TMPDIR=$tmpDir");
            putenv("TEMP=$tmpDir");
            putenv("TMP=$tmpDir");

            // Also set Arduino-specific env vars
            $dataDir = dirname($configFile) === '.' ? $homeDir . '/arduino-cli/data' : str_replace('/arduino-cli.yaml', '', $configFile) . '/data';
            putenv("ARDUINO_DATA_DIR=$dataDir");
            putenv("ARDUINO_SKETCHBOOK_DIR=$sketchDir");

            if (!file_exists($arduinoCli) || !is_executable($arduinoCli)) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Arduino CLI not found. Please run setup-compiler.php first.',
                    'setup_required' => true
                ], 500);
            }

            // Map board name to FQBN (Fully Qualified Board Name)
            $boardMap = $compilerConfig['boards'];
            $fqbn = isset($boardMap[$board]) ? $boardMap[$board] : 'esp32:esp32:esp32';

            // Create a unique sketch directory
            $sketchName = 'miko_sketch_' . $_SESSION['user_id'] . '_' . time();
            $sketchPath = $sketchDir . '/' . $sketchName;
            $outputPath = $outputDir . '/' . $sketchName;
            @mkdir($sketchPath, 0755, true);
            @mkdir($outputPath, 0755, true);

            // Write the code to a .ino file
            $inoFile = $sketchPath . '/' . $sketchName . '.ino';
            file_put_contents($inoFile, $code);

            // Compile with HOME and TMPDIR explicitly set in the command environment
            $compileCmd = 'HOME=' . escapeshellarg($homeDir)
                . ' TMPDIR=' . escapeshellarg($tmpDir) . ' '
                . escapeshellarg($arduinoCli)
                . ' --config-file ' . escapeshellarg($configFile)
                . ' compile --jobs 1'
                . ' --fqbn ' . escapeshellarg($fqbn)
                . ' --output-dir ' . escapeshellarg($outputPath)
                . ' ' . escapeshellarg($sketchPath)
                . ' 2>&1';

            $compileOutput = shell_exec($compileCmd);

            // Find the compiled binary
            $binFile = null;
            $binExtensions = ['.ino.bin', '.ino.hex', '.bin', '.hex'];

            foreach ($binExtensions as $ext) {
                $candidate = $outputPath . '/' . $sketchName . $ext;
                if (file_exists($candidate)) {
                    $binFile = $candidate;
                    break;
                }
            }

            // Also check for any .bin file in output dir
            if (!$binFile) {
                $binFiles = glob($outputPath . '/*.bin');
                if (!empty($binFiles)) {
                    $binFile = $binFiles[0];
                }
            }

            if ($binFile && file_exists($binFile)) {
                $binaryData = base64_encode(file_get_contents($binFile));
                $binarySize = filesize($binFile);

                // Cleanup
                array_map('unlink', glob($sketchPath . '/*'));
                @rmdir($sketchPath);
                array_map('unlink', glob($outputPath . '/*'));
                @rmdir($outputPath);

                jsonResponse([
                    'success' => true,
                    'binary' => $binaryData,
                    'binarySize' => $binarySize,
                    'board' => $board,
                    'fqbn' => $fqbn,
                    'output' => $compileOutput
                ]);
            }
            else {
                // Compilation failed - cleanup and return error
                array_map('unlink', glob($sketchPath . '/*'));
                @rmdir($sketchPath);
                array_map('unlink', glob($outputPath . '/*'));
                @rmdir($outputPath);

                // Parse the error message for user-friendly display
                $errorMsg = 'Compilation failed';
                if (!empty($compileOutput)) {
                    // Extract the most relevant error lines
                    $lines = explode("\n", $compileOutput);
                    $errorLines = [];
                    foreach ($lines as $line) {
                        if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                            $errorLines[] = trim($line);
                        }
                    }
                    if (!empty($errorLines)) {
                        $errorMsg = implode("\n", array_slice($errorLines, 0, 5));
                    }
                    else {
                        $errorMsg = trim($compileOutput);
                    }
                }

                jsonResponse([
                    'success' => false,
                    'error' => $errorMsg,
                    'fullOutput' => $compileOutput,
                    'board' => $board,
                    'fqbn' => $fqbn
                ], 400);
            }
            break;

        default:
            jsonResponse([
                'service' => 'Miko IoT Platform',
                'version' => '1.0',
                'status' => 'running',
                'endpoints' => [
                    'poll' => 'GET /api.php?action=poll&token=DEVICE_TOKEN',
                    'report' => 'POST /api.php?action=report&token=DEVICE_TOKEN',
                    'toggle' => 'POST /api.php?action=toggle',
                    'devices' => 'GET /api.php?action=devices&project_id=ID',
                    'add_device' => 'POST /api.php?action=add_device',
                    'delete_device' => 'POST /api.php?action=delete_device',
                    'create_project' => 'POST /api.php?action=create_project',
                    'delete_project' => 'POST /api.php?action=delete_project',
                    'logs' => 'GET /api.php?action=logs&project_id=ID',
                    'compile_code' => 'POST /api.php?action=compile_code'
                ]
            ]);
            break;
    }


}
catch (Exception $e) {
    jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}
?>
