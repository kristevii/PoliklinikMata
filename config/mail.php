<?php
/**
 * Konfigurasi Email untuk Eyethica Clinic
 * PRODUCTION MODE: Kirim email via SMTP Gmail
 */

// Include PHPMailer
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    // ==============================
    // KONFIGURASI SMTP GMAIL
    // ==============================
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $smtp_secure = 'tls';
    
    // ⚠️ GANTI DENGAN EMAIL DAN PASSWORD ANDA ⚠️
    $smtp_username = '2024tevi@gmail.com';  // Email Gmail Anda
    $smtp_password = 'wyot buul fncr qvcs';     // App Password 16 karakter
    
    $from_email = $smtp_username;
    $from_name = 'Sistem Eyethica Klinik';
    
    // ==============================
    // INISIALISASI PHPMailer
    // ==============================
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port = $smtp_port;
        
        // Optional debugging (nonaktifkan di production)
        // $mail->SMTPDebug = 2;
        
        // Encoding
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->addReplyTo('support@eyethica.com', 'Eyethica Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));
        
        // ==============================
        // KIRIM EMAIL
        // ==============================
        $sent = $mail->send();
        
        // ==============================
        // SIMPAN LOG
        // ==============================
        saveEmailLog($to, $subject, $body, $sent, $sent ? 'Success' : $mail->ErrorInfo);
        
        // Untuk development, simpan OTP di session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $otp = extractOTP($body);
        if ($otp) {
            $_SESSION['debug_otp'] = $otp;
        }
        
        return $sent;
        
    } catch (Exception $e) {
        saveEmailLog($to, $subject, $body, false, $e->getMessage());
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Simpan log email
 */
function saveEmailLog($to, $subject, $body, $success = true, $message = '') {
    $log_dir = __DIR__ . '/email_logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $status = $success ? 'SENT' : 'ERROR';
    $filename = $log_dir . date('Y-m-d_H-i-s') . '_' . $status . '_' . uniqid() . '.html';
    
    $otp_code = extractOTP($body);
    
    $log_content = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial; padding: 20px; background: #f5f5f5; }
            .log-container { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: ' . ($success ? '#28a745' : '#dc3545') . '; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
            .otp-box { background: #e8f4fd; padding: 20px; border: 2px dashed #0077b6; border-radius: 8px; text-align: center; margin: 20px 0; }
            .otp-code { font-size: 36px; font-weight: bold; color: #d32f2f; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="log-container">
            <div class="header">
                <h2>' . ($success ? '✅ EMAIL TERKIRIM' : '❌ EMAIL GAGAL') . '</h2>
            </div>
            <div class="info">
                <strong>📨 Kepada:</strong> ' . htmlspecialchars($to) . '<br>
                <strong>📋 Subjek:</strong> ' . htmlspecialchars($subject) . '<br>
                <strong>🕐 Waktu:</strong> ' . date('Y-m-d H:i:s') . '<br>
                <strong>📁 Status:</strong> ' . htmlspecialchars($message) . '
            </div>';
    
    if ($otp_code) {
        $log_content .= '
            <div class="otp-box">
                <h3>Kode OTP:</h3>
                <div class="otp-code">' . $otp_code . '</div>
            </div>';
    }
    
    $log_content .= $body . '
        </div>
    </body>
    </html>';
    
    file_put_contents($filename, $log_content);
}

/**
 * Extract OTP dari body email
 */
function extractOTP($body) {
    if (preg_match('/>(\d{6})</', $body, $matches)) {
        return $matches[1];
    }
    return false;
}
?>