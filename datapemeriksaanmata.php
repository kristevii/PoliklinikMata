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

// Cek hak akses sesuai header.php: IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang bisa akses data pemeriksaan mata
if ($jabatan_user != 'IT Support' && 
    $role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Medical Record') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data pemeriksaan mata. Hanya Staff dengan jabatan IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data pemeriksaan mata
$all_pemeriksaan = $db->tampil_data_pemeriksaan_mata();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_pemeriksaan = [];
    foreach ($all_pemeriksaan as $pemeriksaan) {
        if (stripos($pemeriksaan['id_pemeriksaan'] ?? '', $search_query) !== false ||
            stripos($pemeriksaan['id_rekam'] ?? '', $search_query) !== false ||
            stripos($pemeriksaan['visus_od'] ?? '', $search_query) !== false ||
            stripos($pemeriksaan['visus_os'] ?? '', $search_query) !== false) {
            $filtered_pemeriksaan[] = $pemeriksaan;
        }
    }
    $all_pemeriksaan = $filtered_pemeriksaan;
}

// Urutkan data berdasarkan ID (default dari terbaru = DESC)
usort($all_pemeriksaan, function($a, $b) use ($sort_order) {
    $val_a = $a['id_pemeriksaan'] ?? 0;
    $val_b = $b['id_pemeriksaan'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_pemeriksaan);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_pemeriksaan = array_slice($all_pemeriksaan, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_pemeriksaan);

// Ambil data rekam medis untuk dropdown dengan detail lengkap
$query_rekam = "SELECT dr.id_rekam, dp.nama_pasien, dr.jenis_kunjungan, dr.tanggal_periksa 
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
    <title>Pemeriksaan Mata - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
    .badge-normal { background-color: #28a745; color: #fff; }
    .badge-abnormal { background-color: #dc3545; color: #fff; }
    .badge-warning { background-color: #ffc107; color: #000; }
    .table th { border-top: none; font-weight: 600; }
    .btn-hapus:hover, .btn-edit:hover, .btn-view:hover { transform: scale(1.05); transition: all 0.3s ease; }
    .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .card-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
        border-radius: 6px;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d;
    }
    .detail-icon { min-width: 40px; text-align: center; }
    .detail-card {
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s ease;
    }
    .detail-card:hover {
        transform: translateY(-2px);
    }
    .info-row {
        margin-bottom: 12px;
    }
    .refraction-value {
        font-family: monospace;
        font-size: 1.1em;
        font-weight: 500;
    }
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
                                <li class="breadcrumb-item" aria-current="page">Data Pemeriksaan Mata</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Pemeriksaan Mata</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- [ Main Content ] start -->
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
                            setTimeout(function() {
                                if (alert && alert.parentNode) {
                                    alert.parentNode.removeChild(alert);
                                }
                            }, 150);
                        }
                    }, 5000);
                </script>
                <?php endif; ?>

                <div class="d-flex justify-content-start mb-4">
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahPemeriksaanModal">
                        <i class="fas fa-plus me-1"></i> Tambah Pemeriksaan Mata
                    </button>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Show Entries dan Search -->
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari data pemeriksaan..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="datapemeriksaanmata.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($search_query)): ?>
                            <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                                <i class="fas fa-info-circle me-2"></i> Menampilkan hasil pencarian untuk: <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><a href="<?= getSortUrl($sort_order) ?>" class="text-decoration-none text-dark">No <?php if ($sort_order === 'asc'): ?><i class="fas fa-sort-up ms-1"></i><?php else: ?><i class="fas fa-sort-down ms-1"></i><?php endif; ?></a></th>
                                        <th>ID</th>
                                        <th>ID Rekam</th>
                                        <th>Visus OD</th>
                                        <th>Visus OS</th>
                                        <th>OD(Sph/Cyl/Axis)</th>
                                        <th>OS(Sph/Cyl/Axis)</th>
                                        <th>TIO OD / TIO OS</th>
                                        <th>Tanggal Pemeriksaan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_pemeriksaan) && is_array($data_pemeriksaan)) {
                                        $counter = $start_number;
                                        foreach ($data_pemeriksaan as $pemeriksaan) {
                                            $id_pemeriksaan = htmlspecialchars($pemeriksaan['id_pemeriksaan'] ?? '');
                                            $id_rekam = htmlspecialchars($pemeriksaan['id_rekam'] ?? '');
                                            $visus_od = htmlspecialchars($pemeriksaan['visus_od'] ?? '-');
                                            $visus_os = htmlspecialchars($pemeriksaan['visus_os'] ?? '-');
                                            $sph_od = htmlspecialchars($pemeriksaan['sph_od'] ?? '0');
                                            $cyl_od = htmlspecialchars($pemeriksaan['cyl_od'] ?? '0');
                                            $axis_od = htmlspecialchars($pemeriksaan['axis_od'] ?? '0');
                                            $sph_os = htmlspecialchars($pemeriksaan['sph_os'] ?? '0');
                                            $cyl_os = htmlspecialchars($pemeriksaan['cyl_os'] ?? '0');
                                            $axis_os = htmlspecialchars($pemeriksaan['axis_os'] ?? '0');
                                            $tio_od = htmlspecialchars($pemeriksaan['tio_od'] ?? '-');
                                            $tio_os = htmlspecialchars($pemeriksaan['tio_os'] ?? '-');
                                            $slit_lamp = htmlspecialchars($pemeriksaan['slit_lamp'] ?? '-');
                                            $catatan = htmlspecialchars($pemeriksaan['catatan'] ?? '-');
                                            $created_at = !empty($pemeriksaan['created_at']) ? date('d/m/Y H:i:s', strtotime($pemeriksaan['created_at'])) : '-';
                                            
                                            // Escape untuk JavaScript
                                            $js_id_pemeriksaan = escapeJsString($id_pemeriksaan);
                                            $js_id_rekam = escapeJsString($id_rekam);
                                            $js_visus_od = escapeJsString($visus_od);
                                            $js_visus_os = escapeJsString($visus_os);
                                            $js_sph_od = escapeJsString($sph_od);
                                            $js_cyl_od = escapeJsString($cyl_od);
                                            $js_axis_od = escapeJsString($axis_od);
                                            $js_sph_os = escapeJsString($sph_os);
                                            $js_cyl_os = escapeJsString($cyl_os);
                                            $js_axis_os = escapeJsString($axis_os);
                                            $js_tio_od = escapeJsString($tio_od);
                                            $js_tio_os = escapeJsString($tio_os);
                                            $js_slit_lamp = escapeJsString($slit_lamp);
                                            $js_catatan = escapeJsString($catatan);
                                            $js_created_at = escapeJsString($created_at);
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= $id_pemeriksaan ?></td>
                                        <td><?= $id_rekam ?></td>
                                        <td><?= $visus_od ?></td>
                                        <td><?= $visus_os ?></td>
                                        <td><small><?= $sph_od ?> / <?= $cyl_od ?> / <?= $axis_od ?>°</small></td>
                                        <td><small><?= $sph_os ?> / <?= $cyl_os ?> / <?= $axis_os ?>°</small></td>
                                        <td><small><?= $tio_od ?> mmHg / <?= $tio_os ?> mmHg</small></td>
                                        <td><?= $created_at ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm btn-view"
                                                    onclick='showDetailPemeriksaanModal("<?= $js_id_pemeriksaan ?>", "<?= $js_id_rekam ?>", "<?= $js_visus_od ?>", "<?= $js_visus_os ?>", "<?= $js_sph_od ?>", "<?= $js_cyl_od ?>", "<?= $js_axis_od ?>", "<?= $js_sph_os ?>", "<?= $js_cyl_os ?>", "<?= $js_axis_os ?>", "<?= $js_tio_od ?>", "<?= $js_tio_os ?>", "<?= $js_slit_lamp ?>", "<?= $js_catatan ?>", "<?= $js_created_at ?>")'
                                                    title="Lihat Detail Pemeriksaan">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                    data-id="<?= $id_pemeriksaan ?>"
                                                    data-id_rekam="<?= $id_rekam ?>"
                                                    data-visus_od="<?= $visus_od ?>"
                                                    data-visus_os="<?= $visus_os ?>"
                                                    data-sph_od="<?= $sph_od ?>"
                                                    data-cyl_od="<?= $cyl_od ?>"
                                                    data-axis_od="<?= $axis_od ?>"
                                                    data-sph_os="<?= $sph_os ?>"
                                                    data-cyl_os="<?= $cyl_os ?>"
                                                    data-axis_os="<?= $axis_os ?>"
                                                    data-tio_od="<?= $tio_od ?>"
                                                    data-tio_os="<?= $tio_os ?>"
                                                    data-slit_lamp="<?= htmlspecialchars($pemeriksaan['slit_lamp'] ?? '') ?>"
                                                    data-catatan="<?= htmlspecialchars($pemeriksaan['catatan'] ?? '') ?>"
                                                    title="Edit Pemeriksaan Mata">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm btn-hapus"
                                                    data-id="<?= $id_pemeriksaan ?>"
                                                    data-id_rekam="<?= $id_rekam ?>"
                                                    title="Hapus Pemeriksaan Mata">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                            $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1;
                                        }
                                    } else {
                                        echo '<tr><td colspan="12" class="text-center text-muted">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data pemeriksaan yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data pemeriksaan mata ditemukan.';
                                        }
                                        echo '</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_entries > 0): ?>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="text-muted mb-0">
                                    Menampilkan <?= $total_entries > 0 ? ($offset + 1) : 0 ?> 
                                    sampai <?= min($offset + $entries_per_page, $total_entries) ?> 
                                    dari <?= $total_entries ?> entri
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

    <!-- Modal Tambah Pemeriksaan Mata -->
    <div class="modal fade" id="tambahPemeriksaanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Pemeriksaan Mata Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-pemeriksaan-mata.php">
                    <input type="hidden" name="tambah_pemeriksaan" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                                    <select class="form-select select2-rekam-tambah" name="id_rekam" id="id_rekam_tambah" required style="width: 100%;">
                                        <option value="">-- Cari Rekam Medis --</option>
                                        <?php foreach ($data_rekam_medis as $rekam): ?>
                                        <option value="<?= htmlspecialchars($rekam['id_rekam'] ?? '') ?>" 
                                            data-nama="<?= htmlspecialchars($rekam['nama_pasien'] ?? '') ?>"
                                            data-jenis="<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '') ?>"
                                            data-tanggal="<?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>">
                                            <?= htmlspecialchars($rekam['id_rekam'] ?? '') ?> - 
                                            <?= htmlspecialchars($rekam['nama_pasien'] ?? '-') ?> 
                                            (<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') ?> - 
                                            <?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Format: ID Rekam - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Pemeriksaan Mata Kanan (OD)</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Visus OD</label>
                                    <input type="text" class="form-control" name="visus_od" placeholder="Contoh: 6/6, 6/9">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Sph OD</label>
                                    <input type="number" step="0.25" class="form-control" name="sph_od" placeholder="Spherical">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Cyl OD</label>
                                    <input type="number" step="0.25" class="form-control" name="cyl_od" placeholder="Cylinder">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Axis OD</label>
                                    <input type="number" class="form-control" name="axis_od" placeholder="0-180">
                                    <small class="text-muted">Tidak boleh minus (-)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Pemeriksaan Mata Kiri (OS)</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Visus OS</label>
                                    <input type="text" class="form-control" name="visus_os" placeholder="Contoh: 6/6, 6/9">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Sph OS</label>
                                    <input type="number" step="0.25" class="form-control" name="sph_os" placeholder="Spherical">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Cyl OS</label>
                                    <input type="number" step="0.25" class="form-control" name="cyl_os" placeholder="Cylinder">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Axis OS</label>
                                    <input type="number" class="form-control" name="axis_os" placeholder="0-180">
                                    <small class="text-muted">Tidak boleh minus (-)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Tekanan Intra Okular (TIO)</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">TIO OD (mmHg)</label>
                                    <input type="number" class="form-control" name="tio_od" placeholder="10-21 mmHg normal">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">TIO OS (mmHg)</label>
                                    <input type="number" class="form-control" name="tio_os" placeholder="10-21 mmHg normal">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slit Lamp Examination</label>
                            <textarea class="form-control" name="slit_lamp" rows="2" placeholder="Hasil pemeriksaan slit lamp..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan / Keterangan</label>
                            <textarea class="form-control" name="catatan" rows="2" placeholder="Catatan tambahan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pemeriksaan Mata -->
    <div class="modal fade" id="editPemeriksaanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Pemeriksaan Mata</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-pemeriksaan-mata.php">
                    <input type="hidden" name="edit_pemeriksaan" value="1">
                    <input type="hidden" name="id_pemeriksaan" id="edit_id_pemeriksaan">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                                    <select class="form-select select2-rekam-edit" name="id_rekam" id="edit_id_rekam" required style="width: 100%;">
                                        <option value="">-- Cari Rekam Medis --</option>
                                        <?php foreach ($data_rekam_medis as $rekam): ?>
                                        <option value="<?= htmlspecialchars($rekam['id_rekam'] ?? '') ?>"
                                            data-nama="<?= htmlspecialchars($rekam['nama_pasien'] ?? '') ?>"
                                            data-jenis="<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '') ?>"
                                            data-tanggal="<?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>">
                                            <?= htmlspecialchars($rekam['id_rekam'] ?? '') ?> - 
                                            <?= htmlspecialchars($rekam['nama_pasien'] ?? '-') ?> 
                                            (<?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') ?> - 
                                            <?= !empty($rekam['tanggal_periksa']) ? date('d/m/Y', strtotime($rekam['tanggal_periksa'])) : '-' ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Format: ID Rekam - Nama Pasien (Jenis Kunjungan - Tanggal Periksa)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Pemeriksaan Mata Kanan (OD)</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Visus OD</label>
                                    <input type="text" class="form-control" name="visus_od" id="edit_visus_od">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Sph OD</label>
                                    <input type="number" step="0.25" class="form-control" name="sph_od" id="edit_sph_od">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Cyl OD</label>
                                    <input type="number" step="0.25" class="form-control" name="cyl_od" id="edit_cyl_od">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Axis OD</label>
                                    <input type="number" class="form-control" name="axis_od" id="edit_axis_od">
                                    <small class="text-muted">Tidak boleh minus (-)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Pemeriksaan Mata Kiri (OS)</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Visus OS</label>
                                    <input type="text" class="form-control" name="visus_os" id="edit_visus_os">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Sph OS</label>
                                    <input type="number" step="0.25" class="form-control" name="sph_os" id="edit_sph_os">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Cyl OS</label>
                                    <input type="number" step="0.25" class="form-control" name="cyl_os" id="edit_cyl_os">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Axis OS</label>
                                    <input type="number" class="form-control" name="axis_os" id="edit_axis_os">
                                    <small class="text-muted">Tidak boleh minus (-)</small>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Tekanan Intra Okular (TIO)</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">TIO OD (mmHg)</label>
                                    <input type="number" class="form-control" name="tio_od" id="edit_tio_od">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">TIO OS (mmHg)</label>
                                    <input type="number" class="form-control" name="tio_os" id="edit_tio_os">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slit Lamp Examination</label>
                            <textarea class="form-control" name="slit_lamp" id="edit_slit_lamp" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan / Keterangan</label>
                            <textarea class="form-control" name="catatan" id="edit_catatan" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Pemeriksaan Mata -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    </div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus pemeriksaan mata ini?</p>
                    <p class="text-center text-danger" id="idPemeriksaanHapus"></p>
                    <p class="text-center text-muted mt-3"><small>Data yang dihapus tidak dapat dikembalikan.</small></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>PERINGATAN:</strong> Data pemeriksaan mata akan dihapus permanen!
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Pemeriksaan Mata -->
    <div class="modal fade" id="detailPemeriksaanModal" tabindex="-1" aria-labelledby="detailPemeriksaanModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="detailPemeriksaanModalLabel">
                        <i class="fas fa-eye me-2"></i>Detail Pemeriksaan Mata
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1" id="detailIdPemeriksaan">-</h4>
                        <div class="text-muted mb-3"><i class="fas fa-calendar me-1"></i><span id="detailTanggalPemeriksaan">-</span></div>
                    </div>
                    
                    <hr>
                    
                    <!-- Pemeriksaan Mata Kanan (OD) -->
                    <div class="card border-0 shadow-sm mb-4 detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-eye me-2 text-primary"></i>Pemeriksaan Mata Kanan (OD)</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Visus OD</small>
                                        <span class="fw-medium fs-5" id="detailVisusOD">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">TIO OD (mmHg)</small>
                                        <span class="fw-medium" id="detailTioOD">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Sph OD</small>
                                        <span class="fw-medium refraction-value" id="detailSphOD">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Cyl OD</small>
                                        <span class="fw-medium refraction-value" id="detailCylOD">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Axis OD</small>
                                        <span class="fw-medium" id="detailAxisOD">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pemeriksaan Mata Kiri (OS) -->
                    <div class="card border-0 shadow-sm mb-4 detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-eye me-2 text-primary"></i>Pemeriksaan Mata Kiri (OS)</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Visus OS</small>
                                        <span class="fw-medium fs-5" id="detailVisusOS">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">TIO OS (mmHg)</small>
                                        <span class="fw-medium" id="detailTioOS">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Sph OS</small>
                                        <span class="fw-medium refraction-value" id="detailSphOS">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Cyl OS</small>
                                        <span class="fw-medium refraction-value" id="detailCylOS">-</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Axis OS</small>
                                        <span class="fw-medium" id="detailAxisOS">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pemeriksaan Lainnya -->
                    <div class="card border-0 shadow-sm detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-stethoscope me-2 text-primary"></i>Pemeriksaan Lainnya</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Slit Lamp Examination</small>
                                        <div class="p-3 bg-light rounded mt-1" id="detailSlitLamp">-</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Catatan / Keterangan</small>
                                        <div class="p-3 bg-light rounded mt-1" id="detailCatatanPemeriksaan">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Tutup</button>
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
    
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        window.location.href = 'datapemeriksaanmata.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }
    
    // Fungsi untuk menampilkan detail pemeriksaan (hanya data dari tabel pemeriksaan mata)
    function showDetailPemeriksaanModal(id_pemeriksaan, id_rekam, visus_od, visus_os, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, tio_od, tio_os, slit_lamp, catatan, created_at) {
        // Set data pemeriksaan
        document.getElementById('detailIdPemeriksaan').textContent = 'Pemeriksaan Mata #' + id_pemeriksaan;
        document.getElementById('detailTanggalPemeriksaan').textContent = created_at || '-';
        document.getElementById('detailVisusOD').textContent = visus_od || '-';
        document.getElementById('detailVisusOS').textContent = visus_os || '-';
        document.getElementById('detailSphOD').textContent = sph_od || '0';
        document.getElementById('detailCylOD').textContent = cyl_od || '0';
        document.getElementById('detailAxisOD').textContent = (axis_od && axis_od != '0') ? axis_od + '°' : '-';
        document.getElementById('detailSphOS').textContent = sph_os || '0';
        document.getElementById('detailCylOS').textContent = cyl_os || '0';
        document.getElementById('detailAxisOS').textContent = (axis_os && axis_os != '0') ? axis_os + '°' : '-';
        document.getElementById('detailTioOD').textContent = tio_od ? tio_od + ' mmHg' : '-';
        document.getElementById('detailTioOS').textContent = tio_os ? tio_os + ' mmHg' : '-';
        document.getElementById('detailSlitLamp').textContent = slit_lamp || '-';
        document.getElementById('detailCatatanPemeriksaan').textContent = catatan || '-';
        
        new bootstrap.Modal(document.getElementById('detailPemeriksaanModal')).show();
    }
    
    // Inisialisasi Select2 untuk pencarian Rekam Medis
    $(document).ready(function() {
        // Untuk modal tambah
        $('.select2-rekam-tambah').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#tambahPemeriksaanModal'),
            placeholder: 'Ketik ID Rekam Medis atau Nama Pasien...',
            allowClear: true,
            width: '100%'
        });
        
        // Untuk modal edit
        $('.select2-rekam-edit').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#editPemeriksaanModal'),
            placeholder: 'Ketik ID Rekam Medis atau Nama Pasien...',
            allowClear: true,
            width: '100%'
        });
    });
    
    // Edit modal handler
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id_pemeriksaan').value = this.dataset.id;
            
            // Set value untuk select2
            const id_rekam = this.dataset.id_rekam;
            $('#edit_id_rekam').val(id_rekam).trigger('change');
            
            document.getElementById('edit_visus_od').value = this.dataset.visus_od || '';
            document.getElementById('edit_visus_os').value = this.dataset.visus_os || '';
            document.getElementById('edit_sph_od').value = this.dataset.sph_od || '';
            document.getElementById('edit_cyl_od').value = this.dataset.cyl_od || '';
            document.getElementById('edit_axis_od').value = this.dataset.axis_od || '';
            document.getElementById('edit_sph_os').value = this.dataset.sph_os || '';
            document.getElementById('edit_cyl_os').value = this.dataset.cyl_os || '';
            document.getElementById('edit_axis_os').value = this.dataset.axis_os || '';
            document.getElementById('edit_tio_od').value = this.dataset.tio_od || '';
            document.getElementById('edit_tio_os').value = this.dataset.tio_os || '';
            document.getElementById('edit_slit_lamp').value = this.dataset.slit_lamp || '';
            document.getElementById('edit_catatan').value = this.dataset.catatan || '';
            
            new bootstrap.Modal(document.getElementById('editPemeriksaanModal')).show();
        });
    });
    
    // Hapus modal handler
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const id_rekam = this.dataset.id_rekam;
            document.getElementById('idPemeriksaanHapus').innerHTML = '<strong>ID Pemeriksaan: ' + id + '<br>ID Rekam: ' + id_rekam + '</strong>';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-pemeriksaan-mata.php?hapus=' + id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });
    
    // Reset form ketika modal ditutup
    document.getElementById('tambahPemeriksaanModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tambahPemeriksaanModal').querySelector('form').reset();
        // Reset select2
        $('#id_rekam_tambah').val(null).trigger('change');
    });
    
    // Auto show modal jika data kosong
    <?php if ($is_data_empty): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('tambahPemeriksaanModal')).show();
        }, 500);
    });
    <?php endif; ?>
    </script>
    
    <?php require_once "footer.php"; ?>
</body>
</html>

<?php
// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    return 'datapemeriksaanmata.php?' . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $params = [];
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'datapemeriksaanmata.php?' . implode('&', $params);
}
?>