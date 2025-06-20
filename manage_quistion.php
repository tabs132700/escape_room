<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'escape_room');
if ($conn->connect_error) {
    die("Connectie mislukt: " . $conn->connect_error);
}

$message = '';
$editQuestion = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $room = (int)$_POST['room'];
                $object = trim($_POST['object']);
                $question = trim($_POST['question']);
                $answer = trim($_POST['answer']);
                $hint = trim($_POST['hint']);
                $difficulty = $_POST['difficulty'];
                
                $stmt = $conn->prepare("INSERT INTO questions (room, object, question, answer, hint, difficulty) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $room, $object, $question, $answer, $hint, $difficulty);