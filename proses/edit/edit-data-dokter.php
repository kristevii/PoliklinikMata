<?php
session_start();
require_once "../../koneksi.php";
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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data dokter. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES EDIT DOKTER DENGAN VALIDASI EMAIL, TELEPON, DAN TANGGAL LAHIR LINTAS TABEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_dokter'])) {
    $id_user = $_POST['id_user'] ?? '';
    $kode_dokter = $_POST['kode_dokter'] ?? '';
    $subspesialisasi = $_POST['subspesialisasi'] ?? '';
    $nama_dokter = $_POST['nama_dokter'] ?? '';
    $tanggal_lahir_dokter = $_POST['tanggal_lahir_dokter'] ?? '';
    $jenis_kelamin_dokter = $_POST['jenis_kelamin_dokter'] ?? '';
    $alamat_dokter = $_POST['alamat_dokter'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telepon_dokter = trim($_POST['telepon_dokter'] ?? '');
    $ruang = $_POST['ruang'] ?? '';
    
    // Validasi input wajib
    if (empty($id_user) || empty($kode_dokter) || empty($nama_dokter)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field kode dokter dan nama dokter wajib diisi!';
        header("Location: ../../dokter.php");
        exit();
    }
    
    // ================= VALIDASI TANGGAL LAHIR =================
    if (!empty($tanggal_lahir_dokter)) {
        $tahun_lahir = date('Y', strtotime($tanggal_lahir_dokter));
        $tahun_sekarang = date('Y');
        
        if ($tahun_lahir == $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh menggunakan tahun ' . $tahun_sekarang . '. Silakan pilih tahun yang valid.';
            header("Location: ../../dokter.php");
            exit();
        }
        
        // Validasi tambahan: tahun lahir tidak boleh lebih dari tahun sekarang
        if ($tahun_lahir > $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh lebih besar dari tahun sekarang.';
            header("Location: ../../dokter.php");
            exit();
        }
        
        // Validasi tambahan: tanggal lahir tidak boleh kosong dan harus valid
        if (strtotime($tanggal_lahir_dokter) === false) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format tanggal lahir tidak valid.';
            header("Location: ../../dokter.php");
            exit();
        }
    }
    
    // CEK DUPLIKAT EMAIL (Dokter + Staff dengan pesan spesifik)
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format email tidak valid!';
            header("Location: ../../dokter.php");
            exit();
        }
        
        // Cek duplikat di tabel data_dokter (kecuali dokter yang sedang diedit)
        $check_email_dokter = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE email = '" . $db->koneksi->real_escape_string($email) . "' AND id_user != $id_user");
        $dokter_count = 0;
        if ($check_email_dokter && $check_email_dokter->num_rows > 0) {
            $row = $check_email_dokter->fetch_assoc();
            $dokter_count = $row['count'];
        }
        
        // Cek duplikat di tabel data_staff
        $check_email_staff = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE email = '" . $db->koneksi->real_escape_string($email) . "'");
        $staff_count = 0;
        if ($check_email_staff && $check_email_staff->num_rows > 0) {
            $row = $check_email_staff->fetch_assoc();
            $staff_count = $row['count'];
        }
        
        // Buat pesan error spesifik
        if ($dokter_count > 0 || $staff_count > 0) {
            $duplicate_in = array();
            if ($dokter_count > 0) $duplicate_in[] = 'Dokter';
            if ($staff_count > 0) $duplicate_in[] = 'Staff';
            
            $location_text = implode(' dan ', $duplicate_in);
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Email "' . $email . '" sudah terdaftar di data ' . $location_text . '. Silakan gunakan email lain.';
            header("Location: ../../dokter.php");
            exit();
        }
    }
    
    // CEK DUPLIKAT TELEPON (Dokter + Staff dengan pesan spesifik)
    if (!empty($telepon_dokter)) {
        if (!preg_match('/^[0-9]{10,15}$/', $telepon_dokter)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon harus terdiri dari 10-15 digit angka!';
            header("Location: ../../dokter.php");
            exit();
        }
        
        // Cek duplikat di tabel data_dokter (kecuali dokter yang sedang diedit)
        $check_phone_dokter = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '" . $db->koneksi->real_escape_string($telepon_dokter) . "' AND id_user != $id_user");
        $dokter_count = 0;
        if ($check_phone_dokter && $check_phone_dokter->num_rows > 0) {
            $row = $check_phone_dokter->fetch_assoc();
            $dokter_count = $row['count'];
        }
        
        // Cek duplikat di tabel data_staff
        $check_phone_staff = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '" . $db->koneksi->real_escape_string($telepon_dokter) . "'");
        $staff_count = 0;
        if ($check_phone_staff && $check_phone_staff->num_rows > 0) {
            $row = $check_phone_staff->fetch_assoc();
            $staff_count = $row['count'];
        }
        
        // Buat pesan error spesifik
        if ($dokter_count > 0 || $staff_count > 0) {
            $duplicate_in = array();
            if ($dokter_count > 0) $duplicate_in[] = 'Dokter';
            if ($staff_count > 0) $duplicate_in[] = 'Staff';
            
            $location_text = implode(' dan ', $duplicate_in);
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon "' . $telepon_dokter . '" sudah terdaftar di data ' . $location_text . '. Silakan gunakan nomor telepon lain.';
            header("Location: ../../dokter.php");
            exit();
        }
    }
    
    // Handle upload foto
    $foto_dokter = null;
    if (isset($_FILES['foto_dokter']) && $_FILES['foto_dokter']['error'] === UPLOAD_ERR_OK) {
        $foto_name = $_FILES['foto_dokter']['name'];
        $foto_tmp = $_FILES['foto_dokter']['tmp_name'];
        $foto_size = $_FILES['foto_dokter']['size'];
        $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($foto_ext, $allowed_ext) && $foto_size <= 2097152) { // 2MB max
            $new_foto_name = uniqid('dokter_', true) . '.' . $foto_ext;
            $upload_path = '../../image-dokter/' . $new_foto_name;
            
            // Pastikan folder upload ada
            $upload_dir = '../../image-dokter/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($foto_tmp, $upload_path)) {
                $foto_dokter = $new_foto_name;
                
                // Hapus foto lama jika ada
                $old_data = $db->get_dokter_by_id($id_user);
                if ($old_data && !empty($old_data['foto_dokter']) && file_exists('../../image-dokter/' . $old_data['foto_dokter'])) {
                    unlink('../../image-dokter/' . $old_data['foto_dokter']);
                }
            } else {
                $_SESSION['notif_status'] = 'warning';
                $_SESSION['notif_message'] = 'Gagal mengupload foto. Foto lama tetap digunakan.';
            }
        } else {
            $_SESSION['notif_status'] = 'warning';
            $_SESSION['notif_message'] = 'Format file tidak didukung atau ukuran terlalu besar. Foto lama tetap digunakan.';
        }
    }
    
    // Ambil data lama untuk foto jika tidak ada upload baru
    if (!$foto_dokter) {
        $old_data = $db->get_dokter_by_id($id_user);
        if ($old_data && !empty($old_data['foto_dokter'])) {
            $foto_dokter = $old_data['foto_dokter'];
        }
    }
    
    $update_dokter_result = $db->edit_data_dokter(
        $id_user, 
        $kode_dokter, 
        $subspesialisasi, 
        $foto_dokter, 
        $nama_dokter,
        $tanggal_lahir_dokter, 
        $jenis_kelamin_dokter,
        $alamat_dokter, 
        $email, 
        $telepon_dokter, 
        $ruang
    );
    
    if ($update_dokter_result) {
        // Update nama di tabel users
        $db->update_nama_user($id_user, $nama_dokter);
        
        // Update email di tabel users jika ada
        if (!empty($email)) {
            $db->koneksi->query("UPDATE users SET email = '" . $db->koneksi->real_escape_string($email) . "' WHERE id_user = $id_user");
        }
        
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Dokter';
        $keterangan = "Dokter '$nama_dokter' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data dokter berhasil diupdate.';
        
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data dokter.';
    }
    
    header("Location: ../../dokter.php");
    exit();
}
?>