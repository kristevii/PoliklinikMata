<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Cek hak akses sesuai header.php: IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang bisa akses data resep obat
if ($jabatan_user != 'IT Support' && 
    $role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Medical Record') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data resep obat. Hanya Staff dengan jabatan IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data detail resep obat dengan JOIN ke data_obat dan data_resep_obat
$query_murni = "SELECT ddr.*, do.nama_obat, do.harga as harga_obat, dro.id_rekam 
                FROM data_detail_resep_obat ddr
                JOIN data_obat do ON ddr.id_obat = do.id_obat
                JOIN data_resep_obat dro ON ddr.id_resep_obat = dro.id_resep_obat";
$result_murni = $db->koneksi->query($query_murni);
$all_detail_resep = [];
if ($result_murni && $result_murni->num_rows > 0) {
    while ($row = $result_murni->fetch_assoc()) {
        $all_detail_resep[] = $row;
    }
}

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_detail = [];
    foreach ($all_detail_resep as $detail) {
        if (stripos($detail['id_detail_resep'] ?? '', $search_query) !== false ||
            stripos($detail['id_resep_obat'] ?? '', $search_query) !== false ||
            stripos($detail['nama_obat'] ?? '', $search_query) !== false ||
            stripos($detail['dosis'] ?? '', $search_query) !== false ||
            stripos($detail['aturan_pakai'] ?? '', $search_query) !== false) {
            $filtered_detail[] = $detail;
        }
    }
    $all_detail_resep = $filtered_detail;
}

