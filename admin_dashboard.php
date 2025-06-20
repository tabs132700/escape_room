<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'escape_room');
if ($conn->connect_error) {
    die("Connectie mislukt: " . $conn->connect_error);
}

// Handle admin actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'reset_user_progress':
            $user_id = (int)$_POST['user_id'];
            // Clear user sessions
            $stmt = $conn->prepare("UPDATE game_sessions SET final_status = 'reset_by_admin' WHERE user_id = ? AND final_status IS NULL");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $message = "Gebruiker voortgang gereset!";
            break;
            
        case 'clear_leaderboard':
            $conn->query("DELETE FROM game_stats WHERE completed = 1");
            $message = "Leaderboard gewist!";
            break;
            
        case 'toggle_user_status':
            $user_id = (int)$_POST['user_id'];
            $current_status = (int)$_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = $new_status ? "Gebruiker geactiveerd!" : "Gebruiker gedeactiveerd!";
            break;
            
        case 'delete_game_session':
            $session_id = (int)$_POST['session_id'];
            $stmt = $conn->prepare("DELETE FROM game_sessions WHERE id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $stmt->close();
            
            $message = "Game sessie verwijderd!";
            break;
            
        case 'toggle_question_visibility':
            $question_id = (int)$_POST['question_id'];
            $current_status = (int)$_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            
            $stmt = $conn->prepare("UPDATE questions SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $question_id);
            $stmt->execute();
            $stmt->close();
            
            $message = $new_status ? "Puzzel geactiveerd!" : "Puzzel gedeactiveerd!";
            break;
            
        case 'show_all_puzzles':
            $conn->query("UPDATE questions SET is_active = 1");
            $message = "Alle puzzels zijn nu zichtbaar voor spelers!";
            break;
            
        case 'hide_all_puzzles':
            $conn->query("UPDATE questions SET is_active = 0");
            $message = "Alle puzzels zijn nu verborgen voor spelers!";
            break;
    }
}

// Get statistics
$stats = [];

// User statistics
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as active FROM users WHERE role = 'user' AND is_active = 1");
$stats['active_users'] = $result->fetch_assoc()['active'];

