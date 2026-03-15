<?php
/**
 * Miko - IoT Cloud Control Platform
 * User Login
 */

require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = isset($_POST['login']) ? sanitize($_POST['login']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($login) || empty($password)) {
        $error = 'All fields are required';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Track Login Activity for Admin Panel
                try {
                    $logStmt = $pdo->prepare("INSERT INTO user_activity (user_id, action, ip_address) VALUES (?, 'login', ?)");
                    $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
                } catch(Exception $e) { /* Table may not exist yet if admin hasn't visited */ }
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username/email or password';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In - Miko</title>
    <meta name="description" content="Sign in to Miko IoT Platform to manage your ESP devices remotely.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <script src="assets/js/theme.js"></script>
</head>
<body class="auth-page">
    <div style="position:fixed;top:20px;right:20px;z-index:100;">
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
            <i class="fas fa-moon"></i>
        </button>
    </div>
    
    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; gap: 15px;">
                <div class="auth-logo">
                    <i class="fas fa-microchip"></i>
                </div>
                <h1>Miko</h1>
            </a>
            <p style="margin-top: 10px;">Sign in to access dashboard</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="login">Username</label>
                <input type="text" id="login" name="login" required 
                       placeholder="Enter username or email"
                       value="<?php echo isset($login) ? htmlspecialchars($login) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;padding:12px;">
                Log In
            </button>
        </form>
        
        <div class="auth-footer">
            Don't have an account? <a href="register.php">Create Account</a>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.password-toggle i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
