<?php
/**
 * Credit Card Form with Card Type Detection (Visa / Other Cards)
 * 
 * This file provides:
 * - A clean, modern credit card input form
 * - Real-time card type detection (Visa, Mastercard, Amex, Discover, etc.)
 * - Client-side (JavaScript) and server-side (PHP) detection
 * - Luhn algorithm validation for card number integrity
 * - Responsive design with card visualization
 */

// ==================== PHP CARD DETECTION FUNCTIONS ====================

/**
 * Detect card type based on card number (BIN/IIN pattern matching)
 * 
 * @param string $cardNumber The credit card number (may contain spaces/dashes)
 * @return string Detected card type: 'Visa', 'Mastercard', 'American Express', 
 *                'Discover', 'JCB', 'Diners Club', or 'Other'
 */
function detectCardType($cardNumber) {
    // Remove all non-digit characters
    $number = preg_replace('/\D/', '', $cardNumber);
    
    if (empty($number)) {
        return 'Other';
    }
    
    // Visa: Starts with 4, length 13 or 16
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
        return 'Visa';
    }
    
    // Mastercard: Starts with 51-55 or 2221-2720, length 16
    if (preg_match('/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$/', $number)) {
        return 'Mastercard';
    }
    
    // American Express: Starts with 34 or 37, length 15
    if (preg_match('/^3[47][0-9]{13}$/', $number)) {
        return 'American Express';
    }
    
    // Discover: Starts with 6011, 65, 644-649, 622126-622925, length 16
    if (preg_match('/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10,11})$/', $number)) {
        return 'Discover';
    }
    
    // JCB: Starts with 3528-3589, 2131, 1800, length 16
    if (preg_match('/^(?:352[8-9][0-9]{11}|35[0-9]{14}|2131[0-9]{11}|1800[0-9]{11})$/', $number)) {
        return 'JCB';
    }
    
    // Diners Club: Starts with 300-305, 309, 36, 38-39, length 14
    if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11,12}$/', $number)) {
        return 'Diners Club';
    }
    
    return 'Other';
}

/**
 * Validate card number using Luhn algorithm (MOD 10)
 * 
 * @param string $cardNumber The credit card number
 * @return bool True if valid according to Luhn algorithm
 */
function luhnCheck($cardNumber) {
    $number = preg_replace('/\D/', '', $cardNumber);
    $sum = 0;
    $alternate = false;
    
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alternate) {
            $n *= 2;
            if ($n > 9) {
                $n = ($n % 10) + 1;
            }
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
    // Sanitize and retrieve form data
    $cardNumberRaw = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $nameOnCard = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $expiryDate = isset($_POST['expiry']) ? trim($_POST['expiry']) : '';
    $securityCode = isset($_POST['cvv']) ? trim($_POST['cvv']) : '';
    
    // Validate that all fields are filled
    if (empty($cardNumberRaw) || empty($nameOnCard) || empty($expiryDate) || empty($securityCode)) {
        $errorMessage = 'Please fill in all fields.';
    } else {
        // Detect card type using PHP
        $detectedType = detectCardType($cardNumberRaw);
        $luhnValid = luhnCheck($cardNumberRaw);
        
        // Create masked version of card number (show last 4 digits only)
        $cleanNumber = preg_replace('/\D/', '', $cardNumberRaw);
        $maskedCard = '';
        if (strlen($cleanNumber) >= 4) {
            $last4 = substr($cleanNumber, -4);
            $maskedCard = '•••• •••• •••• ' . $last4;
        } else {
            $maskedCard = '•••• •••• •••• ••••';
        }
        
        $displayName = htmlspecialchars($nameOnCard);
        $displayExpiry = htmlspecialchars($expiryDate);
        $displayCVV = str_repeat('•', strlen($securityCode));
        $submitted = true;
    }
}

// ==================== HELPER FUNCTIONS FOR CARD BRAND STYLING ====================

