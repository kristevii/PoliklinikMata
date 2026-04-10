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

// PROSES EDIT PEMERIKSAAN MATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pemeriksaan'])) {
    $id_pemeriksaan = $_POST['id_pemeriksaan'] ?? '';
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $visus_od = trim($_POST['visus_od'] ?? '');
    $visus_os = trim($_POST['visus_os'] ?? '');
    $sph_od = !empty($_POST['sph_od']) ? (float)$_POST['sph_od'] : 0;
    $cyl_od = !empty($_POST['cyl_od']) ? (float)$_POST['cyl_od'] : 0;
    $axis_od = !empty($_POST['axis_od']) ? (int)$_POST['axis_od'] : 0;
    $sph_os = !empty($_POST['sph_os']) ? (float)$_POST['sph_os'] : 0;
    $cyl_os = !empty($_POST['cyl_os']) ? (float)$_POST['cyl_os'] : 0;
    $axis_os = !empty($_POST['axis_os']) ? (int)$_POST['axis_os'] : 0;
    $tio_od = !empty($_POST['tio_od']) ? (int)$_POST['tio_od'] : null;
    $tio_os = !empty($_POST['tio_os']) ? (int)$_POST['tio_os'] : null;
    $slit_lamp = trim($_POST['slit_lamp'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    
    if (empty($id_pemeriksaan) || empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Pemeriksaan dan Rekam medis wajib diisi!';
        header("Location: ../../datapemeriksaanmata.php");
        exit();
    }
    
    // Validasi axis
    if ($axis_od < 0 || $axis_od > 180) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Axis OD harus antara 0-180 derajat!';
        header("Location: ../../datapemeriksaanmata.php");
        exit();
    }
    
    if ($axis_os < 0 || $axis_os > 180) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Axis OS harus antara 0-180 derajat!';
        header("Location: ../../datapemeriksaanmata.php");
        exit();
    }
    
    // Cek apakah data ada
    $check_query = "SELECT id_pemeriksaan FROM data_pemeriksaan_mata WHERE id_pemeriksaan = '$id_pemeriksaan'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Data pemeriksaan tidak ditemukan!";
        header("Location: ../../datapemeriksaanmata.php");
        exit();
    }
    
    try {
        $tio_od_sql = $tio_od !== null ? "'$tio_od'" : "NULL";
        $tio_os_sql = $tio_os !== null ? "'$tio_os'" : "NULL";
        $slit_lamp_sql = !empty($slit_lamp) ? "'" . mysqli_real_escape_string($db->koneksi, $slit_lamp) . "'" : "NULL";
        $catatan_sql = !empty($catatan) ? "'" . mysqli_real_escape_string($db->koneksi, $catatan) . "'" : "NULL";
        
        $query = "UPDATE data_pemeriksaan_mata SET 
                    id_rekam = '$id_rekam',
                    visus_od = '$visus_od',
                    visus_os = '$visus_os',
                    sph_od = '$sph_od',
                    cyl_od = '$cyl_od',
                    axis_od = '$axis_od',
                    sph_os = '$sph_os',
                    cyl_os = '$cyl_os',
                    axis_os = '$axis_os',
                    tio_od = $tio_od_sql,
                    tio_os = $tio_os_sql,
                    slit_lamp = $slit_lamp_sql,
                    catatan = $catatan_sql,
                    updated_at = NOW()
                  WHERE id_pemeriksaan = '$id_pemeriksaan'";
        
        if ($db->koneksi->query($query)) {
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Edit';
            $jenis = 'Pemeriksaan Mata';
            $keterangan = "Pemeriksaan Mata ID '$id_pemeriksaan' berhasil diupdate oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data pemeriksaan mata berhasil diupdate.';
        } else {
            throw new Exception("Gagal mengupdate data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data: ' . $e->getMessage();
        error_log("Error edit pemeriksaan mata: " . $db->koneksi->error);
    }
    
    header("Location: ../../datapemeriksaanmata.php");
    exit();
}
?>