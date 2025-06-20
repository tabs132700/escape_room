<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $antwoord = strtolower(trim($_POST['antwoord']));
    $object = $_POST['object'];

    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'escape_room');
    if ($conn->connect_error) {
        die("Connectie mislukt: " . $conn->connect_error);
    }

    // Get correct answer from database
    $stmt = $conn->prepare("SELECT answer FROM questions WHERE object = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $object);
    $stmt->execute();
    $stmt->bind_result($correctAnswer);
    $stmt->fetch();
    $stmt->close();

    if ($correctAnswer === null) {
        $_SESSION['feedback'] = 'Vraag bestaat niet of is niet actief.';
        header('Location: room1.php#' . $object);
        exit;
    }

    $userId = $_SESSION['user_id'];
    
    // Debug: Check if user actually exists in database
    $checkUser = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $checkUser->bind_param("i", $userId);
    $checkUser->execute();
    $userResult = $checkUser->get_result();
    
    if ($userResult->num_rows === 0) {
        // User doesn't exist in database, redirect to login
        $checkUser->close();
        $conn->close();
        session_destroy();
        header('Location: login.php?error=invalid_user');
        exit;
    }
    $checkUser->close();
    
    if ($antwoord === strtolower($correctAnswer)) {
        // Correct answer!
        $_SESSION[$object . '_solved'] = true;
        $_SESSION['feedback'] = "🎉 Correct! Je hebt het juiste antwoord bij $object gevonden.";
        
        // Update question statistics
        $stmt = $conn->prepare("UPDATE questions SET times_solved = times_solved + 1, times_attempted = times_attempted + 1 WHERE object = ?");
        $stmt->bind_param("s", $object);
        $stmt->execute();
        $stmt->close();
        
        // Check if this is the final door
        if ($object === 'door') {
            // Game completed! Calculate final score and save to leaderboard
            $completion_time = time() - $_SESSION['start_time'];
            $hints_used = $_SESSION['hints_used'] ?? 0;
            $wrong_attempts = $_SESSION['wrong_attempts'] ?? 0;
            
            // Calculate score
            $base_score = 1000;
            $time_penalty = floor($completion_time / 60) * 20 + ($completion_time % 60);
            $hint_penalty = $hints_used * 50;
            $wrong_penalty = $wrong_attempts * 10;
            $final_score = max(0, $base_score - $time_penalty - $hint_penalty - $wrong_penalty);
            
            // Get username from database and verify user exists
            $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                $username = $user_data['username'];
                $stmt->close();
                
                // Save to game_stats table for leaderboard
                $stmt = $conn->prepare("INSERT INTO game_stats (user_id, completion_time, hints_used, wrong_attempts, completed, score, player_name, timestamp) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())");
                $stmt->bind_param("iiiiss", $userId, $completion_time, $hints_used, $wrong_attempts, $final_score, $username);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt->close();
                // User doesn't exist, save without user_id (anonymous)
                $stmt = $conn->prepare("INSERT INTO game_stats (user_id, completion_time, hints_used, wrong_attempts, completed, score, player_name, timestamp) VALUES (NULL, ?, ?, ?, 1, ?, 'Anonymous Player', NOW())");
                $stmt->bind_param("iiii", $completion_time, $hints_used, $wrong_attempts, $final_score);
                $stmt->execute();
                $stmt->close();
            }
            
            // Update game session if exists
            if (isset($_SESSION['game_session_id'])) {
                $stmt = $conn->prepare("UPDATE game_sessions SET session_end = NOW(), rooms_completed = 1, total_time_spent = ?, final_status = 'completed', puzzles_solved = 7 WHERE id = ?");
                $stmt->bind_param("ii", $completion_time, $_SESSION['game_session_id']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Update user statistics
            $stmt = $conn->prepare("UPDATE users SET total_games_completed = total_games_completed + 1, best_time = CASE WHEN best_time IS NULL OR ? < best_time THEN ? ELSE best_time END WHERE id = ?");
            $stmt->bind_param("iii", $completion_time, $completion_time, $userId);
            $stmt->execute();
            $stmt->close();
            
            // Store completion data for win page
            $_SESSION['completion_time'] = $completion_time;
            $_SESSION['final_score'] = $final_score;
            
            $conn->close();
            header('Location: win.php');
            exit;
        }
        
        // Update session puzzles count if session exists
        if (isset($_SESSION['game_session_id'])) {
            $stmt = $conn->prepare("UPDATE game_sessions SET puzzles_solved = puzzles_solved + 1 WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['game_session_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->close();
        header('Location: room1.php#' . $object);
        exit;
        
    } else {
        // Wrong answer
        $_SESSION['wrong_attempts'] = ($_SESSION['wrong_attempts'] ?? 0) + 1;
        $_SESSION['feedback'] = "❌ Fout antwoord bij $object. Probeer opnieuw!";
        
        // Update question statistics (attempted but not solved)
        $stmt = $conn->prepare("UPDATE questions SET times_attempted = times_attempted + 1 WHERE object = ?");
        $stmt->bind_param("s", $object);
        $stmt->execute();
        $stmt->close();
        
        $conn->close();
        header('Location: room1.php#' . $object);
        exit;
    }
} else {
    // Not a POST request, redirect to game
    if (isset($_SESSION['user_id'])) {
        header('Location: room1.php');
    } else {
        header('Location: login.php');
    }
    exit;
}
?>