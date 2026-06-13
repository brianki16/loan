<?php
/**
 * Credit Card Form with Light Mode Design (Visa / Other Cards Detection)
 * 
 * Features:
 * - Clean, minimal light theme matching the reference image
 * - Real-time card type detection (Visa, Mastercard, Amex, Discover, etc.)
 * - Client-side (JavaScript) and server-side (PHP) detection
 * - Luhn algorithm validation
 * - Responsive, modern card preview with chip and brand logo
 */

// ==================== PHP CARD DETECTION FUNCTIONS ====================

/**
 * Detect card type based on card number (BIN/IIN pattern matching)
 * 
 * @param string $cardNumber The credit card number (may contain spaces/dashes)
 * @return string Detected card type
 */
function detectCardType($cardNumber) {
    $number = preg_replace('/\D/', '', $cardNumber);
    
    if (empty($number)) return 'Other';
    
    // Visa: starts with 4, length 13 or 16
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) return 'Visa';
    
    // Mastercard: 51-55 or 2221-2720, length 16
    if (preg_match('/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$/', $number)) return 'Mastercard';
    
    // American Express: 34 or 37, length 15
    if (preg_match('/^3[47][0-9]{13}$/', $number)) return 'American Express';
    
    // Discover: 6011, 65, 644-649, 622126-622925
    if (preg_match('/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10,11})$/', $number)) return 'Discover';
    
    // JCB: 3528-3589, 2131, 1800
    if (preg_match('/^(?:352[8-9][0-9]{11}|35[0-9]{14}|2131[0-9]{11}|1800[0-9]{11})$/', $number)) return 'JCB';
    
    // Diners Club
    if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11,12}$/', $number)) return 'Diners Club';
    
    return 'Other';
}

/**
 * Validate card number using Luhn algorithm
 */
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

// ==================== FORM SUBMISSION HANDLING ====================

