<?php
session_start();
include "../koneksi.php";
$db = new database();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    // Redirect sesuai role
    if ($_SESSION['role'] == 'Dokter') {
        header("Location: ../dashboard.php");
    } elseif ($_SESSION['role'] == 'Staff') {
        header("Location: ../dashboard.php");
    }
    exit;
}

// Proses login jika ada data POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Lakukan validasi login
    $users = $db->login($username, $password);
    
    if ($users) {
        $_SESSION['id_user'] = $users['id_user'];
        $_SESSION['nama'] = $users['nama'];
        $_SESSION['username'] = $users['username'];
        $_SESSION['role'] = $users['role'];
        
        // CATAT AKTIVITAS LOGIN
        $id_user = $users['id_user'];
        $nama_user = $users['nama'];
        $role_user = $users['role'];
        
        // Format keterangan sesuai dengan data sebelumnya
        $keterangan = "Pengguna $nama_user berhasil login.";
        
        // Insert ke tabel aktivitas_profile
        $query_aktivitas = "INSERT INTO aktivitas_profile (id_user, jenis, entitas, keterangan, waktu) 
                           VALUES ('$id_user', 'Login', 'User', '$keterangan', NOW())";
        $db->koneksi->query($query_aktivitas);
        
        // Redirect sesuai role
        if ($users['role'] == 'Dokter') {
            header("Location: ../dashboard.php");
        } elseif ($users['role'] == 'Staff') {
            header("Location: ../dashboard.php");
        }
        exit;
    } else {
        $error = "Login gagal! Username atau password salah.";
    }
}

// Tampilkan pesan sukses reset password jika ada
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    if (isset($_SESSION['reset_success'])) {
        $success_message = $_SESSION['reset_success'];
        unset($_SESSION['reset_success']);
    } else {
        $success_message = 'Password berhasil diperbarui! Silakan login dengan password baru.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Eyethica Klinik</title>
    <!-- [Favicon] icon -->
    <link rel="icon" href="../assets/images/faviconeyethica.png" type="image/x-icon"> <!-- [Font] Family -->
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" >
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="../assets/fonts/feather.css" >
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" >
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="../assets/fonts/material.css" >
    <!-- [Template CSS Files] -->
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="../assets/css/style-preset.css" >

<script src="../assets/js/plugins/apexcharts.min.js"></script>
    
    <style>
        :root {
            --primary-blue: #0077b6;
            --dark-blue: #023e8a;
            --light-blue: #caf0f8;
            --bg-gradient: linear-gradient(135deg, #023e8a, #0077b6); 
            --text-gray: #666;
            --border-gray: #e0e0e0;
            --error-red: #dc3545;
            --success-green: #28a745;
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
            overflow: hidden;
        }

        .main-container {
            display: flex;
            width: 100%;
            height: 100vh;
            background: #fff;
            overflow: hidden;
        }

        /* Modal Notification */
        .notification-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            min-width: 380px;
            max-width: 90%;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .notification-modal.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .notification-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-header.success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .notification-icon {
            font-size: 24px;
        }

        .notification-title {
            font-size: 18px;
            font-weight: 600;
        }

        .notification-body {
            padding: 24px;
            text-align: center;
        }

        .notification-message {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .countdown-timer {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .notification-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: background 0.3s;
        }

        .notification-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            width: 100%;
            position: absolute;
            bottom: 0;
            left: 0;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: white;
            animation: countdown 5s linear forwards;
        }

        @keyframes countdown {
            from {
                transform: translateX(0%);
            }
            to {
                transform: translateX(-100%);
            }
        }

        /* --- SISI KIRI (FORM) --- */
        .login-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 40px 80px;
            position: relative;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 60px;
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

        .register-prompt {
            font-size: 14px;
            color: var(--text-gray);
        }

        .register-btn {
            text-decoration: none;
            color: var(--primary-blue);
            font-weight: 600;
            border: 1px solid var(--primary-blue);
            padding: 8px 16px;
            border-radius: 8px;
            margin-left: 10px;
            transition: 0.3s;
        }

        .register-btn:hover {
            background: var(--primary-blue);
            color: white;
        }

        .form-content {
            max-width: 400px;
            margin: auto;
            width: 100%;
            text-align: center;
        }

        .avatar-placeholder {
            width: 80px;
            height: 80px;
            background: var(--light-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--primary-blue);
            font-size: 30px;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #333;
        }

        .subtitle {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 10px;
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

        .input-wrapper input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            outline: none;
            transition: 0.3s;
        }

        .input-wrapper input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 13px;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
            position: relative;
        }

        .login-btn:hover {
            background: var(--dark-blue);
        }

        .login-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        .bottom-info {
            position: absolute;
            bottom: 30px;
            left: 80px;
            right: 80px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-gray);
        }

        /* --- SISI KANAN (VISUAL) --- */
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
        .visual-title p { color: white; opacity: 0.9; font-size: 18px; margin-top: 10px; line-height: 1.4; }

        .contact-info {
            display: flex;
            gap: 50px;
            z-index: 1;
        }

        .info-box h4 { color: white; margin-bottom: 10px; font-size: 16px; font-weight: 600; }
        .info-box p, .info-box a { 
            font-size: 14px; 
            color: rgba(255,255,255,0.8); 
            text-decoration: none; 
            line-height: 1.6;
        }

        /* Responsive untuk Mobile */
        @media (max-width: 768px) {
            .main-container {
                display: block;
                height: 100vh;
                overflow-y: auto;
            }
            
            .visual-side {
                display: none;
            }
            
            .login-side {
                width: 100%;
                height: 100vh;
                padding: 30px 20px;
                justify-content: center;
            }
            
            .top-nav {
                margin-bottom: 40px;
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
            }
            
            .form-content {
                max-width: 100%;
                padding: 0 10px;
                margin-top: 90px;
            }
            
            .notification-modal {
                min-width: 90%;
                max-width: 320px;
            }
            
            .bottom-info {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 40px;
                padding: 0 10px;
                flex-direction: column;
                align-items: center;
                gap: 10px;
                text-align: center;
            }
            
            .form-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .subtitle {
                font-size: 13px;
                margin-bottom: 25px;
            }
            
            .avatar-placeholder {
                width: 70px;
                height: 70px;
                font-size: 25px;
                display: none;
            }
            
            .input-wrapper input {
                padding: 14px 15px 14px 45px;
                font-size: 16px;
            }
            
            .login-btn {
                padding: 16px;
                font-size: 16px;
            }
        }
        
        /* Responsive untuk Tablet */
        @media (min-width: 769px) and (max-width: 900px) {
            .visual-side { display: none; }
            .login-side { 
                padding: 40px 60px;
                width: 100%;
            }
            .form-content {
                max-width: 400px;
            }
        }
    </style>
