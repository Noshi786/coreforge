<?php
$host = "sql208.infinityfree.com";
$user = "if0_42738026";
$pass = "Noshin7862";
$dbname = "if0_42738026_coreforge_db";

// mysqli connection (purane code ke liye)
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// PDO connection (index.php aur baaki files ke liye)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}
?>