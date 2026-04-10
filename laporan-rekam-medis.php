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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk melihat laporan rekam medis. Hanya Staff dengan jabatan IT Support, Medical Record, dan Perawat Spesialis Mata yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// KODE UTAMA HALAMAN (NON-EXPORT)
// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_rekam';

// Ambil semua data rekam medis
$all_rekam_medis = $db->tampil_data_rekam_medis();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_rekam_medis = [];
    foreach ($all_rekam_medis as $rekam) {
        // Cari di semua kolom yang relevan
        if (stripos($rekam['id_rekam'] ?? '', $search_query) !== false ||
            stripos($rekam['id_pasien'] ?? '', $search_query) !== false ||
            stripos($rekam['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($rekam['jenis_kunjungan'] ?? '', $search_query) !== false ||
            stripos($rekam['tanggal_periksa'] ?? '', $search_query) !== false) {
            $filtered_rekam_medis[] = $rekam;
        }
    }
    $all_rekam_medis = $filtered_rekam_medis;
}

// Filter berdasarkan rentang tanggal periksa
if (isset($_GET['tgl_periksa_mulai']) && !empty($_GET['tgl_periksa_mulai'])) {
    $tgl_periksa_mulai = trim($_GET['tgl_periksa_mulai']);
    $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($tgl_periksa_mulai) {
        return strtotime($rekam['tanggal_periksa'] ?? '') >= strtotime($tgl_periksa_mulai);
    });
}

if (isset($_GET['tgl_periksa_selesai']) && !empty($_GET['tgl_periksa_selesai'])) {
    $tgl_periksa_selesai = trim($_GET['tgl_periksa_selesai']);
    $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($tgl_periksa_selesai) {
        return strtotime($rekam['tanggal_periksa'] ?? '') <= strtotime($tgl_periksa_selesai . ' 23:59:59');
    });
}

// Filter berdasarkan ID Pasien
if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) {
    $id_pasien = trim($_GET['id_pasien']);
    $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($id_pasien) {
        return ($rekam['id_pasien'] ?? '') == $id_pasien;
    });
}

// Filter berdasarkan Kode Dokter
if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) {
    $kode_dokter = trim($_GET['kode_dokter']);
    $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($kode_dokter) {
        return ($rekam['kode_dokter'] ?? '') == $kode_dokter;
    });
}

// Filter berdasarkan Jenis Kunjungan
if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) {
    $jenis_kunjungan = trim($_GET['jenis_kunjungan']);
    $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($jenis_kunjungan) {
        return ($rekam['jenis_kunjungan'] ?? '') == $jenis_kunjungan;
    });
}

// Reset array keys setelah filter
$all_rekam_medis = array_values($all_rekam_medis);

// Hitung statistik untuk footer
$total_pasien_unik = count(array_unique(array_column($all_rekam_medis, 'id_pasien')));
$total_dokter_unik = count(array_unique(array_column($all_rekam_medis, 'kode_dokter')));
$total_jenis_baru = count(array_filter($all_rekam_medis, function($rekam) {
    return ($rekam['jenis_kunjungan'] ?? '') == 'Baru';
}));
$total_jenis_kontrol = count(array_filter($all_rekam_medis, function($rekam) {
    return ($rekam['jenis_kunjungan'] ?? '') == 'Kontrol';
}));

