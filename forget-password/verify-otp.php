<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Update redirect path
if (!isset($_SESSION['reset_email'])) {
    header('Location: ../../forget-password.php');
    exit();
}

// Cek apakah OTP sudah expired
if (isset($_SESSION['otp_expires']) && strtotime($_SESSION['otp_expires']) < time()) {
    session_destroy();
    $_SESSION['reset_message'] = 'Kode OTP telah kadaluarsa. Silakan request ulang.';
    $_SESSION['reset_message_type'] = 'error';
    header('Location: ../../forget-password.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - Sistem Informasi Poliklinik Mata Eyethica</title>
    <link rel="icon" href="../assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" >
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" >
    
    <style>
        :root {
            --primary-blue: #0077b6;
            --dark-blue: #023e8a;
            --light-blue: #caf0f8;
            --bg-gradient: linear-gradient(135deg, #023e8a, #0077b6); 
            --text-gray: #666;
            --border-gray: #e0e0e0;
            --success-green: #28a745;
            --error-red: #dc3545;
            --warning-yellow: #ffc107;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f4f7f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-container {
            display: flex;
            flex-direction: row-reverse; 
            width: 100%;
            height: 100vh;
            background: #fff;
            overflow: hidden;
        }

        /* --- SISI BIRU (VISUAL - KIRI) --- */
        .visual-side {
            flex: 1.2;
            background: var(--bg-gradient);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
            position: relative;
        }

        .app-logo-white {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .visual-title h2 { color: white; font-size: 32px; letter-spacing: 2px; font-weight: 800; }
        .visual-title p { opacity: 0.9; font-size: 18px; margin-top: 10px; line-height: 1.4; }

        .bg-icon-watermark {
            font-size: 150px;
            font-weight: 900;
            opacity: 0.1;
            position: absolute;
            left: 60px;
            bottom: 120px;
        }

        /* --- SISI PUTIH (FORM - KANAN) --- */
        .content-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 40px 80px;
            position: relative;
            background: white;
        }

        .top-nav {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 30px;
        }

        .brand-name {
            font-weight: 700;
            color: var(--dark-blue);
            margin-right: 12px;
        }

        .dot-logo {
            width: 35px;
            height: 35px;
            background-color: var(--primary-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }

        .form-content {
            max-width: 400px;
            margin: auto;
            width: 100%;
        }

        .header-icon {
            text-align: center;
            margin-bottom: 25px;
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #333;
            text-align: center;
        }

        .subtitle {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 30px;
            line-height: 1.6;
            text-align: center;
        }

        /* Email Display */
        .email-display {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 25px;
            border: 1px solid #cce7ff;
            font-size: 14px;
        }
        
        .email-display strong {
            color: var(--dark-blue);
        }

        /* OTP Input Styling */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid var(--border-gray);
            border-radius: 8px;
            outline: none;
            transition: all 0.3s;
            background: #fff;
        }
        
        .otp-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }
        
        .otp-input.filled {
            border-color: var(--primary-blue);
            background-color: #f0f8ff;
        }

        /* Timer */
        .timer {
            text-align: center;
            margin: 15px 0 25px 0;
            font-size: 14px;
            color: var(--text-gray);
        }
        
        .timer .count {
            font-weight: bold;
            color: var(--error-red);
        }

        /* Button Styling */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: var(--dark-blue);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Resend Link */
        .resend-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-gray);
        }
        
        .resend-link a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .resend-link .countdown {
            color: var(--text-gray);
            font-weight: bold;
        }

        /* Back Link */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .bottom-info {
            position: absolute;
            bottom: 30px;
            left: 80px;
            right: 80px;
            display: flex;
            justify-content: center;
            font-size: 12px;
            color: #aaa;
        }

        /* Message Styles */
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .message-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: var(--success-green);
        }

        .message-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: var(--error-red);
        }

        .message-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: var(--warning-yellow);
        }

        .message i {
            font-size: 16px;
        }

        @media (max-width: 900px) {
            .main-container { flex-direction: column; }
            .visual-side { display: none; }
            .content-side { padding: 40px 30px; }
            .bottom-info { left: 30px; right: 30px; }
        }

        @media (max-width: 480px) {
            .otp-inputs { gap: 5px; }
            .otp-input { 
                width: 40px; 
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="content-side">
        <div class="top-nav">
            <span class="brand-name">Poliklinik Mata Eyethica</span>
            <div class="dot-logo"><i class="fas fa-eye"></i></div>
        </div>

        <div class="form-content">
            <div class="header-icon">
                <i class="fas fa-shield-alt" style="color: var(--primary-blue); font-size: 4rem;"></i>
            </div>
            
            <h1>Verifikasi Kode OTP</h1>
            <p class="subtitle">
                Masukkan 6-digit kode yang dikirim ke email Anda untuk melanjutkan proses reset password.
            </p>

            <?php if (isset($_SESSION['otp_error'])): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['otp_error']); ?></span>
                </div>
                <?php unset($_SESSION['otp_error']); ?>
            <?php endif; ?>

            <form action="verify-otp-process.php" method="POST" id="otpForm">
                <div class="otp-inputs">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" 
                               name="otp[]" 
                               class="otp-input" 
                               maxlength="1" 
                               data-index="<?php echo $i; ?>"
                               oninput="moveToNext(this)"
                               onkeydown="handleBackspace(event, this)"
                               autocomplete="off">
                    <?php endfor; ?>
                    <input type="hidden" name="full_otp" id="fullOtp">
                </div>
                
                <div class="timer" id="timer">
                    Kode berlaku: <span class="count" id="countdown">10:00</span>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-check-circle"></i> Verifikasi Kode
                </button>
            </form>

            <div class="resend-link">
                <span id="resendText">Tidak menerima kode? </span>
                <a href="resend-otp.php" id="resendLink" style="display: none;">Kirim ulang kode</a>
                <span id="resendCountdown" class="countdown">(60 detik)</span>
            </div>

            <a href="../autentikasi/forget-password.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke halaman sebelumnya
            </a>
        </div>

        <div class="bottom-info">
            <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica. Pusat Pemulihan Akun.</span>
        </div>
    </div>

    <div class="visual-side">
        <div>
            <div class="app-logo-white">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="visual-title">
                <h2>VERIFIKASI OTP</h2>
                <p>Pastikan kode OTP yang Anda masukkan<br>sesuai dengan yang dikirim ke email.</p>
            </div>
        </div>

        <div class="bg-icon-watermark">
            <i class="fas fa-sms"></i>
        </div>

        <div style="z-index: 1;">
            <p style="font-size: 14px; opacity: 0.7; max-width: 300px;">
                Kode OTP hanya berlaku selama 10 menit untuk menjaga keamanan akun Anda.
            </p>
        </div>
    </div>
</div>

<script>
    // Auto-focus input pertama
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.otp-input').focus();
        
        // Start countdown timer
        startCountdown(600); // 10 menit dalam detik
        startResendCountdown(60); // 60 detik untuk resend

        // Auto-hide error message setelah 2 detik
        var msg = document.querySelector('.message');
        if (msg) {
            setTimeout(function() {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-8px)';
                setTimeout(function() {
                    msg.style.visibility = 'hidden';
                    msg.style.height = '0';
                    msg.style.margin = '0';
                    msg.style.padding = '0';
                    msg.style.overflow = 'hidden';
                }, 500);
            }, 2000);
        }
    });
    
    // Fungsi untuk pindah ke input berikutnya
    function moveToNext(input) {
        const value = input.value;
        const index = parseInt(input.dataset.index);
        
        // Hanya terima angka
        if (!/^\d$/.test(value)) {
            input.value = '';
            return;
        }
        
        // Update input dengan angka yang valid
        input.value = value;
        input.classList.add('filled');
        
        // Pindah ke input berikutnya
        if (index < 6) {
            const nextInput = document.querySelector(`.otp-input[data-index="${index + 1}"]`);
            nextInput.focus();
        }
        
        // Update hidden input dengan OTP lengkap
        updateFullOtp();
    }
    
    // Fungsi untuk handle backspace
    function handleBackspace(event, input) {
        const index = parseInt(input.dataset.index);
        
        if (event.key === 'Backspace' && input.value === '' && index > 1) {
            const prevInput = document.querySelector(`.otp-input[data-index="${index - 1}"]`);
            prevInput.focus();
            prevInput.value = '';
            prevInput.classList.remove('filled');
            updateFullOtp();
        }
    }
    
    // Update hidden input dengan OTP lengkap
    function updateFullOtp() {
        const otpInputs = document.querySelectorAll('.otp-input');
        let fullOtp = '';
        
        otpInputs.forEach(input => {
            fullOtp += input.value;
        });
        
        document.getElementById('fullOtp').value = fullOtp;
        
        // Enable/disable submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = fullOtp.length !== 6;
        
        // Auto submit jika semua input terisi
        if (fullOtp.length === 6) {
            setTimeout(() => {
                document.getElementById('otpForm').submit();
            }, 500);
        }
    }
    
    // Timer countdown untuk OTP
    function startCountdown(seconds) {
        const timerElement = document.getElementById('countdown');
        const interval = setInterval(() => {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            
            timerElement.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
            
            if (seconds <= 0) {
                clearInterval(interval);
                timerElement.textContent = 'Kadaluarsa!';
                timerElement.style.color = '#dc3545';
                
                // Disable submit button
                document.getElementById('submitBtn').disabled = true;
            }
            
            seconds--;
        }, 1000);
    }
    
    // Timer untuk resend OTP
    function startResendCountdown(seconds) {
        const resendLink = document.getElementById('resendLink');
        const resendText = document.getElementById('resendText');
        const resendCountdown = document.getElementById('resendCountdown');
        
        const interval = setInterval(() => {
            resendCountdown.textContent = `(${seconds} detik)`;
            
            if (seconds <= 0) {
                clearInterval(interval);
                resendLink.style.display = 'inline';
                resendCountdown.style.display = 'none';
                resendText.textContent = 'Tidak menerima kode? ';
            }
            
            seconds--;
        }, 1000);
    }
</script>

</body>
</html>