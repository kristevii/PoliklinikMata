<?php
session_start();

// Cek apakah OTP sudah diverifikasi
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header('Location: ../../forget-password.php');
    exit();
}

$email = $_SESSION['verified_email'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Password Baru - Sistem Informasi Poliklinik Mata Eyethica</title>
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
            margin-bottom: 20px;
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
            color: var(--text-gray);
        }
        
        .email-display strong {
            color: var(--dark-blue);
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #444;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            font-size: 15px;
            transition: 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        /* Password Input Specific */
        .password-input {
            position: relative;
        }
        
        .password-input input {
            width: 100%;
            padding: 12px 40px 12px 45px;
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            font-size: 15px;
            transition: 0.3s;
        }
        
        .password-input input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }
        
        .password-input .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            z-index: 2;
        }
        
        .password-input .toggle-password:hover {
            color: var(--primary-blue);
        }

        /* Password Strength */
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-gray);
        }
        
        .strength-bar {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
            border-radius: 3px;
        }
        
        .strength-weak {
            background: var(--error-red);
            width: 25%;
        }
        
        .strength-medium {
            background: var(--warning-yellow);
            width: 50%;
        }
        
        .strength-strong {
            background: var(--success-green);
            width: 75%;
        }
        
        .strength-very-strong {
            background: var(--success-green);
            width: 100%;
        }

        /* Password Match Indicator */
        .password-match {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-match.valid {
            color: var(--success-green);
        }
        
        .password-match.invalid {
            color: var(--error-red);
        }

        /* Requirements */
        .requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 20px;
            border: 1px solid var(--border-gray);
        }
        
        .requirements p {
            font-weight: 600;
            margin-bottom: 10px;
            color: #444;
        }
        
        .requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .requirements li {
            margin-bottom: 5px;
            color: var(--text-gray);
            list-style-type: none;
            position: relative;
            padding-left: 20px;
        }
        
        .requirements li:before {
            content: '○';
            position: absolute;
            left: 0;
            color: #ccc;
        }
        
        .requirements li.valid {
            color: var(--success-green);
        }
        
        .requirements li.valid:before {
            content: '✓';
            color: var(--success-green);
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
            margin-top: 10px;
        }

        .btn-primary {
            background: var(--success-green);
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
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
            
            <h1>Buat Password Baru</h1>
            <p class="subtitle">
                Buat password baru yang kuat untuk akun Anda. Pastikan password memenuhi semua kriteria keamanan.
            </p>

            <?php if (isset($_SESSION['password_error'])): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['password_error']); ?></span>
                </div>
                <?php unset($_SESSION['password_error']); ?>
            <?php endif; ?>

            <form action="update-password.php" method="POST" id="passwordForm">
                <div class="form-group">
                    <label>Password Baru</label>
                    <div class="password-input">
                        <i class="fas fa-key"></i>
                        <input type="password" 
                               name="new_password" 
                               id="newPassword" 
                               placeholder="Masukkan password baru"
                               oninput="checkPasswordStrength()"
                               required>
                        <i class="fas fa-eye toggle-password" id="togglePassword1" onclick="togglePassword('newPassword', 'togglePassword1')"></i>
                    </div>
                    <div class="password-strength">
                        <span id="strengthText">Kekuatan password: -</span>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthBar"></div>
                        </div>
                    </div>
                    <div style="display:none;">
                        <ul>
                            <li id="reqLength"></li>
                            <li id="reqUppercase"></li>
                            <li id="reqLowercase"></li>
                            <li id="reqNumber"></li>
                            <li id="reqSpecial"></li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <div class="password-input">
                        <i class="fas fa-redo"></i>
                        <input type="password" 
                               name="confirm_password" 
                               id="confirmPassword" 
                               placeholder="Ketik ulang password"
                               oninput="checkPasswordMatch()"
                               required>
                        <i class="fas fa-eye toggle-password" id="togglePassword2" onclick="togglePassword('confirmPassword', 'togglePassword2')"></i>
                    </div>
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-save"></i> Simpan Password Baru
                </button>
            </form>

            <a href="../autentikasi/sign-in.php" class="back-link">
                <i class="fas fa-sign-in-alt"></i>  Kembali ke Login
            </a>
        </div>

        <div class="bottom-info">
            <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica. Pusat Pemulihan Akun.</span>
        </div>
    </div>

    <div class="visual-side">
        <div>
            <div class="app-logo-white">
                <i class="fas fa-lock"></i>
            </div>
            <div class="visual-title">
                <h2>KEAMANAN AKUN</h2>
                <p>Buat password yang kuat untuk<br>melindungi akun Anda.</p>
            </div>
        </div>

        <div class="bg-icon-watermark">
            <i class="fas fa-shield-alt"></i>
        </div>

        <div style="z-index: 1;">
            <p style="font-size: 14px; opacity: 0.7; max-width: 300px;">
                Gunakan kombinasi huruf, angka, dan karakter khusus untuk password yang lebih aman.
            </p>
        </div>
    </div>
