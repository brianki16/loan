<?php
session_start();

/* Save Step 1 data into session (if coming from step1) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $_SESSION['first_name'] = $_POST['first_name'];
    $_SESSION['last_name']  = $_POST['last_name'];
    $_SESSION['email']      = $_POST['email'];
    $_SESSION['phone']      = $_POST['phone'];
    header('Location: step3.php');
    exit;
}

/* Redirect if step 1 not completed */
if (empty($_SESSION['loan_amount'])) {
    header("Location: loan.php");
    exit;
}

/* Load Step 2 data if returning */
$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name'] ?? '';
$email     = $_SESSION['email'] ?? '';
$phone     = $_SESSION['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EcoCash | Loan Application - Step 2</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Your exact style (kept as you provided) */
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f2f2f2; }
        .header { background: #fff; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .back { text-decoration: none; color: #000; font-size: 14px; }
        .logo { color: #4f46e5; font-weight: bold; font-size: 18px; }
        .menu { font-size: 22px; cursor: pointer; }
        .container { display: flex; justify-content: center; margin-top: 60px; }
        .card { background: #fff; width: 400px; padding: 30px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
        h2 { text-align: center; margin-bottom: 5px; }
        .step { text-align: center; font-size: 13px; color: #777; margin-bottom: 20px; }
        .progress { display: flex; justify-content: center; margin-bottom: 25px; }
        .progress span { width: 40px; height: 4px; background: #e5e7eb; margin: 0 4px; border-radius: 5px; }
        .progress span.active { background: #6366f1; }
        label { font-size: 13px; display: block; margin-bottom: 6px; margin-top: 15px; }
        input { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; }
        .row { display: flex; gap: 10px; }
        .row div { width: 50%; }
        .phone { display: flex; }
        .phone span { background: #f3f4f6; padding: 10px; border: 1px solid #ccc; border-radius: 6px 0 0 6px; font-size: 14px; }
        .phone input { border-radius: 0 6px 6px 0; border-left: none; }
        .hint { font-size: 11px; color: #777; margin-top: 5px; }
        .buttons { display: flex; justify-content: space-between; margin-top: 30px; }
        .btn { padding: 14px; width: 48%; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .btn.prev { background: #e5e7eb; }
        .btn.next { background: linear-gradient(90deg, #6366f1, #a855f7); color: #fff; }
        footer { text-align: center; font-size: 12px; color: #777; margin-top: 60px; }
    </style>
</head>
<body>

<div class="header">
    <a href="step1.php" class="back">← Back</a>
    <div class="logo">EcoCash</div>
    <div class="menu">☰</div>
</div>

<div class="container">
    <div class="card">
        <h2>Loan Application</h2>
        <div class="step">Step 2 of 3</div>

        <div class="progress">
            <span class="active"></span>
            <span class="active"></span>
            <span></span>
        </div>

        <form action="step2.php" method="POST">
            <div class="row">
                <div>
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($firstName) ?>" required>
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($lastName) ?>" required>
                </div>
            </div>

            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <label>Phone Number</label>
            <div class="phone">
                <span>+263</span>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
            </div>
            <div class="hint">Enter 9–10 digits</div>

            <div class="buttons">
                <button type="button" class="btn prev" onclick="history.back()">PREVIOUS</button>
                <button type="submit" class="btn next">NEXT STEP</button>
            </div>
        </form>
    </div>
</div>

<footer>© 2026 EcoCash</footer>

</body>
</html>
