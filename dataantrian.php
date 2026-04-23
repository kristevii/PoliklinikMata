<?php
session_start();
require_once "koneksi.php";
$db = new database();

// Validasi akses: Hanya Dokter dan Staff dengan jabatan tertentu yang bisa mengakses halaman ini
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];
$jabatan_user = '';
$kode_dokter_login = ''; // Variabel untuk menyimpan kode dokter yang login

// Ambil data jabatan user jika role adalah Staff
if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = mysqli_query($db->koneksi, $query_staff);
    
    if ($result_staff && mysqli_num_rows($result_staff) > 0) {
        $staff_data = mysqli_fetch_assoc($result_staff);
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

// Jika role adalah Dokter, ambil kode_dokter dari data_dokter
if ($role == 'Dokter' || $role == 'dokter') {
    $query_dokter = "SELECT kode_dokter FROM data_dokter WHERE id_user = '$id_user'";
    $result_dokter = mysqli_query($db->koneksi, $query_dokter);
    
    if ($result_dokter && mysqli_num_rows($result_dokter) > 0) {
        $dokter_data = mysqli_fetch_assoc($result_dokter);
        $kode_dokter_login = $dokter_data['kode_dokter'];
    }
}

// Cek hak akses sesuai header.php: Dokter, Administrasi, dan IT Support yang bisa akses data antrian
if ($role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Administrasi' && 
    $jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data antrian. Hanya Dokter, Administrasi, dan IT Support yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc';
$filter_dokter = isset($_GET['filter_dokter']) ? $_GET['filter_dokter'] : '';

// Ambil data antrian berdasarkan role
if ($role == 'Dokter' || $role == 'dokter') {
    // Dokter hanya melihat antriannya sendiri
    $all_antrian = $db->tampil_data_antrian_by_dokter($kode_dokter_login);
} else {
    // Staff (Administrasi/IT Support) melihat semua
    $all_antrian = $db->tampil_data_antrian();
}

// Ambil data pasien dan dokter untuk dropdown
$all_pasien = $db->tampil_data_pasien();
$all_dokter = $db->tampil_data_dokter();

// Filter data berdasarkan filter_dokter (untuk Staff)
if (!empty($filter_dokter) && ($jabatan_user == 'Administrasi' || $jabatan_user == 'IT Support')) {
    $filtered_by_dokter = [];
    foreach ($all_antrian as $antrian) {
        if ($antrian['kode_dokter'] == $filter_dokter) {
            $filtered_by_dokter[] = $antrian;
        }
    }
    $all_antrian = $filtered_by_dokter;
}

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_antrian = [];
    foreach ($all_antrian as $antrian) {
        if (stripos($antrian['id_antrian'] ?? '', $search_query) !== false ||
            stripos($antrian['nomor_antrian'] ?? '', $search_query) !== false ||
            stripos($antrian['id_pasien'] ?? '', $search_query) !== false ||
            stripos($antrian['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($antrian['jenis_antrian'] ?? '', $search_query) !== false ||
            stripos($antrian['status'] ?? '', $search_query) !== false ||
            stripos($antrian['waktu_daftar'] ?? '', $search_query) !== false) {
            $filtered_antrian[] = $antrian;
        }
    }
    $all_antrian = $filtered_antrian;
}

// Urutkan data berdasarkan ID Antrian
if ($sort_order === 'desc') {
    usort($all_antrian, function($a, $b) {
        return ($b['id_antrian'] ?? 0) - ($a['id_antrian'] ?? 0);
    });
} else {
    usort($all_antrian, function($a, $b) {
        return ($a['id_antrian'] ?? 0) - ($b['id_antrian'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_antrian);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_antrian = array_slice($all_antrian, $offset, $entries_per_page);

if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

// Siapkan data antrian untuk JavaScript (untuk generate nomor otomatis)
$antrian_for_js = [];
foreach ($all_antrian as $ant) {
    $antrian_for_js[] = [
        'nomor_antrian' => $ant['nomor_antrian'],
        'jenis_antrian' => $ant['jenis_antrian'],
        'tanggal_antrian' => $ant['tanggal_antrian'],
        'waktu_daftar' => $ant['waktu_daftar']
    ];
}

// Siapkan data pasien untuk Select2 dengan format ID_PASIEN - NIK - NAMA_PASIEN
$pasien_for_select2 = [];
foreach ($all_pasien as $pasien) {
    $pasien_for_select2[] = [
        'id' => $pasien['id_pasien'],
        'nik' => $pasien['nik'] ?? '',
        'nama' => $pasien['nama_pasien']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Antrian - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
    <link rel="stylesheet" href="assets/fonts/feather.css" >
    <link rel="stylesheet" href="assets/fonts/fontawesome.css" >
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css" >
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
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
    .modal.fade .modal-dialog {
        transform: translate(0, -50px);
        transition: transform 0.3s ease-out;
    }
    .modal.show .modal-dialog {
        transform: none;
    }
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
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
    .btn-hapus, .btn-edit, .btn-cetak {
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
    .btn-cetak:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
    }
    .badge-menunggu { background-color: #ffc107; color: #000; }
    .badge-dijadwalkan { background-color: #fd7e14; color: #fff; }
    .badge-dipanggil { background-color: #17a2b8; color: #fff; }
    .badge-dilayani { background-color: #007bff; color: #fff; }
    .badge-selesai { background-color: #28a745; color: #fff; }
    .badge-batal { background-color: #dc3545; color: #fff; }
    .nomor-antrian {
        font-weight: bold;
        font-size: 1.2em;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 15px;
        border-radius: 25px;
        display: inline-block;
        min-width: 130px;
        text-align: center;
        letter-spacing: 1px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .badge-baru { background-color: #28a745; color: white; }
    .badge-kontrol { background-color: #6c757d; color: white; }
    .today-date {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        display: inline-block;
        font-weight: bold;
        margin-bottom: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .table th { background-color: #f8f9fa; font-weight: 600; border-bottom: 2px solid #dee2e6; }
    .table td { vertical-align: middle; }
    .table-hover tbody tr:hover { background-color: rgba(0, 123, 255, 0.05); }
    .btn-cetak { background-color: #17a2b8; border-color: #17a2b8; color: white; }
    .btn-cetak:hover { background-color: #138496; border-color: #117a8b; color: white; }
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
        border-radius: 6px;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d;
    }
    .select2-results__option {
        padding: 8px 12px;
    }
    .select2-results__option--highlighted {
        background-color: #0d6efd !important;
    }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
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
                                <li class="breadcrumb-item" aria-current="page">Data Antrian</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Antrian</h2>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahAntrianModal">
                        <i class="fas fa-plus me-1"></i> Tambah Antrian
                    </button>
                    
                    <div class="today-date">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?= date('d F Y') ?>
                        <span class="badge bg-light text-dark ms-2">
                            <i class="fas fa-users me-1"></i>
                            <?= $total_entries ?> Antrian
                        </span>
                    </div>
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
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Cari data antrian..." 
                                               value="<?= htmlspecialchars($search_query) ?>"
                                               aria-label="Search">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <?php if (!empty($filter_dokter)): ?>
                                        <input type="hidden" name="filter_dokter" value="<?= htmlspecialchars($filter_dokter) ?>">
                                        <?php endif; ?>
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="dataantrian.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?><?= !empty($filter_dokter) ? '&filter_dokter=' . urlencode($filter_dokter) : '' ?>" class="btn btn-outline-danger" type="button">
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
                            <table id="antrianTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <a href="<?= getSortUrl($sort_order, $entries_per_page, $search_query, $filter_dokter) ?>" class="text-decoration-none text-dark">
                                                No 
                                                <?php if ($sort_order === 'asc'): ?>
                                                    <i class="fas fa-sort-up ms-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-sort-down ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>ID</th>
                                        <th>Nomor Antrian</th>
                                        <th>ID Pasien</th>
                                        <th>Kode Dokter</th>
                                        <th>Jenis Antrian</th>
                                        <th>Tgl Kontrol</th>
                                        <th>Status</th>
                                        <th>Waktu Daftar</th>
                                        <th>Update At</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_antrian) && is_array($data_antrian)) {
                                        foreach ($data_antrian as $antrian) {
                                            $id_antrian = htmlspecialchars($antrian['id_antrian'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $nomor_antrian = htmlspecialchars($antrian['nomor_antrian'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $id_pasien = htmlspecialchars($antrian['id_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $kode_dokter = htmlspecialchars($antrian['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $jenis_antrian = htmlspecialchars($antrian['jenis_antrian'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $tanggal_antrian = $antrian['tanggal_antrian'] ?? null;
                                            $status = htmlspecialchars($antrian['status'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $waktu_daftar = htmlspecialchars($antrian['waktu_daftar'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $update_at = htmlspecialchars($antrian['update_at'] ?? '', ENT_QUOTES, 'UTF-8');
                                            
                                            $waktu_daftar_formatted = !empty($waktu_daftar) ? date('d/m/Y H:i', strtotime($waktu_daftar)) : '-';
                                            $tanggal_antrian_formatted = !empty($tanggal_antrian) ? date('d/m/Y', strtotime($tanggal_antrian)) : '-';
                                            $update_at_formatted = !empty($update_at) ? date('d/m/Y H:i', strtotime($update_at)) : '-';
                                            
                                            $badge_class = 'badge-secondary';
                                            switch ($status) {
                                                case 'Dijadwalkan':
                                                    $badge_class = 'badge-dijadwalkan';
                                                    break;
                                                case 'Menunggu':
                                                    $badge_class = 'badge-menunggu';
                                                    break;
                                                case 'Dipanggil':
                                                    $badge_class = 'badge-dipanggil';
                                                    break;
                                                case 'Dilayani':
                                                    $badge_class = 'badge-dilayani';
                                                    break;
                                                case 'Selesai':
                                                    $badge_class = 'badge-selesai';
                                                    break;
                                                case 'Batal':
                                                    $badge_class = 'badge-batal';
                                                    break;
                                            }
                                            
                                            $jenis_badge_class = 'badge-secondary';
                                            switch ($jenis_antrian) {
                                                case 'Baru':
                                                    $jenis_badge_class = 'badge-baru';
                                                    break;
                                                case 'Kontrol':
                                                    $jenis_badge_class = 'badge-kontrol';
                                                    break;
                                            }
                                    ?>
                                        <tr>
                                            <td><?= $start_number ?></td>
                                            <td><?= $id_antrian ?></td>
                                            <td>
                                                <span class="nomor-antrian">
                                                    <?= $nomor_antrian ?>
                                                </span>
                                            </td>
                                            <td><?= $id_pasien ?></td>
                                            <td><?= !empty($kode_dokter) ? $kode_dokter : '-' ?></td>
                                            <td>
                                                <span class="badge <?= $jenis_badge_class ?>"><?= $jenis_antrian ?></span>
                                            </td>
                                            <td><?= $tanggal_antrian_formatted ?></td>
                                            <td>
                                                <span class="badge <?= $badge_class ?>"><?= $status ?></span>
                                            </td>
                                            <td><?= $waktu_daftar_formatted ?></td>
                                            <td><?= $update_at_formatted ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button"
                                                            class="btn btn-info btn-sm btn-cetak"
                                                            onclick="cetakAntrian('<?= $id_antrian ?>', '<?= $nomor_antrian ?>')"
                                                            title="Cetak Tiket Antrian">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-warning btn-sm btn-edit"
                                                            data-id="<?= $id_antrian ?>"
                                                            data-nomor="<?= $nomor_antrian ?>"
                                                            data-pasien="<?= $id_pasien ?>"
                                                            data-dokter="<?= $kode_dokter ?>"
                                                            data-jenis-antrian="<?= $jenis_antrian ?>"
                                                            data-tanggal-antrian="<?= $tanggal_antrian ?>"
                                                            data-status="<?= $status ?>"
                                                            title="Edit Antrian">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-danger btn-sm btn-hapus"
                                                            data-id="<?= $id_antrian ?>"
                                                            data-nomor="<?= $nomor_antrian ?>"
                                                            title="Hapus Antrian">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                             </td>
                                         </tr>
                                    <?php
                                            if ($sort_order === 'desc') {
                                                $start_number--;
                                            } else {
                                                $start_number++;
                                            }
                                        }
                                    } else {
                                        echo '<tr><td colspan="11" class="text-center text-muted py-4">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data antrian yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else if (!empty($filter_dokter)) {
                                            echo 'Tidak ada data antrian untuk dokter yang dipilih.';
                                        } else if (($role == 'Dokter' || $role == 'dokter') && empty($data_antrian)) {
                                            echo 'Belum ada antrian untuk Anda hari ini.';
                                        } else {
                                            echo 'Tidak ada data antrian ditemukan.';
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
                                    <?php if (!empty($filter_dokter)): ?>
                                    <span class="text-primary">(filter dokter)</span>
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
                                            <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order, $filter_dokter) : '#' ?>">
                                                Sebelumnya
                                            </a>
                                        </li>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order, $filter_dokter) . '">1</a>';
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
                                                echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order, $filter_dokter) . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                        }
                                        
                                        if ($end < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        if ($total_pages > 1) {
                                            echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order, $filter_dokter) . '">' . $total_pages . '</a>';
                                            echo '</li>';
                                        }
                                        ?>
                                        
                                        <!-- Next Page -->
                                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order, $filter_dokter) : '#' ?>">
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

    <!-- Modal Tambah Antrian -->
    <div class="modal fade" id="tambahAntrianModal" tabindex="-1" aria-labelledby="tambahAntrianModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahAntrianModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Antrian Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="nomor-antrian" style="font-size: 1.3em; padding: 10px 20px; margin: 0 auto; display: inline-block;">
                                <span id="preview_nomor_display">-</span>
                            </div>
                            <div class="mt-2 text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Nomor antrian akan digenerate otomatis
                            </div>
                        </div>
                        
                        <form method="POST" action="proses/tambah/tambah-data-antrian.php" id="formTambahAntrian">
                            <input type="hidden" name="tambah_antrian" value="1">
                            <input type="hidden" name="nomor_antrian" id="nomor_antrian_hidden">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenis_antrian" class="form-label">Jenis Antrian <span class="text-danger">*</span></label>
                                        <select class="form-select" id="jenis_antrian" name="jenis_antrian" required onchange="toggleTanggalAntrian()">
                                            <option value="Baru">Baru</option>
                                            <option value="Kontrol">Kontrol</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6" id="tanggal_antrian_container" style="display:none;">
                                    <div class="mb-3">
                                        <label for="tanggal_antrian" class="form-label">Tanggal Kontrol <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="tanggal_antrian" name="tanggal_antrian">
                                        <small class="text-muted">Pilih tanggal dan waktu kontrol pasien</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="id_pasien" class="form-label">Pasien <span class="text-danger">*</span></label>
                                <select class="form-select select2-pasien-tambah" id="id_pasien" name="id_pasien" required style="width: 100%;">
                                    <option value="">-- Cari Pasien (ID / NIK / Nama) --</option>
                                    <?php if (!empty($all_pasien)): ?>
                                        <?php foreach ($all_pasien as $pasien): ?>
                                            <option value="<?= htmlspecialchars($pasien['id_pasien'] ?? '') ?>"
                                                    data-nik="<?= htmlspecialchars($pasien['nik'] ?? '') ?>"
                                                    data-nama="<?= htmlspecialchars($pasien['nama_pasien'] ?? '') ?>">
                                                <?= htmlspecialchars($pasien['id_pasien'] ?? '') ?> - 
                                                <?= htmlspecialchars($pasien['nik'] ?? '') ?> - 
                                                <?= htmlspecialchars($pasien['nama_pasien'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">Tidak ada data pasien</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="kode_dokter" class="form-label">Dokter</label>
                                        <select class="form-select" id="kode_dokter" name="kode_dokter">
                                            <option value="">Pilih Dokter</option>
                                            <?php if (!empty($all_dokter)): ?>
                                                <?php foreach ($all_dokter as $dokter): ?>
                                                    <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                        <?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="">Tidak tersedia</option>
                                            <?php endif; ?>
                                        </select>
                                        <small class="text-muted">Ruang diambil otomatis dari data dokter</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="Dijadwalkan" selected>Dijadwalkan</option>
                                            <option value="Menunggu">Menunggu</option>
                                            <option value="Dipanggil">Dipanggil</option>
                                            <option value="Dilayani">Dilayani</option>
                                            <option value="Selesai">Selesai</option>
                                            <option value="Batal">Batal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Pastikan semua data sudah benar sebelum disimpan.
                            </div>

                            <div class="text-end mt-4">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Batal
                                </button>
                                <button type="submit" class="btn btn-success" id="btnTambahAntrian">
                                    <i class="fas fa-save me-1"></i>Simpan Antrian
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Antrian -->
    <div class="modal fade" id="editAntrianModal" tabindex="-1" aria-labelledby="editAntrianModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAntrianModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Antrian
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-antrian.php" id="editAntrianForm">
                    <input type="hidden" name="edit_antrian" value="1">
                    <input type="hidden" id="edit_id_antrian" name="id_antrian">
                    
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informasi:</strong> Nomor antrian tidak dapat diubah setelah disimpan.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nomor_antrian" class="form-label">Nomor Antrian</label>
                                    <input type="text" class="form-control" id="edit_nomor_antrian" name="nomor_antrian" readonly style="font-weight: bold; background-color: #f8f9fa;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jenis_antrian" class="form-label">Jenis Antrian</label>
                                    <select class="form-select" id="edit_jenis_antrian" name="jenis_antrian" onchange="toggleEditTanggalAntrian()">
                                        <option value="Baru">Baru</option>
                                        <option value="Kontrol">Kontrol</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="edit_tanggal_antrian_container" style="display:none;">
                            <label for="edit_tanggal_antrian" class="form-label">Tanggal Kontrol</label>
                            <input type="date" class="form-control" id="edit_tanggal_antrian" name="tanggal_antrian">
                        </div>

                        <div class="mb-3">
                            <label for="edit_id_pasien" class="form-label">Pasien <span class="text-danger">*</span></label>
                            <select class="form-select select2-pasien-edit" id="edit_id_pasien" name="id_pasien" required style="width: 100%;">
                                <option value="">-- Cari Pasien (ID / NIK / Nama) --</option>
                                <?php if (!empty($all_pasien)): ?>
                                    <?php foreach ($all_pasien as $pasien): ?>
                                        <option value="<?= htmlspecialchars($pasien['id_pasien'] ?? '') ?>"
                                                data-nik="<?= htmlspecialchars($pasien['nik'] ?? '') ?>"
                                                data-nama="<?= htmlspecialchars($pasien['nama_pasien'] ?? '') ?>">
                                            <?= htmlspecialchars($pasien['id_pasien'] ?? '') ?> - 
                                            <?= htmlspecialchars($pasien['nik'] ?? '') ?> - 
                                            <?= htmlspecialchars($pasien['nama_pasien'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Tidak ada data pasien</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_dokter" class="form-label">Dokter</label>
                                    <select class="form-select" id="edit_kode_dokter" name="kode_dokter">
                                        <option value="">Pilih Dokter</option>
                                        <?php if (!empty($all_dokter)): ?>
                                            <?php foreach ($all_dokter as $dokter): ?>
                                                <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>">
                                                    <?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">Tidak tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">Ruang diambil otomatis dari data dokter</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="Dijadwalkan">Dijadwalkan</option>
                                        <option value="Menunggu">Menunggu</option>
                                        <option value="Dipanggil">Dipanggil</option>
                                        <option value="Dilayani">Dilayani</option>
                                        <option value="Selesai">Selesai</option>
                                        <option value="Batal">Batal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateAntrian">
                            <i class="fas fa-save me-1"></i>Update Antrian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Antrian -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus antrian:</p>
                    <h5 class="text-center text-danger" id="nomorAntrianHapus"></h5>
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
        // Data antrian dari PHP untuk generate nomor
        const existingAntrian = <?= json_encode($antrian_for_js) ?>;
        
        // Data pasien untuk Select2 (opsional)
        const pasienData = <?= json_encode($pasien_for_select2) ?>;
        
        // Function untuk mendapatkan nomor antrian terakhir berdasarkan jenis dan tanggal
        function getLastNomorAntrian(jenis, tanggal) {
            let maxNomor = 0;
            const prefix = jenis === 'Baru' ? 'A' : 'K';
            
            for (let antrian of existingAntrian) {
                if (antrian.jenis_antrian !== jenis) continue;
                
                // Cek tanggal sesuai jenis
                let tanggalAntrian = '';
                if (jenis === 'Baru') {
                    tanggalAntrian = antrian.waktu_daftar ? antrian.waktu_daftar.split(' ')[0] : '';
                } else {
                    tanggalAntrian = antrian.tanggal_antrian ? antrian.tanggal_antrian.split(' ')[0] : '';
                }
                
                if (tanggalAntrian === tanggal) {
                    const nomor = antrian.nomor_antrian;
                    const angka = parseInt(nomor.substring(1));
                    if (!isNaN(angka) && angka > maxNomor) {
                        maxNomor = angka;
                    }
                }
            }
            
            return maxNomor;
        }

        // Function untuk generate nomor antrian baru
        function generateNomorAntrian(jenis, tanggal = null) {
            const previewNomor = document.getElementById('preview_nomor_display');
            const hiddenNomor = document.getElementById('nomor_antrian_hidden');
            
            if (!hiddenNomor) {
                console.error('Hidden nomor antrian element not found');
                return;
            }
            
            // Tentukan tanggal yang digunakan
            let tanggalUntukGenerate = '';
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            
            if (jenis === 'Baru') {
                tanggalUntukGenerate = todayStr;
            } else {
                if (!tanggal) {
                    const tanggalInput = document.getElementById('tanggal_antrian');
                    if (tanggalInput && tanggalInput.value) {
                        tanggalUntukGenerate = tanggalInput.value.split('T')[0];
                    } else {
                        tanggalUntukGenerate = todayStr;
                    }
                } else {
                    tanggalUntukGenerate = tanggal.split('T')[0];
                }
            }
            
            // Hitung nomor terakhir dari data yang ada
            const lastNomor = getLastNomorAntrian(jenis, tanggalUntukGenerate);
            const newNumber = lastNomor + 1;
            const prefix = jenis === 'Baru' ? 'A' : 'K';
            const newNomor = prefix + String(newNumber).padStart(3, '0');
            
            // Update preview
            if (previewNomor) previewNomor.innerHTML = newNomor;
            hiddenNomor.value = newNomor;
            console.log(`Generated ${jenis} nomor: ${newNomor} untuk tanggal ${tanggalUntukGenerate} (last: ${lastNomor})`);
        }

        // Function untuk toggle field tanggal antrian
        function toggleTanggalAntrian() {
            const jenisAntrian = document.getElementById('jenis_antrian').value;
            const tanggalContainer = document.getElementById('tanggal_antrian_container');
            const tanggalInput = document.getElementById('tanggal_antrian');
            
            if (jenisAntrian === 'Kontrol') {
                tanggalContainer.style.display = 'block';
                tanggalInput.required = true;
                
                if (!tanggalInput.value) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    tanggalInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
                
                generateNomorAntrian('Kontrol', tanggalInput.value);
                tanggalInput.removeEventListener('change', onTanggalKontrolChange);
                tanggalInput.addEventListener('change', onTanggalKontrolChange);
            } else {
                tanggalContainer.style.display = 'none';
                tanggalInput.required = false;
                generateNomorAntrian('Baru');
            }
        }

        function onTanggalKontrolChange(e) {
            const jenisAntrian = document.getElementById('jenis_antrian').value;
            if (jenisAntrian === 'Kontrol') {
                generateNomorAntrian('Kontrol', e.target.value);
            }
        }

        function toggleEditTanggalAntrian() {
            const jenisAntrian = document.getElementById('edit_jenis_antrian').value;
            const tanggalContainer = document.getElementById('edit_tanggal_antrian_container');
            
            if (jenisAntrian === 'Kontrol') {
                tanggalContainer.style.display = 'block';
            } else {
                tanggalContainer.style.display = 'none';
            }
        }

        function cetakAntrian(id, nomor) {
            const cetakWindow = window.open(`proses/cetak/cetak-antrian.php?id_antrian=${id}`, '_blank');
            if (!cetakWindow || cetakWindow.closed || typeof cetakWindow.closed == 'undefined') {
                alert('Popup blocker mungkin aktif. Silakan izinkan popup untuk halaman ini.');
            }
        }

        function changeEntries() {
            const entries = document.getElementById('entriesPerPage').value;
            const search = '<?= $search_query ?>';
            const sort = '<?= $sort_order ?>';
            const filterDokter = '<?= $filter_dokter ?>';
            let url = 'dataantrian.php?entries=' + entries + '&page=1&sort=' + sort;
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            if (filterDokter) {
                url += '&filter_dokter=' + encodeURIComponent(filterDokter);
            }
            window.location.href = url;
        }

        function showHapusModal(id, nomor) {
            document.getElementById('nomorAntrianHapus').textContent = 'Nomor ' + nomor;
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-antrian.php?hapus=' + id;
            const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
            hapusModal.show();
        }

        function showEditModal(id, nomor, pasien, dokter, jenis_antrian, tanggal_antrian, status) {
            document.getElementById('edit_id_antrian').value = id;
            document.getElementById('edit_nomor_antrian').value = nomor;
            document.getElementById('edit_kode_dokter').value = dokter;
            document.getElementById('edit_jenis_antrian').value = jenis_antrian;
            document.getElementById('edit_status').value = status;
            
            if (pasien && $('#edit_id_pasien').find(`option[value="${pasien}"]`).length > 0) {
                $('#edit_id_pasien').val(pasien).trigger('change');
            }
            
            if (jenis_antrian === 'Kontrol' && tanggal_antrian && tanggal_antrian !== 'null') {
                let tgl = tanggal_antrian;
                if (tgl.includes(' ')) {
                    tgl = tgl.split(' ')[0];
                }
                document.getElementById('edit_tanggal_antrian').value = tgl;
                document.getElementById('edit_tanggal_antrian_container').style.display = 'block';
            } else {
                document.getElementById('edit_tanggal_antrian_container').style.display = 'none';
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editAntrianModal'));
            editModal.show();
        }

        $(document).ready(function() {
            function customMatcher(params, data) {
                if ($.trim(params.term) === '') {
                    return data;
                }
                if (typeof data.text === 'undefined') {
                    return null;
                }
                var originalText = data.text;
                var searchTerm = params.term.toLowerCase();
                if (originalText.toLowerCase().indexOf(searchTerm) > -1) {
                    var modifiedData = $.extend({}, data);
                    var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    modifiedData.text = originalText.replace(regex, '<mark>$1</mark>');
                    return modifiedData;
                }
                return null;
            }
            
            $('.select2-pasien-tambah').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#tambahAntrianModal'),
                placeholder: 'Ketik ID / NIK / Nama Pasien...',
                allowClear: true,
                width: '100%',
                matcher: customMatcher,
                escapeMarkup: function(markup) { return markup; }
            });
            
            $('.select2-pasien-edit').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#editAntrianModal'),
                placeholder: 'Ketik ID / NIK / Nama Pasien...',
                allowClear: true,
                width: '100%',
                matcher: customMatcher,
                escapeMarkup: function(markup) { return markup; }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    showEditModal(
                        this.getAttribute('data-id'),
                        this.getAttribute('data-nomor'),
                        this.getAttribute('data-pasien'),
                        this.getAttribute('data-dokter'),
                        this.getAttribute('data-jenis-antrian'),
                        this.getAttribute('data-tanggal-antrian'),
                        this.getAttribute('data-status')
                    );
                });
            });

            document.querySelectorAll('.btn-hapus').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    showHapusModal(this.getAttribute('data-id'), this.getAttribute('data-nomor'));
                });
            });

            const tambahModal = document.getElementById('tambahAntrianModal');
            if (tambahModal) {
                tambahModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('formTambahAntrian').reset();
                    document.getElementById('tanggal_antrian_container').style.display = 'none';
                    document.getElementById('jenis_antrian').value = 'Baru';
                    $('#id_pasien').val(null).trigger('change');
                    const tanggalInput = document.getElementById('tanggal_antrian');
                    if (tanggalInput) {
                        tanggalInput.removeEventListener('change', onTanggalKontrolChange);
                    }
                    const previewNomor = document.getElementById('preview_nomor_display');
                    if (previewNomor) previewNomor.innerHTML = '-';
                    const hiddenNomor = document.getElementById('nomor_antrian_hidden');
                    if (hiddenNomor) hiddenNomor.value = '';
                });
                
                tambahModal.addEventListener('show.bs.modal', function() {
                    const jenisSelect = document.getElementById('jenis_antrian');
                    jenisSelect.value = 'Baru';
                    toggleTanggalAntrian();
                });
            }

            const formTambah = document.getElementById('formTambahAntrian');
            if (formTambah) {
                formTambah.addEventListener('submit', function(e) {
                    const idPasien = document.getElementById('id_pasien').value;
                    if (!idPasien) {
                        e.preventDefault();
                        alert('Silakan pilih pasien terlebih dahulu!');
                        return false;
                    }
                    const submitBtn = document.getElementById('btnTambahAntrian');
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
                    submitBtn.disabled = true;
                });
            }

            const formEdit = document.getElementById('editAntrianForm');
            if (formEdit) {
                formEdit.addEventListener('submit', function(e) {
                    const idPasien = document.getElementById('edit_id_pasien').value;
                    if (!idPasien) {
                        e.preventDefault();
                        alert('Silakan pilih pasien terlebih dahulu!');
                        return false;
                    }
                    const submitBtn = document.getElementById('btnUpdateAntrian');
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                    submitBtn.disabled = true;
                });
            }
            
            toggleTanggalAntrian();
        });
    </script>
</body>
</html>

<?php
// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc', $filter_dokter = '') {
    $url = 'dataantrian.php?';
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
    
    if (!empty($filter_dokter)) {
        $params[] = 'filter_dokter=' . urlencode($filter_dokter);
    }
    
    return $url . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort, $entries, $search = '', $filter_dokter = '') {
    $url = 'dataantrian.php?';
    $params = [];
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if (!empty($filter_dokter)) {
        $params[] = 'filter_dokter=' . urlencode($filter_dokter);
    }
    
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<?php require_once "footer.php"; ?>