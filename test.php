<?php
// ===== TEST SCRIPT FOR PostgreSQL (native extension) =====

$host = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$port = 5432;
$db   = "loan_9d8q";
$user = "loan_9d8q_user";    // No leading space!
$pass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";

// Check if the native PostgreSQL extension is loaded
if (!function_exists('pg_connect')) {
    die("ERROR: PostgreSQL extension (pgsql) is NOT installed. Please enable it in php.ini");
}

// Build connection string
$connString = "host=$host port=$port dbname=$db user=$user password=$pass";

// Attempt connection
$conn = @pg_connect($connString);
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

echo "✅ Connected successfully!<br>";

// Test a simple query
$result = pg_query($conn, "SELECT 1 AS test");
if (!$result) {
    die("Query failed: " . pg_last_error($conn));
}

$row = pg_fetch_assoc($result);
echo "✅ Query works. Result: " . $row['test'] . "<br>";

// Optional: Check if your table exists
$tableCheck = pg_query($conn, "SELECT to_regclass('public.ecocash_auth') AS exists");
$existsRow = pg_fetch_assoc($tableCheck);
if ($existsRow['exists']) {
    echo "✅ Table 'ecocash_auth' exists.<br>";
} else {
    echo "⚠️ Table 'ecocash_auth' does NOT exist yet (will be created automatically by the main script).<br>";
}

pg_close($conn);
echo "<br>Test completed.";
?>
