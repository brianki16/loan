<?php
// Empty PHP - no backend logic
// This file is primarily HTML with PHP tags only for file extension
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Session Conflict | EcoCash Loan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .warning-container {
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .warning-card {
            background: #fff;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transition: transform 0.3s ease;
        }

        .warning-card:hover {
            transform: translateY(-5px);
        }

        .warning-header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            padding: 30px 24px;
            text-align: center;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.9;
            }
        }

        .warning-icon svg {
            width: 48px;
            height: 48px;
            fill: white;
        }

        .warning-header h1 {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .warning-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }

        .warning-body {
            padding: 32px 28px;
            background: #fff;
        }

        .conflict-message {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .conflict-message .oops {
            font-size: 1.5rem;
            font-weight: 800;
            color: #991b1b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .conflict-message .oops::before {
            content: "⚠️";
            font-size: 1.5rem;
        }

        .conflict-message .description {
            color: #7f1d1d;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .details-list {
            background: #f9fafb;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 28px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 32px;
            height: 32px;
            background: #fee2e2;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .detail-text {
            flex: 1;
        }

        .detail-text .label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-text .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
        }

        .warning-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 60px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            width: 100%;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
            box-shadow: 0 4px 14px 0 rgba(220, 38, 38, 0.4);
        }

        .btn-logout:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(220, 38, 38, 0.5);
        }

        .btn-reapply {
            background: #10b981;
            color: white;
            box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.4);
        }

        .btn-reapply:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.5);
        }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 0.7rem;
            color: #9ca3af;
        }

        .blink-warning {
            animation: blinkWarning 1s ease-in-out 3;
        }

        @keyframes blinkWarning {
            0%, 100% { background-color: #fef2f2; }
            50% { background-color: #fee2e2; }
        }

        @media (max-width: 480px) {
            .warning-body {
                padding: 24px 20px;
            }
            .warning-header h1 {
                font-size: 1.4rem;
            }
            .warning-icon {
                width: 60px;
                height: 60px;
            }
            .warning-icon svg {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="warning-container">
        <div class="warning-card">
            <div class="warning-header">
                <div class="warning-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </div>
                <h1>Session Conflict Detected!</h1>
                <p>Action Required Immediately</p>
            </div>
            <div class="warning-body">
                <div class="conflict-message blink-warning" id="conflictMessage">
                    <div class="oops">OOPS!!</div>
                    <div class="description">
                        Conflicting sessions detected from your EcoCash app. Please logout app and reapply for a loan.Thanks
                    </div>
                </div>

               
                
                    <div class="detail-item">
                        <div class="detail-icon">⏱️</div>
                        <div class="detail-text">
                            <div class="label">Session Timeout</div>
                            <div class="value">Previous session didn't close properly</div>
                        </div>
                    </div>
                </div>

                <div class="warning-actions">
                    <a href="index.php" class="btn btn-reapply">
                        🔄 Reapply for Loan
                    </a>
                   
                </div>

                <div class="footer-note">
                    <span>⚠️ For security reasons, please reapply or logout to continue</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animate the warning message border
        const msgDiv = document.getElementById('conflictMessage');
        setInterval(() => {
            if (msgDiv) {
                msgDiv.style.borderLeftColor = '#ef4444';
                setTimeout(() => {
                    if (msgDiv) msgDiv.style.borderLeftColor = '#dc2626';
                }, 300);
            }
        }, 2000);
    </script>
</body>
</html>
