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

// PROSES TAMBAH ALAT MEDIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_alat'])) {
    $kode_alat = trim($_POST['kode_alat'] ?? '');
    $nama_alat = trim($_POST['nama_alat'] ?? '');
    $jenis_alat = trim($_POST['jenis_alat'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $kondisi = $_POST['kondisi'] ?? 'Baik';
    $tanggal_beli = !empty($_POST['tanggal_beli']) ? $_POST['tanggal_beli'] : null;
    $status = $_POST['status'] ?? 'Aktif';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    // Validasi data
    if (empty($kode_alat) || empty($nama_alat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Kode alat dan Nama alat wajib diisi!';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    // Validasi format kode alat (harus ALT + 3 digit angka)
    if (!preg_match('/^ALT\d{3}$/', $kode_alat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Format kode alat tidak valid! Format yang benar: ALT001, ALT002, dst.';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    // Validasi jenis_alat (harus dari ENUM yang tersedia)
    $valid_jenis = ['Diagnostik', 'Pemeriksaan', 'Penunjang', 'Tindakan Medis', 'Operasi'];
    if (!empty($jenis_alat) && !in_array($jenis_alat, $valid_jenis)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Jenis alat tidak valid! Pilihan: Diagnostik, Pemeriksaan, Penunjang, Tindakan Medis, Operasi';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    // Validasi kondisi (harus dari ENUM yang tersedia)
    $valid_kondisi = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
    if (!in_array($kondisi, $valid_kondisi)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Kondisi tidak valid! Pilihan: Baik, Rusak Ringan, Rusak Berat';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    // Validasi status (harus dari ENUM yang tersedia)
    $valid_status = ['Aktif', 'Tidak Aktif', 'Maintenance'];
    if (!in_array($status, $valid_status)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Status tidak valid! Pilihan: Aktif, Tidak Aktif, Maintenance';
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    // Cek duplikasi kode alat
    $check_query = "SELECT id_alat FROM data_alat_medis WHERE kode_alat = '$kode_alat'";
    $check_result = $db->koneksi->query($check_query);
    if ($check_result && $check_result->num_rows > 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Kode alat '$kode_alat' sudah terdaftar!";
        header("Location: ../../dataalatmedis.php");
        exit();
    }
    
    try {
        $tanggal_beli_sql = $tanggal_beli ? "'$tanggal_beli'" : "NULL";
        
        $query = "INSERT INTO data_alat_medis (kode_alat, nama_alat, jenis_alat, lokasi, kondisi, tanggal_beli, status, deskripsi, created_at) 
                  VALUES ('$kode_alat', '$nama_alat', '$jenis_alat', '$lokasi', '$kondisi', $tanggal_beli_sql, '$status', '$deskripsi', NOW())";
        
        if ($db->koneksi->query($query)) {
            $id_alat = $db->koneksi->insert_id;
            
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Tambah';
            $jenis = 'Alat Medis';
            $keterangan = "Alat Medis (ID: $id_alat, Kode: $kode_alat) berhasil ditambahkan oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data alat medis berhasil ditambahkan.';
        } else {
            throw new Exception("Gagal menambahkan data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah alat medis: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataalatmedis.php");
    exit();
}
?>