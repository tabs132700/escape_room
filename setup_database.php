<?php
$servername = "localhost";
$username = "root";
$password = "";

try {
    // Create connection without database first
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $conn->exec("CREATE DATABASE IF NOT EXISTS `escape_room`");
    echo "Database 'escape_room' created successfully<br>";
    
    // Use the database
    $conn->exec("USE `escape_room`");
    
    // Create questions table
    $sql = "CREATE TABLE IF NOT EXISTS `questions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `room` int(11) NOT NULL,
        `object` varchar(50) NOT NULL,
        `question` text,
        `answer` varchar(100) NOT NULL,
        PRIMARY KEY (`id`)
    )";
    $conn->exec($sql);
    echo "Table 'questions' created successfully<br>";
    
    // Insert sample questions
    $questions = [
        [1, 'clock', 'Wat is 12 + 34?', '46'],
        [1, 'computer', 'Wat wil je doen uit deze kamer?', 'ontsnappen'],
        [1, 'drawer', 'Wat is 2000 + 25?', '2025'],
        [2, 'bookshelf', 'Hoeveel letters heeft GEHEUGEN?', '8'],
        [2, 'safe', 'Hoofdstad van Nederland?', 'amsterdam'],
        [2, 'door', 'Wil je ontsnappen?', 'ja']
    ];
    
    $stmt = $conn->prepare("INSERT INTO questions (room, object, question, answer) VALUES (?, ?, ?, ?)");
    
    foreach ($questions as $q) {
        $stmt->execute($q);
    }
    
    echo "Sample questions inserted successfully<br>";
    echo "<strong>Setup complete! You can now start playing.</strong>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
