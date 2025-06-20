<?php
session_start();

// Calculate stats
$completion_time = $_SESSION['completion_time'] ?? 0;
$minutes = floor($completion_time / 60);
$seconds = $completion_time % 60;
$hints_used = $_SESSION['hints_used'] ?? 0;
$wrong_attempts = $_SESSION['wrong_attempts'] ?? 0;

// Calculate score
$base_score = 1000;
$time_penalty = $minutes * 20 + $seconds;
$hint_penalty = $hints_used * 50;
$wrong_penalty = $wrong_attempts * 10;
$final_score = max(0, $base_score - $time_penalty - $hint_penalty - $wrong_penalty);

// Determine rating
if ($final_score >= 800) {
    $rating = "⭐⭐⭐⭐⭐ Meesterlijk!";
    $rating_color = "#FFD700";
} elseif ($final_score >= 600) {
    $rating = "⭐⭐⭐⭐ Uitstekend!";
    $rating_color = "#C0C0C0";
} elseif ($final_score >= 400) {
    $rating = "⭐⭐⭐ Goed gedaan!";
    $rating_color = "#CD7F32";
} else {
    $rating = "⭐⭐ Voltooid!";
    $rating_color = "#f39c12";
}

// Optional: Save stats to database
try {
    $conn = new mysqli('localhost', 'root', '', 'escape_room');
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO game_stats (completion_time, hints_used, wrong_attempts, completed) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iii", $completion_time, $hints_used, $wrong_attempts);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
} catch (Exception $e) {
    // Silent fail - don't show errors on victory screen
}

// Clear session
session_destroy();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <title>Gewonnen! - Escape Room</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .stats-container {
      background: rgba(20,20,20,0.9);
      padding: 30px;
      border-radius: 15px;
      margin: 20px 0;
      border: 2px solid #f39c12;
    }
    
    .stat-row {
      display: flex;
      justify-content: space-between;
      margin: 15px 0;
      font-size: 18px;
    }
    
    .stat-label {
      color: #f39c12;
    }
    
    .stat-value {
      color: #e0e0e0;
      font-weight: bold;
    }
    
    .score-display {
      text-align: center;
      margin: 30px 0;
    }
    
    .score-number {
      font-size: 48px;
      color: #f39c12;
      text-shadow: 0 0 20px rgba(243,156,18,0.5);
      margin: 10px 0;
    }
    
    .rating {
      font-size: 24px;
      margin: 20px 0;
    }
    
    .celebration {
      animation: bounce 2s infinite;
    }
    
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-20px); }
    }
    
    .confetti-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      overflow: hidden;
    }
    
    .confetti-piece {
      position: absolute;
      width: 15px;
      height: 15px;
      background: #f39c12;
      animation: confettiAnimation 3s ease-out;
    }
    
    @keyframes confettiAnimation {
      0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
      }
      100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
      }
    }
  </style>
</head>
<body>
  <div class="confetti-container" id="confetti"></div>
  
  <div class="result-container">
    <div class="result-content">
      <h1 class="celebration">🎉 Gefeliciteerd! 🎉</h1>
      <h2>Je hebt de Geheugenkamer ontsnapt!</h2>
      
      <p>Je geheugen is volledig hersteld. Je herinnert je nu alles - dit was een test die je jezelf had gesteld om je geest scherp te houden.</p>
      
      <div class="stats-container">
        <h3>📊 Jouw Prestaties:</h3>
        
        <div class="stat-row">
          <span class="stat-label">⏱️ Tijd:</span>
          <span class="stat-value"><?php echo $minutes; ?> min <?php echo $seconds; ?> sec</span>
        </div>
        
        <div class="stat-row">
          <span class="stat-label">💡 Hints gebruikt:</span>
          <span class="stat-value"><?php echo $hints_used; ?></span>
        </div>
        
        <div class="stat-row">
          <span class="stat-label">❌ Foute pogingen:</span>
          <span class="stat-value"><?php echo $wrong_attempts; ?></span>
        </div>
      </div>
      
      <div class="score-display">
        <h3>Totale Score:</h3>
        <div class="score-number"><?php echo $final_score; ?></div>
        <div class="rating" style="color: <?php echo $rating_color; ?>"><?php echo $rating; ?></div>
      </div>
      
      <div class="actions">
        <a href="index.php" class="btn">🏠 Terug naar start</a>
        <a href="room1.php" class="btn">🔄 Speel opnieuw</a>
        <button onclick="shareScore()" class="btn">📱 Deel je score</button>
      </div>
    </div>
  </div>
  
  <script>
    // Create confetti effect
    function createConfetti() {
      const container = document.getElementById('confetti');
      const colors = ['#f39c12', '#e67e22', '#d68910', '#3498db', '#2ecc71', '#e74c3c'];
      
      for (let i = 0; i < 100; i++) {
        setTimeout(() => {
          const confetti = document.createElement('div');
          confetti.className = 'confetti-piece';
          confetti.style.left = Math.random() * 100 + '%';
          confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
          confetti.style.animationDelay = Math.random() * 2 + 's';
          confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
          container.appendChild(confetti);
          
          setTimeout(() => confetti.remove(), 3000);
        }, i * 30);
      }
    }
    
    createConfetti();
    
    // Share functionality
    function shareScore() {
      const text = `Ik heb de Escape Room gehaald in <?php echo $minutes; ?>:<?php echo str_pad($seconds, 2, '0', STR_PAD_LEFT); ?> met een score van <?php echo $final_score; ?> punten! 🎉`;
      
      if (navigator.share) {
        navigator.share({
          title: 'Escape Room Score',
          text: text,
          url: window.location.href
        });
      } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(text);
        alert('Score gekopieerd naar klembord!');
      }
    }
  </script>
</body>
</html>