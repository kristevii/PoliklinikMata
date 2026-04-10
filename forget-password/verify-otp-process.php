<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Include class database
require_once __DIR__ . '/../koneksi.php';

// Buat instance database
$db = new database();

if (!isset($_SESSION['reset_email'])) {
    header('Location: ../autentikasi/forget-password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['reset_email'];
    $otp_input = isset($_POST['full_otp']) ? trim($_POST['full_otp']) : '';
    
    // Validasi panjang OTP
    if (strlen($otp_input) !== 6) {
        $_SESSION['otp_error'] = 'Kode OTP harus 6 digit.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Validasi hanya angka
    if (!ctype_digit($otp_input)) {
        $_SESSION['otp_error'] = 'Kode OTP hanya boleh berisi angka.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Escape input untuk keamanan
    $otp_input = mysqli_real_escape_string($db->koneksi, $otp_input);
    $email = mysqli_real_escape_string($db->koneksi, $email);
    
    // Cek OTP di database
    $query = "SELECT id, attempts, expires_at, verified 
              FROM password_reset_otp 
              WHERE email = '$email' 
              AND otp_code = '$otp_input' 
              LIMIT 1";
    
    $result = mysqli_query($db->koneksi, $query);
    
    if (!$result) {
        $_SESSION['otp_error'] = 'Terjadi kesalahan database.';
        header('Location: verify-otp.php');
        exit();
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Update attempts count untuk OTP yang masih valid
        $update_query = "UPDATE password_reset_otp 
                        SET attempts = attempts + 1 
                        WHERE email = '$email' 
                        AND expires_at > NOW()";
        mysqli_query($db->koneksi, $update_query);
        
        $_SESSION['otp_error'] = 'Kode OTP tidak valid.';
        header('Location: verify-otp.php');
        exit();
    }
    
    $otp_record = mysqli_fetch_assoc($result);
    
    // Cek apakah OTP sudah digunakan
    if ($otp_record['verified'] == 1) {
        $_SESSION['otp_error'] = 'Kode OTP sudah digunakan.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Cek apakah OTP sudah expired
    $expires = strtotime($otp_record['expires_at']);
    $now = time();
    if ($expires < $now) {
        $_SESSION['otp_error'] = 'Kode OTP sudah kadaluarsa.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Cek jumlah percobaan
    if ($otp_record['attempts'] >= 3) {
        $_SESSION['otp_error'] = 'Terlalu banyak percobaan. Silakan request OTP baru.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Tandai OTP sebagai terverifikasi
    $update_query = "UPDATE password_reset_otp 
                    SET verified = 1 
                    WHERE email = '$email' 
                    AND otp_code = '$otp_input'";
    
    if (!mysqli_query($db->koneksi, $update_query)) {
        $_SESSION['otp_error'] = 'Gagal memverifikasi OTP.';
        header('Location: verify-otp.php');
        exit();
    }
    
    // Set session untuk reset password
    $_SESSION['otp_verified'] = true;
    $_SESSION['verified_email'] = $email;
    
    // Redirect ke halaman reset password
    header('Location: reset-password.php');
    exit();
    
} else {
    // Jika bukan POST request
    header('Location: verify-otp.php');
    exit();
}

// Tutup koneksi
$db->close();
?>