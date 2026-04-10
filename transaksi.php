<?php
session_start();
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Kasir & Billing dan IT Support yang bisa mengakses halaman ini
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

// Cek hak akses sesuai header.php: Hanya Kasir & Billing dan IT Support (Administrasi hanya antrian)
if ($jabatan_user != 'Kasir & Billing' && 
    $jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data transaksi. Hanya Kasir & Billing dan IT Support yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// PROSES HAPUS TRANSAKSI (beserta detailnya)
if (isset($_GET['hapus'])) {
    $id_transaksi = $_GET['hapus'];
    
    // Hapus detail transaksi terkait
    $hapus_detail = "DELETE FROM data_detail_transaksi WHERE id_transaksi = '$id_transaksi'";
    $db->koneksi->query($hapus_detail);
    
    if ($db->hapus_data_transaksi($id_transaksi)) {
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data transaksi berhasil dihapus.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data transaksi.';
    }
    header("Location: transaksi.php");
    exit();
}

// PROSES EDIT TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id_transaksi = $_POST['id_transaksi'] ?? '';
    $id_rekam = $_POST['id_rekam'] ?? '';
    $kode_staff = $_POST['kode_staff'] ?? '';
    $tanggal_transaksi = $_POST['tanggal_transaksi'] ?? date('Y-m-d H:i:s');
    $grand_total = $_POST['grand_total'] ?? 0;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    $status_pembayaran = $_POST['status_pembayaran'] ?? 'Belum Bayar';
    
    if (empty($id_transaksi)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'ID Transaksi tidak valid!';
        header("Location: transaksi.php");
        exit();
    }
    
    if ($db->edit_data_transaksi($id_transaksi, $id_rekam, $kode_staff, $tanggal_transaksi, $grand_total, $metode_pembayaran, $status_pembayaran)) {
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data transaksi berhasil diupdate.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data transaksi.';
    }
    header("Location: transaksi.php");
    exit();
}

// PROSES TAMBAH TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_transaksi'])) {
    $id_rekam = $_POST['id_rekam'] ?? '';
    $kode_staff = $_POST['kode_staff'] ?? '';
    $grand_total = $_POST['grand_total'] ?? 0;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    $status_pembayaran = $_POST['status_pembayaran'] ?? 'Belum Bayar';
    $tanggal_transaksi = $_POST['tanggal_transaksi'] ?? date('Y-m-d H:i:s');
    
    if ($db->tambah_data_transaksi($id_rekam, $kode_staff, $tanggal_transaksi, $grand_total, $metode_pembayaran, $status_pembayaran)) {
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data transaksi berhasil ditambahkan.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data transaksi.';
    }
    header("Location: transaksi.php");
    exit();
}

// ==================== PROSES AJAX ====================

