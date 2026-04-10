<?php

session_start();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - Sistem Eyethica Klinik</title>
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

        /* Styling Input */
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
            left: 70px;
            right: 70px;
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

        .countdown {
            display: inline-block;
            background: var(--primary-blue);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 5px;
        }

        @media (max-width: 900px) {
            .main-container { flex-direction: column; }
            .visual-side { display: none; }
            .content-side { padding: 40px 30px; }
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
                <i class="fas fa-key" style="color: var(--primary-blue); font-size: 4rem;"></i>
            </div>
            
            <h1>Lupa Kata Sandi?</h1>
            <p class="subtitle">
                Jangan khawatir. Masukkan alamat email yang terdaftar dan kami akan mengirimkan kode OTP untuk verifikasi.
            </p>

            <?php
            // Tampilkan pesan sukses/error jika ada
            if (isset($_SESSION['reset_message'])) {
                $messageType = $_SESSION['reset_message_type'] ?? 'error';
                $messageClass = $messageType === 'success' ? 'message-success' : 
                               ($messageType === 'warning' ? 'message-warning' : 'message-error');
                $messageIcon = $messageType === 'success' ? 'fa-check-circle' : 
                              ($messageType === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle');
                
                echo '<div class="message ' . $messageClass . '">';
                echo '<i class="fas ' . $messageIcon . '"></i>';
                echo '<span>' . htmlspecialchars($_SESSION['reset_message']) . '</span>';
                echo '</div>';
                
                unset($_SESSION['reset_message']);
                unset($_SESSION['reset_message_type']);
            }
            ?>

            <form action="../forget-password/send-otp.php" method="POST" id="resetForm">
                <div class="form-group">
                    <label>Alamat Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="nama@email.com" required
                               value="<?php echo isset($_SESSION['reset_email']) ? htmlspecialchars($_SESSION['reset_email']) : ''; ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span id="btnText">Kirim Kode OTP</span>
                    <span id="btnLoading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Mengirim...
                    </span>
                </button>
            </form>

            <a href="sign-in.php" class="back-link">
                <i class="fas fa-arrow-left"></i>  Kembali ke Halaman Login
            </a>
        </div>

        <div class="bottom-info">
            <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica. Pusat Pemulihan Akun.</span>
        </div>
    </div>

    <div class="visual-side">
        <div>
            <div class="app-logo-white">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="visual-title">
                <h2>PEMULIHAN AKUN</h2>
                <p>Kami akan membantu Anda <br>mendapatkan kembali akses ke sistem.</p>
            </div>
        </div>

        <div class="bg-icon-watermark">
            <i class="fas fa-unlock-alt"></i>
        </div>

        <div style="z-index: 1;">
            <p style="font-size: 14px; opacity: 0.7; max-width: 300px;">
                Demi alasan keamanan, kami melakukan verifikasi melalui kode OTP sebelum mengizinkan perubahan password.
            </p>
        </div>
    </div>
</div>

<script>
    // Menangani submit form dengan loading state
    document.getElementById('resetForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');
        
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
    });

    // Auto-hide message setelah 2 detik
    window.addEventListener('DOMContentLoaded', function() {
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
</script>

</body>
</html>