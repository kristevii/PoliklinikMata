<?php
session_start();
require_once "../../koneksi.php";
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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data staff. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES HAPUS STAFF - DIMODIFIKASI
if (isset($_GET['hapus'])) {
    $id_user = $_GET['hapus'];
    $staff_data = $db->get_staff_by_id($id_user);
    $foto_to_delete = $staff_data['foto_staff'] ?? null;

    // Coba hapus dari kedua tabel
    $success = true;
    $error_message = '';
    
    // 1. Hapus dari tabel staff
    if (!$db->hapus_data_staff($id_user)) {
        $success = false;
        $error_message = 'Gagal menghapus dari tabel staff.';
    }
    
    // 2. Hapus dari tabel users (tambahkan method ini di class database)
    if ($success && !$db->hapus_data_user($id_user)) {
        $success = false;
        $error_message = 'Gagal menghapus dari tabel users.';
    }
    
    if ($success) {
        // Hapus foto
        if ($foto_to_delete && file_exists('image-staff/' . $foto_to_delete)) {
            unlink('image-staff/' . $foto_to_delete);
        }

        // Log aktivitas
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Staff';
        $keterangan = "Staff '{$staff_data['nama_staff']}' berhasil dihapus oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data staff berhasil dihapus.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = $error_message ?: 'Gagal menghapus data staff.';
    }
    
    header("Location: ../../staff.php");
    exit();
}
?>