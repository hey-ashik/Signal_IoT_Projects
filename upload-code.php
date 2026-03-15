<?php
/**
 * Upload Code - Web Serial Flash Tool
 * Flash Arduino/ESP code directly from the browser via USB
 * Uses Web Serial API for direct communication
 */

require_once 'config.php';
$userId = requireLogin();

if ($userId === -1) {
    header('Location: ashikadmin.php');
    exit;
}

$pdo = getDB();

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get all projects for sidebar
$stmt = $pdo->prepare("
    SELECT p.*, 
           CASE WHEN h.last_seen IS NOT NULL AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(h.last_seen) < 30 THEN 1 ELSE 0 END as is_online
    FROM projects p 
    LEFT JOIN esp_heartbeat h ON p.id = h.project_id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$userId]);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Code - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="Upload Arduino and ESP code directly to your microcontroller via USB from your browser.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=5">
    <link rel="stylesheet" href="assets/css/upload-code.css?v=1">
    <script src="assets/js/theme.js"></script>
</head>
<body class="dashboard-page">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <i class="fas fa-microchip"></i>
                <span>Miko</span>
            </a>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="ai-code-editor.php" class="nav-item">
                <i class="fas fa-wand-magic-sparkles"></i>
                <span>AI Code Editor</span>
            </a>
            <a href="upload-code.php" class="nav-item active">
                <i class="fas fa-upload"></i>
                <span>Upload Code</span>
            </a>
            <div class="nav-divider">
                <span>Your Projects</span>
            </div>
            <?php foreach ($projects as $project): ?>
                <a href="projects/<?php echo $project['project_slug']; ?>" class="nav-item">
                    <span class="status-dot <?php echo $project['is_online'] ? 'online' : 'offline'; ?>"></span>
                    <span><?php echo $project['project_name']; ?></span>
                </a>
            <?php
endforeach; ?>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo $user['username']; ?></span>
                    <span class="user-email"><?php echo $user['email']; ?></span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-ghost btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>
    
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2>
                <i class="fas fa-upload" style="color: var(--primary); margin-right: 8px;"></i>Upload Code 
                <span class="topbar-badge">ESP32 & ESP8266</span>
            </h2>
            <div class="topbar-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
        
        <div class="content-area upload-code-content">
            <!-- Browser Support Warning -->
            <div class="browser-warning" id="browserWarning" style="display:none;">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="warning-content">
                    <h4>Browser Not Supported</h4>
                    <p>Web Serial API requires <strong>Google Chrome</strong>, <strong>Microsoft Edge</strong>, or <strong>Opera</strong> (version 89+). Please switch to a supported browser to use this feature.</p>
                </div>
            </div>

            <!-- Connection Bar -->
            <div class="connection-bar" id="connectionBar">
                <div class="connection-status">
                    <div class="connection-dot" id="connectionDot"></div>
                    <span class="connection-label" id="connectionLabel">No Device Connected</span>
                </div>
                <div class="connection-info" id="connectionInfo" style="display:none;">
                    <span class="info-chip" id="portInfo"><i class="fas fa-usb"></i> <span id="portName">-</span></span>
                    <span class="info-chip" id="baudInfo"><i class="fas fa-gauge-high"></i> <span id="baudDisplay">115200</span> baud</span>
                </div>
                <div class="connection-actions">
                    <div class="baud-selector">
                        <label for="baudRate">Baud:</label>
                        <select id="baudRate" class="baud-select">
                            <option value="9600">9600</option>
                            <option value="19200">19200</option>
                            <option value="38400">38400</option>
                            <option value="57600">57600</option>
                            <option value="74880">74880</option>
                            <option value="115200" selected>115200</option>
                            <option value="230400">230400</option>
                            <option value="460800">460800</option>
                            <option value="921600">921600</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm" id="connectBtn" onclick="handleConnect()">
                        <i class="fas fa-plug"></i>
                        <span class="btn-label">Connect</span>
                    </button>
                    <button class="btn btn-ghost btn-sm" id="disconnectBtn" onclick="handleDisconnect()" style="display:none;">
                        <i class="fas fa-plug-circle-xmark"></i>
                        <span class="btn-label">Disconnect</span>
                    </button>
                </div>
            </div>

            <!-- Main Editor Layout -->
            <div class="upload-layout">
                <!-- Code Editor Panel -->
                <div class="upload-panel code-panel" id="codePanel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-code"></i>
                            <span>Code Editor</span>
                            <span class="lang-badge" id="langBadge">Arduino C++</span>
                        </div>
                        <div class="panel-actions">
                            <label class="btn btn-ghost btn-sm" for="codeFileUpload" title="Open File">
                                <i class="fas fa-folder-open"></i>
                                <span class="action-text">Open</span>
                            </label>
                            <input type="file" id="codeFileUpload" accept=".ino,.cpp,.c,.h,.hpp,.txt" style="display:none;">
                            <button class="btn btn-ghost btn-sm" onclick="copyEditorCode()" title="Copy Code">
                                <i class="fas fa-copy"></i>
                                <span class="action-text">Copy</span>
                            </button>
                            <button class="btn btn-ghost btn-sm" onclick="clearEditorCode()" title="Clear">
                                <i class="fas fa-eraser"></i>
                                <span class="action-text">Clear</span>
                            </button>
                            <button class="btn btn-ghost btn-sm" onclick="downloadCode()" title="Download">
                                <i class="fas fa-download"></i>
                                <span class="action-text">Save</span>
                            </button>
                        </div>
                    </div>
                    <div class="code-editor-area">
                        <div class="editor-line-numbers" id="editorLineNumbers"></div>
                        <textarea id="codeEditor" class="code-textarea" spellcheck="false" placeholder="Paste your Arduino/ESP code here...

