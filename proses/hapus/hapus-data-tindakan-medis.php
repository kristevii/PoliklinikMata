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

// PROSES HAPUS DETAIL TINDAKAN MEDIS
if (isset($_GET['hapus'])) {
    $id_detail_tindakanmedis = $_GET['hapus'];
    
    // Ambil data detail sebelum dihapus untuk log
    $query_detail = "SELECT dtm.*, dr.no_rekam_medis, dp.nik, dp.nama_pasien, tm.nama_tindakan 
                    FROM data_detail_tindakanmedis dtm
                    JOIN data_rekam_medis dr ON dtm.id_rekam = dr.id_rekam
                    JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                    JOIN data_tindakan_medis tm ON dtm.id_tindakan_medis = tm.id_tindakan_medis
                    WHERE dtm.id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
    $result_detail = $db->koneksi->query($query_detail);
    $detail_data = $result_detail ? $result_detail->fetch_assoc() : null;
    
    if (!$detail_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data detail tindakan medis tidak ditemukan.';
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    try {
        $query = "DELETE FROM data_detail_tindakanmedis WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
        
        if ($db->koneksi->query($query)) {
            $username = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Hapus';
            $jenis = 'Detail Tindakan Medis';
            $keterangan = "Detail Tindakan Medis ID '$id_detail_tindakanmedis' untuk pasien {$detail_data['nama_pasien']} (NIK: {$detail_data['nik']}, No. RM: {$detail_data['no_rekam_medis']}) dengan tindakan {$detail_data['nama_tindakan']} berhasil dihapus oleh $username.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data detail tindakan medis berhasil dihapus.';
        } else {
            throw new Exception("Gagal menghapus data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus detail tindakan medis: " . $db->koneksi->error);
    }
    
    header("Location: ../../data_detail_tindakan_medis.php");
    exit();
}
?>