$submitted = false;
$detectedType = '';
$luhnValid = false;
$maskedCard = '';
$displayName = '';
$displayExpiry = '';
$displayCVV = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumberRaw = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $nameOnCard = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $expiryDate = isset($_POST['expiry']) ? trim($_POST['expiry']) : '';
    $securityCode = isset($_POST['cvv']) ? trim($_POST['cvv']) : '';
    
    if (empty($cardNumberRaw) || empty($nameOnCard) || empty($expiryDate) || empty($securityCode)) {
        $errorMessage = 'Please fill in all fields.';
    } else {
        $detectedType = detectCardType($cardNumberRaw);
        $luhnValid = luhnCheck($cardNumberRaw);
        
        $cleanNumber = preg_replace('/\D/', '', $cardNumberRaw);
        $last4 = substr($cleanNumber, -4);
        $maskedCard = '•••• •••• •••• ' . $last4;
        
        $displayName = htmlspecialchars($nameOnCard);
        $displayExpiry = htmlspecialchars($expiryDate);
        $displayCVV = str_repeat('•', strlen($securityCode));
        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Light Mode Card Payment | Visa Detection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Main container – light mode card form */
        .payment-container {
            max-width: 520px;
            width: 100%;
            margin: 0 auto;
        }

        /* Card form wrapper */
        .card-form {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        /* Credit Card Preview (light mode, like image) */
        .card-preview-light {
            background: linear-gradient(135deg, #fefefe, #f8fafc);
            padding: 28px 24px 24px;
            border-bottom: 1px solid #eef2f6;
            position: relative;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .card-chip {
            width: 42px;
            height: 32px;
            background: linear-gradient(135deg, #e6b85e, #d4a23a);
            border-radius: 8px;
            position: relative;
        }
        .card-chip:before {
            content: '';
            position: absolute;
            top: 6px;
            left: 6px;
            width: 30px;
            height: 20px;
            background: rgba(255,255,240,0.3);
            border-radius: 4px;
        }

        .card-brand {
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: 1px;
            color: #1e293b;
            font-family: monospace;
        }

        .visa-brand {
            color: #1a1f71;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .preview-number {
            font-size: 1.3rem;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #0f172a;
            background: rgba(0,0,0,0.02);
            padding: 12px 0;
            margin: 16px 0;
            word-break: break-all;
        }

        .preview-details-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #475569;
        }

        .preview-label {
            font-size: 0.65rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
        }

        .preview-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 0.8rem;
        }

        /* Form area */
        .form-fields {
            padding: 28px 28px 32px;
        }

        .input-row {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5b6e8c;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            font-size: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            transition: all 0.2s;
            font-family: inherit;
            color: #0f172a;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        input::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        .two-columns {
            display: flex;
            gap: 20px;
        }
        .two-columns > div {
            flex: 1;
        }

        /* Card type indicator (light) */
        .card-type-badge {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #f8fafc;
            padding: 6px 14px;
            border-radius: 60px;
            width: fit-content;
            border: 1px solid #eef2f6;
        }

        .detected-icon {
            font-weight: 600;
        }

        /* Receive Button */
        .receive-now-btn {
            width: 100%;
            background: #0f172a;
            border: none;
            padding: 16px;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: white;
            border-radius: 40px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 12px;
            font-weight: 700;
        }
        .receive-now-btn:hover {
            background: #1e293b;
            transform: scale(0.98);
        }

        /* Result panel (light mode) */
        .result-panel {
            margin-top: 24px;
            background: #f8fafc;
            border-radius: 24px;
            padding: 20px;
            border-left: 5px solid #3b82f6;
            font-size: 0.85rem;
            color: #1e293b;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .detection-badge {
            background: #e6f0ff;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            margin: 6px 0;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 20px;
            margin-top: 20px;
        }
        .disclaimer-light {
            text-align: center;
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #edf2f7;
        }

        /* Additional spacing */
        .mt-2 { margin-top: 6px; }
    </style>
</head>
<body>
<div class="payment-container">
    <div class="card-form">
        <!-- Light mode card preview (matches image style) -->
        <div class="card-preview-light">
            <div class="card-header">
                <div class="card-chip"></div>
                <div class="card-brand" id="liveBrandLogo">VISA</div>
            </div>
            <div class="preview-number" id="livePreviewNumber">•••• •••• •••• ••••</div>
            <div class="preview-details-row">
                <div>
                    <div class="preview-label">Cardholder name</div>
                    <div class="preview-value" id="livePreviewName">YOUR NAME</div>
                </div>
                <div>
                    <div class="preview-label">Expires</div>
                    <div class="preview-value" id="livePreviewExpiry">MM/YY</div>
                </div>
            </div>
        </div>

        <!-- Form with fields -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="paymentForm">
            <div class="form-fields">
                <!-- Card Number -->
                <div class="input-row">
                    <label>Card number</label>
                    <input type="text" name="card_number" id="cardNumber" 
                           placeholder="1234 5678 9012 3456" autocomplete="off"
                           value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>"
                           required>
                    <div class="card-type-badge" id="cardTypeIndicator">
                        <span id="detectedCardTypeText">💳 Enter card number</span>
                    </div>
                </div>

                <!-- Name on card -->
                <div class="input-row">
                    <label>Name on card</label>
                    <input type="text" name="card_name" id="cardName" 
                           placeholder="John Doe" 
                           value="<?php echo isset($_POST['card_name']) ? htmlspecialchars($_POST['card_name']) : ''; ?>"
                           required>
                </div>

                <!-- Expiry & CVV row -->
                <div class="two-columns">
                    <div>
                        <label>Expiration date</label>
                        <input type="text" name="expiry" id="expiryDate" 
                               placeholder="MM / YY" 
                               value="<?php echo isset($_POST['expiry']) ? htmlspecialchars($_POST['expiry']) : ''; ?>"
                               required>
                    </div>
                    <div>
                        <label>Security code (CVV)</label>
                        <input type="text" name="cvv" id="cvvCode" 
                               placeholder="123" maxlength="4" 
                               value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>"
                               required>
                    </div>
                </div>

                <button type="submit" class="receive-now-btn">✨ Receive Now ✨</button>
                <div class="disclaimer-light">
                    🔒 Demo — Real-time Visa / card type detection | No real charges
                </div>
            </div>
        </form>
    </div>

    <!-- Server-side result panel -->
    <?php if ($submitted && empty($errorMessage)): ?>
    <div class="result-panel">
        <strong>✅ Payment simulation received</strong><br>
        📇 <strong>Card type (PHP):</strong> 
        <span class="detection-badge"><?php echo htmlspecialchars($detectedType); ?></span><br>
        <?php if ($detectedType === 'Visa'): ?>
            💙 <strong>VISA card detected</strong><br>
        <?php elseif ($detectedType !== 'Other'): ?>
            🃏 <strong><?php echo htmlspecialchars($detectedType); ?> detected</strong><br>
        <?php else: ?>
            ⚠️ Other card type (not Visa/Mastercard/Amex)<br>
        <?php endif; ?>
        🔢 <strong>Luhn check:</strong> <?php echo $luhnValid ? '✅ Valid format' : '❌ Invalid checksum'; ?><br>
        💳 <strong>Masked:</strong> <?php echo $maskedCard; ?><br>
        👤 <strong>Cardholder:</strong> <?php echo $displayName; ?><br>
        📅 <strong>Expiry:</strong> <?php echo $displayExpiry; ?>
    </div>
    <?php elseif ($submitted && $errorMessage): ?>
    <div class="result-panel error-message">
        ❌ <?php echo htmlspecialchars($errorMessage); ?>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript: Real-time Light Mode Detection + Preview Updates (Visa / Other) -->
<script>
    (function(){
        // Card type detection (same as PHP)
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

        function formatCardNumberDisplay(value) {
            let digits = value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < digits.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += digits[i];
            }
            return formatted;
        }

        function updateLightUI() {
            const cardInput = document.getElementById('cardNumber');
            const nameInput = document.getElementById('cardName');
            const expiryInput = document.getElementById('expiryDate');
            
            let rawNumber = cardInput.value;
            let cleanNumber = rawNumber.replace(/\D/g, '');
            
            // Auto-format input spacing
            let formattedInput = formatCardNumberDisplay(rawNumber);
            if (formattedInput !== rawNumber && cardInput !== document.activeElement) {
                cardInput.value = formattedInput;
            }
            
            const detected = detectCardTypeJS(rawNumber);
            const typeSpan = document.getElementById('detectedCardTypeText');
            const brandLogo = document.getElementById('liveBrandLogo');
            
            // Update detection text & brand logo on card preview
            if (detected === 'Visa') {
                typeSpan.innerHTML = '💙 Visa card detected';
                brandLogo.innerHTML = 'VISA';
                brandLogo.className = 'card-brand visa-brand';
                brandLogo.style.color = '#1a1f71';
            } else if (detected === 'Mastercard') {
                typeSpan.innerHTML = '🧡 Mastercard detected';
                brandLogo.innerHTML = 'Mastercard';
                brandLogo.className = 'card-brand';
                brandLogo.style.color = '#cc0000';
            } else if (detected === 'American Express') {
                typeSpan.innerHTML = '🔷 American Express';
                brandLogo.innerHTML = 'AMEX';
                brandLogo.style.color = '#006fcf';
                brandLogo.className = 'card-brand';
            } else if (detected === 'Other') {
                if (cleanNumber.length > 0) {
                    typeSpan.innerHTML = '🃏 Other card (not Visa)';
                    brandLogo.innerHTML = 'CARD';
                    brandLogo.style.color = '#475569';
                } else {
                    typeSpan.innerHTML = '💳 Enter card number';
                    brandLogo.innerHTML = 'VISA';
                    brandLogo.style.color = '#1a1f71';
                }
                brandLogo.className = 'card-brand';
            } else {
                typeSpan.innerHTML = `✨ ${detected} detected`;
                brandLogo.innerHTML = detected.substring(0,6);
                brandLogo.style.color = '#0f172a';
            }
            
            // Update preview card number masked
            const previewNumSpan = document.getElementById('livePreviewNumber');
            if (cleanNumber.length > 0) {
                let last4 = cleanNumber.slice(-4);
                let masked = '•••• •••• •••• ' + last4;
                previewNumSpan.textContent = masked;
            } else {
                previewNumSpan.textContent = '•••• •••• •••• ••••';
            }
            
            // Update name preview
            const previewNameSpan = document.getElementById('livePreviewName');
            let holderName = nameInput.value.trim();
            previewNameSpan.textContent = holderName === "" ? "YOUR NAME" : holderName.toUpperCase().substring(0, 25);
            
            // Update expiry preview
            const previewExpirySpan = document.getElementById('livePreviewExpiry');
            let expiryRaw = expiryInput.value.replace(/\s/g, '');
            let cleanExpiry = expiryRaw.replace(/\D/g, '');
            if (cleanExpiry.length >= 2) {
                let month = cleanExpiry.slice(0,2);
                let year = cleanExpiry.slice(2,4);
                if (year.length) previewExpirySpan.textContent = `${month}/${year}`;
                else previewExpirySpan.textContent = month;
            } else {
                previewExpirySpan.textContent = "MM/YY";
            }
            
            // Auto slash for expiry input
            if (expiryInput.value.length === 2 && !expiryInput.value.includes('/')) {
                expiryInput.value = expiryInput.value + '/';
            }
        }
        
        // Attach events
        const cardField = document.getElementById('cardNumber');
        const nameField = document.getElementById('cardName');
        const expiryField = document.getElementById('expiryDate');
        const cvvField = document.getElementById('cvvCode');
        
        [cardField, nameField, expiryField, cvvField].forEach(field => {
            if (field) field.addEventListener('input', updateLightUI);
        });
        
        // Extra expiry formatting
        expiryField.addEventListener('input', function(e) {
            let val = expiryField.value.replace(/\D/g, '');
            if (val.length >= 2) {
                let month = val.slice(0,2);
                let year = val.slice(2,4);
                if (year.length) expiryField.value = month + '/' + year;
                else expiryField.value = month;
            } else {
                expiryField.value = val;
            }
            updateLightUI();
        });
        
        // Restrict card number length
        cardField.addEventListener('input', function(e) {
            let digits = cardField.value.replace(/\D/g, '');
            if (digits.length > 19) {
                cardField.value = formatCardNumberDisplay(digits.slice(0,19));
            }
            updateLightUI();
        });
        
        cvvField.addEventListener('input', function(e) {
            cvvField.value = cvvField.value.replace(/\D/g, '').slice(0,4);
            updateLightUI();
        });
        
        updateLightUI();
    })();
</script>
</body>
</html>
