<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Include class database
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/mail.php';

// Buat instance database
$db = new database();

if (!isset($_SESSION['reset_email'])) {
    header('Location: ../autentikasi/forget-password.php');
    exit();
}

$email = mysqli_real_escape_string($db->koneksi, $_SESSION['reset_email']);

// Hapus OTP lama yang belum terverifikasi
$delete_query = "DELETE FROM password_reset_otp WHERE email = '$email' AND verified = 0";
mysqli_query($db->koneksi, $delete_query);

// Ambil data user
$query = "SELECT nama FROM users WHERE email = '$email' LIMIT 1";
$result = mysqli_query($db->koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['otp_error'] = 'Email tidak ditemukan.';
    header('Location: ../autentikasi/forget-password.php');
    exit();
}

$user = mysqli_fetch_assoc($result);

// Generate OTP baru
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 menit dari sekarang

// Simpan OTP baru ke database
$insert_query = "INSERT INTO password_reset_otp (email, otp_code, expires_at, created_at) 
                 VALUES ('$email', '$otp', '$expiresAt', NOW())";

if (!mysqli_query($db->koneksi, $insert_query)) {
    $_SESSION['otp_error'] = 'Gagal membuat OTP baru.';
    header('Location: verify-otp.php');
    exit();
}

// Update session
$_SESSION['otp_expires'] = $expiresAt;

// Kirim email OTP baru
$subject = "Kode OTP Baru - Sistem Informasi Poliklinik Mata Eyethica";
$body = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .otp-code { font-size: 32px; font-weight: bold; color: #0077b6; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h2 style='color: #0077b6;'>Kode OTP Baru</h2>
        <p>Halo " . htmlspecialchars($user['nama']) . ",</p>
        <p>Berikut adalah kode OTP baru Anda:</p>
        <div class='otp-code'>" . $otp . "</div>
        <p>Kode ini berlaku 10 menit.</p>
        <p>Gunakan kode ini untuk melanjutkan proses reset password.</p>
    </div>
</body>
</html>";

// Kirim email
sendEmail($email, $subject, $body);

$_SESSION['reset_message'] = 'Kode OTP baru telah dikirim.';
$_SESSION['reset_message_type'] = 'success';

header('Location: verify-otp.php');
exit();

// Tutup koneksi
$db->close();
?>