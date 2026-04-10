<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support
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

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data alat medis
$all_alat = $db->tampil_data_alat_medis();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_alat = [];
    foreach ($all_alat as $alat) {
        if (stripos($alat['id_alat'] ?? '', $search_query) !== false ||
            stripos($alat['kode_alat'] ?? '', $search_query) !== false ||
            stripos($alat['nama_alat'] ?? '', $search_query) !== false ||
            stripos($alat['jenis_alat'] ?? '', $search_query) !== false ||
            stripos($alat['lokasi'] ?? '', $search_query) !== false ||
            stripos($alat['kondisi'] ?? '', $search_query) !== false ||
            stripos($alat['status'] ?? '', $search_query) !== false) {
            $filtered_alat[] = $alat;
        }
    }
    $all_alat = $filtered_alat;
}

// Urutkan data berdasarkan ID
usort($all_alat, function($a, $b) use ($sort_order) {
    $val_a = $a['id_alat'] ?? 0;
    $val_b = $b['id_alat'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_alat);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_alat = array_slice($all_alat, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_alat);

// Generate kode alat berikutnya
function getNextKodeAlat($db) {
    $query = "SELECT kode_alat FROM data_alat_medis ORDER BY id_alat DESC LIMIT 1";
    $result = $db->koneksi->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_kode = $row['kode_alat'];
        
        if (preg_match('/(\d+)$/', $last_kode, $matches)) {
            $last_number = (int)$matches[1];
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
    } else {
        $new_number = 1;
    }
    
    return 'ALT' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

$next_kode_alat = getNextKodeAlat($db);

// Fungsi untuk escape JavaScript string
function escapeJsString($str) {
    return str_replace(
        ["\\", "'", '"', "\n", "\r", "\t", "\x08", "\x0c"],
        ["\\\\", "\\'", '\\"', "\\n", "\\r", "\\t", "\\b", "\\f"],
        $str
    );
}

// Fungsi format tanggal Indonesia
function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', strtotime($tanggal));
    $bln = (int)date('m', strtotime($tanggal));
    $thn = date('Y', strtotime($tanggal));
    return $tgl . ' ' . $bulan[$bln] . ' ' . $thn;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Alat Medis - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    .badge-baik { background-color: #28a745; color: #fff; }
    .badge-tidak-baik { background-color: #dc3545; color: #fff; }
    .badge-warning { background-color: #ffc107; color: #000; }
    .badge-aktif { background-color: #17a2b8; color: #fff; }
    .badge-tidak-aktif { background-color: #6c757d; color: #fff; }
    .badge-maintenance { background-color: #ffc107; color: #000; }
    .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .btn-hapus:hover, .btn-edit:hover, .btn-view:hover { transform: scale(1.05); transition: all 0.3s ease; }
    .table th { border-top: none; font-weight: 600; }
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
    .detail-header {
        background: linear-gradient(135deg, #17a2b8 0%, #28a745 100%);
        color: white;
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
                                <li class="breadcrumb-item" aria-current="page">Data Alat Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Alat Medis</h2>
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
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahAlatModal">
                        <i class="fas fa-plus me-1"></i> Tambah Alat Medis
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari data alat medis..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="dataalatmedis.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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
                                        <th>Kode Alat</th>
                                        <th>Nama Alat</th>
                                        <th>Jenis Alat</th>
                                        <th>Lokasi</th>
                                        <th>Kondisi</th>
                                        <th>Status</th>
                                        <th>Tanggal Beli</th>
                                        <th>Create At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_alat) && is_array($data_alat)) {
                                        $counter = $start_number;
                                        foreach ($data_alat as $alat) {
                                            $id_alat = htmlspecialchars($alat['id_alat'] ?? '');
                                            $kode_alat = htmlspecialchars($alat['kode_alat'] ?? '');
                                            $nama_alat = htmlspecialchars($alat['nama_alat'] ?? '');
                                            $jenis_alat = htmlspecialchars($alat['jenis_alat'] ?? '');
                                            $lokasi = htmlspecialchars($alat['lokasi'] ?? '');
                                            $kondisi = htmlspecialchars($alat['kondisi'] ?? '');
                                            $status = htmlspecialchars($alat['status'] ?? '');
                                            $deskripsi = htmlspecialchars($alat['deskripsi'] ?? '');
                                            $tanggal_beli = !empty($alat['tanggal_beli']) ? $alat['tanggal_beli'] : '';
                                            $tanggal_beli_display = !empty($tanggal_beli) ? date('d/m/Y', strtotime($tanggal_beli)) : '-';
                                            $created_at = !empty($alat['created_at']) ? date('d/m/Y H:i:s', strtotime($alat['created_at'])) : '-';
                                            
                                            // Badge kondisi
                                            $kondisi_class = '';
                                            switch ($kondisi) {
                                                case 'Baik': $kondisi_class = 'badge-baik'; break;
                                                case 'Rusak Ringan': $kondisi_class = 'badge-warning'; break;
                                                case 'Rusak Berat': $kondisi_class = 'badge-tidak-baik'; break;
                                                default: $kondisi_class = 'badge-secondary';
                                            }
                                            
                                            // Badge status
                                            $status_class = '';
                                            switch ($status) {
                                                case 'Aktif': $status_class = 'badge-aktif'; break;
                                                case 'Tidak Aktif': $status_class = 'badge-tidak-aktif'; break;
                                                case 'Maintenance': $status_class = 'badge-maintenance'; break;
                                                default: $status_class = 'badge-secondary';
                                            }
                                            
                                            // Escape untuk JavaScript
                                            $js_id_alat = escapeJsString($id_alat);
                                            $js_kode_alat = escapeJsString($kode_alat);
                                            $js_nama_alat = escapeJsString($nama_alat);
                                            $js_jenis_alat = escapeJsString($jenis_alat);
                                            $js_lokasi = escapeJsString($lokasi);
                                            $js_kondisi = escapeJsString($kondisi);
                                            $js_status = escapeJsString($status);
                                            $js_tanggal_beli = escapeJsString($tanggal_beli);
                                            $js_deskripsi = escapeJsString($deskripsi);
                                            $js_created_at = escapeJsString($created_at);
                                            $js_tanggal_beli_display = escapeJsString($tanggal_beli_display);
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= $id_alat ?></td>
                                        <td><?= $kode_alat ?></td>
                                        <td><?= $nama_alat ?></td>
                                        <td><?= $jenis_alat ?></td>
                                        <td><?= $lokasi ?></td>
                                        <td><span class="badge <?= $kondisi_class ?>"><?= $kondisi ?></span></td>
                                        <td><span class="badge <?= $status_class ?>"><?= $status ?></span></td>
                                        <td><?= $tanggal_beli_display ?></td>
                                        <td><?= $created_at ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm btn-view"
                                                    onclick='showDetailAlatModal("<?= $js_id_alat ?>", "<?= $js_kode_alat ?>", "<?= $js_nama_alat ?>", "<?= $js_jenis_alat ?>", "<?= $js_lokasi ?>", "<?= $js_kondisi ?>", "<?= $js_status ?>", "<?= $js_tanggal_beli ?>", "<?= $js_deskripsi ?>", "<?= $js_created_at ?>", "<?= $js_tanggal_beli_display ?>")'
                                                    title="Lihat Detail Alat Medis">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                    data-id="<?= $id_alat ?>"
                                                    data-kode="<?= $kode_alat ?>"
                                                    data-nama="<?= $nama_alat ?>"
                                                    data-jenis="<?= $jenis_alat ?>"
                                                    data-lokasi="<?= $lokasi ?>"
                                                    data-kondisi="<?= $kondisi ?>"
                                                    data-status="<?= $status ?>"
                                                    data-tanggal="<?= $tanggal_beli ?>"
                                                    data-deskripsi="<?= $deskripsi ?>"
                                                    title="Edit Alat Medis">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm btn-hapus"
                                                    data-id="<?= $id_alat ?>"
                                                    data-nama="<?= $nama_alat ?>"
                                                    title="Hapus Alat Medis">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                            $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1;
                                        }
                                    } else {
                                        echo '<tr><td colspan="11" class="text-center text-muted">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data alat medis yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data alat medis ditemukan.';
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

    <!-- Modal Tambah Alat Medis -->
    <div class="modal fade" id="tambahAlatModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Alat Medis Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-alat-medis.php">
                    <input type="hidden" name="tambah_alat" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Alat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_alat" id="kode_alat" value="<?= $next_kode_alat ?>" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted">Kode akan digenerate secara otomatis (ALT001, ALT002, dst)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Alat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_alat" required placeholder="Contoh: USG, X-Ray, dll">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Alat</label>
                                    <select class="form-select" name="jenis_alat">
                                        <option value="">Pilih Jenis Alat</option>
                                        <option value="Diagnostik">Diagnostik</option>
                                        <option value="Pemeriksaan">Pemeriksaan</option>
                                        <option value="Penunjang">Penunjang</option>
                                        <option value="Tindakan Medis">Tindakan Medis</option>
                                        <option value="Operasi">Operasi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasi</label>
                                    <input type="text" class="form-control" name="lokasi" placeholder="Contoh: Ruang Operasi, Lab, dll">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Kondisi</label>
                                    <select class="form-select" name="kondisi">
                                        <option value="Baik">Baik</option>
                                        <option value="Rusak Ringan">Rusak Ringan</option>
                                        <option value="Rusak Berat">Rusak Berat</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="Aktif">Aktif</option>
                                        <option value="Tidak Aktif">Tidak Aktif</option>
                                        <option value="Maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Beli</label>
                                    <input type="date" class="form-control" name="tanggal_beli">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3" placeholder="Deskripsi alat medis..."></textarea>
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

    <!-- Modal Edit Alat Medis -->
    <div class="modal fade" id="editAlatModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Alat Medis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-alat-medis.php">
                    <input type="hidden" name="edit_alat" value="1">
                    <input type="hidden" name="id_alat" id="edit_id_alat">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Alat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_alat" id="edit_kode_alat" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Alat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_alat" id="edit_nama_alat" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Alat</label>
                                    <select class="form-select" name="jenis_alat" id="edit_jenis_alat">
                                        <option value="">Pilih Jenis Alat</option>
                                        <option value="Diagnostik">Diagnostik</option>
                                        <option value="Pemeriksaan">Pemeriksaan</option>
                                        <option value="Penunjang">Penunjang</option>
                                        <option value="Tindakan Medis">Tindakan Medis</option>
                                        <option value="Operasi">Operasi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasi</label>
                                    <input type="text" class="form-control" name="lokasi" id="edit_lokasi">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Kondisi</label>
                                    <select class="form-select" name="kondisi" id="edit_kondisi">
                                        <option value="Baik">Baik</option>
                                        <option value="Rusak Ringan">Rusak Ringan</option>
                                        <option value="Rusak Berat">Rusak Berat</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_status">
                                        <option value="Aktif">Aktif</option>
                                        <option value="Tidak Aktif">Tidak Aktif</option>
                                        <option value="Maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Beli</label>
                                    <input type="date" class="form-control" name="tanggal_beli" id="edit_tanggal_beli">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
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

    <!-- Modal Hapus Alat Medis -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus alat medis:</p>
                    <h5 class="text-center text-danger" id="namaAlatHapus"></h5>
                    <p class="text-center text-muted mt-3"><small>Data yang dihapus tidak dapat dikembalikan.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Alat Medis -->
    <div class="modal fade" id="detailAlatModal" tabindex="-1" aria-labelledby="detailAlatModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="detailAlatModalLabel">
                        <i class="fas fa-microscope me-2"></i>Detail Alat Medis
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1" id="detailNamaAlat">-</h4>
                        <div class="text-muted mb-2"><i class="fas fa-barcode me-1"></i>Kode: <strong id="detailKodeAlat">-</strong></div>
                        <div class="text-muted"><i class="fas fa-calendar me-1"></i>Dibuat pada: <span id="detailCreatedAt">-</span></div>
                    </div>
                    
                    <hr>
                    
                    <!-- Informasi Utama -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Alat</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">ID Alat</small>
                                        <span class="fw-medium" id="detailIdAlat">-</span>
                                    </div>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Jenis Alat</small>
                                        <span class="fw-medium" id="detailJenisAlat">-</span>
                                    </div>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Lokasi</small>
                                        <span class="fw-medium" id="detailLokasi">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-chart-line me-2 text-primary"></i>Kondisi & Status</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Kondisi</small>
                                        <span id="detailKondisi">-</span>
                                    </div>
                                    <div class="info-row mt-3">
                                        <small class="text-muted d-block">Status</small>
                                        <span id="detailStatus">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Pembelian -->
                    <div class="card border-0 shadow-sm mb-4 detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-shopping-cart me-2 text-primary"></i>Informasi Pembelian</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Tanggal Pembelian</small>
                                        <span class="fw-medium" id="detailTanggalBeli">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deskripsi -->
                    <div class="card border-0 shadow-sm detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-file-alt me-2 text-primary"></i>Deskripsi</h6>
                            <div class="p-3 bg-light rounded" id="detailDeskripsi">-</div>
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

    <script>
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        window.location.href = 'dataalatmedis.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }

    // Fungsi untuk menampilkan detail alat medis
    function showDetailAlatModal(id_alat, kode_alat, nama_alat, jenis_alat, lokasi, kondisi, status, tanggal_beli, deskripsi, created_at, tanggal_beli_display) {
        // Set data dasar
        document.getElementById('detailIdAlat').textContent = id_alat || '-';
        document.getElementById('detailKodeAlat').textContent = kode_alat || '-';
        document.getElementById('detailNamaAlat').textContent = nama_alat || '-';
        document.getElementById('detailJenisAlat').textContent = jenis_alat || '-';
        document.getElementById('detailLokasi').textContent = lokasi || '-';
        document.getElementById('detailCreatedAt').textContent = created_at || '-';
        
        // Set kondisi dengan badge
        let kondisiBadge = '';
        switch (kondisi) {
            case 'Baik':
                kondisiBadge = '<span class="badge bg-success">Baik</span>';
                break;
            case 'Rusak Ringan':
                kondisiBadge = '<span class="badge bg-warning text-dark">Rusak Ringan</span>';
                break;
            case 'Rusak Berat':
                kondisiBadge = '<span class="badge bg-danger">Rusak Berat</span>';
                break;
            default:
                kondisiBadge = '<span class="badge bg-secondary">' + kondisi + '</span>';
        }
        document.getElementById('detailKondisi').innerHTML = kondisiBadge;
        
        // Set status dengan badge
        let statusBadge = '';
        switch (status) {
            case 'Aktif':
                statusBadge = '<span class="badge bg-info">Aktif</span>';
                break;
            case 'Tidak Aktif':
                statusBadge = '<span class="badge bg-secondary">Tidak Aktif</span>';
                break;
            case 'Maintenance':
                statusBadge = '<span class="badge bg-warning text-dark">Maintenance</span>';
                break;
            default:
                statusBadge = '<span class="badge bg-secondary">' + status + '</span>';
        }
        document.getElementById('detailStatus').innerHTML = statusBadge;
        
        // Set tanggal beli
        if (tanggal_beli && tanggal_beli !== '') {
            document.getElementById('detailTanggalBeli').textContent = formatTanggalIndo(tanggal_beli);
        } else {
            document.getElementById('detailTanggalBeli').textContent = '-';
        }
        
        // Set deskripsi
        document.getElementById('detailDeskripsi').textContent = deskripsi && deskripsi !== '' ? deskripsi : 'Tidak ada deskripsi';
        
        new bootstrap.Modal(document.getElementById('detailAlatModal')).show();
    }
    
    function formatTanggalIndo(dateString) {
        if (!dateString) return '-';
        let bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        let date = new Date(dateString);
        let tgl = date.getDate();
        let bln = bulan[date.getMonth()];
        let thn = date.getFullYear();
        return tgl + ' ' + bln + ' ' + thn;
    }

    // Edit modal handler
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id_alat').value = this.dataset.id;
            document.getElementById('edit_kode_alat').value = this.dataset.kode;
            document.getElementById('edit_nama_alat').value = this.dataset.nama;
            document.getElementById('edit_jenis_alat').value = this.dataset.jenis || '';
            document.getElementById('edit_lokasi').value = this.dataset.lokasi || '';
            document.getElementById('edit_kondisi').value = this.dataset.kondisi || 'Baik';
            document.getElementById('edit_status').value = this.dataset.status || 'Aktif';
            document.getElementById('edit_tanggal_beli').value = this.dataset.tanggal || '';
            document.getElementById('edit_deskripsi').value = this.dataset.deskripsi || '';
            
            new bootstrap.Modal(document.getElementById('editAlatModal')).show();
        });
    });

    // Hapus modal handler
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nama = this.dataset.nama;
            document.getElementById('namaAlatHapus').textContent = nama + ' (ID: ' + id + ')';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-alat-medis.php?hapus=' + id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });

    // Reset form ketika modal ditutup
    document.getElementById('tambahAlatModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tambahAlatModal').querySelector('form').reset();
        document.getElementById('kode_alat').value = '<?= getNextKodeAlat($db) ?>';
    });

    // Auto show modal jika data kosong
    <?php if ($is_data_empty): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('tambahAlatModal')).show();
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
    return 'dataalatmedis.php?' . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $params = [];
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'dataalatmedis.php?' . implode('&', $params);
}
?>