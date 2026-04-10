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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data staff. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES EDIT STAFF DENGAN VALIDASI EMAIL, TELEPON, DAN TANGGAL LAHIR LINTAS TABEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $id_user = $_POST['id_user'] ?? '';
    $kode_staff = $_POST['kode_staff'] ?? '';
    $jabatan_staff = $_POST['jabatan_staff'] ?? '';
    $nama_staff = $_POST['nama_staff'] ?? '';
    $jenis_kelamin_staff = $_POST['jenis_kelamin_staff'] ?? '';
    $tanggal_lahir_staff = $_POST['tanggal_lahir_staff'] ?? '';
    $alamat_staff = $_POST['alamat_staff'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telepon_staff = trim($_POST['telepon_staff'] ?? '');
    
    // Validasi input wajib
    if (empty($id_user) || empty($kode_staff) || empty($nama_staff)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field kode staff dan nama staff wajib diisi!';
        header("Location: ../../staff.php");
        exit();
    }
    
    // ================= VALIDASI TANGGAL LAHIR =================
    if (!empty($tanggal_lahir_staff)) {
        $tahun_lahir = date('Y', strtotime($tanggal_lahir_staff));
        $tahun_sekarang = date('Y');
        
        if ($tahun_lahir == $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh menggunakan tahun ' . $tahun_sekarang . '. Silakan pilih tahun yang valid.';
            header("Location: ../../staff.php");
            exit();
        }
        
        // Validasi tambahan: tahun lahir tidak boleh lebih dari tahun sekarang
        if ($tahun_lahir > $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh lebih besar dari tahun sekarang.';
            header("Location: ../../staff.php");
            exit();
        }
        
        // Validasi tambahan: tanggal lahir harus valid
        if (strtotime($tanggal_lahir_staff) === false) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format tanggal lahir tidak valid.';
            header("Location: ../../staff.php");
            exit();
        }
    }
    
    // CEK DUPLIKAT EMAIL (Staff + Dokter dengan pesan spesifik)
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format email tidak valid!';
            header("Location: ../../staff.php");
            exit();
        }
        
        // Cek duplikat di tabel data_staff (kecuali staff yang sedang diedit)
        $check_email_staff = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE email = '" . $db->koneksi->real_escape_string($email) . "' AND id_user != $id_user");
        $staff_count = 0;
        if ($check_email_staff && $check_email_staff->num_rows > 0) {
            $row = $check_email_staff->fetch_assoc();
            $staff_count = $row['count'];
        }
        
        // Cek duplikat di tabel data_dokter
        $check_email_dokter = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE email = '" . $db->koneksi->real_escape_string($email) . "'");
        $dokter_count = 0;
        if ($check_email_dokter && $check_email_dokter->num_rows > 0) {
            $row = $check_email_dokter->fetch_assoc();
            $dokter_count = $row['count'];
        }
        
        // Buat pesan error spesifik
        if ($staff_count > 0 || $dokter_count > 0) {
            $duplicate_in = array();
            if ($staff_count > 0) $duplicate_in[] = 'Staff';
            if ($dokter_count > 0) $duplicate_in[] = 'Dokter';
            
            $location_text = implode(' dan ', $duplicate_in);
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Email "' . $email . '" sudah terdaftar di data ' . $location_text . '. Silakan gunakan email lain.';
            header("Location: ../../staff.php");
            exit();
        }
    }
    
    // CEK DUPLIKAT TELEPON (Staff + Dokter dengan pesan spesifik)
    if (!empty($telepon_staff)) {
        if (!preg_match('/^[0-9]{10,15}$/', $telepon_staff)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon harus terdiri dari 10-15 digit angka!';
            header("Location: ../../staff.php");
            exit();
        }
        
        // Cek duplikat di tabel data_staff (kecuali staff yang sedang diedit)
        $check_phone_staff = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '" . $db->koneksi->real_escape_string($telepon_staff) . "' AND id_user != $id_user");
        $staff_count = 0;
        if ($check_phone_staff && $check_phone_staff->num_rows > 0) {
            $row = $check_phone_staff->fetch_assoc();
            $staff_count = $row['count'];
        }
        
        // Cek duplikat di tabel data_dokter
        $check_phone_dokter = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '" . $db->koneksi->real_escape_string($telepon_staff) . "'");
        $dokter_count = 0;
        if ($check_phone_dokter && $check_phone_dokter->num_rows > 0) {
            $row = $check_phone_dokter->fetch_assoc();
            $dokter_count = $row['count'];
        }
        
        // Buat pesan error spesifik
        if ($staff_count > 0 || $dokter_count > 0) {
            $duplicate_in = array();
            if ($staff_count > 0) $duplicate_in[] = 'Staff';
            if ($dokter_count > 0) $duplicate_in[] = 'Dokter';
            
            $location_text = implode(' dan ', $duplicate_in);
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon "' . $telepon_staff . '" sudah terdaftar di data ' . $location_text . '. Silakan gunakan nomor telepon lain.';
            header("Location: ../../staff.php");
            exit();
        }
    }
    
    // Ambil data lama
    $old_data = $db->get_staff_by_id($id_user);
    $current_foto = $old_data['foto_staff'] ?? null;
    
    // Default gunakan foto lama
    $foto_staff = $current_foto;
    
    // Handle upload foto baru
    if (isset($_FILES['foto_staff']) && $_FILES['foto_staff']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_staff'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        // Validasi ukuran file (max 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file_size > $max_size) {
            $_SESSION['notif_status'] = 'warning';
            $_SESSION['notif_message'] = 'Ukuran file terlalu besar. Maksimal 2MB. Foto lama tetap digunakan.';
        } else {
            // Validasi ekstensi file
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Generate nama file baru yang unik
                $new_file_name = 'staff_' . $id_user . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                
                // Pastikan folder upload ada
                $upload_dir = '../../image-staff/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Path lengkap untuk upload
                $upload_path = $upload_dir . $new_file_name;
                
                // Coba upload file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $foto_staff = $new_file_name;
                    
                    // Hapus foto lama jika ada
                    if ($current_foto && file_exists($upload_dir . $current_foto) && $current_foto != $new_file_name) {
                        unlink($upload_dir . $current_foto);
                    }
                } else {
                    $_SESSION['notif_status'] = 'warning';
                    $_SESSION['notif_message'] = 'Gagal mengupload foto. Foto lama tetap digunakan.';
                }
            } else {
                $_SESSION['notif_status'] = 'warning';
                $_SESSION['notif_message'] = 'Format file tidak didukung. Hanya JPG, PNG, GIF, WEBP yang diperbolehkan. Foto lama tetap digunakan.';
            }
        }
    }
    
    // Update data staff di database
    $update_staff_result = $db->edit_data_staff(
        $id_user, 
        $kode_staff, 
        $jabatan_staff, 
        $foto_staff, 
        $nama_staff, 
        $jenis_kelamin_staff,
        $tanggal_lahir_staff,
        $alamat_staff, 
        $email, 
        $telepon_staff
    );
    
    if ($update_staff_result) {
        // Update nama di tabel users
        $db->update_nama_user($id_user, $nama_staff);
        
        // Update email di tabel users jika ada
        if (!empty($email)) {
            $db->koneksi->query("UPDATE users SET email = '" . $db->koneksi->real_escape_string($email) . "' WHERE id_user = $id_user");
        }
        
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Staff';
        $keterangan = "Staff '$nama_staff' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data staff berhasil diupdate.';
        
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data staff.';
    }
    
    header("Location: ../../staff.php");
    exit();
}
?>