function getCardBrandIcon($type) {
    $icons = [
        'Visa' => '<span class="brand-icon visa-icon">💳 Visa</span>',
        'Mastercard' => '<span class="brand-icon mastercard-icon">💳 Mastercard</span>',
        'American Express' => '<span class="brand-icon amex-icon">💳 Amex</span>',
        'Discover' => '<span class="brand-icon discover-icon">💳 Discover</span>',
        'JCB' => '<span class="brand-icon jcb-icon">💳 JCB</span>',
        'Diners Club' => '<span class="brand-icon diners-icon">💳 Diners</span>',
        'Other' => '<span class="brand-icon other-icon">💳 Card</span>'
    ];
    return $icons[$type] ?? $icons['Other'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Secure Card Payment | Visa & Card Type Detection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Poppins', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Main Card Container */
        .payment-wrapper {
            max-width: 580px;
            width: 100%;
            margin: 20px auto;
        }

        .credit-card-form {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 32px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            backdrop-filter: blur(2px);
            transition: transform 0.2s ease;
        }

        .credit-card-form:hover {
            transform: translateY(-4px);
        }

        /* Virtual Card Preview */
        .card-preview {
            background: linear-gradient(145deg, #1e2a3a, #0f1722);
            padding: 28px 24px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-preview::before {
            content: "💳";
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 48px;
            opacity: 0.15;
            pointer-events: none;
        }

        .card-brand-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 28px;
        }

        .preview-card-number {
            font-size: 1.3rem;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            margin-bottom: 24px;
            word-break: break-word;
            background: rgba(0,0,0,0.3);
            padding: 10px 12px;
            border-radius: 12px;
            display: inline-block;
            width: 100%;
        }

        .preview-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .preview-name, .preview-expiry {
            background: rgba(255,255,255,0.08);
            padding: 6px 12px;
            border-radius: 20px;
        }

        /* Form Sections */
        .form-section {
            padding: 28px 28px 32px;
        }

        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #5b6e8c;
            margin-bottom: 8px;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px;
            font-size: 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            transition: all 0.2s;
            background: #fefefe;
            font-family: inherit;
        }

        .input-group input:focus {
            outline: none;
            border-color: #2c7da0;
            box-shadow: 0 0 0 3px rgba(44, 125, 160, 0.2);
        }

        /* Row for expiry and CVV */
        .row-2cols {
            display: flex;
            gap: 18px;
        }

        .row-2cols .input-group {
            flex: 1;
        }

        /* Card type indicator */
        .card-type-indicator {
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 6px 12px;
            background: #f1f5f9;
            border-radius: 60px;
            width: fit-content;
        }

        .brand-icon {
            font-weight: 600;
            background: white;
            padding: 4px 12px;
            border-radius: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-size: 0.8rem;
        }

        .visa-icon { color: #1a1f71; border-left: 3px solid #1a1f71; }
        .mastercard-icon { color: #cc0000; border-left: 3px solid #cc0000; }
        .amex-icon { color: #006fcf; border-left: 3px solid #006fcf; }
        .other-icon { color: #2d3748; border-left: 3px solid #94a3b8; }

        /* Receive button */
        .receive-btn {
            width: 100%;
            background: linear-gradient(95deg, #0f2b3d, #1b4f6e);
            border: none;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: white;
            border-radius: 44px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 12px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.2);
        }

        .receive-btn:hover {
            background: linear-gradient(95deg, #1b4f6e, #0f2b3d);
            transform: scale(0.98);
        }

        /* Result panel (server response) */
        .result-panel {
            margin-top: 24px;
            background: #eef2ff;
            border-radius: 24px;
            padding: 18px 22px;
            border-left: 6px solid #2c7da0;
            font-size: 0.9rem;
        }

        .detection-badge {
            background: #0f2b3d;
            color: white;
            padding: 5px 12px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            margin: 8px 0;
        }

        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 14px;
            border-radius: 20px;
            margin-top: 20px;
            font-weight: 500;
        }

        .disclaimer {
            text-align: center;
            font-size: 0.7rem;
            color: #5b6e8c;
            margin-top: 20px;
            background: rgba(0,0,0,0.03);
            padding: 10px;
            border-radius: 50px;
        }

        /* Responsive */
        @media (max-width: 500px) {
            .form-section { padding: 20px; }
            .preview-card-number { font-size: 1rem; }
        }
    </style>
</head>
<body>

<div class="payment-wrapper">
    <div class="credit-card-form">
        <!-- Virtual Card Display Area (matches visual reference) -->
        <div class="card-preview">
            <div class="card-brand-badge" id="liveCardBrandLabel">💳 CARD TYPE</div>
            <div class="preview-card-number" id="liveCardNumberPreview">XXXX XXXX XXXX XXXX</div>
            <div class="preview-details">
                <span class="preview-name" id="liveCardHolderPreview">YOUR NAME</span>
                <span class="preview-expiry" id="liveExpiryPreview">MM/YY</span>
            </div>
        </div>

        <!-- Payment Form -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="cardPaymentForm">
            <div class="form-section">
                <!-- Card Number Field with real-time detection -->
                <div class="input-group">
                    <label>Card number</label>
                    <input type="text" name="card_number" id="cardNumber" 
                           placeholder="1234 5678 9012 3456" 
                           autocomplete="off"
                           value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>"
                           required>
                    <div class="card-type-indicator" id="cardTypeIndicator">
                        <span id="detectedCardTypeText">🔍 Enter card number</span>
                    </div>
                </div>

                <!-- Name on card -->
                <div class="input-group">
                    <label>Name on card</label>
                    <input type="text" name="card_name" id="cardName" 
                           placeholder="JOHN DOE" 
                           value="<?php echo isset($_POST['card_name']) ? htmlspecialchars($_POST['card_name']) : ''; ?>"
                           required>
                </div>

                <!-- Expiry & CVV row -->
                <div class="row-2cols">
                    <div class="input-group">
                        <label>Expiration date</label>
                        <input type="text" name="expiry" id="expiryDate" 
                               placeholder="MM / YY" 
                               value="<?php echo isset($_POST['expiry']) ? htmlspecialchars($_POST['expiry']) : ''; ?>"
                               required>
                    </div>
                    <div class="input-group">
                        <label>Security code (CVV)</label>
                        <input type="text" name="cvv" id="cvvCode" 
                               placeholder="123" maxlength="4"
                               value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>"
                               required>
                    </div>
                </div>

                <button type="submit" class="receive-btn">✨ Receive Now ✨</button>
                <div class="disclaimer">
                    🔒 Demo mode — Card detection (Visa / Mastercard / Amex / etc.)<br>
                    No real transactions processed.
                </div>
            </div>
        </form>
    </div>

    <!-- Server-side detection & submission result -->
    <?php if ($submitted && empty($errorMessage)): ?>
    <div class="result-panel">
        <strong>✅ Payment simulation received</strong><br>
        <span>📇 <strong>Card type (PHP detection):</strong> 
            <span class="detection-badge"><?php echo htmlspecialchars($detectedType); ?></span>
        </span><br>
        <?php if ($detectedType === 'Visa'): ?>
            <span>💙 <strong>VISA card detected</strong> — Standard Visa network</span><br>
        <?php elseif ($detectedType !== 'Other' && $detectedType !== ''): ?>
            <span>🃏 <strong>Other card network detected:</strong> <?php echo htmlspecialchars($detectedType); ?></span><br>
        <?php elseif ($detectedType === 'Other'): ?>
            <span>⚠️ <strong>Other card type</strong> — Not recognized as Visa/Mastercard/Amex etc.</span><br>
        <?php endif; ?>
        <span>🔢 <strong>Luhn validity:</strong> <?php echo $luhnValid ? '✅ Valid card number format' : '❌ Invalid checksum (not a real card number)'; ?></span><br>
        <span>💳 <strong>Masked card:</strong> <?php echo $maskedCard; ?></span><br>
        <span>👤 <strong>Cardholder:</strong> <?php echo $displayName; ?></span><br>
        <span>📅 <strong>Expiry:</strong> <?php echo $displayExpiry; ?></span><br>
        <span>🔐 <strong>CVV:</strong> <?php echo $displayCVV; ?></span>
    </div>
    <?php elseif ($submitted && $errorMessage): ?>
    <div class="result-panel error-message">
        ❌ <?php echo htmlspecialchars($errorMessage); ?>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript: Real-time Card Detection & UI Updates (Visa / other cards) -->
<script>
    (function() {
        // ---------- Card Type Detection Logic (mirrors PHP) ----------
        function detectCardTypeJS(cardNumber) {
            let num = cardNumber.replace(/\D/g, '');
            if (num.length === 0) return 'Other';
            
            // Visa
            if (/^4[0-9]{12}(?:[0-9]{3})?$/.test(num)) return 'Visa';
            // Mastercard (improved range)
            if (/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$/.test(num)) return 'Mastercard';
            // American Express
            if (/^3[47][0-9]{13}$/.test(num)) return 'American Express';
            // Discover
            if (/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10,11})$/.test(num)) return 'Discover';
            // JCB
            if (/^(?:352[8-9][0-9]{11}|35[0-9]{14}|2131[0-9]{11}|1800[0-9]{11})$/.test(num)) return 'JCB';
            // Diners Club
            if (/^3(?:0[0-5]|[68][0-9])[0-9]{11,12}$/.test(num)) return 'Diners Club';
            
            return 'Other';
        }

        // Helper: Format card number with spaces every 4 digits
        function formatCardNumber(value) {
            let digits = value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < digits.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += digits[i];
            }
            return formatted.trim();
        }

        // Update live preview and detection UI
        function updateCardUI() {
            const cardInput = document.getElementById('cardNumber');
            const nameInput = document.getElementById('cardName');
            const expiryInput = document.getElementById('expiryDate');
            const cvvInput = document.getElementById('cvvCode');
            
            let rawNumber = cardInput.value;
            let cleanNumber = rawNumber.replace(/\D/g, '');
            
            // Format the card number display in input field (nice formatting)
            if (rawNumber !== formatCardNumber(rawNumber)) {
                let cursorPos = cardInput.selectionStart;
                let formatted = formatCardNumber(rawNumber);
                cardInput.value = formatted;
                // try to restore cursor position roughly
                let newPos = cursorPos + (formatted.length - rawNumber.length);
                cardInput.setSelectionRange(newPos, newPos);
            }
            
            // Card type detection based on cleaned number
            let detected = detectCardTypeJS(rawNumber);
            const typeTextSpan = document.getElementById('detectedCardTypeText');
            const liveBrandSpan = document.getElementById('liveCardBrandLabel');
            
            // Update indicator & badge
            if (detected === 'Visa') {
                typeTextSpan.innerHTML = '💙 Visa card detected';
                liveBrandSpan.innerHTML = '💳 VISA';
            } else if (detected === 'Mastercard') {
                typeTextSpan.innerHTML = '🧡 Mastercard detected';
                liveBrandSpan.innerHTML = '💳 Mastercard';
            } else if (detected === 'American Express') {
                typeTextSpan.innerHTML = '🔷 American Express detected';
                liveBrandSpan.innerHTML = '💳 AMEX';
            } else if (detected === 'Discover') {
                typeTextSpan.innerHTML = '🏦 Discover card';
                liveBrandSpan.innerHTML = '💳 Discover';
            } else if (detected === 'Other') {
                if (cleanNumber.length > 0) {
                    typeTextSpan.innerHTML = '🃏 Other card type (not Visa)';
                    liveBrandSpan.innerHTML = '💳 OTHER CARD';
                } else {
                    typeTextSpan.innerHTML = '🔍 Enter card number';
                    liveBrandSpan.innerHTML = '💳 CARD TYPE';
                }
            } else {
                typeTextSpan.innerHTML = `✨ ${detected} detected`;
                liveBrandSpan.innerHTML = `💳 ${detected}`;
            }
            
            // update live preview of card number (masked preview)
            const previewNumberSpan = document.getElementById('liveCardNumberPreview');
            if (cleanNumber.length > 0) {
                let masked = '';
                if (cleanNumber.length <= 4) {
                    masked = cleanNumber;
                } else {
                    let last4 = cleanNumber.slice(-4);
                    let hiddenLength = cleanNumber.length - 4;
                    let maskedPart = '•'.repeat(Math.min(hiddenLength, 12));
                    masked = maskedPart + last4;
                    // insert spaces for readability
                    let spacedMasked = '';
                    for (let i = 0; i < masked.length; i++) {
                        if (i > 0 && i % 4 === 0) spacedMasked += ' ';
                        spacedMasked += masked[i];
                    }
                    masked = spacedMasked;
                }
                previewNumberSpan.textContent = masked || 'XXXX XXXX XXXX XXXX';
            } else {
                previewNumberSpan.textContent = 'XXXX XXXX XXXX XXXX';
            }
            
            // Update name preview
            const namePreviewSpan = document.getElementById('liveCardHolderPreview');
            let holderName = nameInput.value.trim();
            if (holderName === "") {
                namePreviewSpan.textContent = "YOUR NAME";
            } else {
                namePreviewSpan.textContent = holderName.toUpperCase().substring(0, 25);
            }
            
            // Update expiry preview
            const expiryPreviewSpan = document.getElementById('liveExpiryPreview');
            let expiryRaw = expiryInput.value.replace(/\s/g, '');
            if (expiryRaw.length >= 2) {
                let formattedExpiry = expiryRaw;
                if (expiryRaw.length >= 2 && !expiryRaw.includes('/')) {
                    formattedExpiry = expiryRaw.slice(0,2) + (expiryRaw.length > 2 ? '/' + expiryRaw.slice(2,4) : '');
                }
                expiryPreviewSpan.textContent = formattedExpiry.slice(0,5);
            } else {
                expiryPreviewSpan.textContent = "MM/YY";
            }
            
            // Auto format expiry field: adds slash after 2 digits
            if (expiryInput.value.length === 2 && !expiryInput.value.includes('/')) {
                expiryInput.value = expiryInput.value + '/';
            }
        }
        
        // Attach event listeners for all relevant inputs
        const cardNumberField = document.getElementById('cardNumber');
        const nameField = document.getElementById('cardName');
        const expiryField = document.getElementById('expiryDate');
        const cvvField = document.getElementById('cvvCode');
        
        const inputs = [cardNumberField, nameField, expiryField, cvvField];
        inputs.forEach(input => {
            if (input) {
                input.addEventListener('input', updateCardUI);
                input.addEventListener('keyup', updateCardUI);
            }
        });
        
        // Special handling for expiry field manual slash
        expiryField.addEventListener('input', function(e) {
            let val = expiryField.value.replace(/\D/g, '');
            if (val.length >= 2) {
                let month = val.slice(0,2);
                let year = val.slice(2,4);
                if (year.length > 0) {
                    expiryField.value = month + '/' + year;
                } else {
                    expiryField.value = month;
                }
            } else {
                expiryField.value = val;
            }
            updateCardUI();
        });
        
        // Card number input validation: limit to max 19 digits + 4 spaces = 23 chars
        cardNumberField.addEventListener('keydown', function(e) {
            let currentVal = cardNumberField.value.replace(/\s/g, '');
            if (currentVal.length >= 19 && !/Backspace|Delete|Arrow|Tab/.test(e.key) && !e.ctrlKey && !e.metaKey && !/[0-9]/.test(e.key) === false) {
                if (/[0-9]/.test(e.key)) e.preventDefault();
            }
        });
        
        // CVV maxlength 4 digits only numeric
        cvvField.addEventListener('input', function(e) {
            cvvField.value = cvvField.value.replace(/\D/g, '').slice(0,4);
            updateCardUI();
        });
        
        // Initial update on page load
        updateCardUI();
        
        // Real-time detection on load even if prefilled
        window.addEventListener('DOMContentLoaded', function() {
            updateCardUI();
        });
    })();
</script>
</body>
</html>
