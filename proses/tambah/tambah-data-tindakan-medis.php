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
    
    // Cek apakah id_rekam valid
    $check_query = "SELECT id_rekam FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Rekam medis tidak valid!";
        header("Location: ../../data_detail_tindakan_medis.php");
        exit();
    }
    
    try {
        $query = "INSERT INTO data_detail_tindakanmedis (id_rekam, id_tindakan_medis, qty, harga, subtotal, created_at) 
                  VALUES ('$id_rekam', '$id_tindakan_medis', '$qty', '$harga', '$subtotal', NOW())";
        
        if ($db->koneksi->query($query)) {
            $id_detail = $db->koneksi->insert_id;
            
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Tambah';
            $jenis = 'Detail Tindakan Medis';
            $keterangan = "Detail Tindakan Medis (ID: $id_detail) berhasil ditambahkan oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data detail tindakan medis berhasil ditambahkan.';
        } else {
            throw new Exception("Gagal menambahkan data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah detail tindakan medis: " . $db->koneksi->error);
    }
    
    header("Location: ../../data_detail_tindakan_medis.php");
    exit();
}
?>