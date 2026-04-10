<?php
session_start();

include_once('../koneksi.php'); // sesuaikan path
$db = new database();

if (isset($_SESSION['id_user']) && isset($_SESSION['username'])) {
    $id_user = $_SESSION['id_user'];
    $username = $_SESSION['username'];
    $nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : $username;
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
    
    $jenis = 'Logout';
    $entitas = 'User';
    $keterangan = "Pengguna '$nama_user' telah logout dari sistem.";
    $waktu = date('Y-m-d H:i:s');
    
    // Panggil method
    $db->tambah_aktivitas_profile($id_user, $jenis, $entitas, $keterangan, $waktu);
}

$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
session_destroy();
header("Location: ../index.php");
exit();
?>