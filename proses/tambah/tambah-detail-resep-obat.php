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

// PROSES TAMBAH DETAIL RESEP OBAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_detail_resep'])) {
    $id_resep_obat = trim($_POST['id_resep_obat'] ?? '');
    $id_obat = trim($_POST['id_obat'] ?? '');
    $jumlah = trim($_POST['jumlah'] ?? '');
    $dosis = trim($_POST['dosis'] ?? '');
    $aturan_pakai = trim($_POST['aturan_pakai'] ?? '');
    $subtotal = trim($_POST['subtotal'] ?? '0');
    
    // Validasi data
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
    
    if (empty($jumlah) || $jumlah < 1) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Jumlah obat wajib diisi minimal 1!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    // Ambil harga dan nama obat
    $query_obat = "SELECT harga, nama_obat FROM data_obat WHERE id_obat = '$id_obat'";
    $result_obat = $db->koneksi->query($query_obat);
    if (!$result_obat || $result_obat->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data obat tidak ditemukan!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    $obat_data = $result_obat->fetch_assoc();
    $harga = $obat_data['harga'];
    $nama_obat = $obat_data['nama_obat'];
    
    // Hitung ulang subtotal
    $subtotal_calc = $harga * $jumlah;
    
    // Cek apakah id_resep_obat valid dan ambil id_rekam
    $query_resep = "SELECT dro.id_resep_obat, dro.id_rekam 
                    FROM data_resep_obat dro 
                    WHERE dro.id_resep_obat = '$id_resep_obat'";
    $result_resep = $db->koneksi->query($query_resep);
    if (!$result_resep || $result_resep->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Resep Obat tidak valid!';
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    $resep_data = $result_resep->fetch_assoc();
    $id_rekam = $resep_data['id_rekam'];
    
    // Cari id_transaksi berdasarkan id_rekam
    $query_transaksi = "SELECT id_transaksi FROM data_transaksi WHERE id_rekam = '$id_rekam'";
    $result_transaksi = $db->koneksi->query($query_transaksi);
    
    if (!$result_transaksi || $result_transaksi->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Transaksi untuk rekam medis ini tidak ditemukan!";
        header("Location: ../../data-detail-resep-obat.php");
        exit();
    }
    
    $transaksi_data = $result_transaksi->fetch_assoc();
    $id_transaksi = $transaksi_data['id_transaksi'];
    
    // Mulai transaction
    $db->koneksi->begin_transaction();
    
    try {
        $dosis_sql = !empty($dosis) ? "'" . mysqli_real_escape_string($db->koneksi, $dosis) . "'" : "NULL";
        $aturan_pakai_sql = !empty($aturan_pakai) ? "'" . mysqli_real_escape_string($db->koneksi, $aturan_pakai) . "'" : "NULL";
        
        // Insert ke data_detail_resep_obat
        $query = "INSERT INTO data_detail_resep_obat (id_resep_obat, id_obat, jumlah, dosis, aturan_pakai, harga, subtotal) 
                  VALUES ('$id_resep_obat', '$id_obat', '$jumlah', $dosis_sql, $aturan_pakai_sql, '$harga', '$subtotal_calc')";
        
        if (!$db->koneksi->query($query)) {
            throw new Exception("Gagal menambahkan data detail resep obat: " . $db->koneksi->error);
        }
        
        $id_detail_resep = $db->koneksi->insert_id;
        
        // Insert ke data_detail_transaksi
        $jenis_item = 'Obat';
        $query_transaksi_detail = "INSERT INTO data_detail_transaksi (id_transaksi, id_detail_tindakanmedis, id_detail_resep, jenis_item, nama_item, qty, harga, subtotal, created_at) 
                                   VALUES ('$id_transaksi', NULL, '$id_detail_resep', '$jenis_item', '$nama_obat', '$jumlah', '$harga', '$subtotal_calc', NOW())";
        
        if (!$db->koneksi->query($query_transaksi_detail)) {
            throw new Exception("Gagal menambahkan data detail transaksi: " . $db->koneksi->error);
        }
        
        $id_detail_transaksi = $db->koneksi->insert_id;
        
        // Update grand_total di data_transaksi
        if (!updateGrandTotal($db, $id_rekam, $subtotal_calc)) {
            throw new Exception("Gagal mengupdate grand_total: " . $db->koneksi->error);
        }
        
        // Commit transaction
        $db->koneksi->commit();
        
        // Catat aktivitas user untuk detail resep obat
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Tambah';
        $jenis = 'Detail Resep Obat';
        $keterangan = "Detail Resep Obat (ID: $id_detail_resep) berhasil ditambahkan oleh $username_session. Grand Total diupdate +" . number_format($subtotal_calc, 0, ',', '.');
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        // Catat aktivitas user untuk detail transaksi
        $entitas = 'Tambah';
        $jenis = 'Detail Transaksi';
        $keterangan = "Detail Transaksi (ID: $id_detail_transaksi) berhasil ditambahkan secara otomatis dari Detail Resep Obat (ID: $id_detail_resep) oleh $username_session.";
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Detail resep obat dan detail transaksi berhasil ditambahkan. Grand Total telah diupdate.';
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $db->koneksi->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah detail resep obat: " . $e->getMessage());
    }
    
    header("Location: ../../data-detail-resep-obat.php");
    exit();
}
?>