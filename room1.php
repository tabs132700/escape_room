<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize game session variables
$_SESSION['start_time'] = $_SESSION['start_time'] ?? time();
$_SESSION['hints_used'] = $_SESSION['hints_used'] ?? 0;
$_SESSION['wrong_attempts'] = $_SESSION['wrong_attempts'] ?? 0;

// Create game session in database if not exists
if (!isset($_SESSION['game_session_id'])) {
    $conn = new mysqli('localhost', 'root', '', 'escape-room');
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO game_sessions (user_id, session_start) VALUES (?, NOW())");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $_SESSION['game_session_id'] = $conn->insert_id;
        $stmt->close();
        $conn->close();
    }
}

// Calculate solved puzzles
$solved_puzzles = [];
$total_puzzles = ['clock', 'drawer', 'computer', 'painting', 'safe', 'bookshelf'];
foreach ($total_puzzles as $puzzle) {
    if (isset($_SESSION[$puzzle . '_solved'])) {
        $solved_puzzles[] = $puzzle;
    }
}
$progress_percentage = (count($solved_puzzles) / count($total_puzzles)) * 100;

// Admin and puzzle visibility settings
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$puzzle_visibility = [
    'clock' => true,
    'drawer' => true,
    'computer' => true,
    'painting' => true,
    'safe' => true,
    'bookshelf' => true,
    'door' => true
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <title>Escape Room - De Geheugenkamer</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Enhanced styling for hint system */
    .stuck-btn {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      border: none;
      padding: 15px 25px;
      border-radius: 50px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(231, 76, 60, 0.4);
      animation: wiggle 3s infinite;
      z-index: 1000;
      transition: all 0.3s ease;
    }
    
    .stuck-btn:hover {
      background: linear-gradient(135deg, #c0392b, #922b21);
      transform: scale(1.05);
      box-shadow: 0 6px 30px rgba(231, 76, 60, 0.6);
    }
    
    @keyframes wiggle {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(-5deg); }
      75% { transform: rotate(5deg); }
    }
    
    .hint-popup {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      backdrop-filter: blur(5px);
    }
    
    .hint-content {
      background: linear-gradient(135deg, #2c3e50, #34495e);
      padding: 40px;
      border-radius: 20px;
      max-width: 500px;
      text-align: center;
      border: 2px solid #f39c12;
      box-shadow: 0 20px 60px rgba(0,0,0,0.8);
      animation: popIn 0.3s ease;
      color: #e0e0e0;
    }
    
    @keyframes popIn {
      from { transform: scale(0.8); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    
    .hint-content h2 {
      color: #f39c12;
      margin-bottom: 20px;
      font-size: 2em;
      text-shadow: 0 0 10px rgba(243, 156, 18, 0.5);
    }
    
    .hint-content p {
      margin-bottom: 25px;
      font-size: 1.1em;
      line-height: 1.6;
    }
    
    .hint-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin: 20px 0;
    }
    
    .hint-option {
      background: linear-gradient(135deg, #3498db, #2980b9);
      color: white;
      border: none;
      padding: 15px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: bold;
      transition: all 0.3s ease;
      font-size: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .hint-option:hover {
      background: linear-gradient(135deg, #2980b9, #1f5f8b);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }
    
    .hint-display {
      background: rgba(52, 152, 219, 0.1);
      border: 1px solid #3498db;
      padding: 20px;
      border-radius: 10px;
      margin: 20px 0;
      color: #3498db;
      font-style: italic;
      font-size: 18px;
      display: none;
      line-height: 1.6;
    }
    
    .close-hint {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      border: none;
      padding: 12px 25px;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 20px;
      font-weight: bold;
      transition: all 0.3s ease;
    }
    
    .close-hint:hover {
      background: linear-gradient(135deg, #c0392b, #922b21);
      transform: translateY(-2px);
    }
    
    .progress-indicator {
      position: fixed;
      top: 100px;
      left: 30px;
      background: rgba(26, 26, 26, 0.9);
      padding: 15px 20px;
      border-radius: 15px;
      border: 1px solid #f39c12;
      z-index: 100;
      backdrop-filter: blur(10px);
    }
    
    .progress-text {
      color: #f39c12;
      font-weight: bold;
      margin-bottom: 10px;
      font-size: 14px;
    }
    
    .progress-bar-small {
      width: 200px;
      height: 8px;
      background: rgba(255,255,255,0.2);
      border-radius: 4px;
      overflow: hidden;
    }
    
    .progress-fill-small {
      height: 100%;
      background: linear-gradient(90deg, #f39c12, #e67e22);
      width: <?php echo $progress_percentage; ?>%;
      transition: width 0.5s ease;
      border-radius: 4px;
    }
    
    .user-info {
      position: fixed;
      top: 30px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(26, 26, 26, 0.9);
      padding: 10px 20px;
      border-radius: 10px;
      border: 1px solid #f39c12;
      color: #f39c12;
      font-weight: bold;
      z-index: 100;
      backdrop-filter: blur(10px);
    }
    
    .logout-btn {
      position: fixed;
      top: 30px;
      right: 150px;
      background: rgba(231, 76, 60, 0.9);
      color: white;
      padding: 8px 15px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      z-index: 100;
      transition: all 0.3s ease;
    }
    
    .logout-btn:hover {
      background: rgba(192, 57, 43, 1);
      transform: translateY(-2px);
    }
    
    .hotspot {
      opacity: 0 !important;
      transition: opacity 0.3s ease;
    }
    
    .hotspot:hover {
      opacity: 1 !important;
    }
    
    .solved-indicator {
      position: absolute;
      top: -10px;
      right: -10px;
      background: #27ae60;
      color: white;
      width: 25px;
      height: 25px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
      box-shadow: 0 2px 10px rgba(39,174,96,0.5);
    }

  </style>
</head>
<body>
  <!-- User Info -->
  <div class="user-info">
    👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      | <a href="admin_dashboard.php" style="color: #f39c12; text-decoration: none;">⚙️ Admin</a>
    <?php endif; ?>
  </div>
  
  <!-- Logout Button -->
  <button class="logout-btn" onclick="window.location.href='logout.php'">🚪 Uitloggen</button>

  <!-- Timer -->
  <div id="timer">08:00</div>
  
  <!-- Room Info -->
  <div class="room-info">De Geheugenkamer - Kamer 1</div>

  <!-- Progress Indicator -->
  <div class="progress-indicator">
    <div class="progress-text"><?php echo count($solved_puzzles); ?>/<?php echo count($total_puzzles); ?> Puzzels Opgelost</div>
    <div class="progress-bar-small">
      <div class="progress-fill-small"></div>
    </div>
  </div>

  <!-- Feedback Messages -->
  <?php if (isset($_SESSION['feedback'])): ?>
    <div class="feedback-message <?php echo strpos($_SESSION['feedback'], 'Correct') !== false ? 'feedback-success' : 'feedback-error'; ?>">
      <?php echo $_SESSION['feedback']; unset($_SESSION['feedback']); ?>
    </div>
  <?php endif; ?>

  <!-- "Ben je vast?" Button -->
  <button class="stuck-btn" onclick="showHintMenu()">
    🤔 Ben je vast?
  </button>

  <!-- Hint Menu Popup -->
  <div class="hint-popup" id="hintPopup">
    <div class="hint-content">
      <h2>🤔 Ben je vast?</h2>
      <p>Kies waar je hulp bij nodig hebt:</p>
      
      <div class="hint-options">
        <button class="hint-option" onclick="showSpecificHint('clock')">
          🕐 De Klok
        </button>
        <button class="hint-option" onclick="showSpecificHint('computer')">
          💻 De Computer
        </button>
        <button class="hint-option" onclick="showSpecificHint('drawer')">
          🗄️ De Lade
        </button>
        <button class="hint-option" onclick="showSpecificHint('painting')">
          🖼️ Het Schilderij
        </button>
        <button class="hint-option" onclick="showSpecificHint('safe')">
          🔐 De Kluis
        </button>
        <button class="hint-option" onclick="showSpecificHint('bookshelf')">
          📚 De Boekenkast
        </button>
      </div>
      
      <div class="hint-display" id="hintDisplay"></div>
      
      <button class="close-hint" onclick="closeHintMenu()">❌ Sluiten</button>
    </div>
  </div>

  <!-- Room Container -->
  <div class="room-container">
    <!-- Original puzzles - check visibility for regular users -->
    <?php if ($isAdmin || $puzzle_visibility['clock']): ?>
    <button class="hotspot" id="clock-hotspot" style="top: 18%; left: 31%;" data-popup="clock-puzzle">
      🕐
      <?php if (isset($_SESSION['clock_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>
    
    <?php if ($isAdmin || $puzzle_visibility['drawer']): ?>
    <button class="hotspot" id="drawer-hotspot" style="bottom: 35%; left: 20%;" data-popup="drawer-puzzle">
      🗄️
      <?php if (isset($_SESSION['drawer_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>
    
    <?php if ($isAdmin || $puzzle_visibility['computer']): ?>
    <button class="hotspot" id="computer-hotspot" style="top: 45%; left: 35%;" data-popup="computer-puzzle">
      💻
      <?php if (isset($_SESSION['computer_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>
    
    <!-- Progressive puzzles - check visibility AND progression for regular users -->
    <?php if ($isAdmin || ($puzzle_visibility['painting'] && isset($_SESSION['clock_solved']))): ?>
    <button class="hotspot" id="painting-hotspot" style="top: 21%; right: 47%;" data-popup="painting-puzzle">
      🖼️
      <?php if (isset($_SESSION['painting_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>
    
    <?php if ($isAdmin || ($puzzle_visibility['safe'] && isset($_SESSION['computer_solved']))): ?>
    <button class="hotspot" id="safe-hotspot" style="bottom: 19%; right: 60%; " data-popup="safe-puzzle">
      🔐
      <?php if (isset($_SESSION['safe_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>
    
    <?php if ($isAdmin || ($puzzle_visibility['bookshelf'] && isset($_SESSION['drawer_solved']))): ?>
    <button class="hotspot" id="bookshelf-hotspot" style="top: 30%; left: 70%;" data-popup="bookshelf-puzzle">
      📚
      <?php if (isset($_SESSION['bookshelf_solved'])): ?>
        <span class="solved-indicator">✓</span>
      <?php endif; ?>
    </button>
    <?php endif; ?>

    <!-- Final door - check visibility and progression -->
    <?php
    $all_puzzles = ['clock', 'drawer', 'computer', 'painting', 'safe', 'bookshelf'];
    $all_solved = true;
    foreach ($all_puzzles as $puzzle) {
        if (!isset($_SESSION[$puzzle . '_solved'])) {
            $all_solved = false;
            break;
        }
    }
    ?>
    <?php if ($isAdmin || ($puzzle_visibility['door'] && $all_solved)): ?>
    <button class="hotspot" id="door-hotspot" style="top: 50%; left: 10%;" data-popup="door-puzzle">
      🚪 UITGANG
    </button>
    <?php endif; ?>
  </div>

  <!-- Clock Puzzle -->
  <div id="clock-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('clock-puzzle')">&times;</span>
      <h3>🕐 De Geheugeklok</h3>
      <p>De klok staat stil op 12:34. Eronder staat geschreven:</p>
      <p><em>"Wanneer de tijd stilstaat, tel dan de uren en minuten samen..."</em></p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="clock">
        <input type="number" name="antwoord" placeholder="Voer je antwoord in..." required>
        <br><br>
        <button type="submit">✅ Controleer</button>
      </form>
    </div>
  </div>

  <!-- Drawer Puzzle -->
  <div id="drawer-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('drawer-puzzle')">&times;</span>
      <h3>🗄️ De Mysterieuze Lade</h3>
      <p>Er zit een cijferslot op. Je vindt een verkreukeld briefje:</p>
      <p><em>"Het jaar waarin alles begon... 2000 + 25"</em></p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="drawer">
        <input type="number" name="antwoord" placeholder="Voer het jaar in..." required>
        <br><br>
        <button type="submit">🔓 Open Lade</button>
      </form>
    </div>
  </div>

  <!-- Computer Puzzle -->
  <div id="computer-puzzle" class="popup">
    <div class="popup-content win7-login">
      <span class="close-btn" onclick="closePopup('computer-puzzle')">&times;</span>
      <div class="win7-avatar"></div>
      <h3 class="win7-title">Gebruiker</h3>
      <p class="win7-user">Hint: Wat wil je doen uit deze kamer?</p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="computer">
        <input type="password" name="antwoord" class="win7-input" placeholder="Voer wachtwoord in..." required>
        <br>
        <button type="submit" class="win7-btn">🔐 Aanmelden</button>
      </form>
    </div>
  </div>

  <!-- Painting Puzzle -->
  <div id="painting-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('painting-puzzle')">&times;</span>
      <h3>🖼️ Het Mysterieuze Schilderij</h3>
      <p>Achter het schilderij vind je een raadsel:</p>
      <p><em>"Rood, oranje, geel, groen, blauw, indigo en violet... Hoeveel kleuren zie je in de regenboog?"</em></p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="painting">
        <input type="number" name="antwoord" placeholder="Aantal kleuren..." required>
        <br><br>
        <button type="submit">🌈 Controleer</button>
      </form>
    </div>
  </div>

  <!-- Safe Puzzle -->
  <div id="safe-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('safe-puzzle')">&times;</span>
      <h3>🔐 De Geheime Kluis</h3>
      <p>Een digitale kluis met een toetsenbord. Op een briefje staat:</p>
      <p><em>"De stad van grachten, fietsen en tulpen... waar Anne Frank zich verschool..."</em></p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="safe">
        <input type="text" name="antwoord" placeholder="Stad naam..." required>
        <br><br>
        <button type="submit">🏛️ Open Kluis</button>
      </form>
    </div>
  </div>

  <!-- Bookshelf Puzzle -->
  <div id="bookshelf-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('bookshelf-puzzle')">&times;</span>
      <h3>📚 De Boekenkast</h3>
      <p>Tussen de boeken zie je zes letters uitgelicht:</p>
      <p style="font-size: 24px; letter-spacing: 10px;"><strong>E S C A P E</strong></p>
      <p><em>"Tel de letters om het geheim te onthullen..."</em></p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="bookshelf">
        <input type="number" name="antwoord" placeholder="Aantal letters..." required>
        <br><br>
        <button type="submit">📖 Controleer</button>
      </form>
    </div>
  </div>

  <!-- Final Door -->
  <div id="door-puzzle" class="popup">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup('door-puzzle')">&times;</span>
      <h3>🚪 De Uitgang</h3>
      <p>Je hebt alle puzzels opgelost! Je geheugen komt langzaam terug...</p>
      <p><strong>Je herinnert je nu alles. Dit was een test die je voor jezelf had opgezet.</strong></p>
      <p>De deur kan nu geopend worden. Type 'OPEN' om te ontsnappen.</p>
      <form action="check_answer.php" method="POST">
        <input type="hidden" name="object" value="door">
        <input type="text" name="antwoord" placeholder="Type het magische woord..." required>
        <br><br>
        <button type="submit">🎉 Ontsnap!</button>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    // Hint system functionality
    const hints = {
      clock: "De klok toont 12:34. Je moet de uren (12) en minuten (34) bij elkaar optellen: 12 + 34 = ?",
      computer: "Denk aan wat je doel is in deze escape room. Wat wil je doen? Je wilt hier... wegkomen! 💭",
      drawer: "Het briefje zegt '2000 + 25'. Dit is een simpele som die het jaar aangeeft waarin iets begon.",
      painting: "Tel alle kleuren die in de tekst worden genoemd: rood, oranje, geel, groen, blauw, indigo, violet.",
      safe: "De hint wijst naar Nederland. Welke stad is de hoofdstad van Nederland? 🇳🇱",
      bookshelf: "Kijk naar de letters 'E S C A P E' en tel ze: E-S-C-A-P-E = hoeveel letters?"
    };

    function showHintMenu() {
      document.getElementById('hintPopup').style.display = 'flex';
      document.getElementById('hintDisplay').style.display = 'none';
    }

    function closeHintMenu() {
      document.getElementById('hintPopup').style.display = 'none';
    }

    function showSpecificHint(puzzle) {
      const hintDisplay = document.getElementById('hintDisplay');
      const hintText = hints[puzzle];
      
      if (hintText) {
        hintDisplay.innerHTML = `<strong>💡 Hint voor ${getPuzzleName(puzzle)}:</strong><br><br>${hintText}`;
        hintDisplay.style.display = 'block';
        
        // Track hint usage
        fetch('track_hint.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'puzzle=' + puzzle
        });
        
        // Show penalty warning
        setTimeout(() => {
          const warning = document.createElement('div');
          warning.className = 'hint-warning';
          warning.textContent = '⚠️ -30 seconden voor hint!';
          warning.style.position = 'fixed';
          warning.style.top = '150px';
          warning.style.right = '30px';
          warning.style.background = '#e74c3c';
          warning.style.color = 'white';
          warning.style.padding = '10px 20px';
          warning.style.borderRadius = '10px';
          warning.style.zIndex = '1500';
          warning.style.fontWeight = 'bold';
          document.body.appendChild(warning);
          
          // Deduct time from timer
          if (window.duration) {
            window.duration -= 30;
          }
          
          setTimeout(() => warning.remove(), 3000);
        }, 1000);
      } else {
        hintDisplay.innerHTML = '<strong>🤷‍♂️ Geen hint beschikbaar voor deze puzzel.</strong>';
        hintDisplay.style.display = 'block';
      }
    }

    function getPuzzleName(puzzle) {
      const names = {
        clock: 'De Klok',
        computer: 'De Computer', 
        drawer: 'De Lade',
        painting: 'Het Schilderij',
        safe: 'De Kluis',
        bookshelf: 'De Boekenkast'
      };
      return names[puzzle] || puzzle;
    }

    // Setup popup functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Setup hotspot clicks
      document.querySelectorAll('.hotspot').forEach(function(button) {
        button.addEventListener('click', function() {
          const popupId = button.getAttribute('data-popup');
          openPopup(popupId);
        });
      });

      // Close hint menu when clicking outside
      document.getElementById('hintPopup').addEventListener('click', function(e) {
        if (e.target === this) {
          closeHintMenu();
        }
      });
    });

    // Function to open a popup
    function openPopup(id) {
      document.getElementById(id).style.display = 'block';
    }

    // Function to close a popup
    function closePopup(id) {
      document.getElementById(id).style.display = 'none';
    }

    // Close popup when clicking outside
    document.querySelectorAll('.popup').forEach(popup => {
      popup.addEventListener('click', (e) => {
        if (e.target === popup) {
          popup.style.display = 'none';
        }
      });
    });

    // Escape key to close popups
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.popup').forEach(popup => {
          popup.style.display = 'none';
        });
        closeHintMenu();
      }
    });
  </script>
</body>
</html>