<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// Fungsi update grand_total yang lebih robust
function updateGrandTotal($db, $id_rekam, $change_amount) {
    $change_amount = (float)$change_amount;
    
    // Pastikan id_rekam valid dan ada di data_transaksi
    $query_cek = "SELECT id_transaksi FROM data_transaksi WHERE id_rekam = '$id_rekam'";
    $result_cek = $db->koneksi->query($query_cek);
    
    if (!$result_cek || $result_cek->num_rows == 0) {
        error_log("ERROR updateGrandTotal: id_rekam $id_rekam tidak ditemukan di data_transaksi");
        return false;
    }
    
    // Gunakan UPDATE dengan WHERE yang lebih spesifik
    $query = "UPDATE data_transaksi 
              SET grand_total = IFNULL(grand_total, 0) + $change_amount
              WHERE id_rekam = '$id_rekam'";
    
    $result = $db->koneksi->query($query);
    
    if (!$result) {
        error_log("ERROR updateGrandTotal: " . $db->koneksi->error);
        return false;
    }
    
    if ($db->koneksi->affected_rows == 0) {
        error_log("WARNING updateGrandTotal: Tidak ada baris yang terupdate untuk id_rekam $id_rekam");
        return false;
    }
    
    return true;
}

// Validasi akses
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: ../../dashboard.php");
    exit();
}

// PROSES HAPUS DETAIL RESEP OBAT
if (isset($_GET['hapus'])) {
    $id_detail_resep = $_GET['hapus'];
    
    // Validasi ID tidak kosong
    if (empty($id_detail_resep)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Detail Resep tidak valid!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    // Ambil data detail resep untuk log dan ambil subtotal serta id_rekam
    $query_detail = "SELECT ddr.id_detail_resep, ddr.id_resep_obat, ddr.id_obat, ddr.jumlah, ddr.subtotal,
                            dro.id_rekam, do.nama_obat
                     FROM data_detail_resep_obat ddr
                     JOIN data_resep_obat dro ON ddr.id_resep_obat = dro.id_resep_obat
                     JOIN data_obat do ON ddr.id_obat = do.id_obat 
                     WHERE ddr.id_detail_resep = '$id_detail_resep'";
    $result_detail = $db->koneksi->query($query_detail);
    $detail_data = $result_detail ? $result_detail->fetch_assoc() : null;
    
    if (!$detail_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data detail resep tidak ditemukan.';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    $subtotal = $detail_data['subtotal'];
    $id_rekam = $detail_data['id_rekam'];
    
    // Mulai transaction
    $db->koneksi->begin_transaction();
    
    try {
        // Cek apakah data di data_detail_transaksi ada
        $query_cek_transaksi = "SELECT id_detail_transaksi FROM data_detail_transaksi 
                                WHERE id_detail_resep = '$id_detail_resep' AND jenis_item = 'Obat'";
        $result_cek = $db->koneksi->query($query_cek_transaksi);
        $transaksi_ada = ($result_cek && $result_cek->num_rows > 0);
        
        if ($transaksi_ada) {
            // Hapus dari data_detail_transaksi terlebih dahulu
            $query_hapus_transaksi = "DELETE FROM data_detail_transaksi 
                                      WHERE id_detail_resep = '$id_detail_resep' AND jenis_item = 'Obat'";
            
            if (!$db->koneksi->query($query_hapus_transaksi)) {
                throw new Exception("Gagal menghapus data detail transaksi: " . $db->koneksi->error);
            }
        }
        
        // Hapus dari data_detail_resep_obat
        $query = "DELETE FROM data_detail_resep_obat WHERE id_detail_resep = '$id_detail_resep'";
        
        if (!$db->koneksi->query($query)) {
            throw new Exception("Gagal menghapus data detail resep obat: " . $db->koneksi->error);
        }
        
        // Update grand_total di data_transaksi (kurangi dengan subtotal)
        if (!updateGrandTotal($db, $id_rekam, -$subtotal)) {
            throw new Exception("Gagal mengupdate grand_total: " . $db->koneksi->error);
        }
        
        // Commit transaction
        $db->koneksi->commit();
        
        // Catat aktivitas user
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Detail Resep Obat';
        $keterangan = "Detail Resep Obat ID '$id_detail_resep' (ID Resep: {$detail_data['id_resep_obat']}, Obat: {$detail_data['nama_obat']}, Jumlah: {$detail_data['jumlah']}, Subtotal: " . number_format($subtotal, 0, ',', '.') . ") ";
        
        if ($transaksi_ada) {
            $keterangan .= "beserta Detail Transaksi terkait ";
        }
        
        $keterangan .= "berhasil dihapus oleh $username. Grand Total diupdate -" . number_format($subtotal, 0, ',', '.');
        
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        
        if ($transaksi_ada) {
            $_SESSION['notif_message'] = 'Data detail resep obat dan detail transaksi berhasil dihapus. Grand Total telah diupdate.';
        } else {
            $_SESSION['notif_message'] = 'Data detail resep obat berhasil dihapus. Grand Total telah diupdate.';
        }
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $db->koneksi->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus detail resep obat: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-resep-obat.php");
    exit();
}
?>