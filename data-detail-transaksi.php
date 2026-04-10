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

// Cek hak akses sesuai header.php: Kasir & Billing dan IT Support yang bisa akses data detail transaksi
if ($jabatan_user != 'Kasir & Billing' && $jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data detail transaksi. Hanya Staff dengan jabatan Kasir & Billing dan IT Support yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Konfigurasi pagination
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data dari tabel data_detail_transaksi
$query = "SELECT * FROM data_detail_transaksi ORDER BY id_detail_transaksi DESC";
$result = $db->koneksi->query($query);

if (!$result) {
    die("Error query: " . $db->koneksi->error);
}

$all_detail_transaksi = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_detail_transaksi[] = $row;
    }
}

// Filter berdasarkan search
if (!empty($search_query)) {
    $filtered_data = [];
    foreach ($all_detail_transaksi as $detail) {
        if (stripos($detail['id_detail_transaksi'] ?? '', $search_query) !== false ||
            stripos($detail['id_transaksi'] ?? '', $search_query) !== false ||
            stripos($detail['id_detail_tindakanmedis'] ?? '', $search_query) !== false ||
            stripos($detail['id_detail_resep'] ?? '', $search_query) !== false ||
            stripos($detail['jenis_item'] ?? '', $search_query) !== false ||
            stripos($detail['nama_item'] ?? '', $search_query) !== false) {
            $filtered_data[] = $detail;
        }
    }
    $all_detail_transaksi = $filtered_data;
}

// Urutkan data berdasarkan ID Detail Transaksi
if ($sort_order === 'desc') {
    usort($all_detail_transaksi, function($a, $b) {
        return ($b['id_detail_transaksi'] ?? 0) - ($a['id_detail_transaksi'] ?? 0);
    });
} else {
    usort($all_detail_transaksi, function($a, $b) {
        return ($a['id_detail_transaksi'] ?? 0) - ($b['id_detail_transaksi'] ?? 0);
    });
}

// Pagination
$total_entries = count($all_detail_transaksi);
$total_pages = ceil($total_entries / $entries_per_page);
$offset = ($current_page - 1) * $entries_per_page;
$data_detail_transaksi = array_slice($all_detail_transaksi, $offset, $entries_per_page);

// Hitung nomor urut yang benar berdasarkan sorting
if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

// Notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'data-detail-transaksi.php?';
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
    
    return $url . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $url = 'data-detail-transaksi.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if ($page > 1) {
        $params[] = 'page=' . $page;
    }
    
    // Toggle sort order
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Detail Transaksi - Sistem Informasi Poliklinik Mata Eyethica</title>
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
        .badge-tindakan { background-color: #17a2b8; color: #fff; padding: 5px 10px; border-radius: 20px; font-size: 11px; }
        .badge-obat { background-color: #28a745; color: #fff; padding: 5px 10px; border-radius: 20px; font-size: 11px; }
        .subtotal-column { font-weight: 600; color: #2c3e50; }
        .info-box { background-color: #e7f3ff; border-left: 4px solid #2196f3; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .table th { border-top: none; font-weight: 600; }
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
                                <li class="breadcrumb-item active">Data Detail Transaksi</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Detail Transaksi</h2>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

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
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Cari ID Transaksi, ID Tindakan, ID Resep, atau Nama Item..." 
                                               value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="data-detail-transaksi.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger">
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
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                        <th>ID Transaksi</th>
                                        <th>Tindakan Medis</th>
                                        <th>Resep</th>
                                        <th>Jenis Item</th>
                                        <th>Nama Item</th>
                                        <th>Qty</th>
                                        <th>Harga</th>
                                        <th>Subtotal</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_detail_transaksi)): ?>
                                        <?php $no = $start_number; foreach ($data_detail_transaksi as $detail): ?>
                                        <?php
                                        $jenis_item = $detail['jenis_item'] ?? '';
                                        ?>
                                        <tr>
                                            <td><?= $no ?></td>
                                            <td><?= htmlspecialchars($detail['id_detail_transaksi'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($detail['id_transaksi'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($detail['id_detail_tindakanmedis'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($detail['id_detail_resep'] ?? '-') ?></td>
                                            <td>
                                                <span class="<?= $jenis_item == 'Tindakan' ? 'badge-tindakan' : 'badge-obat' ?>">
                                                    <?= htmlspecialchars($jenis_item) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($detail['nama_item'] ?? '-') ?></td>
                                            <td><?= number_format($detail['qty'] ?? 0, 0, ',', '.') ?></td>
                                            <td><?= formatRupiah($detail['harga'] ?? 0) ?></td>
                                            <td class="subtotal-column"><?= formatRupiah($detail['subtotal'] ?? 0) ?></td>
                                            <td><?= !empty($detail['created_at']) ? date('d/m/Y H:i:s', strtotime($detail['created_at'])) : '-' ?></td>
                                        </tr>
                                        <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $no--;
                                        } else {
                                            $no++;
                                        }
                                        ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="11" class="text-center text-muted">
                                            <?php if (!empty($search_query)): ?>
                                                Tidak ada data detail transaksi yang sesuai dengan pencarian "<?= htmlspecialchars($search_query) ?>"
                                            <?php else: ?>
                                                Tidak ada data detail transaksi ditemukan.
                                            <?php endif; ?>
                                        </td></tr>
                                    <?php endif; ?>
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
                                            <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                                Sebelumnya
                                            </a>
                                        </li>
                                        <?php
                                        // Selalu tampilkan halaman 1
                                        echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order) . '">1</a>';
                                        echo '</li>';
                                        
                                        // Tentukan range halaman yang akan ditampilkan
                                        $start = 2;
                                        $end = min(5, $total_pages - 1);
                                        
                                        if ($current_page > 3) {
                                            $start = $current_page - 1;
                                            $end = min($current_page + 2, $total_pages - 1);
                                        }
                                        
                                        if ($start > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        for ($i = $start; $i <= $end; $i++) {
                                            if ($i < $total_pages) {
                                                echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                                echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order) . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                        }
                                        
                                        if ($end < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        if ($total_pages > 1) {
                                            echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order) . '">' . $total_pages . '</a>';
                                            echo '</li>';
                                        }
                                        ?>
                                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                                Selanjutnya
                                            </a>
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
    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= addslashes($search_query) ?>';
        const sort = '<?= $sort_order ?>';
        let url = 'data-detail-transaksi.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }

    // Auto focus pada input search
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && '<?= $search_query ?>') {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }
    });
    </script>

    <?php require_once "footer.php"; ?>
</body>
</html>