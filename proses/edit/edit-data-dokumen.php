<?php
// Cek apakah session sudah ada, jika belum start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../../koneksi.php";
$db = new database();

// Validasi akses: Hanya Staff dengan jabatan IT Support
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengedit data dokumen.";
    header("Location: ../../unautorized.php");
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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengedit data dokumen. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES EDIT DOKUMEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_dokumen'])) {
    $id_data_dokumen = $_POST['id_data_dokumen'] ?? '';
    $id_dokumen = $_POST['id_dokumen'] ?? '';
    $kode_dokter = $_POST['kode_dokter'] ?? '';
    $kode_staff = $_POST['kode_staff'] ?? '';
    $status = $_POST['status'] ?? 'Berlaku';
    
    // Validasi input
    if (empty($id_data_dokumen) || empty($id_dokumen)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Dokumen wajib diisi!';
        header("Location: ../../datadokumen.php");
        exit();
    }
    
    if (!empty($kode_dokter) && !empty($kode_staff)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Hanya bisa memilih Dokter ATAU Staff, tidak boleh keduanya!';
        header("Location: ../../datadokumen.php");
        exit();
    }
    
    // Setup folder paths
    define('PDF_BASE_DIR', '../../dokumen/');
    define('STR_DIR', PDF_BASE_DIR . 'surat-tanda-registrasi/');
    define('SIP_DIR', PDF_BASE_DIR . 'surat-izin-praktik/');
    define('IJAZAH_DIR', PDF_BASE_DIR . 'ijazah-transkrip/');
    define('SERTIFIKAT_DIR', PDF_BASE_DIR . 'sertifikasi-kompetensi/');
    define('PELATIHAN_DIR', PDF_BASE_DIR . 'sertifikat-pelatihan-khusus/');
    define('KALIBRASI_DIR', PDF_BASE_DIR . 'sertifikat-kalibrasi-k3/');
    define('GP_SATPAM_DIR', PDF_BASE_DIR . 'sertifikat-gp-ka-satpam/');
    define('PORTOFOLIO_DIR', PDF_BASE_DIR . 'portofolio/');
    define('PENGALAMAN_DIR', PDF_BASE_DIR . 'surat-pengalaman-kerja/');
    define('SEHAT_DIR', PDF_BASE_DIR . 'sk-sehat-bebas-narkoba/');
    
    $folder_mapping = [
        1 => STR_DIR,
        2 => SIP_DIR,
        3 => IJAZAH_DIR,
        4 => SERTIFIKAT_DIR,
        5 => PELATIHAN_DIR,
        6 => KALIBRASI_DIR,
        7 => GP_SATPAM_DIR,
        8 => PORTOFOLIO_DIR,
        9 => PENGALAMAN_DIR,
        10 => SEHAT_DIR
    ];
    
    function getFolderByJenisDokumen($id_dokumen) {
        global $folder_mapping;
        return isset($folder_mapping[$id_dokumen]) ? $folder_mapping[$id_dokumen] : PDF_BASE_DIR;
    }
    
    function getFolderNameByJenisDokumen($id_dokumen) {
        $folder_names = [
            1 => 'STR',
            2 => 'SIP',
            3 => 'IJZ', 
            4 => 'SERT', 
            5 => 'PLT', 
            6 => 'KAL', 
            7 => 'SAT', 
            8 => 'PORT', 
            9 => 'PGL', 
            10 => 'SKH' 
        ];
        return isset($folder_names[$id_dokumen]) ? $folder_names[$id_dokumen] : 'DOC';
    }
    
    function handlePdfUpload($file_key, $folder, $id_dokumen, $kode_dokter = '', $kode_staff = '', $old_filename = null) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$file_key]['tmp_name'];
            $file_size = $_FILES[$file_key]['size'];
            $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'pdf') {
                if ($file_size <= 5242880) { // 5MB max
                    $prefix = getFolderNameByJenisDokumen($id_dokumen);
                    
                    // Tentukan owner code
                    $owner_code = '';
                    if (!empty($kode_dokter)) {
                        $owner_code = 'D' . substr($kode_dokter, -3);
                    } elseif (!empty($kode_staff)) {
                        $owner_code = 'S' . substr($kode_staff, -3);
                    }
                    
                    // Generate tanggal dan waktu
                    $date = date('ymd');
                    $time = substr(date('His'), 0, 2);
                    $random = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(3))), 0, 4);
                    
                    // Format nama file
                    if (!empty($owner_code)) {
                        $new_file_name = sprintf('%s_%s_%s%s_%s.pdf', $prefix, $owner_code, $date, $time, $random);
                    } else {
                        $new_file_name = sprintf('%s_%s%s_%s.pdf', $prefix, $date, $time, $random);
                    }
                    
                    // Pastikan maksimal 20 karakter
                    if (strlen($new_file_name) > 20) {
                        $new_file_name = substr($new_file_name, 0, 16) . '.pdf';
                    }
                    
                    $upload_path = $folder . $new_file_name;
                    
                    // Hapus file lama jika ada
                    if (!empty($old_filename)) {
                        $old_file_path = $folder . $old_filename;
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                    }
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        return $new_file_name;
                    }
                }
            } else {
                return false; // File terlalu besar
            }
        } else {
            return false; // Bukan file PDF
        }
        return null;
    }
    
    function deleteOldFile($old_filename, $folder) {
        if (!empty($old_filename)) {
            $old_file_path = $folder . $old_filename;
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }
        return true;
    }
    
    // Ambil data lama
    $old_data = $db->get_dokumen_by_id($id_data_dokumen);
    $old_folder = getFolderByJenisDokumen($old_data['id_dokumen'] ?? 0);
    $old_file = $old_data['file_dokumen'] ?? '';
    
    $new_folder = getFolderByJenisDokumen($id_dokumen);
    
    $file_dokumen = $old_file; // Default ke file lama
    
    // Proses upload file baru jika ada
    if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handlePdfUpload('file_dokumen', $new_folder, $id_dokumen, $kode_dokter, $kode_staff, $old_file);
        
        if ($upload_result === false) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'File harus PDF dan maksimal 5MB!';
            header("Location: ../../datadokumen.php");
            exit();
        } elseif ($upload_result !== null) {
            $file_dokumen = $upload_result;
            
            // Jika folder berubah, hapus file lama dari folder lama
            if ($old_folder !== $new_folder && !empty($old_file)) {
                deleteOldFile($old_file, $old_folder);
            }
        }
    }
    
    // Update data di database
    if ($db->edit_data_dokumen($id_data_dokumen, $id_dokumen, $kode_dokter, $kode_staff, $file_dokumen, $status)) {
        // Log aktivitas user
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'Dokumen';
        $keterangan = "Dokumen ID '$id_data_dokumen' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data dokumen berhasil diupdate.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data dokumen.';
    }
    
    header("Location: ../../datadokumen.php");
    exit();
}
?>