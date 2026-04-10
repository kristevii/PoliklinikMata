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

// PROSES TAMBAH RESEP OBAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_resep'])) {
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $tanggal_resep = trim($_POST['tanggal_resep'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Validasi data
    if (empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Rekam medis wajib dipilih!';
        header("Location: ../../dataresepobat.php");
        exit();
    }
    
    if (empty($tanggal_resep)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Tanggal resep wajib diisi!';
        header("Location: ../../dataresepobat.php");
        exit();
    }
    
    // Konversi datetime-local ke format MySQL (Y-m-d H:i:s)
    $tanggal_resep_formatted = date('Y-m-d H:i:s', strtotime($tanggal_resep));
    
    // Cek apakah id_rekam valid
    $check_query = "SELECT id_rekam FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Rekam medis tidak valid!";
        header("Location: ../../dataresepobat.php");
        exit();
    }
    
    try {
        $catatan_sql = !empty($catatan) ? "'" . mysqli_real_escape_string($db->koneksi, $catatan) . "'" : "NULL";
        
        $query = "INSERT INTO data_resep_obat (id_rekam, tanggal_resep, catatan, created_at) 
                  VALUES ('$id_rekam', '$tanggal_resep_formatted', $catatan_sql, NOW())";
        
        if ($db->koneksi->query($query)) {
            $id_resep = $db->koneksi->insert_id;
            
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Tambah';
            $jenis = 'Resep Obat';
            $keterangan = "Resep Obat (ID: $id_resep) berhasil ditambahkan oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep obat berhasil ditambahkan.';
        } else {
            throw new Exception("Gagal menambahkan data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah resep obat: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepobat.php");
    exit();
}
?>