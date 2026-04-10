<?php
session_start();
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support yang bisa mengakses halaman ini
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: dashboard.php");
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

if ($jabatan_user != 'IT Support' && 
    $jabatan_user != 'Administrasi') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: unautorized.php");
    exit();
}

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PDF_BASE_DIR', 'dokumen/');
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

// Pastikan semua folder ada
foreach ($folder_mapping as $id => $folder_path) {
    if (!is_dir($folder_path)) {
        if (!mkdir($folder_path, 0777, true)) {
            error_log("Error: Gagal membuat folder $folder_path");
        }
    }
}

// Pastikan folder base juga ada
if (!is_dir(PDF_BASE_DIR)) {
    mkdir(PDF_BASE_DIR, 0777, true);
}

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

function getFullFolderNameByJenisDokumen($id_dokumen) {
    $folder_names = [
        1 => 'surat-tanda-registrasi',
        2 => 'surat-izin-praktik',
        3 => 'ijazah-transkrip',
        4 => 'sertifikasi-kompetensi',
        5 => 'sertifikat-pelatihan-khusus',
        6 => 'sertifikat-kalibrasi-k3',
        7 => 'sertifikat-gp-ka-satpam',
        8 => 'portofolio',
        9 => 'surat-pengalaman-kerja',
        10 => 'sk-sehat-bebas-narkoba'
    ];
    
    return isset($folder_names[$id_dokumen]) ? $folder_names[$id_dokumen] : 'dokumen-umum';
}

// REVISI: Fungsi upload dengan format nama file dan maksimal 20 karakter
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
                    $owner_code = 'D' . substr($kode_dokter, -3); // D + 3 karakter terakhir
                } elseif (!empty($kode_staff)) {
                    $owner_code = 'S' . substr($kode_staff, -3); // S + 3 karakter terakhir
                }
                
                // Generate tanggal dan waktu (YYMMDDHH)
                $date = date('ymd'); // YYMMDD
                $time = substr(date('His'), 0, 2); // HH saja
                
                // Generate random string (4 karakter)
                $random = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(3))), 0, 4);
                
                // Format: foldershort_ownercode_date_time_random.pdf
                if (!empty($owner_code)) {
                    $new_file_name = sprintf(
                        '%s_%s_%s%s_%s.pdf',
                        $prefix,
                        $owner_code,
                        $date,
                        $time,
                        $random
                    );
                } else {
                    $new_file_name = sprintf(
                        '%s_%s%s_%s.pdf',
                        $prefix,
                        $date,
                        $time,
                        $random
                    );
                }
                
                // REVISI: Pastikan maksimal 20 karakter
                if (strlen($new_file_name) > 20) {
                    // Jika lebih dari 20 karakter, potong dan pastikan masih ada ekstensi .pdf
                    $new_file_name = substr($new_file_name, 0, 16) . '.pdf';
                }
                
                $upload_path = $folder . $new_file_name;
                
                // Hapus file lama jika ada
                if (!empty($old_filename)) {
                    $old_file_path = $folder . $old_filename;
                    if (file_exists($old_file_path)) {
                        if (unlink($old_file_path)) {
                            error_log("File lama berhasil dihapus: " . $old_file_path);
                        } else {
                            error_log("Gagal menghapus file lama: " . $old_file_path);
                        }
                    } else {
                        error_log("File lama tidak ditemukan: " . $old_file_path);
                    }
                }
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    error_log("File baru berhasil diupload: " . $upload_path);
                    return $new_file_name;
                } else {
                    error_log("Gagal mengupload file baru ke: " . $upload_path);
                }
            } else {
                error_log("File terlalu besar: " . $file_size . " bytes");
                return false; // File terlalu besar
            }
        } else {
            error_log("File bukan PDF: " . $file_ext);
            return false; // Bukan file PDF
        }
    } else {
        $error_code = $_FILES[$file_key]['error'] ?? 'unknown';
        error_log("Upload error: " . $error_code);
    }
    return null;
}

// Fungsi untuk menghapus file lama
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

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

// Ambil semua data dokumen
$all_dokumen = $db->tampil_data_dokumen();

