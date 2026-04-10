<?php
session_start();
// Include class database
require_once __DIR__ . '/../koneksi.php';

// Buat instance database
$db = new database();

if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header('Location: ../autentikasi/forget-password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['verified_email'];
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);
    
    // Validasi
    if (strlen($newPassword) < 8) {
        $_SESSION['password_error'] = 'Password minimal 8 karakter.';
        header('Location: reset-password.php');
        exit();
    }
    
    if (!preg_match('/[A-Z]/', $newPassword)) {
        $_SESSION['password_error'] = 'Password harus mengandung huruf besar.';
        header('Location: reset-password.php');
        exit();
    }
    
    if (!preg_match('/[a-z]/', $newPassword)) {
        $_SESSION['password_error'] = 'Password harus mengandung huruf kecil.';
        header('Location: reset-password.php');
        exit();
    }
    
    if (!preg_match('/\d/', $newPassword)) {
        $_SESSION['password_error'] = 'Password harus mengandung angka.';
        header('Location: reset-password.php');
        exit();
    }
    
    if (!preg_match('/[@$!%*?&]/', $newPassword)) {
        $_SESSION['password_error'] = 'Password harus mengandung karakter khusus (@$!%*?&).';
        header('Location: reset-password.php');
        exit();
    }
    
    if ($newPassword !== $confirmPassword) {
        $_SESSION['password_error'] = 'Password tidak cocok.';
        header('Location: reset-password.php');
        exit();
    }
    
    // ⚠️ PERUBAHAN: SIMPAN PASSWORD PLAIN TEXT ⚠️
    // Tidak menggunakan password_hash(), langsung simpan password asli
    $plainPassword = mysqli_real_escape_string($db->koneksi, $newPassword);
    $email = mysqli_real_escape_string($db->koneksi, $email);
    
    // Update password di database (plain text)
    $update_query = "UPDATE users SET password = '$plainPassword' WHERE email = '$email'";
    
    if (!mysqli_query($db->koneksi, $update_query)) {
        $_SESSION['password_error'] = 'Gagal memperbarui password: ' . mysqli_error($db->koneksi);
        header('Location: reset-password.php');
        exit();
    }
    
    // Hapus OTP record
    $delete_query = "DELETE FROM password_reset_otp WHERE email = '$email'";
    mysqli_query($db->koneksi, $delete_query);
    
    // Hapus semua session terkait reset password
    $session_vars = ['reset_email', 'otp_expires', 'otp_verified', 'verified_email', 'password_error', 'email_debug', 'debug_otp'];
    foreach ($session_vars as $var) {
        if (isset($_SESSION[$var])) {
            unset($_SESSION[$var]);
        }
    }
    
    // Buat session baru dengan pesan sukses
    session_destroy();
    session_start();
    $_SESSION['reset_success'] = 'Password berhasil diperbarui! Silakan login dengan password baru.';
    
    // Debug: tampilkan password yang disimpan
    error_log("Password reset untuk $email: $newPassword (plain text)");
    
    // Redirect ke login page
    header('Location: ../autentikasi/sign-in.php?reset=success');
    exit();
    
} else {
    // Jika bukan POST request
    header('Location: reset-password.php');
    exit();
}

// Tutup koneksi
$db->close();
?>