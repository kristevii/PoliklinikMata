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

// PROSES EDIT JADWAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_jadwal'])) {
    $id_jadwal = $_POST['id_jadwal'] ?? '';
    $kode_dokter = $_POST['kode_dokter'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $shift = $_POST['shift'] ?? '';
    $status = $_POST['status'] ?? 'Aktif';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    
    // Validasi data
    if (empty($id_jadwal) || empty($kode_dokter) || empty($hari) || empty($shift) || empty($jam_mulai) || empty($jam_selesai)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Semua field wajib diisi!';
        header("Location: ../../datajadwaldokter.php");
        exit();
    }
    
    // Validasi jam mulai harus sebelum jam selesai
    if (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Jam mulai harus sebelum jam selesai!';
        header("Location: ../../datajadwaldokter.php");
        exit();
    }
    
    try {
        // Update data jadwal
        if (!$db->edit_data_jadwal_dokter($id_jadwal, $kode_dokter, $hari, $shift, $status, $jam_mulai, $jam_selesai)) {
            throw new Exception("Gagal mengupdate data jadwal");
        }
        
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Jadwal Dokter';
        $keterangan = "Jadwal ID '$id_jadwal' berhasil diupdate oleh $username_session.";
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data jadwal berhasil diupdate.';
        
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
    } catch (Exception $e) {
        $error_detail = mysqli_error($db->koneksi);
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        
        error_log("ERROR EDIT: Database error saat edit jadwal: " . $error_detail . " | Exception: " . $e->getMessage());
    }
    
    header("Location: ../../datajadwaldokter.php");
    exit();
}
?>