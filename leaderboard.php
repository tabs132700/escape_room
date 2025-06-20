<?php
session_start();

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'escape_room');
if ($conn->connect_error) {
    die("Connectie mislukt: " . $conn->connect_error);
}

// Get leaderboard data
$leaderboard_query = "
    SELECT 
        u.username,
        gs.score,
        gs.completion_time,
        gs.hints_used,
        gs.wrong_attempts,
        gs.timestamp,
        ROW_NUMBER() OVER (ORDER BY gs.score DESC, gs.completion_time ASC) as position
    FROM game_stats gs
    LEFT JOIN users u ON gs.user_id = u.id
    WHERE gs.completed = 1 AND gs.score > 0
    ORDER BY gs.score DESC, gs.completion_time ASC
    LIMIT 20
";

$leaderboard = $conn->query($leaderboard_query)->fetch_all(MYSQLI_ASSOC);

// Get recent completions
$recent_query = "
    SELECT 
        u.username,
        gs.score,
        gs.completion_time,
        gs.timestamp
    FROM game_stats gs
    LEFT JOIN users u ON gs.user_id = u.id
    WHERE gs.completed = 1
    ORDER BY gs.timestamp DESC
    LIMIT 10
";

$recent_completions = $conn->query($recent_query)->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_attempts,
        COUNT(CASE WHEN completed = 1 THEN 1 END) as total_completions,
        AVG(CASE WHEN completed = 1 THEN completion_time END) as avg_time,
        AVG(CASE WHEN completed = 1 THEN score END) as avg_score,
        MAX(score) as highest_score,
        MIN(CASE WHEN completed = 1 AND completion_time > 0 THEN completion_time END) as fastest_time
    FROM game_stats
";

$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();

function formatTime($seconds) {
    if (!$seconds) return "N/A";
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return "{$minutes}m {$seconds}s";
}

function formatDateTime($datetime) {
    return date('d-m-Y H:i', strtotime($datetime));
}

function getMedal($position) {
    switch($position) {
        case 1: return "🥇";
        case 2: return "🥈";
        case 3: return "🥉";
        default: return "#{$position}";
    }
}

