<?php
session_start();
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Staff dengan jabatan IT Support yang bisa mengakses halaman ini (sesuai header.php)
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
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk melihat laporan transaksi. Hanya Staff dengan jabatan IT Support, Medical Record, dan Perawat Spesialis Mata yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Konfigurasi pagination
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_transaksi';

// Ambil semua data transaksi
$all_transaksi = $db->tampil_data_transaksi();
$all_pasien = $db->tampil_data_pasien();
$all_staff = $db->tampil_data_staff();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_transaksi = [];
    foreach ($all_transaksi as $transaksi) {
        if (stripos($transaksi['id_transaksi'] ?? '', $search_query) !== false ||
            stripos($transaksi['id_rekam'] ?? '', $search_query) !== false ||
            stripos($transaksi['kode_staff'] ?? '', $search_query) !== false ||
            stripos($transaksi['tanggal_transaksi'] ?? '', $search_query) !== false ||
            stripos($transaksi['metode_pembayaran'] ?? '', $search_query) !== false ||
            stripos($transaksi['grand_total'] ?? '', $search_query) !== false ||
            stripos($transaksi['status_pembayaran'] ?? '', $search_query) !== false) {
            $filtered_transaksi[] = $transaksi;
        }
    }
    $all_transaksi = $filtered_transaksi;
}

// Filter berdasarkan status pembayaran
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = trim($_GET['status']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($status) {
        return ($transaksi['status_pembayaran'] ?? '') == $status;
    });
}

// Filter berdasarkan metode pembayaran
if (isset($_GET['metode']) && !empty($_GET['metode'])) {
    $metode = trim($_GET['metode']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($metode) {
        return ($transaksi['metode_pembayaran'] ?? '') == $metode;
    });
}

// Filter berdasarkan pasien (melalui id_rekam)
if (isset($_GET['pasien']) && !empty($_GET['pasien'])) {
    $id_pasien = trim($_GET['pasien']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($id_pasien, $db) {
        $rekam = $db->tampil_data_rekam_medis_by_id($transaksi['id_rekam'] ?? '');
        return ($rekam['id_pasien'] ?? '') == $id_pasien;
    });
}

// Filter berdasarkan staff
if (isset($_GET['staff']) && !empty($_GET['staff'])) {
    $kode_staff = trim($_GET['staff']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($kode_staff) {
        return ($transaksi['kode_staff'] ?? '') == $kode_staff;
    });
}

// Filter berdasarkan rentang tanggal
if (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) {
    $tanggal_mulai = trim($_GET['tanggal_mulai']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($tanggal_mulai) {
        return strtotime($transaksi['tanggal_transaksi'] ?? '') >= strtotime($tanggal_mulai);
    });
}

if (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) {
    $tanggal_selesai = trim($_GET['tanggal_selesai']);
    $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($tanggal_selesai) {
        return strtotime($transaksi['tanggal_transaksi'] ?? '') <= strtotime($tanggal_selesai . ' 23:59:59');
    });
}

// Reset array keys setelah filter
$all_transaksi = array_values($all_transaksi);

// Hitung statistik untuk footer
$total_metode_tunai = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['metode_pembayaran'] ?? '') == 'Tunai';
}));
$total_metode_transfer = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['metode_pembayaran'] ?? '') == 'Transfer';
}));
$total_metode_qris = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['metode_pembayaran'] ?? '') == 'QRIS';
}));
$total_metode_debit = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['metode_pembayaran'] ?? '') == 'Debit';
}));
$total_lunas = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['status_pembayaran'] ?? '') == 'Lunas';
}));
$total_belum = count(array_filter($all_transaksi, function($transaksi) {
    return ($transaksi['status_pembayaran'] ?? '') == 'Belum Bayar';
}));

