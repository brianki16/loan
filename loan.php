<?php
session_start();

// Only redirect if step2 tries to access without step1 data
// But for step1 itself, always show empty fields.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_type   = $_POST['loan_type'];
    $loan_amount = $_POST['loan_amount'];
    $loan_term   = $_POST['loan_term'];
    $purpose     = trim($_POST['purpose']);

    $errors = [];
    if ($loan_amount < 100 || $loan_amount > 50000) $errors[] = 'Loan amount must be $100 – $50,000';
    if (empty($purpose)) $errors[] = 'Please enter the loan purpose';

    if (empty($errors)) {
        // Save to session (still needed for step2 & step3)
        $_SESSION['loan_type']   = $loan_type;
        $_SESSION['loan_amount'] = $loan_amount;
        $_SESSION['loan_term']   = $loan_term;
        $_SESSION['purpose']     = $purpose;

        header('Location: step2.php');
        exit;
    } else {
        $error_msg = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoCash - Loan Application Step 1</title>
    <style>
        /* Your existing CSS (unchanged) */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: 'Inter', sans-serif; background: #f5f5f5; overflow: hidden; }
        body { display: flex; flex-direction: column; align-items: center; padding: 10px; }
        header { width: 100%; max-width: 480px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .back-btn { font-size: 28px; background: none; border: none; cursor: pointer; color: #333; text-decoration: none; }
        .logo { font-size: 28px; font-weight: 700; }
        .logo-eco { color: #0066ff; }
        .logo-cash { color: #ff0000; }
        .card { background: white; border-radius: 24px; padding: 40px 30px; width: 100%; max-width: 480px; box-shadow: 0 15px 40px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow-y: auto; }
        .form-title { font-size: 28px; font-weight: 700; text-align: center; margin-bottom: 20px; }
        .progress-bar { width: 180px; height: 6px; background: #e0e0e0; border-radius: 3px; margin: 30px auto; overflow: hidden; }
        .progress-fill { width: 33.33%; height: 100%; background: linear-gradient(to right, #4361ee, #7209b7); }
        .step-text { text-align: center; color: #777; font-size: 15px; margin-bottom: 30px; }
        .form-label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 15px; }
        .form-select, .form-input, .form-textarea { width: 100%; padding: 16px; border: 1px solid #ddd; border-radius: 12px; font-size: 16px; margin-bottom: 24px; transition: border 0.3s; }
        .form-select:focus, .form-input:focus, .form-textarea:focus { border-color: #4361ee; outline: none; }
        .invalid { border-color: #e74c3c !important; }
        .next-btn { width: 100%; padding: 18px; background: linear-gradient(to right, #4361ee, #7209b7); color: white; border: none; border-radius: 50px; font-size: 18px; font-weight: 700; cursor: pointer; margin-top: auto; }
        footer { padding: 20px; color: #aaa; font-size: 13px; text-align: center; margin-top: auto; }
        .error-message { color: #e74c3c; background: #ffe6e6; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
<header>
    <a href="#" class="back-btn" onclick="history.back()">←</a>
    <div class="logo"><span class="logo-eco">Eco</span><span class="logo-cash">Cash</span></div>
    <div></div>
</header>
<main class="card">
    <h1 class="form-title">Loan Application</h1>
    <div class="progress-bar"><div class="progress-fill"></div></div>
    <div class="step-text">Step 1 of 3</div>

    <?php if (isset($error_msg)): ?>
        <div class="error-message"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label class="form-label">Loan Type</label>
        <select class="form-select" name="loan_type">
            <option value="Personal Loan">Personal Loan</option>
            <option value="Business Loan">Business Loan</option>
            <option value="Home Loan">Home Loan</option>
            <option value="Car Loan">Car Loan</option>
            <option value="Education Loan">Education Loan</option>
        </select>

        <label class="form-label">Loan Amount ($)</label>
        <input type="number" class="form-input" name="loan_amount" placeholder="5000" min="100" max="50000" required>

        <label class="form-label">Loan Term</label>
        <select class="form-select" name="loan_term">
            <option value="6">6 Months</option>
            <option value="12">12 Months</option>
            <option value="18">18 Months</option>
            <option value="24">24 Months</option>
            <option value="36">36 Months</option>
            <option value="48">48 Months</option>
            <option value="60">60 Months</option>
        </select>

        <label class="form-label">Purpose of Loan</label>
        <textarea class="form-textarea" name="purpose" placeholder="What will you use the loan for?" required></textarea>

        <button type="submit" class="next-btn">NEXT STEP</button>
    </form>
</main>
<footer>© 2025 EcoCash</footer>
</body>
</html>