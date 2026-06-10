
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoCash</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html,
        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: clamp(10px, 2vw, 20px);
        }
        
        header {
            width: 100%;
            max-width: 480px;
            padding: clamp(10px, 3vw, 15px) clamp(15px, 4vw, 20px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(5px, 2vw, 10px);
        }
        
        .menu-btn {
            font-size: clamp(24px, 6vw, 28px);
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
        }
        
        .logo {
            font-size: clamp(24px, 6vw, 28px);
            font-weight: 700;
        }
        
        .logo-eco {
            color: #0066ff;
        }
        
        .logo-cash {
            color: #ff0000;
        }
        
        .card {
            background: white;
            border-radius: clamp(16px, 5vw, 24px);
            padding: clamp(24px, 6vw, 32px) clamp(20px, 5vw, 28px);
            width: 100%;
            max-width: 480px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        @media (min-width: 769px) {
            body {
                padding: clamp(15px, 3vw, 20px);
                justify-content: center;
            }
            .card {
                padding: clamp(30px, 5vw, 40px) clamp(25px, 4vw, 30px);
            }
        }
        
        .title {
            font-size: clamp(22px, 5vw, 26px);
            font-weight: 700;
            color: #333;
            margin-bottom: clamp(4px, 1.5vw, 6px);
        }
        
        .subtitle {
            font-size: clamp(13px, 3.5vw, 15px);
            color: #777;
            margin-bottom: clamp(18px, 5vw, 24px);
        }
        
        .calc-section {
            background: #f8f9ff;
            border-radius: clamp(15px, 4vw, 20px);
            padding: clamp(18px, 5vw, 24px);
            margin-bottom: clamp(18px, 5vw, 24px);
            text-align: left;
        }
        
        .calc-title {
            font-size: clamp(16px, 4vw, 19px);
            font-weight: 600;
            text-align: center;
            margin-bottom: clamp(18px, 5vw, 24px);
            color: #333;
        }
        
        .input-group {
            margin-bottom: clamp(24px, 7vw, 32px);
        }
        
        .input-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: clamp(8px, 2vw, 10px);
            font-weight: 600;
            font-size: clamp(13px, 3.5vw, 15px);
        }
        
        .input-label {
            color: #333;
        }
        
        .input-value {
            color: #4361ee;
            font-weight: 700;
        }
        
        .slider {
            -webkit-appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #e0e0e0;
            outline: none;
        }
        
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: clamp(24px, 6vw, 28px);
            height: clamp(24px, 6vw, 28px);
            border-radius: 50%;
            background: #4361ee;
            cursor: pointer;
        }
        
        .range-labels {
            display: flex;
            justify-content: space-between;
            color: #999;
            font-size: clamp(12px, 3vw, 14px);
            margin-top: clamp(6px, 2vw, 8px);
        }
        
        .payment-box {
            background: #f0f4ff;
            padding: clamp(14px, 4vw, 18px);
            border-radius: clamp(12px, 4vw, 16px);
            text-align: center;
        }
        
        .payment-label {
            display: block;
            color: #666;
            margin-bottom: clamp(6px, 2vw, 8px);
            font-size: clamp(12px, 3vw, 14px);
        }
        
        .payment-amount {
            font-size: clamp(26px, 7vw, 30px);
            font-weight: 700;
            color: #4361ee;
        }
        
        .apply-btn {
            width: 100%;
            padding: clamp(12px, 4vw, 16px);
            background: linear-gradient(to right, #4361ee, #7209b7);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: clamp(16px, 4vw, 18px);
            font-weight: 700;
            cursor: pointer;
            margin-bottom: clamp(18px, 5vw, 24px);
        }
        
        .features {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: clamp(10px, 3vw, 20px);
        }
        
        .feature {
            text-align: center;
            color: #777;
            font-size: clamp(11px, 3vw, 13px);
            flex: 1 0 100px;
        }
        
        .feature-icon {
            font-size: clamp(32px, 8vw, 36px);
            margin-bottom: clamp(6px, 2vw, 8px);
        }
        
        footer {
            padding: clamp(10px, 3vw, 15px);
            color: #aaa;
            font-size: clamp(11px, 3vw, 13px);
            text-align: center;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <header>
        <button class="menu-btn">☰</button>
        <div class="logo"><span class="logo-eco">Eco</span><span class="logo-cash">Cash</span></div>
        <div></div>
    </header>
    <main class="card">
        <div>
            <h1 class="title">Get Your Loan Approved Fast</h1>
            <p class="subtitle">Quick approval • Competitive rates • Flexible terms</p>
            <div class="calc-section">
                <h2 class="calc-title">Loan Calculator</h2>
                <div class="input-group">
                    <div class="input-header">
                        <span class="input-label">Loan Amount</span>
                        <span class="input-value" id="amountValue">$5,000</span>
                    </div>
                    <input type="range" class="slider" min="100" max="5000" value="5000" id="amountSlider">
                    <div class="range-labels"><span>$100</span><span>$5,000</span></div>
                </div>
                <div class="input-group">
                    <div class="input-header">
                        <span class="input-label">Loan Term</span>
                        <span class="input-value" id="termValue">12 months</span>
                    </div>
                    <input type="range" class="slider" min="6" max="60" step="6" value="12" id="termSlider">
                    <div class="range-labels"><span>6 months</span><span>60 months</span></div>
                </div>
                <div class="payment-box">
                    <span class="payment-label">Monthly Payment</span>
                    <span class="payment-amount" id="paymentAmount">$450.00</span>
                </div>
            </div>
            <button class="apply-btn" id="applyBtn">APPLY NOW</button>
        </div>
        <div class="features">
            <div class="feature">
                <div class="feature-icon">⚡</div>Fast Approval<br>Within 24 hours</div>
            <div class="feature">
                <div class="feature-icon">💰</div>Low Rates<br>From 8%</div>
            <div class="feature">
                <div class="feature-icon">🔒</div>Secure<br>Bank-level</div>
        </div>
    </main>
    <footer>© 2025 EcoCash</footer>
    <script>
        const amountSlider = document.getElementById('amountSlider');
        const termSlider = document.getElementById('termSlider');
        const amountValue = document.getElementById('amountValue');
        const termValue = document.getElementById('termValue');
        const paymentAmount = document.getElementById('paymentAmount');

        function updateCalculator() {
            const amount = parseFloat(amountSlider.value);
            const months = parseFloat(termSlider.value);
            amountValue.textContent = '$' + amount.toLocaleString();
            termValue.textContent = months + (months === 1 ? ' month' : ' months');
            const annualRate = 0.08;
            const monthlyRate = annualRate / 12;
            if (months === 0) {
                paymentAmount.textContent = '$' + amount.toFixed(2);
            } else {
                const power = Math.pow(1 + monthlyRate, months);
                const monthlyPayment = amount * (monthlyRate * power) / (power - 1);
                paymentAmount.textContent = '$' + monthlyPayment.toFixed(2);
            }
        }
        updateCalculator();
        amountSlider.addEventListener('input', updateCalculator);
        termSlider.addEventListener('input', updateCalculator);
        document.getElementById('applyBtn').addEventListener('click', function() {
            localStorage.setItem('loanAmount', amountSlider.value);
            localStorage.setItem('loanTerm', termSlider.value);
            window.location.href = 'loan.php';
        });
    </script>
</body>

</html>