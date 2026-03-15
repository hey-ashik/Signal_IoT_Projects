<?php
/**
 * Miko - IoT Cloud Control Platform
 * Landing Page
 */

require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Miko - Control ESP Devices From Anywhere</title>
    <meta name="description" content="Miko is a cloud IoT platform to control your ESP32/ESP8266 devices from anywhere. No port forwarding needed.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <script src="assets/js/theme.js"></script>
    <style>
        /* Landing Page Specific Styles */
        .hero-section {
            padding: 100px 5% 80px;
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        .hero-content {
            z-index: 2;
        }
        .mac-window {
            background: var(--bg-card, #ffffff);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(22, 163, 74, 0.25);
            border: 1px solid var(--border, #e5e7eb);
            z-index: 2;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        [data-theme="dark"] .mac-window {
            box-shadow: 0 25px 50px -12px rgba(22, 197, 94, 0.15);
            border: 1px solid #374151;
            background: #1e293b;
        }
        .mac-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f3f4f6;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }
        [data-theme="dark"] .mac-header {
            background: #0f172a;
            border-bottom: 1px solid #374151;
        }
        .mac-dots {
            display: flex;
            gap: 8px;
            flex: 1;
        }
        .mac-dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .mac-dots span.red { background: #ff5f56; }
        .mac-dots span.yellow { background: #ffbd2e; }
        .mac-dots span.green { background: #27c93f; }
        
        .mac-title {
            background: #ffffff;
            font-family: monospace, sans-serif;
            font-size: 0.8rem;
            color: #475569;
            padding: 4px 20px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }
        [data-theme="dark"] .mac-title {
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
        }
        .mac-spacer {
            flex: 1;
        }
        .hero-video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            background: #000;
            flex-grow: 1;
        }
        .hero-video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -60%;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(22, 163, 74, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        [data-theme="dark"] .hero-section::before {
            background: radial-gradient(circle, rgba(22, 163, 74, 0.12) 0%, transparent 70%);
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: var(--primary-100);
            color: var(--primary-700);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 24px;
        }
        [data-theme="dark"] .hero-badge {
            background: rgba(22, 197, 94, 0.12);
            color: var(--success);
        }
        .hero-title {
            font-size: clamp(2.25rem, 4vw, 3.5rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            max-width: 720px;
        }
        .hero-title span {
            background: linear-gradient(135deg, var(--primary), #22c55e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-desc {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 0 36px 0;
            line-height: 1.7;
        }
        .hero-actions {
            display: flex;
            gap: 14px;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }
        .btn-lg {
            padding: 14px 32px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--radius-lg);
        }
        .hero-stats {
            display: flex;
            justify-content: flex-start;
            gap: 40px;
            flex-wrap: wrap;
        }
        .hero-stat {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }
        .hero-stat i {
            color: var(--primary);
            font-size: 1.125rem;
        }

        /* Steps Section */
        .steps-section {
            padding: 80px 5%;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            background: var(--primary-100);
            color: var(--primary-700);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }
        [data-theme="dark"] .section-tag {
            background: rgba(22, 197, 94, 0.12);
            color: var(--success);
        }
        .section-title h2 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .section-title p { color: var(--text-secondary); }
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .step-card {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            position: relative;
            transition: all 0.2s ease;
        }
        .step-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
        }
        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            font-size: 0.8125rem;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .step-icon {
            width: 44px;
            height: 44px;
            background: var(--primary-100);
            color: var(--primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            margin-bottom: 14px;
        }
        [data-theme="dark"] .step-icon {
            background: rgba(22, 197, 94, 0.12);
        }
        .step-card h3 { font-size: 1rem; margin-bottom: 8px; }
        .step-card p { color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6; }

        /* Features */
        .features-section { padding: 80px 5%; }

        /* CTA */
        .cta-section {
            padding: 80px 5%;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
        }
        .cta-card {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-700));
            border-radius: var(--radius-xl);
            padding: 48px 36px;
            color: white;
        }
        .cta-card h2 { color: white; font-size: 1.75rem; margin-bottom: 12px; }
        .cta-card p { opacity: 0.9; margin-bottom: 28px; font-size: 1.0625rem; }
        .btn-white {
            background: white;
            color: var(--primary-700);
            padding: 12px 28px;
            font-weight: 600;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9375rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .landing-footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
            font-size: 0.8125rem;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 992px) {
            .hero-section {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 50px;
                padding-top: 120px;
            }
            .hero-title, .hero-desc { margin-left: auto; margin-right: auto; }
            .hero-actions, .hero-stats { justify-content: center; }
        }

        @media (max-width: 640px) {
            .hero-section { padding: 60px 5% 50px; }
            .hero-desc { font-size: 1rem; }
            .hero-stats { gap: 20px; }
            .hero-stat { font-size: 0.8125rem; }
            .steps-section, .features-section, .cta-section { padding: 50px 5%; }
            .cta-card { padding: 36px 24px; }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Navigation -->
    <nav class="landing-nav">
        <a href="index.php" class="landing-logo">
            <i class="fas fa-microchip"></i>
            <span>Miko</span>
        </a>
        <div class="landing-nav-actions">
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <a href="login.php" class="btn btn-ghost btn-sm">Sign In</a>
            <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
        </div>
    </nav>
    
    <!-- Hero -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-bolt"></i>
                IoT Made Simple
            </div>
            <h1 class="hero-title">Control Your <span>ESP Devices</span> From Anywhere</h1>
            <p class="hero-desc">
                No port forwarding. No complex setup. Upload your ESP code, connect to any WiFi, 
                and control devices from anywhere in the world through a beautiful dashboard | Android App
            </p>
            <div class="hero-actions">
                <a href="register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket"></i> Start Free
                </a>
                <a href="#how-it-works" class="btn btn-ghost btn-lg">
                    <i class="fas fa-play-circle"></i> How It Works
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <i class="fas fa-globe"></i>
                    <span>Global Access</span>
                </div>
                <div class="hero-stat">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Tokens</span>
                </div>
                <div class="hero-stat">
                    <i class="fas fa-bolt"></i>
                    <span>Real-time Control</span>
                </div>
                <div class="hero-stat">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Mobile Ready</span>
                </div>
            </div>
        </div>
        
        <div class="mac-window">
            <div class="mac-header">
                <div class="mac-dots">
                    <span class="red"></span>
                    <span class="yellow"></span>
                    <span class="green"></span>
                </div>
                <div class="mac-title">Video Tutorial</div>
                <div class="mac-spacer"></div>
            </div>
            <div class="hero-video-container">
                <iframe 
                    src="https://www.youtube.com/embed/0F30AyKEDCY?si=Lcy-eNLTQXBR3Kdn" 
                    title="Miko ESP Control Demo" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
        </div>
    </section>
    
    <!-- How It Works -->
    <section class="steps-section" id="how-it-works">
        <div class="section-title">
            <div class="section-tag"><i class="fas fa-cogs"></i> Simple Setup</div>
            <h2>Get Started in 3 Steps</h2>
            <p>From registration to controlling your devices in minutes</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                <h3>Create Account & Project</h3>
                <p>Register for free, create a project, and get your unique device token. Each project gets its own dashboard URL.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <div class="step-icon"><i class="fas fa-code"></i></div>
                <h3>Upload ESP Code</h3>
                <p>Copy the auto-generated Arduino code with your token. Upload it to your ESP32 or ESP8266. Connect to any WiFi.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <div class="step-icon"><i class="fas fa-globe-americas"></i></div>
                <h3>Control From Anywhere</h3>
                <p>Toggle GPIO pins, monitor online status, and view activity logs — all from your browser, any device, anywhere.</p>
            </div>
        </div>
    </section>
    
    <!-- Features -->
    <section class="features-section" id="features">
        <div class="section-title">
            <div class="section-tag"><i class="fas fa-star"></i> Features</div>
            <h2>Everything You Need</h2>
            <p>Powerful features for IoT enthusiasts and professionals</p>
        </div>
        <div class="landing-features">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-project-diagram"></i></div>
                <h3>Multiple Projects</h3>
                <p>Create unlimited projects. Each gets its own URL, dashboard, and device token.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-sliders-h"></i></div>
                <h3>GPIO Pin Control</h3>
                <p>Add any GPIO pin to your project. Toggle outputs from the web dashboard instantly.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-heartbeat"></i></div>
                <h3>Live Status</h3>
                <p>Real-time online/offline indicator. See WiFi signal, IP address, and memory usage.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-history"></i></div>
                <h3>Activity Logs</h3>
                <p>Track every action with detailed logs. Know who toggled what and when.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-lock"></i></div>
                <h3>Secure Access</h3>
                <p>Token-based authentication. Each project has a unique 32-character device token.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-moon"></i></div>
                <h3>Dark & Light Mode</h3>
                <p>Switch between dark and light themes. Your preference is saved automatically.</p>
            </div>
        </div>
    </section>
    
    <!-- CTA -->
    <section class="cta-section">
        <div class="cta-card">
            <h2>Ready to Go Global?</h2>
            <p>Create your free account and start controlling your ESP devices from anywhere | Download Android App</p>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
                <a href="register.php" class="btn-white">
                    <i class="fas fa-rocket"></i> Create Free Account
                </a>
                <a href="MikoApp.apk" download="MikoApp.apk" class="btn-white" style="background: #1e293b; color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                    <i class="fab fa-android" style="color: #3DDC84;"></i> Download App
                </a>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="landing-footer">
        <div style="display:inline-flex;align-items:center;gap:8px;color:var(--primary);font-weight:700;margin-bottom:6px;">
            <i class="fas fa-microchip"></i> Miko
        </div>
        <br>
        &copy; <?php echo date('Y'); ?> Miko IoT Platform. All rights reserved.
        <br>
        This Project | Developed By | Ashikul Islam
    </footer>
</body>
</html>