// AJAX: Ambil detail transaksi dari data_detail_transaksi
if (isset($_GET['ajax_get_detail'])) {
    header('Content-Type: application/json');
    $id_transaksi = $_GET['id_transaksi'] ?? '';
    
    if (empty($id_transaksi)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Transaksi diperlukan']);
        exit;
    }
    
    $query = "SELECT * FROM data_detail_transaksi WHERE id_transaksi = '$id_transaksi' ORDER BY id_detail_transaksi ASC";
    $result = $db->koneksi->query($query);
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// Konfigurasi pagination
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

date_default_timezone_set('Asia/Jakarta');

// Ambil semua data transaksi
$all_transaksi = $db->tampil_data_transaksi();

// Ambil data untuk dropdown
$all_pasien = $db->tampil_data_pasien();
$all_staff = $db->tampil_data_staff();
$all_rekam_medis = $db->tampil_data_rekam_medis();

$pasien_map = [];
foreach ($all_pasien as $pasien) {
    $pasien_map[$pasien['id_pasien']] = $pasien['nama_pasien'];
}

// Filter data
if (!empty($search_query)) {
    $filtered_transaksi = [];
    foreach ($all_transaksi as $transaksi) {
        if (stripos($transaksi['id_transaksi'] ?? '', $search_query) !== false ||
            stripos($transaksi['id_rekam'] ?? '', $search_query) !== false ||
            stripos($transaksi['kode_staff'] ?? '', $search_query) !== false ||
            stripos($transaksi['tanggal_transaksi'] ?? '', $search_query) !== false ||
            stripos($transaksi['grand_total'] ?? '', $search_query) !== false) {
            $filtered_transaksi[] = $transaksi;
        }
    }
    $all_transaksi = $filtered_transaksi;
}

// Sorting
if ($sort_order === 'desc') {
    usort($all_transaksi, function($a, $b) {
        return ($b['id_transaksi'] ?? 0) - ($a['id_transaksi'] ?? 0);
    });
} else {
    usort($all_transaksi, function($a, $b) {
        return ($a['id_transaksi'] ?? 0) - ($b['id_transaksi'] ?? 0);
    });
}

// Pagination
$total_entries = count($all_transaksi);
$total_pages = ceil($total_entries / $entries_per_page);
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

// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'transaksi.php?';
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
    $url = 'transaksi.php?';
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
    
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transaksi - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css">
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/fonts/feather.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/style-preset.css">
    <style>
        .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
        .modal-content { border: none; border-radius: 12px; }
        .btn-detail:hover { transform: scale(1.05); }
        .badge-lunas { background-color: #28a745; color: #fff; }
        .badge-belum-bayar { background-color: #ffc107; color: #000; }
        .badge-tunai { background-color: #17a2b8; color: #fff; }
        .badge-transfer { background-color: #6610f2; color: #fff; }
        .badge-qris { background-color: #e83e8c; color: #fff; }
        .badge-debit { background-color: #fd7e14; color: #fff; }
        .badge-tindakan { background-color: #17a2b8; color: #fff; padding: 5px 10px; border-radius: 20px; font-size: 11px; }
        .badge-obat { background-color: #28a745; color: #fff; padding: 5px 10px; border-radius: 20px; font-size: 11px; }
        .grand-total { font-weight: bold; color: #28a745; }
        .today-date { background: linear-gradient(135deg, #17a2b8 0%, #1174ff 100%); color: white; padding: 8px 15px; border-radius: 20px; display: inline-block; margin-bottom: 15px; }
        .table-detail th, .table-detail td { padding: 8px; vertical-align: middle; }
        .info-box { background-color: #e7f3ff; border-left: 4px solid #2196f3; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        
        /* Styling untuk pagination seperti gambar */
        .pagination {
            gap: 5px;
        }
        .page-item .page-link {
            border-radius: 4px;
            color: #6c757d;
            padding: 6px 12px;
        }
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .page-item.disabled .page-link {
            color: #adb5bd;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
        .page-item:not(.disabled) .page-link:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>
    <?php include 'header.php'; ?>

    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Data Transaksi</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <h2 class="mb-0">Data Transaksi</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="container-fluid">
                <?php if ($notif_message): ?>
                <div class="alert alert-<?= $notif_status ?> alert-dismissible fade show">
                    <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($notif_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#tambahTransaksiModal">
                        <i class="fas fa-plus me-1"></i> Tambah Transaksi
                    </button>
                    <div class="today-date">
                        <i class="fas fa-calendar-alt me-2"></i><?= date('d F Y') ?>
                        <span class="badge bg-light text-dark ms-2"><?= $total_entries ?> Transaksi</span>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Search dan Entries -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <label class="me-2">Show</label>
                                    <select class="form-select form-select-sm w-auto" id="entriesPerPage" onchange="changeEntries()">
                                        <option value="5" <?= $entries_per_page == 5 ? 'selected' : '' ?>>5</option>
                                        <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $entries_per_page == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                    <label class="ms-2">entries</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" class="d-flex justify-content-end">
                                    <div class="input-group input-group-sm" style="width: 300px;">
                                        <input type="text" class="form-control" name="search" placeholder="Cari transaksi..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="transaksi.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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

                        <!-- Tabel Transaksi -->
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
                                        <th>ID Transaksi</th>
                                        <th>ID Rekam</th>
                                        <th>Kode Staff</th>
                                        <th>Tanggal</th>
                                        <th>Grand Total</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_transaksi)): ?>
                                        <?php $no = $start_number; foreach ($data_transaksi as $transaksi): 
                                            $id = $transaksi['id_transaksi'];
                                            $grand_total = $transaksi['grand_total'] ?? 0;
                                            $tanggal_transaksi = !empty($transaksi['tanggal_transaksi']) ? date('Y-m-d H:i:s', strtotime($transaksi['tanggal_transaksi'])) : '-';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?= $no ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($id) ?></td>
                                            <td><?= htmlspecialchars($transaksi['id_rekam'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($transaksi['kode_staff'] ?? '-') ?></td>
                                            <td><?= $tanggal_transaksi ?></td>
                                            <td class="grand-total grand-total-<?= $id ?>">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    switch($transaksi['metode_pembayaran']) {
                                                        case 'Tunai': echo 'badge-tunai'; break;
                                                        case 'Transfer': echo 'badge-transfer'; break;
                                                        case 'QRIS': echo 'badge-qris'; break;
                                                        case 'Debit': echo 'badge-debit'; break;
                                                        default: echo 'badge-secondary';
                                                    }
                                                ?>"><?= $transaksi['metode_pembayaran'] ?? '-' ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= ($transaksi['status_pembayaran'] ?? '') == 'Lunas' ? 'badge-lunas' : 'badge-belum-bayar' ?>">
                                                    <?= $transaksi['status_pembayaran'] ?? 'Belum Bayar' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-primary btn-sm btn-detail" onclick="showDetailTransaksi('<?= $id ?>', '<?= number_format($grand_total, 0, ',', '.') ?>', this)" title="Lihat Detail Item"><i class="fas fa-list-alt"></i></button>
                                                    <button class="btn btn-info btn-sm" onclick="cetakTransaksi('<?= $id ?>')" title="Cetak"><i class="fas fa-print"></i></button>
                                                    <button class="btn btn-warning btn-sm btn-edit" data-id="<?= $id ?>" data-rekam="<?= htmlspecialchars($transaksi['id_rekam'] ?? '') ?>" data-staff="<?= htmlspecialchars($transaksi['kode_staff'] ?? '') ?>" data-tanggal="<?= $transaksi['tanggal_transaksi'] ?? '' ?>" data-metode="<?= $transaksi['metode_pembayaran'] ?? '' ?>" data-status="<?= $transaksi['status_pembayaran'] ?? 'Belum Bayar' ?>" data-grand_total="<?= $grand_total ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-danger btn-sm btn-hapus" data-id="<?= $id ?>" title="Hapus"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php $no = ($sort_order === 'desc') ? $no - 1 : $no + 1; endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="9" class="text-center">Tidak ada data transaksi</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer seperti gambar -->
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
                                            <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                                Sebelumnya
                                            </a>
                                        </li>
                                        
                                        <!-- Page Numbers -->
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 1 && $i <= $current_page + 1)): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= getPaginationUrl($i, $entries_per_page, $search_query, $sort_order) ?>"><?= $i ?></a>
                                                </li>
                                            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Page -->
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
        </div>
    </div>

    <!-- Modal Tambah Transaksi -->
    <div class="modal fade" id="tambahTransaksiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-receipt me-2"></i>Tambah Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="tambah_transaksi" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">ID Rekam Medis</label>
                                <select class="form-select" name="id_rekam">
                                    <option value="">Pilih Rekam Medis</option>
                                    <?php foreach ($all_rekam_medis as $rekam): ?>
                                        <option value="<?= $rekam['id_rekam'] ?>">RM-<?= $rekam['id_rekam'] ?> - <?= $pasien_map[$rekam['id_pasien']] ?? '-' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Staff</label>
                                <select class="form-select" name="kode_staff">
                                    <option value="">Pilih Staff</option>
                                    <?php foreach ($all_staff as $staff): ?>
                                        <option value="<?= $staff['kode_staff'] ?>"><?= $staff['nama_staff'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Metode Pembayaran</label>
                                <select class="form-select" name="metode_pembayaran">
                                    <option value="">Pilih</option>
                                    <option value="Tunai">Tunai</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="Debit">Debit</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Pembayaran</label>
                                <select class="form-select" name="status_pembayaran">
                                    <option value="Belum Bayar">Belum Bayar</option>
                                    <option value="Lunas">Lunas</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Transaksi</label>
                                <input type="datetime-local" class="form-control" name="tanggal_transaksi" value="<?= date('Y-m-d\TH:i:s') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grand Total (Rp)</label>
                                <input type="number" class="form-control" name="grand_total" value="0">
                                <small class="text-muted">Isi sesuai total dari item transaksi</small>
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

    <!-- Modal Edit Transaksi -->
    <div class="modal fade" id="editTransaksiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-edit me-2"></i>Edit Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="edit_transaksi" value="1">
                    <input type="hidden" name="id_transaksi" id="edit_id_transaksi">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">ID Rekam Medis</label>
                                <select class="form-select" id="edit_id_rekam" name="id_rekam">
                                    <option value="">Pilih Rekam Medis</option>
                                    <?php foreach ($all_rekam_medis as $rekam): ?>
                                        <option value="<?= $rekam['id_rekam'] ?>">RM-<?= $rekam['id_rekam'] ?> - <?= $pasien_map[$rekam['id_pasien']] ?? '-' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Staff</label>
                                <select class="form-select" id="edit_kode_staff" name="kode_staff">
                                    <option value="">Pilih Staff</option>
                                    <?php foreach ($all_staff as $staff): ?>
                                        <option value="<?= $staff['kode_staff'] ?>"><?= $staff['nama_staff'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Metode Pembayaran</label>
                                <select class="form-select" id="edit_metode_pembayaran" name="metode_pembayaran">
                                    <option value="">Pilih</option>
                                    <option value="Tunai">Tunai</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="Debit">Debit</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Pembayaran</label>
                                <select class="form-select" id="edit_status_pembayaran" name="status_pembayaran">
                                    <option value="Belum Bayar">Belum Bayar</option>
                                    <option value="Lunas">Lunas</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Transaksi</label>
                                <input type="datetime-local" class="form-control" id="edit_tanggal_transaksi" name="tanggal_transaksi">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grand Total (Rp)</label>
                                <input type="number" class="form-control" id="edit_grand_total" name="grand_total">
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

    <!-- Modal Hapus Transaksi -->
    <div class="modal fade" id="hapusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-trash text-danger"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                    <p>Yakin hapus transaksi <strong id="idTransaksiHapus"></strong>?</p>
                    <p class="text-muted"><small>Semua item detail transaksi juga akan dihapus.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger">Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Transaksi -->
    <div class="modal fade" id="detailTransaksiModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5><i class="fas fa-list-alt me-2"></i>Detail Item Transaksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>ID Transaksi:</strong> <span id="detail_id_transaksi" class="fw-bold"></span></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4><strong>Grand Total:</strong> <span class="text-success" id="detail_grand_total">Rp 0</span></h4>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-bordered table-detail">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Jenis Item</th>
                                    <th width="35%">Nama Item</th>
                                    <th width="10%">Qty</th>
                                    <th width="15%">Harga Satuan</th>
                                    <th width="20%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="detail_transaksi_body">
                                <tr><td colspan="6" class="text-center"><div class="spinner-border text-primary"></div> Memuat data...</td></tr>
                            </tbody>
                            <tfoot class="table-active">
                                <tr>
                                    <th colspan="5" class="text-end">TOTAL</th>
                                    <th id="footer_total" class="text-success fw-bold">Rp 0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button class="btn btn-info" onclick="cetakStruk()"><i class="fas fa-print"></i> Cetak Struk</button>
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
    let currentDetailIdTransaksi = null;

    function formatNumber(num) {
        if (num === undefined || num === null) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function changeEntries() {
        let url = `transaksi.php?entries=${document.getElementById('entriesPerPage').value}&page=1&sort=<?= $sort_order ?>`;
        const search = '<?= addslashes($search_query) ?>';
        if (search) url += `&search=${encodeURIComponent(search)}`;
        window.location.href = url;
    }

    function cetakTransaksi(id) {
        window.open(`proses/cetak/cetak-transaksi-cetak.php?id_transaksi=${id}`, '_blank');
    }

    function cetakStruk() {
        if (currentDetailIdTransaksi) cetakTransaksi(currentDetailIdTransaksi);
        else alert('ID Transaksi tidak ditemukan');
    }

    // Hapus transaksi
    document.querySelectorAll('.btn-hapus').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('idTransaksiHapus').textContent = 'ID ' + this.dataset.id;
            document.getElementById('hapusButton').href = 'transaksi.php?hapus=' + this.dataset.id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });

    // Edit transaksi
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id_transaksi').value = this.dataset.id;
            document.getElementById('edit_id_rekam').value = this.dataset.rekam || '';
            document.getElementById('edit_kode_staff').value = this.dataset.staff || '';
            document.getElementById('edit_metode_pembayaran').value = this.dataset.metode || '';
            document.getElementById('edit_status_pembayaran').value = this.dataset.status || 'Belum Bayar';
            document.getElementById('edit_grand_total').value = this.dataset.grand_total || 0;
            
            if (this.dataset.tanggal && this.dataset.tanggal !== '' && this.dataset.tanggal !== 'null') {
                const d = new Date(this.dataset.tanggal);
                if (!isNaN(d.getTime())) {
                    document.getElementById('edit_tanggal_transaksi').value = d.toISOString().slice(0, 16);
                }
            }
            
            new bootstrap.Modal(document.getElementById('editTransaksiModal')).show();
        });
    });

    // Menampilkan detail transaksi
    function showDetailTransaksi(id_transaksi, grand_total_formatted, btnElement) {
        currentDetailIdTransaksi = id_transaksi;
        document.getElementById('detail_id_transaksi').innerText = id_transaksi;
        
        if (grand_total_formatted) {
            document.getElementById('detail_grand_total').innerHTML = 'Rp ' + grand_total_formatted;
        } else if (btnElement) {
            const row = btnElement.closest('tr');
            if (row) {
                const grandTotalCell = row.querySelector('.grand-total');
                if (grandTotalCell) {
                    document.getElementById('detail_grand_total').innerHTML = grandTotalCell.innerHTML;
                }
            }
        }
        
        loadDetailTransaksi(id_transaksi);
        new bootstrap.Modal(document.getElementById('detailTransaksiModal')).show();
    }

    // Load detail transaksi
    function loadDetailTransaksi(id_transaksi) {
        const tbody = document.getElementById('detail_transaksi_body');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary"></div> Memuat data...</td></tr>';
        
        fetch(`transaksi.php?ajax_get_detail=1&id_transaksi=${id_transaksi}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    renderDetailTable(data.data);
                    let total = 0;
                    data.data.forEach(item => total += parseFloat(item.subtotal));
                    document.getElementById('footer_total').innerHTML = 'Rp ' + formatNumber(total);
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada item transaksi</td></tr>';
                    document.getElementById('footer_total').innerHTML = 'Rp 0';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Gagal memuat data</td></tr>';
            });
    }

    // Render tabel detail
    function renderDetailTable(items) {
        const tbody = document.getElementById('detail_transaksi_body');
        tbody.innerHTML = '';
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada item transaksi</td></tr>';
            return;
        }
        
        let no = 1;
        items.forEach(item => {
            const row = tbody.insertRow();
            row.insertCell(0).innerHTML = no++;
            row.insertCell(1).innerHTML = `<span class="badge ${item.jenis_item == 'Tindakan' ? 'badge-tindakan' : 'badge-obat'}">${escapeHtml(item.jenis_item)}</span>`;
            row.insertCell(2).innerHTML = escapeHtml(item.nama_item);
            row.insertCell(3).innerHTML = item.qty;
            row.insertCell(4).innerHTML = 'Rp ' + formatNumber(item.harga);
            row.insertCell(5).innerHTML = 'Rp ' + formatNumber(item.subtotal);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    </script>
</body>
</html>

<?php require_once "footer.php"; ?>