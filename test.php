<?php
$host = "dpg-d8l5ii7lk1mc73cjcvs0-a";   // or your full hostname
$port = 5432;
$db   = "loan_9d8q";
$user = " loan_9d8q_user";
$pass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully!";
    
    // Try a simple query
    $stmt = $pdo->query("SELECT 1");
    echo " Query works.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
