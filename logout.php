<?php
session_start();

// Update game session if user was playing
if (isset($_SESSION['game_session_id']) && isset($_SESSION['user_id'])) {
    $conn = new mysqli('localhost', 'root', '', 'escape_room');
    if (!$conn->connect_error) {
        // Update session end time
        $stmt = $conn->prepare("UPDATE game_sessions SET session_end = NOW(), final_status = 'abandoned' WHERE id = ? AND final_status IS NULL");
        $stmt->bind_param("i", $_SESSION['game_session_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit;
?>