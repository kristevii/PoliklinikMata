<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// Validasi akses: Hanya Dokter dan Staff dengan jabatan tertentu yang bisa menghapus data antrian
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
    $result_staff = $db->koneksi->query($query_staff);
    
    if ($result_staff && $result_staff->num_rows > 0) {
        $staff_data = $result_staff->fetch_assoc();
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

// Cek hak akses: Dokter atau Staff dengan jabatan tertentu
if ($role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Refaksionis/Optometris' &&
    $jabatan_user != 'IT Support') {
    $_SESSION['notif_status'] = 'error';
    $_SESSION['notif_message'] = 'Anda tidak memiliki hak akses untuk menghapus data antrian. Hanya Dokter dan Staff dengan jabatan tertentu yang diizinkan.';
    header("Location: ../../dataantrian.php");
    exit();
}

// PROSES HAPUS ANTRIAN
if (isset($_GET['hapus'])) {
    $id_antrian = $_GET['hapus'];
    
    // Validasi ID antrian
    if (empty($id_antrian)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID antrian tidak valid!';
        header("Location: ../../dataantrian.php");
        exit();
    }
    
    // Ambil data antrian untuk logging
    $antrian_data = $db->get_antrian_by_id($id_antrian);
    
    if (!$antrian_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data antrian tidak ditemukan!';
        header("Location: ../../dataantrian.php");
        exit();
    }
    
    // Hapus data antrian
    if ($db->hapus_data_antrian($id_antrian)) {
        // Log aktivitas
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Antrian';
        $keterangan = "Antrian '{$antrian_data['nomor_antrian']}' berhasil dihapus oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data antrian berhasil dihapus.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data antrian.';
    }
    
    header("Location: ../../dataantrian.php");
    exit();
} else {
    // Jika tidak ada parameter hapus, redirect ke halaman utama
    $_SESSION['notif_status'] = 'error';
    $_SESSION['notif_message'] = 'Parameter tidak valid!';
    header("Location: ../../dataantrian.php");
    exit();
}
?>