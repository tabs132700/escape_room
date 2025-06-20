<?php
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <title>Escape Room - De Geheugenkamer</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .user-status {
      position: fixed;
      top: 20px;
      right: 20px;
      background: rgba(20,20,20,0.9);
      color: #f39c12;
      padding: 10px 20px;
      border-radius: 10px;
      z-index: 100;
      border: 2px solid #f39c12;
    }
    
    .user-status a {
      color: #f39c12;
      text-decoration: none;
      margin: 0 5px;
    }
    
    .user-status a:hover {
      color: #e67e22;
    }
    
    .auth-required {
      background: rgba(231, 76, 60, 0.2);
      border: 2px solid #e74c3c;
      border-radius: 15px;
      padding: 20px;
      margin-top: 20px;
      text-align: center;
    }
    
    .auth-required h3 {
      color: #e74c3c;
      margin-bottom: 15px;
    }
    
    .auth-buttons {
      margin-top: 20px;
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .auth-btn {
      background: linear-gradient(45deg, #3498db, #2980b9);
      color: white;
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
      transition: all 0.3s ease;
      display: inline-block;
    }
    
    .auth-btn:hover {
      background: linear-gradient(45deg, #2980b9, #1f5f8b);
      transform: translateY(-2px);
    }
    
    .auth-btn.signup {
      background: linear-gradient(45deg, #27ae60, #229954);
    }
    
    .auth-btn.signup:hover {
      background: linear-gradient(45deg, #229954, #1e8449);
    }
    
    .auth-btn.admin {
      background: linear-gradient(45deg, #9b59b6, #8e44ad);
    }
    
    .auth-btn.admin:hover {
      background: linear-gradient(45deg, #8e44ad, #6b2c91);
    }
    
    .start-btn.disabled {
      background: #7f8c8d;
      cursor: not-allowed;
      opacity: 0.6;
    }
    
    .start-btn.disabled:hover {
      background: #7f8c8d;
      transform: none;
    }
  </style>
</head>
<body>
  <!-- User Status Display -->
  <?php if ($isLoggedIn): ?>
  <div class="user-status">
    👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
    (<?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Speler'; ?>)
    <?php if ($isAdmin): ?>
      | <a href="admin_dashboard.php">Admin Panel</a>
    <?php endif; ?>
    | <a href="logout.php">Uitloggen</a>
  </div>
  <?php endif; ?>

  <div class="intro-container">
    <h1>🧠 Escape Room: De Geheugenkamer</h1>

    <div class="story">
      <p><strong>Verhaal:</strong></p>
      <p>Je wordt wakker in een donkere kamer. Alles is stil. Op de muur staat:  
      <em>"Je koos dit zelf. Vind de waarheid... of vergeet alles voor altijd."</em></p>

      <p>Je geheugen is verdwenen. Maar met elke puzzel ontdek je meer over jezelf.  
      Wie ben je? Wat heb je gedaan? En waarom koos je ervoor om jezelf op te sluiten?</p>

      <p><strong>Doel:</strong></p>
      <p>Herstel je geheugen en ontsnap uit de kamer voordat je geest voorgoed verdwijnt.</p>
      <p><strong>Tijd:</strong> Je hebt 5 minuten per kamer!</p>

      <p><strong>Gemaakt door:</strong> Tariq Abbas</p>
      
      <?php if ($isLoggedIn): ?>
        <!-- User is logged in - can play -->
        <a href="room1.php" class="start-btn">🚀 Start de Escape Room</a>
        
        <?php if ($isAdmin): ?>
          <div class="auth-buttons">
            <a href="admin_dashboard.php" class="auth-btn admin">🔧 Admin Dashboard</a>
          </div>
        <?php endif; ?>
        
      <?php else: ?>
        <!-- User is NOT logged in - must login first -->
        <div class="auth-required">
          <h3>🔒 Login Vereist</h3>
          <p>Je moet inloggen om de Escape Room te kunnen spelen!</p>
          <p>Dit zorgt ervoor dat je voortgang wordt opgeslagen.</p>
        </div>
        
        <div class="auth-buttons">
          <a href="login.php" class="auth-btn">🔐 Inloggen</a>
          <a href="signup.php" class="auth-btn signup">📝 Account Aanmaken</a>
        </div>
        
        <!-- Disabled start button -->
        <a href="#" class="start-btn disabled" onclick="alert('Je moet eerst inloggen!'); return false;">
          🔒 Start de Escape Room (Login Vereist)
        </a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>