// Urutkan data berdasarkan kolom yang dipilih
usort($all_transaksi, function($a, $b) use ($sort_column, $sort_order) {
    $val_a = $a[$sort_column] ?? '';
    $val_b = $b[$sort_column] ?? '';
    
    // Handle numeric comparison untuk ID dan total
    if (in_array($sort_column, ['id_transaksi', 'grand_total'])) {
        $val_a = (int) $val_a;
        $val_b = (int) $val_b;
        if ($sort_order === 'desc') {
            return ($val_b - $val_a);
        } else {
            return ($val_a - $val_b);
        }
    }
    
    // Handle date comparison untuk tanggal
    if ($sort_column == 'tanggal_transaksi') {
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
$total_entries = count($all_transaksi);
$total_pages = ceil($total_entries / $entries_per_page);
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $entries_per_page;
$data_transaksi = array_slice($all_transaksi, $offset, $entries_per_page);

if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Data Transaksi - Sistem Informasi Poliklinik Mata Eyethica</title>
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
        .badge-lunas { background-color: #28a745; color: #fff; }
        .badge-belum-dibayar { background-color: #ffc107; color: #000; }
        .badge-tunai { background-color: #17a2b8; color: #fff; }
        .badge-transfer { background-color: #6610f2; color: #fff; }
        .badge-qris { background-color: #e83e8c; color: #fff; }
        .badge-debit { background-color: #fd7e14; color: #fff; }
        .total-biaya { font-weight: bold; color: #28a745; }
        .table-responsive { font-size: 0.875rem; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.75rem; }
            .btn-group .btn { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        }
        #filterCard {
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
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
                                <li class="breadcrumb-item" aria-current="page">Laporan Transaksi</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Laporan Transaksi</h2>
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
                    <a href="proses/cetak/cetak-laporan-transaksi-excel.php?export_excel=1<?= getExportParams() ?>" class="btn btn-success btn-export">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </a>
                    <a href="proses/cetak/cetak-laporan-transaksi-pdf.php?export_pdf=1<?= getExportParams() ?>" class="btn btn-danger btn-export">
                        <i class="fas fa-file-pdf me-1"></i> PDF
                    </a>
                    <a href="proses/cetak/cetak-laporan-transaksi-cetak.php?cetak=1<?= getExportParams() ?>" class="btn btn-primary btn-export" target="_blank">
                        <i class="fas fa-print me-1"></i> Cetak
                    </a>
                    <button type="button" class="btn btn-warning" onclick="toggleFilterCard()">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>

                <!-- Filter Card -->
                <div id="filterCard" class="card shadow-sm mb-4 d-none">
                    <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filter Data Transaksi</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="laporan-transaksi.php" id="filterForm">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="filterSearch" class="form-label"><i class="fas fa-search me-1"></i> Cari Transaksi</label>
                                    <input type="text" class="form-control" id="filterSearch" name="search" 
                                           placeholder="Cari ID Transaksi, ID Rekam, Staff, Metode..." 
                                           value="<?= htmlspecialchars($search_query) ?>">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="filterSort" class="form-label"><i class="fas fa-sort me-1"></i> Urutkan</label>
                                    <select class="form-select" id="filterSort" name="sort">
                                        <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>Terlama</option>
                                        <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Terbaru</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="filterEntries" class="form-label"><i class="fas fa-list me-1"></i> Tampilkan</label>
                                    <select class="form-select" id="filterEntries" name="entries" onchange="changeEntries()">
                                        <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10 entri</option>
                                        <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25 entri</option>
                                        <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50 entri</option>
                                        <option value="100" <?= $entries_per_page == 100 ? 'selected' : '' ?>>100 entri</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="filterSortColumn" class="form-label"><i class="fas fa-sort-amount-down me-1"></i> Urutkan Berdasarkan</label>
                                    <select class="form-select" id="filterSortColumn" name="sort_column">
                                        <option value="id_transaksi" <?= $sort_column == 'id_transaksi' ? 'selected' : '' ?>>ID Transaksi</option>
                                        <option value="id_rekam" <?= $sort_column == 'id_rekam' ? 'selected' : '' ?>>ID Rekam</option>
                                        <option value="kode_staff" <?= $sort_column == 'kode_staff' ? 'selected' : '' ?>>Kode Staff</option>
                                        <option value="tanggal_transaksi" <?= $sort_column == 'tanggal_transaksi' ? 'selected' : '' ?>>Tanggal Transaksi</option>
                                        <option value="grand_total" <?= $sort_column == 'grand_total' ? 'selected' : '' ?>>Total Biaya</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label"><i class="fas fa-money-check-alt me-1"></i> Status Pembayaran</label>
                                    <select class="form-select" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="Lunas" <?= isset($_GET['status']) && $_GET['status'] == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                                        <option value="Belum Bayar" <?= isset($_GET['status']) && $_GET['status'] == 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label"><i class="fas fa-credit-card me-1"></i> Metode Pembayaran</label>
                                    <select class="form-select" name="metode">
                                        <option value="">Semua Metode</option>
                                        <option value="Tunai" <?= isset($_GET['metode']) && $_GET['metode'] == 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                                        <option value="Transfer" <?= isset($_GET['metode']) && $_GET['metode'] == 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                                        <option value="QRIS" <?= isset($_GET['metode']) && $_GET['metode'] == 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                                        <option value="Debit" <?= isset($_GET['metode']) && $_GET['metode'] == 'Debit' ? 'selected' : '' ?>>Debit</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label"><i class="fas fa-user-md me-1"></i> Staff</label>
                                    <select class="form-select" name="staff">
                                        <option value="">Semua Staff</option>
                                        <?php foreach ($all_staff as $staff): ?>
                                            <option value="<?= $staff['kode_staff'] ?>" 
                                                <?= isset($_GET['staff']) && $_GET['staff'] == $staff['kode_staff'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($staff['nama_staff']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label"><i class="fas fa-user-injured me-1"></i> Pasien</label>
                                    <select class="form-select" name="pasien">
                                        <option value="">Semua Pasien</option>
                                        <?php foreach ($all_pasien as $pasien): ?>
                                            <option value="<?= $pasien['id_pasien'] ?>" 
                                                <?= isset($_GET['pasien']) && $_GET['pasien'] == $pasien['id_pasien'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pasien['nama_pasien']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Rentang Tanggal Transaksi</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="date" class="form-control" name="tanggal_mulai" 
                                                   value="<?= isset($_GET['tanggal_mulai']) ? htmlspecialchars($_GET['tanggal_mulai']) : '' ?>"
                                                   placeholder="Tanggal Mulai">
                                            <small class="text-muted">Tanggal Mulai</small>
                                        </div>
                                        <div class="col">
                                            <input type="date" class="form-control" name="tanggal_selesai" 
                                                   value="<?= isset($_GET['tanggal_selesai']) ? htmlspecialchars($_GET['tanggal_selesai']) : '' ?>"
                                                   placeholder="Tanggal Selesai">
                                            <small class="text-muted">Tanggal Selesai</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3 d-flex align-items-end justify-content-end">
                                    <div class="btn-group" role="group">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Terapkan Filter</button>
                                        <a href="laporan-transaksi.php" class="btn btn-secondary"><i class="fas fa-redo me-1"></i> Reset</a>
                                        <button type="button" class="btn btn-outline-warning" onclick="toggleFilterCard()"><i class="fas fa-times me-1"></i> Tutup</button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php
                            $active_filters = [];
                            if (!empty($search_query)) $active_filters[] = "Pencarian: \"$search_query\"";
                            if (isset($_GET['status']) && !empty($_GET['status'])) $active_filters[] = "Status: " . $_GET['status'];
                            if (isset($_GET['metode']) && !empty($_GET['metode'])) $active_filters[] = "Metode: " . $_GET['metode'];
                            if (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) $active_filters[] = "Tgl Mulai: " . date('d-m-Y', strtotime($_GET['tanggal_mulai']));
                            if (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) $active_filters[] = "Tgl Selesai: " . date('d-m-Y', strtotime($_GET['tanggal_selesai']));
                            if (isset($_GET['pasien']) && !empty($_GET['pasien'])) {
                                foreach ($all_pasien as $pasien) {
                                    if ($pasien['id_pasien'] == $_GET['pasien']) {
                                        $active_filters[] = "Pasien: " . $pasien['nama_pasien'];
                                        break;
                                    }
                                }
                            }
                            if (isset($_GET['staff']) && !empty($_GET['staff'])) {
                                foreach ($all_staff as $staff) {
                                    if ($staff['kode_staff'] == $_GET['staff']) {
                                        $active_filters[] = "Staff: " . $staff['nama_staff'];
                                        break;
                                    }
                                }
                            }
                            ?>
                            
                            <?php if (!empty($active_filters)): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="alert alert-info alert-dismissible fade show py-2" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Filter Aktif:</strong> <?= implode(', ', $active_filters) ?>
                                        <a href="laporan-transaksi.php" class="btn btn-sm btn-outline-danger ms-3">
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
                                        <th class="sortable-header" onclick="sortBy('id_transaksi')">
                                            ID Transaksi
                                            <?php if ($sort_column == 'id_transaksi'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th class="sortable-header" onclick="sortBy('id_rekam')">
                                            ID Rekam
                                            <?php if ($sort_column == 'id_rekam'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th>Nama Pasien</th>
                                        <th class="sortable-header" onclick="sortBy('kode_staff')">
                                            Kode Staff
                                            <?php if ($sort_column == 'kode_staff'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th class="sortable-header" onclick="sortBy('tanggal_transaksi')">
                                            Tanggal Transaksi
                                            <?php if ($sort_column == 'tanggal_transaksi'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th>Metode Bayar</th>
                                        <th class="sortable-header" onclick="sortBy('grand_total')">
                                            Total Biaya
                                            <?php if ($sort_column == 'grand_total'): ?>
                                                <i class="fas fa-sort-<?= $sort_order == 'asc' ? 'up' : 'down' ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-muted"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_transaksi)): ?>
                                        <?php $no = $start_number; foreach ($data_transaksi as $transaksi): 
                                            $nama_pasien = 'Pasien Tidak Diketahui';
                                            $id_rekam = $transaksi['id_rekam'] ?? '';
                                            if (!empty($id_rekam)) {
                                                $rekam = $db->tampil_data_rekam_medis_by_id($id_rekam);
                                                if ($rekam) {
                                                    foreach ($all_pasien as $pasien) {
                                                        if ($pasien['id_pasien'] == $rekam['id_pasien']) {
                                                            $nama_pasien = $pasien['nama_pasien'];
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            $tanggal_formatted = !empty($transaksi['tanggal_transaksi']) ? 
                                                date('d-m-Y H:i:s', strtotime($transaksi['tanggal_transaksi'])) : '-';
                                            $biaya = $transaksi['grand_total'] ?? 0;
                                            $biaya_formatted = 'Rp ' . number_format($biaya, 0, ',', '.');
                                            
                                            $metode_class = '';
                                            switch($transaksi['metode_pembayaran']) {
                                                case 'Tunai': $metode_class = 'badge-tunai'; break;
                                                case 'Transfer': $metode_class = 'badge-transfer'; break;
                                                case 'QRIS': $metode_class = 'badge-qris'; break;
                                                case 'Debit': $metode_class = 'badge-debit'; break;
                                                default: $metode_class = 'badge-secondary';
                                            }
                                            
                                            $status_class = ($transaksi['status_pembayaran'] == 'Lunas') ? 'badge-lunas' : 'badge-belum-dibayar';
                                        ?>
                                        <tr>
                                            <td><?= $no ?></td>
                                            <td><?= htmlspecialchars($transaksi['id_transaksi'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($transaksi['id_rekam'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($nama_pasien) ?></td>
                                            <td><?= htmlspecialchars($transaksi['kode_staff'] ?? '-') ?></td>
                                            <td><?= $tanggal_formatted ?></td>
                                            <td><span class="badge <?= $metode_class ?>"><?= $transaksi['metode_pembayaran'] ?? '-' ?></span></td>
                                            <td class="total-biaya"><?= $biaya_formatted ?></td>
                                            <td><span class="badge <?= $status_class ?>"><?= $transaksi['status_pembayaran'] ?? 'Belum Bayar' ?></span></td>
                                        </tr>
                                        <?php $no = ($sort_order === 'desc') ? $no - 1 : $no + 1; endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="9" class="text-center text-muted">
                                            <?php if (!empty($search_query)): ?>
                                                Tidak ada data transaksi yang sesuai dengan pencarian "<?= htmlspecialchars($search_query) ?>"
                                            <?php else: ?>
                                                Tidak ada data transaksi ditemukan.
                                            <?php endif; ?>
                                        </td></tr>
                                    <?php endif; ?>
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
        let url = 'laporan-transaksi.php?entries=' + entries + '&page=1&sort=' + sort + '&sort_column=' + sort_column;
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
        
        let url = 'laporan-transaksi.php?';
        let params = [];
        params.push('sort_column=' + column);
        params.push('sort=' + newSort);
        if (entries != 10) params.push('entries=' + entries);
        if (search) params.push('search=' + encodeURIComponent(search));
        
        const filterParams = ['status', 'metode', 'pasien', 'staff', 'tanggal_mulai', 'tanggal_selesai'];
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
        const hasFilterParams = ['status', 'metode', 'pasien', 'staff', 'tanggal_mulai', 'tanggal_selesai'].some(param => urlParams.has(param) && urlParams.get(param));
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
    $filter_params = ['search', 'status', 'metode', 'pasien', 'staff', 'tanggal_mulai', 'tanggal_selesai', 'sort', 'sort_column'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

function getPaginationUrl($page, $entries, $search = '', $sort = 'asc', $sort_column = 'id_transaksi') {
    $url = 'laporan-transaksi.php?';
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    if ($sort_column != 'id_transaksi') $params[] = 'sort_column=' . $sort_column;
    
    $filter_params = ['status', 'metode', 'pasien', 'staff', 'tanggal_mulai', 'tanggal_selesai'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    return $url . implode('&', $params);
}
?>

<?php require_once "footer.php"; ?>