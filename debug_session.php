<?php
session_start();

echo "<h2>🔍 Session Debug Information</h2>";

echo "<h3>Session Status:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in<br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
} else {
    echo "❌ User is NOT logged in<br>";
}

echo "<h3>All Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Cookie Information:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";

echo "<h3>Database Connection Test:</h3>";
$conn = new mysqli('localhost', 'root', '', 'escape_room');
if ($conn->connect_error) {
    echo "❌ Database connection failed: " . $conn->connect_error . "<br>";
} else {
    echo "✅ Database connection successful<br>";
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "✅ User found in database: " . $user['username'] . " (" . $user['role'] . ")<br>";
        } else {
            echo "❌ User ID " . $_SESSION['user_id'] . " not found in database<br>";
        }
        $stmt->close();
    }
    $conn->close();
}

echo "<h3>Quick Actions:</h3>";
echo "<a href='login.php'>🔐 Go to Login</a> | ";
echo "<a href='room1.php'>🎮 Go to Game</a> | ";
echo "<a href='logout.php'>🚪 Logout</a>";
?>