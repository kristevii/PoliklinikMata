<?php
session_start();
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

// Cek hak akses sesuai header.php: IT Support dan Administrasi yang bisa akses data pasien
if ($jabatan_user != 'IT Support' && $jabatan_user != 'Administrasi') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data pasien. Hanya Staff dengan jabatan IT Support dan Administrasi yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Fungsi format tanggal sesuai bahasa
function formatTanggal($tanggal, $bahasa = 'id') {
    if (empty($tanggal)) return '';
    
    try {
        $formatter = new IntlDateFormatter(
            $bahasa,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'Asia/Jakarta',
            IntlDateFormatter::GREGORIAN
        );
        return $formatter->format(new DateTime($tanggal));
    } catch (Exception $e) {
        return $tanggal;
    }
}

// PROSES AJAX CHECK UNTUK VALIDASI TELEPON REAL-TIME
if (isset($_GET['ajax_check_phone'])) {
    header('Content-Type: application/json');
    $telepon = $db->koneksi->real_escape_string($_GET['telepon'] ?? '');
    $id_pasien = isset($_GET['id_pasien']) && $_GET['id_pasien'] ? (int)$_GET['id_pasien'] : null;
    
    $response = ['exists' => false];
    
    if (!empty($telepon)) {
        $query = "SELECT COUNT(*) as count FROM data_pasien WHERE telepon_pasien = '$telepon'";
        if ($id_pasien) {
            $query .= " AND id_pasien != $id_pasien";
        }
        $result = $db->koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['exists'] = $row['count'] > 0;
        }
    }
    
    echo json_encode($response);
    exit();
}