Example ESP32 Blink:

void setup() {
  Serial.begin(115200);
  pinMode(2, OUTPUT);
}

void loop() {
  digitalWrite(2, HIGH);
  delay(1000);
  digitalWrite(2, LOW);
  delay(1000);
  Serial.println(&quot;Blink!&quot;);
}"></textarea>
                    </div>
                    <div class="file-bar" id="fileBar" style="display:none;">
                        <i class="fas fa-file-code"></i>
                        <span id="openFileName"></span>
                        <span class="file-size" id="openFileSize"></span>
                        <button class="btn-icon-sm" onclick="clearFile()" title="Remove file">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Right Side: Upload & Monitor -->
                <div class="upload-right-panel">
                    <!-- Upload Actions Card -->
                    <div class="upload-actions-card">
                        <div class="upload-card-header">
                            <h3><i class="fas fa-rocket"></i> Flash to Device</h3>
                        </div>
                        <div class="upload-card-body">
                            <!-- Board Selector -->
                            <div class="board-selector">
                                <label><i class="fas fa-microchip"></i> Board Type</label>
                                <div class="board-options">
                                    <button class="board-option active" data-board="esp32" onclick="selectBoard('esp32')">
                                        <div class="board-option-icon">
                                            <i class="fas fa-microchip"></i>
                                        </div>
                                        <span>ESP32 (Generic)</span>
                                    </button>
                                    <button class="board-option" data-board="esp32-devkit-v1" onclick="selectBoard('esp32-devkit-v1')">
                                        <div class="board-option-icon">
                                            <i class="fas fa-microchip"></i>
                                        </div>
                                        <span>ESP32 DevKit V1</span>
                                    </button>
                                    <button class="board-option" data-board="esp8266" onclick="selectBoard('esp8266')">
                                        <div class="board-option-icon">
                                            <i class="fas fa-microchip"></i>
                                        </div>
                                        <span>ESP8266</span>
                                    </button>
                                    <button class="board-option" data-board="arduino-uno" onclick="selectBoard('arduino-uno')">
                                        <div class="board-option-icon">
                                            <i class="fas fa-microchip"></i>
                                        </div>
                                        <span>Arduino Uno</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Upload Steps -->
                            <div class="upload-steps">
                                <div class="step-item" id="step1">
                                    <div class="step-number">1</div>
                                    <div class="step-content">
                                        <span class="step-title">Connect Device</span>
                                        <span class="step-desc">Plug in via USB & click Connect</span>
                                    </div>
                                    <div class="step-status" id="step1Status">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                </div>
                                <div class="step-item" id="step2">
                                    <div class="step-number">2</div>
                                    <div class="step-content">
                                        <span class="step-title">Write Code</span>
                                        <span class="step-desc">Paste or type your Arduino code</span>
                                    </div>
                                    <div class="step-status" id="step2Status">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                </div>
                                <div class="step-item" id="step3">
                                    <div class="step-number">3</div>
                                    <div class="step-content">
                                        <span class="step-title">Compile & Upload</span>
                                        <span class="step-desc">Click upload to flash the code</span>
                                    </div>
                                    <div class="step-status" id="step3Status">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Button -->
                            <button class="btn btn-primary btn-upload" id="uploadBtn" onclick="handleUpload()">
                                <i class="fas fa-bolt"></i>
                                <span>Compile & Upload</span>
                            </button>
                            
                            <!-- Progress Bar -->
                            <div class="upload-progress" id="uploadProgress" style="display:none;">
                                <div class="progress-header">
                                    <span class="progress-label" id="progressLabel">Compiling...</span>
                                    <span class="progress-percent" id="progressPercent">0%</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                            </div>

                            <!-- Info Note -->
                            <div class="upload-note">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>How it works:</strong> Your code is compiled on our cloud server, then the binary is flashed directly to your board via Web Serial API. No Arduino IDE needed!
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Serial Monitor -->
                    <div class="serial-monitor-card">
                        <div class="monitor-header">
                            <h3><i class="fas fa-terminal"></i> Serial Monitor</h3>
                            <div class="monitor-actions">
                                <button class="btn btn-ghost btn-sm" onclick="clearMonitor()" title="Clear Monitor">
                                    <i class="fas fa-eraser"></i>
                                </button>
                                <label class="auto-scroll-toggle">
                                    <input type="checkbox" id="autoScroll" checked>
                                    <span>Auto-scroll</span>
                                </label>
                            </div>
                        </div>
                        <div class="monitor-output" id="serialMonitor">
                            <div class="monitor-welcome">
                                <i class="fas fa-terminal"></i>
                                <span>Serial monitor will show output when a device is connected...</span>
                            </div>
                        </div>
                        <div class="monitor-input-bar">
                            <input type="text" id="serialInput" class="monitor-input" placeholder="Type message to send..." disabled>
                            <button class="btn btn-primary btn-sm" id="sendBtn" onclick="sendSerialMessage()" disabled>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="assets/js/app.js?v=6"></script>
    <script src="assets/js/upload-code.js?v=2"></script>
</body>
</html>
