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

// ========== SHOW ALL TABLES ==========
echo "<h3>All Tables in Database:</h3>";
$tables_result = pg_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
if($tables_result && pg_num_rows($tables_result) > 0) {
    echo "<ul>";
    while($table = pg_fetch_assoc($tables_result)) {
        echo "<li><strong>" . $table['table_name'] . "</strong></li>";
        
        // Show column structure for each table
        $columns_result = pg_query_params($conn, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name=$1 AND table_schema='public' ORDER BY ordinal_position", [$table['table_name']]);
        if($columns_result && pg_num_rows($columns_result) > 0) {
            echo "<ul style='margin-bottom:15px;'>";
            while($col = pg_fetch_assoc($columns_result)) {
                echo "<li><small>" . $col['column_name'] . " (" . $col['data_type'] . ")</small></li>";
            }
            echo "</ul>";
        }
    }
    echo "</ul>";
} else {
    echo "No tables found or error: " . pg_last_error($conn);
}

// ========== SHOW ALL USERS ==========
echo "<hr>";
echo "<h3>All Users (Complete List):</h3>";

// First, check what columns exist in users table
$columns_result = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' ORDER BY ordinal_position");
$columns = [];
if($columns_result) {
    while($col = pg_fetch_assoc($columns_result)) {
        $columns[] = $col['column_name'];
    }
}

if(!empty($columns)) {
    // Get all users data
    $users_result = pg_query($conn, "SELECT * FROM users ORDER BY phone");
    if($users_result && pg_num_rows($users_result) > 0) {
        echo "<p><strong>Total users found: " . pg_num_rows($users_result) . "</strong></p>";
        
        // Build table header
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;'>";
        echo "<tr style='background-color:#f0f0f0'>";
        foreach($columns as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        
        // Show each user
        while($user = pg_fetch_assoc($users_result)) {
            echo "<tr>";
            foreach($columns as $col) {
                $value = $user[$col] ?? '';
                if($value === null || $value === '') {
                    $display = "<em>NULL</em>";
                } else {
                    $display = htmlspecialchars($value);
                }
                echo "<td>" . $display . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No users found in database.";
    }
} else {
    echo "Could not retrieve users table structure: " . pg_last_error($conn);
}

// ========== SUMMARY STATISTICS ==========
echo "<hr>";
echo "<h3>Database Statistics:</h3>";
$stats_result = pg_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public') as total_tables
");
if($stats_result && $stats = pg_fetch_assoc($stats_result)) {
    echo "<ul>";
    echo "<li><strong>Total Tables:</strong> " . $stats['total_tables'] . "</li>";
    echo "<li><strong>Total Users:</strong> " . $stats['total_users'] . "</li>";
    echo "</ul>";
}

// ========== OPTIONAL: SHOW SPECIFIC DATA FOR TESTING ==========
echo "<hr>";
echo "<h3>Test Links (Update OTP - replace PHONE with actual phone number):</h3>";
echo "<p>Set OTP to 1 (WRONG): <a href='?test_phone=PHONE_NUMBER&test_value=1'>Click here</a></p>";
echo "<p>Set OTP to 2 (CORRECT): <a href='?test_phone=PHONE_NUMBER&test_value=2'>Click here</a></p>";

// Handle test updates
if(isset($_GET['test_phone']) && isset($_GET['test_value'])) {
    $testPhone = $_GET['test_phone'];
    $testValue = (int)$_GET['test_value'];
    
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

pg_close($conn);
?>
