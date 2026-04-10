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

// PROSES HAPUS RESEP OBAT
if (isset($_GET['hapus'])) {
    $id_resep_obat = $_GET['hapus'];
    
    // Ambil data resep sebelum dihapus untuk log
    $query_resep = "SELECT rk.*, dr.no_rekam_medis, dp.nik, dp.nama_pasien 
                    FROM data_resep_obat rk
                    JOIN data_rekam_medis dr ON rk.id_rekam = dr.id_rekam
                    JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                    WHERE rk.id_resep_obat = '$id_resep_obat'";
    $result_resep = $db->koneksi->query($query_resep);
    $resep_data = $result_resep ? $result_resep->fetch_assoc() : null;
    
    if (!$resep_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data resep obat tidak ditemukan.';
        header("Location: ../../dataresepobat.php");
        exit();
    }
    
    try {
        $query = "DELETE FROM data_resep_obat WHERE id_resep_obat = '$id_resep_obat'";
        
        if ($db->koneksi->query($query)) {
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Resep Obat';
            $keterangan = "Resep Obat ID '$id_resep_obat' untuk pasien {$resep_data['nama_pasien']} (NIK: {$resep_data['nik']}, No. RM: {$resep_data['no_rekam_medis']}) berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep obat berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus resep obat: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepobat.php");
    exit();
}
?>