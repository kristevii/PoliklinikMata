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

// Ambil semua data obat
$all_obat = $db->tampil_data_obat();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_obat = [];
    foreach ($all_obat as $obat) {
        if (stripos($obat['id_obat'] ?? '', $search_query) !== false ||
            stripos($obat['kode_obat'] ?? '', $search_query) !== false ||
            stripos($obat['nama_obat'] ?? '', $search_query) !== false ||
            stripos($obat['jenis_obat'] ?? '', $search_query) !== false ||
            stripos($obat['satuan'] ?? '', $search_query) !== false) {
            $filtered_obat[] = $obat;
        }
    }
    $all_obat = $filtered_obat;
}

// Urutkan data berdasarkan ID
usort($all_obat, function($a, $b) use ($sort_order) {
    $val_a = $a['id_obat'] ?? 0;
    $val_b = $b['id_obat'] ?? 0;
    
    if ($sort_order === 'desc') {
        return $val_b - $val_a;
    } else {
        return $val_a - $val_b;
    }
});

// Hitung total data
$total_entries = count($all_obat);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_obat = array_slice($all_obat, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_obat);

// Generate kode obat berikutnya
function getNextKodeObat($db) {
    $query = "SELECT kode_obat FROM data_obat ORDER BY id_obat DESC LIMIT 1";
    $result = $db->koneksi->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_kode = $row['kode_obat'];
        
        if (preg_match('/(\d+)$/', $last_kode, $matches)) {
            $last_number = (int)$matches[1];
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
    } else {
        $new_number = 1;
    }
    
    return 'OBT' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

$next_kode_obat = getNextKodeObat($db);

// Fungsi untuk escape JavaScript string
function escapeJsString($str) {
    return str_replace(
        ["\\", "'", '"', "\n", "\r", "\t", "\x08", "\x0c"],
        ["\\\\", "\\'", '\\"', "\\n", "\\r", "\\t", "\\b", "\\f"],
        $str
    );
}

// Fungsi format tanggal
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
    <title>Obat - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    .badge-stok-rendah { background-color: #ffc107; color: #000; }
    .badge-stok-habis { background-color: #dc3545; color: #fff; }
    .badge-stok-cukup { background-color: #28a745; color: #fff; }
    .badge-expired { background-color: #dc3545; color: #fff; }
    .badge-expiring { background-color: #ffc107; color: #000; }
    .badge-aman { background-color: #28a745; color: #fff; }
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
        background: linear-gradient(135deg, #28a745 0%, #17a2b8 100%);
        color: white;
    }
    .stok-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                                <li class="breadcrumb-item" aria-current="page">Data Obat</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Obat</h2>
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
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahObatModal">
                        <i class="fas fa-plus me-1"></i> Tambah Obat
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari data obat..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="dataobat.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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
                                        <th>Kode Obat</th>
                                        <th>Nama Obat</th>
                                        <th>Jenis Obat</th>
                                        <th>Satuan</th>
                                        <th>Stok</th>
                                        <th>Harga</th>
                                        <th>Expired</th>
                                        <th>Create At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_obat) && is_array($data_obat)) {
                                        $counter = $start_number;
                                        foreach ($data_obat as $obat) {
                                            $id_obat = htmlspecialchars($obat['id_obat'] ?? '');
                                            $kode_obat = htmlspecialchars($obat['kode_obat'] ?? '');
                                            $nama_obat = htmlspecialchars($obat['nama_obat'] ?? '');
                                            $jenis_obat = htmlspecialchars($obat['jenis_obat'] ?? '');
                                            $satuan = htmlspecialchars($obat['satuan'] ?? '');
                                            $stok = (int)($obat['stok'] ?? 0);
                                            $harga = number_format($obat['harga'] ?? 0, 0, ',', '.');
                                            $harga_asli = $obat['harga'] ?? 0;
                                            $expired_date = !empty($obat['expired_date']) ? $obat['expired_date'] : '';
                                            $expired_date_display = !empty($expired_date) ? date('d/m/Y', strtotime($expired_date)) : '-';
                                            $deskripsi = htmlspecialchars($obat['deskripsi'] ?? '');
                                            $created_at = !empty($obat['created_at']) ? date('d/m/Y H:i:s', strtotime($obat['created_at'])) : '-';
                                            
                                            // Badge stok
                                            $stok_class = '';
                                            $stok_text = '';
                                            if ($stok <= 0) {
                                                $stok_class = 'badge-stok-habis';
                                                $stok_text = 'Habis';
                                            } elseif ($stok <= 10) {
                                                $stok_class = 'badge-stok-rendah';
                                                $stok_text = 'Rendah (' . $stok . ')';
                                            } else {
                                                $stok_class = 'badge-stok-cukup';
                                                $stok_text = $stok;
                                            }
                                            
                                            // Badge expired
                                            $expired_class = '';
                                            $expired_status = '';
                                            $today = new DateTime();
                                            if (!empty($expired_date)) {
                                                $expired = new DateTime($expired_date);
                                                $diff = $today->diff($expired);
                                                
                                                if ($expired < $today) {
                                                    $expired_class = 'badge-expired';
                                                    $expired_status = 'Kadaluarsa';
                                                } elseif ($diff->days <= 30) {
                                                    $expired_class = 'badge-expiring';
                                                    $expired_status = 'Akan Kadaluarsa (' . $diff->days . ' hari)';
                                                } else {
                                                    $expired_class = 'badge-aman';
                                                    $expired_status = 'Aman';
                                                }
                                            }
                                            
                                            // Escape untuk JavaScript
                                            $js_id_obat = escapeJsString($id_obat);
                                            $js_kode_obat = escapeJsString($kode_obat);
                                            $js_nama_obat = escapeJsString($nama_obat);
                                            $js_jenis_obat = escapeJsString($jenis_obat);
                                            $js_satuan = escapeJsString($satuan);
                                            $js_stok = $stok;
                                            $js_harga = $harga_asli;
                                            $js_expired_date = escapeJsString($expired_date);
                                            $js_deskripsi = escapeJsString($deskripsi);
                                            $js_created_at = escapeJsString($created_at);
                                            $js_expired_display = escapeJsString($expired_date_display);
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= $id_obat ?></td>
                                        <td><?= $kode_obat ?></td>
                                        <td><?= $nama_obat ?></td>
                                        <td><?= $jenis_obat ?></td>
                                        <td><?= $satuan ?></td>
                                        <td><span class="badge <?= $stok_class ?>"><?= $stok_text ?></span></td>
                                        <td>Rp <?= $harga ?></td>
                                        <td><span class="badge <?= $expired_class ?>"><?= $expired_date_display ?></span></td>
                                        <td><?= $created_at ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm btn-view"
                                                    onclick='showDetailObatModal("<?= $js_id_obat ?>", "<?= $js_kode_obat ?>", "<?= $js_nama_obat ?>", "<?= $js_jenis_obat ?>", "<?= $js_satuan ?>", <?= $js_stok ?>, <?= $js_harga ?>, "<?= $js_expired_date ?>", "<?= $js_deskripsi ?>", "<?= $js_created_at ?>", "<?= $js_expired_display ?>")'
                                                    title="Lihat Detail Obat">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                    data-id="<?= $id_obat ?>"
                                                    data-kode="<?= $kode_obat ?>"
                                                    data-nama="<?= $nama_obat ?>"
                                                    data-jenis="<?= $jenis_obat ?>"
                                                    data-satuan="<?= $satuan ?>"
                                                    data-stok="<?= $stok ?>"
                                                    data-harga="<?= $harga_asli ?>"
                                                    data-expired="<?= $expired_date ?>"
                                                    data-deskripsi="<?= $deskripsi ?>"
                                                    title="Edit Obat">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm btn-hapus"
                                                    data-id="<?= $id_obat ?>"
                                                    data-nama="<?= $nama_obat ?>"
                                                    title="Hapus Obat">
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
                                            echo 'Tidak ada data obat yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data obat ditemukan.';
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

    <!-- Modal Tambah Obat -->
    <div class="modal fade" id="tambahObatModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Obat Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-obat.php">
                    <input type="hidden" name="tambah_obat" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Obat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_obat" id="kode_obat" value="<?= $next_kode_obat ?>" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted">Kode akan digenerate secara otomatis (OBT001, OBT002, dst)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Obat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_obat" required placeholder="Contoh: Paracetamol, Amoxicillin, dll">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Obat</label>
                                    <select class="form-select" name="jenis_obat">
                                        <option value="">Pilih Jenis Obat</option>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Sirup">Sirup</option>
                                        <option value="Salep">Salep</option>
                                        <option value="Tetes Mata">Tetes Mata</option>
                                        <option value="Injeksi">Injeksi</option>
                                        <option value="Krim">Krim</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Satuan</label>
                                    <select class="form-select" name="satuan">
                                        <option value="">Pilih Satuan</option>
                                        <option value="Botol">Botol</option>
                                        <option value="Strip">Strip</option>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Tube">Tube</option>
                                        <option value="Vial">Vial</option>
                                        <option value="Ampul">Ampul</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stok <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="stok" required min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Harga <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="harga" required min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Expired Date</label>
                                    <input type="date" class="form-control" name="expired_date">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3" placeholder="Deskripsi obat..."></textarea>
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

    <!-- Modal Edit Obat -->
    <div class="modal fade" id="editObatModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Obat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-obat.php">
                    <input type="hidden" name="edit_obat" value="1">
                    <input type="hidden" name="id_obat" id="edit_id_obat">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Obat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_obat" id="edit_kode_obat" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Obat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_obat" id="edit_nama_obat" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Obat</label>
                                    <select class="form-select" name="jenis_obat" id="edit_jenis_obat">
                                        <option value="">Pilih Jenis Obat</option>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Sirup">Sirup</option>
                                        <option value="Salep">Salep</option>
                                        <option value="Tetes Mata">Tetes Mata</option>
                                        <option value="Injeksi">Injeksi</option>
                                        <option value="Krim">Krim</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Satuan</label>
                                    <select class="form-select" name="satuan" id="edit_satuan">
                                        <option value="">Pilih Satuan</option>
                                        <option value="Botol">Botol</option>
                                        <option value="Strip">Strip</option>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Tube">Tube</option>
                                        <option value="Vial">Vial</option>
                                        <option value="Ampul">Ampul</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stok <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="stok" id="edit_stok" required min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Harga <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="harga" id="edit_harga" required min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Expired Date</label>
                                    <input type="date" class="form-control" name="expired_date" id="edit_expired_date">
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

    <!-- Modal Hapus Obat -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus obat:</p>
                    <h5 class="text-center text-danger" id="namaObatHapus"></h5>
                    <p class="text-center text-muted mt-3"><small>Data yang dihapus tidak dapat dikembalikan.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Obat -->
    <div class="modal fade" id="detailObatModal" tabindex="-1" aria-labelledby="detailObatModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="detailObatModalLabel">
                        <i class="fas fa-capsules me-2"></i>Detail Obat
                    </h5>
                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1" id="detailNamaObat">-</h4>
                        <div class="text-muted mb-2"><i class="fas fa-barcode me-1"></i>Kode: <strong id="detailKodeObat">-</strong></div>
                        <div class="text-muted"><i class="fas fa-calendar me-1"></i>Dibuat pada: <span id="detailCreatedAt">-</span></div>
                    </div>
                    
                    <hr>
                    
                    <!-- Informasi Utama -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Obat</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">ID Obat</small>
                                        <span class="fw-medium" id="detailIdObat">-</span>
                                    </div>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Jenis Obat</small>
                                        <span class="fw-medium" id="detailJenisObat">-</span>
                                    </div>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Satuan</small>
                                        <span class="fw-medium" id="detailSatuan">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm detail-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-chart-line me-2 text-primary"></i>Stok & Harga</h6>
                                    <div class="info-row">
                                        <small class="text-muted d-block">Stok Tersedia</small>
                                        <span class="fw-medium fs-4" id="detailStok">-</span>
                                    </div>
                                    <div class="info-row mt-3">
                                        <small class="text-muted d-block">Harga</small>
                                        <span class="fw-medium fs-5 text-success" id="detailHarga">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Expired -->
                    <div class="card border-0 shadow-sm mb-4 detail-card">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-hourglass-half me-2 text-primary"></i>Status Kadaluarsa</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Tanggal Expired</small>
                                        <span class="fw-medium" id="detailExpiredDate">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge" id="detailExpiredStatus">-</span>
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
        window.location.href = 'dataobat.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }

    // Fungsi untuk menampilkan detail obat
    function showDetailObatModal(id_obat, kode_obat, nama_obat, jenis_obat, satuan, stok, harga, expired_date, deskripsi, created_at, expired_display) {
        // Set data dasar
        document.getElementById('detailIdObat').textContent = id_obat || '-';
        document.getElementById('detailKodeObat').textContent = kode_obat || '-';
        document.getElementById('detailNamaObat').textContent = nama_obat || '-';
        document.getElementById('detailJenisObat').textContent = jenis_obat || '-';
        document.getElementById('detailSatuan').textContent = satuan || '-';
        document.getElementById('detailCreatedAt').textContent = created_at || '-';
        
        // Set stok dengan badge
        let stokText = '';
        let stokClass = '';
        if (stok <= 0) {
            stokText = 'Habis (0)';
            stokClass = 'text-danger';
        } else if (stok <= 10) {
            stokText = stok + ' (Stok Rendah)';
            stokClass = 'text-warning';
        } else {
            stokText = stok;
            stokClass = 'text-success';
        }
        document.getElementById('detailStok').innerHTML = '<span class="' + stokClass + ' fw-bold">' + stokText + '</span>';
        
        // Set harga
        let hargaFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(harga);
        document.getElementById('detailHarga').textContent = hargaFormatted;
        
        // Set expired
        if (expired_date && expired_date !== '') {
            let expiredDateFormatted = formatTanggalIndo(expired_date);
            document.getElementById('detailExpiredDate').textContent = expiredDateFormatted;
            
            // Hitung status expired
            let today = new Date();
            let expired = new Date(expired_date);
            today.setHours(0, 0, 0, 0);
            expired.setHours(0, 0, 0, 0);
            
            let diffTime = expired - today;
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            let statusBadge = '';
            if (diffDays < 0) {
                statusBadge = '<span class="badge bg-danger">Kadaluarsa</span>';
            } else if (diffDays <= 30) {
                statusBadge = '<span class="badge bg-warning text-dark">Akan Kadaluarsa (' + diffDays + ' hari)</span>';
            } else {
                statusBadge = '<span class="badge bg-success">Aman</span>';
            }
            document.getElementById('detailExpiredStatus').innerHTML = statusBadge;
        } else {
            document.getElementById('detailExpiredDate').textContent = '-';
            document.getElementById('detailExpiredStatus').innerHTML = '<span class="badge bg-secondary">Tidak ada data</span>';
        }
        
        // Set deskripsi
        document.getElementById('detailDeskripsi').textContent = deskripsi && deskripsi !== '' ? deskripsi : 'Tidak ada deskripsi';
        
        new bootstrap.Modal(document.getElementById('detailObatModal')).show();
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
            document.getElementById('edit_id_obat').value = this.dataset.id;
            document.getElementById('edit_kode_obat').value = this.dataset.kode;
            document.getElementById('edit_nama_obat').value = this.dataset.nama;
            document.getElementById('edit_jenis_obat').value = this.dataset.jenis || '';
            document.getElementById('edit_satuan').value = this.dataset.satuan || '';
            document.getElementById('edit_stok').value = this.dataset.stok || 0;
            document.getElementById('edit_harga').value = this.dataset.harga || 0;
            document.getElementById('edit_expired_date').value = this.dataset.expired || '';
            document.getElementById('edit_deskripsi').value = this.dataset.deskripsi || '';
            
            new bootstrap.Modal(document.getElementById('editObatModal')).show();
        });
    });

    // Hapus modal handler
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nama = this.dataset.nama;
            document.getElementById('namaObatHapus').textContent = nama + ' (ID: ' + id + ')';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-obat.php?hapus=' + id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });

    // Reset form ketika modal ditutup
    document.getElementById('tambahObatModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tambahObatModal').querySelector('form').reset();
        document.getElementById('kode_obat').value = '<?= getNextKodeObat($db) ?>';
    });

    // Auto show modal jika data kosong
    <?php if ($is_data_empty): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('tambahObatModal')).show();
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
    return 'dataobat.php?' . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $params = [];
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'dataobat.php?' . implode('&', $params);
}
?>