// PROSES AJAX CHECK UNTUK VALIDASI NIK REAL-TIME
if (isset($_GET['ajax_check_nik'])) {
    header('Content-Type: application/json');
    $nik = $db->koneksi->real_escape_string($_GET['nik'] ?? '');
    $id_pasien = isset($_GET['id_pasien']) && $_GET['id_pasien'] ? (int)$_GET['id_pasien'] : null;
    
    $response = ['exists' => false];
    
    if (!empty($nik)) {
        $query = "SELECT COUNT(*) as count FROM data_pasien WHERE nik = '$nik'";
        if ($id_pasien) {
            $query .= " AND id_pasien != $id_pasien";
        }
        $result = $db->koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['exists'] = $row['count'] > 0;
        }
    }
    
    echo json_encode($response);
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data pasien
$all_pasien = $db->tampil_data_pasien();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_pasien = [];
    foreach ($all_pasien as $pasien) {
        // Cari di semua kolom yang relevan
        if (stripos($pasien['id_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['nama_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['nik'] ?? '', $search_query) !== false ||
            stripos($pasien['jenis_kelamin_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['tgl_lahir_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['alamat_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['telepon_pasien'] ?? '', $search_query) !== false ||
            stripos($pasien['tanggal_registrasi_pasien'] ?? '', $search_query) !== false) {
            $filtered_pasien[] = $pasien;
        }
    }
    $all_pasien = $filtered_pasien;
}

// Urutkan data berdasarkan ID Pasien
if ($sort_order === 'desc') {
    // Urutkan dari ID terbesar ke terkecil (terakhir ke terawal)
    usort($all_pasien, function($a, $b) {
        return ($b['id_pasien'] ?? 0) - ($a['id_pasien'] ?? 0);
    });
} else {
    // Urutkan dari ID terkecil ke terbesar (terawal ke terakhir) - default
    usort($all_pasien, function($a, $b) {
        return ($a['id_pasien'] ?? 0) - ($b['id_pasien'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_pasien);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_pasien = array_slice($all_pasien, $offset, $entries_per_page);

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

// Cek apakah data pasien kosong untuk memicu modal
$is_data_empty = empty($data_pasien);
?>

<!DOCTYPE html>
<html lang="en">
  <!-- [Head] start -->
  <head>
    <title>Pasien - Sistem Informasi Poliklinik Mata Eyethica</title>
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
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <!-- [Font] Family -->
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

    <!-- Tambahkan jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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

    /* Tambahan style untuk validasi telepon */
    .invalid-feedback {
        display: none;
        font-size: 0.875rem;
    }

    .valid-feedback {
        display: none;
        font-size: 0.875rem;
    }

    /* Styling untuk validasi */
    .form-text.text-danger {
        font-weight: 500;
        padding: 5px;
        border-radius: 3px;
        background-color: rgba(220, 53, 69, 0.1);
    }

    .form-text.text-success {
        font-weight: 500;
        padding: 5px;
        border-radius: 3px;
        background-color: rgba(25, 135, 84, 0.1);
    }

    /* Validasi styling */
    .is-valid {
        border-color: #198754 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right calc(0.375em + 0.1875rem) center !important;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
    }

    .is-invalid {
        border-color: #dc3545 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right calc(0.375em + 0.1875rem) center !important;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
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
                  <li class="breadcrumb-item" aria-current="page">Data Pasien</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Data Pasien</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="container-fluid">
            <?php if ($notif_message): ?>
            <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert" id="autoDismissAlert">
                <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($notif_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <script>
                // Auto dismiss notifikasi setelah 5 detik dengan animasi
                setTimeout(function() {
                    var alert = document.getElementById('autoDismissAlert');
                    if (alert) {
                        alert.classList.remove('show');
                        setTimeout(function() {
                            if (alert && alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 150);
                        if (typeof bootstrap !== 'undefined') {
                            try {
                                var bsAlert = bootstrap.Alert.getInstance(alert);
                                if (bsAlert) {
                                    bsAlert.close();
                                } else {
                                    bsAlert = new bootstrap.Alert(alert);
                                    bsAlert.close();
                                }
                            } catch (e) {}
                        }
                    }
                }, 5000);
            </script>
            <?php endif; ?>

            <div class="d-flex justify-content-start mb-4">
                <!-- Tombol Tambah Pasien dengan Modal -->
                <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahPasienModal">
                    <i class="fas fa-plus me-1"></i> Tambah Pasien
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
                                           placeholder="Cari data pasien..." 
                                           value="<?= htmlspecialchars($search_query) ?>"
                                           aria-label="Search">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                    <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="datapasien.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger" type="button">
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
                        <table id="pasienTable" class="table table-hover">
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
                                    <th>NIK</th>
                                    <th>Nama Lengkap</th>
                                    <th>JK</th>
                                    <th>Tgl Lahir</th>
                                    <th>Alamat</th>
                                    <th>Telepon</th>
                                    <th>Tgl Registrasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_pasien) && is_array($data_pasien)) {
                                    foreach ($data_pasien as $pasien) {
                                        $id_pasien = htmlspecialchars($pasien['id_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nik = htmlspecialchars($pasien['nik'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nama_pasien = htmlspecialchars($pasien['nama_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $jenis_kelamin_pasien = htmlspecialchars($pasien['jenis_kelamin_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tgl_lahir_pasien = htmlspecialchars($pasien['tgl_lahir_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $alamat_pasien = htmlspecialchars($pasien['alamat_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $telepon_pasien = htmlspecialchars($pasien['telepon_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tanggal_registrasi_pasien = htmlspecialchars($pasien['tanggal_registrasi_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                        
                                        // Format tanggal
                                        $tgl_lahir_formatted = !empty($tgl_lahir_pasien) ? date('d/m/Y', strtotime($tgl_lahir_pasien)) : '-';
                                        $tanggal_registrasi_formatted = !empty($tanggal_registrasi_pasien) ? date('d/m/Y H:i:s', strtotime($tanggal_registrasi_pasien)) : '-';
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_pasien ?></td>
                                        <td><?= $nik ?></td>
                                        <td><?= $nama_pasien ?></td>
                                        <td>
                                            <?php 
                                            if ($jenis_kelamin_pasien == 'L') {
                                                echo 'Laki-laki';
                                            } elseif ($jenis_kelamin_pasien == 'P') {
                                                echo 'Perempuan';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?= $tgl_lahir_formatted ?></td>
                                        <td><?= $alamat_pasien ?></td>
                                        <td><?= $telepon_pasien ?></td>
                                        <td><?= $tanggal_registrasi_formatted ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn btn-warning btn-sm btn-edit"
                                                        data-id="<?= $id_pasien ?>"
                                                        data-nik="<?= $nik ?>"
                                                        data-nama="<?= $nama_pasien ?>"
                                                        data-jenis_kelamin="<?= $jenis_kelamin_pasien ?>"
                                                        data-tgl_lahir="<?= $tgl_lahir_pasien ?>"
                                                        data-alamat="<?= $alamat_pasien ?>"
                                                        data-telepon="<?= $telepon_pasien ?>"
                                                        title="Edit Pasien">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-hapus"
                                                        data-id="<?= $id_pasien ?>"
                                                        data-nama="<?= $nama_pasien ?>"
                                                        title="Hapus Pasien">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $start_number--;
                                        } else {
                                            $start_number++;
                                        }
                                    }
                                } else {
                                    echo '<tr><td colspan="10" class="text-center text-muted">';
                                    if (!empty($search_query)) {
                                        echo 'Tidak ada data pasien yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                    } else {
                                        echo 'Tidak ada data pasien ditemukan.';
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
                                    echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order) . '">1</a>';
                                    echo '</li>';
                                    
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
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Tambah Pasien -->
    <div class="modal fade" id="tambahPasienModal" tabindex="-1" aria-labelledby="tambahPasienModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahPasienModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Tambah Pasien Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/tambah/tambah-data-pasien.php" id="tambahPasienForm">
                    <input type="hidden" name="tambah_pasien" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nik" class="form-label">NIK</label>
                                    <input type="text" class="form-control" id="nik" name="nik" 
                                           placeholder="Masukkan NIK (16 digit angka)"
                                           pattern="[0-9]{16}"
                                           maxlength="16"
                                           minlength="16"
                                           oninput="validateNIK(this, 'tambah')">
                                    <div class="invalid-feedback" id="errorNIKTambah">
                                        NIK harus terdiri dari 16 digit angka
                                    </div>
                                    <div class="valid-feedback" id="successNIKTambah" style="display:none;">
                                        NIK valid
                                    </div>
                                    <div id="duplicateNIKTambah" class="form-text text-danger" style="display:none;">
                                        <i class="fas fa-times-circle"></i> NIK sudah terdaftar untuk pasien lain
                                    </div>
                                    <div class="form-text">NIK harus 16 digit angka (opsional)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nama_pasien" class="form-label">Nama Pasien <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_pasien" name="nama_pasien" required 
                                           placeholder="Masukkan nama pasien">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jenis_kelamin_pasien" class="form-label">Jenis Kelamin</label>
                                    <select class="form-select" id="jenis_kelamin_pasien" name="jenis_kelamin_pasien">
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tgl_lahir_pasien" class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" id="tgl_lahir_pasien" name="tgl_lahir_pasien">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telepon_pasien" class="form-label">Telepon</label>
                                    <input type="text" class="form-control" id="telepon_pasien" name="telepon_pasien" 
                                           placeholder="Masukkan nomor telepon"
                                           pattern="[0-9]*"
                                           minlength="10"
                                           maxlength="13"
                                           oninput="validatePhoneAndCheckDuplicate(this, 'tambah')">
                                    <div class="invalid-feedback" id="errorTeleponTambah">
                                        Hanya boleh memasukkan angka (10-13 digit)
                                    </div>
                                    <div class="valid-feedback" id="successTeleponTambah" style="display:none;">
                                        Nomor telepon valid
                                    </div>
                                    <div id="duplicateTeleponTambah" class="form-text text-danger" style="display:none;">
                                        <i class="fas fa-times-circle"></i> Nomor telepon sudah terdaftar untuk pasien lain
                                    </div>
                                    <div class="form-text">Contoh: 081234567890 (10-13 digit angka, opsional)</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="alamat_pasien" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="alamat_pasien" name="alamat_pasien" 
                                              placeholder="Masukkan alamat" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnTambahPasien">
                            <i class="fas fa-save me-1"></i>Simpan Pasien
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pasien -->
    <div class="modal fade" id="editPasienModal" tabindex="-1" aria-labelledby="editPasienModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPasienModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Pasien
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-pasien.php" id="editPasienForm">
                    <input type="hidden" name="edit_pasien" value="1">
                    <input type="hidden" id="edit_id_pasien" name="id_pasien">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nik" class="form-label">NIK</label>
                                    <input type="text" class="form-control" id="edit_nik" name="nik" 
                                           placeholder="Masukkan NIK (16 digit angka)"
                                           pattern="[0-9]{16}"
                                           maxlength="16"
                                           minlength="16"
                                           oninput="validateNIK(this, 'edit')">
                                    <div class="invalid-feedback" id="errorNIKEdit">
                                        NIK harus terdiri dari 16 digit angka
                                    </div>
                                    <div class="valid-feedback" id="successNIKEdit" style="display:none;">
                                        NIK valid
                                    </div>
                                    <div id="duplicateNIKEdit" class="form-text text-danger" style="display:none;">
                                        <i class="fas fa-times-circle"></i> NIK sudah terdaftar untuk pasien lain
                                    </div>
                                    <div class="form-text">NIK harus 16 digit angka (opsional)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nama_pasien" class="form-label">Nama Pasien <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_nama_pasien" name="nama_pasien" required 
                                           placeholder="Masukkan nama pasien">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jenis_kelamin_pasien" class="form-label">Jenis Kelamin</label>
                                    <select class="form-select" id="edit_jenis_kelamin_pasien" name="jenis_kelamin_pasien">
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_tgl_lahir_pasien" class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" id="edit_tgl_lahir_pasien" name="tgl_lahir_pasien">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telepon_pasien" class="form-label">Telepon</label>
                                    <input type="text" class="form-control" id="edit_telepon_pasien" name="telepon_pasien" 
                                           placeholder="Masukkan nomor telepon"
                                           pattern="[0-9]*"
                                           minlength="10"
                                           maxlength="13"
                                           oninput="validatePhoneAndCheckDuplicate(this, 'edit')">
                                    <div class="invalid-feedback" id="errorTeleponEdit">
                                        Hanya boleh memasukkan angka (10-13 digit)
                                    </div>
                                    <div class="valid-feedback" id="successTeleponEdit" style="display:none;">
                                        Nomor telepon valid
                                    </div>
                                    <div id="duplicateTeleponEdit" class="form-text text-danger" style="display:none;">
                                        <i class="fas fa-times-circle"></i> Nomor telepon sudah terdaftar untuk pasien lain
                                    </div>
                                    <div class="form-text">Contoh: 081234567890 (10-13 digit angka, opsional)</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="edit_alamat_pasien" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="edit_alamat_pasien" name="alamat_pasien" 
                                              placeholder="Masukkan alamat" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdatePasien">
                            <i class="fas fa-save me-1"></i>Update Pasien
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Pasien -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-labelledby="hapusModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    </div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus pasien:</p>
                    <h5 class="text-center text-danger" id="namaPasienHapus"></h5>
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
    <script defer src="https://fomo.codedthemes.com/pixel/CDkpF1sQ8Tt5wpMZgqRvKpQiUhpWE3bc"></script>

    <script>
    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        let url = 'datapasien.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }

    // Function untuk cek duplikat telepon dengan AJAX
    function checkDuplicatePhone(telepon, idPasien = null, callback) {
        if (!telepon || telepon.length < 10) {
            if (callback) callback(false);
            return;
        }
        
        let url = 'datapasien.php?ajax_check_phone=1&telepon=' + encodeURIComponent(telepon);
        if (idPasien) {
            url += '&id_pasien=' + idPasien;
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response.exists);
            },
            error: function() {
                if (callback) callback(false);
            }
        });
    }

    // Function untuk cek duplikat NIK dengan AJAX
    function checkDuplicateNIK(nik, idPasien = null, callback) {
        if (!nik || nik.length !== 16) {
            if (callback) callback(false);
            return;
        }
        
        let url = 'datapasien.php?ajax_check_nik=1&nik=' + encodeURIComponent(nik);
        if (idPasien) {
            url += '&id_pasien=' + idPasien;
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response.exists);
            },
            error: function() {
                if (callback) callback(false);
            }
        });
    }

    // Function untuk validasi NIK dan cek duplikat
    function validateNIK(input, type) {
        const errorElement = document.getElementById('errorNIK' + type.charAt(0).toUpperCase() + type.slice(1));
        const successElement = document.getElementById('successNIK' + type.charAt(0).toUpperCase() + type.slice(1));
        const duplicateElement = document.getElementById('duplicateNIK' + type.charAt(0).toUpperCase() + type.slice(1));
        
        // Hapus karakter non-angka
        input.value = input.value.replace(/[^0-9]/g, '');
        const nik = input.value;
        const idPasien = type === 'edit' ? document.getElementById('edit_id_pasien').value : null;
        
        // Jika kosong, hilangkan semua validasi (NIK opsional)
        if (nik.length === 0) {
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');
            errorElement.style.display = 'none';
            successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return true;
        }
        
        // Validasi panjang (harus 16 digit)
        if (nik.length !== 16) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorElement.style.display = 'block';
            successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return false;
        }
        
        // Cek duplikat NIK
        checkDuplicateNIK(nik, idPasien, function(exists) {
            if (exists) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorElement.style.display = 'none';
                successElement.style.display = 'none';
                if (duplicateElement) {
                    duplicateElement.style.display = 'block';
                }
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                errorElement.style.display = 'none';
                successElement.style.display = 'block';
                if (duplicateElement) {
                    duplicateElement.style.display = 'none';
                }
            }
        });
        
        return true;
    }

    // Function untuk validasi telepon dan cek duplikat
    function validatePhoneAndCheckDuplicate(input, type) {
        const errorElement = document.getElementById('errorTelepon' + type.charAt(0).toUpperCase() + type.slice(1));
        const successElement = document.getElementById('successTelepon' + type.charAt(0).toUpperCase() + type.slice(1));
        const duplicateElement = document.getElementById('duplicateTelepon' + type.charAt(0).toUpperCase() + type.slice(1));
        
        // Hapus karakter non-angka
        input.value = input.value.replace(/[^0-9]/g, '');
        const telepon = input.value;
        const idPasien = type === 'edit' ? document.getElementById('edit_id_pasien').value : null;
        
        // Jika kosong, hilangkan semua validasi (telepon opsional)
        if (telepon.length === 0) {
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');
            errorElement.style.display = 'none';
            successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return true;
        }
        
        // Validasi panjang
        if (telepon.length < 10 || telepon.length > 13) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorElement.style.display = 'block';
            successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return false;
        }
        
        // Cek duplikat
        checkDuplicatePhone(telepon, idPasien, function(exists) {
            if (exists) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorElement.style.display = 'none';
                successElement.style.display = 'none';
                if (duplicateElement) {
                    duplicateElement.style.display = 'block';
                }
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                errorElement.style.display = 'none';
                successElement.style.display = 'block';
                if (duplicateElement) {
                    duplicateElement.style.display = 'none';
                }
            }
        });
        
        return true;
    }

    // Function untuk menampilkan modal hapus
    function showHapusModal(id, nama) {
        document.getElementById('namaPasienHapus').textContent = nama;
        document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-pasien.php?hapus=' + id;
        
        const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
        hapusModal.show();
    }

    // Function untuk menampilkan modal edit
    function showEditModal(id, nik, nama, jenis_kelamin, tgl_lahir, alamat, telepon) {
        document.getElementById('edit_id_pasien').value = id;
        document.getElementById('edit_nik').value = nik;
        document.getElementById('edit_nama_pasien').value = nama;
        document.getElementById('edit_jenis_kelamin_pasien').value = jenis_kelamin;
        document.getElementById('edit_tgl_lahir_pasien').value = tgl_lahir;
        document.getElementById('edit_alamat_pasien').value = alamat;
        document.getElementById('edit_telepon_pasien').value = telepon;
        
        // Reset validasi NIK
        const nikInput = document.getElementById('edit_nik');
        nikInput.classList.remove('is-invalid', 'is-valid');
        document.getElementById('errorNIKEdit').style.display = 'none';
        document.getElementById('successNIKEdit').style.display = 'none';
        document.getElementById('duplicateNIKEdit').style.display = 'none';
        
        // Reset validasi Telepon
        const teleponInput = document.getElementById('edit_telepon_pasien');
        teleponInput.classList.remove('is-invalid', 'is-valid');
        document.getElementById('errorTeleponEdit').style.display = 'none';
        document.getElementById('successTeleponEdit').style.display = 'none';
        document.getElementById('duplicateTeleponEdit').style.display = 'none';
        
        // Validate jika ada NIK
        if (nik && nik.length === 16) {
            validateNIK(nikInput, 'edit');
        }
        
        // Validate jika ada telepon
        if (telepon && telepon.length >= 10 && telepon.length <= 13) {
            validatePhoneAndCheckDuplicate(teleponInput, 'edit');
        }
        
        const editModal = new bootstrap.Modal(document.getElementById('editPasienModal'));
        editModal.show();
    }

    // Function untuk handle submit form
    function handleFormSubmit(e, buttonId, formType) {
        e.preventDefault();
        
        const nikInput = document.getElementById(formType === 'tambah' ? 'nik' : 'edit_nik');
        const teleponInput = document.getElementById(formType === 'tambah' ? 'telepon_pasien' : 'edit_telepon_pasien');
        const nik = nikInput.value;
        const telepon = teleponInput.value;
        
        // Validasi NIK jika diisi
        if (nik.length > 0 && nik.length !== 16) {
            nikInput.focus();
            return false;
        }
        
        // Validasi telepon jika diisi
        if (telepon.length > 0 && (telepon.length < 10 || telepon.length > 13)) {
            teleponInput.focus();
            return false;
        }
        
        const submitButton = document.getElementById(buttonId);
        
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
        submitButton.disabled = true;
        
        setTimeout(() => {
            e.target.submit();
        }, 300);
    }

    // Setup modal dengan event delegation
    document.addEventListener('DOMContentLoaded', function() {
        // Event delegation untuk tombol hapus
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-hapus')) {
                e.preventDefault();
                const button = e.target.closest('.btn-hapus');
                const id = button.getAttribute('data-id');
                const nama = button.getAttribute('data-nama');
                showHapusModal(id, nama);
            }
        });

        // Event delegation untuk tombol edit
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit')) {
                e.preventDefault();
                const button = e.target.closest('.btn-edit');
                const id = button.getAttribute('data-id');
                const nik = button.getAttribute('data-nik');
                const nama = button.getAttribute('data-nama');
                const jenis_kelamin = button.getAttribute('data-jenis_kelamin');
                const tgl_lahir = button.getAttribute('data-tgl_lahir');
                const alamat = button.getAttribute('data-alamat');
                const telepon = button.getAttribute('data-telepon');
                showEditModal(id, nik, nama, jenis_kelamin, tgl_lahir, alamat, telepon);
            }
        });

        // Event listener untuk form tambah
        const tambahForm = document.getElementById('tambahPasienForm');
        if (tambahForm) {
            tambahForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnTambahPasien', 'tambah');
            });
        }

        // Event listener untuk form edit
        const editForm = document.getElementById('editPasienForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnUpdatePasien', 'edit');
            });
        }

        // Auto focus pada input search
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && '<?= $search_query ?>') {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }

        // Reset form modal ketika ditutup
        const tambahPasienModal = document.getElementById('tambahPasienModal');
        if (tambahPasienModal) {
            tambahPasienModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('tambahPasienForm').reset();
                const submitButton = document.getElementById('btnTambahPasien');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Simpan Pasien';
                submitButton.disabled = false;
                
                // Reset NIK validation
                const nikInput = document.getElementById('nik');
                nikInput.classList.remove('is-invalid', 'is-valid');
                document.getElementById('errorNIKTambah').style.display = 'none';
                document.getElementById('successNIKTambah').style.display = 'none';
                document.getElementById('duplicateNIKTambah').style.display = 'none';
                
                // Reset Telepon validation
                const teleponInput = document.getElementById('telepon_pasien');
                teleponInput.classList.remove('is-invalid', 'is-valid');
                document.getElementById('errorTeleponTambah').style.display = 'none';
                document.getElementById('successTeleponTambah').style.display = 'none';
                document.getElementById('duplicateTeleponTambah').style.display = 'none';
            });
        }

        const editPasienModal = document.getElementById('editPasienModal');
        if (editPasienModal) {
            editPasienModal.addEventListener('hidden.bs.modal', function () {
                const submitButton = document.getElementById('btnUpdatePasien');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Update Pasien';
                submitButton.disabled = false;
                
                // Reset NIK validation
                const nikInput = document.getElementById('edit_nik');
                nikInput.classList.remove('is-invalid', 'is-valid');
                document.getElementById('errorNIKEdit').style.display = 'none';
                document.getElementById('successNIKEdit').style.display = 'none';
                document.getElementById('duplicateNIKEdit').style.display = 'none';
                
                // Reset Telepon validation
                const teleponInput = document.getElementById('edit_telepon_pasien');
                teleponInput.classList.remove('is-invalid', 'is-valid');
                document.getElementById('errorTeleponEdit').style.display = 'none';
                document.getElementById('successTeleponEdit').style.display = 'none';
                document.getElementById('duplicateTeleponEdit').style.display = 'none';
            });
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

<?php
// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'datapasien.php?';
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
    $url = 'datapasien.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>