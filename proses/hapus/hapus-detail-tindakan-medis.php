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

// PROSES HAPUS DETAIL TINDAKAN MEDIS
if (isset($_GET['hapus'])) {
    $id_detail_tindakanmedis = $_GET['hapus'];
    
    // Validasi ID tidak kosong
    if (empty($id_detail_tindakanmedis)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Detail Tindakan Medis tidak valid!';
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    // Ambil data detail tindakan medis untuk log dan ambil subtotal
    $query_detail = "SELECT dtm.id_detail_tindakanmedis, dtm.id_rekam, dtm.id_tindakan_medis, dtm.qty, dtm.subtotal,
                            tm.nama_tindakan, tm.tarif
                     FROM data_detail_tindakan_medis dtm
                     JOIN data_tindakan_medis tm ON dtm.id_tindakan_medis = tm.id_tindakan_medis 
                     WHERE dtm.id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
    $result_detail = $db->koneksi->query($query_detail);
    $detail_data = $result_detail ? $result_detail->fetch_assoc() : null;
    
    if (!$detail_data) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data detail tindakan medis tidak ditemukan.';
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    $subtotal = $detail_data['subtotal'];
    $id_rekam = $detail_data['id_rekam'];
    
    // Mulai transaction
    $db->koneksi->begin_transaction();
    
    try {
        // Cek apakah data di data_detail_transaksi ada
        $query_cek_transaksi = "SELECT id_detail_transaksi FROM data_detail_transaksi 
                                WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis' AND jenis_item = 'Tindakan'";
        $result_cek = $db->koneksi->query($query_cek_transaksi);
        $transaksi_ada = ($result_cek && $result_cek->num_rows > 0);
        
        if ($transaksi_ada) {
            // Hapus dari data_detail_transaksi terlebih dahulu (foreign key)
            $query_hapus_transaksi = "DELETE FROM data_detail_transaksi 
                                      WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis' AND jenis_item = 'Tindakan'";
            
            if (!$db->koneksi->query($query_hapus_transaksi)) {
                throw new Exception("Gagal menghapus data detail transaksi: " . $db->koneksi->error);
            }
        }
        
        // Hapus dari data_detail_tindakan_medis
        $query = "DELETE FROM data_detail_tindakan_medis WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
        
        if (!$db->koneksi->query($query)) {
            throw new Exception("Gagal menghapus data detail tindakan medis: " . $db->koneksi->error);
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
        $jenis = 'Detail Tindakan Medis';
        $keterangan = "Detail Tindakan Medis ID '$id_detail_tindakanmedis' (ID Rekam: {$detail_data['id_rekam']}, Tindakan: {$detail_data['nama_tindakan']}, Qty: {$detail_data['qty']}, Subtotal: " . number_format($subtotal, 0, ',', '.') . ") ";
        
        if ($transaksi_ada) {
            $keterangan .= "beserta Detail Transaksi terkait ";
        }
        
        $keterangan .= "berhasil dihapus oleh $username. Grand Total diupdate -" . number_format($subtotal, 0, ',', '.');
        
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        
        if ($transaksi_ada) {
            $_SESSION['notif_message'] = 'Data detail tindakan medis dan detail transaksi berhasil dihapus. Grand Total telah diupdate.';
        } else {
            $_SESSION['notif_message'] = 'Data detail tindakan medis berhasil dihapus. Grand Total telah diupdate.';
        }
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $db->koneksi->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data: ' . $e->getMessage();
        error_log("Error hapus detail tindakan medis: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-tindakan-medis.php");
    exit();
}
?>