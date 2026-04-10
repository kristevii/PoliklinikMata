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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data obat.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES HAPUS OBAT
if (isset($_GET['hapus'])) {
    $id_obat = $_GET['hapus'];
    
    // Ambil data obat sebelum dihapus untuk log
    $query_obat = "SELECT kode_obat, nama_obat FROM data_obat WHERE id_obat = '$id_obat'";
    $result_obat = $db->koneksi->query($query_obat);
    $obat_data = $result_obat ? $result_obat->fetch_assoc() : null;
    
    if (!$obat_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data obat tidak ditemukan.';
        header("Location: ../../dataobat.php");
        exit();
    }
    
    $db->beginTransaction();
    
    try {
        $query = "DELETE FROM data_obat WHERE id_obat = '$id_obat'";
        
        if ($db->koneksi->query($query)) {
            $db->commit();
            
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Obat';
            $keterangan = "Obat ID '$id_obat' (Kode: {$obat_data['kode_obat']}, Nama: {$obat_data['nama_obat']}) berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data obat berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus obat: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataobat.php");
    exit();
}
?>