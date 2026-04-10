<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - Sistem Informasi Poliklinik Mata Eyethica</title>
    <!-- [Favicon] icon -->
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon"> <!-- [Font] Family -->
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="assets/fonts/feather.css" >
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="assets/fonts/fontawesome.css" >
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <!-- [Template CSS Files] -->
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css" >

<script src="assets/js/plugins/apexcharts.min.js"></script>
    
    <style>
        :root {
            --primary-blue: #0077b6;
            --dark-blue: #023e8a;
            --light-blue: #caf0f8;
            --bg-gradient: linear-gradient(135deg, #023e8a, #0077b6); 
            --text-gray: #666;
            --border-gray: #e0e0e0;
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
            flex-direction: row-reverse; /* Membalik urutan flex item */
            width: 100%;
            height: 100vh;
            background: #fff;
            overflow: hidden;
        }

        /* --- SISI BIRU (VISUAL - SEKARANG DI KIRI) --- */
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

        .error-code {
            font-size: 150px;
            font-weight: 900;
            opacity: 0.1;
            position: absolute;
            left: 40px;
            bottom: 100px;
        }

        /* --- SISI PUTIH (CONTENT - SEKARANG DI KANAN) --- */
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
            justify-content: flex-end; /* Logo klinik di kanan atas */
            align-items: center;
            margin-bottom: 60px;
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

        .error-content {
            max-width: 400px;
            margin: auto;
            width: 100%;
            text-align: center;
        }

        .icon-box {
            width: 80px;
            height: 80px;
            background: var(--light-blue);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--primary-blue);
            font-size: 32px;
            transform: rotate(-10deg);
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #333;
        }

        .subtitle {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 35px;
            line-height: 1.6;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 119, 182, 0.2);
        }

        .btn-primary:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .btn-light {
            background: #f8f9fa;
            color: var(--text-gray);
            border: 1px solid var(--border-gray);
        }

        .btn-light:hover {
            background: #eee;
        }

        .bottom-info {
            position: absolute;
            bottom: 30px;
            left: 80px;
            right: 80px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #aaa;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .main-container { flex-direction: column; }
            .visual-side { display: none; }
            .content-side { padding: 40px 30px; }
            .bottom-info { position: relative; bottom: 0; left: 0; right: 0; margin-top: 50px; text-align: center; flex-direction: column; gap: 10px; }
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

        <div class="error-content">
            <i class="fas fa-user-lock" style="color: var(--primary-blue); font-size: 4.5rem; margin-bottom: 20px;"></i>
            <h1>Akses Terbatas</h1>
            <p class="subtitle">
                Halaman ini memerlukan hak akses khusus. Akun Anda saat ini tidak diizinkan untuk melihat modul ini.
            </p>

            <div class="action-area">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
                <a href="javascript:history.back()" class="btn btn-light">
                    Halaman Sebelumnya
                </a>
            </div>
        </div>

        <div class="bottom-info">
            <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica.</span>
            <span>HTTP 401: Unauthorized</span>
        </div>
    </div>

    <div class="visual-side">
        <div>
            <div class="app-logo-white">
                <i class="fas fa-lock"></i>
            </div>
            <div class="visual-title">
                <h2>KEAMANAN SISTEM</h2>
                <p>Otentikasi diperlukan untuk <br>melanjutkan ke area ini.</p>
            </div>
        </div>

        <div class="error-code">401</div>

        <div style="z-index: 1;">
            <p style="font-size: 14px; opacity: 0.7; max-width: 300px;">
                Jika Anda merasa ini adalah kesalahan, silakan hubungi tim IT Poliklinik Mata Eyethica.
            </p>
        </div>
    </div>
</div>

</body>
</html>