// Ambil data untuk dropdown
$all_dokter = $db->tampil_data_dokter();
$all_staff = $db->tampil_data_staff();
$all_jenis_dokumen = $db->tampil_jenis_dokumen();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_dokumen = [];
    foreach ($all_dokumen as $dokumen) {
        if (stripos($dokumen['id_data_dokumen'] ?? '', $search_query) !== false ||
            stripos($dokumen['nama_dokumen'] ?? '', $search_query) !== false ||
            stripos($dokumen['id_dokumen'] ?? '', $search_query) !== false ||
            stripos($dokumen['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($dokumen['kode_staff'] ?? '', $search_query) !== false ||
            stripos($dokumen['file_dokumen'] ?? '', $search_query) !== false ||
            stripos($dokumen['status'] ?? '', $search_query) !== false) {
            $filtered_dokumen[] = $dokumen;
        }
    }
    $all_dokumen = $filtered_dokumen;
}

// Filter data berdasarkan status jika dipilih
if (!empty($filter_status)) {
    $filtered_dokumen_by_status = [];
    foreach ($all_dokumen as $dokumen) {
        if ($dokumen['status'] === $filter_status) {
            $filtered_dokumen_by_status[] = $dokumen;
        }
    }
    $all_dokumen = $filtered_dokumen_by_status;
}

// Urutkan data berdasarkan ID Dokumen
if ($sort_order === 'desc') {
    usort($all_dokumen, function($a, $b) {
        return ($b['id_data_dokumen'] ?? 0) - ($a['id_data_dokumen'] ?? 0);
    });
} else {
    usort($all_dokumen, function($a, $b) {
        return ($a['id_data_dokumen'] ?? 0) - ($b['id_data_dokumen'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_dokumen);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_dokumen = array_slice($all_dokumen, $offset, $entries_per_page);

// Hitung nomor urut
if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

// Tampilkan notifikasi jika ada
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dokumen - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
    <link rel="stylesheet" href="assets/fonts/feather.css" >
    <link rel="stylesheet" href="assets/fonts/fontawesome.css" >
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css" >
    
    <style>
    .badge-file {
        background-color: #17a2b8;
        color: #fff;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: help;
        display: inline-block;
        border-radius: 0.25rem;
    }

    .badge-file:hover {
        overflow: visible;
        white-space: normal;
        word-break: break-all;
        z-index: 1000;
        position: relative;
        max-width: 300px;
    }

    .badge-berlaku {
        background-color: #28a745;
        color: #fff;
    }

    .badge-tidak-berlaku {
        background-color: #dc3545;
        color: #fff;
    }

    .badge-pdf {
        background-color: #e63946;
        color: #fff;
    }

    .file-actions {
        margin-top: 10px;
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .modal-dialog {
        display: flex;
        align-items: center;
        min-height: calc(100% - 1rem);
    }

    @media (min-width: 576px) {
        .modal-dialog {
            min-height: calc(100% - 3.5rem);
        }
    }

    .modal-content {
        margin: auto;
    }

    .btn-hapus, .btn-edit {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-hapus:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }

    .btn-edit:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }

    .table th {
        border-top: none;
        font-weight: 600;
        white-space: nowrap;
    }

    .table td {
        vertical-align: middle;
    }

    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }
        
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .file-actions {
            flex-direction: column;
        }
        
        .badge-file {
            max-width: 150px;
        }
    }

    .form-file-info {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .form-file-requirements {
        background-color: #f8f9fa;
        border-left: 4px solid #17a2b8;
        padding: 0.75rem;
        margin-top: 0.5rem;
        border-radius: 0.25rem;
    }

    .file-size-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
        background-color: #6c757d;
        color: white;
        border-radius: 0.25rem;
    }
    
    .folder-badge {
        background-color: #6f42c1;
        color: white;
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        margin-top: 0.25rem;
        display: inline-block;
    }
    
    .folder-info-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .folder-info-text strong {
        color: #495057;
    }
    
    .exclusive-select-alert {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 0.75rem;
        border-radius: 0.375rem;
        margin-top: 0.5rem;
        font-size: 0.875rem;
    }
    
    .exclusive-select-alert i {
        color: #ffc107;
    }
    
    .owner-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        margin-top: 0.25rem;
        display: inline-block;
    }
    
    .owner-dokter {
        background-color: #0d6efd;
        color: white;
    }
    
    .owner-staff {
        background-color: #198754;
        color: white;
    }
    
    .owner-none {
        background-color: #6c757d;
        color: white;
    }
    
    .nama-pemilik {
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .kode-pemilik {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    .file-name-preview {
        background-color: #f8f9fa;
        padding: 0.5rem;
        border-radius: 0.25rem;
        margin-top: 0.5rem;
        font-size: 0.8rem;
        border-left: 3px solid #17a2b8;
        word-break: break-all;
    }
    
    .file-name-preview strong {
        color: #495057;
        font-family: monospace;
    }
    
    .file-name-tooltip {
        position: relative;
        cursor: help;
    }
    
    .file-name-tooltip:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: #fff;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        white-space: normal;
        max-width: 300px;
        z-index: 1000;
        margin-bottom: 5px;
    }
    
    .filename-example {
        background-color: #f8f9fa;
        padding: 5px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 0.75rem;
        margin-top: 5px;
        border: 1px dashed #dee2e6;
        color: #495057;
    }
    
    .filename-structure {
        font-size: 0.7rem;
        color: #6c757d;
        margin-top: 5px;
    }
    
    .file-format-badge {
        background-color: #6c757d;
        color: white;
        font-size: 0.65rem;
        padding: 0.15rem 0.3rem;
        border-radius: 0.2rem;
        font-family: monospace;
        margin-top: 2px;
    }
    
    .filename-short {
        font-family: 'Courier New', monospace;
        font-size: 0.7rem;
        color: #495057;
    }
    
    .filename-length {
        font-size: 0.7rem;
        color: #28a745;
        font-weight: bold;
    }
    
    .filename-length-warning {
        font-size: 0.7rem;
        color: #dc3545;
        font-weight: bold;
    }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <?php include 'header.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
      <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                  <li class="breadcrumb-item" aria-current="page">Data Dokumen Dokter & Staff</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Data Dokumen Dokter & Staff</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        
        <!-- [ Main Content ] start -->
        <div class="container-fluid">
            <?php if ($notif_message): ?>
            <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert" id="autoDismissAlert">
                <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($notif_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <script>
                // Auto dismiss notifikasi setelah 5 detik dengan animasi
                setTimeout(function() {
                    var alert = document.getElementById('autoDismissAlert');
                    if (alert) {
                        alert.classList.remove('show');
                        setTimeout(function() {
                            if (alert && alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 150);
                        if (typeof bootstrap !== 'undefined') {
                            try {
                                var bsAlert = bootstrap.Alert.getInstance(alert);
                                if (bsAlert) {
                                    bsAlert.close();
                                } else {
                                    bsAlert = new bootstrap.Alert(alert);
                                    bsAlert.close();
                                }
                            } catch (e) {}
                        }
                    }
                }, 5000);
            </script>
            <?php endif; ?>

            <div class="d-flex justify-content-start mb-4">
                <!-- Tombol Tambah Dokumen dengan Modal -->
                <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahDokumenModal">
                    <i class="fas fa-plus me-1"></i> Tambah Dokumen
                </button>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <!-- Show Entries, Search, dan Filter Status -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <label class="me-2 mb-0">Show</label>
                                <select class="form-select form-select-sm w-auto" id="entriesPerPage" onchange="changeEntries()">
                                    <option value="5" <?= $entries_per_page == 5 ? 'selected' : '' ?>>5</option>
                                    <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $entries_per_page == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <label class="ms-2 mb-0">entries</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <label class="me-2 mb-0">Filter Status</label>
                                <select class="form-select form-select-sm w-auto" id="filterStatus" onchange="filterByStatus()">
                                    <option value="">Semua Status</option>
                                    <option value="Berlaku" <?= $filter_status == 'Berlaku' ? 'selected' : '' ?>>Berlaku</option>
                                    <option value="Tidak Berlaku" <?= $filter_status == 'Tidak Berlaku' ? 'selected' : '' ?>>Tidak Berlaku</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <form method="GET" action="" class="d-flex justify-content-end">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           placeholder="Cari data dokumen..." 
                                           value="<?= htmlspecialchars($search_query) ?>"
                                           aria-label="Search">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                    <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                    <?php if ($filter_status): ?>
                                    <input type="hidden" name="filter_status" value="<?= $filter_status ?>">
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="datadokumen.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?><?= $filter_status ? '&filter_status=' . urlencode($filter_status) : '' ?>" class="btn btn-outline-danger" type="button">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($search_query)): ?>
                    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Menampilkan hasil pencarian untuk: <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($filter_status)): ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-filter me-2"></i>
                        Menampilkan dokumen dengan status: <strong>"<?= htmlspecialchars($filter_status) ?>"</strong>
                        <a href="datadokumen.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>" class="btn-close"></a>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="dokumenTable" class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <a href="<?= getSortUrl($sort_order) ?>" class="text-decoration-none text-dark">
                                            No 
                                            <?php if ($sort_order === 'asc'): ?>
                                                <i class="fas fa-sort-up ms-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort-down ms-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>ID</th>
                                    <th>Jenis Dokumen</th>
                                    <th>Pemilik Dokumen</th>
                                    <th>File Dokumen</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_dokumen) && is_array($data_dokumen)) {
                                    foreach ($data_dokumen as $dokumen) {
                                        $id_data_dokumen = htmlspecialchars($dokumen['id_data_dokumen'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $id_dokumen = htmlspecialchars($dokumen['id_dokumen'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nama_dokumen = 'Tidak Diketahui';
                                        foreach ($all_jenis_dokumen as $jenis) {
                                            if ($jenis['id_dokumen'] == $id_dokumen) {
                                                $nama_dokumen = htmlspecialchars($jenis['nama_dokumen'] ?? '');
                                                break;
                                            }
                                        }
                                        $kode_dokter = htmlspecialchars($dokumen['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $kode_staff = htmlspecialchars($dokumen['kode_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $file_dokumen = htmlspecialchars($dokumen['file_dokumen'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $status = htmlspecialchars($dokumen['status'] ?? 'Berlaku', ENT_QUOTES, 'UTF-8');
                                        $nama_dokter = '';
                                        $nama_staff = '';
                                        if (!empty($kode_dokter)) {
                                            foreach ($all_dokter as $dokter) {
                                                if ($dokter['kode_dokter'] == $kode_dokter) {
                                                    $nama_dokter = htmlspecialchars($dokter['nama_dokter']);
                                                    break;
                                                }
                                            }
                                        }
                                        if (!empty($kode_staff)) {
                                            foreach ($all_staff as $staff) {
                                                if ($staff['kode_staff'] == $kode_staff) {
                                                    $nama_staff = htmlspecialchars($staff['nama_staff']);
                                                    break;
                                                }
                                            }
                                        }
                                        $status_badge_class = 'badge-secondary';
                                        switch ($status) {
                                            case 'Berlaku':
                                                $status_badge_class = 'badge-berlaku';
                                                break;
                                            case 'Tidak Berlaku':
                                                $status_badge_class = 'badge-tidak-berlaku';
                                                break;
                                        }
                                        
                                        // Tentukan path file
                                        $folder_name = getFullFolderNameByJenisDokumen($id_dokumen);
                                        $folder_path = getFolderByJenisDokumen($id_dokumen);
                                        $file_path = $folder_path . $file_dokumen;
                                        $file_exists = file_exists($file_path);
                                        
                                        // Hitung panjang nama file
                                        $filename_length = strlen($file_dokumen);
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_data_dokumen ?></td>
                                        <td>
                                            <strong><?= $nama_dokumen ?></strong><br>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <?php if (!empty($kode_dokter)): ?>
                                                    <div>
                                                        <?php if ($nama_dokter): ?>
                                                            <div class="nama-pemilik"><?= $nama_dokter ?></div>
                                                        <?php else: ?>
                                                            <div class="nama-pemilik text-muted">Dokter tidak ditemukan</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif (!empty($kode_staff)): ?>
                                                    <div>
                                                        <?php if ($nama_staff): ?>
                                                            <div class="nama-pemilik"><?= $nama_staff ?></div>
                                                        <?php else: ?>
                                                            <div class="nama-pemilik text-muted">Staff tidak ditemukan</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="owner-badge owner-none">
                                                        <i class="fas fa-question-circle me-1"></i>Tidak Ada Pemilik
                                                    </span>
                                                    <div class="text-muted small mt-1">-</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($file_dokumen): ?>
                                                <div class="d-flex flex-column">
                                                    <span class="badge badge-file file-name-tooltip" title="<?= htmlspecialchars($file_dokumen) ?>">
                                                        <i class="fas fa-file-pdf me-1"></i><?= htmlspecialchars($file_dokumen) ?>
                                                    </span>
                                                </div>
                                                <div class="file-actions mt-2">
                                                    <?php if ($file_exists): ?>
                                                    <a href="<?= $folder_name . '/' . $file_dokumen ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary btn-view-file"
                                                       title="Lihat File"
                                                       data-id-dokumen="<?= $id_dokumen ?>"
                                                       data-file-name="<?= $file_dokumen ?>">
                                                        <i class="fas fa-eye"></i> Lihat
                                                    </a>
                                                    <a href="<?= $folder_name . '/' . $file_dokumen ?>" 
                                                       download 
                                                       class="btn btn-sm btn-outline-success btn-download-file"
                                                       title="Download File"
                                                       data-id-dokumen="<?= $id_dokumen ?>"
                                                       data-file-name="<?= $file_dokumen ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="text-danger small">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>File tidak ditemukan
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Tidak ada file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_badge_class ?>">
                                                <?php if ($status === 'Berlaku'): ?>
                                                    <i class="fas fa-check-circle me-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle me-1"></i>
                                                <?php endif; ?>
                                                <?= $status ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn btn-warning btn-sm btn-edit"
                                                        data-id="<?= $id_data_dokumen ?>"
                                                        data-iddokumen="<?= $id_dokumen ?>"
                                                        data-dokter="<?= $kode_dokter ?>"
                                                        data-staff="<?= $kode_staff ?>"
                                                        data-file="<?= $file_dokumen ?>"
                                                        data-status="<?= $status ?>"
                                                        title="Edit Dokumen">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-hapus"
                                                        data-id="<?= $id_data_dokumen ?>"
                                                        title="Hapus Dokumen">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $start_number--;
                                        } else {
                                            $start_number++;
                                        }
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center text-muted">';
                                    if (!empty($search_query) || !empty($filter_status)) {
                                        $filters = [];
                                        if (!empty($search_query)) $filters[] = 'pencarian "' . htmlspecialchars($search_query) . '"';
                                        if (!empty($filter_status)) $filters[] = 'status "' . htmlspecialchars($filter_status) . '"';
                                        echo 'Tidak ada data dokumen yang sesuai dengan ' . implode(' dan ', $filters);
                                    } else {
                                        echo 'Tidak ada data dokumen ditemukan.';
                                    }
                                    echo '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination - SELALU TAMPIL JIKA ADA DATA -->
                    <?php if ($total_entries > 0): ?>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-0">
                                Menampilkan <?= $total_entries > 0 ? ($offset + 1) : 0 ?> 
                                sampai <?= min($offset + $entries_per_page, $total_entries) ?> 
                                dari <?= $total_entries ?> entri
                                <?php if (!empty($search_query)): ?>
                                <span class="text-info">(hasil pencarian)</span>
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                <span class="text-warning">(status: <?= htmlspecialchars($filter_status) ?>)</span>
                                <?php endif; ?>
                                <?php if ($sort_order === 'desc'): ?>
                                <span class="text-secondary">(diurutkan dari terbaru)</span>
                                <?php else: ?>
                                <span class="text-secondary">(diurutkan dari terlama)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <!-- Previous Page -->
                                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order, $filter_status) : '#' ?>">
                                            Sebelumnya
                                        </a>
                                    </li>
                                    
                                    <!-- Page Numbers dengan format: Sebelumnya | 1 | 2 3 4 5... 11 Selanjutnya -->
                                    <?php
                                    // Selalu tampilkan halaman 1
                                    echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order, $filter_status) . '">1</a>';
                                    echo '</li>';
                                    
                                    // Tentukan range halaman yang akan ditampilkan
                                    $start = 2;
                                    $end = min(5, $total_pages - 1);
                                    
                                    // Jika current page > 3, adjust the range
                                    if ($current_page > 3) {
                                        $start = $current_page - 1;
                                        $end = min($current_page + 2, $total_pages - 1);
                                    }
                                    
                                    // Tampilkan ellipsis jika ada gap
                                    if ($start > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    // Tampilkan halaman-halaman
                                    for ($i = $start; $i <= $end; $i++) {
                                        if ($i < $total_pages) {
                                            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order, $filter_status) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                    }
                                    
                                    // Tampilkan ellipsis sebelum halaman terakhir jika perlu
                                    if ($end < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    // Tampilkan halaman terakhir jika lebih dari 1 halaman
                                    if ($total_pages > 1) {
                                        echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order, $filter_status) . '">' . $total_pages . '</a>';
                                        echo '</li>';
                                    }
                                    ?>
                                    
                                    <!-- Next Page -->
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order, $filter_status) : '#' ?>">
                                            Selanjutnya
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <?php else: ?>
                            <!-- Tampilkan pagination sederhana jika hanya 1 halaman -->
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Sebelumnya</a>
                                    </li>
                                    <li class="page-item active">
                                        <a class="page-link" href="#">1</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Selanjutnya</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Tambah Dokumen -->
    <div class="modal fade" id="tambahDokumenModal" tabindex="-1" aria-labelledby="tambahDokumenModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahDokumenModalLabel">
                        <i class="fas fa-file-upload me-2"></i>Tambah Dokumen Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-dokumen.php" id="tambahDokumenForm" enctype="multipart/form-data">
                    <input type="hidden" name="tambah_dokumen" value="1">
                    <div class="modal-body">
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="id_dokumen" class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                                    <select class="form-select" id="id_dokumen" name="id_dokumen" required>
                                        <option value="">Pilih Jenis Dokumen</option>
                                        <?php foreach ($all_jenis_dokumen as $jenis): ?>
                                            <option value="<?= htmlspecialchars($jenis['id_dokumen'] ?? '') ?>">
                                                <?= htmlspecialchars($jenis['nama_dokumen'] ?? '') ?> 
                                                (<?= getFolderNameByJenisDokumen($jenis['id_dokumen'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="folderInfo" class="mt-2" style="display: none;">
                                        <small class="folder-info-text">
                                            <i class="fas fa-folder me-1"></i>
                                            File akan disimpan di folder: <strong id="folderName"></strong>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="file_dokumen" class="form-label">File Dokumen <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="file_dokumen" name="file_dokumen" 
                                           accept=".pdf" required>
                                    <div class="form-file-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Hanya file PDF yang diperbolehkan (maks. 5MB)
                                    </div>
                                    <div id="fileNamePreview" class="mt-2" style="display: none;">
                                        <div class="file-name-preview">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            Nama file akan menjadi: <br>
                                            <strong id="previewFileName" class="file-name-tooltip"></strong>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="filename-example" id="filenameExample"></small>
                                                <span id="filenameLength" class="filename-length"></span>
                                            </div>
                                            <div class="filename-structure mt-2">
                                                Format: <code>foldershort_ownercode_YYMMDDHH_random.pdf</code> (maks. 20 karakter)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kode_dokter" class="form-label">Dokter</label>
                                    <select class="form-select" id="kode_dokter" name="kode_dokter">
                                        <option value="">Pilih Dokter</option>
                                        <?php foreach ($all_dokter as $dokter): ?>
                                            <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?> (<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kode_staff" class="form-label">Staff</label>
                                    <select class="form-select" id="kode_staff" name="kode_staff">
                                        <option value="">Pilih Staff</option>
                                        <?php foreach ($all_staff as $staff): ?>
                                            <option value="<?= htmlspecialchars($staff['kode_staff'] ?? '') ?>">
                                                <?= htmlspecialchars($staff['nama_staff'] ?? '') ?> (<?= htmlspecialchars($staff['kode_staff'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="exclusive-select-alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Penting:</strong> Hanya bisa memilih Dokter ATAU Staff, tidak boleh keduanya!
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status Dokumen</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Berlaku" selected>Berlaku</option>
                                        <option value="Tidak Berlaku">Tidak Berlaku</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnTambahDokumen">
                            <i class="fas fa-save me-1"></i>Simpan Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Dokumen -->
    <div class="modal fade" id="editDokumenModal" tabindex="-1" aria-labelledby="editDokumenModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDokumenModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Dokumen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-dokumen.php" id="editDokumenForm" enctype="multipart/form-data">
                    <input type="hidden" name="edit_dokumen" value="1">
                    <input type="hidden" id="edit_id_data_dokumen" name="id_data_dokumen">
                    
                    <div class="modal-body">
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_id_dokumen" class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_id_dokumen" name="id_dokumen" required>
                                        <option value="">Pilih Jenis Dokumen</option>
                                        <?php foreach ($all_jenis_dokumen as $jenis): ?>
                                            <option value="<?= htmlspecialchars($jenis['id_dokumen'] ?? '') ?>">
                                                <?= htmlspecialchars($jenis['nama_dokumen'] ?? '') ?> 
                                                (<?= getFolderNameByJenisDokumen($jenis['id_dokumen'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="editFolderInfo" class="mt-2" style="display: none;">
                                        <small class="folder-info-text">
                                            <i class="fas fa-folder me-1"></i>
                                            File akan disimpan di folder: <strong id="editFolderName"></strong>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_file_dokumen" class="form-label">File Dokumen Baru</label>
                                    <input type="file" class="form-control" id="edit_file_dokumen" name="file_dokumen" 
                                           accept=".pdf">
                                    <div class="form-file-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Biarkan kosong jika tidak ingin mengubah file
                                    </div>
                                    <div id="currentFileInfo" class="mt-2"></div>
                                    <div id="editFileNamePreview" class="mt-2" style="display: none;">
                                        <div class="file-name-preview">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            Nama file baru akan menjadi: <br>
                                            <strong id="previewEditFileName" class="file-name-tooltip"></strong>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="filename-example" id="editFilenameExample"></small>
                                                <span id="editFilenameLength" class="filename-length"></span>
                                            </div>
                                            <div class="filename-structure mt-2">
                                                Format: <code>foldershort_ownercode_YYMMDDHH_random.pdf</code> (maks. 20 karakter)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_dokter" class="form-label">Dokter</label>
                                    <select class="form-select" id="edit_kode_dokter" name="kode_dokter">
                                        <option value="">Pilih Dokter</option>
                                        <?php foreach ($all_dokter as $dokter): ?>
                                            <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?> (<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_staff" class="form-label">Staff</label>
                                    <select class="form-select" id="edit_kode_staff" name="kode_staff">
                                        <option value="">Pilih Staff</option>
                                        <?php foreach ($all_staff as $staff): ?>
                                            <option value="<?= htmlspecialchars($staff['kode_staff'] ?? '') ?>">
                                                <?= htmlspecialchars($staff['nama_staff'] ?? '') ?> (<?= htmlspecialchars($staff['kode_staff'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="exclusive-select-alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Penting:</strong> Hanya bisa memilih Dokter ATAU Staff, tidak boleh keduanya!
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status Dokumen</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="Berlaku">Berlaku</option>
                                        <option value="Tidak Berlaku">Tidak Berlaku</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateDokumen">
                            <i class="fas fa-save me-1"></i>Update Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Dokumen -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-labelledby="hapusModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    </div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus dokumen:</p>
                    <h5 class="text-center text-danger" id="idDokumenHapus"></h5>
                    <p class="text-center text-muted mt-3">
                        <small>Data yang dihapus tidak dapat dikembalikan, termasuk file PDF yang terkait.</small>
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <a href="#" id="hapusButton" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>

    <script>
    // Mapping folder yang sesuai dengan PHP
    const folderMapping = {
        1: 'STR',
        2: 'SIP', 
        3: 'IJZ',
        4: 'SERT',
        5: 'PLT',
        6: 'KAL',
        7: 'SAT',
        8: 'PORT',
        9: 'PGL',
        10: 'SKH'
    };

    const fullFolderMapping = {
        1: 'surat-tanda-registrasi',
        2: 'surat-izin-praktik',
        3: 'ijazah-transkrip',
        4: 'sertifikasi-kompetensi',
        5: 'sertifikat-pelatihan-khusus',
        6: 'sertifikat-kalibrasi-k3',
        7: 'sertifikat-gp-ka-satpam',
        8: 'portofolio',
        9: 'surat-pengalaman-kerja',
        10: 'sk-sehat-bebas-narkoba'
    };

    // Function untuk mendapatkan nama folder singkat berdasarkan ID
    function getFolderNameById(idDokumen) {
        return folderMapping[idDokumen] || 'DOC';
    }

    // Function untuk mendapatkan nama folder lengkap berdasarkan ID
    function getFullFolderNameById(idDokumen) {
        return fullFolderMapping[idDokumen] || 'dokumen-umum';
    }

    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        const filterStatus = '<?= $filter_status ?>';
        
        let url = 'datadokumen.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        if (filterStatus) {
            url += '&filter_status=' + encodeURIComponent(filterStatus);
        }
        
        window.location.href = url;
    }

    // Function untuk filter berdasarkan status
    function filterByStatus() {
        const status = document.getElementById('filterStatus').value;
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        
        let url = 'datadokumen.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        if (status) {
            url += '&filter_status=' + encodeURIComponent(status);
        }
        
        window.location.href = url;
    }

    // Function untuk menampilkan modal hapus
    function showHapusModal(id) {
        document.getElementById('idDokumenHapus').textContent = 'ID ' + id;
        document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-dokumen.php?hapus=' + id;
        
        const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
        hapusModal.show();
    }

    // Function untuk preview file
    function previewCurrentFile(idDokumen, fileName) {
        const folder = getFullFolderNameById(idDokumen);
        const fileUrl = `dokumen/${folder}/${fileName}`;
        window.open(fileUrl, '_blank');
    }

    // Function untuk download file
    function downloadCurrentFile(idDokumen, fileName) {
        const folder = getFullFolderNameById(idDokumen);
        const fileUrl = `dokumen/${folder}/${fileName}`;
        
        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Function untuk generate preview nama file (maksimal 20 karakter)
    function generateFileNamePreview(idDokumen, kodeDokter, kodeStaff) {
        const folderName = getFolderNameById(idDokumen);
        
        let ownerCode = '';
        
        if (kodeDokter) {
            ownerCode = 'D' + kodeDokter.substring(kodeDokter.length - 3);
        } else if (kodeStaff) {
            ownerCode = 'S' + kodeStaff.substring(kodeStaff.length - 3);
        }
        
        const now = new Date();
        const dateStr = now.getFullYear().toString().slice(-2) + 
                       (now.getMonth() + 1).toString().padStart(2, '0') + 
                       now.getDate().toString().padStart(2, '0');
        const timeStr = now.getHours().toString().padStart(2, '0');
        const randomStr = Math.random().toString(36).substring(2, 6).toUpperCase();
        
        let fileName = '';
        if (ownerCode) {
            fileName = `${folderName}_${ownerCode}_${dateStr}${timeStr}_${randomStr}.pdf`;
        } else {
            fileName = `${folderName}_${dateStr}${timeStr}_${randomStr}.pdf`;
        }
        
        if (fileName.length > 20) {
            fileName = fileName.substring(0, 16) + '.pdf';
        }
        
        return fileName;
    }

    // Function untuk generate contoh filename
    function generateExampleFilename(folderShort, kodeDokter, kodeStaff) {
        let ownerCode = '';
        let example = '';
        
        if (kodeDokter) {
            ownerCode = 'D' + kodeDokter.substring(kodeDokter.length - 3);
            example = `${folderShort}_${ownerCode}_25012712_A1b2.pdf`;
        } else if (kodeStaff) {
            ownerCode = 'S' + kodeStaff.substring(kodeStaff.length - 3);
            example = `${folderShort}_${ownerCode}_25012712_A1b2.pdf`;
        } else {
            example = `${folderShort}_25012712_A1b2.pdf`;
        }
        
        if (example.length > 20) {
            example = example.substring(0, 16) + '.pdf';
        }
        
        return example;
    }

    // Function untuk menampilkan modal edit
    function showEditModal(id, iddokumen, dokter, staff, file, status) {
        document.getElementById('edit_id_data_dokumen').value = id;
        document.getElementById('edit_id_dokumen').value = iddokumen || '';
        
        if (dokter) {
            document.getElementById('edit_kode_dokter').value = dokter;
            document.getElementById('edit_kode_staff').value = '';
        } else if (staff) {
            document.getElementById('edit_kode_staff').value = staff;
            document.getElementById('edit_kode_dokter').value = '';
        } else {
            document.getElementById('edit_kode_dokter').value = '';
            document.getElementById('edit_kode_staff').value = '';
        }
        
        document.getElementById('edit_status').value = status || 'Berlaku';
        
        updateEditFolderInfo(iddokumen);
        
        const fileInfo = document.getElementById('currentFileInfo');
        if (file) {
            const folderName = getFullFolderNameById(iddokumen);
            const filenameLength = file.length;
            fileInfo.innerHTML = `
                <div class="alert alert-info p-2">
                    <i class="fas fa-file-pdf me-2 text-danger"></i>
                    <strong>File saat ini:</strong><br>
                    <div class="mt-1">
                        <code class="filename-short">${file}</code>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted">
                            <i class="fas fa-folder me-1"></i>
                            Folder: ${folderName}
                        </small>
                        <span class="filename-length ${filenameLength > 20 ? 'filename-length-warning' : ''}">
                            ${filenameLength} karakter
                        </span>
                    </div>
                    <div class="mt-2">
                        <button type="button" onclick="previewCurrentFile(${iddokumen}, '${file}')" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> Lihat
                        </button>
                        <button type="button" onclick="downloadCurrentFile(${iddokumen}, '${file}')" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            `;
        } else {
            fileInfo.innerHTML = `
                <div class="alert alert-warning p-2">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Tidak ada file saat ini
                </div>
            `;
        }
        
        const editModal = new bootstrap.Modal(document.getElementById('editDokumenModal'));
        editModal.show();
    }

    function updateFolderInfo() {
        const idDokumen = document.getElementById('id_dokumen').value;
        const folderInfo = document.getElementById('folderInfo');
        const folderName = document.getElementById('folderName');
        const fileNamePreview = document.getElementById('fileNamePreview');
        const previewFileName = document.getElementById('previewFileName');
        const filenameExample = document.getElementById('filenameExample');
        const filenameLength = document.getElementById('filenameLength');
        
        if (idDokumen) {
            const folder = getFullFolderNameById(idDokumen);
            folderName.textContent = folder;
            folderInfo.style.display = 'block';
            
            const kodeDokter = document.getElementById('kode_dokter').value;
            const kodeStaff = document.getElementById('kode_staff').value;
            const fileName = generateFileNamePreview(idDokumen, kodeDokter, kodeStaff);
            const fileNameLengthValue = fileName.length;
            
            if (previewFileName) {
                previewFileName.textContent = fileName;
                previewFileName.title = fileName;
                previewFileName.className = 'file-name-tooltip';
                
                const folderShort = getFolderNameById(idDokumen);
                const example = generateExampleFilename(folderShort, kodeDokter, kodeStaff);
                
                if (filenameExample) {
                    filenameExample.textContent = `Contoh: ${example}`;
                }
                
                if (filenameLength) {
                    filenameLength.textContent = `${fileNameLengthValue} karakter`;
                    filenameLength.className = fileNameLengthValue > 20 ? 'filename-length-warning' : 'filename-length';
                }
                
                if (fileNamePreview) {
                    fileNamePreview.style.display = 'block';
                }
            }
        } else {
            folderInfo.style.display = 'none';
            if (fileNamePreview) {
                fileNamePreview.style.display = 'none';
            }
        }
    }

    function updateEditFolderInfo(idDokumen) {
        const folderInfo = document.getElementById('editFolderInfo');
        const folderName = document.getElementById('editFolderName');
        const fileNamePreview = document.getElementById('editFileNamePreview');
        const previewFileName = document.getElementById('previewEditFileName');
        const editFilenameExample = document.getElementById('editFilenameExample');
        const editFilenameLength = document.getElementById('editFilenameLength');
        
        if (idDokumen) {
            const folder = getFullFolderNameById(idDokumen);
            folderName.textContent = folder;
            folderInfo.style.display = 'block';
            
            const fileInput = document.getElementById('edit_file_dokumen');
            if (fileInput.files.length > 0) {
                const kodeDokter = document.getElementById('edit_kode_dokter').value;
                const kodeStaff = document.getElementById('edit_kode_staff').value;
                const fileName = generateFileNamePreview(idDokumen, kodeDokter, kodeStaff);
                const fileNameLengthValue = fileName.length;
                
                if (previewFileName) {
                    previewFileName.textContent = fileName;
                    previewFileName.title = fileName;
                    previewFileName.className = 'file-name-tooltip';
                    
                    const folderShort = getFolderNameById(idDokumen);
                    const example = generateExampleFilename(folderShort, kodeDokter, kodeStaff);
                    
                    if (editFilenameExample) {
                        editFilenameExample.textContent = `Contoh: ${example}`;
                    }
                    
                    if (editFilenameLength) {
                        editFilenameLength.textContent = `${fileNameLengthValue} karakter`;
                        editFilenameLength.className = fileNameLengthValue > 20 ? 'filename-length-warning' : 'filename-length';
                    }
                    
                    if (fileNamePreview) {
                        fileNamePreview.style.display = 'block';
                    }
                }
            } else {
                if (fileNamePreview) {
                    fileNamePreview.style.display = 'none';
                }
            }
        } else {
            folderInfo.style.display = 'none';
            if (fileNamePreview) {
                fileNamePreview.style.display = 'none';
            }
        }
    }

    function validateExclusiveSelection(kodeDokter, kodeStaff, isEdit = false) {
        if (kodeDokter && kodeStaff) {
            alert('Hanya bisa memilih Dokter ATAU Staff, tidak boleh keduanya!');
            return false;
        }
        return true;
    }

    function handleFormSubmit(e, buttonId) {
        e.preventDefault();
        
        let kodeDokter, kodeStaff;
        
        if (buttonId === 'btnTambahDokumen') {
            kodeDokter = document.getElementById('kode_dokter').value;
            kodeStaff = document.getElementById('kode_staff').value;
        } else {
            kodeDokter = document.getElementById('edit_kode_dokter').value;
            kodeStaff = document.getElementById('edit_kode_staff').value;
        }
        
        if (!validateExclusiveSelection(kodeDokter, kodeStaff, buttonId === 'btnUpdateDokumen')) {
            return;
        }
        
        const fileInput = buttonId === 'btnTambahDokumen' 
            ? document.getElementById('file_dokumen')
            : document.getElementById('edit_file_dokumen');
            
        if (buttonId === 'btnTambahDokumen' && !fileInput.files[0]) {
            alert('File dokumen wajib diupload!');
            return;
        }
        
        if (fileInput.files[0]) {
            const fileName = fileInput.files[0].name;
            const fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt !== 'pdf') {
                alert('Hanya file PDF yang diperbolehkan!');
                return;
            }
            
            const fileSize = fileInput.files[0].size;
            const maxSize = 5 * 1024 * 1024;
            
            if (fileSize > maxSize) {
                alert('Ukuran file maksimal 5MB!');
                return;
            }
        }
        
        const submitButton = document.getElementById(buttonId);
        const originalText = submitButton.innerHTML;
        
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
        submitButton.disabled = true;
        
        setTimeout(() => {
            e.target.submit();
        }, 500);
    }

    function resetOtherDropdown(selectedId, otherId) {
        const selected = document.getElementById(selectedId);
        const other = document.getElementById(otherId);
        
        selected.addEventListener('change', function() {
            if (this.value) {
                other.value = '';
                if (selectedId === 'kode_dokter' || selectedId === 'kode_staff') {
                    updateFolderInfo();
                } else if (selectedId === 'edit_kode_dokter' || selectedId === 'edit_kode_staff') {
                    const idDokumen = document.getElementById('edit_id_dokumen').value;
                    if (idDokumen) {
                        updateEditFolderInfo(idDokumen);
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        resetOtherDropdown('kode_dokter', 'kode_staff');
        resetOtherDropdown('kode_staff', 'kode_dokter');
        resetOtherDropdown('edit_kode_dokter', 'edit_kode_staff');
        resetOtherDropdown('edit_kode_staff', 'edit_kode_dokter');

        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-hapus')) {
                e.preventDefault();
                const button = e.target.closest('.btn-hapus');
                const id = button.getAttribute('data-id');
                showHapusModal(id);
            }
        });

        document.addEventListener('click', function(e) {
            const editButton = e.target.closest('.btn-edit');
            if (editButton) {
                e.preventDefault();
                e.stopPropagation();
                
                const id = editButton.getAttribute('data-id');
                const iddokumen = editButton.getAttribute('data-iddokumen');
                const dokter = editButton.getAttribute('data-dokter');
                const staff = editButton.getAttribute('data-staff');
                const file = editButton.getAttribute('data-file');
                const status = editButton.getAttribute('data-status');
                
                showEditModal(id, iddokumen, dokter, staff, file, status);
            }
        });

        document.getElementById('id_dokumen').addEventListener('change', updateFolderInfo);
        document.getElementById('kode_dokter').addEventListener('change', updateFolderInfo);
        document.getElementById('kode_staff').addEventListener('change', updateFolderInfo);
        
        document.getElementById('edit_id_dokumen').addEventListener('change', function() {
            updateEditFolderInfo(this.value);
        });
        
        document.getElementById('edit_kode_dokter').addEventListener('change', function() {
            const idDokumen = document.getElementById('edit_id_dokumen').value;
            if (idDokumen) {
                updateEditFolderInfo(idDokumen);
            }
        });
        
        document.getElementById('edit_kode_staff').addEventListener('change', function() {
            const idDokumen = document.getElementById('edit_id_dokumen').value;
            if (idDokumen) {
                updateEditFolderInfo(idDokumen);
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-view-file')) {
                e.preventDefault();
                const button = e.target.closest('.btn-view-file');
                const idDokumen = button.getAttribute('data-id-dokumen');
                const fileName = button.getAttribute('data-file-name');
                
                if (idDokumen && fileName) {
                    previewCurrentFile(parseInt(idDokumen), fileName);
                }
            }
            
            if (e.target.closest('.btn-download-file')) {
                e.preventDefault();
                const button = e.target.closest('.btn-download-file');
                const idDokumen = button.getAttribute('data-id-dokumen');
                const fileName = button.getAttribute('data-file-name');
                
                if (idDokumen && fileName) {
                    downloadCurrentFile(parseInt(idDokumen), fileName);
                }
            }
        });

        const tambahForm = document.getElementById('tambahDokumenForm');
        if (tambahForm) {
            tambahForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnTambahDokumen');
            });
        }

        const editForm = document.getElementById('editDokumenForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnUpdateDokumen');
            });
        }

        const fileInputTambah = document.getElementById('file_dokumen');
        if (fileInputTambah) {
            fileInputTambah.addEventListener('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    
                    if (fileSize > 5) {
                        alert('Ukuran file maksimal 5MB!');
                        e.target.value = '';
                    } else {
                        updateFolderInfo();
                    }
                }
            });
        }

        const fileInputEdit = document.getElementById('edit_file_dokumen');
        if (fileInputEdit) {
            fileInputEdit.addEventListener('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    
                    if (fileSize > 5) {
                        alert('Ukuran file maksimal 5MB!');
                        e.target.value = '';
                    } else {
                        const idDokumen = document.getElementById('edit_id_dokumen').value;
                        if (idDokumen) {
                            updateEditFolderInfo(idDokumen);
                        }
                    }
                }
            });
        }

        const tambahDokumenModal = document.getElementById('tambahDokumenModal');
        if (tambahDokumenModal) {
            tambahDokumenModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('tambahDokumenForm').reset();
                document.getElementById('folderInfo').style.display = 'none';
                document.getElementById('fileNamePreview').style.display = 'none';
                const submitButton = document.getElementById('btnTambahDokumen');
                if (submitButton) {
                    submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Simpan Dokumen';
                    submitButton.disabled = false;
                }
            });
        }
        
        const editDokumenModal = document.getElementById('editDokumenModal');
        if (editDokumenModal) {
            editDokumenModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('editFolderInfo').style.display = 'none';
                document.getElementById('editFileNamePreview').style.display = 'none';
                document.getElementById('currentFileInfo').innerHTML = '';
                const submitButton = document.getElementById('btnUpdateDokumen');
                if (submitButton) {
                    submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Update Dokumen';
                    submitButton.disabled = false;
                }
            });
        }
    });
    </script>
</body>
</html>

<?php
// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc', $filter_status = '') {
    $url = 'datadokumen.php?';
    $params = [];
    
    if ($page > 1) {
        $params[] = 'page=' . $page;
    }
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if ($sort != 'asc') {
        $params[] = 'sort=' . $sort;
    }
    
    if (!empty($filter_status)) {
        $params[] = 'filter_status=' . urlencode($filter_status);
    }
    
    return $url . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $url = 'datadokumen.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if (!empty($filter_status)) {
        $params[] = 'filter_status=' . urlencode($filter_status);
    }
    
    // Toggle sort order
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>