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

// PROSES HAPUS RESEP KACAMATA
if (isset($_GET['hapus'])) {
    $id_resep_kacamata = $_GET['hapus'];
    
    // Ambil data resep sebelum dihapus untuk log
    $query_resep = "SELECT rk.*, dr.no_rekam_medis, dp.nama_pasien 
                    FROM data_resep_kacamata rk
                    JOIN data_rekam_medis dr ON rk.id_rekam = dr.id_rekam
                    JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                    WHERE rk.id_resep_kacamata = '$id_resep_kacamata'";
    $result_resep = $db->koneksi->query($query_resep);
    $resep_data = $result_resep ? $result_resep->fetch_assoc() : null;
    
    if (!$resep_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data resep kacamata tidak ditemukan.';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    $db->beginTransaction();
    
    try {
        $query = "DELETE FROM data_resep_kacamata WHERE id_resep_kacamata = '$id_resep_kacamata'";
        
        if ($db->koneksi->query($query)) {
            $db->commit();
            
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Resep Kacamata';
            $keterangan = "Resep Kacamata ID '$id_resep_kacamata' untuk pasien {$resep_data['nama_pasien']} (No. RM: {$resep_data['no_rekam_medis']}) berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep kacamata berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus resep kacamata: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepkacamata.php");
    exit();
}
?>