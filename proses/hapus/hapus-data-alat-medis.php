<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// Validasi akses
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: ../../dashboard.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];
$jabatan_user = '';

if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = $db->koneksi->query($query_staff);
    if ($result_staff && $result_staff->num_rows > 0) {
        $staff_data = $result_staff->fetch_assoc();
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

if ($jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data alat medis.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES HAPUS ALAT MEDIS
if (isset($_GET['hapus'])) {
    $id_alat = $_GET['hapus'];
    
    // Ambil data alat sebelum dihapus untuk log
    $query_alat = "SELECT kode_alat, nama_alat FROM data_alat_medis WHERE id_alat = '$id_alat'";
    $result_alat = $db->koneksi->query($query_alat);
    $alat_data = $result_alat ? $result_alat->fetch_assoc() : null;
    
    if (!$alat_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data alat medis tidak ditemukan.';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    $db->beginTransaction();
    
    try {
        $query = "DELETE FROM data_alat_medis WHERE id_alat = '$id_alat'";
        
        if ($db->koneksi->query($query)) {
            $db->commit();
            
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Alat Medis';
            $keterangan = "Alat Medis ID '$id_alat' (Kode: {$alat_data['kode_alat']}, Nama: {$alat_data['nama_alat']}) berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data alat medis berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus alat medis: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataalatmedis.php");
    exit();
}
?>