// Game statistics
$result = $conn->query("SELECT COUNT(*) as total FROM game_sessions");
$stats['total_sessions'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as completed FROM game_sessions WHERE final_status = 'completed'");
$stats['completed_games'] = $result->fetch_assoc()['completed'];

$result = $conn->query("SELECT COUNT(*) as failed FROM game_sessions WHERE final_status = 'failed'");
$stats['failed_games'] = $result->fetch_assoc()['failed'];

$result = $conn->query("SELECT COUNT(*) as today FROM game_sessions WHERE DATE(session_start) = CURDATE()");
$stats['sessions_today'] = $result->fetch_assoc()['today'];

// Average statistics
$result = $conn->query("SELECT AVG(total_time_spent) as avg_time FROM game_sessions WHERE final_status = 'completed'");
$avg_time = $result->fetch_assoc()['avg_time'];
$stats['avg_completion_time'] = $avg_time ? round($avg_time / 60, 1) : 0;

$result = $conn->query("SELECT AVG(score) as avg_score FROM game_stats WHERE completed = 1");
$avg_score = $result->fetch_assoc()['avg_score'];
$stats['avg_score'] = $avg_score ? round($avg_score) : 0;

// Get users with simple info
$users_query = "
    SELECT 
        u.*,
        COUNT(gs.id) as total_sessions,
        COUNT(CASE WHEN gs.final_status = 'completed' THEN 1 END) as completed_sessions
    FROM users u
    LEFT JOIN game_sessions gs ON u.id = gs.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$users = $conn->query($users_query)->fetch_all(MYSQLI_ASSOC);

// Get recent game sessions
$sessions_query = "
    SELECT 
        gs.*,
        u.username
    FROM game_sessions gs
    LEFT JOIN users u ON gs.user_id = u.id
    ORDER BY gs.session_start DESC
    LIMIT 20
";
$recent_sessions = $conn->query($sessions_query)->fetch_all(MYSQLI_ASSOC);

// Get questions
$questions = $conn->query("SELECT * FROM questions ORDER BY room, object")->fetch_all(MYSQLI_ASSOC);

// Get leaderboard
$leaderboard = $conn->query("SELECT u.username, gs.* FROM game_stats gs LEFT JOIN users u ON gs.user_id = u.id WHERE gs.completed = 1 ORDER BY gs.score DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$conn->close();

function formatTime($seconds) {
    if (!$seconds) return "N/A";
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return "{$minutes}m {$seconds}s";
}

function formatDateTime($datetime) {
    if (!$datetime) return "Nooit";
    return date('d-m-Y H:i', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Escape Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f1419);
            min-height: 100vh;
            padding: 20px;
            color: #e0e0e0;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #f39c12;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .admin-header h1 {
            color: #f39c12;
            margin: 0;
            font-size: 2.5em;
            text-shadow: 0 0 20px rgba(243, 156, 18, 0.5);
        }
        
        .admin-nav {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .admin-nav a, .admin-nav button {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .admin-nav a:hover, .admin-nav button:hover {
            background: linear-gradient(45deg, #e67e22, #d68910);
            transform: translateY(-2px);
        }
        
        .message {
            background: linear-gradient(45deg, rgba(46, 204, 113, 0.9), rgba(39, 174, 96, 0.9));
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #27ae60;
            text-align: center;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #f39c12;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #f39c12;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #e0e0e0;
            font-size: 1em;
        }
        
        .admin-section {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #f39c12;
        }
        
        .section-title {
            color: #f39c12;
            margin-bottom: 20px;
            font-size: 1.8em;
            text-shadow: 0 0 10px rgba(243, 156, 18, 0.3);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .data-table th {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
            padding: 15px;
            font-weight: bold;
            text-align: left;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(243, 156, 18, 0.2);
        }
        
        .data-table tr:hover {
            background: rgba(243, 156, 18, 0.1);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active { background: #27ae60; color: white; }
        .status-inactive { background: #e74c3c; color: white; }
        .status-completed { background: #27ae60; color: white; }
        .status-failed { background: #e74c3c; color: white; }
        .status-abandoned { background: #f39c12; color: #121212; }
        .status-playing { background: #3498db; color: white; }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8em;
            margin: 2px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-delete {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-toggle {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
        }
        
        .btn-reset {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        
        .danger-zone {
            background: rgba(231, 76, 60, 0.1);
            border: 2px solid #e74c3c;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .danger-zone h3 {
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-button {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
            border: 2px solid #f39c12;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .tab-button.active {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1>🔧 Admin Dashboard</h1>
                <p>Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?>! Eenvoudige controle over het spel.</p>
            </div>
            <div class="admin-nav">
                <a href="room1.php">🎮 Speel Game</a>
                <a href="leaderboard.php">🏆 Leaderboard</a>
                <button onclick="location.reload()">🔄 Ververs</button>
                <a href="logout.php">🚪 Uitloggen</a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Totaal Gebruikers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Actieve Gebruikers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['sessions_today']; ?></div>
                <div class="stat-label">Sessies Vandaag</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_games']; ?></div>
                <div class="stat-label">Voltooide Games</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['failed_games']; ?></div>
                <div class="stat-label">Mislukte Games</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['avg_completion_time']; ?>m</div>
                <div class="stat-label">Gem. Voltooitijd</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['avg_score']; ?></div>
                <div class="stat-label">Gem. Score</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-section">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('users')">👥 Gebruikers</button>
                <button class="tab-button" onclick="showTab('sessions')">🎮 Game Sessies</button>
                <button class="tab-button" onclick="showTab('leaderboard')">🏆 Leaderboard</button>
                <button class="tab-button" onclick="showTab('questions')">❓ Puzzels</button>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content active">
                <h2>👥 Gebruikers Beheer</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Gebruiker</th>
                            <th>E-mail</th>
                            <th>Aangemeld</th>
                            <th>Laatste Login</th>
                            <th>Sessies</th>
                            <th>Voltooid</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo formatDateTime($user['created_at']); ?></td>
                            <td><?php echo formatDateTime($user['last_login']); ?></td>
                            <td><?php echo $user['total_sessions']; ?></td>
                            <td><?php echo $user['completed_sessions']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_user_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                    <button type="submit" class="btn-small btn-toggle">
                                        <?php echo $user['is_active'] ? '🔒' : '🔓'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reset voortgang?');">
                                    <input type="hidden" name="action" value="reset_user_progress">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-small btn-reset">🔄</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sessions Tab -->
            <div id="sessions" class="tab-content">
                <h2>🎮 Recente Game Sessies</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Speler</th>
                            <th>Gestart</th>
                            <th>Geëindigd</th>
                            <th>Duur</th>
                            <th>Status</th>
                            <th>Puzzels</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sessions as $session): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['username'] ?? 'Anoniem'); ?></td>
                            <td><?php echo formatDateTime($session['session_start']); ?></td>
                            <td><?php echo formatDateTime($session['session_end']); ?></td>
                            <td><?php echo formatTime($session['total_time_spent']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $session['final_status'] ?: 'playing'; ?>">
                                    <?php 
                                    $status_names = [
                                        'completed' => 'Voltooid',
                                        'failed' => 'Mislukt',
                                        'abandoned' => 'Verlaten',
                                        'reset_by_admin' => 'Reset',
                                        null => 'Spelend'
                                    ];
                                    echo $status_names[$session['final_status']] ?? 'Spelend';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $session['puzzles_solved']; ?>/7</td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Verwijder sessie?');">
                                    <input type="hidden" name="action" value="delete_game_session">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <button type="submit" class="btn-small btn-delete">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Leaderboard Tab -->
            <div id="leaderboard" class="tab-content">
                <h2>🏆 Huidige Leaderboard</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Positie</th>
                            <th>Speler</th>
                            <th>Score</th>
                            <th>Tijd</th>
                            <th>Hints</th>
                            <th>Fouten</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $entry): ?>
                        <tr>
                            <td><strong>#<?php echo $index + 1; ?></strong></td>
                            <td><?php echo htmlspecialchars($entry['username'] ?? $entry['player_name'] ?? 'Anoniem'); ?></td>
                            <td><strong><?php echo $entry['score']; ?></strong></td>
                            <td><?php echo formatTime($entry['completion_time']); ?></td>
                            <td><?php echo $entry['hints_used']; ?></td>
                            <td><?php echo $entry['wrong_attempts']; ?></td>
                            <td><?php echo formatDateTime($entry['timestamp']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="danger-zone">
                    <h3>⚠️ Leaderboard Beheer</h3>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je het hele leaderboard wilt wissen?');">
                        <input type="hidden" name="action" value="clear_leaderboard">
                        <button type="submit" class="btn-small btn-delete">🗑️ Wis Leaderboard</button>
                    </form>
                </div>
            </div>

            <!-- Questions Tab -->
            <div id="questions" class="tab-content">
                <h2>❓ Puzzel Beheer</h2>
                <p style="color: #3498db; margin-bottom: 20px;">
                    💡 <strong>Tip:</strong> Deactiveer puzzels om ze onzichtbaar te maken voor spelers. Admins zien altijd alle puzzels.
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kamer</th>
                            <th>Object</th>
                            <th>Vraag</th>
                            <th>Antwoord</th>
                            <th>Moeilijkheid</th>
                            <th>Geprobeerd</th>
                            <th>Opgelost</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td>Kamer <?php echo $question['room']; ?></td>
                            <td><strong><?php echo htmlspecialchars($question['object']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($question['question'], 0, 40)) . '...'; ?></td>
                            <td><code><?php echo htmlspecialchars($question['answer']); ?></code></td>
                            <td>
                                <span class="status-badge status-<?php echo $question['difficulty']; ?>">
                                    <?php echo ucfirst($question['difficulty']); ?>
                                </span>
                            </td>
                            <td><?php echo $question['times_attempted'] ?? 0; ?>x</td>
                            <td><?php echo $question['times_solved'] ?? 0; ?>x</td>
                            <td>
                                <span class="status-badge <?php echo $question['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $question['is_active'] ? 'Zichtbaar' : 'Verborgen'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_question_visibility">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $question['is_active']; ?>">
                                    <button type="submit" class="btn-small <?php echo $question['is_active'] ? 'btn-delete' : 'btn-toggle'; ?>" 
                                            title="<?php echo $question['is_active'] ? 'Verberg puzzel' : 'Toon puzzel'; ?>">
                                        <?php echo $question['is_active'] ? '👁️‍🗨️ Verberg' : '👁️ Toon'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="puzzle-controls" style="margin-top: 20px; padding: 20px; background: rgba(52, 152, 219, 0.1); border: 1px solid #3498db; border-radius: 10px;">
                    <h3 style="color: #3498db; margin-bottom: 15px;">🎮 Bulk Acties</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="show_all_puzzles">
                            <button type="submit" class="btn-small btn-toggle">👁️ Toon Alle Puzzels</button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Alle puzzels verbergen voor spelers?');">
                            <input type="hidden" name="action" value="hide_all_puzzles">
                            <button type="submit" class="btn-small btn-delete">👁️‍🗨️ Verberg Alle Puzzels</button>
                        </form>
                    </div>
                    <p style="font-size: 0.9em; color: #95a5a6; margin-top: 10px;">
                        <strong>Let op:</strong> Verborgen puzzels zijn alleen onzichtbaar voor normale spelers. Admins zien altijd alle puzzels.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>