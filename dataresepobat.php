<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Staff dengan jabatan IT Support yang bisa mengakses halaman ini (sesuai header.php)
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'IT Support') {
    header("Location: dashboard.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data resep obat (DATA MURNI dari tabel data_resep_obat)
$query_murni = "SELECT * FROM data_resep_obat";
$result_murni = $db->koneksi->query($query_murni);
$all_resep = [];
if ($result_murni && $result_murni->num_rows > 0) {
    while ($row = $result_murni->fetch_assoc()) {
        $all_resep[] = $row;
    }
}

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_resep = [];
    foreach ($all_resep as $resep) {
        if (stripos($resep['id_resep_obat'] ?? '', $search_query) !== false ||
            stripos($resep['id_rekam'] ?? '', $search_query) !== false ||
            stripos($resep['catatan'] ?? '', $search_query) !== false ||
            stripos($resep['tanggal_resep'] ?? '', $search_query) !== false) {
            $filtered_resep[] = $resep;
        }
    }
    $all_resep = $filtered_resep;
}

// Urutkan data berdasarkan ID
usort($all_resep, function($a, $b) use ($sort_order) {
    $val_a = $a['id_resep_obat'] ?? 0;
    $val_b = $b['id_resep_obat'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_resep);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_resep = array_slice($all_resep, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_resep);

// Ambil data rekam medis untuk dropdown dengan detail lengkap
$query_rekam = "SELECT dr.id_rekam, dp.nik, dp.nama_pasien, dr.jenis_kunjungan, dr.tanggal_periksa 
                FROM data_rekam_medis dr 
                JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien 
                ORDER BY dr.id_rekam DESC";
$result_rekam = $db->koneksi->query($query_rekam);
$data_rekam_medis = [];
if ($result_rekam && $result_rekam->num_rows > 0) {
    while ($row = $result_rekam->fetch_assoc()) {
        $data_rekam_medis[] = $row;
    }
}

// Fungsi untuk escape JavaScript string
function escapeJsString($str) {
    return str_replace(
        ["\\", "'", '"', "\n", "\r", "\t", "\x08", "\x0c"],
        ["\\\\", "\\'", '\\"', "\\n", "\\r", "\\t", "\\b", "\\f"],
        $str
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Resep Obat - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    .detail-card { border-radius: 12px; overflow: hidden; transition: transform 0.2s ease; }
    .detail-card:hover { transform: translateY(-2px); }
    .info-row { margin-bottom: 12px; }
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
                                <li class="breadcrumb-item" aria-current="page">Data Resep Obat</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Resep Obat</h2>
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
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahResepModal">
                        <i class="fas fa-plus me-1"></i> Tambah Resep Obat
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari ID Resep, ID Rekam, atau Catatan..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="dataresepobat.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><a href="<?= getSortUrl($sort_order) ?>" class="text-decoration-none text-dark">No <?php if ($sort_order === 'asc'): ?><i class="fas fa-sort-up ms-1"></i><?php else: ?><i class="fas fa-sort-down ms-1"></i><?php endif; ?></a></th>
                                        <th>ID</th>
                                        <th>ID Rekam</th>
                                        <th>Tanggal Resep</th>
                                        <th>Catatan</th>
                                        <th>Created At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_resep) && is_array($data_resep)): ?>
                                        <?php $counter = $start_number; foreach ($data_resep as $resep): ?>
                                            <?php
                                            $id_resep_obat = htmlspecialchars($resep['id_resep_obat'] ?? '');
                                            $id_rekam = htmlspecialchars($resep['id_rekam'] ?? '');
                                            $tanggal_resep = !empty($resep['tanggal_resep']) ? date('d/m/Y H:i:s', strtotime($resep['tanggal_resep'])) : '-';
                                            $catatan = htmlspecialchars($resep['catatan'] ?? '-');
                                            $created_at = !empty($resep['created_at']) ? date('d/m/Y H:i:s', strtotime($resep['created_at'])) : '-';
                                            ?>
                                            <tr>
                                                <td><?= $counter ?></td>
                                                <td><?= $id_resep_obat ?></td>
                                                <td><?= $id_rekam ?></td>
                                                <td><?= $tanggal_resep ?></td>
                                                <td><?= strlen($catatan) > 50 ? substr($catatan, 0, 50) . '...' : $catatan ?></td>
                                                <td><?= $created_at ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-info btn-sm btn-view" onclick='showDetail(<?= $id_resep_obat ?>, <?= $id_rekam ?>, "<?= addslashes($catatan) ?>", "<?= $tanggal_resep ?>", "<?= $created_at ?>")' title="Lihat Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-warning btn-sm btn-edit" data-id="<?= $id_resep_obat ?>" data-id_rekam="<?= $id_rekam ?>" data-tanggal_resep="<?= $tanggal_resep ?>" data-catatan="<?= htmlspecialchars($resep['catatan'] ?? '') ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-hapus" data-id="<?= $id_resep_obat ?>" data-id_rekam="<?= $id_rekam ?>" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted">Tidak ada data resep obat ditemukan.<?php ?>
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

    <!-- Modal Tambah -->
    <div class="modal fade" id="tambahResepModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Resep Obat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="proses/tambah/tambah-data-resep-obat.php">
                    <input type="hidden" name="tambah_resep" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-rekam-tambah" name="id_rekam" required style="width:100%">
                                <option value="">-- Cari Rekam Medis --</option>
                                <?php foreach ($data_rekam_medis as $rekam): ?>
                                <option value="<?= $rekam['id_rekam'] ?>"
                                    data-nama="<?= htmlspecialchars($rekam['nama_pasien'] ?? '') ?>"
                                    data-jenis="<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '') ?>"
                                    data-tanggal="<?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>">
                                    <?= $rekam['id_rekam'] ?> - <?= $rekam['nik'] ?> - <?= $rekam['nama_pasien'] ?> (<?= $rekam['jenis_kunjungan'] ?> - <?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Format: ID Rekam - NIK - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                        </div>
                        <div class="mb-3"><label class="form-label">Tanggal Resep <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" name="tanggal_resep" required></div>
                        <div class="mb-3"><label class="form-label">Catatan</label><textarea class="form-control" name="catatan" rows="4"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editResepModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Resep Obat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="proses/edit/edit-data-resep-obat.php">
                    <input type="hidden" name="edit_resep" value="1">
                    <input type="hidden" name="id_resep_obat" id="edit_id_resep_obat">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-rekam-edit" name="id_rekam" id="edit_id_rekam" required style="width:100%">
                                <option value="">-- Cari Rekam Medis --</option>
                                <?php foreach ($data_rekam_medis as $rekam): ?>
                                <option value="<?= $rekam['id_rekam'] ?>"
                                    data-nama="<?= htmlspecialchars($rekam['nama_pasien'] ?? '') ?>"
                                    data-jenis="<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '') ?>"
                                    data-tanggal="<?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>">
                                    <?= $rekam['id_rekam'] ?> - <?= $rekam['nik'] ?> - <?= $rekam['nama_pasien'] ?> (<?= $rekam['jenis_kunjungan'] ?> - <?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Format: ID Rekam - NIK - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                        </div>
                        <div class="mb-3"><label class="form-label">Tanggal Resep <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" name="tanggal_resep" id="edit_tanggal_resep" required></div>
                        <div class="mb-3"><label class="form-label">Catatan</label><textarea class="form-control" name="catatan" id="edit_catatan" rows="4"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Update</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-center">
                    <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    <p>Apakah Anda yakin ingin menghapus resep obat ini?</p>
                    <p class="text-danger" id="resepHapusInfo"></p>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i><strong>PERINGATAN:</strong> Data akan dihapus permanen!</div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailResepModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-prescription-bottle me-2"></i>Detail Resep Obat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4"><h4 class="fw-bold mb-1" id="detailIdResep">-</h4></div>
                    <hr>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-database me-2 text-primary"></i>Informasi Resep Obat</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">ID Resep Obat</small><span id="detailIdResepFull">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">ID Rekam Medis</small><span id="detailIdRekam">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Tanggal Resep</small><span id="detailTanggalResep">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Created At</small><span id="detailCreatedAt">-</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-capsules me-2 text-primary"></i>Catatan Resep Obat</h6>
                            <div class="p-3 bg-light rounded" style="white-space: pre-wrap;" id="detailCatatanResep">-</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
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
        window.location.href = 'dataresepobat.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }
    
    function showDetail(id_resep, id_rekam, catatan, tanggal_resep, created_at) {
        document.getElementById('detailIdResep').innerHTML = 'Resep Obat #' + id_resep;
        document.getElementById('detailIdResepFull').innerHTML = id_resep;
        document.getElementById('detailIdRekam').innerHTML = id_rekam;
        document.getElementById('detailTanggalResep').innerHTML = tanggal_resep;
        document.getElementById('detailCreatedAt').innerHTML = created_at;
        document.getElementById('detailCatatanResep').innerHTML = catatan || '-';
        new bootstrap.Modal(document.getElementById('detailResepModal')).show();
    }
    
    $(document).ready(function() {
        $('.select2-rekam-tambah').select2({ 
            theme: 'bootstrap-5', 
            dropdownParent: $('#tambahResepModal'), 
            placeholder: 'Cari rekam medis...', 
            allowClear: true, 
            width: '100%' 
        });
        $('.select2-rekam-edit').select2({ 
            theme: 'bootstrap-5', 
            dropdownParent: $('#editResepModal'), 
            placeholder: 'Cari rekam medis...', 
            allowClear: true, 
            width: '100%' 
        });
    });
    
    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id_resep_obat').value = this.dataset.id;
            $('#edit_id_rekam').val(this.dataset.id_rekam).trigger('change');
            var tanggal = this.dataset.tanggal_resep;
            if (tanggal && tanggal !== '-') {
                var parts = tanggal.split(' ');
                if (parts.length >= 2) {
                    var dateParts = parts[0].split('/');
                    var timeParts = parts[1].split(':');
                    if (dateParts.length >= 3 && timeParts.length >= 2) {
                        var formatted = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0] + 'T' + timeParts[0] + ':' + timeParts[1];
                        document.getElementById('edit_tanggal_resep').value = formatted;
                    }
                }
            }
            document.getElementById('edit_catatan').value = this.dataset.catatan || '';
            new bootstrap.Modal(document.getElementById('editResepModal')).show();
        });
    });
    
    document.querySelectorAll('.btn-hapus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('resepHapusInfo').innerHTML = '<strong>ID Resep: ' + this.dataset.id + '<br>ID Rekam: ' + this.dataset.id_rekam + '</strong>';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-resep-obat.php?hapus=' + this.dataset.id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });
    
    document.getElementById('tambahResepModal').addEventListener('hidden.bs.modal', function() {
        this.querySelector('form').reset();
        $('.select2-rekam-tambah').val(null).trigger('change');
    });
    </script>
    
    <?php require_once "footer.php"; ?>
</body>
</html>

<?php
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    return 'dataresepobat.php?' . implode('&', $params);
}

function getSortUrl($current_sort) {
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $params = [];
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($page > 1) $params[] = 'page=' . $page;
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'dataresepobat.php?' . implode('&', $params);
}
?>