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

// PROSES EDIT RESEP OBAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resep'])) {
    $id_resep_obat = $_POST['id_resep_obat'] ?? '';
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $tanggal_resep = trim($_POST['tanggal_resep'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    
    if (empty($id_resep_obat) || empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Resep dan Rekam medis wajib diisi!';
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
    
    // Cek apakah data ada
    $check_query = "SELECT id_resep_obat FROM data_resep_obat WHERE id_resep_obat = '$id_resep_obat'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Data resep obat tidak ditemukan!";
        header("Location: ../../dataresepobat.php");
        exit();
    }
    
    try {
        $catatan_sql = !empty($catatan) ? "'" . mysqli_real_escape_string($db->koneksi, $catatan) . "'" : "NULL";
        
        $query = "UPDATE data_resep_obat SET 
                    id_rekam = '$id_rekam',
                    tanggal_resep = '$tanggal_resep_formatted',
                    catatan = $catatan_sql
                  WHERE id_resep_obat = '$id_resep_obat'";
        
        if ($db->koneksi->query($query)) {
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Edit';
            $jenis = 'Resep Obat';
            $keterangan = "Resep Obat ID '$id_resep_obat' berhasil diupdate oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep obat berhasil diupdate.';
        } else {
            throw new Exception("Gagal mengupdate data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        error_log("Error edit resep obat: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepobat.php");
    exit();
}
?>