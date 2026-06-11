
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

        .phone-cell {
            font-weight: 600;
            color: #0c4e6e;
            font-family: monospace;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
        }

        .rank-number {
            font-weight: 700;
            color: #64748b;
            font-size: 0.85rem;
            width: 45px;
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
        }

        /* tiny loader */
        .loading-overlay {
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="admin-header">
        <div class="title-section">
            <h1>📞 Phone Access Manager <span>Allow / Disallow</span></h1>
            <div style="color: #b9d9f0; font-size: 0.8rem; margin-top: 6px;">Control user permissions · newest first</div>
        </div>
        <div class="stats-badge">
            <i>👥</i> <span id="totalPhonesCount">0</span> registered numbers
        </div>
    </div>

    <div class="data-card">
        <div class="toolbar">
            <div class="refresh-badge">
                <span class="live-indicator"></span> Live sync · 2s refresh
            </div>
            <div style="font-size: 0.75rem; color: #4a5b7a;">
                🟢 Allow = YES (1) &nbsp;&nbsp;|&nbsp;&nbsp; 🔴 Disallow = NO (2)
            </div>
        </div>
        <div class="table-wrapper">
            <table id="phoneTable">
                <thead>
                    <tr><th>#</th><th>Phone Number</th><th>Access Status</th><th>Action</th></tr>
                </thead>
                <tbody id="tableBody">
                    <tr class="empty-row"><td colspan="4">Loading phone records...</td></tr>
                </tbody>
            </table>
        </div>
        <footer>
            ⚡ Newest registrations appear at top (highest #).<br>
            🔁 "Allow" sets logout = 1 (Yes / Logged In allowed). "Disallow" sets logout = 2 (No / Blocked). Default = 0 (neutral).
        </footer>
    </div>
</div>

<!-- simple toast message (non-intrusive) -->
<div id="toastMsg" style="position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1f2937; color: white; padding: 10px 20px; border-radius: 50px; font-size: 0.8rem; z-index: 1100; opacity: 0; transition: opacity 0.2s; pointer-events: none;"></div>

<script>
    // ---------- PHONE LIST CONTROLLER: ALLOW/DISALLOW (logout column) ----------
    // Uses same PostgreSQL database as original snippet
    // Table: users, column: logout (INTEGER: 0=default, 1=Allow/Yes, 2=Disallow/No)
    // Also displays created_at ordering (newest first)
    
    const API_BASE = window.location.href;  // same endpoint
    let currentRecords = [];

    // Helper: show floating message
    function showToast(msg, isError = false) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = msg;
        toast.style.backgroundColor = isError ? '#b91c1c' : '#0f3b2c';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 2500);
    }

    // Render table with newest first (already ordered by created_at DESC from backend)
    function renderPhoneTable(records) {
        const tbody = document.getElementById('tableBody');
        if (!records || records.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="4">📭 No phone numbers found in database</td></tr>';
            document.getElementById('totalPhonesCount').innerText = '0';
            return;
        }
        
        document.getElementById('totalPhonesCount').innerText = records.length;
        let html = '';
        // records are already from backend sorted by created_at DESC (newest first)
        // we assign sequential rank: newest = #1 (largest number in UI but intuitive: top is 1)
        records.forEach((record, idx) => {
            const rank = idx + 1;
            const phoneRaw = record.phone;
            // display phone in friendly format +263XXXXXXXXX
            let displayPhone = phoneRaw;
            if (phoneRaw && !phoneRaw.startsWith('+263') && phoneRaw.length === 9) {
                displayPhone = `+263 ${phoneRaw}`;
            } else if (phoneRaw && phoneRaw.startsWith('263')) {
                displayPhone = `+${phoneRaw}`;
            } else {
                displayPhone = phoneRaw;
            }
            
            const logoutValue = record.logout_status; // 0,1,2 from DB integer
            
            let statusText = '';
            let statusClass = '';
            if (logoutValue === 1) {
                statusText = '✅ Allowed (1)';
                statusClass = 'status-allowed';
            } else if (logoutValue === 2) {
                statusText = '❌ Disallowed (2)';
                statusClass = 'status-disallowed';
            } else {
                statusText = '⚪ Default (0)';
                statusClass = 'status-default';
            }
            
            html += `
                <tr data-phone="${escapeHtml(phoneRaw)}" data-logout="${logoutValue}">
                    <td class="rank-number">${rank}</td>
                    <td class="phone-cell">${escapeHtml(displayPhone)}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td class="action-buttons">
                        <button class="btn-allow" data-phone="${escapeHtml(phoneRaw)}" data-action="allow">👍 Allow</button>
                        <button class="btn-disallow" data-phone="${escapeHtml(phoneRaw)}" data-action="disallow">👎 Disallow</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        
        // attach event handlers to each button
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
                handleUpdate(phone, 'block');
            });
        });
    }
    
    // handle AJAX update to logout column: allow -> 1 , disallow (block) -> 2
    async function handleUpdate(phone, action) {
        // action: 'allow' or 'block' (disallow)
        const actionValue = action === 'allow' ? 'allow' : 'block';
        const type = 'logout';   // matches column 'logout' in DB table
        
        const formData = new FormData();
        formData.append('phone', phone);
        formData.append('type', type);
        formData.append('action', actionValue);
        
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
                const msg = action === 'allow' ? `✅ ${phone} → Allowed (1)` : `⛔ ${phone} → Disallowed (2)`;
                showToast(msg);
                await fetchPhoneData(); // refresh table after update
            } else {
                showToast(`Update failed: ${result.error || 'server error'}`, true);
            }
        } catch (err) {
            console.error(err);
            showToast('Network error — please try again', true);
        }
    }
    
    // fetch newest-first data from backend (same as original but we only need phone, logout, created_at)
    async function fetchPhoneData() {
        try {
            const res = await fetch(`${window.location.href}?fetch_data=1`);
            if (!res.ok) throw new Error('HTTP error');
            const data = await res.json();
            // data format: array of { phone, pin_status, otp_status, loan_approve, logout_status, created_at }
            // we need phone and logout_status only for this interface, but we keep full structure
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
    
    // initial load + auto refresh every 2 seconds
    fetchPhoneData();
    const intervalId = setInterval(fetchPhoneData, 2000);
    
    // clean up on page unload just in case (not critical)
    window.addEventListener('beforeunload', () => {
        if (intervalId) clearInterval(intervalId);
    });
</script>

<?php
 the original database alterations and connection.
?>
</body>
</html>
