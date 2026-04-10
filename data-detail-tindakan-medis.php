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

// Cek hak akses sesuai header.php: IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang bisa akses data detail tindakan medis
if ($jabatan_user != 'IT Support' && 
    $role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Medical Record') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data detail tindakan medis. Hanya Staff dengan jabatan IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data detail tindakan medis dengan JOIN ke tabel terkait
$query_murni = "SELECT dtm.*, dr.id_rekam, dr.tanggal_periksa, dp.nama_pasien, dp.nik,
                tm.nama_tindakan, tm.tarif as harga_tindakan
                FROM data_detail_tindakan_medis dtm
                JOIN data_rekam_medis dr ON dtm.id_rekam = dr.id_rekam
                JOIN data_pasien dp ON dr.id_pasien = dp.id_pasien
                JOIN data_tindakan_medis tm ON dtm.id_tindakan_medis = tm.id_tindakan_medis
                ORDER BY dtm.id_detail_tindakanmedis $sort_order";
$result_murni = $db->koneksi->query($query_murni);
$all_detail = [];
if ($result_murni && $result_murni->num_rows > 0) {
    while ($row = $result_murni->fetch_assoc()) {
        $all_detail[] = $row;
    }
}

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_detail = [];
    foreach ($all_detail as $detail) {
        if (stripos($detail['id_detail_tindakanmedis'] ?? '', $search_query) !== false ||
            stripos($detail['id_rekam'] ?? '', $search_query) !== false ||
            stripos($detail['nama_tindakan'] ?? '', $search_query) !== false ||
            stripos($detail['nama_pasien'] ?? '', $search_query) !== false) {
            $filtered_detail[] = $detail;
        }
    }
    $all_detail = $filtered_detail;
}

// Hitung total data
$total_entries = count($all_detail);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_detail = array_slice($all_detail, $offset, $entries_per_page);

// Nomor urut
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

// Tampilkan notifikasi
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$is_data_empty = empty($data_detail);

// Ambil data rekam medis untuk dropdown
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

