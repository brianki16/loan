<?php
session_start();

// Optional: protect page if user is not logged in
if (!isset($_SESSION['phone'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Approved | EcoCash</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(145deg, #c8e8ff 0%, #a0d2f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            padding: 20px;
        }

        /* Balloon container */
        .balloon-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
            overflow: hidden;
        }

        .balloon {
            position: absolute;
            bottom: -150px;
            width: 60px;
            height: 80px;
            background-size: contain;
            background-repeat: no-repeat;
            animation: flyUp linear forwards;
            opacity: 0.9;
        }

        /* Different balloon colors via pseudo-elements or background */
        .balloon.red { background: radial-gradient(circle at 30% 35%, #ff7e7e, #e63946); }
        .balloon.blue { background: radial-gradient(circle at 30% 35%, #7eb6ff, #1e6091); }
        .balloon.green { background: radial-gradient(circle at 30% 35%, #8effa0, #2e8b57); }
        .balloon.yellow { background: radial-gradient(circle at 30% 35%, #fff27e, #f4c542); }
        .balloon.purple { background: radial-gradient(circle at 30% 35%, #d18eff, #6a4e9b); }
        .balloon.pink { background: radial-gradient(circle at 30% 35%, #ffb3d9, #e85d9e); }

        /* Balloon string */
        .balloon::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            width: 2px;
            height: 25px;
            background: #6b4c3b;
            transform: translateX(-50%);
        }

        /* Knot */
        .balloon::before {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            width: 8px;
            height: 8px;
            background: #8b5a2b;
            border-radius: 50%;
            transform: translateX(-50%);
            z-index: 1;
        }

        @keyframes flyUp {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-120vh) rotate(15deg);
                opacity: 0;
            }
        }

        /* Main card */
        .card {
            background: rgba(255, 255, 255, 0.96);
            max-width: 550px;
            width: 100%;
            padding: 40px 30px;
            border-radius: 48px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255,255,255,0.5);
            text-align: center;
            backdrop-filter: blur(2px);
            z-index: 20;
            transition: transform 0.3s;
            animation: fadeInUp 0.7s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .checkmark {
            font-size: 80px;
            background: #2ecc71;
            width: 120px;
            height: 120px;
            line-height: 120px;
            border-radius: 60px;
            color: white;
            margin: 0 auto 25px;
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }

        h1 {
            font-size: 2.2rem;
            color: #1f6392;
            margin-bottom: 20px;
        }

        .message {
            font-size: 1.3rem;
            color: #2c3e50;
            margin: 20px 0;
            line-height: 1.5;
        }

        .note {
            background: #f9f3e0;
            padding: 15px;
            border-radius: 30px;
            font-size: 1rem;
            color: #b45f06;
            margin: 20px 0;
            display: inline-block;
        }

        .thanks {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f3b5c;
            margin-top: 10px;
        }

        .btn-home {
            display: inline-block;
            margin-top: 25px;
            background: #2563eb;
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.2s;
            border: none;
            font-size: 1rem;
        }

        .btn-home:hover {
            background: #1d4ed8;
            transform: scale(1.02);
        }

        /* Confetti particles (small addition) */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: gold;
            opacity: 0.7;
            animation: confettiFall 3s linear forwards;
            pointer-events: none;
            z-index: 15;
        }

        @keyframes confettiFall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>

<div class="balloon-container" id="balloonContainer"></div>

<div class="card">
    <div class="checkmark">✓</div>
    <h1>Loan Approved! 🎉</h1>
    <div class="message">
        Your loan has been approved.<br>
        You will receive a confirmation message within 15 minutes.
    </div>
    <div class="note">
        ⏱️ SMS / Email notification incoming
    </div>
    <div class="thanks">
        Thank you for choosing EcoCash.
    </div>
    
</div>

<script>
    // Create flying balloons
    function createBalloon() {
        const container = document.getElementById('balloonContainer');
        if (!container) return;

        const balloon = document.createElement('div');
        const colors = ['red', 'blue', 'green', 'yellow', 'purple', 'pink'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        balloon.className = `balloon ${randomColor}`;

        // Random size variation
        const scale = 0.6 + Math.random() * 0.7;
        balloon.style.width = `${60 * scale}px`;
        balloon.style.height = `${80 * scale}px`;
        balloon.style.left = `${Math.random() * 100}%`;
        balloon.style.animationDuration = `${6 + Math.random() * 5}s`;
        balloon.style.animationDelay = `${Math.random() * 2}s`;
        balloon.style.zIndex = Math.floor(Math.random() * 5);
        
        container.appendChild(balloon);
        
        // Remove after animation ends
        balloon.addEventListener('animationend', () => {
            balloon.remove();
        });
    }

    // Generate balloons continuously
    let balloonInterval = setInterval(() => {
        createBalloon();
        // Random extra balloons
        if (Math.random() > 0.6) {
            setTimeout(() => createBalloon(), 200);
        }
    }, 800);

    // Small confetti effect (optional)
    function createConfetti() {
        const body = document.body;
        const conf = document.createElement('div');
        conf.classList.add('confetti');
        const colorsConf = ['#ffd966', '#ffb347', '#ff6b6b', '#6bffb0', '#6b9eff'];
        conf.style.backgroundColor = colorsConf[Math.floor(Math.random() * colorsConf.length)];
        conf.style.left = Math.random() * window.innerWidth + 'px';
        conf.style.width = conf.style.height = (5 + Math.random() * 8) + 'px';
        conf.style.position = 'fixed';
        conf.style.top = '-20px';
        conf.style.borderRadius = '50%';
        conf.style.animationDuration = (2 + Math.random() * 3) + 's';
        body.appendChild(conf);
        
        conf.addEventListener('animationend', () => conf.remove());
    }

    // Burst of confetti on page load
    for (let i = 0; i < 60; i++) {
        setTimeout(() => createConfetti(), i * 80);
    }

    // Also generate random confetti while page is open (every 1.5 sec)
    let confettiInterval = setInterval(() => {
        if (Math.random() > 0.5) createConfetti();
    }, 1500);

    // Cleanup intervals if the page gets unloaded (optional but good practice)
    window.addEventListener('beforeunload', () => {
        clearInterval(balloonInterval);
        clearInterval(confettiInterval);
    });
</script>

</body>
</html>
