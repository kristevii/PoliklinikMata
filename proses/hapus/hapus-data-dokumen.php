<?php
// Cek apakah session sudah ada, jika belum start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../../koneksi.php";
$db = new database();

// Validasi akses: Hanya Staff dengan jabatan IT Support
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk menghapus data dokumen.";
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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk menghapus data dokumen. Hanya Staff dengan jabatan IT Support yang diizinkan.";
    header("Location: ../../unautorized.php");
    exit();
}

// PROSES HAPUS DOKUMEN
if (isset($_GET['hapus'])) {
    $id_data_dokumen = $_GET['hapus'];
    
    // Validasi ID
    if (empty($id_data_dokumen) || !is_numeric($id_data_dokumen)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID dokumen tidak valid!';
        header("Location: ../../datadokumen.php");
        exit();
    }
    
    // Ambil data dokumen untuk hapus file
    $dokumen_data = $db->get_dokumen_by_id($id_data_dokumen);

    if ($dokumen_data) {
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
        
        function deleteOldFile($old_filename, $folder) {
            if (!empty($old_filename)) {
                $old_file_path = $folder . $old_filename;
                error_log("Attempting to delete old file: " . $old_file_path);
                
                if (file_exists($old_file_path)) {
                    if (unlink($old_file_path)) {
                        error_log("Successfully deleted old file: " . $old_file_path);
                        return true;
                    } else {
                        error_log("Failed to delete old file: " . $old_file_path);
                        return false;
                    }
                } else {
                    error_log("Old file not found: " . $old_file_path);
                    return true; // Return true karena file sudah tidak ada
                }
            }
            return true;
        }
        
        // Hapus file dokumen jika ada
        if (!empty($dokumen_data['file_dokumen'])) {
            $folder = getFolderByJenisDokumen($dokumen_data['id_dokumen']);
            deleteOldFile($dokumen_data['file_dokumen'], $folder);
        }
    }

    // Hapus data dari database
    if ($db->hapus_data_dokumen($id_data_dokumen)) {
        // Log aktivitas user
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Dokumen';
        $deskripsi = "Dokumen ID '{$dokumen_data['id_data_dokumen']}' berhasil dihapus oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $deskripsi, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data dokumen berhasil dihapus.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data dokumen.';
    }
    
    header("Location: ../../datadokumen.php");
    exit();
}
?>