// Urutkan data berdasarkan ID
usort($all_detail_resep, function($a, $b) use ($sort_order) {
    $val_a = $a['id_detail_resep'] ?? 0;
    $val_b = $b['id_detail_resep'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_detail_resep);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_detail_resep = array_slice($all_detail_resep, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

// Ambil data obat untuk dropdown
$query_obat = "SELECT id_obat, nama_obat, harga FROM data_obat ORDER BY nama_obat ASC";
$result_obat = $db->koneksi->query($query_obat);
$data_obat = [];
if ($result_obat && $result_obat->num_rows > 0) {
    while ($row = $result_obat->fetch_assoc()) {
        $data_obat[] = $row;
    }
}

// Ambil data resep obat untuk dropdown dengan JOIN ke rekam medis dan pasien
$query_resep = "SELECT dro.id_resep_obat, dro.id_rekam, dro.catatan, dro.tanggal_resep, dp.nama_pasien, dr.jenis_kunjungan, dr.tanggal_periksa
                FROM data_resep_obat dro
                JOIN data_rekam_medis dr ON dro.id_rekam = dr.id_rekam
                JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                ORDER BY dro.id_resep_obat DESC";
$result_resep = $db->koneksi->query($query_resep);
$data_resep = [];
if ($result_resep && $result_resep->num_rows > 0) {
    while ($row = $result_resep->fetch_assoc()) {
        $data_resep[] = $row;
    }
}

function formatNumber($num) {
    return number_format($num, 0, ',', '.');
}

function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    return 'data-detail-resep-obat.php?' . implode('&', $params);
}

function getSortUrl($current_sort, $entries, $search) {
    $params = [];
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'data-detail-resep-obat.php?' . implode('&', $params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Detail Resep Obat - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
    .table th { border-top: none; font-weight: 600; }
    .btn-hapus:hover, .btn-edit:hover, .btn-view:hover { transform: scale(1.05); transition: all 0.3s ease; }
    .info-card { background-color: #f8f9fa; border-left: 4px solid #0d6efd; }
    </style>
</head>
<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical">
    <div class="loader-bg">
        <div class="loader-track"><div class="loader-fill"></div></div>
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
                                <li class="breadcrumb-item" aria-current="page">Data Detail Resep Obat</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Detail Resep Obat</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid">
                <?php if ($notif_message): ?>
                <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert" id="autoDismissAlert">
                    <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($notif_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <script>
                setTimeout(function() {
                    var alert = document.getElementById('autoDismissAlert');
                    if (alert) {
                        alert.classList.remove('show');
                        setTimeout(function() { if(alert && alert.parentNode) alert.parentNode.removeChild(alert); }, 150);
                    }
                }, 5000);
                </script>
                <?php endif; ?>
                
                <div class="d-flex justify-content-start mb-4">
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#tambahDetailResepModal">
                        <i class="fas fa-plus me-1"></i> Tambah Detail Resep Obat
                    </button>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
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
                            <div class="col-md-6">
                                <form method="GET" action="" class="d-flex justify-content-end">
                                    <div class="input-group input-group-sm" style="width: 300px;">
                                        <input type="text" class="form-control" name="search" placeholder="Cari ID Detail, ID Resep, Nama Obat..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="data-detail-resep-obat.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><a href="<?= getSortUrl($sort_order, $entries_per_page, $search_query) ?>" class="text-decoration-none text-dark">No <?php if ($sort_order === 'asc'): ?><i class="fas fa-sort-up ms-1"></i><?php else: ?><i class="fas fa-sort-down ms-1"></i><?php endif; ?></a></th>
                                        <th>ID</th>
                                        <th>ID Resep</th>
                                        <th>ID Rekam</th>
                                        <th>Nama Obat</th>
                                        <th>Jumlah</th>
                                        <th>Dosis</th>
                                        <th>Aturan Pakai</th>
                                        <th>Harga</th>
                                        <th>Subtotal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_detail_resep) && is_array($data_detail_resep)): ?>
                                        <?php $counter = $start_number; foreach ($data_detail_resep as $detail): ?>
                                            <?php
                                            $id_detail_resep = htmlspecialchars($detail['id_detail_resep'] ?? '');
                                            $id_resep_obat = htmlspecialchars($detail['id_resep_obat'] ?? '');
                                            $id_rekam = htmlspecialchars($detail['id_rekam'] ?? '');
                                            $nama_obat = htmlspecialchars($detail['nama_obat'] ?? '-');
                                            $jumlah = htmlspecialchars($detail['jumlah'] ?? '0');
                                            $dosis = htmlspecialchars($detail['dosis'] ?? '-');
                                            $aturan_pakai = htmlspecialchars($detail['aturan_pakai'] ?? '-');
                                            $harga = !empty($detail['harga']) ? 'Rp ' . formatNumber($detail['harga']) : '-';
                                            $subtotal = !empty($detail['subtotal']) ? 'Rp ' . formatNumber($detail['subtotal']) : '-';
                                            ?>
                                            <tr>
                                                <td><?= $counter ?></td>
                                                <td><?= $id_detail_resep ?></td>
                                                <td><?= $id_resep_obat ?></td>
                                                <td><?= $id_rekam ?></td>
                                                <td><?= $nama_obat ?></td>
                                                <td><?= $jumlah ?></td>
                                                <td><?= $dosis ?></td>
                                                <td><?= $aturan_pakai ?></td>
                                                <td><?= $harga ?></td>
                                                <td><?= $subtotal ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-warning btn-sm btn-edit" 
                                                                data-id="<?= $id_detail_resep ?>"
                                                                data-id_resep_obat="<?= $id_resep_obat ?>"
                                                                data-id_obat="<?= $detail['id_obat'] ?>"
                                                                data-jumlah="<?= $detail['jumlah'] ?>"
                                                                data-dosis="<?= htmlspecialchars($detail['dosis'] ?? '') ?>"
                                                                data-aturan_pakai="<?= htmlspecialchars($detail['aturan_pakai'] ?? '') ?>"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-hapus" 
                                                                data-id="<?= $id_detail_resep ?>"
                                                                data-nama_obat="<?= $nama_obat ?>"
                                                                title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="11" class="text-center text-muted">Tidak ada data detail resep obat ditemukan.<?php ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_entries > 0): ?>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p class="text-muted mb-0">
                                        Menampilkan <?= $total_entries > 0 ? ($offset + 1) : 0 ?> sampai <?= min($offset + $entries_per_page, $total_entries) ?> dari <?= $total_entries ?> entri
                                        <?php if (!empty($search_query)): ?><span class="text-info">(hasil pencarian)</span><?php endif; ?>
                                        <?php if ($sort_order === 'desc'): ?><span class="text-warning">(diurutkan dari terbaru)</span><?php else: ?><span class="text-warning">(diurutkan dari terlama)</span><?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0">
                                                <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">Sebelumnya</a>
                                                </li>
                                                <?php
                                                echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '"><a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order) . '">1</a></li>';
                                                $start = 2;
                                                $end = min(5, $total_pages - 1);
                                                if ($current_page > 3) {
                                                    $start = $current_page - 1;
                                                    $end = min($current_page + 2, $total_pages - 1);
                                                }
                                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                for ($i = $start; $i <= $end; $i++) {
                                                    if ($i < $total_pages) {
                                                        echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order) . '">' . $i . '</a></li>';
                                                    }
                                                }
                                                if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                if ($total_pages > 1) {
                                                    echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '"><a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order) . '">' . $total_pages . '</a></li>';
                                                }
                                                ?>
                                                <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">Selanjutnya</a>
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

    <!-- Modal Tambah Detail Resep Obat -->
    <div class="modal fade" id="tambahDetailResepModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Detail Resep Obat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-detail-resep-obat.php">
                    <input type="hidden" name="tambah_detail_resep" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Pilih Resep Obat <span class="text-danger">*</span></label>
                                <select class="form-select select2-resep" name="id_resep_obat" id="select_resep_obat" required style="width:100%">
                                    <option value="">-- Cari Resep Obat --</option>
                                    <?php foreach ($data_resep as $resep): ?>
                                    <option value="<?= $resep['id_resep_obat'] ?>"
                                        data-catatan="<?= htmlspecialchars($resep['catatan'] ?? '') ?>"
                                        data-id_rekam="<?= $resep['id_rekam'] ?>"
                                        data-tanggal_resep="<?= !empty($resep['tanggal_resep']) ? date('d/m/Y H:i:s', strtotime($resep['tanggal_resep'])) : '-' ?>"
                                        data-nama_pasien="<?= htmlspecialchars($resep['nama_pasien'] ?? '') ?>"
                                        data-jenis_kunjungan="<?= htmlspecialchars($resep['jenis_kunjungan'] ?? '') ?>"
                                        data-tanggal_periksa="<?= !empty($resep['tanggal_periksa']) ? date('d/m/Y', strtotime($resep['tanggal_periksa'])) : '-' ?>">
                                        ID Resep: <?= $resep['id_resep_obat'] ?> - <?= htmlspecialchars($resep['nama_pasien'] ?? '-') ?> (<?= $resep['jenis_kunjungan'] ?? '-' ?> - <?= !empty($resep['tanggal_periksa']) ? date('d/m/Y', strtotime($resep['tanggal_periksa'])) : '-' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Format: ID Resep - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                            </div>
                            
                            <!-- Card untuk menampilkan catatan resep -->
                            <div class="col-md-12 mb-3" id="catatanResepCard" style="display: none;">
                                <div class="card info-card">
                                    <div class="card-body py-2">
                                        <small class="text-muted d-block mb-1"><i class="fas fa-sticky-note me-1"></i> Catatan Resep:</small>
                                        <p class="mb-0" id="catatanResepText">-</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pilih Obat <span class="text-danger">*</span></label>
                                <select class="form-select select2-obat" name="id_obat" id="select_obat" required style="width:100%">
                                    <option value="">-- Cari Obat --</option>
                                    <?php foreach ($data_obat as $obat): ?>
                                    <option value="<?= $obat['id_obat'] ?>" data-harga="<?= $obat['harga'] ?>"><?= $obat['nama_obat'] ?> (Rp <?= formatNumber($obat['harga']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="jumlah" id="jumlah_obat" required min="1" value="1">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Harga Satuan</label>
                                <input type="text" class="form-control" id="harga_satuan" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dosis</label>
                                <input type="text" class="form-control" name="dosis" placeholder="Contoh: 1 x 1 tablet">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Aturan Pakai</label>
                                <input type="text" class="form-control" name="aturan_pakai" placeholder="Contoh: Setelah makan">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subtotal</label>
                                <input type="text" class="form-control" id="subtotal_display" readonly>
                                <input type="hidden" name="subtotal" id="subtotal_hidden">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Detail Resep Obat -->
    <div class="modal fade" id="editDetailResepModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Detail Resep Obat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="proses/edit/edit-detail-resep-obat.php">
                    <input type="hidden" name="edit_detail_resep" value="1">
                    <input type="hidden" name="id_detail_resep" id="edit_id_detail_resep">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Pilih Resep Obat <span class="text-danger">*</span></label>
                                <select class="form-select select2-resep-edit" name="id_resep_obat" id="edit_id_resep_obat" required style="width:100%">
                                    <option value="">-- Cari Resep Obat --</option>
                                    <?php foreach ($data_resep as $resep): ?>
                                    <option value="<?= $resep['id_resep_obat'] ?>"
                                        data-catatan="<?= htmlspecialchars($resep['catatan'] ?? '') ?>"
                                        data-id_rekam="<?= $resep['id_rekam'] ?>"
                                        data-tanggal_resep="<?= !empty($resep['tanggal_resep']) ? date('d/m/Y H:i:s', strtotime($resep['tanggal_resep'])) : '-' ?>"
                                        data-nama_pasien="<?= htmlspecialchars($resep['nama_pasien'] ?? '') ?>"
                                        data-jenis_kunjungan="<?= htmlspecialchars($resep['jenis_kunjungan'] ?? '') ?>"
                                        data-tanggal_periksa="<?= !empty($resep['tanggal_periksa']) ? date('d/m/Y', strtotime($resep['tanggal_periksa'])) : '-' ?>">
                                        ID Resep: <?= $resep['id_resep_obat'] ?> - <?= htmlspecialchars($resep['nama_pasien'] ?? '-') ?> (<?= $resep['jenis_kunjungan'] ?? '-' ?> - <?= !empty($resep['tanggal_periksa']) ? date('d/m/Y', strtotime($resep['tanggal_periksa'])) : '-' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Format: ID Resep - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                            </div>
                            
                            <!-- Card untuk menampilkan catatan resep -->
                            <div class="col-md-12 mb-3" id="editCatatanResepCard" style="display: none;">
                                <div class="card info-card">
                                    <div class="card-body py-2">
                                        <small class="text-muted d-block mb-1"><i class="fas fa-sticky-note me-1"></i> Catatan Resep:</small>
                                        <p class="mb-0" id="editCatatanResepText">-</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pilih Obat <span class="text-danger">*</span></label>
                                <select class="form-select select2-obat-edit" name="id_obat" id="edit_id_obat" required style="width:100%">
                                    <option value="">-- Cari Obat --</option>
                                    <?php foreach ($data_obat as $obat): ?>
                                    <option value="<?= $obat['id_obat'] ?>" data-harga="<?= $obat['harga'] ?>"><?= $obat['nama_obat'] ?> (Rp <?= formatNumber($obat['harga']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required min="1">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Harga Satuan</label>
                                <input type="text" class="form-control" id="edit_harga_satuan" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dosis</label>
                                <input type="text" class="form-control" name="dosis" id="edit_dosis" placeholder="Contoh: 1 x 1 tablet">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Aturan Pakai</label>
                                <input type="text" class="form-control" name="aturan_pakai" id="edit_aturan_pakai" placeholder="Contoh: Setelah makan">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Detail Resep Obat -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-center">
                    <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    <p>Apakah Anda yakin ingin menghapus detail resep obat ini?</p>
                    <p class="text-danger" id="detailHapusInfo"></p>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i><strong>PERINGATAN:</strong> Data akan dihapus permanen beserta data transaksi terkait!</div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger">Ya, Hapus</a>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    function changeEntries() {
        var entries = document.getElementById('entriesPerPage').value;
        var search = '<?= addslashes($search_query) ?>';
        var sort = '<?= $sort_order ?>';
        window.location.href = 'data-detail-resep-obat.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function hitungSubtotal(selectId, jumlahId, hargaSatuanId, subtotalDisplayId, subtotalHiddenId) {
        let selectedOption = $(selectId + ' option:selected');
        let harga = selectedOption.data('harga') || 0;
        let jumlah = parseInt($(jumlahId).val()) || 0;
        let subtotal = harga * jumlah;
        $(hargaSatuanId).val('Rp ' + formatNumber(harga));
        $(subtotalDisplayId).val('Rp ' + formatNumber(subtotal));
        $(subtotalHiddenId).val(subtotal);
    }

    // Fungsi untuk menampilkan catatan resep berdasarkan id_resep_obat yang dipilih
    function showCatatanResep(selectElement, cardId, textId) {
        let selectedOption = $(selectElement).find('option:selected');
        let catatan = selectedOption.data('catatan');
        
        if (catatan && catatan.trim() !== '') {
            $(cardId).show();
            $(textId).text(catatan);
        } else {
            $(cardId).hide();
            $(textId).text('-');
        }
    }

    $(document).ready(function() {
        // Initialize Select2
        $('.select2-resep').select2({ theme: 'bootstrap-5', dropdownParent: $('#tambahDetailResepModal'), placeholder: 'Cari resep obat...', allowClear: true, width: '100%' });
        $('.select2-resep-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#editDetailResepModal'), placeholder: 'Cari resep obat...', allowClear: true, width: '100%' });
        $('.select2-obat').select2({ theme: 'bootstrap-5', dropdownParent: $('#tambahDetailResepModal'), placeholder: 'Cari obat...', allowClear: true, width: '100%' });
        $('.select2-obat-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#editDetailResepModal'), placeholder: 'Cari obat...', allowClear: true, width: '100%' });

        // Event untuk menampilkan catatan resep saat memilih resep obat (Tambah)
        $('#select_resep_obat').on('change', function() {
            showCatatanResep('#select_resep_obat', '#catatanResepCard', '#catatanResepText');
        });

        // Event untuk menampilkan catatan resep saat memilih resep obat (Edit)
        $('#edit_id_resep_obat').on('change', function() {
            showCatatanResep('#edit_id_resep_obat', '#editCatatanResepCard', '#editCatatanResepText');
        });

        // Hitung subtotal untuk tambah
        $('#select_obat').on('change', function() {
            hitungSubtotal('.select2-obat', '#jumlah_obat', '#harga_satuan', '#subtotal_display', '#subtotal_hidden');
        });
        
        $('#jumlah_obat').on('input', function() {
            hitungSubtotal('.select2-obat', '#jumlah_obat', '#harga_satuan', '#subtotal_display', '#subtotal_hidden');
        });

        // Hitung subtotal untuk edit
        $('#edit_id_obat').on('change', function() {
            let selectedOption = $(this).find('option:selected');
            let harga = selectedOption.data('harga') || 0;
            let jumlah = parseInt($('#edit_jumlah').val()) || 0;
            let subtotal = harga * jumlah;
            $('#edit_harga_satuan').val('Rp ' + formatNumber(harga));
        });
        
        $('#edit_jumlah').on('input', function() {
            let selectedOption = $('#edit_id_obat option:selected');
            let harga = selectedOption.data('harga') || 0;
            let jumlah = parseInt($(this).val()) || 0;
            let subtotal = harga * jumlah;
            $('#edit_harga_satuan').val('Rp ' + formatNumber(harga));
        });
    });

    // Edit button handler
    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id_detail_resep').value = this.dataset.id;
            $('#edit_id_resep_obat').val(this.dataset.id_resep_obat).trigger('change');
            $('#edit_id_obat').val(this.dataset.id_obat).trigger('change');
            document.getElementById('edit_jumlah').value = this.dataset.jumlah;
            document.getElementById('edit_dosis').value = this.dataset.dosis || '';
            document.getElementById('edit_aturan_pakai').value = this.dataset.aturan_pakai || '';
            
            // Hitung ulang harga satuan
            setTimeout(function() {
                let selectedOption = $('#edit_id_obat option:selected');
                let harga = selectedOption.data('harga') || 0;
                document.getElementById('edit_harga_satuan').value = 'Rp ' + formatNumber(harga);
                
                // Tampilkan catatan resep untuk edit
                showCatatanResep('#edit_id_resep_obat', '#editCatatanResepCard', '#editCatatanResepText');
            }, 100);
            
            new bootstrap.Modal(document.getElementById('editDetailResepModal')).show();
        });
    });

    // Hapus button handler
    document.querySelectorAll('.btn-hapus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var namaObat = this.dataset.nama_obat;
            var idResep = this.closest('tr').querySelector('td:nth-child(3)').innerText;
            document.getElementById('detailHapusInfo').innerHTML = '<strong>ID Detail: ' + this.dataset.id + '<br>ID Resep: ' + idResep + '<br>Obat: ' + namaObat + '</strong>';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-detail-resep-obat.php?hapus=' + this.dataset.id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });

    // Reset form tambah modal
    document.getElementById('tambahDetailResepModal').addEventListener('hidden.bs.modal', function() {
        this.querySelector('form').reset();
        $('.select2-resep').val(null).trigger('change');
        $('.select2-obat').val(null).trigger('change');
        $('#harga_satuan').val('');
        $('#subtotal_display').val('');
        $('#jumlah_obat').val(1);
        $('#catatanResepCard').hide();
        $('#catatanResepText').text('-');
    });

    // Reset form edit modal
    document.getElementById('editDetailResepModal').addEventListener('hidden.bs.modal', function() {
        this.querySelector('form').reset();
        $('.select2-resep-edit').val(null).trigger('change');
        $('.select2-obat-edit').val(null).trigger('change');
        $('#editCatatanResepCard').hide();
        $('#editCatatanResepText').text('-');
    });
    </script>
    
    <?php require_once "footer.php"; ?>
</body>
</html>