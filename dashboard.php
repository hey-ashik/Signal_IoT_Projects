<?php
/**
 * ESP IoT Cloud Control Platform
 * Main Dashboard - Shows all user projects
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

// Get all projects with online status (use UNIX_TIMESTAMP to avoid timezone issues)
$stmt = $pdo->prepare("
    SELECT p.*, h.last_seen, 
           (SELECT COUNT(*) FROM devices WHERE project_id = p.id) as device_count,
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
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=5">
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
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="ai-code-editor.php" class="nav-item">
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
            <h2>Dashboard</h2>
            <div class="topbar-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="btn btn-primary btn-sm" onclick="showCreateProject()">
                    <i class="fas fa-plus"></i> <span class="btn-text">New Project</span>
                </button>
            </div>
        </div>
        
        <div class="content-area">
            <!-- Stats Overview -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #dcfce7; color: #15803d;">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Projects</span>
                        <span class="stat-card-value"><?php echo count($projects); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #dbeafe; color: #1d4ed8;">
                        <i class="fa-solid fa-signal"></i>
                    </div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Online Devices</span>
                        <span class="stat-card-value"><?php echo count(array_filter($projects, function ($p) {
    return $p['is_online']; })); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #f3e8ff; color: #7e22ce;">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Pins</span>
                        <span class="stat-card-value"><?php echo array_sum(array_column($projects, 'device_count')); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #ffedd5; color: #c2410c;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Your API Key</span>
                        <span class="stat-card-value" 
                              style="font-size: 1.25rem; font-family: monospace; letter-spacing: -0.5px; cursor: pointer;" 
                              onclick="copyApiKey('<?php echo $user['api_key']; ?>')" 
                              title="Click to copy full API Key">
                            <?php echo substr($user['api_key'], 0, 8) . '...'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Projects Grid -->
            <div class="section-header-inline">
                <h3>Your Projects</h3>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3>No Projects Yet</h3>
                    <p>Create your first project to start controlling ESP devices from anywhere!</p>
                    <button class="btn btn-primary" onclick="showCreateProject()">
                        <i class="fas fa-plus"></i> Create First Project
                    </button>
                </div>
            <?php
else: ?>
                <div class="projects-grid">
                    <?php foreach ($projects as $project): ?>
                        <div class="project-card" id="project-<?php echo $project['id']; ?>">
                            <div class="project-card-header">
                                <div class="project-status">
                                    <span class="status-indicator <?php echo $project['is_online'] ? 'online' : 'offline'; ?>">
                                        <span class="status-dot-lg"></span>
                                        <?php echo $project['is_online'] ? 'Online' : 'Offline'; ?>
                                    </span>
                                </div>
                                <div class="project-actions-menu">
                                    <button class="btn-icon" onclick="toggleMenu(<?php echo $project['id']; ?>)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu" id="menu-<?php echo $project['id']; ?>">
                                        <button onclick="showToken(<?php echo $project['id']; ?>, '<?php echo $project['device_token']; ?>')">
                                            <i class="fas fa-key"></i> View Token
                                        </button>
                                        <button onclick="showEspCode(<?php echo $project['id']; ?>, '<?php echo $project['device_token']; ?>', '<?php echo $project['project_name']; ?>')">
                                            <i class="fas fa-code"></i> ESP Code
                                        </button>
                                        <button class="danger" onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo addslashes($project['project_name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="project-card-body">
                                <h3><?php echo $project['project_name']; ?></h3>
                                <p class="project-description"><?php echo $project['description'] ?: 'No description'; ?></p>
                                <div class="project-meta">
                                    <span><i class="fas fa-microchip"></i> <?php echo $project['device_count']; ?> pins</span>
                                    <span><i class="fas fa-link"></i> /projects/<?php echo $project['project_slug']; ?></span>
                                </div>
                            </div>
                            <div class="project-card-footer">
                                <a href="projects/<?php echo $project['project_slug']; ?>" class="btn btn-primary btn-sm btn-full">
                                    <i class="fas fa-external-link-alt"></i> Open Dashboard
                                </a>
                            </div>
                        </div>
                    <?php
    endforeach; ?>
                    
                    <!-- Add New Project Card -->
                    <div class="project-card add-project-card" onclick="showCreateProject()">
                        <div class="add-project-content">
                            <i class="fas fa-plus-circle"></i>
                            <span>Create New Project</span>
                        </div>
                    </div>
                </div>
            <?php
endif; ?>
        </div>
    </main>
    
    <!-- Create Project Modal -->
    <div class="modal-overlay" id="createProjectModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Project</h3>
                <button class="modal-close" onclick="closeModal('createProjectModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="project_name"><i class="fas fa-tag"></i> Project Name</label>
                    <input type="text" id="project_name" placeholder="e.g., Home Automation" required>
                </div>
                <div class="form-group">
                    <label for="project_slug"><i class="fas fa-folder"></i> Folder Name (URL path)</label>
                    <input type="text" id="project_slug" placeholder="e.g., home-automation" 
                           pattern="[a-z0-9\-_]+" required>
                    <small>Lowercase letters, numbers, hyphens, underscores only. Your dashboard URL will be: <strong><?php echo SITE_URL; ?>/projects/<span id="slug-preview">your-folder</span></strong></small>
                </div>
                <div class="form-group">
                    <label for="project_desc"><i class="fas fa-align-left"></i> Description (optional)</label>
                    <textarea id="project_desc" rows="3" placeholder="What is this project for?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeModal('createProjectModal')">Cancel</button>
                <button class="btn btn-primary" onclick="createProject()" id="create-project-btn">
                    <i class="fas fa-plus"></i> Create Project
                </button>
            </div>
        </div>
    </div>
    
    <!-- Token Modal -->
    <div class="modal-overlay" id="tokenModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Device Token</h3>
                <button class="modal-close" onclick="closeModal('tokenModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Use this token in your ESP code to connect to this project:</p>
                <div class="token-display">
                    <code id="tokenValue"></code>
                    <button class="btn btn-ghost btn-sm" onclick="copyToken()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="alert alert-warning" style="margin-top:15px">
                    <i class="fas fa-exclamation-triangle"></i>
                    Keep this token secret! Anyone with this token can control your device.
                </div>
            </div>
        </div>
    </div>
    
    <!-- ESP Code Modal -->
    <div class="modal-overlay" id="espCodeModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-code"></i> ESP Arduino Code</h3>
                <button class="modal-close" onclick="closeModal('espCodeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Copy this code to your Arduino IDE and upload it to your ESP8266/ESP32:</p>
                <div class="code-actions">
                    <button class="btn btn-primary btn-sm" onclick="copyEspCode()">
                        <i class="fas fa-copy"></i> Copy Code
                    </button>
                </div>
                <pre class="code-block" id="espCodeBlock"></pre>
                <div class="alert alert-info" style="margin-top:15px">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> Replace <code>YOUR_WIFI_SSID</code> and <code>YOUR_WIFI_PASSWORD</code> with your actual WiFi credentials before uploading.
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js?v=6"></script>
    <script>
        const SITE_URL = '<?php echo SITE_URL; ?>';
        
        // Sidebar toggle for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('show');
        }
        
        // Slug preview
        document.getElementById('project_slug').addEventListener('input', function() {
            let val = this.value.toLowerCase().replace(/[^a-z0-9\-_]/g, '');
            this.value = val;
            document.getElementById('slug-preview').textContent = val || 'your-folder';
        });
        
        // Auto-generate slug from name
        document.getElementById('project_name').addEventListener('input', function() {
            const slugField = document.getElementById('project_slug');
            if (!slugField.dataset.manual) {
                slugField.value = this.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-_]/g, '');
                document.getElementById('slug-preview').textContent = slugField.value || 'your-folder';
            }
        });
        
        document.getElementById('project_slug').addEventListener('focus', function() {
            this.dataset.manual = 'true';
        });
        
        // Auto-refresh the dashboard without a full page reload
        setInterval(function() {
            var modalOpen = document.querySelector('.modal-overlay.show');
            var dropdownOpen = document.querySelector('.dropdown-menu.show');
            
            if (!modalOpen) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Update stats row
                        const newStatsRow = doc.querySelector('.stats-row');
                        const currentStatsRow = document.querySelector('.stats-row');
                        if (newStatsRow && currentStatsRow) {
                            currentStatsRow.innerHTML = newStatsRow.innerHTML;
                        }
                        
                        // If no dropdown is open, safe to replace the whole projects grid
                        if (!dropdownOpen) {
                            const newProjectsGrid = doc.querySelector('.projects-grid');
                            const currentProjectsGrid = document.querySelector('.projects-grid');
                            const newEmptyState = doc.querySelector('.empty-state');
                            const currentEmptyState = document.querySelector('.empty-state');
                            
                            if (newProjectsGrid && currentProjectsGrid) {
                                currentProjectsGrid.innerHTML = newProjectsGrid.innerHTML;
                            } else if (newEmptyState && currentEmptyState) {
                                currentEmptyState.innerHTML = newEmptyState.innerHTML;
                            } else if (newEmptyState && currentProjectsGrid) {
                                currentProjectsGrid.parentNode.replaceChild(newEmptyState, currentProjectsGrid);
                            } else if (newProjectsGrid && currentEmptyState) {
                                currentEmptyState.parentNode.replaceChild(newProjectsGrid, currentEmptyState);
                            }
                            
                            // Also update sidebar nav if we replaced grid
                            const newSidebarNav = doc.querySelector('.sidebar-nav');
                            const currentSidebarNav = document.querySelector('.sidebar-nav');
                            if (newSidebarNav && currentSidebarNav) {
                                currentSidebarNav.innerHTML = newSidebarNav.innerHTML;
                            }
                        } else {
                            // If dropdown is open, only update status indicators surgically
                            // to avoid closing the dropdown
                            const currentCards = document.querySelectorAll('.project-card');
                            currentCards.forEach(card => {
                                const id = card.id;
                                if (id) {
                                    const newCard = doc.getElementById(id);
                                    if (newCard) {
                                        // Update status
                                        const statusObj = card.querySelector('.project-status');
                                        const newStatusObj = newCard.querySelector('.project-status');
                                        if (statusObj && newStatusObj) {
                                            statusObj.innerHTML = newStatusObj.innerHTML;
                                        }
                                        
                                        // Update device count meta
                                        const metaObj = card.querySelector('.project-meta');
                                        const newMetaObj = newCard.querySelector('.project-meta');
                                        if (metaObj && newMetaObj) {
                                            metaObj.innerHTML = newMetaObj.innerHTML;
                                        }
                                    }
                                }
                            });
                            
                            // Update sidebar dots surgically
                            const currentSidebarItems = document.querySelectorAll('.sidebar-nav .nav-item');
                            const newSidebarItems = doc.querySelectorAll('.sidebar-nav .nav-item');
                            
                            currentSidebarItems.forEach((item, index) => {
                                const dot = item.querySelector('.status-dot');
                                const newDot = newSidebarItems[index] ? newSidebarItems[index].querySelector('.status-dot') : null;
                                if (dot && newDot) {
                                    dot.className = newDot.className;
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error refreshing dashboard:', error));
            }
        }, 5000);
    </script>
</body>
</html>
