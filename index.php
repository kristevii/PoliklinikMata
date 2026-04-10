<?php
session_start();
include "koneksi.php";
$db = new database();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'Dokter' || $_SESSION['role'] == 'Staff') {
        header("Location: dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Poliklinik Mata Eyethica</title>
    <!-- [Favicon] -->
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <!-- [Font] -->
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <!-- [Tabler Icons] -->
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
    <!-- [Feather Icons] -->
    <link rel="stylesheet" href="assets/fonts/feather.css">
    <!-- [Font Awesome Icons] -->
    <link rel="stylesheet" href="assets/fonts/fontawesome.css">
    <!-- [Material Icons] -->
    <link rel="stylesheet" href="assets/fonts/material.css">
    <!-- [Template CSS] -->
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link">
    <link rel="stylesheet" href="assets/css/style-preset.css">

    <style>
        :root {
            --primary-blue: #0077b6;
            --dark-blue: #023e8a;
            --light-blue: #caf0f8;
            --bg-gradient: linear-gradient(135deg, #023e8a 0%, #0077b6 60%, #00b4d8 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        /* ===== BACKGROUND ===== */
        .landing-bg {
            position: fixed;
            inset: 0;
            background: var(--bg-gradient);
            z-index: 0;
        }

        /* Decorative circles */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .bg-circle.c1 { width: 600px; height: 600px; top: -150px; right: -150px; }
        .bg-circle.c2 { width: 400px; height: 400px; bottom: -100px; left: -100px; }
        .bg-circle.c3 { width: 200px; height: 200px; top: 40%; left: 15%; }

        /* ===== NAVBAR ===== */
        .navbar {
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 60px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .nav-logo-icon {
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .nav-brand-text {
            font-size: 20px;
            font-weight: 800;
            color: white;
            letter-spacing: 1.5px;
        }

        .nav-brand-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            letter-spacing: 0.5px;
            font-weight: 400;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: rgba(255,255,255,0.75);
        }

        .nav-contact-btn {
            padding: 8px 18px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.3s;
            background: rgba(255,255,255,0.08);
        }

        .nav-contact-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        /* ===== HERO SECTION ===== */
        .hero-section {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            height: calc(100vh - 90px);
            padding: 0 20px;
        }

        /* Badge */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50px;
            padding: 7px 18px;
            color: white;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 28px;
            animation: fadeSlideDown 0.6s ease forwards;
        }

        .hero-badge i {
            font-size: 14px;
            color: #caf0f8;
        }

        /* Heading */
        .hero-title {
            font-size: clamp(36px, 5vw, 60px);
            font-weight: 900;
            color: white;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 20px;
            animation: fadeSlideDown 0.7s ease forwards;
        }

        .hero-title span {
            color: #caf0f8;
        }

        /* Subtitle */
        .hero-subtitle {
            font-size: clamp(15px, 2vw, 18px);
            color: rgba(255,255,255,0.8);
            max-width: 520px;
            line-height: 1.7;
            margin-bottom: 44px;
            animation: fadeSlideDown 0.8s ease forwards;
        }

        /* CTA Buttons */
        .hero-cta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            animation: fadeSlideDown 0.9s ease forwards;
        }

        .btn-primary-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 36px;
            background: white;
            color: var(--primary-blue);
            font-size: 15px;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .btn-primary-cta:hover {
            background: #f0faff;
            color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }

        .btn-primary-cta i {
            font-size: 16px;
        }

        .btn-secondary-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 36px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            color: white;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.35);
            transition: all 0.3s ease;
        }

        .btn-secondary-cta:hover {
            background: rgba(255,255,255,0.22);
            color: white;
            transform: translateY(-2px);
        }

        /* Stats row */
        .hero-stats {
            display: flex;
            gap: 50px;
            margin-top: 64px;
            animation: fadeSlideUp 1s ease forwards;
        }

        .stat-item {
            text-align: center;
            color: white;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            display: block;
        }

        .stat-label {
            font-size: 12px;
            color: rgba(255,255,255,0.65);
            margin-top: 4px;
            display: block;
        }

        .stat-divider {
            width: 1px;
            background: rgba(255,255,255,0.2);
            height: 40px;
            align-self: center;
        }

        /* ===== FOOTER ===== */
        .landing-footer {
            position: absolute;
            bottom: 24px;
            left: 0; right: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .navbar {
                padding: 20px 24px;
            }

            .nav-right span {
                display: none;
            }

            .hero-stats {
                gap: 28px;
            }

            .stat-number {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .hero-stats {
                display: none;
            }

            .btn-primary-cta, .btn-secondary-cta {
                padding: 13px 28px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<!-- Background -->
<div class="landing-bg">
    <div class="bg-circle c1"></div>
    <div class="bg-circle c2"></div>
    <div class="bg-circle c3"></div>
</div>

<!-- Hero -->
<section class="hero-section">

    <div class="hero-badge">
        <i class="fas fa-shield-halved"></i>
        Portal Manajemen Klinik — Sistem Terintegrasi
    </div>

    <h1 class="hero-title">
        Selamat Datang di<br>
        <span>Poliklinik Mata Eyethica</span>
    </h1>

    <p class="hero-subtitle">
        Sistem informasi poliklinik mata terpadu untuk dokter dan staf. 
        Kelola pasien, jadwal, dan rekam medis dengan mudah dan aman.
    </p>

    <div class="hero-cta">
        <a href="autentikasi/sign-in.php" class="btn-primary-cta">
            <i class="fas fa-right-to-bracket"></i>
            Masuk ke Portal
        </a>
        <a href="mailto:support@eyethica.id" class="btn-secondary-cta">
            <i class="fas fa-headset"></i>
            Hubungi Support
        </a>
    </div>

    <div class="hero-stats">
        <div class="stat-item">
            <span class="stat-number">100%</span>
            <span class="stat-label">Data Aman & Terenkripsi</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-number">24/7</span>
            <span class="stat-label">Akses Sistem</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-number">2+</span>
            <span class="stat-label">Role Pengguna</span>
        </div>
    </div>

</section>

<!-- Footer -->
<footer class="landing-footer">
    <span>© 2026 Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</span>
    <span>·</span>
    <span><i class="fas fa-shield-halved"></i> Secure System</span>
</footer>

</body>
</html>