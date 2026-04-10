<?php
session_start();
require_once "koneksi.php"; // Pastikan path ke file koneksi.php benar
$db = new database();

// Validasi akses: Hanya Staff dengan jabatan tertentu yang bisa mengakses halaman ini
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

// Cek hak akses sesuai header.php: IT Support, Medical Record, dan Perawat Spesialis Mata yang bisa akses laporan
if ($jabatan_user != 'IT Support' && 
    $jabatan_user != 'Medical Record' && 
    $jabatan_user != 'Perawat Spesialis Mata') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk melihat laporan data pasien. Hanya Staff dengan jabatan IT Support, Medical Record, dan Perawat Spesialis Mata yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// KODE UTAMA HALAMAN (NON-EXPORT)
// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_pasien';

// Ambil semua data pasien
$all_pasien = $db->tampil_data_pasien();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_pasien = [];
    foreach ($all_pasien as $pasien) {
        // Cari di semua kolom yang relevan (termasuk NIK)
        if (stripos($pasien['id_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['nik'] ?? '', $search_query) !== false ||
            stripos($pasien['nama_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['jenis_kelamin_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['tgl_lahir_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['alamat_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['telepon_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['tanggal_registrasi_pasien'] ?? '', $search_query) !== false) {
            $filtered_pasien[] = $pasien;
        }
    }
    $all_pasien = $filtered_pasien;
}

// Filter berdasarkan jenis kelamin
if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
    $jenis_kelamin = trim($_GET['jenis_kelamin']);
    $all_pasien = array_filter($all_pasien, function($pasien) use ($jenis_kelamin) {
        return ($pasien['jenis_kelamin_pasien'] ?? '') == $jenis_kelamin;
    });
}

// Filter berdasarkan rentang tanggal lahir
if (isset($_GET['tgl_lahir_mulai']) && !empty($_GET['tgl_lahir_mulai'])) {
    $tgl_lahir_mulai = trim($_GET['tgl_lahir_mulai']);
    $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_lahir_mulai) {
        return strtotime($pasien['tgl_lahir_pasien'] ?? '') >= strtotime($tgl_lahir_mulai);
    });
}

if (isset($_GET['tgl_lahir_selesai']) && !empty($_GET['tgl_lahir_selesai'])) {
    $tgl_lahir_selesai = trim($_GET['tgl_lahir_selesai']);
    $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_lahir_selesai) {
        return strtotime($pasien['tgl_lahir_pasien'] ?? '') <= strtotime($tgl_lahir_selesai);
    });
}

// Filter berdasarkan rentang tanggal registrasi
if (isset($_GET['tgl_registrasi_mulai']) && !empty($_GET['tgl_registrasi_mulai'])) {
    $tgl_registrasi_mulai = trim($_GET['tgl_registrasi_mulai']);
    $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_registrasi_mulai) {
        return strtotime($pasien['tanggal_registrasi_pasien'] ?? '') >= strtotime($tgl_registrasi_mulai);
    });
}

if (isset($_GET['tgl_registrasi_selesai']) && !empty($_GET['tgl_registrasi_selesai'])) {
    $tgl_registrasi_selesai = trim($_GET['tgl_registrasi_selesai']);
    $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_registrasi_selesai) {
        return strtotime($pasien['tanggal_registrasi_pasien'] ?? '') <= strtotime($tgl_registrasi_selesai . ' 23:59:59');
    });
}

// Reset array keys setelah filter
$all_pasien = array_values($all_pasien);

// Urutkan data berdasarkan kolom yang dipilih
usort($all_pasien, function($a, $b) use ($sort_column, $sort_order) {
    $val_a = $a[$sort_column] ?? '';
    $val_b = $b[$sort_column] ?? '';
    
    // Handle numeric comparison untuk ID
    if ($sort_column == 'id_pasien') {
        if ($sort_order === 'desc') {
            return ($val_b - $val_a);
        } else {
            return ($val_a - $val_b);
        }
    }
    
    // String comparison untuk kolom lainnya
    if ($sort_order === 'desc') {
        return strcasecmp($val_b, $val_a);
    } else {
        return strcasecmp($val_a, $val_b);
    }
});

