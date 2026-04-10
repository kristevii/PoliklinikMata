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

// PROSES TAMBAH RESEP KACAMATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_resep'])) {
    $id_rekam = trim($_POST['id_rekam'] ?? '');
    $sph_od = !empty($_POST['sph_od']) ? (float)$_POST['sph_od'] : 0;
    $cyl_od = !empty($_POST['cyl_od']) ? (float)$_POST['cyl_od'] : 0;
    $axis_od = !empty($_POST['axis_od']) ? (int)$_POST['axis_od'] : 0;
    $sph_os = !empty($_POST['sph_os']) ? (float)$_POST['sph_os'] : 0;
    $cyl_os = !empty($_POST['cyl_os']) ? (float)$_POST['cyl_os'] : 0;
    $axis_os = !empty($_POST['axis_os']) ? (int)$_POST['axis_os'] : 0;
    $pd = !empty($_POST['pd']) ? (int)$_POST['pd'] : null;
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Validasi data
    if (empty($id_rekam)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Rekam medis wajib dipilih!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    // Validasi axis (0-180)
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
    
    // Validasi PD (Pupil Distance) biasanya antara 50-75 mm
    if ($pd !== null && ($pd < 40 || $pd > 80)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'PD (Pupil Distance) harus antara 40-80 mm!';
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    // Cek apakah id_rekam valid
    $check_query = "SELECT id_rekam FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
    $check_result = $db->koneksi->query($check_query);
    if (!$check_result || $check_result->num_rows == 0) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = "Rekam medis tidak valid!";
        header("Location: ../../dataresepkacamata.php");
        exit();
    }
    
    try {
        $pd_sql = $pd !== null ? "'$pd'" : "NULL";
        $catatan_sql = !empty($catatan) ? "'" . mysqli_real_escape_string($db->koneksi, $catatan) . "'" : "NULL";
        
        $query = "INSERT INTO data_resep_kacamata (id_rekam, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, pd, catatan, created_at) 
                  VALUES ('$id_rekam', '$sph_od', '$cyl_od', '$axis_od', '$sph_os', '$cyl_os', '$axis_os', $pd_sql, $catatan_sql, NOW())";
        
        if ($db->koneksi->query($query)) {
            $id_resep = $db->koneksi->insert_id;
            
            $username_session = $_SESSION['username'] ?? 'unknown user';
            $entitas = 'Tambah';
            $jenis = 'Resep Kacamata';
            $keterangan = "Resep Kacamata (ID: $id_resep) berhasil ditambahkan oleh $username_session.";
            $waktu = date('Y-m-d H:i:s');
            $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
            
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data resep kacamata berhasil ditambahkan.';
        } else {
            throw new Exception("Gagal menambahkan data: " . $db->koneksi->error);
        }
    } catch (Exception $e) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data: ' . $e->getMessage();
        error_log("Error tambah resep kacamata: " . $db->koneksi->error);
    }
    
    header("Location: ../../dataresepkacamata.php");
    exit();
}
?>