</div>

<script>
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    function checkPasswordStrength() {
        const password = document.getElementById('newPassword').value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        
        let strength = 0;
        
        // Kriteria
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSpecial = /[@$!%*?&]/.test(password);
        
        // Update requirement indicators
        document.getElementById('reqLength').className = hasLength ? 'valid' : '';
        document.getElementById('reqUppercase').className = hasUppercase ? 'valid' : '';
        document.getElementById('reqLowercase').className = hasLowercase ? 'valid' : '';
        document.getElementById('reqNumber').className = hasNumbers ? 'valid' : '';
        document.getElementById('reqSpecial').className = hasSpecial ? 'valid' : '';
        
        // Hitung strength
        if (hasLength) strength += 20;
        if (hasUppercase) strength += 20;
        if (hasLowercase) strength += 20;
        if (hasNumbers) strength += 20;
        if (hasSpecial) strength += 20;
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Update strength color and text
        if (strength === 0) {
            strengthBar.className = 'strength-fill';
            strengthText.textContent = 'Kekuatan password: -';
            strengthText.style.color = 'var(--text-gray)';
        } else if (strength <= 25) {
            strengthBar.className = 'strength-fill strength-weak';
            strengthText.textContent = 'Kekuatan password: Lemah';
            strengthText.style.color = 'var(--error-red)';
        } else if (strength <= 50) {
            strengthBar.className = 'strength-fill strength-medium';
            strengthText.textContent = 'Kekuatan password: Sedang';
            strengthText.style.color = 'var(--warning-yellow)';
        } else if (strength <= 75) {
            strengthBar.className = 'strength-fill strength-strong';
            strengthText.textContent = 'Kekuatan password: Kuat';
            strengthText.style.color = 'var(--success-green)';
        } else {
            strengthBar.className = 'strength-fill strength-very-strong';
            strengthText.textContent = 'Kekuatan password: Sangat Kuat';
            strengthText.style.color = 'var(--success-green)';
        }
        
        checkPasswordMatch();
    }
    
    function checkPasswordMatch() {
        const password = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        const matchDiv = document.getElementById('passwordMatch');
        const submitBtn = document.getElementById('submitBtn');
        
        if (confirm === '') {
            matchDiv.textContent = '';
            matchDiv.className = 'password-match';
            submitBtn.disabled = true;
            return;
        }
        
        if (password === confirm) {
            matchDiv.innerHTML = '<i class="fas fa-check-circle"></i> Password cocok';
            matchDiv.className = 'password-match valid';
            
            // Enable submit button if all requirements met
            const allValid = Array.from(document.querySelectorAll('.requirements li')).every(li => li.className === 'valid');
            submitBtn.disabled = !allValid;
        } else {
            matchDiv.innerHTML = '<i class="fas fa-times-circle"></i> Password tidak cocok';
            matchDiv.className = 'password-match invalid';
            submitBtn.disabled = true;
        }
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        checkPasswordStrength();
        checkPasswordMatch();

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
        
        // Auto submit jika semua valid dan user menekan enter
        document.getElementById('passwordForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !document.getElementById('submitBtn').disabled) {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
        });
    });

</script>
</body>
</html>