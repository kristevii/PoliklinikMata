<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php"; // Pastikan path ke file koneksi.php benar
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support yang bisa mengakses halaman ini
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: ../../dashboard.php");
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

// Cek hak akses: Staff dengan jabatan IT Support
if ($jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data jadwal dokter. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES HAPUS JADWAL
if (isset($_GET['hapus'])) {
    $id_jadwal = $_GET['hapus'];
    $jadwal_data = $db->get_jadwal_by_id($id_jadwal);
    
    // Debug
    error_log("DEBUG: Memulai proses hapus jadwal ID: " . $id_jadwal);
    
    if (!$jadwal_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data jadwal tidak ditemukan.';
        header("Location: ../../datajadwaldokter.php");
        exit();
    }

    // Mulai transaksi
    $db->beginTransaction();
    
    try {
        // Hapus data jadwal
        error_log("DEBUG: Menghapus data jadwal ID: " . $id_jadwal);
        if (!$db->hapus_data_jadwal_dokter($id_jadwal)) {
            throw new Exception("Gagal menghapus data jadwal");
        }
        
        $db->commit();
        error_log("DEBUG: Transaksi commit berhasil");
        
        // Log aktivitas
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Jadwal Dokter';
        $keterangan = "Jadwal ID '{$jadwal_data['id_jadwal']}' berhasil dihapus oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data jadwal berhasil dihapus.';
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("ERROR: Rollback karena: " . $e->getMessage());
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data jadwal: ' . $e->getMessage();
    }
    
    header("Location: ../../datajadwaldokter.php");
    exit();
}
?>