// Ambil data tindakan medis untuk dropdown
$query_tindakan = "SELECT * FROM data_tindakan_medis ORDER BY nama_tindakan ASC";
$result_tindakan = $db->koneksi->query($query_tindakan);
$data_tindakan_medis = [];
if ($result_tindakan && $result_tindakan->num_rows > 0) {
    while ($row = $result_tindakan->fetch_assoc()) {
        $data_tindakan_medis[] = $row;
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
    <title>Detail Tindakan Medis - Sistem Informasi Poliklinik Mata Eyethica</title>
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
                                <li class="breadcrumb-item" aria-current="page">Data Detail Tindakan Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Detail Tindakan Medis</h2>
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
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahDetailModal">
                        <i class="fas fa-plus me-1"></i> Tambah Detail Tindakan Medis
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari ID, Pasien, atau Tindakan..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="data_detail_tindakan_medis.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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
                                        <th>Tindakan</th>
                                        <th>Qty</th>
                                        <th>Harga</th>
                                        <th>Subtotal</th>
                                        <th>Created At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_detail) && is_array($data_detail)): ?>
                                        <?php $counter = $start_number; foreach ($data_detail as $detail): ?>
                                            <?php
                                            $id_detail = htmlspecialchars($detail['id_detail_tindakanmedis'] ?? '');
                                            $id_rekam = htmlspecialchars($detail['id_rekam'] ?? '');
                                            $nama_pasien = htmlspecialchars($detail['nama_pasien'] ?? '-');
                                            $nama_tindakan = htmlspecialchars($detail['nama_tindakan'] ?? '-');
                                            $qty = htmlspecialchars($detail['qty'] ?? 0);
                                            $harga = number_format($detail['harga'] ?? 0, 0, ',', '.');
                                            $subtotal = number_format($detail['subtotal'] ?? 0, 0, ',', '.');
                                            $created_at = !empty($detail['created_at']) ? date('d/m/Y H:i:s', strtotime($detail['created_at'])) : '-';
                                            ?>
                                            <tr>
                                                <td><?= $counter ?></td>
                                                <td><?= $id_detail ?></td>
                                                <td><?= $id_rekam ?></td>
                                                <td><?= $nama_tindakan ?></td>
                                                <td><?= $qty ?></td>
                                                <td>Rp <?= $harga ?></td>
                                                <td>Rp <?= $subtotal ?></td>
                                                <td><?= $created_at ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-info btn-sm btn-view" onclick='showDetail(<?= $id_detail ?>, "<?= addslashes($nama_pasien) ?>", "<?= addslashes($nama_tindakan) ?>", <?= $qty ?>, <?= $detail['harga'] ?? 0 ?>, <?= $detail['subtotal'] ?? 0 ?>, "<?= $created_at ?>")' title="Lihat Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-warning btn-sm btn-edit" 
                                                                data-id="<?= $id_detail ?>" 
                                                                data-id_rekam="<?= $id_rekam ?>"
                                                                data-id_tindakan="<?= $detail['id_tindakan_medis'] ?>"
                                                                data-qty="<?= $qty ?>"
                                                                data-harga="<?= $detail['harga'] ?>"
                                                                data-subtotal="<?= $detail['subtotal'] ?>"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-hapus" 
                                                                data-id="<?= $id_detail ?>" 
                                                                data-nama_tindakan="<?= $nama_tindakan ?>"
                                                                title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter = ($sort_order === 'desc') ? $counter - 1 : $counter + 1; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="10" class="text-center text-muted">Tidak ada data detail tindakan medis ditemukan.</td></tr>
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
    <div class="modal fade" id="tambahDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Detail Tindakan Medis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="proses/tambah/tambah-detail-tindakan-medis.php" id="formTambah">
                    <input type="hidden" name="tambah_detail" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-rekam-tambah" name="id_rekam" required style="width:100%">
                                <option value="">-- Cari Rekam Medis --</option>
                                <?php foreach ($data_rekam_medis as $rekam): ?>
                                <option value="<?= $rekam['id_rekam'] ?>"><?= $rekam['id_rekam'] ?> - <?= $rekam['nik'] ?> - <?= $rekam['nama_pasien'] ?> (<?= $rekam['jenis_kunjungan'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih Tindakan Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-tindakan-tambah" name="id_tindakan_medis" id="id_tindakan_medis_tambah" required style="width:100%">
                                <option value="">-- Cari Tindakan Medis --</option>
                                <?php foreach ($data_tindakan_medis as $tindakan): ?>
                                <option value="<?= $tindakan['id_tindakan_medis'] ?>" data-tarif="<?= $tindakan['tarif'] ?>"><?= $tindakan['nama_tindakan'] ?> (Rp <?= number_format($tindakan['tarif'], 0, ',', '.') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="qty" id="qty_tambah" min="1" value="1" required onchange="hitungSubtotalTambah()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga</label>
                            <input type="text" class="form-control" id="harga_tambah" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subtotal</label>
                            <input type="text" class="form-control" id="subtotal_tambah" readonly>
                            <input type="hidden" name="subtotal" id="subtotal_hidden_tambah">
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Detail Tindakan Medis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="proses/edit/edit-detail-tindakan-medis.php" id="formEdit">
                    <input type="hidden" name="edit_detail" value="1">
                    <input type="hidden" name="id_detail_tindakanmedis" id="edit_id_detail">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Rekam Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-rekam-edit" name="id_rekam" id="edit_id_rekam" required style="width:100%">
                                <option value="">-- Cari Rekam Medis --</option>
                                <?php foreach ($data_rekam_medis as $rekam): ?>
                                <option value="<?= $rekam['id_rekam'] ?>"><?= $rekam['id_rekam'] ?> - <?= $rekam['nik'] ?> - <?= $rekam['nama_pasien'] ?> (<?= $rekam['jenis_kunjungan'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih Tindakan Medis <span class="text-danger">*</span></label>
                            <select class="form-select select2-tindakan-edit" name="id_tindakan_medis" id="edit_id_tindakan" required style="width:100%">
                                <option value="">-- Cari Tindakan Medis --</option>
                                <?php foreach ($data_tindakan_medis as $tindakan): ?>
                                <option value="<?= $tindakan['id_tindakan_medis'] ?>" data-tarif="<?= $tindakan['tarif'] ?>"><?= $tindakan['nama_tindakan'] ?> (Rp <?= number_format($tindakan['tarif'], 0, ',', '.') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="qty" id="edit_qty" min="1" required onchange="hitungSubtotalEdit()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga</label>
                            <input type="text" class="form-control" id="edit_harga" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subtotal</label>
                            <input type="text" class="form-control" id="edit_subtotal" readonly>
                            <input type="hidden" name="subtotal" id="edit_subtotal_hidden">
                        </div>
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
                    <p>Apakah Anda yakin ingin menghapus detail tindakan medis ini?</p>
                    <p class="text-danger" id="detailHapusInfo"></p>
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
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-notes-medical me-2"></i>Detail Tindakan Medis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4"><h4 class="fw-bold mb-1" id="detailId">-</h4></div>
                    <hr>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-database me-2 text-primary"></i>Informasi Detail</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">ID Detail</small><span id="detailIdFull">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Tindakan Medis</small><span id="detailTindakan">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Quantity</small><span id="detailQty">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Harga</small><span id="detailHarga">-</span></div>
                                <div class="col-md-6 mb-2"><small class="text-muted d-block">Subtotal</small><span id="detailSubtotal">-</span></div>
                                <div class="col-md-12 mb-2"><small class="text-muted d-block">Created At</small><span id="detailCreatedAt">-</span></div>
                            </div>
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
        window.location.href = 'data_detail_tindakan_medis.php?entries=' + entries + '&page=1&sort=' + sort + (search ? '&search=' + encodeURIComponent(search) : '');
    }
    
    function hitungSubtotalTambah() {
        var select = document.getElementById('id_tindakan_medis_tambah');
        var harga = select.options[select.selectedIndex]?.getAttribute('data-tarif') || 0;
        var qty = parseInt(document.getElementById('qty_tambah').value) || 0;
        var subtotal = harga * qty;
        document.getElementById('harga_tambah').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(harga);
        document.getElementById('subtotal_tambah').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
        document.getElementById('subtotal_hidden_tambah').value = subtotal;
    }
    
    function hitungSubtotalEdit() {
        var select = document.getElementById('edit_id_tindakan');
        var harga = select.options[select.selectedIndex]?.getAttribute('data-tarif') || 0;
        var qty = parseInt(document.getElementById('edit_qty').value) || 0;
        var subtotal = harga * qty;
        document.getElementById('edit_harga').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(harga);
        document.getElementById('edit_subtotal').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
        document.getElementById('edit_subtotal_hidden').value = subtotal;
    }
    
    function showDetail(id, tindakan, qty, harga, subtotal, created_at) {
        document.getElementById('detailId').innerHTML = 'Detail Tindakan Medis #' + id;
        document.getElementById('detailIdFull').innerHTML = id;
        document.getElementById('detailTindakan').innerHTML = tindakan;
        document.getElementById('detailQty').innerHTML = qty;
        document.getElementById('detailHarga').innerHTML = 'Rp ' + new Intl.NumberFormat('id-ID').format(harga);
        document.getElementById('detailSubtotal').innerHTML = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
        document.getElementById('detailCreatedAt').innerHTML = created_at;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
    
    $(document).ready(function() {
        $('.select2-rekam-tambah').select2({ theme: 'bootstrap-5', dropdownParent: $('#tambahDetailModal'), placeholder: 'Cari rekam medis...', allowClear: true, width: '100%' });
        $('.select2-tindakan-tambah').select2({ theme: 'bootstrap-5', dropdownParent: $('#tambahDetailModal'), placeholder: 'Cari tindakan medis...', allowClear: true, width: '100%' });
        $('.select2-rekam-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#editDetailModal'), placeholder: 'Cari rekam medis...', allowClear: true, width: '100%' });
        $('.select2-tindakan-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#editDetailModal'), placeholder: 'Cari tindakan medis...', allowClear: true, width: '100%' });
        
        $('#id_tindakan_medis_tambah').on('change', function() { hitungSubtotalTambah(); });
        $('#edit_id_tindakan').on('change', function() { hitungSubtotalEdit(); });
    });
    
    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id_detail').value = this.dataset.id;
            $('#edit_id_rekam').val(this.dataset.id_rekam).trigger('change');
            $('#edit_id_tindakan').val(this.dataset.id_tindakan).trigger('change');
            document.getElementById('edit_qty').value = this.dataset.qty;
            // Simpan harga asli untuk ditampilkan
            var hargaAsli = this.dataset.harga;
            setTimeout(function() { 
                hitungSubtotalEdit();
                // Jika perlu menampilkan harga asli
                if (hargaAsli) {
                    document.getElementById('edit_harga').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(hargaAsli);
                }
            }, 100);
            new bootstrap.Modal(document.getElementById('editDetailModal')).show();
        });
    });
    
    document.querySelectorAll('.btn-hapus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('detailHapusInfo').innerHTML = '<strong>ID Detail: ' + this.dataset.id + '<br>Pasien: ' + this.dataset.nama_pasien + '<br>Tindakan: ' + this.dataset.nama_tindakan + '</strong>';
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-detail-tindakan-medis.php?hapus=' + this.dataset.id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        });
    });
    
    document.getElementById('tambahDetailModal').addEventListener('hidden.bs.modal', function() {
        this.querySelector('form').reset();
        $('#id_rekam_tambah').val(null).trigger('change');
        $('#id_tindakan_medis_tambah').val(null).trigger('change');
        document.getElementById('harga_tambah').value = '';
        document.getElementById('subtotal_tambah').value = '';
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
    return 'data_detail_tindakan_medis.php?' . implode('&', $params);
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
    return 'data_detail_tindakan_medis.php?' . implode('&', $params);
}
?>