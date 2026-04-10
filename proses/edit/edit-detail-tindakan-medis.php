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

// PROSES EDIT DETAIL TINDAKAN MEDIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_detail'])) {
    $id_detail_tindakanmedis = $_POST['id_detail_tindakanmedis'] ?? '';
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $id_tindakan_medis = trim($_POST['id_tindakan_medis'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $subtotal_dari_form = (int)($_POST['subtotal'] ?? 0);
    
    // Validasi data
    if (empty($id_detail_tindakanmedis) || empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Detail dan Rekam medis wajib diisi!';
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    if (empty($id_tindakan_medis)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Tindakan medis wajib dipilih!';
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    if ($qty <= 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Quantity harus lebih dari 0!';
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    // Mulai transaction
    $db->koneksi->begin_transaction();
    
    try {
        // ========== 1. AMBIL DATA LAMA SEBELUM UPDATE ==========
        $query_lama = "SELECT subtotal, id_tindakan_medis, qty as qty_lama 
                       FROM data_detail_tindakan_medis 
                       WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
        $result_lama = $db->koneksi->query($query_lama);
        
        if (!$result_lama || $result_lama->num_rows == 0) {
            throw new Exception("Data detail tindakan medis tidak ditemukan!");
        }
        
        $data_lama = $result_lama->fetch_assoc();
        $subtotal_lama = (float)$data_lama['subtotal'];
        $id_tindakan_lama = $data_lama['id_tindakan_medis'];
        $qty_lama = (int)$data_lama['qty_lama'];
        
        // ========== 2. AMBIL HARGA DAN NAMA TINDAKAN BARU ==========
        $query_tindakan = "SELECT tarif, nama_tindakan FROM data_tindakan_medis WHERE id_tindakan_medis = '$id_tindakan_medis'";
        $result_tindakan = $db->koneksi->query($query_tindakan);
        
        if (!$result_tindakan || $result_tindakan->num_rows == 0) {
            throw new Exception("Tindakan medis tidak valid!");
        }
        
        $tindakan_data = $result_tindakan->fetch_assoc();
        $tarif = (float)$tindakan_data['tarif'];
        $nama_tindakan = $tindakan_data['nama_tindakan'];
        
        // Validasi tarif
        if ($tarif <= 0) {
            throw new Exception("Tarif tindakan medis tidak valid!");
        }
        
        // ========== 3. HITUNG SUBTOTAL BARU ==========
        $subtotal_baru = $tarif * $qty;
        
        // ========== 4. HITUNG SELISIH ==========
        $selisih = $subtotal_baru - $subtotal_lama;
        
        // ========== 5. CARI ID TRANSAKSI ==========
        $query_transaksi = "SELECT id_transaksi, grand_total FROM data_transaksi WHERE id_rekam = '$id_rekam'";
        $result_transaksi = $db->koneksi->query($query_transaksi);
        
        if (!$result_transaksi || $result_transaksi->num_rows == 0) {
            throw new Exception("Transaksi untuk rekam medis ini tidak ditemukan!");
        }
        
        $transaksi_data = $result_transaksi->fetch_assoc();
        $id_transaksi = $transaksi_data['id_transaksi'];
        $grand_total_lama = (float)$transaksi_data['grand_total'];
        
        // ========== 6. HITUNG GRAND TOTAL BARU ==========
        $grand_total_baru = $grand_total_lama + $selisih;
        
        // ========== 7. UPDATE DATA DETAIL TINDAKAN MEDIS ==========
        $query_update_detail = "UPDATE data_detail_tindakan_medis SET 
                                id_rekam = '$id_rekam',
                                id_tindakan_medis = '$id_tindakan_medis',
                                qty = '$qty',
                                harga = '$tarif',
                                subtotal = '$subtotal_baru'
                              WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
        
        if (!$db->koneksi->query($query_update_detail)) {
            throw new Exception("Gagal mengupdate data detail tindakan medis: " . $db->koneksi->error);
        }
        
        // ========== 8. UPDATE DATA DETAIL TRANSAKSI ==========
        $query_update_transaksi_detail = "UPDATE data_detail_transaksi SET 
                                          id_transaksi = '$id_transaksi',
                                          jenis_item = 'Tindakan',
                                          nama_item = '$nama_tindakan',
                                          qty = '$qty',
                                          harga = '$tarif',
                                          subtotal = '$subtotal_baru'
                                        WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis' AND jenis_item = 'Tindakan'";
        
        if (!$db->koneksi->query($query_update_transaksi_detail)) {
            throw new Exception("Gagal mengupdate data detail transaksi: " . $db->koneksi->error);
        }
        
        // ========== 9. UPDATE GRAND TOTAL DI DATA TRANSAKSI ==========
        $query_update_grand = "UPDATE data_transaksi 
                               SET grand_total = grand_total + ($selisih)
                               WHERE id_rekam = '$id_rekam'";
        
        if (!$db->koneksi->query($query_update_grand)) {
            throw new Exception("Gagal mengupdate grand_total: " . $db->koneksi->error);
        }
        
        // ========== 10. VERIFIKASI GRAND TOTAL ==========
        $query_verifikasi = "SELECT grand_total FROM data_transaksi WHERE id_rekam = '$id_rekam'";
        $result_verifikasi = $db->koneksi->query($query_verifikasi);
        $data_verifikasi = $result_verifikasi->fetch_assoc();
        $grand_total_verifikasi = (float)$data_verifikasi['grand_total'];
        
        if ($grand_total_verifikasi != $grand_total_baru) {
            throw new Exception("Verifikasi grand_total gagal! Seharusnya $grand_total_baru, tapi menjadi $grand_total_verifikasi");
        }
        
        // Commit transaction
        $db->koneksi->commit();
        
        // Catat aktivitas user
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Detail Tindakan Medis';
        $selisih_text = $selisih >= 0 ? '+' . number_format($selisih, 0, ',', '.') : number_format($selisih, 0, ',', '.');
        $keterangan = "Detail Tindakan Medis ID '$id_detail_tindakanmedis' berhasil diupdate. "
                    . "Subtotal: " . number_format($subtotal_lama, 0, ',', '.') . " → " . number_format($subtotal_baru, 0, ',', '.')
                    . " (Selisih: $selisih_text). "
                    . "Grand Total: " . number_format($grand_total_lama, 0, ',', '.') . " → " . number_format($grand_total_baru, 0, ',', '.');
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data berhasil diupdate. Grand Total: ' . number_format($grand_total_baru, 0, ',', '.');
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $db->koneksi->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        error_log("Error edit detail tindakan medis: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-tindakan-medis.php");
    exit();
}
?>