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

// PROSES TAMBAH OBAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_obat'])) {
    $kode_obat = trim($_POST['kode_obat'] ?? '');
    $nama_obat = trim($_POST['nama_obat'] ?? '');
    $jenis_obat = trim($_POST['jenis_obat'] ?? '');
    $satuan = trim($_POST['satuan'] ?? '');
    $stok = (int)($_POST['stok'] ?? 0);
    $harga = (int)($_POST['harga'] ?? 0);
    $expired_date = !empty($_POST['expired_date']) ? $_POST['expired_date'] : null;
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    // Validasi data
    if (empty($kode_obat) || empty($nama_obat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Kode obat dan Nama obat wajib diisi!';
        header("Location: ../../dataobat.php");
        exit();
    }
    
    // Validasi format kode obat (harus OBT + 3 digit angka)
    if (!preg_match('/^OBT\d{3}$/', $kode_obat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Format kode obat tidak valid! Format yang benar: OBT001, OBT002, dst.';
        header("Location: ../../dataobat.php");
        exit();
    }
    
    // Validasi stok dan harga minimal 0
    if ($stok < 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Stok tidak boleh negatif!';
        header("Location: ../../dataobat.php");
        exit();
    }
    
    if ($harga < 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Harga tidak boleh negatif!';
        header("Location: ../../dataobat.php");
        exit();
    }
    
    // Cek duplikasi kode obat
    $check_query = "SELECT id_obat FROM data_obat WHERE kode_obat = '$kode_obat'";
    $check_result = $db->koneksi->query($check_query);
    if ($check_result && $check_result->num_rows > 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Kode obat '$kode_obat' sudah terdaftar!";
        header("Location: ../../dataobat.php");
        exit();
    }
    
    try {
        $expired_date_sql = $expired_date ? "'$expired_date'" : "NULL";
        
        $query = "INSERT INTO data_obat (kode_obat, nama_obat, jenis_obat, satuan, stok, harga, expired_date, deskripsi, created_at) 
                  VALUES ('$kode_obat', '$nama_obat', '$jenis_obat', '$satuan', $stok, $harga, $expired_date_sql, '$deskripsi', NOW())";
        
        if ($db->koneksi->query($query)) {
            $id_obat = $db->koneksi->insert_id;
            
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Tambah';
            $jenis = 'Obat';
            $keterangan = "Obat (ID: $id_obat, Kode: $kode_obat) berhasil ditambahkan oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data obat berhasil ditambahkan.';
        } else {
            throw new Exception("Gagal menambahkan data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah obat: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataobat.php");
    exit();
}
?>