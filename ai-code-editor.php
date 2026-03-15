<?php
/**
 * AI Code Editor
 * Powered by Groq Cloud - LLaMA 3 120B OSS Model
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
    <title>AI Code Editor - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="AI-powered code editor. Write code, upload files, and let AI enhance your projects with new features.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=5">
    <link rel="stylesheet" href="assets/css/ai-editor.css?v=1">
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
            <a href="ai-code-editor.php" class="nav-item active">
                <i class="fas fa-wand-magic-sparkles"></i>
                <span>AI Code Editor</span>
            </a>
            <a href="upload-code.php" class="nav-item">
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
            <h2><i class="fas fa-wand-magic-sparkles" style="color: var(--primary); margin-right: 8px;"></i>AI Code Editor <span style="font-size:0.65rem;font-weight:500;padding:3px 8px;border-radius:6px;background:var(--primary-100);color:var(--primary-700);vertical-align:middle;margin-left:6px;">Arduino & ESP Supported</span></h2>
            <div class="topbar-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
        
        <div class="content-area ai-editor-content">
            <!-- Editor Layout -->
            <div class="editor-layout">
                <!-- Left Panel: Code Input -->
                <div class="editor-panel" id="inputPanel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-code"></i>
                            <span>Your Code</span>
                        </div>
                        <div class="panel-actions">
                            <label class="btn btn-ghost btn-sm upload-btn" for="fileUpload" title="Upload File">
                                <i class="fas fa-upload"></i>
                                <span class="action-text">Upload</span>
                            </label>
                            <input type="file" id="fileUpload" accept=".py,.js,.ts,.jsx,.tsx,.html,.css,.php,.c,.cpp,.java,.go,.rs,.rb,.swift,.kt,.dart,.lua,.sh,.sql,.json,.xml,.yaml,.yml,.md,.txt,.ino,.h,.hpp" style="display:none;">
                            <button class="btn btn-ghost btn-sm" onclick="clearCode()" title="Clear Code">
                                <i class="fas fa-eraser"></i>
                                <span class="action-text">Clear</span>
                            </button>
                            <div class="lang-select-wrapper">
                                <select id="languageSelect" class="lang-select" title="Language">
                                    <option value="auto">Auto Detect</option>
                                    <option value="javascript">JavaScript</option>
                                    <option value="typescript">TypeScript</option>
                                    <option value="python">Python</option>
                                    <option value="php">PHP</option>
                                    <option value="html">HTML</option>
                                    <option value="css">CSS</option>
                                    <option value="cpp">C/C++</option>
                                    <option value="java">Java</option>
                                    <option value="go">Go</option>
                                    <option value="rust">Rust</option>
                                    <option value="ruby">Ruby</option>
                                    <option value="swift">Swift</option>
                                    <option value="kotlin">Kotlin</option>
                                    <option value="dart">Dart</option>
                                    <option value="arduino">Arduino (C++)</option>
                                    <option value="sql">SQL</option>
                                    <option value="shell">Shell/Bash</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="code-editor-wrapper">
                        <div class="line-numbers" id="lineNumbers"></div>
                        <textarea id="codeInput" class="code-textarea" 
                                  placeholder="Paste or type your code here...

Or upload a file using the Upload button above.

Example:
function greet(name) {
    return 'Hello, ' + name;
}" spellcheck="false"></textarea>
                    </div>
                    <div class="file-info-bar" id="fileInfoBar" style="display:none;">
                        <i class="fas fa-file-code"></i>
                        <span id="fileName"></span>
                        <button class="btn-icon-sm" onclick="clearUpload()" title="Remove file">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Center Panel: AI Prompt -->
                <div class="ai-prompt-panel" id="promptPanel">
                    <div class="prompt-card">
                        <div class="prompt-header">
                            <div class="ai-badge">
                                <div class="ai-icon-glow">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <span>AI Assistant</span>
                                <span class="model-tag">GPT-120B</span>
                                <span class="model-tag" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-microchip" style="font-size:0.55rem;"></i> Arduino</span>
                            </div>
                        </div>
                        <div class="prompt-body">
                            <label for="aiPrompt" class="prompt-label">What would you like AI to do?</label>
                            <textarea id="aiPrompt" class="prompt-textarea" 
                                      placeholder="Examples:
• Add WiFi reconnect logic to my Arduino code
• Add a DHT11 temperature sensor reading
• Add error handling and input validation
• Convert ESP8266 code to work with ESP32
• Add OTA (Over-The-Air) update support
• Optimize memory usage for ESP boards
• Add comments and documentation"></textarea>
                            <div class="prompt-suggestions" id="promptSuggestions">
                                <button class="suggestion-chip" onclick="useSuggestion('Add WiFi reconnect and error handling for ESP32/ESP8266')">
                                    <i class="fas fa-microchip"></i> Arduino WiFi
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Add DHT11/DHT22 temperature and humidity sensor reading')">
                                    <i class="fas fa-temperature-half"></i> Sensor Reading
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Add OTA (Over-The-Air) firmware update support')">
                                    <i class="fas fa-cloud-arrow-up"></i> OTA Update
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Add error handling and input validation')">
                                    <i class="fas fa-shield-halved"></i> Error Handling
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Optimize this code for better performance')">
                                    <i class="fas fa-bolt"></i> Optimize
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Add comments and documentation to explain the code')">
                                    <i class="fas fa-comment-dots"></i> Add Comments
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Convert ESP8266 code to ESP32 compatible')">
                                    <i class="fas fa-arrows-rotate"></i> ESP32 Convert
                                </button>
                                <button class="suggestion-chip" onclick="useSuggestion('Fix bugs and potential issues in this code')">
                                    <i class="fas fa-bug"></i> Fix Bugs
                                </button>
                            </div>
                        </div>
                        <div class="prompt-footer">
                            <button class="btn btn-primary btn-generate" id="generateBtn" onclick="generateCode()">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                <span>Generate with AI</span>
                            </button>
                        </div>
                    </div>

                    <!-- Chat History -->
                    <div class="chat-history" id="chatHistory" style="display:none;">
                        <div class="chat-history-header">
                            <i class="fas fa-clock-rotate-left"></i>
                            <span>Conversation</span>
                            <button class="btn-icon-sm" onclick="clearHistory()" title="Clear history">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="chat-messages" id="chatMessages"></div>
                    </div>
                </div>
                
                <!-- Right Panel: AI Output -->
                <div class="editor-panel output-panel" id="outputPanel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span>AI Output</span>
                        </div>
                        <div class="panel-actions">
                            <button class="btn btn-primary btn-sm" onclick="copyOutput()" id="copyBtn" disabled title="Copy Output">
                                <i class="fas fa-copy"></i>
                                <span class="action-text">Copy</span>
                            </button>
                            <button class="btn btn-ghost btn-sm" onclick="applyToInput()" id="applyBtn" disabled title="Use as Input">
                                <i class="fas fa-arrow-left"></i>
                                <span class="action-text">Use as Input</span>
                            </button>
                        </div>
                    </div>
                    <div class="code-editor-wrapper output-wrapper">
                        <div class="line-numbers" id="outputLineNumbers"></div>
                        <div class="code-output" id="codeOutput">
                            <div class="empty-output" id="emptyOutput">
                                <div class="empty-output-icon">
                                    <i class="fas fa-wand-magic-sparkles"></i>
                                </div>
                                <h4>AI-Generated Code Will Appear Here</h4>
                                <p>Write your code on the left, describe what you want, and click <strong>"Generate with AI"</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="ai-loading-overlay" id="loadingOverlay">
        <div class="ai-loading-card">
            <div class="ai-loading-animation">
                <div class="ai-orb"></div>
                <div class="ai-ring"></div>
                <div class="ai-ring ring-2"></div>
            </div>
            <h3>AI is working its magic...</h3>
            <p id="loadingStatus">Analyzing your code and generating improvements</p>
            <div class="loading-progress">
                <div class="loading-progress-bar" id="loadingBar"></div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="assets/js/app.js?v=4"></script>
    <script src="assets/js/ai-editor.js?v=1"></script>
</body>
</html>
