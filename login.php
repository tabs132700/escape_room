<?php
// login.php - Simple Login Page
session_start();

// If already logged in, redirect to game
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: room1.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Vul beide velden in.';
    } else {
        // Connect to database
        $conn = new mysqli('localhost', 'root', '', 'escape_room');
        if ($conn->connect_error) {
            die("Connectie mislukt: " . $conn->connect_error);
        }
        
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: room1.php');
                }
                exit;
            } else {
                $error = 'Onjuist wachtwoord.';
            }
        } else {
            $error = 'Gebruiker niet gevonden.';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Login - Escape Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f1419);
        }
        
        .login-form {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            border: 2px solid #f39c12;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-form h1 {
            color: #f39c12;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #e0e0e0;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #f39c12;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: #e0e0e0;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 15px rgba(243, 156, 18, 0.4);
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .login-btn {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            background: linear-gradient(45deg, #e67e22, #d68910);
        }
        
        .demo-info {
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #e0e0e0;
        }
        
        .back-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(243, 156, 18, 0.3);
        }
        
        .back-link a {
            color: #f39c12;
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: #e67e22;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST">
            <h1>🔐 Login</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="demo-info">
                <strong>Test Accounts:</strong><br>
                Admin: <strong>admin</strong> / <strong>admin123</strong><br>
                User: <strong>demo_player</strong> / <strong>demo123</strong>
            </div>
            
            <div class="form-group">
                <label for="username">Gebruikersnaam:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">🚀 Inloggen</button>
            
            <div class="back-link">
                <a href="index.php">← Terug naar start</a>
            </div>
        </form>
    </div>
</body>
</html>