function getScoreColor($score) {
    if ($score >= 800) return "#FFD700"; // Gold
    if ($score >= 600) return "#C0C0C0"; // Silver
    if ($score >= 400) return "#CD7F32"; // Bronze
    return "#f39c12"; // Default
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Escape Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .leaderboard-container {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f1419);
            min-height: 100vh;
            padding: 20px;
            color: #e0e0e0;
        }
        
        .leaderboard-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #2c2c54, #40407a);
            border-radius: 20px;
            border: 2px solid #f39c12;
        }
        
        .leaderboard-header h1 {
            color: #f39c12;
            font-size: 3em;
            margin-bottom: 20px;
            text-shadow: 0 0 20px rgba(243, 156, 18, 0.5);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 20px;
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
        
        .leaderboard-section {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            border: 2px solid #f39c12;
        }
        
        .section-title {
            color: #f39c12;
            font-size: 2em;
            margin-bottom: 25px;
            text-align: center;
            text-shadow: 0 0 10px rgba(243, 156, 18, 0.3);
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .leaderboard-table th {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
            padding: 18px;
            font-weight: bold;
            text-align: left;
            font-size: 1.1em;
        }
        
        .leaderboard-table td {
            padding: 15px 18px;
            border-bottom: 1px solid rgba(243, 156, 18, 0.2);
            transition: background 0.3s ease;
        }
        
        .leaderboard-table tr:hover {
            background: rgba(243, 156, 18, 0.1);
        }
        
        .position-cell {
            font-size: 1.5em;
            font-weight: bold;
            text-align: center;
            width: 80px;
        }
        
        .medal {
            font-size: 2em;
        }
        
        .username-cell {
            font-weight: bold;
            color: #3498db;
        }
        
        .score-cell {
            font-weight: bold;
            font-size: 1.2em;
            text-align: center;
        }
        
        .time-cell {
            text-align: center;
            font-family: monospace;
        }
        
        .hints-cell, .attempts-cell {
            text-align: center;
            color: #e74c3c;
        }
        
        .date-cell {
            color: #95a5a6;
            font-size: 0.9em;
        }
        
        .no-data {
            text-align: center;
            color: #95a5a6;
            font-style: italic;
            padding: 40px;
            font-size: 1.2em;
        }
        
        .navigation {
            text-align: center;
            margin: 30px 0;
        }
        
        .nav-btn {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: #121212;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 0 10px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .nav-btn:hover {
            background: linear-gradient(45deg, #e67e22, #d68910);
            transform: translateY(-2px);
        }
        
        .current-user {
            background: rgba(52, 152, 219, 0.2);
            border-left: 4px solid #3498db;
        }
        
        .crown {
            color: #FFD700;
            font-size: 1.5em;
            margin-right: 10px;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(45deg, #27ae60, #229954);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: linear-gradient(45deg, #229954, #1e8449);
            transform: scale(1.1);
        }
        
        .podium {
            display: flex;
            justify-content: center;
            align-items: end;
            margin: 30px 0;
            gap: 20px;
        }
        
        .podium-place {
            background: linear-gradient(135deg, #2c2c54, #40407a);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 2px solid;
            min-width: 150px;
        }
        
        .podium-place.first {
            border-color: #FFD700;
            height: 120px;
        }
        
        .podium-place.second {
            border-color: #C0C0C0;
            height: 100px;
        }
        
        .podium-place.third {
            border-color: #CD7F32;
            height: 80px;
        }
        
        .podium-medal {
            font-size: 3em;
            margin-bottom: 10px;
        }
        
        .podium-name {
            font-weight: bold;
            color: #e0e0e0;
            margin-bottom: 5px;
        }
        
        .podium-score {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .leaderboard-container {
                padding: 10px;
            }
            
            .leaderboard-header h1 {
                font-size: 2em;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .leaderboard-table {
                font-size: 0.9em;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 10px 8px;
            }
            
            .podium {
                flex-direction: column;
                align-items: center;
            }
            
            .podium-place {
                width: 100%;
                max-width: 300px;
                height: auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="leaderboard-container">
        <!-- Header -->
        <div class="leaderboard-header">
            <h1>🏆 Leaderboard</h1>
            <p>De beste spelers van De Geheugenkamer</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_attempts']); ?></div>
                <div class="stat-label">Totaal Pogingen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_completions']); ?></div>
                <div class="stat-label">Voltooide Games</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['avg_time'] ? formatTime($stats['avg_time']) : 'N/A'; ?></div>
                <div class="stat-label">Gemiddelde Tijd</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['avg_score'] ? round($stats['avg_score']) : 'N/A'; ?></div>
                <div class="stat-label">Gemiddelde Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['highest_score'] ?? 'N/A'; ?></div>
                <div class="stat-label">Hoogste Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['fastest_time'] ? formatTime($stats['fastest_time']) : 'N/A'; ?></div>
                <div class="stat-label">Snelste Tijd</div>
            </div>
        </div>

        <!-- Podium for Top 3 -->
        <?php if (count($leaderboard) >= 3): ?>
        <div class="leaderboard-section">
            <h2 class="section-title">🎖️ Top 3 Champions</h2>
            <div class="podium">
                <!-- Second Place -->
                <div class="podium-place second">
                    <div class="podium-medal">🥈</div>
                    <div class="podium-name"><?php echo htmlspecialchars($leaderboard[1]['username'] ?? 'Anoniem'); ?></div>
                    <div class="podium-score" style="color: #C0C0C0"><?php echo $leaderboard[1]['score']; ?></div>
                </div>
                
                <!-- First Place -->
                <div class="podium-place first">
                    <div class="podium-medal">🥇</div>
                    <div class="podium-name"><?php echo htmlspecialchars($leaderboard[0]['username'] ?? 'Anoniem'); ?></div>
                    <div class="podium-score" style="color: #FFD700"><?php echo $leaderboard[0]['score']; ?></div>
                </div>
                
                <!-- Third Place -->
                <div class="podium-place third">
                    <div class="podium-medal">🥉</div>
                    <div class="podium-name"><?php echo htmlspecialchars($leaderboard[2]['username'] ?? 'Anoniem'); ?></div>
                    <div class="podium-score" style="color: #CD7F32"><?php echo $leaderboard[2]['score']; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full Leaderboard -->
        <div class="leaderboard-section">
            <h2 class="section-title">📊 Volledige Ranglijst</h2>
            
            <?php if (count($leaderboard) > 0): ?>
            <table class="leaderboard-table">
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
                    <?php foreach ($leaderboard as $entry): ?>
                    <tr <?php echo (isset($_SESSION['username']) && $entry['username'] === $_SESSION['username']) ? 'class="current-user"' : ''; ?>>
                        <td class="position-cell">
                            <?php if ($entry['position'] <= 3): ?>
                                <span class="medal"><?php echo getMedal($entry['position']); ?></span>
                            <?php else: ?>
                                #<?php echo $entry['position']; ?>
                            <?php endif; ?>
                        </td>
                        <td class="username-cell">
                            <?php if ($entry['position'] == 1): ?>
                                <span class="crown">👑</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($entry['username'] ?? 'Anoniem'); ?>
                        </td>
                        <td class="score-cell" style="color: <?php echo getScoreColor($entry['score']); ?>">
                            <?php echo $entry['score']; ?>
                        </td>
                        <td class="time-cell"><?php echo formatTime($entry['completion_time']); ?></td>
                        <td class="hints-cell"><?php echo $entry['hints_used']; ?></td>
                        <td class="attempts-cell"><?php echo $entry['wrong_attempts']; ?></td>
                        <td class="date-cell"><?php echo formatDateTime($entry['timestamp']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🎮 Nog geen voltooide games!</p>
                <p>Wees de eerste om je naam op het leaderboard te krijgen!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Completions -->
        <div class="leaderboard-section">
            <h2 class="section-title">🕒 Recente Voltooiingen</h2>
            
            <?php if (count($recent_completions) > 0): ?>
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Speler</th>
                        <th>Score</th>
                        <th>Tijd</th>
                        <th>Wanneer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_completions as $entry): ?>
                    <tr>
                        <td class="username-cell"><?php echo htmlspecialchars($entry['username'] ?? 'Anoniem'); ?></td>
                        <td class="score-cell" style="color: <?php echo getScoreColor($entry['score']); ?>"><?php echo $entry['score']; ?></td>
                        <td class="time-cell"><?php echo formatTime($entry['completion_time']); ?></td>
                        <td class="date-cell"><?php echo formatDateTime($entry['timestamp']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>Nog geen recente voltooiingen</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="navigation">
            <a href="index.php" class="nav-btn">🏠 Terug naar Home</a>
            <a href="room1.php" class="nav-btn">🎮 Speel Nu</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php" class="nav-btn">⚙️ Admin Panel</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="location.reload()" title="Ververs Leaderboard">
        🔄
    </button>

    <script>
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Highlight current user
        <?php if (isset($_SESSION['username'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const currentUserRows = document.querySelectorAll('.current-user');
            currentUserRows.forEach(row => {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>