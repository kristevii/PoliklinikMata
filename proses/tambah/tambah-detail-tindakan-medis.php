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

// PROSES TAMBAH DETAIL TINDAKAN MEDIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_detail'])) {
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $id_tindakan_medis = trim($_POST['id_tindakan_medis'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $subtotal = (int)($_POST['subtotal'] ?? 0);
    
    // Validasi data
    if (empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Rekam medis wajib dipilih!';
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
    
    // Ambil tarif dan nama tindakan dari tabel tindakan_medis
    $query_tindakan = "SELECT tarif, nama_tindakan FROM data_tindakan_medis WHERE id_tindakan_medis = '$id_tindakan_medis'";
    $result_tindakan = $db->koneksi->query($query_tindakan);
    if (!$result_tindakan || $result_tindakan->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Tindakan medis tidak valid!";
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    $tindakan_data = $result_tindakan->fetch_assoc();
    $tarif = $tindakan_data['tarif'];
    $nama_tindakan = $tindakan_data['nama_tindakan'];
    
    // Validasi tarif
    if ($tarif <= 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Tarif tindakan medis tidak valid!";
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    // Hitung ulang subtotal untuk keamanan
    $subtotal_calc = $tarif * $qty;
    if ($subtotal != $subtotal_calc) {
        $subtotal = $subtotal_calc;
    }
    
    // Cek apakah id_rekam valid
    $check_query = "SELECT id_rekam FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Rekam medis tidak valid!";
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    // Cari id_transaksi berdasarkan id_rekam
    $query_transaksi = "SELECT id_transaksi FROM data_transaksi WHERE id_rekam = '$id_rekam'";
    $result_transaksi = $db->koneksi->query($query_transaksi);
    
    if (!$result_transaksi || $result_transaksi->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Transaksi untuk rekam medis ini tidak ditemukan!";
        header("Location: ../../data-detail-tindakan-medis.php");
        exit();
    }
    
    $transaksi_data = $result_transaksi->fetch_assoc();
    $id_transaksi = $transaksi_data['id_transaksi'];
    
    // Mulai transaction untuk memastikan kedua insert berhasil
    $db->koneksi->begin_transaction();
    
    try {
        // Insert ke data_detail_tindakan_medis
        $query = "INSERT INTO data_detail_tindakan_medis (id_rekam, id_tindakan_medis, qty, harga, subtotal, created_at) 
                  VALUES ('$id_rekam', '$id_tindakan_medis', '$qty', '$tarif', '$subtotal', NOW())";
        
        if (!$db->koneksi->query($query)) {
            throw new Exception("Gagal menambahkan data detail tindakan medis: " . $db->koneksi->error);
        }
        
        $id_detail = $db->koneksi->insert_id;
        
        // Insert ke data_detail_transaksi
        $jenis_item = 'Tindakan';
        $query_transaksi_detail = "INSERT INTO data_detail_transaksi (id_transaksi, id_detail_tindakanmedis, id_detail_resep, jenis_item, nama_item, qty, harga, subtotal, created_at) 
                                   VALUES ('$id_transaksi', '$id_detail', NULL, '$jenis_item', '$nama_tindakan', '$qty', '$tarif', '$subtotal', NOW())";
        
        if (!$db->koneksi->query($query_transaksi_detail)) {
            throw new Exception("Gagal menambahkan data detail transaksi: " . $db->koneksi->error);
        }
        
        $id_detail_transaksi = $db->koneksi->insert_id;
        
        // Update grand_total di data_transaksi
        if (!updateGrandTotal($db, $id_rekam, $subtotal)) {
            throw new Exception("Gagal mengupdate grand_total: " . $db->koneksi->error);
        }
        
        // Commit transaction
        $db->koneksi->commit();
        
        // Catat aktivitas user untuk detail tindakan medis
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Tambah';
        $jenis = 'Detail Tindakan Medis';
        $keterangan = "Detail Tindakan Medis (ID: $id_detail) berhasil ditambahkan oleh $username_session. Grand Total diupdate +" . number_format($subtotal, 0, ',', '.');
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        // Catat aktivitas user untuk detail transaksi
        $entitas = 'Tambah';
        $jenis = 'Detail Transaksi';
        $keterangan = "Detail Transaksi (ID: $id_detail_transaksi) berhasil ditambahkan secara otomatis dari Detail Tindakan Medis (ID: $id_detail) oleh $username_session.";
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data detail tindakan medis dan detail transaksi berhasil ditambahkan. Grand Total telah diupdate.';
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $db->koneksi->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah detail tindakan medis: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-tindakan-medis.php");
    exit();
}
?>