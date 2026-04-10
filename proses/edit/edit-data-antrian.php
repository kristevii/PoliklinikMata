<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// Validasi akses
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    $_SESSION['notif_status'] = 'error';
    $_SESSION['notif_message'] = 'Anda belum login!';
    header("Location: ../../unautorized.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];
$jabatan_user = '';

// Ambil data jabatan user jika role adalah Staff
if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = mysqli_query($db->koneksi, $query_staff);
    
    if ($result_staff && mysqli_num_rows($result_staff) > 0) {
        $staff_data = mysqli_fetch_assoc($result_staff);
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

// Cek hak akses
if ($role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Refaksionis/Optometris' &&
    $jabatan_user != 'IT Support') {
    $_SESSION['notif_status'] = 'error';
    $_SESSION['notif_message'] = 'Anda tidak memiliki hak akses untuk mengedit data antrian.';
    header("Location: ../../dataantrian.php");
    exit();
}

// PROSES EDIT ANTRIAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_antrian'])) {
    $id_antrian = mysqli_real_escape_string($db->koneksi, $_POST['id_antrian'] ?? '');
    $nomor_antrian = mysqli_real_escape_string($db->koneksi, $_POST['nomor_antrian'] ?? '');
    $id_pasien = mysqli_real_escape_string($db->koneksi, $_POST['id_pasien'] ?? '');
    $kode_dokter = mysqli_real_escape_string($db->koneksi, $_POST['kode_dokter'] ?? '');
    $jenis_antrian = mysqli_real_escape_string($db->koneksi, $_POST['jenis_antrian'] ?? '');
    $status = mysqli_real_escape_string($db->koneksi, $_POST['status'] ?? '');
    $tanggal_antrian = $_POST['tanggal_antrian'] ?? null;
    $update_at = date('Y-m-d H:i:s');
    
    // Validasi input
    if (empty($id_antrian) || empty($nomor_antrian) || empty($id_pasien)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field nomor antrian dan pasien wajib diisi!';
        header("Location: ../../dataantrian.php");
        exit();
    }
    
    // Validasi tanggal antrian untuk jenis Kontrol
    if ($jenis_antrian == 'Kontrol' && empty($tanggal_antrian)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Tanggal kontrol wajib diisi untuk jenis antrian Kontrol!';
        header("Location: ../../dataantrian.php");
        exit();
    }
    
    // Handle empty doctor
    if ($kode_dokter === '') {
        $kode_dokter = null;
    }
    
    // Format tanggal antrian: simpan sebagai DATETIME (Y-m-d H:i:s)
    $tanggal_antrian_formatted = null;
    if ($jenis_antrian == 'Kontrol' && !empty($tanggal_antrian)) {
        // Jika tanggal_antrian sudah dalam format datetime lengkap
        if (strpos($tanggal_antrian, ':') !== false) {
            $tanggal_antrian_formatted = date('Y-m-d H:i:s', strtotime($tanggal_antrian));
        } else {
            // Jika hanya tanggal, set waktu ke 00:00:00
            $tanggal_antrian_formatted = date('Y-m-d 00:00:00', strtotime($tanggal_antrian));
        }
    }
    
    // Edit data antrian
    if ($db->edit_data_antrian($id_antrian, $nomor_antrian, $id_pasien, $kode_dokter, $status, $jenis_antrian, $tanggal_antrian_formatted, $update_at)) {
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Antrian';
        $keterangan = "Antrian '$nomor_antrian' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data antrian berhasil diupdate.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data antrian: ' . mysqli_error($db->koneksi);
    }
    
    header("Location: ../../dataantrian.php");
    exit();
} else {
    $_SESSION['notif_status'] = 'error';
    $_SESSION['notif_message'] = 'Metode request tidak valid!';
    header("Location: ../../dataantrian.php");
    exit();
}
?>