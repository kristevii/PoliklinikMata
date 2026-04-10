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
    $subtotal = (int)($_POST['subtotal'] ?? 0);
    
    if (empty($id_detail_tindakanmedis) || empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Detail dan Rekam medis wajib diisi!';
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    if (empty($id_tindakan_medis)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Tindakan medis wajib dipilih!';
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    if ($qty <= 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Quantity harus lebih dari 0!';
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    // Ambil harga dari tabel tindakan_medis
    $query_harga = "SELECT harga FROM data_tindakan_medis WHERE id_tindakan_medis = '$id_tindakan_medis'";
    $result_harga = $db->koneksi->query($query_harga);
    if (!$result_harga || $result_harga->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Tindakan medis tidak valid!";
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    $harga_data = $result_harga->fetch_assoc();
    $harga = $harga_data['harga'];
    
    // Hitung ulang subtotal untuk keamanan
    $subtotal_calc = $harga * $qty;
    if ($subtotal != $subtotal_calc) {
        $subtotal = $subtotal_calc;
    }
    
    // Cek apakah data ada
    $check_query = "SELECT id_detail_tindakanmedis FROM data_detail_tindakanmedis WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Data detail tindakan medis tidak ditemukan!";
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    try {
        $query = "UPDATE data_detail_tindakanmedis SET 
                    id_rekam = '$id_rekam',
                    id_tindakan_medis = '$id_tindakan_medis',
                    qty = '$qty',
                    harga = '$harga',
                    subtotal = '$subtotal'
                  WHERE id_detail_tindakanmedis = '$id_detail_tindakanmedis'";
        
        if ($db->koneksi->query($query)) {
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Edit';
            $jenis = 'Detail Tindakan Medis';
            $keterangan = "Detail Tindakan Medis ID '$id_detail_tindakanmedis' berhasil diupdate oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data detail tindakan medis berhasil diupdate.';
        } else {
            throw new Exception("Gagal mengupdate data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        error_log("Error edit detail tindakan medis: " . $db->koneksi->error);
    }
    
    header("Location: ../../data_detail_tindakan_medis.php");
    exit();
}
?>