// Hitung total data
$total_entries = count($all_pasien);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_pasien = array_slice($all_pasien, $offset, $entries_per_page);

// Hitung nomor urut yang benar berdasarkan sorting
if ($sort_order === 'desc') {
    // Untuk descending: nomor urut dari total_entries ke bawah
    $start_number = $total_entries - $offset;
} else {
    // Untuk ascending: nomor urut dari 1 ke atas (default)
    $start_number = $offset + 1;
}

// Tampilkan notifikasi jika ada
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);
?>

<!DOCTYPE html>
<html lang="en">
  <!-- [Head] start -->

  <head>
    <title>Laporan Data Pasien - Sistem Informasi Poliklinik Mata Eyethica</title>
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description"
      content="Able Pro is a trending dashboard template built with the Bootstrap 5 design framework. It is available in multiple technologies, including Bootstrap, React, Vue, CodeIgniter, Angular, .NET, and more.">
    <meta name="keywords"
      content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel, react dashboard, angular dashboard">
    <meta name="author" content="Phoenixcoded">

    <!-- [Favicon] icon -->
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon"> <!-- [Font] Family -->
<link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
<!-- [Tabler Icons] https://tablericons.com -->
<link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
<!-- [Feather Icons] https://feathericons.com -->
<link rel="stylesheet" href="assets/fonts/feather.css" >
<!-- [Font Awesome Icons] https://fontawesome.com/icons -->
<link rel="stylesheet" href="assets/fonts/fontawesome.css" >
<!-- [Material Icons] https://fonts.google.com/icons -->
<link rel="stylesheet" href="assets/fonts/material.css" >
<!-- [Template CSS Files] -->
<link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
<link rel="stylesheet" href="assets/css/style-preset.css" >

<style>
/* Style dasar untuk modal */
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

.modal.fade .modal-dialog {
    transform: translate(0, -50px);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: none;
}

.modal-content {
    margin: auto;
}

/* Modal Backdrop */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal {
    backdrop-filter: blur(2px);
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

/* Animasi untuk modal */
.modal.fade .modal-dialog {
    transform: translateY(-50px);
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: translateY(0);
}

/* Tombol aksi */
.btn-hapus, .btn-edit {
    transition: all 0.2s ease;
}

.btn-hapus:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-edit:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

/* Badge jenis kelamin */
.badge-laki-laki {
    background-color: #0d6efd;
    color: #fff;
}

.badge-perempuan {
    background-color: #e83e8c;
    color: #fff;
}

/* Styling untuk tabel */
.table-responsive {
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.75rem;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}

/* Animasi untuk filter card */
#filterCard {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Styling untuk filter form */
#filterForm .form-label i {
    width: 20px;
    text-align: center;
}

/* Styling untuk active filters */
.alert-info {
    background-color: #e8f4fd;
    border-left: 4px solid #0d6efd;
}

