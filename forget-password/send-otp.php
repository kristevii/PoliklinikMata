<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ==============================
// INCLUDE CLASS DATABASE
// ==============================
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/mail.php';

// Buat instance database
$db = new database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bersihkan input email
    $email = mysqli_real_escape_string($db->koneksi, trim($_POST['email']));
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reset_message'] = 'Format email tidak valid.';
        $_SESSION['reset_message_type'] = 'error';
        header('Location: ../autentikasi/forget-password.php');
        exit();
    }
    
    try {
        // 1. Cek apakah email terdaftar di database
        $query = "SELECT id_user, nama FROM users WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($db->koneksi, $query);
        
        if (!$result) {
            throw new Exception('Database query error: ' . mysqli_error($db->koneksi));
        }
        
        if (mysqli_num_rows($result) == 0) {
            $_SESSION['reset_message'] = 'Email tidak terdaftar dalam sistem.';
            $_SESSION['reset_message_type'] = 'error';
            header('Location: ../autentikasi/forget-password.php');
            exit();
        }
        
        $user = mysqli_fetch_assoc($result);
        
        // 2. Hapus OTP lama yang belum terverifikasi
        $delete_query = "DELETE FROM password_reset_otp WHERE email = '$email' AND verified = 0";
        mysqli_query($db->koneksi, $delete_query);
        
        // Generate OTP 6 digit
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 menit dari sekarang
        
        // 3. Simpan OTP baru ke database
        $insert_query = "INSERT INTO password_reset_otp (email, otp_code, expires_at, created_at) 
                         VALUES ('$email', '$otp', '$expiresAt', NOW())";
        
        if (!mysqli_query($db->koneksi, $insert_query)) {
            throw new Exception('Gagal menyimpan OTP: ' . mysqli_error($db->koneksi));
        }
        
        // 4. Simpan email di session
        $_SESSION['reset_email'] = $email;
        $_SESSION['otp_expires'] = $expiresAt;
        
        // 5. Kirim email dengan OTP
        $subject = "Kode OTP Reset Password - Sistem Informasi Poliklinik Mata Eyethica";
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                .header { background: #0077b6; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; }
                .otp-code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    letter-spacing: 10px; 
                    color: #0077b6; 
                    text-align: center; 
                    margin: 20px 0; 
                    padding: 15px; 
                    background: #f0f8ff; 
                    border-radius: 8px; 
                    border: 2px dashed #0077b6;
                }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px; color: #856404; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Kode OTP Reset Password</h2>
                </div>
                <div class="content">
                    <p>Halo <strong>' . htmlspecialchars($user['nama']) . '</strong>,</p>
                    <p>Berikut adalah kode OTP untuk mereset password akun Eyethica Clinic Anda:</p>
                    
                    <div class="otp-code">' . $otp . '</div>
                    
                    <p>Kode ini berlaku selama <strong>10 menit</strong>.</p>
                    <p>Masukkan kode ini pada halaman verifikasi untuk melanjutkan proses reset password.</p>
                    
                    <div class="warning">
                        <p><strong>⚠️ PERINGATAN:</strong> Jangan bagikan kode ini kepada siapapun.</p>
                    </div>
                </div>
                <div class="footer">
                    <p>Email ini dikirim otomatis. © ' . date('Y') . ' Eyethica Eye Clinic.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Kirim email
        if (sendEmail($email, $subject, $body)) {
            $_SESSION['reset_message'] = 'Kode OTP telah dikirim. Silakan cek email Anda.';
            $_SESSION['reset_message_type'] = 'success';
            header('Location: verify-otp.php');
            exit();
        } else {
            $_SESSION['reset_message'] = 'Gagal mengirim email. Silakan coba lagi.';
            $_SESSION['reset_message_type'] = 'error';
            header('Location: ../autentikasi/forget-password.php');
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['reset_message'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        $_SESSION['reset_message_type'] = 'error';
        header('Location: ../autentikasi/forget-password.php');
        exit();
    }
    
} else {
    // Jika bukan POST request, redirect
    header('Location: ../autentikasi/forget-password.php');
    exit();
}

// Tutup koneksi
$db->close();
?>