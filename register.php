<?php
/**
 * Miko - IoT Cloud Control Platform
 * User Registration
 */

require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be 3-50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $apiKey = generateToken(16);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, api_key) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $passwordHash, $apiKey]);
                
                $userId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Account - Miko</title>
    <meta name="description" content="Create an account on Miko to control your ESP devices from anywhere.">
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
            <p style="margin-top: 10px;">Create your free account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Choose a username"
                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required 
                       placeholder="your@email.com"
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required 
                           placeholder="Minimum 6 characters">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Re-enter password">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;padding:12px;">
                Create Account
            </button>
        </form>
        
        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign In</a>
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
