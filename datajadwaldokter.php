<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "koneksi.php"; // Pastikan path ke file koneksi.php benar
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support yang bisa mengakses halaman ini
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

// Ambil semua data jadwal
$all_jadwal = $db->tampil_data_jadwal_dokter();

// Ambil data dokter untuk dropdown
$all_dokter = $db->tampil_data_dokter();

// Buat array untuk mapping dokter berdasarkan kode
$dokter_map = [];
foreach ($all_dokter as $dokter) {
    $dokter_map[$dokter['kode_dokter']] = $dokter['nama_dokter'];
}

// Array hari untuk mapping
$hari_array = [
    'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
];

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_jadwal = [];
    foreach ($all_jadwal as $jadwal) {
        // Cari di semua kolom yang relevan
        if (stripos($jadwal['id_jadwal'] ?? '', $search_query) !== false ||
            stripos($jadwal['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($jadwal['hari'] ?? '', $search_query) !== false ||
            stripos($jadwal['shift'] ?? '', $search_query) !== false ||
            stripos($jadwal['status'] ?? '', $search_query) !== false ||
            stripos($jadwal['jam_mulai'] ?? '', $search_query) !== false ||
            stripos($jadwal['jam_selesai'] ?? '', $search_query) !== false) {
            $filtered_jadwal[] = $jadwal;
        }
    }
    $all_jadwal = $filtered_jadwal;
}

// Urutkan data berdasarkan ID Jadwal
if ($sort_order === 'desc') {
    // Urutkan dari ID terbesar ke terkecil (terakhir ke terawal)
    usort($all_jadwal, function($a, $b) {
        return ($b['id_jadwal'] ?? 0) - ($a['id_jadwal'] ?? 0);
    });
} else {
    // Urutkan dari ID terkecil ke terbesar (terawal ke terakhir) - default
    usort($all_jadwal, function($a, $b) {
        return ($a['id_jadwal'] ?? 0) - ($b['id_jadwal'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_jadwal);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_jadwal = array_slice($all_jadwal, $offset, $entries_per_page);

// Hitung nomor urut yang benar berdasarkan sorting
if ($sort_order === 'desc') {
    // Untuk descending: nomor urut dari total_entries ke bawah
    $start_number = $total_entries - $offset;
} else {
    // Untuk ascending: nomor urut dari 1 ke atas (default)
    $start_number = $offset + 1;
}

// Tampilkan notifikasi jika ada
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

// Cek apakah data jadwal kosong untuk memicu modal
$is_data_empty = empty($data_jadwal);

// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'datajadwaldokter.php?';
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
    $url = 'datajadwaldokter.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    // Toggle sort order
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!DOCTYPE html>
<html lang="en">
  <!-- [Head] start -->

  <head>
    <title>Jadwal Dokter - Sistem Informasi Poliklinik Mata Eyethica</title>
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description"
      content="Able Pro is a trending dashboard template built with the Bootstrap 5 design framework. It is available in multiple technologies, including Bootstrap, React, Vue, CodeIgniter, Angular, .NET, and more.">
    <meta name="keywords"
      content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel, react dashboard, angular dashboard">
    <meta name="author" content="Phoenixcoded">

    <!-- [Favicon] icon -->
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon"> <!-- [Font] Family -->
<link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
<!-- [Tabler Icons] https://tablericons.com -->
<link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
<!-- [Feather Icons] https://feathericons.com -->
<link rel="stylesheet" href="assets/fonts/feather.css" >
<!-- [Font Awesome Icons] https://fontawesome.com/icons -->
<link rel="stylesheet" href="assets/fonts/fontawesome.css" >
<!-- [Material Icons] https://fonts.google.com/icons -->
<link rel="stylesheet" href="assets/fonts/material.css" >
<!-- [Template CSS Files] -->
<link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
<link rel="stylesheet" href="assets/css/style-preset.css" >

<style>
/* Memusatkan modal secara vertikal */
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

/* Optional: Tambahkan animasi yang lebih smooth */
.modal.fade .modal-dialog {
    transform: translate(0, -50px);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: none;
}

/* Memastikan modal konten memiliki margin otomatis */
.modal-content {
    margin: auto;
}

/* Styling untuk modal hapus */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal {
    backdrop-filter: blur(2px);
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

/* Animasi modal */
.modal.fade .modal-dialog {
    transform: translateY(-50px);
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: translateY(0);
}

/* Tombol hapus dan edit */
.btn-hapus, .btn-edit {
    transition: all 0.3s ease;
}

.btn-hapus:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-edit:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

/* Loading spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Badge status jadwal */
.badge-aktif {
    background-color: #28a745;
    color: #fff;
}

.badge-libur {
    background-color: #ffc107;
    color: #000;
}

.badge-cuti {
    background-color: #dc3545;
    color: #fff;
}

/* Styling untuk table */
.table th {
    border-top: none;
    font-weight: 600;
}

/* Responsive table */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
}

/* Alert untuk informasi */
.alert-informasi {
    background-color: #e3f2fd;
    border-color: #bbdefb;
    color: #1565c0;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    margin-top: 0.5rem;
    border-radius: 0.375rem;
}

/* Styling untuk jam */
.jam-badge {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 3px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9rem;
}
</style>
  </head>
  <!-- [Head] end -->
  <!-- [Body] Start -->

  <body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
<div class="loader-bg">
  <div class="loader-track">
    <div class="loader-fill"></div>
  </div>
</div>
<!-- [ Pre-loader ] End -->
<?php include 'header.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
      <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                  <li class="breadcrumb-item" aria-current="page">Data Jadwal Dokter</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Data Jadwal Dokter</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
         <div class="container-fluid">
            <?php if ($notif_message): ?>
            <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($notif_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-start mb-4">
                <!-- Tombol Tambah Jadwal dengan Modal -->
                <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahJadwalModal">
                    <i class="fas fa-plus me-1"></i> Tambah Jadwal
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
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           placeholder="Cari data jadwal..." 
                                           value="<?= htmlspecialchars($search_query) ?>"
                                           aria-label="Search">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                    <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="datajadwaldokter.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger" type="button">
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="jadwalTable" class="table table-hover">
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
                                    <th>Kode Dokter</th>
                                    <th>Nama Dokter</th>
                                    <th>Hari</th>
                                    <th>Shift</th>
                                    <th>Status</th>
                                    <th>Jam Mulai</th>
                                    <th>Jam Selesai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_jadwal) && is_array($data_jadwal)) {
                                    foreach ($data_jadwal as $jadwal) {
                                        $id_jadwal = htmlspecialchars($jadwal['id_jadwal'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $kode_dokter = htmlspecialchars($jadwal['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $hari = htmlspecialchars($jadwal['hari'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $shift = htmlspecialchars($jadwal['shift'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $status = htmlspecialchars($jadwal['status'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $jam_mulai = htmlspecialchars($jadwal['jam_mulai'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $jam_selesai = htmlspecialchars($jadwal['jam_selesai'] ?? '', ENT_QUOTES, 'UTF-8');
                                        
                                        // Format jam
                                        $jam_mulai_formatted = !empty($jam_mulai) ? date('H:i', strtotime($jam_mulai)) : '-';
                                        $jam_selesai_formatted = !empty($jam_selesai) ? date('H:i', strtotime($jam_selesai)) : '-';
                                        
                                        // Get nama dokter
                                        $nama_dokter = isset($dokter_map[$kode_dokter]) ? htmlspecialchars($dokter_map[$kode_dokter]) : 'Tidak Diketahui';
                                        
                                        // Tentukan class badge berdasarkan status
                                        $badge_class = 'badge-secondary';
                                        switch ($status) {
                                            case 'Aktif':
                                                $badge_class = 'badge-aktif';
                                                break;
                                            case 'Libur':
                                                $badge_class = 'badge-libur';
                                                break;
                                            case 'Cuti':
                                                $badge_class = 'badge-cuti';
                                                break;
                                        }
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_jadwal ?></td>
                                        <td><?= $kode_dokter ?></td>
                                        <td><?= $nama_dokter ?></td>
                                        <td><?= $hari ?></td>
                                        <td><?= $shift ?></td>
                                        <td><span class="badge <?= $badge_class ?>"><?= $status ?></span></td>
                                        <td><span class="jam-badge"><?= $jam_mulai_formatted ?></span></td>
                                        <td><span class="jam-badge"><?= $jam_selesai_formatted ?></span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn btn-warning btn-sm btn-edit"
                                                        data-id="<?= $id_jadwal ?>"
                                                        data-dokter="<?= $kode_dokter ?>"
                                                        data-hari="<?= $hari ?>"
                                                        data-shift="<?= $shift ?>"
                                                        data-status="<?= $status ?>"
                                                        data-jam_mulai="<?= $jam_mulai ?>"
                                                        data-jam_selesai="<?= $jam_selesai ?>"
                                                        title="Edit Jadwal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-hapus"
                                                        data-id="<?= $id_jadwal ?>"
                                                        title="Hapus Jadwal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $start_number--; // Untuk descending: turun
                                        } else {
                                            $start_number++; // Untuk ascending: naik
                                        }
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center text-muted">';
                                    if (!empty($search_query)) {
                                        echo 'Tidak ada data jadwal yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                    } else {
                                        echo 'Tidak ada data jadwal ditemukan.';
                                    }
                                    echo '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination - SELALU TAMPIL JIKA ADA DATA -->
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
                                    
                                    <!-- Page Numbers dengan format: Sebelumnya | 1 | 2 3 4 5... 11 Selanjutnya -->
                                    <?php
                                    // Selalu tampilkan halaman 1
                                    echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order) . '">1</a>';
                                    echo '</li>';
                                    
                                    // Tentukan range halaman yang akan ditampilkan
                                    $start = 2;
                                    $end = min(5, $total_pages - 1);
                                    
                                    // Jika current page > 3, adjust the range
                                    if ($current_page > 3) {
                                        $start = $current_page - 1;
                                        $end = min($current_page + 2, $total_pages - 1);
                                    }
                                    
                                    // Tampilkan ellipsis jika ada gap
                                    if ($start > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    // Tampilkan halaman-halaman
                                    for ($i = $start; $i <= $end; $i++) {
                                        if ($i < $total_pages) {
                                            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                    }
                                    
                                    // Tampilkan ellipsis sebelum halaman terakhir jika perlu
                                    if ($end < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    // Tampilkan halaman terakhir jika lebih dari 1 halaman
                                    if ($total_pages > 1) {
                                        echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order) . '">' . $total_pages . '</a>';
                                        echo '</li>';
                                    }
                                    ?>
                                    
                                    <!-- Next Page -->
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                            Selanjutnya
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <?php else: ?>
                            <!-- Tampilkan pagination sederhana jika hanya 1 halaman -->
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
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Tambah Jadwal -->
    <div class="modal fade" id="tambahJadwalModal" tabindex="-1" aria-labelledby="tambahJadwalModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahJadwalModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Tambah Jadwal Baru
                    </h5>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-jadwal-dokter.php" id="tambahJadwalForm">
                    <input type="hidden" name="tambah_jadwal" value="1">
                    <div class="modal-body">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kode_dokter" class="form-label">Dokter <span class="text-danger">*</span></label>
                                    <select class="form-select" id="kode_dokter" name="kode_dokter" required>
                                        <option value="">Pilih Dokter</option>
                                        <?php if (!empty($all_dokter)): ?>
                                            <?php foreach ($all_dokter as $dokter): ?>
                                                <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                    <?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">Data dokter tidak tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hari" class="form-label">Hari <span class="text-danger">*</span></label>
                                    <select class="form-select" id="hari" name="hari" required>
                                        <option value="">Pilih Hari</option>
                                        <option value="Minggu">Minggu</option>
                                        <option value="Senin">Senin</option>
                                        <option value="Selasa">Selasa</option>
                                        <option value="Rabu">Rabu</option>
                                        <option value="Kamis">Kamis</option>
                                        <option value="Jumat">Jumat</option>
                                        <option value="Sabtu">Sabtu</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jam_mulai" class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="jam_mulai" name="jam_mulai" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jam_selesai" class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="jam_selesai" name="jam_selesai" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Aktif" selected>Aktif</option>
                                        <option value="Libur">Libur</option>
                                        <option value="Cuti">Cuti</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="shift" class="form-label">Shift</label>
                                    <select class="form-select" id="shift" name="shift">
                                        <option value="Pagi" selected>Shift Pagi</option>
                                        <option value="Sore">Shift Sore</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnTambahJadwal">
                            <i class="fas fa-save me-1"></i>Simpan Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Jadwal -->
    <div class="modal fade" id="editJadwalModal" tabindex="-1" aria-labelledby="editJadwalModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editJadwalModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Jadwal
                    </h5>
                </div>
                <form method="POST" action="proses/edit/edit-data-jadwal-dokter.php" id="editJadwalForm">
                    <input type="hidden" name="edit_jadwal" value="1">
                    <input type="hidden" id="edit_id_jadwal" name="id_jadwal">
                    
                    <div class="modal-body">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_dokter" class="form-label">Dokter <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_kode_dokter" name="kode_dokter" required>
                                        <option value="">Pilih Dokter</option>
                                        <?php if (!empty($all_dokter)): ?>
                                            <?php foreach ($all_dokter as $dokter): ?>
                                                <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                    <?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">Data dokter tidak tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_hari" class="form-label">Hari <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_hari" name="hari" required>
                                        <option value="">Pilih Hari</option>
                                        <option value="Minggu">Minggu</option>
                                        <option value="Senin">Senin</option>
                                        <option value="Selasa">Selasa</option>
                                        <option value="Rabu">Rabu</option>
                                        <option value="Kamis">Kamis</option>
                                        <option value="Jumat">Jumat</option>
                                        <option value="Sabtu">Sabtu</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jam_mulai" class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="edit_jam_mulai" name="jam_mulai" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jam_selesai" class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="edit_jam_selesai" name="jam_selesai" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="Aktif">Aktif</option>
                                        <option value="Libur">Libur</option>
                                        <option value="Cuti">Cuti</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_shift" class="form-label">Shift</label>
                                    <select class="form-select" id="edit_shift" name="shift">
                                        <option value="Pagi">Shift Pagi</option>
                                        <option value="Sore">Shift Sore</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateJadwal">
                            <i class="fas fa-save me-1"></i>Update Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Jadwal -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-labelledby="hapusModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    </div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus jadwal:</p>
                    <h5 class="text-center text-danger" id="idJadwalHapus"></h5>
                    <p class="text-center text-muted mt-3">
                        <small>Data yang dihapus tidak dapat dikembalikan.</small>
                    </p>

                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <a href="#" id="hapusButton" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>
     <!-- Buy Now Link Script -->
    <script defer src="https://fomo.codedthemes.com/pixel/CDkpF1sQ8Tt5wpMZgqRvKpQiUhpWE3bc"></script>

    <script>
    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        let url = 'datajadwaldokter.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }

    // Function untuk clear search
    function clearSearch() {
        const entries = document.getElementById('entriesPerPage').value;
        const sort = '<?= $sort_order ?>';
        window.location.href = 'datajadwaldokter.php?entries=' + entries + '&sort=' + sort;
    }

    // Function untuk menutup modal tambah jadwal
    function closeTambahJadwalModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('tambahJadwalModal'));
        modal.hide();
    }

    // Function untuk menutup modal edit jadwal
    function closeEditJadwalModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('editJadwalModal'));
        modal.hide();
    }

    // Function untuk menutup modal hapus jadwal
    function closeHapusModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('hapusModal'));
        modal.hide();
    }

    // Function untuk menampilkan modal hapus
    function showHapusModal(id) {
        document.getElementById('idJadwalHapus').textContent = 'ID ' + id;
        document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-jadwal-dokter.php?hapus=' + id;
        
        // Tampilkan modal dengan membuat instance baru
        const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
        hapusModal.show();
    }

    // Function untuk menampilkan modal edit
    function showEditModal(id, dokter, hari, jam_mulai, jam_selesai, status) {
        // Isi form dengan data yang ada
        document.getElementById('edit_id_jadwal').value = id;
        document.getElementById('edit_kode_dokter').value = dokter;
        document.getElementById('edit_hari').value = hari;
        document.getElementById('edit_jam_mulai').value = jam_mulai;
        document.getElementById('edit_jam_selesai').value = jam_selesai;
        document.getElementById('edit_status').value = status;
        
        // Tampilkan modal
        const editModal = new bootstrap.Modal(document.getElementById('editJadwalModal'));
        editModal.show();
    }

    // Function untuk handle submit form
    function handleFormSubmit(e, buttonId) {
        e.preventDefault();
        
        const submitButton = document.getElementById(buttonId);
        const originalText = submitButton.innerHTML;
        
        // Tampilkan loading
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
        submitButton.disabled = true;
        
        // Submit form
        setTimeout(() => {
            e.target.submit();
        }, 500);
    }

    // Validasi jam (jam mulai harus sebelum jam selesai)
    function setupTimeValidation() {
        const jamMulaiInput = document.getElementById('jam_mulai');
        const jamSelesaiInput = document.getElementById('jam_selesai');
        const editJamMulaiInput = document.getElementById('edit_jam_mulai');
        const editJamSelesaiInput = document.getElementById('edit_jam_selesai');
        
        function validateTime(startInput, endInput) {
            const startTime = startInput.value;
            const endTime = endInput.value;
            
            if (startTime && endTime) {
                const start = new Date('1970-01-01T' + startTime + ':00');
                const end = new Date('1970-01-01T' + endTime + ':00');
                
                if (start >= end) {
                    alert('Jam mulai harus sebelum jam selesai!');
                    endInput.value = '';
                    endInput.focus();
                    return false;
                }
            }
            return true;
        }
        
        if (jamMulaiInput && jamSelesaiInput) {
            jamSelesaiInput.addEventListener('change', function() {
                validateTime(jamMulaiInput, jamSelesaiInput);
            });
        }
        
        if (editJamMulaiInput && editJamSelesaiInput) {
            editJamSelesaiInput.addEventListener('change', function() {
                validateTime(editJamMulaiInput, editJamSelesaiInput);
            });
        }
    }

    // Setup modal dengan event delegation
    document.addEventListener('DOMContentLoaded', function() {
        // Setup validasi waktu
        setupTimeValidation();
        
        // Event delegation untuk tombol hapus
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-hapus')) {
                e.preventDefault();
                const button = e.target.closest('.btn-hapus');
                const id = button.getAttribute('data-id');
                showHapusModal(id);
            }
        });

        // Event delegation untuk tombol edit
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit')) {
                e.preventDefault();
                const button = e.target.closest('.btn-edit');
                const id = button.getAttribute('data-id');
                const dokter = button.getAttribute('data-dokter');
                const hari = button.getAttribute('data-hari');
                const jam_mulai = button.getAttribute('data-jam_mulai');
                const jam_selesai = button.getAttribute('data-jam_selesai');
                const status = button.getAttribute('data-status');
                showEditModal(id, dokter, hari, jam_mulai, jam_selesai, status);
            }
        });

        // Event listener untuk form tambah
        const tambahForm = document.getElementById('tambahJadwalForm');
        if (tambahForm) {
            tambahForm.addEventListener('submit', function(e) {
                // Validasi sebelum submit
                const jamMulai = document.getElementById('jam_mulai').value;
                const jamSelesai = document.getElementById('jam_selesai').value;
                
                if (jamMulai && jamSelesai) {
                    const start = new Date('1970-01-01T' + jamMulai + ':00');
                    const end = new Date('1970-01-01T' + jamSelesai + ':00');
                    
                    if (start >= end) {
                        alert('Jam mulai harus sebelum jam selesai!');
                        e.preventDefault();
                        return;
                    }
                }
                
                handleFormSubmit(e, 'btnTambahJadwal');
            });
        }

        // Event listener untuk form edit
        const editForm = document.getElementById('editJadwalForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                // Validasi sebelum submit
                const jamMulai = document.getElementById('edit_jam_mulai').value;
                const jamSelesai = document.getElementById('edit_jam_selesai').value;
                
                if (jamMulai && jamSelesai) {
                    const start = new Date('1970-01-01T' + jamMulai + ':00');
                    const end = new Date('1970-01-01T' + jamSelesai + ':00');
                    
                    if (start >= end) {
                        alert('Jam mulai harus sebelum jam selesai!');
                        e.preventDefault();
                        return;
                    }
                }
                
                handleFormSubmit(e, 'btnUpdateJadwal');
            });
        }

        // Auto focus pada input search
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && '<?= $search_query ?>') {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }

        // Reset form modal ketika ditutup
        const tambahJadwalModal = document.getElementById('tambahJadwalModal');
        if (tambahJadwalModal) {
            tambahJadwalModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('tambahJadwalForm').reset();
                const submitButton = document.getElementById('btnTambahJadwal');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Simpan Jadwal';
                submitButton.disabled = false;
            });
        }

        const editJadwalModal = document.getElementById('editJadwalModal');
        if (editJadwalModal) {
            editJadwalModal.addEventListener('hidden.bs.modal', function () {
                const submitButton = document.getElementById('btnUpdateJadwal');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Update Jadwal';
                submitButton.disabled = false;
            });
        }

        // Event listener untuk tombol close manual
        document.querySelectorAll('#tambahJadwalModal .btn-close, #tambahJadwalModal .btn-secondary').forEach(btn => {
            btn.addEventListener('click', closeTambahJadwalModal);
        });
        
        document.querySelectorAll('#editJadwalModal .btn-close, #editJadwalModal .btn-secondary').forEach(btn => {
            btn.addEventListener('click', closeEditJadwalModal);
        });
        
        document.querySelectorAll('#hapusModal .btn-close, #hapusModal .btn-secondary').forEach(btn => {
            btn.addEventListener('click', closeHapusModal);
        });
        
        // Inisialisasi modal saat pertama kali load
        if ('<?= $is_data_empty ?>' === '1') {
            const tambahModal = new bootstrap.Modal(document.getElementById('tambahJadwalModal'));
            setTimeout(() => {
                tambahModal.show();
            }, 1000);
        }
    });
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
    
  </body>
  <!-- [Body] end -->
</html>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>