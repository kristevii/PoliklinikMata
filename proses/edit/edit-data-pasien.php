<?php
session_start();
require_once "../../koneksi.php"; // Pastikan path ke file koneksi.php benar
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support yang bisa mengakses halaman ini
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: ../../dashboard.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];
$jabatan_user = '';

// Ambil data jabatan user jika role adalah Staff
if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = $db->koneksi->query($query_staff);
    
    if ($result_staff && $result_staff->num_rows > 0) {
        $staff_data = $result_staff->fetch_assoc();
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

// Cek hak akses: Staff dengan jabatan IT Support
if ($jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data pasien. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES EDIT PASIEN DENGAN VALIDASI NIK DAN TELEPON DUPLIKAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pasien'])) {
    $id_pasien = $_POST['id_pasien'] ?? '';
    $nik = trim($_POST['nik'] ?? '');
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    $jenis_kelamin_pasien = trim($_POST['jenis_kelamin_pasien'] ?? '');
    $tgl_lahir_pasien = $_POST['tgl_lahir_pasien'] ?? '';
    $alamat_pasien = trim($_POST['alamat_pasien'] ?? '');
    $telepon_pasien = trim($_POST['telepon_pasien'] ?? '');
    
    // Validasi data
    if (empty($id_pasien) || empty($nama_pasien)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field nama pasien wajib diisi!';
        header("Location: ../../datapasien.php");
        exit();
    }
    
    // Validasi NIK jika diisi
    if (!empty($nik)) {
        // Validasi format NIK (harus 16 digit angka)
        if (!preg_match('/^[0-9]{16}$/', $nik)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'NIK harus terdiri dari 16 digit angka!';
            header("Location: ../../datapasien.php");
            exit();
        }
        
        // Cek duplikat NIK (kecuali untuk pasien yang sedang diedit)
        $check_nik = $db->koneksi->query("SELECT COUNT(*) as count FROM data_pasien WHERE nik = '" . $db->koneksi->real_escape_string($nik) . "' AND id_pasien != $id_pasien");
        if ($check_nik && $check_nik->num_rows > 0) {
            $row = $check_nik->fetch_assoc();
            if ($row['count'] > 0) {
                $_SESSION['notif_status'] = 'error';
                $_SESSION['notif_message'] = 'NIK "' . $nik . '" sudah terdaftar untuk pasien lain. Silakan gunakan NIK lain.';
                header("Location: ../../datapasien.php");
                exit();
            }
        }
    }
    
    // Validasi format telepon jika diisi
    if (!empty($telepon_pasien)) {
        if (!preg_match('/^[0-9]{10,13}$/', $telepon_pasien)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon harus terdiri dari 10-13 digit angka!';
            header("Location: ../../datapasien.php");
            exit();
        }
        
        // Cek duplikat telepon (kecuali untuk pasien yang sedang diedit)
        $check_telepon = $db->koneksi->query("SELECT COUNT(*) as count FROM data_pasien WHERE telepon_pasien = '" . $db->koneksi->real_escape_string($telepon_pasien) . "' AND id_pasien != $id_pasien");
        if ($check_telepon && $check_telepon->num_rows > 0) {
            $row = $check_telepon->fetch_assoc();
            if ($row['count'] > 0) {
                $_SESSION['notif_status'] = 'error';
                $_SESSION['notif_message'] = 'Nomor telepon "' . $telepon_pasien . '" sudah terdaftar untuk pasien lain. Silakan gunakan nomor telepon lain.';
                header("Location: ../../datapasien.php");
                exit();
            }
        }
    }
    
    // Normalisasi jenis kelamin
    if (!in_array($jenis_kelamin_pasien, ['L', 'P', ''])) {
        $jenis_kelamin_pasien = '';
    }
    
    // Update data pasien
    if ($db->edit_data_pasien($id_pasien, $nik, $nama_pasien, $jenis_kelamin_pasien, $tgl_lahir_pasien, $alamat_pasien, $telepon_pasien)) {
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Pasien';
        $keterangan = "Pasien '$nama_pasien' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data pasien berhasil diupdate.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data pasien.';
    }
    
    header("Location: ../../datapasien.php");
    exit();
}
?>