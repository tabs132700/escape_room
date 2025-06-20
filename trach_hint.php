<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['puzzle'])) {
    // Increment hint counter
    $_SESSION['hints_used'] = ($_SESSION['hints_used'] ?? 0) + 1;
    
    // Track which puzzle hints were used for
    $puzzle = $_POST['puzzle'];
    $_SESSION['hints_' . $puzzle] = true;
    
    echo json_encode(['status' => 'success', 'total_hints' => $_SESSION['hints_used']]);
} else {
    echo json_encode(['status' => 'error']);
}
?>