</head>
<body>

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- Notification Modal -->
<div class="notification-modal" id="notificationModal">
    <button class="notification-close" id="closeModal">
        <i class="fas fa-times"></i>
    </button>
    <div class="notification-header" id="modalHeader">
        <i class="fas fa-circle-exclamation notification-icon"></i>
        <span class="notification-title">Login Gagal</span>
        <div class="progress-bar"></div>
    </div>
    <div class="notification-body">
        <p class="notification-message" id="modalMessage"></p>
        <div class="countdown-timer">
            Modal akan tertutup dalam <span id="countdownTimer">5</span> detik
        </div>
    </div>
</div>

<div class="main-container">
    <div class="login-side">
        <div class="top-nav">
            <div class="dot-logo"><i class="fas fa-eye"></i></div>
            <div class="register-prompt">
                Belum memiliki akun? <a href="#" class="register-btn">Hubungi Admin</a>
            </div>
        </div>

        <div class="form-content">
            <div class="avatar-placeholder">
                <i class="fas fa-user-md"></i>
            </div>
            <h1>Portal Staff Eyethica</h1>
            <p class="subtitle">Silakan masukkan kredensial Anda untuk akses sistem.</p>

            <form action="sign-in.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label>Username*</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="Masukkan username Anda" 
                               required autofocus
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password*</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Masukkan password" required>
                        <i class="fas fa-eye toggle-password" 
                           style="left: auto; right: 15px; cursor: pointer;"></i>
                    </div>
                </div>

                <div class="form-footer">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    </label>
                    <a href="forget-password.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 500;">Lupa password?</a>
                </div>

                <button type="submit" class="login-btn" id="loginButton">
                    <span id="buttonText">Masuk ke Dashboard</span>
                    <div class="loading-spinner" id="loadingSpinner"></div>
                </button>
            </form>
        </div>

        <div class="bottom-info">
            <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</span>
            <span><i class="fas fa-shield-halved"></i> Secure Access</span>
        </div>
    </div>

    <div class="visual-side">
        <div>
            <div class="app-logo-white">
                <i class="fas fa-eye"></i>
            </div>
            <div class="visual-title">
                <h2>Poliklinik Mata Eyethica</h2>
                <p>Memberikan solusi kesehatan mata <br> <span >terbaik dan terpercaya.</span></p>
            </div>
        </div>

        <div class="contact-info">
            <div class="info-box">
                <h4>Butuh Bantuan?</h4>
                <p>Hubungi IT Support di <a href="mailto:support@eyethica.id" style="color: white; text-decoration: underline;">support@eyethica.id</a></p>
            </div>
            <div class="info-box">
                <h4>Layanan Darurat</h4>
                <p>Untuk kendala akses mendesak,<br> hubungi ekstensi 404</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    document.querySelector('.toggle-password').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this;
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Modal functionality
    const notificationModal = document.getElementById('notificationModal');
    const modalOverlay = document.getElementById('modalOverlay');
    const closeModalBtn = document.getElementById('closeModal');
    const modalMessage = document.getElementById('modalMessage');
    const modalHeader = document.getElementById('modalHeader');
    const countdownTimer = document.getElementById('countdownTimer');

    let countdownInterval;

    function showNotification(message, type = 'error') {
        // Set message
        modalMessage.textContent = message;
        
        // Set style based on type
        if (type === 'success') {
            modalHeader.classList.add('success');
            modalHeader.querySelector('.notification-icon').className = 'fas fa-circle-check notification-icon';
            modalHeader.querySelector('.notification-title').textContent = 'Berhasil';
        } else {
            modalHeader.classList.remove('success');
            modalHeader.querySelector('.notification-icon').className = 'fas fa-circle-exclamation notification-icon';
            modalHeader.querySelector('.notification-title').textContent = 'Login Gagal';
        }
        
        // Reset countdown
        let seconds = 5;
        countdownTimer.textContent = seconds;
        
        // Show modal
        notificationModal.classList.add('active');
        modalOverlay.classList.add('active');
        
        // Start countdown
        clearInterval(countdownInterval);
        countdownInterval = setInterval(() => {
            seconds--;
            countdownTimer.textContent = seconds;
            
            if (seconds <= 0) {
                closeNotification();
            }
        }, 1000);
    }

    function closeNotification() {
        clearInterval(countdownInterval);
        notificationModal.classList.remove('active');
        modalOverlay.classList.remove('active');
    }

    // Close modal on overlay click
    modalOverlay.addEventListener('click', closeNotification);
    closeModalBtn.addEventListener('click', closeNotification);

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeNotification();
        }
    });

    // Form submission loading state
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const button = document.getElementById('loginButton');
        const buttonText = document.getElementById('buttonText');
        const spinner = document.getElementById('loadingSpinner');
        
        // Show loading state
        button.disabled = true;
        buttonText.textContent = 'Memproses...';
        spinner.style.display = 'block';
        
        // Simulate network delay for demo (remove in production)
        setTimeout(() => {
            button.disabled = false;
            buttonText.textContent = 'Masuk ke Dashboard';
            spinner.style.display = 'none';
        }, 2000);
    });

    // Remember username from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const savedUsername = localStorage.getItem('eyethica_username');
        const rememberCheckbox = document.querySelector('input[name="remember"]');
        
        if (savedUsername) {
            document.getElementById('username').value = savedUsername;
            if (rememberCheckbox) rememberCheckbox.checked = true;
        }
        
        // Save username if remember me is checked
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', function() {
                const username = document.getElementById('username').value;
                if (this.checked && username) {
                    localStorage.setItem('eyethica_username', username);
                } else {
                    localStorage.removeItem('eyethica_username');
                }
            });
        }
        
        // Check if there's an error message from PHP
        <?php if (!empty($error)): ?>
            setTimeout(() => {
                showNotification('<?= htmlspecialchars($error, ENT_QUOTES) ?>', 'error');
            }, 500);
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            setTimeout(() => {
                showNotification('<?= htmlspecialchars($success, ENT_QUOTES) ?>', 'success');
            }, 500);
        <?php endif; ?>
    });
</script>

</body>
</html>