// Urutkan data berdasarkan kolom yang dipilih
usort($all_rekam_medis, function($a, $b) use ($sort_column, $sort_order) {
    $val_a = $a[$sort_column] ?? '';
    $val_b = $b[$sort_column] ?? '';
    
    // Handle numeric comparison untuk ID
    if (in_array($sort_column, ['id_rekam', 'id_pasien'])) {
        $val_a = (int) $val_a;
        $val_b = (int) $val_b;
        if ($sort_order === 'desc') {
            return ($val_b - $val_a);
        } else {
            return ($val_a - $val_b);
        }
    }
    
    // Handle date comparison untuk tanggal
    if ($sort_column == 'tanggal_periksa') {
        $time_a = strtotime($val_a);
        $time_b = strtotime($val_b);
        if ($sort_order === 'desc') {
            return ($time_b - $time_a);
        } else {
            return ($time_a - $time_b);
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
$total_entries = count($all_rekam_medis);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_rekam_medis = array_slice($all_rekam_medis, $offset, $entries_per_page);

// Hitung nomor urut yang benar berdasarkan sorting
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
    <title>Laporan Rekam Medis - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/fonts/feather.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome.css">
    <link rel="stylesheet" href="assets/fonts/material.css">
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link">
    <link rel="stylesheet" href="assets/css/style-preset.css">
    <style>
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
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
    }
    .modal {
        backdrop-filter: blur(2px);
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
    .badge-baru {
        background-color: #28a745;
        color: #fff;
        padding: 0.25em 0.65em;
        font-size: 0.75em;
        border-radius: 20px;
    }
    .badge-kontrol {
        background-color: #17a2b8;
        color: #fff;
        padding: 0.25em 0.65em;
        font-size: 0.75em;
        border-radius: 20px;
    }
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
    .btn-export {
        transition: all 0.3s ease;
    }
    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
    
    /* Style untuk footer info box */
    .info-box {
        background-color: #e7f3ff;
        border-left: 4px solid #2196f3;
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 20px;
    }
    .info-box p {
        margin-bottom: 0;
    }
    .info-box i {
        margin-right: 8px;
        color: #2196f3;
    }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include 'header.php'; ?>

    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">Laporan Rekam Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Laporan Rekam Medis</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($notif_message): ?>
                <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($notif_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-start mb-4 flex-wrap gap-2">
                    <a href="proses/cetak/cetak-laporan-rekam-medis-excel.php?export_excel=1<?= getExportParams() ?>" class="btn btn-success btn-export">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </a>
                    <a href="proses/cetak/cetak-laporan-rekam-medis-pdf.php?export_pdf=1<?= getExportParams() ?>" class="btn btn-danger btn-export">
                        <i class="fas fa-file-pdf me-1"></i> PDF
                    </a>
                    <a href="proses/cetak/cetak-laporan-rekam-medis-cetak.php?cetak=1<?= getExportParams() ?>" class="btn btn-primary btn-export" target="_blank">
                        <i class="fas fa-print me-1"></i> Cetak
                    </a>
                    <button type="button" class="btn btn-warning" onclick="toggleFilterCard()">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>

                <!-- Filter Card -->
                <div id="filterCard" class="card shadow-sm mb-4 d-none">
                    <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filter Rekam Medis</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="laporan-rekam-medis.php" id="filterForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="filterSearch" class="form-label"><i class="fas fa-search me-1"></i> Cari</label>
                                    <input type="text" class="form-control" id="filterSearch" name="search" 
                                           placeholder="Cari ID Rekam, ID Pasien, Kode Dokter, Jenis Kunjungan..." 
                                           value="<?= htmlspecialchars($search_query) ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="filterSort" class="form-label"><i class="fas fa-sort me-1"></i> Urutkan</label>
                                    <select class="form-select" id="filterSort" name="sort">
                                        <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>Terlama</option>
                                        <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Terbaru</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="filterEntries" class="form-label"><i class="fas fa-list me-1"></i> Tampilkan</label>
                                    <select class="form-select" id="filterEntries" name="entries" onchange="changeEntries()">
                                        <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10 entri</option>
                                        <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25 entri</option>
                                        <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50 entri</option>
                                        <option value="100" <?= $entries_per_page == 100 ? 'selected' : '' ?>>100 entri</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><i class="fas fa-calendar-check me-1"></i> Rentang Tanggal Periksa</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="date" class="form-control" name="tgl_periksa_mulai" 
                                                   value="<?= isset($_GET['tgl_periksa_mulai']) ? htmlspecialchars($_GET['tgl_periksa_mulai']) : '' ?>"
                                                   placeholder="Tanggal Mulai">
                                        </div>
                                        <div class="col">
                                            <input type="date" class="form-control" name="tgl_periksa_selesai" 
                                                   value="<?= isset($_GET['tgl_periksa_selesai']) ? htmlspecialchars($_GET['tgl_periksa_selesai']) : '' ?>"
                                                   placeholder="Tanggal Selesai">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="filterIdPasien" class="form-label"><i class="fas fa-id-card me-1"></i> ID Pasien</label>
                                    <input type="text" class="form-control" name="id_pasien" 
                                           placeholder="ID Pasien" 
                                           value="<?= isset($_GET['id_pasien']) ? htmlspecialchars($_GET['id_pasien']) : '' ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="filterKodeDokter" class="form-label"><i class="fas fa-user-md me-1"></i> Kode Dokter</label>
                                    <input type="text" class="form-control" name="kode_dokter" 
                                           placeholder="Kode Dokter" 
                                           value="<?= isset($_GET['kode_dokter']) ? htmlspecialchars($_GET['kode_dokter']) : '' ?>">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="filterJenisKunjungan" class="form-label"><i class="fas fa-tag me-1"></i> Jenis Kunjungan</label>
                                    <select class="form-select" name="jenis_kunjungan">
                                        <option value="">Semua</option>
                                        <option value="Baru" <?= (isset($_GET['jenis_kunjungan']) && $_GET['jenis_kunjungan'] == 'Baru') ? 'selected' : '' ?>>Baru</option>
                                        <option value="Kontrol" <?= (isset($_GET['jenis_kunjungan']) && $_GET['jenis_kunjungan'] == 'Kontrol') ? 'selected' : '' ?>>Kontrol</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="filterSortColumn" class="form-label"><i class="fas fa-sort-amount-down me-1"></i> Urutkan Berdasarkan</label>
                                    <select class="form-select" name="sort_column">
                                        <option value="id_rekam" <?= $sort_column == 'id_rekam' ? 'selected' : '' ?>>ID Rekam</option>
                                        <option value="id_pasien" <?= $sort_column == 'id_pasien' ? 'selected' : '' ?>>ID Pasien</option>
                                        <option value="kode_dokter" <?= $sort_column == 'kode_dokter' ? 'selected' : '' ?>>Kode Dokter</option>
                                        <option value="tanggal_periksa" <?= $sort_column == 'tanggal_periksa' ? 'selected' : '' ?>>Tanggal Periksa</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3 d-flex align-items-end justify-content-end">
                                    <div class="btn-group" role="group">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Terapkan Filter</button>
                                        <a href="laporan-rekam-medis.php" class="btn btn-secondary"><i class="fas fa-redo me-1"></i> Reset</a>
                                        <button type="button" class="btn btn-outline-warning" onclick="toggleFilterCard()"><i class="fas fa-times me-1"></i> Tutup</button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php
                            $active_filters = [];
                            if (!empty($search_query)) $active_filters[] = "Pencarian: \"$search_query\"";
                            if (isset($_GET['tgl_periksa_mulai']) && !empty($_GET['tgl_periksa_mulai'])) $active_filters[] = "Tgl Mulai: " . date('d-m-Y', strtotime($_GET['tgl_periksa_mulai']));
                            if (isset($_GET['tgl_periksa_selesai']) && !empty($_GET['tgl_periksa_selesai'])) $active_filters[] = "Tgl Selesai: " . date('d-m-Y', strtotime($_GET['tgl_periksa_selesai']));
                            if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) $active_filters[] = "ID Pasien: " . htmlspecialchars($_GET['id_pasien']);
                            if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) $active_filters[] = "Kode Dokter: " . htmlspecialchars($_GET['kode_dokter']);
                            if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) $active_filters[] = "Jenis: " . htmlspecialchars($_GET['jenis_kunjungan']);
                            ?>
                            
                            <?php if (!empty($active_filters)): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="alert alert-info alert-dismissible fade show py-2" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Filter Aktif:</strong> <?= implode(', ', $active_filters) ?>
                                        <a href="laporan-rekam-medis.php" class="btn btn-sm btn-outline-danger ms-3">
                                            <i class="fas fa-times me-1"></i> Hapus Semua Filter
                                        </a>
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
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th class="sortable-header" onclick="sortBy('id_rekam')">
                                            ID Rekam
                                            <?php if ($sort_column == 'id_rekam'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th class="sortable-header" onclick="sortBy('id_pasien')">
                                            ID Pasien
                                            <?php if ($sort_column == 'id_pasien'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th class="sortable-header" onclick="sortBy('kode_dokter')">
                                            Kode Dokter
                                            <?php if ($sort_column == 'kode_dokter'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th>Jenis Kunjungan</th>
                                        <th class="sortable-header" onclick="sortBy('tanggal_periksa')">
                                            Tanggal Periksa
                                            <?php if ($sort_column == 'tanggal_periksa'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_rekam_medis) && is_array($data_rekam_medis)) {
                                        $counter = $start_number;
                                        foreach ($data_rekam_medis as $rekam) {
                                            $id_rekam = htmlspecialchars($rekam['id_rekam'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $id_pasien = htmlspecialchars($rekam['id_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $kode_dokter = htmlspecialchars($rekam['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $jenis_kunjungan = htmlspecialchars($rekam['jenis_kunjungan'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $tanggal_periksa = htmlspecialchars($rekam['tanggal_periksa'] ?? '', ENT_QUOTES, 'UTF-8');
                                            
                                            $tanggal_periksa_formatted = !empty($tanggal_periksa) ? date('d-m-Y H:i', strtotime($tanggal_periksa)) : '-';
                                            $jenis_class = $jenis_kunjungan == 'Baru' ? 'badge-baru' : 'badge-kontrol';
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= $id_rekam ?></td>
                                        <td><?= $id_pasien ?: '-' ?></td>
                                        <td><?= $kode_dokter ?: '-' ?></td>
                                        <td><span class="<?= $jenis_class ?>"><?= $jenis_kunjungan ?: '-' ?></span></td>
                                        <td><?= $tanggal_periksa_formatted ?></td>
                                    </tr>
                                    <?php
                                            if ($sort_order === 'desc') {
                                                $counter--;
                                            } else {
                                                $counter++;
                                            }
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center text-muted">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data rekam medis yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data rekam medis ditemukan.';
                                        }
                                        echo '</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

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
                                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order, $sort_column) : '#' ?>">Sebelumnya</a>
                                        </li>
                                        <?php
                                        echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order, $sort_column) . '">1</a></li>';
                                        
                                        $start = 2;
                                        $end = min(5, $total_pages - 1);
                                        if ($current_page > 3) {
                                            $start = $current_page - 1;
                                            $end = min($current_page + 2, $total_pages - 1);
                                        }
                                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        for ($i = $start; $i <= $end; $i++) {
                                            if ($i < $total_pages) {
                                                echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                                echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order, $sort_column) . '">' . $i . '</a></li>';
                                            }
                                        }
                                        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        if ($total_pages > 1) {
                                            echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order, $sort_column) . '">' . $total_pages . '</a></li>';
                                        }
                                        ?>
                                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order, $sort_column) : '#' ?>">Selanjutnya</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php else: ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        <li class="page-item disabled"><a class="page-link" href="#">Sebelumnya</a></li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item disabled"><a class="page-link" href="#">Selanjutnya</a></li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>

    <script>
    function toggleFilterCard() {
        const filterCard = document.getElementById('filterCard');
        if (filterCard) {
            filterCard.classList.toggle('d-none');
            if (!filterCard.classList.contains('d-none')) {
                filterCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    function changeEntries() {
        const entries = document.getElementById('filterEntries').value;
        const search = '<?= addslashes($search_query) ?>';
        const sort = '<?= addslashes($sort_order) ?>';
        const sort_column = '<?= addslashes($sort_column) ?>';
        let url = 'laporan-rekam-medis.php?entries=' + entries + '&page=1&sort=' + sort + '&sort_column=' + sort_column;
        if (search) url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    function sortBy(column) {
        const currentSort = '<?= $sort_order ?>';
        const currentColumn = '<?= $sort_column ?>';
        const search = '<?= addslashes($search_query) ?>';
        const entries = '<?= $entries_per_page ?>';
        
        let newSort = 'asc';
        if (column === currentColumn) {
            newSort = currentSort === 'asc' ? 'desc' : 'asc';
        }
        
        let url = 'laporan-rekam-medis.php?';
        let params = [];
        params.push('sort_column=' + column);
        params.push('sort=' + newSort);
        if (entries != 10) params.push('entries=' + entries);
        if (search) params.push('search=' + encodeURIComponent(search));
        
        const filterParams = ['tgl_periksa_mulai', 'tgl_periksa_selesai', 'id_pasien', 'kode_dokter', 'jenis_kunjungan'];
        filterParams.forEach(param => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has(param) && urlParams.get(param)) {
                params.push(param + '=' + encodeURIComponent(urlParams.get(param)));
            }
        });
        
        window.location.href = url + params.join('&');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilterParams = ['tgl_periksa_mulai', 'tgl_periksa_selesai', 'id_pasien', 'kode_dokter', 'jenis_kunjungan'].some(param => urlParams.has(param) && urlParams.get(param));
        if (hasFilterParams) {
            setTimeout(() => {
                const filterCard = document.getElementById('filterCard');
                if (filterCard) filterCard.classList.remove('d-none');
            }, 100);
        }
    });
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
</body>
</html>

<?php
function getExportParams() {
    $params = [];
    $filter_params = ['search', 'tgl_periksa_mulai', 'tgl_periksa_selesai', 'id_pasien', 'kode_dokter', 'jenis_kunjungan', 'sort', 'sort_column'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

function getPaginationUrl($page, $entries, $search = '', $sort = 'asc', $sort_column = 'id_rekam') {
    $url = 'laporan-rekam-medis.php?';
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    if ($sort_column != 'id_rekam') $params[] = 'sort_column=' . $sort_column;
    
    $filter_params = ['tgl_periksa_mulai', 'tgl_periksa_selesai', 'id_pasien', 'kode_dokter', 'jenis_kunjungan'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    return $url . implode('&', $params);
}
?>

<?php require_once "footer.php"; ?>