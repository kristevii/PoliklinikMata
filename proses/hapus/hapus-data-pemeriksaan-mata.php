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

// PROSES HAPUS PEMERIKSAAN MATA
if (isset($_GET['hapus'])) {
    $id_pemeriksaaan = $_GET['hapus'];
    
    // Ambil data pemeriksaan sebelum dihapus untuk log
    $query_pemeriksaan = "SELECT pm.*, dr.no_rekam_medis, dp.nama_pasien 
                          FROM data_pemeriksaan_mata pm
                          JOIN data_rekam_medis dr ON pm.id_rekam = dr.id_rekam
                          JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                          WHERE pm.id_pemeriksaaan = '$id_pemeriksaaan'";
    $result_pemeriksaan = $db->koneksi->query($query_pemeriksaan);
    $pemeriksaan_data = $result_pemeriksaan ? $result_pemeriksaan->fetch_assoc() : null;
    
    if (!$pemeriksaan_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data pemeriksaan mata tidak ditemukan.';
        header("Location: ../../datapemeriksaanmata.php");
        exit();
    }
    
    $db->beginTransaction();
    
    try {
        $query = "DELETE FROM data_pemeriksaan_mata WHERE id_pemeriksaaan = '$id_pemeriksaaan'";
        
        if ($db->koneksi->query($query)) {
            $db->commit();
            
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Pemeriksaan Mata';
            $keterangan = "Pemeriksaan Mata ID '$id_pemeriksaaan' untuk pasien {$pemeriksaan_data['nama_pasien']} (No. RM: {$pemeriksaan_data['no_rekam_medis']}) berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data pemeriksaan mata berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus pemeriksaan mata: " . $db->koneksi->error);
    }
    
    header("Location: ../../datapemeriksaanmata.php");
    exit();
}
?>