/* Styling untuk tombol export */
.btn-export {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.btn-export:active {
    transform: translateY(0);
}

.btn-export i {
    margin-right: 8px;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    border: none;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    border: none;
    color: #212529;
}

.btn-warning:hover {
    color: #212529;
}

/* Styling untuk header kolom yang bisa diurutkan */
.sortable-header {
    cursor: pointer;
    transition: background-color 0.2s;
}

.sortable-header:hover {
    background-color: #e9ecef;
}

.sortable-header i {
    font-size: 0.8rem;
    margin-left: 4px;
    opacity: 0.6;
}
</style>
  </head>
  <!-- [Head] end -->
  <!-- [Body] Start -->

  <body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
<div class="loader-bg">
  <div class="loader-track">
    <div class="loader-fill"></div>
  </div>
</div>
<!-- [ Pre-loader ] End -->
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
                  <li class="breadcrumb-item" aria-current="page">Laporan Data Pasien</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Laporan Data Pasien</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
         <div class="container-fluid">
            <?php if ($notif_message): ?>
            <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($notif_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-start mb-4">
                <!-- Tombol Export Excel -->
                <a href="proses/cetak/cetak-laporan-data-pasien-excel.php?export_excel=1<?= getExportParams() ?>" class="btn btn-success me-2 btn-export">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </a>

                <!-- Tombol Export PDF -->
                <a href="proses/cetak/cetak-laporan-data-pasien-pdf.php?export_pdf=1<?= getExportParams() ?>" class="btn btn-danger me-2 btn-export">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </a>

                <!-- Tombol Cetak -->
                <a href="proses/cetak/cetak-laporan-data-pasien-cetak.php?cetak=1<?= getExportParams() ?>" class="btn btn-primary me-2 btn-export" target="_blank">
                    <i class="fas fa-print me-1"></i> Cetak
                </a>
                
                <!-- Tombol Filter -->
                <button type="button" class="btn btn-warning me-2" onclick="toggleFilterCard()">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>

            <!-- Filter Card -->
            <div id="filterCard" class="card shadow-sm mb-4 d-none">
                <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i> Filter Data Pasien
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="laporan-data-pasien.php" id="filterForm">
                        <div class="row">
                            <!-- Search Input -->
                            <div class="col-md-6 mb-3">
                                <label for="filterSearch" class="form-label">
                                    <i class="fas fa-search me-1"></i> Cari Pasien
                                </label>
                                <input type="text" class="form-control" id="filterSearch" name="search" 
                                       placeholder="Cari ID, NIK, Nama, Alamat, Telepon..." 
                                       value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                            
                            <!-- Jenis Kelamin -->
                            <div class="col-md-3 mb-3">
                                <label for="filterJenisKelamin" class="form-label">
                                    <i class="fas fa-venus-mars me-1"></i> Jenis Kelamin
                                </label>
                                <select class="form-select" id="filterJenisKelamin" name="jenis_kelamin">
                                    <option value="">Semua</option>
                                    <option value="L" <?= isset($_GET['jenis_kelamin']) && $_GET['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="P" <?= isset($_GET['jenis_kelamin']) && $_GET['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                            
                            <!-- Urutan -->
                            <div class="col-md-3 mb-3">
                                <label for="filterSort" class="form-label">
                                    <i class="fas fa-sort me-1"></i> Urutkan
                                </label>
                                <select class="form-select" id="filterSort" name="sort">
                                    <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>A-Z / Terlama</option>
                                    <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Z-A / Terbaru</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Rentang Tanggal Lahir -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-birthday-cake me-1"></i> Rentang Tanggal Lahir
                                </label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="date" class="form-control" id="filterTglLahirMulai" name="tgl_lahir_mulai" 
                                               value="<?= isset($_GET['tgl_lahir_mulai']) ? htmlspecialchars($_GET['tgl_lahir_mulai']) : '' ?>">
                                        <small class="form-text text-muted">Tanggal Mulai</small>
                                    </div>
                                    <div class="col">
                                        <input type="date" class="form-control" id="filterTglLahirSelesai" name="tgl_lahir_selesai" 
                                               value="<?= isset($_GET['tgl_lahir_selesai']) ? htmlspecialchars($_GET['tgl_lahir_selesai']) : '' ?>">
                                        <small class="form-text text-muted">Tanggal Selesai</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rentang Tanggal Registrasi -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> Rentang Tanggal Registrasi
                                </label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="date" class="form-control" id="filterTglRegistrasiMulai" name="tgl_registrasi_mulai" 
                                               value="<?= isset($_GET['tgl_registrasi_mulai']) ? htmlspecialchars($_GET['tgl_registrasi_mulai']) : '' ?>">
                                        <small class="form-text text-muted">Tanggal Mulai</small>
                                    </div>
                                    <div class="col">
                                        <input type="date" class="form-control" id="filterTglRegistrasiSelesai" name="tgl_registrasi_selesai" 
                                               value="<?= isset($_GET['tgl_registrasi_selesai']) ? htmlspecialchars($_GET['tgl_registrasi_selesai']) : '' ?>">
                                        <small class="form-text text-muted">Tanggal Selesai</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Kolom Sorting -->
                            <div class="col-md-6 mb-3">
                                <label for="filterSortColumn" class="form-label">
                                    <i class="fas fa-sort-amount-down me-1"></i> Urutkan Berdasarkan
                                </label>
                                <select class="form-select" id="filterSortColumn" name="sort_column">
                                    <option value="id_pasien" <?= $sort_column == 'id_pasien' ? 'selected' : '' ?>>ID Pasien</option>
                                    <option value="nama_pasien" <?= $sort_column == 'nama_pasien' ? 'selected' : '' ?>>Nama Pasien</option>
                                    <option value="tgl_lahir_pasien" <?= $sort_column == 'tgl_lahir_pasien' ? 'selected' : '' ?>>Tanggal Lahir</option>
                                    <option value="tanggal_registrasi_pasien" <?= $sort_column == 'tanggal_registrasi_pasien' ? 'selected' : '' ?>>Tanggal Registrasi</option>
                                </select>
                            </div>
                            
                            <!-- Tombol Aksi Filter -->
                            <div class="col-md-6 mb-3 d-flex align-items-end justify-content-end">
                                <div class="btn-group" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Terapkan Filter
                                    </button>
                                    <a href="laporan-data-pasien.php" class="btn btn-secondary">
                                        <i class="fas fa-redo me-1"></i> Reset
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" onclick="toggleFilterCard()">
                                        <i class="fas fa-times me-1"></i> Tutup
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Indikator Filter Aktif -->
                        <?php
                        $active_filters = [];
                        if (!empty($search_query)) $active_filters[] = "Pencarian: \"$search_query\"";
                        if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
                            $jk = $_GET['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
                            $active_filters[] = "Jenis Kelamin: $jk";
                        }
                        if (isset($_GET['tgl_lahir_mulai']) && !empty($_GET['tgl_lahir_mulai'])) $active_filters[] = "Tgl Lahir Mulai: " . htmlspecialchars($_GET['tgl_lahir_mulai']);
                        if (isset($_GET['tgl_lahir_selesai']) && !empty($_GET['tgl_lahir_selesai'])) $active_filters[] = "Tgl Lahir Selesai: " . htmlspecialchars($_GET['tgl_lahir_selesai']);
                        if (isset($_GET['tgl_registrasi_mulai']) && !empty($_GET['tgl_registrasi_mulai'])) $active_filters[] = "Tgl Registrasi Mulai: " . htmlspecialchars($_GET['tgl_registrasi_mulai']);
                        if (isset($_GET['tgl_registrasi_selesai']) && !empty($_GET['tgl_registrasi_selesai'])) $active_filters[] = "Tgl Registrasi Selesai: " . htmlspecialchars($_GET['tgl_registrasi_selesai']);
                        ?>
                        
                        <?php if (!empty($active_filters)): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="alert alert-info alert-dismissible fade show py-2" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Filter Aktif:</strong> 
                                    <?= implode(', ', $active_filters) ?>
                                    <a href="laporan-data-pasien.php" class="btn btn-sm btn-outline-danger ms-3">
                                        <i class="fas fa-times me-1"></i> Hapus Semua Filter
                                    </a>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="pasienTable" class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th class="sortable-header" onclick="sortBy('id_pasien')">
                                        ID
                                        <?php if ($sort_column == 'id_pasien'): ?>
                                            <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort text-muted"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>NIK</th>
                                    <th class="sortable-header" onclick="sortBy('nama_pasien')">
                                        Nama
                                        <?php if ($sort_column == 'nama_pasien'): ?>
                                            <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort text-muted"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Jenis Kelamin</th>
                                    <th class="sortable-header" onclick="sortBy('tgl_lahir_pasien')">
                                        Tanggal Lahir
                                        <?php if ($sort_column == 'tgl_lahir_pasien'): ?>
                                            <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort text-muted"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Alamat</th>
                                    <th>Telepon</th>
                                    <th class="sortable-header" onclick="sortBy('tanggal_registrasi_pasien')">
                                        Tanggal Registrasi
                                        <?php if ($sort_column == 'tanggal_registrasi_pasien'): ?>
                                            <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort text-muted"></i>
                                        <?php endif; ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_pasien) && is_array($data_pasien)) {
                                    foreach ($data_pasien as $pasien) {
                                        $id_pasien = htmlspecialchars($pasien['id_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nik = htmlspecialchars($pasien['nik'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nama_pasien = htmlspecialchars($pasien['nama_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $jenis_kelamin = htmlspecialchars($pasien['jenis_kelamin_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tgl_lahir = htmlspecialchars($pasien['tgl_lahir_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $alamat = htmlspecialchars($pasien['alamat_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $telepon = htmlspecialchars($pasien['telepon_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tgl_registrasi = htmlspecialchars($pasien['tanggal_registrasi_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        
                                        // Format NIK (tampilkan bintang untuk privasi jika perlu)
                                        $nik_display = !empty($nik) ? $nik : '-';
                                        
                                        // Format jenis kelamin
                                        $jenis_kelamin_text = $jenis_kelamin == 'L' ? 'Laki-laki' : ($jenis_kelamin == 'P' ? 'Perempuan' : '-');
                                        $jk_badge_class = $jenis_kelamin == 'L' ? 'badge-laki-laki' : ($jenis_kelamin == 'P' ? 'badge-perempuan' : 'badge-secondary');
                                        
                                        // Format tanggal lahir
                                        $tgl_lahir_formatted = !empty($tgl_lahir) ? date('d-m-Y', strtotime($tgl_lahir)) : '-';
                                        
                                        // Format tanggal registrasi
                                        $tgl_registrasi_formatted = !empty($tgl_registrasi) ? date('d-m-Y H:i:s', strtotime($tgl_registrasi)) : '-';
                                        
                                        // Format alamat (potong jika terlalu panjang)
                                        $alamat_short = strlen($alamat) > 30 ? substr($alamat, 0, 30) . '...' : $alamat;
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_pasien ?></td>
                                        <td><?= $nik_display ?></td>
                                        <td><?= $nama_pasien ?: '-' ?></td>
                                        <td>
                                            <span class="badge <?= $jk_badge_class ?>"><?= $jenis_kelamin_text ?></span>
                                        </td>
                                        <td><?= $tgl_lahir_formatted ?></td>
                                        <td><?= $alamat_short ?: '-' ?></td>
                                        <td><?= $telepon ?: '-' ?></td>
                                        <td><?= $tgl_registrasi_formatted ?></td>
                                    </tr>
                                <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $start_number--; // Untuk descending: turun
                                        } else {
                                            $start_number++; // Untuk ascending: naik
                                        }
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center text-muted">';
                                    if (!empty($search_query)) {
                                        echo 'Tidak ada data pasien yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                    } else {
                                        echo 'Tidak ada data pasien ditemukan.';
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
                                <?php if ($sort_order === 'desc'): ?>
                                <span class="text-warning">(diurutkan dari terbaru)</span>
                                <?php else: ?>
                                <span class="text-warning">(diurutkan dari terlama)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <!-- Previous Page -->
                                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order, $sort_column) : '#' ?>">
                                            Sebelumnya
                                        </a>
                                    </li>
                                    
                                    <!-- Page Numbers -->
                                    <?php
                                    // Selalu tampilkan halaman 1
                                    echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order, $sort_column) . '">1</a>';
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
                                            echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order, $sort_column) . '">' . $i . '</a>';
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
                                        echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order, $sort_column) . '">' . $total_pages . '</a>';
                                        echo '</li>';
                                    }
                                    ?>
                                    
                                    <!-- Next Page -->
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order, $sort_column) : '#' ?>">
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

    <!-- Required Js -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>
     <!-- Buy Now Link Script -->
    <script defer src="https://fomo.codedthemes.com/pixel/CDkpF1sQ8Tt5wpMZgqRvKpQiUhpWE3bc"></script>

    <script>
    // Function untuk menampilkan/sembunyikan filter card
    function toggleFilterCard() {
        const filterCard = document.getElementById('filterCard');
        if (filterCard) {
            filterCard.classList.toggle('d-none');
            
            // Scroll ke filter card jika ditampilkan
            if (!filterCard.classList.contains('d-none')) {
                filterCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        const sort_column = '<?= $sort_column ?>';
        let url = 'laporan-data-pasien.php?entries=' + entries + '&page=1&sort=' + sort + '&sort_column=' + sort_column;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }

    // Function untuk sorting
    function sortBy(column) {
        const currentSort = '<?= $sort_order ?>';
        const currentColumn = '<?= $sort_column ?>';
        const search = '<?= $search_query ?>';
        const entries = '<?= $entries_per_page ?>';
        
        // Toggle sort order jika kolom sama, otherwise default to asc
        let newSort = 'asc';
        if (column === currentColumn) {
            newSort = currentSort === 'asc' ? 'desc' : 'asc';
        }
        
        // Build URL dengan parameter yang ada
        let url = 'laporan-data-pasien.php?';
        let params = [];
        
        params.push('sort_column=' + column);
        params.push('sort=' + newSort);
        
        if (entries != 10) {
            params.push('entries=' + entries);
        }
        
        if (search) {
            params.push('search=' + encodeURIComponent(search));
        }
        
        // Tambahkan semua parameter filter
        const filterParams = ['jenis_kelamin', 'tgl_lahir_mulai', 'tgl_lahir_selesai', 'tgl_registrasi_mulai', 'tgl_registrasi_selesai'];
        filterParams.forEach(param => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has(param)) {
                params.push(param + '=' + encodeURIComponent(urlParams.get(param)));
            }
        });
        
        window.location.href = url + params.join('&');
    }

    // Function untuk loading saat export
    function showExportLoading(type) {
        const buttons = document.querySelectorAll('.btn-export');
        buttons.forEach(button => {
            const originalHTML = button.innerHTML;
            button.disabled = true;
            
            // Reset setelah 3 detik jika masih disabled
            setTimeout(() => {
                if (button.disabled) {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
            }, 3000);
        });
        
        // Notifikasi
        const messages = {
            'excel': 'Sedang menyiapkan file Excel...',
            'pdf': 'Sedang menyiapkan file PDF...',
            'cetak': 'Membuka halaman cetak...'
        };
        
        if (messages[type]) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info alert-dismissible fade show mt-3';
            alertDiv.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                ${messages[type]}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    }

    // Event listener untuk tombol export
    document.addEventListener('DOMContentLoaded', function() {
        const excelBtn = document.querySelector('a[href*="export_excel"]');
        const pdfBtn = document.querySelector('a[href*="export_pdf"]');
        const printBtn = document.querySelector('a[href*="cetak"]');
        
        if (excelBtn) {
            excelBtn.addEventListener('click', function() {
                showExportLoading('excel');
            });
        }
        
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function() {
                showExportLoading('pdf');
            });
        }
        
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                showExportLoading('cetak');
            });
        }

        // Auto tampilkan filter card jika ada parameter filter
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilterParams = ['jenis_kelamin', 'tgl_lahir_mulai', 'tgl_lahir_selesai', 'tgl_registrasi_mulai', 'tgl_registrasi_selesai'].some(param => urlParams.has(param));
        if (hasFilterParams) {
            // Tunggu sebentar agar DOM selesai di-render
            setTimeout(() => {
                const filterCard = document.getElementById('filterCard');
                if (filterCard) {
                    filterCard.classList.remove('d-none');
                }
            }, 100);
        }
    });
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
<script>preset_change("preset-1");</script>
    
  </body>
  <!-- [Body] end -->
</html>

<?php
// Fungsi untuk mendapatkan parameter filter untuk export
function getExportParams() {
    $params = [];
    
    // Parameter filter yang mungkin ada
    $filter_params = [
        'search', 'jenis_kelamin', 'tgl_lahir_mulai', 'tgl_lahir_selesai',
        'tgl_registrasi_mulai', 'tgl_registrasi_selesai', 'sort', 'sort_column'
    ];
    
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $params ? '&' . implode('&', $params) : '';
}

// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc', $sort_column = 'id_pasien') {
    $url = 'laporan-data-pasien.php?';
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
    
    if ($sort_column != 'id_pasien') {
        $params[] = 'sort_column=' . $sort_column;
    }
    
    // Tambahkan semua parameter filter
    $filter_params = ['jenis_kelamin', 'tgl_lahir_mulai', 'tgl_lahir_selesai', 'tgl_registrasi_mulai', 'tgl_registrasi_selesai'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url . implode('&', $params);
}
?>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>