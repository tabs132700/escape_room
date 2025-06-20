<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: room1.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Vul alle velden in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minstens 6 karakters lang zijn.';
    } elseif ($password !== $confirm_password) {
        $error = 'Wachtwoorden komen niet overeen.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Gebruikersnaam mag alleen letters, cijfers en underscores bevatten.';
    } else {
        // Connect to database
        $conn = new mysqli('localhost', 'root', '', 'escape_room');
        if ($conn->connect_error) {
            die("Connectie mislukt: " . $conn->connect_error);
        }
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Gebruikersnaam of e-mailadres is al in gebruik.';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $insertStmt->bind_param("sss", $username, $email, $hashedPassword);
            
            if ($insertStmt->execute()) {
                $success = 'Account succesvol aangemaakt! Je kunt nu inloggen.';
                
                // Clear form data after successful registration
                $_POST = array();
            } else {
                $error = 'Er is een fout opgetreden bij het aanmaken van je account.';
            }
            $insertStmt->close();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren - Escape Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f1419);
            padding: 20px;
        }
        
        .auth-form {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            border: 2px solid #f39c12;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .auth-form h1 {
            color: #f39c12;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 0 0 20px rgba(243, 156, 18, 0.5);
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
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 15px rgba(243, 156, 18, 0.4);
            background: rgba(255,255,255,0.15);
        }
        
        .error-message {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.95), rgba(192, 57, 43, 0.95));
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #c0392b;
        }
        
        .success-message {
            background: linear-gradient(45deg, rgba(46, 204, 113, 0.95), rgba(39, 174, 96, 0.95));
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #27ae60;
        }
        
        .auth-links {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(243, 156, 18, 0.3);
        }
        
        .auth-links a {
            color: #f39c12;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .auth-links a:hover {
            color: #e67e22;
            text-decoration: underline;
        }
        
        .password-requirements {
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #e0e0e0;
            font-size: 14px;
            text-align: left;
        }
        
        .password-requirements ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .form-group.inline {
            display: flex;
            gap: 15px;
        }
        
        .form-group.inline .form-group {
            flex: 1;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <form class="auth-form" method="POST">
            <h1>📝 Registreren</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="password-requirements">
                <strong>Account vereisten:</strong>
                <ul>
                    <li>Gebruikersnaam: alleen letters, cijfers en _</li>
                    <li>Wachtwoord: minimaal 6 karakters</li>
                    <li>Geldig e-mailadres verplicht</li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="username">Gebruikersnaam:</label>
                <input type="text" id="username" name="username" required pattern="[a-zA-Z0-9_]+"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       placeholder="bijv. player123">
            </div>
            
            <div class="form-group">
                <label for="email">E-mailadres:</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="jouw@email.com">
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord:</label>
                <input type="password" id="password" name="password" required minlength="6"
                       placeholder="Minimaal 6 karakters">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Bevestig Wachtwoord:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                       placeholder="Herhaal je wachtwoord">
            </div>
            
            <button type="submit">🎮 Account Aanmaken</button>
            
            <div class="auth-links">
                <a href="login.php">Al een account? Login hier</a><br>
                <a href="index.php">← Terug naar start</a>
            </div>
        </form>
    </div>
    
    <script>
        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#f39c12';
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;
            
            if (!regex.test(username) && username.length > 0) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#f39c12';
            }
        });
    </script>
</body>
</html>