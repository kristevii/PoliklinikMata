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

// PROSES EDIT DETAIL RESEP OBAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_detail_resep'])) {
    $id_detail_resep = $_POST['id_detail_resep'] ?? '';
    $id_resep_obat = trim($_POST['id_resep_obat'] ?? '');
    $id_obat = trim($_POST['id_obat'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $dosis = trim($_POST['dosis'] ?? '');
    $aturan_pakai = trim($_POST['aturan_pakai'] ?? '');
    
    // Validasi data
    if (empty($id_detail_resep)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Detail Resep tidak valid!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    if (empty($id_resep_obat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Resep Obat tidak valid!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    if (empty($id_obat)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Obat wajib dipilih!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    if ($jumlah < 1) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Jumlah obat wajib diisi minimal 1!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    // Mulai transaction
    $db->koneksi->begin_transaction();
    
    try {
        // ========== 1. AMBIL DATA LAMA SEBELUM UPDATE ==========
        $query_lama = "SELECT ddr.subtotal, ddr.id_obat as id_obat_lama, ddr.jumlah as jumlah_lama,
                              dro.id_rekam
                       FROM data_detail_resep_obat ddr
                       JOIN data_resep_obat dro ON ddr.id_resep_obat = dro.id_resep_obat
                       WHERE ddr.id_detail_resep = '$id_detail_resep'";
        $result_lama = $db->koneksi->query($query_lama);
        
        if (!$result_lama || $result_lama->num_rows == 0) {
            throw new Exception("Data detail resep tidak ditemukan!");
        }
        
        $data_lama = $result_lama->fetch_assoc();
        $subtotal_lama = (float)$data_lama['subtotal'];
        $id_rekam = $data_lama['id_rekam'];
        $id_obat_lama = $data_lama['id_obat_lama'];
        $jumlah_lama = (int)$data_lama['jumlah_lama'];
        
        // ========== 2. AMBIL HARGA DAN NAMA OBAT BARU ==========
        $query_obat = "SELECT harga, nama_obat FROM data_obat WHERE id_obat = '$id_obat'";
        $result_obat = $db->koneksi->query($query_obat);
        
        if (!$result_obat || $result_obat->num_rows == 0) {
            throw new Exception("Data obat tidak ditemukan!");
        }
        
        $obat_data = $result_obat->fetch_assoc();
        $harga = (float)$obat_data['harga'];
        $nama_obat = $obat_data['nama_obat'];
        
        // ========== 3. HITUNG SUBTOTAL BARU ==========
        $subtotal_baru = $harga * $jumlah;
        
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
        
        // ========== 7. UPDATE DATA DETAIL RESEP OBAT ==========
        $dosis_sql = !empty($dosis) ? "'" . mysqli_real_escape_string($db->koneksi, $dosis) . "'" : "NULL";
        $aturan_pakai_sql = !empty($aturan_pakai) ? "'" . mysqli_real_escape_string($db->koneksi, $aturan_pakai) . "'" : "NULL";
        
        $query_update_detail = "UPDATE data_detail_resep_obat SET 
                                id_resep_obat = '$id_resep_obat',
                                id_obat = '$id_obat',
                                jumlah = '$jumlah',
                                dosis = $dosis_sql,
                                aturan_pakai = $aturan_pakai_sql,
                                harga = '$harga',
                                subtotal = '$subtotal_baru'
                              WHERE id_detail_resep = '$id_detail_resep'";
        
        if (!$db->koneksi->query($query_update_detail)) {
            throw new Exception("Gagal mengupdate data detail resep obat: " . $db->koneksi->error);
        }
        
        // ========== 8. UPDATE DATA DETAIL TRANSAKSI ==========
        $query_update_transaksi_detail = "UPDATE data_detail_transaksi SET 
                                          id_transaksi = '$id_transaksi',
                                          jenis_item = 'Obat',
                                          nama_item = '$nama_obat',
                                          qty = '$jumlah',
                                          harga = '$harga',
                                          subtotal = '$subtotal_baru'
                                        WHERE id_detail_resep = '$id_detail_resep' AND jenis_item = 'Obat'";
        
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
        $jenis = 'Detail Resep Obat';
        $selisih_text = $selisih >= 0 ? '+' . number_format($selisih, 0, ',', '.') : number_format($selisih, 0, ',', '.');
        $keterangan = "Detail Resep Obat ID '$id_detail_resep' berhasil diupdate. "
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
        error_log("Error edit detail resep obat: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-resep-obat.php");
    exit();
}
?>