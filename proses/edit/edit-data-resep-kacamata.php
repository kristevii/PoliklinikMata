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

// PROSES EDIT RESEP KACAMATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resep'])) {
    $id_resep_kacamata = $_POST['id_resep_kacamata'] ?? '';
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $sph_od = !empty($_POST['sph_od']) ? (float)$_POST['sph_od'] : 0;
    $cyl_od = !empty($_POST['cyl_od']) ? (float)$_POST['cyl_od'] : 0;
    $axis_od = !empty($_POST['axis_od']) ? (int)$_POST['axis_od'] : 0;
    $sph_os = !empty($_POST['sph_os']) ? (float)$_POST['sph_os'] : 0;
    $cyl_os = !empty($_POST['cyl_os']) ? (float)$_POST['cyl_os'] : 0;
    $axis_os = !empty($_POST['axis_os']) ? (int)$_POST['axis_os'] : 0;
    $pd = !empty($_POST['pd']) ? (int)$_POST['pd'] : null;
    $catatan = trim($_POST['catatan'] ?? '');
    
    if (empty($id_resep_kacamata) || empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Resep dan Rekam medis wajib diisi!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    // Validasi axis
    if ($axis_od < 0 || $axis_od > 180) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Axis OD harus antara 0-180 derajat!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    if ($axis_os < 0 || $axis_os > 180) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Axis OS harus antara 0-180 derajat!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    // Validasi PD
    if ($pd !== null && ($pd < 40 || $pd > 80)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'PD (Pupil Distance) harus antara 40-80 mm!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    // Cek apakah data ada
    $check_query = "SELECT id_resep_kacamata FROM data_resep_kacamata WHERE id_resep_kacamata = '$id_resep_kacamata'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Data resep kacamata tidak ditemukan!";
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    try {
        $pd_sql = $pd !== null ? "'$pd'" : "NULL";
        $catatan_sql = !empty($catatan) ? "'" . mysqli_real_escape_string($db->koneksi, $catatan) . "'" : "NULL";
        
        $query = "UPDATE data_resep_kacamata SET 
                    id_rekam = '$id_rekam',
                    sph_od = '$sph_od',
                    cyl_od = '$cyl_od',
                    axis_od = '$axis_od',
                    sph_os = '$sph_os',
                    cyl_os = '$cyl_os',
                    axis_os = '$axis_os',
                    pd = $pd_sql,
                    catatan = $catatan_sql
                  WHERE id_resep_kacamata = '$id_resep_kacamata'";
        
        if ($db->koneksi->query($query)) {
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Edit';
            $jenis = 'Resep Kacamata';
            $keterangan = "Resep Kacamata ID '$id_resep_kacamata' berhasil diupdate oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep kacamata berhasil diupdate.';
        } else {
            throw new Exception("Gagal mengupdate data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        error_log("Error edit resep kacamata: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepkacamata.php");
    exit();
}
?>