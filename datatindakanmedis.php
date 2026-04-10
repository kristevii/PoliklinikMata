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

// Ambil semua data tindakan medis
$all_tindakan = $db->tampil_data_tindakan_medis();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_tindakan = [];
    foreach ($all_tindakan as $tindakan) {
        if (stripos($tindakan['id_tindakan_medis'] ?? '', $search_query) !== false ||
            stripos($tindakan['kode_tindakan'] ?? '', $search_query) !== false ||
            stripos($tindakan['nama_tindakan'] ?? '', $search_query) !== false ||
            stripos($tindakan['kategori'] ?? '', $search_query) !== false ||
            stripos($tindakan['tarif'] ?? '', $search_query) !== false) {
            $filtered_tindakan[] = $tindakan;
        }
    }
    $all_tindakan = $filtered_tindakan;
}

// Urutkan data berdasarkan ID
usort($all_tindakan, function($a, $b) use ($sort_order) {
    $val_a = $a['id_tindakan_medis'] ?? 0;
    $val_b = $b['id_tindakan_medis'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_tindakan);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_tindakan = array_slice($all_tindakan, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_tindakan);

// Generate kode tindakan berikutnya
function getNextKodeTindakan($db) {
    $query = "SELECT kode_tindakan FROM data_tindakan_medis ORDER BY id_tindakan_medis DESC LIMIT 1";
    $result = $db->koneksi->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_kode = $row['kode_tindakan'];
        
        if (preg_match('/(\d+)$/', $last_kode, $matches)) {
            $last_number = (int)$matches[1];
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
    } else {
        $new_number = 1;
    }
    
    return 'TIN' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

$next_kode_tindakan = getNextKodeTindakan($db);

// Fungsi format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

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
    <title>Tindakan Medis - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    .badge-kategori { 
        background-color: #17a2b8; 
        color: #fff; 
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 11px;
    }
    .badge-kategori-diagnostik { background-color: #6610f2; color: #fff; }
    .badge-kategori-pemeriksaan { background-color: #28a745; color: #fff; }
    .badge-kategori-tindakan { background-color: #fd7e14; color: #fff; }
    .badge-kategori-minor { background-color: #20c997; color: #fff; }
    .badge-kategori-penunjang { background-color: #6f42c1; color: #fff; }
    .badge-kategori-terapi { background-color: #e83e8c; color: #fff; }
    .badge-kategori-operasi { background-color: #dc3545; color: #fff; }
    
    .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .btn-hapus:hover, .btn-edit:hover, .btn-view:hover { transform: scale(1.05); transition: all 0.3s ease; }
    .table th { border-top: none; font-weight: 600; }
    .tarif-column {
        font-weight: 600;
        color: #2c3e50;
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
    .detail-header {
        background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%);
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
                                <li class="breadcrumb-item" aria-current="page">Data Tindakan Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Tindakan Medis</h2>
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
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahTindakanModal">
                        <i class="fas fa-plus me-1"></i> Tambah Tindakan Medis
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari data tindakan medis..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="datatindakanmedis.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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
                                        <th><a href="<?= getSortUrl($sort_order, $entries_per_page, $search_query) ?>" class="text-decoration-none text-dark">No <?= $sort_order === 'asc' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>' ?></a></th>
                                        <th>ID</th>
                                        <th>Kode Tindakan</th>
                                        <th>Nama Tindakan</th>
                                        <th>Kategori</th>
                                        <th>Tarif</th>
                                        <th>Created At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_tindakan) && is_array($data_tindakan)) {
                                        $counter = $start_number;
                                        foreach ($data_tindakan as $tindakan) {
                                            $id_tindakan = htmlspecialchars($tindakan['id_tindakan_medis'] ?? '');
                                            $kode_tindakan = htmlspecialchars($tindakan['kode_tindakan'] ?? '');
                                            $nama_tindakan = htmlspecialchars($tindakan['nama_tindakan'] ?? '');
                                            $kategori = htmlspecialchars($tindakan['kategori'] ?? '');
                                            $tarif = isset($tindakan['tarif']) ? formatRupiah($tindakan['tarif']) : 'Rp 0';
                                            $tarif_asli = $tindakan['tarif'] ?? 0;
                                            $deskripsi = htmlspecialchars($tindakan['deskripsi'] ?? '');
                                            $created_at = !empty($tindakan['created_at']) ? date('d/m/Y H:i:s', strtotime($tindakan['created_at'])) : '-';
                                            
                                            // Badge kategori dengan warna berbeda
                                            $kategori_class = 'badge-kategori';
                                            switch ($kategori) {
                                                case 'Diagnostik': $kategori_class .= ' badge-kategori-diagnostik'; break;
                                                case 'Pemeriksaan': $kategori_class .= ' badge-kategori-pemeriksaan'; break;
                                                case 'Tindakan Medis': $kategori_class .= ' badge-kategori-tindakan'; break;
                                                case 'Tindakan Minor': $kategori_class .= ' badge-kategori-minor'; break;
                                                case 'Penunjang Medis': $kategori_class .= ' badge-kategori-penunjang'; break;
                                                case 'Terapi': $kategori_class .= ' badge-kategori-terapi'; break;
                                                case 'Operasi': $kategori_class .= ' badge-kategori-operasi'; break;
                                                default: $kategori_class = 'badge-kategori';
                                            }
                                            
                                            // Escape untuk JavaScript
                                            $js_id_tindakan = escapeJsString($id_tindakan);
                                            $js_kode_tindakan = escapeJsString($kode_tindakan);
                                            $js_nama_tindakan = escapeJsString($nama_tindakan);
                                            $js_kategori = escapeJsString($kategori);
                                            $js_tarif = $tarif_asli;
                                            $js_deskripsi = escapeJsString($deskripsi);
                                            $js_created_at = escapeJsString($created_at);
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= $id_tindakan ?></td>
                                        <td><span class="badge bg-secondary"><?= $kode_tindakan ?></span></td>
                                        <td><?= $nama_tindakan ?></td>
                                        <td><span class="badge <?= $kategori_class ?>"><?= $kategori ?></span></td>
                                        <td class="tarif-column"><?= $tarif ?></td>
                                        <td><?= $created_at ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm btn-view"
                                                    onclick='showDetailTindakanModal("<?= $js_id_tindakan ?>", "<?= $js_kode_tindakan ?>", "<?= $js_nama_tindakan ?>", "<?= $js_kategori ?>", <?= $js_tarif ?>, "<?= $js_deskripsi ?>", "<?= $js_created_at ?>")'
                                                    title="Lihat Detail Tindakan Medis">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                    data-id="<?= $id_tindakan ?>"
                                                    data-kode="<?= $kode_tindakan ?>"
                                                    data-nama="<?= $nama_tindakan ?>"
                                                    data-kategori="<?= $kategori ?>"
                                                    data-tarif="<?= $tarif_asli ?>"
                                                    data-deskripsi="<?= htmlspecialchars($deskripsi) ?>"
                                                    title="Edit Tindakan Medis">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm btn-hapus"
                                                    data-id="<?= $id_tindakan ?>"
                                                    data-nama="<?= $nama_tindakan ?>"
                                                    title="Hapus Tindakan Medis">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                            $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1;
                                        }
                                    } else {
                                        echo '<tr><td colspan="8" class="text-center text-muted">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data tindakan medis yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data tindakan medis ditemukan.';
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

    <!-- Modal Tambah Tindakan Medis -->
    <div class="modal fade" id="tambahTindakanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Tindakan Medis Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-tindakan-medis.php">
                    <input type="hidden" name="tambah_tindakan" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Tindakan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_tindakan" id="kode_tindakan" value="<?= $next_kode_tindakan ?>" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted">Kode akan digenerate secara otomatis (TIN001, TIN002, dst)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Tindakan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_tindakan" required placeholder="Contoh: USG 4D, Cek Mata, dll">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                    <select class="form-select" name="kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="Pemeriksaan">Pemeriksaan</option>
                                        <option value="Diagnostik">Diagnostik</option>
                                        <option value="Tindakan Medis">Tindakan Medis</option>
                                        <option value="Tindakan Minor">Tindakan Minor</option>
                                        <option value="Penunjang Medis">Penunjang Medis</option>
                                        <option value="Terapi">Terapi</option>
                                        <option value="Operasi">Operasi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tarif <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" name="tarif" required min="0" step="1000" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3" placeholder="Deskripsi tindakan medis..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnTambahTindakan"><i class="fas fa-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Tindakan Medis -->
    <div class="modal fade" id="editTindakanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Tindakan Medis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-tindakan-medis.php">
                    <input type="hidden" name="edit_tindakan" value="1">
                    <input type="hidden" name="id_tindakan" id="edit_id_tindakan">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Tindakan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_tindakan" id="edit_kode_tindakan" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Tindakan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_tindakan" id="edit_nama_tindakan" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                    <select class="form-select" name="kategori" id="edit_kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="Pemeriksaan">Pemeriksaan</option>
                                        <option value="Diagnostik">Diagnostik</option>
                                        <option value="Tindakan Medis">Tindakan Medis</option>
                                        <option value="Tindakan Minor">Tindakan Minor</option>
                                        <option value="Penunjang Medis">Penunjang Medis</option>
                                        <option value="Terapi">Terapi</option>
                                        <option value="Operasi">Operasi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tarif <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" name="tarif" id="edit_tarif" required min="0" step="1000">
                                    </div>
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
                        <button type="submit" class="btn btn-primary" id="btnUpdateTindakan"><i class="fas fa-save me-1"></i>Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Tindakan Medis -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus tindakan medis:</p>
                    <h5 class="text-center text-danger" id="namaTindakanHapus"></h5>
                    <p class="text-center text-muted mt-3"><small>Data yang dihapus tidak dapat dikembalikan.</small></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>PERINGATAN:</strong> Data tindakan medis akan dihapus permanen!
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Tindakan Medis -->
    <div class="modal fade" id="detailTindakanModal" tabindex="-1" aria-labelledby="detailTindakanModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="detailTindakanModalLabel">
                        <i class="fas fa-stethoscope me-2"></i>Detail Tindakan Medis
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1" id="detailNamaTindakan">-</h4>
                        <div class="text-muted mb-2"><i class="fas fa-barcode me-1"></i>Kode: <strong id="detailKodeTindakan">-</strong></div>
                        <div class="text-muted"><i class="fas fa-calendar me-1"></i>Dibuat pada: <span id="detailCreatedAt">-</span></div>
                    </div>
                    
                    <hr>
                    
                    <!-- Informasi Utama -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Tindakan</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">ID Tindakan</small>
                                        <span class="fw-medium" id="detailIdTindakan">-</span>
                                    </div>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Kategori</small>
                                        <span id="detailKategori">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-tag me-2 text-primary"></i>Tarif</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Biaya Tindakan</small>
                                        <span class="fw-medium fs-4 text-success" id="detailTarif">-</span>
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
        window.location.href = 'datatindakanmedis.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }

    // Fungsi untuk menampilkan detail tindakan medis
    function showDetailTindakanModal(id_tindakan, kode_tindakan, nama_tindakan, kategori, tarif, deskripsi, created_at) {
        // Set data dasar
        document.getElementById('detailIdTindakan').textContent = id_tindakan || '-';
        document.getElementById('detailKodeTindakan').textContent = kode_tindakan || '-';
        document.getElementById('detailNamaTindakan').textContent = nama_tindakan || '-';
        document.getElementById('detailCreatedAt').textContent = created_at || '-';
        
        // Set kategori dengan badge
        let kategoriClass = '';
        let kategoriText = kategori || '-';
        switch (kategori) {
            case 'Diagnostik': kategoriClass = 'badge bg-info'; break;
            case 'Pemeriksaan': kategoriClass = 'badge bg-success'; break;
            case 'Tindakan Medis': kategoriClass = 'badge bg-warning text-dark'; break;
            case 'Tindakan Minor': kategoriClass = 'badge bg-info'; break;
            case 'Penunjang Medis': kategoriClass = 'badge bg-secondary'; break;
            case 'Terapi': kategoriClass = 'badge bg-danger'; break;
            case 'Operasi': kategoriClass = 'badge bg-dark'; break;
            default: kategoriClass = 'badge bg-secondary';
        }
        document.getElementById('detailKategori').innerHTML = '<span class="' + kategoriClass + '">' + kategoriText + '</span>';
        
        // Set tarif
        let tarifFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(tarif);
        document.getElementById('detailTarif').textContent = tarifFormatted;
        
        // Set deskripsi
        document.getElementById('detailDeskripsi').textContent = deskripsi && deskripsi !== '' ? deskripsi : 'Tidak ada deskripsi';
        
        new bootstrap.Modal(document.getElementById('detailTindakanModal')).show();
    }

    // Edit modal handler
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id_tindakan').value = this.dataset.id;
            document.getElementById('edit_kode_tindakan').value = this.dataset.kode;
            document.getElementById('edit_nama_tindakan').value = this.dataset.nama;
            document.getElementById('edit_kategori').value = this.dataset.kategori || '';
            document.getElementById('edit_tarif').value = this.dataset.tarif || 0;
            document.getElementById('edit_deskripsi').value = this.dataset.deskripsi || '';
            
            new bootstrap.Modal(document.getElementById('editTindakanModal')).show();
        });
    });

    // Hapus modal handler
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nama = this.dataset.nama;
            document.getElementById('namaTindakanHapus').textContent = nama + ' (ID: ' + id + ')';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-tindakan-medis.php?hapus=' + id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });

    // Reset form ketika modal ditutup
    document.getElementById('tambahTindakanModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tambahTindakanModal').querySelector('form').reset();
        document.getElementById('kode_tindakan').value = '<?= getNextKodeTindakan($db) ?>';
    });

    <?php if ($is_data_empty): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('tambahTindakanModal')).show();
        }, 500);
    });
    <?php endif; ?>
    </script>
    
    <?php require_once "footer.php"; ?>
</body>
</html>

<?php
// Fungsi URL untuk pagination dan sorting
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    return 'datatindakanmedis.php?' . implode('&', $params);
}

function getSortUrl($current_sort, $entries, $search) {
    $params = [];
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'datatindakanmedis.php?' . implode('&', $params);
}
?>