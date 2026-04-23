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
$kode_dokter_session = ''; // Untuk menyimpan kode dokter yang sedang login

// Ambil data jabatan user jika role adalah Staff
if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = $db->koneksi->query($query_staff);
    
    if ($result_staff && $result_staff->num_rows > 0) {
        $staff_data = $result_staff->fetch_assoc();
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

// Ambil kode_dokter jika role adalah Dokter
if ($role == 'Dokter' || $role == 'dokter') {
    $kode_dokter_session = $_SESSION['kode_dokter'] ?? '';
    
    if (empty($kode_dokter_session)) {
        $query_dokter = "SELECT kode_dokter FROM data_dokter WHERE id_user = '$id_user'";
        $result_dokter = $db->koneksi->query($query_dokter);
        if ($result_dokter && $result_dokter->num_rows > 0) {
            $dokter_data = $result_dokter->fetch_assoc();
            $kode_dokter_session = $dokter_data['kode_dokter'];
            $_SESSION['kode_dokter'] = $kode_dokter_session;
        }
    }
}

// Cek hak akses sesuai header.php: IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang bisa akses data rekam medis
if ($jabatan_user != 'IT Support' && 
    $role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Medical Record') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data rekam medis. Hanya Staff dengan jabatan IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

function formatTanggal($tanggal, $bahasa = 'id') {
    if (empty($tanggal)) return '-';
    $formatter = new IntlDateFormatter($bahasa, IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Jakarta', IntlDateFormatter::GREGORIAN);
    return $formatter->format(new DateTime($tanggal));
}

function getNamaPasienById($id_pasien, $all_pasien) {
    foreach ($all_pasien as $pasien) {
        if ($pasien['id_pasien'] == $id_pasien) return $pasien['nama_pasien'];
    }
    return 'Unknown';
}

// Fungsi untuk mendapatkan data lengkap pasien berdasarkan ID
function getDetailPasienById($id_pasien, $all_pasien) {
    foreach ($all_pasien as $pasien) {
        if ($pasien['id_pasien'] == $id_pasien) return $pasien;
    }
    return null;
}

// Fungsi untuk mendapatkan nama dokter berdasarkan kode dokter
function getNamaDokterByKode($kode_dokter, $all_dokter) {
    foreach ($all_dokter as $dokter) {
        if ($dokter['kode_dokter'] == $kode_dokter) return $dokter['nama_dokter'];
    }
    return 'Unknown';
}

// Fungsi untuk escape JavaScript string
function escapeJsString($str) {
    return str_replace(
        ["\\", "'", '"', "\n", "\r", "\t", "\x08", "\x0c"],
        ["\\\\", "\\'", '\\"', "\\n", "\\r", "\\t", "\\b", "\\f"],
        $str
    );
}

// Fungsi validasi ENUM jenis_kunjungan
function validateJenisKunjungan($value) {
    $validValues = ['Baru', 'Kontrol'];
    $value = trim($value);
    if (in_array($value, $validValues)) {
        return $value;
    }
    return 'Baru'; // Default value
}

if (isset($_GET['hapus'])) {
    $id_rekam = $_GET['hapus'];
    $rekam_data = $db->get_rekam_by_id($id_rekam);

    // Validasi untuk dokter: hanya bisa hapus rekam medis miliknya
    if ($role == 'Dokter' || $role == 'dokter') {
        if ($rekam_data['kode_dokter'] != $kode_dokter_session) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Anda tidak memiliki izin untuk menghapus rekam medis dokter lain.';
            header("Location: datarekammedis.php");
            exit();
        }
    }

    if ($db->hapus_data_rekam($id_rekam)) {
        $username = $_SESSION['username'] ?? 'unknown user';
        $db->tambah_aktivitas_user('Hapus', 'Rekam Medis', "Rekam medis ID '{$rekam_data['id_rekam']}' berhasil dihapus oleh $username.", date('Y-m-d H:i:s'));
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data rekam medis berhasil dihapus.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data rekam medis.';
    }
    header("Location: datarekammedis.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_rekam_medis'])) {
    $id_rekam = $_POST['id_rekam'] ?? '';
    $id_pasien = $_POST['id_pasien'] ?? '';
    $kode_dokter = $_POST['kode_dokter'] ?? '';
    $jenis_kunjungan = validateJenisKunjungan($_POST['jenis_kunjungan'] ?? '');
    $keluhan = $_POST['keluhan'] ?? '';
    $diagnosa = $_POST['diagnosa'] ?? '';
    $catatan = $_POST['catatan'] ?? '';

    if (empty($id_rekam) || empty($id_pasien) || empty($kode_dokter)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field pasien dan dokter wajib diisi!';
        header("Location: datarekammedis.php");
        exit();
    }

    // Validasi untuk dokter: hanya bisa edit rekam medis miliknya
    if ($role == 'Dokter' || $role == 'dokter') {
        $cek_query = "SELECT kode_dokter FROM rekam_medis WHERE id_rekam = '$id_rekam'";
        $cek_result = $db->koneksi->query($cek_query);
        if ($cek_result && $cek_result->num_rows > 0) {
            $rekam_data = $cek_result->fetch_assoc();
            if ($rekam_data['kode_dokter'] != $kode_dokter_session) {
                $_SESSION['notif_status'] = 'error';
                $_SESSION['notif_message'] = 'Anda tidak memiliki izin untuk mengedit rekam medis dokter lain.';
                header("Location: datarekammedis.php");
                exit();
            }
        }
        
        // Jika dokter mencoba mengganti kode_dokter dengan dokter lain, tolak
        if ($kode_dokter != $kode_dokter_session) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Anda hanya dapat mengedit rekam medis dengan kode dokter Anda sendiri.';
            header("Location: datarekammedis.php");
            exit();
        }
    }

    if ($db->edit_data_rekam($id_rekam, $id_pasien, $kode_dokter, $jenis_kunjungan, $keluhan, $diagnosa, $catatan)) {
        $username = $_SESSION['username'] ?? 'unknown user';
        $db->tambah_aktivitas_user('Edit', 'Rekam Medis', "Rekam medis ID '$id_rekam' berhasil diupdate oleh $username.", date('Y-m-d H:i:s'));
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data rekam medis berhasil diupdate.';
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data rekam medis.';
    }
    header("Location: datarekammedis.php");
    exit();
}

// PROSES TAMBAH REKAM MEDIS DENGAN OTOMATIS MEMBUAT TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_rekam_medis'])) {
    $id_pasien = $_POST['id_pasien'] ?? '';
    $kode_dokter = $_POST['kode_dokter'] ?? '';
    $jenis_kunjungan = validateJenisKunjungan($_POST['jenis_kunjungan'] ?? '');
    $tanggal_periksa = $_POST['tanggal_periksa'] ?? date('Y-m-d H:i:s');
    $keluhan = $_POST['keluhan'] ?? '';
    $diagnosa = $_POST['diagnosa'] ?? '';
    $catatan = $_POST['catatan'] ?? '';

    if (empty($id_pasien) || empty($kode_dokter)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Field pasien dan dokter wajib diisi!';
        header("Location: datarekammedis.php");
        exit();
    }

    // Validasi untuk dokter: hanya bisa menambah rekam medis dengan kode dokter sendiri
    if ($role == 'Dokter' || $role == 'dokter') {
        if ($kode_dokter != $kode_dokter_session) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Anda hanya dapat menambah rekam medis dengan kode dokter Anda sendiri.';
            header("Location: datarekammedis.php");
            exit();
        }
    }

    // Tambah data rekam medis dan dapatkan ID-nya
    $id_rekam = $db->tambah_data_rekam($id_pasien, $kode_dokter, $jenis_kunjungan, $tanggal_periksa, $keluhan, $diagnosa, $catatan);
    
    if ($id_rekam) {
        // ========== OTOMATIS BUAT TRANSAKSI DARI REKAM MEDIS ==========
        // Data default untuk transaksi
        $kode_staff = $_SESSION['kode_staff'] ?? null;
        $grand_total = 0;
        $metode_pembayaran = 'Tunai';
        $status_pembayaran = 'Belum Bayar';
        $tanggal_transaksi = date('Y-m-d H:i:s');
        
        $id_transaksi = $db->tambah_data_transaksi_by_rekam(
            $id_rekam,
            $kode_staff,
            $grand_total,
            $metode_pembayaran,
            $status_pembayaran,
            $tanggal_transaksi
        );
        
        if ($id_transaksi) {
            $username = $_SESSION['username'] ?? 'unknown user';
            $db->tambah_aktivitas_user(
                'Tambah', 
                'Rekam Medis & Transaksi', 
                "Rekam medis baru (ID: $id_rekam) dan transaksi otomatis (ID: $id_transaksi) berhasil ditambahkan oleh $username.", 
                date('Y-m-d H:i:s')
            );
            $_SESSION['notif_status'] = 'success';
            $_SESSION['notif_message'] = 'Data rekam medis berhasil ditambahkan dan transaksi otomatis telah dibuat.';
        } else {
            $username = $_SESSION['username'] ?? 'unknown user';
            $db->tambah_aktivitas_user(
                'Tambah', 
                'Rekam Medis', 
                "Rekam medis baru (ID: $id_rekam) berhasil ditambahkan oleh $username, tetapi gagal membuat transaksi otomatis.", 
                date('Y-m-d H:i:s')
            );
            $_SESSION['notif_status'] = 'warning';
            $_SESSION['notif_message'] = 'Data rekam medis berhasil ditambahkan, tetapi gagal membuat transaksi otomatis. Silakan tambah transaksi secara manual di halaman Data Transaksi.';
        }
    } else {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data rekam medis.';
    }
    header("Location: datarekammedis.php");
    exit();
}

$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil data rekam medis dengan filter berdasarkan role
if ($role == 'Dokter' || $role == 'dokter') {
    // Dokter hanya melihat rekam medis dengan kode_dokter miliknya
    $all_rekam_medis = $db->tampil_data_rekam_medis_by_dokter($kode_dokter_session);
} else {
    // Staff melihat semua data
    $all_rekam_medis = $db->tampil_data_rekam_medis();
}

$all_pasien = $db->tampil_data_pasien();
$all_dokter = $db->tampil_data_dokter();

// Siapkan data pasien untuk Select2 dengan format ID - NIK - Nama
$pasien_for_select2 = [];
foreach ($all_pasien as $pasien) {
    $pasien_for_select2[] = [
        'id' => $pasien['id_pasien'],
        'nik' => $pasien['nik'] ?? '',
        'nama' => $pasien['nama_pasien']
    ];
}

if (!empty($search_query)) {
    $filtered = [];
    foreach ($all_rekam_medis as $rekam) {
        if (stripos($rekam['id_rekam'] ?? '', $search_query) !== false ||
            stripos($rekam['id_pasien'] ?? '', $search_query) !== false ||
            stripos($rekam['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($rekam['jenis_kunjungan'] ?? '', $search_query) !== false ||
            stripos($rekam['tanggal_periksa'] ?? '', $search_query) !== false ||
            stripos($rekam['keluhan'] ?? '', $search_query) !== false ||
            stripos($rekam['diagnosa'] ?? '', $search_query) !== false ||
            stripos($rekam['catatan'] ?? '', $search_query) !== false) {
            $filtered[] = $rekam;
        }
    }
    $all_rekam_medis = $filtered;
}

usort($all_rekam_medis, function($a, $b) use ($sort_order) {
    return $sort_order === 'desc' ? ($b['id_rekam'] ?? 0) - ($a['id_rekam'] ?? 0) : ($a['id_rekam'] ?? 0) - ($b['id_rekam'] ?? 0);
});

$total_entries = count($all_rekam_medis);
$total_pages = ceil($total_entries / $entries_per_page);
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $entries_per_page;
$data_rekam_medis = array_slice($all_rekam_medis, $offset, $entries_per_page);
$start_number = $sort_order === 'desc' ? $total_entries - $offset : $offset + 1;

$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Rekam Medis - Sistem Informasi Poliklinik Mata Eyethica</title>
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
        .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
        @media (min-width: 576px) { .modal-dialog { min-height: calc(100% - 3.5rem); } }
        .modal.fade .modal-dialog { transform: translate(0, -50px); transition: transform 0.3s ease-out; }
        .modal.show .modal-dialog { transform: none; }
        .modal-content { margin: auto; border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .modal-backdrop { background-color: rgba(0,0,0,0.5); }
        .modal { backdrop-filter: blur(2px); }
        .modal-header { border-bottom: 1px solid #dee2e6; padding: 1rem 1.5rem; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid #dee2e6; padding: 1rem 1.5rem; }
        .modal-footer .btn { min-width: 80px; }
        .modal-footer .me-auto .btn { margin-right: 5px; }
        .btn-hapus, .btn-edit, .btn-view { transition: all 0.3s ease; }
        .btn-hapus:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(220,53,69,0.3); }
        .btn-edit:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(255,193,7,0.3); }
        .btn-view:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(23,162,184,0.3); }
        .table th { border-top: none; font-weight: 600; }
        .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .jenis-baru { background-color: rgba(40,167,69,0.15); color: #28a745; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .jenis-kontrol { background-color: rgba(23,162,184,0.15); color: #17a2b8; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.875rem; } }
        .readonly-field { background-color: #e9ecef; }
        .detail-icon { min-width: 40px; text-align: center; }
        .info-row { margin-bottom: 12px; }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border-radius: 6px;
        }
        .select2-results__option {
            padding: 8px 12px;
        }
        .select2-results__option--highlighted {
            background-color: #0d6efd !important;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
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
                                <li class="breadcrumb-item" aria-current="page">Data Rekam Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Rekam Medis</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- [ Main Content ] start -->
            <div class="container-fluid">
                <?php if ($notif_message): ?>
                <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert" id="autoDismissAlert">
                    <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : ($notif_status === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle') ?> me-2"></i>
                    <?= htmlspecialchars($notif_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                    setTimeout(function() {
                        var alert = document.getElementById('autoDismissAlert');
                        if (alert && typeof bootstrap !== 'undefined') {
                            try {
                                var bsAlert = bootstrap.Alert.getInstance(alert);
                                if (bsAlert) bsAlert.close();
                                else new bootstrap.Alert(alert).close();
                            } catch(e) { alert.remove(); }
                        } else if (alert) alert.remove();
                    }, 5000);
                </script>
                <?php endif; ?>

                <div class="d-flex justify-content-start mb-4">
                    <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahRekamMedisModal">
                        <i class="fas fa-plus me-1"></i> Tambah Rekam Medis
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari data rekam medis..." value="<?= htmlspecialchars($search_query) ?>" aria-label="Search">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                            <a href="datarekammedis.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
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
                            <table id="rekamMedisTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><a href="<?= getSortUrl($sort_order, $entries_per_page, $search_query) ?>" class="text-decoration-none text-dark">No <?php if ($sort_order === 'asc'): ?><i class="fas fa-sort-up ms-1"></i><?php else: ?><i class="fas fa-sort-down ms-1"></i><?php endif; ?></a></th>
                                        <th>ID</th>
                                        <th>ID Pasien</th>
                                        <th>Nama Pasien</th>
                                        <th>Kode Dokter</th>
                                        <th>Jenis Kunjungan</th>
                                        <th>Tanggal Periksa</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_rekam_medis)): ?>
                                        <?php foreach ($data_rekam_medis as $rekam): 
                                            $id_rekam = htmlspecialchars($rekam['id_rekam'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $id_pasien = htmlspecialchars($rekam['id_pasien'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $kode_dokter = htmlspecialchars($rekam['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $jenis_kunjungan = htmlspecialchars($rekam['jenis_kunjungan'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $tanggal_periksa = htmlspecialchars($rekam['tanggal_periksa'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $tanggal_periksa_formatted = !empty($tanggal_periksa) ? date('d/m/Y H:i:s', strtotime($tanggal_periksa)) : '-';
                                            $nama_pasien = getNamaPasienById($id_pasien, $all_pasien);
                                            $jenis_class = $jenis_kunjungan == 'Baru' ? 'jenis-baru' : 'jenis-kontrol';
                                            
                                            $keluhan = htmlspecialchars($rekam['keluhan'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $diagnosa = htmlspecialchars($rekam['diagnosa'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $catatan = htmlspecialchars($rekam['catatan'] ?? '', ENT_QUOTES, 'UTF-8');
                                            
                                            $js_id_rekam = escapeJsString($id_rekam);
                                            $js_id_pasien = escapeJsString($id_pasien);
                                            $js_nama_pasien = escapeJsString($nama_pasien);
                                            $js_kode_dokter = escapeJsString($kode_dokter);
                                            $js_jenis_kunjungan = escapeJsString($jenis_kunjungan);
                                            $js_tanggal_periksa = escapeJsString($tanggal_periksa);
                                            $js_keluhan = escapeJsString($keluhan);
                                            $js_diagnosa = escapeJsString($diagnosa);
                                            $js_catatan = escapeJsString($catatan);
                                        ?>
                                            <tr>
                                                <td><?= $start_number++ ?></td>
                                                <td><?= $id_rekam ?></td>
                                                <td><?= $id_pasien ?></td>
                                                <td><?= htmlspecialchars($nama_pasien) ?></td>
                                                <td><?= $kode_dokter ?></td>
                                                <td><span class="<?= $jenis_class ?>"><?= $jenis_kunjungan ?></span></td>
                                                <td><?= $tanggal_periksa_formatted ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-info btn-sm btn-view" onclick='showDetailRekamModal("<?= $js_id_rekam ?>", "<?= $js_id_pasien ?>", "<?= $js_nama_pasien ?>", "<?= $js_kode_dokter ?>", "<?= $js_jenis_kunjungan ?>", "<?= $js_tanggal_periksa ?>", "<?= $js_keluhan ?>", "<?= $js_diagnosa ?>", "<?= $js_catatan ?>")' title="Lihat Detail"><i class="fas fa-eye"></i></button>
                                                        <button type="button" class="btn btn-warning btn-sm btn-edit" data-id="<?= $id_rekam ?>" data-pasien="<?= $id_pasien ?>" data-dokter="<?= $kode_dokter ?>" data-jenis="<?= $jenis_kunjungan ?>" data-tanggal_periksa="<?= $tanggal_periksa ?>" data-keluhan="<?= htmlspecialchars($keluhan) ?>" data-diagnosa="<?= htmlspecialchars($diagnosa) ?>" data-catatan="<?= htmlspecialchars($catatan) ?>" title="Edit Rekam Medis"><i class="fas fa-edit"></i></button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-hapus" data-id="<?= $id_rekam ?>" title="Hapus Rekam Medis"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <?= !empty($search_query) ? 'Tidak ada data rekam medis yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"' : 'Tidak ada data rekam medis ditemukan.' ?>
                                            </td>
                                        </tr>
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

    <!-- Modal Tambah Rekam Medis -->
    <div class="modal fade" id="tambahRekamMedisModal" tabindex="-1" aria-labelledby="tambahRekamMedisModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahRekamMedisModalLabel"><i class="fas fa-file-medical me-2"></i>Tambah Rekam Medis Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="datarekammedis.php" id="tambahRekamMedisForm">
                    <input type="hidden" name="tambah_rekam_medis" value="1">
                    <input type="hidden" name="tanggal_periksa" id="tanggal_periksa_hidden" value="<?= date('Y-m-d H:i:s') ?>">
                    <div class="modal-body">
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Setelah rekam medis ditambahkan, sistem akan secara otomatis membuat transaksi dengan ID rekam medis ini.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="id_pasien" class="form-label">Pasien <span class="text-danger">*</span></label>
                                    <select class="form-select select2-pasien-tambah" id="id_pasien" name="id_pasien" required style="width: 100%;">
                                        <option value="">-- Cari Pasien (ID / NIK / Nama) --</option>
                                        <?php foreach ($all_pasien as $pasien): ?>
                                            <option value="<?= htmlspecialchars($pasien['id_pasien']) ?>"
                                                    data-nik="<?= htmlspecialchars($pasien['nik'] ?? '') ?>"
                                                    data-nama="<?= htmlspecialchars($pasien['nama_pasien']) ?>">
                                                <?= htmlspecialchars($pasien['id_pasien']) ?> - 
                                                <?= htmlspecialchars($pasien['nik'] ?? '') ?> - 
                                                <?= htmlspecialchars($pasien['nama_pasien']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kode_dokter" class="form-label">Dokter <span class="text-danger">*</span></label>
                                    <?php if ($role == 'Dokter' || $role == 'dokter'): ?>
                                        <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($kode_dokter_session) ?> - <?= htmlspecialchars(getNamaDokterByKode($kode_dokter_session, $all_dokter)) ?>" readonly disabled>
                                        <input type="hidden" name="kode_dokter" value="<?= htmlspecialchars($kode_dokter_session) ?>">
                                        <small class="text-muted">Dokter terikat dengan akun Anda</small>
                                    <?php else: ?>
                                        <select class="form-select" id="kode_dokter" name="kode_dokter" required>
                                            <option value="">Pilih Dokter</option>
                                            <?php if (!empty($all_dokter)): ?>
                                                <?php foreach ($all_dokter as $dokter): ?>
                                                    <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>"><?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="">Data dokter tidak tersedia</option>
                                            <?php endif; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jenis_kunjungan" class="form-label">Jenis Kunjungan <span class="text-danger">*</span></label>
                                    <select class="form-select" id="jenis_kunjungan" name="jenis_kunjungan" required>
                                        <option value="Baru">Baru</option>
                                        <option value="Kontrol">Kontrol</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Periksa</label>
                                    <input type="text" class="form-control readonly-field" id="tanggal_periksa_display" value="<?= date('d-m-Y H:i:s') ?>" readonly disabled>
                                    <small class="text-muted">Waktu akan terisi otomatis saat simpan</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="keluhan" class="form-label">Keluhan</label>
                                    <textarea class="form-control" id="keluhan" name="keluhan" placeholder="Masukkan keluhan pasien" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="diagnosa" class="form-label">Diagnosa</label>
                                    <textarea class="form-control" id="diagnosa" name="diagnosa" placeholder="Masukkan diagnosa" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="catatan" class="form-label">Catatan</label>
                                    <textarea class="form-control" id="catatan" name="catatan" placeholder="Masukkan catatan tambahan" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnTambahRekamMedis"><i class="fas fa-save me-1"></i>Simpan Rekam Medis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Rekam Medis -->
    <div class="modal fade" id="editRekamMedisModal" tabindex="-1" aria-labelledby="editRekamMedisModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRekamMedisModalLabel"><i class="fas fa-edit me-2"></i>Edit Data Rekam Medis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="datarekammedis.php" id="editRekamMedisForm">
                    <input type="hidden" name="edit_rekam_medis" value="1">
                    <input type="hidden" id="edit_id_rekam" name="id_rekam">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_id_pasien" class="form-label">Pasien <span class="text-danger">*</span></label>
                                    <select class="form-select select2-pasien-edit" id="edit_id_pasien" name="id_pasien" required style="width: 100%;">
                                        <option value="">-- Cari Pasien (ID / NIK / Nama) --</option>
                                        <?php foreach ($all_pasien as $pasien): ?>
                                            <option value="<?= htmlspecialchars($pasien['id_pasien']) ?>"
                                                    data-nik="<?= htmlspecialchars($pasien['nik'] ?? '') ?>"
                                                    data-nama="<?= htmlspecialchars($pasien['nama_pasien']) ?>">
                                                <?= htmlspecialchars($pasien['id_pasien']) ?> - 
                                                <?= htmlspecialchars($pasien['nik'] ?? '') ?> - 
                                                <?= htmlspecialchars($pasien['nama_pasien']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_dokter" class="form-label">Dokter <span class="text-danger">*</span></label>
                                    <?php if ($role == 'Dokter' || $role == 'dokter'): ?>
                                        <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($kode_dokter_session) ?> - <?= htmlspecialchars(getNamaDokterByKode($kode_dokter_session, $all_dokter)) ?>" readonly disabled>
                                        <input type="hidden" name="kode_dokter" value="<?= htmlspecialchars($kode_dokter_session) ?>">
                                        <small class="text-muted">Dokter terikat dengan akun Anda (tidak dapat diubah)</small>
                                    <?php else: ?>
                                        <select class="form-select" id="edit_kode_dokter" name="kode_dokter" required>
                                            <option value="">Pilih Dokter</option>
                                            <?php if (!empty($all_dokter)): ?>
                                                <?php foreach ($all_dokter as $dokter): ?>
                                                    <option value="<?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?>"><?= htmlspecialchars($dokter['kode_dokter'] ?? '') ?> - <?= htmlspecialchars($dokter['nama_dokter'] ?? '') ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="">Data dokter tidak tersedia</option>
                                            <?php endif; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jenis_kunjungan" class="form-label">Jenis Kunjungan <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_jenis_kunjungan" name="jenis_kunjungan" required>
                                        <option value="Baru">Baru</option>
                                        <option value="Kontrol">Kontrol</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Periksa</label>
                                    <input type="text" class="form-control readonly-field" id="edit_tanggal_periksa_display" readonly disabled>
                                    <small class="text-muted">Tanggal periksa tidak dapat diubah</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="edit_keluhan" class="form-label">Keluhan</label>
                                    <textarea class="form-control" id="edit_keluhan" name="keluhan" placeholder="Masukkan keluhan pasien" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="edit_diagnosa" class="form-label">Diagnosa</label>
                                    <textarea class="form-control" id="edit_diagnosa" name="diagnosa" placeholder="Masukkan diagnosa" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="edit_catatan" class="form-label">Catatan</label>
                                    <textarea class="form-control" id="edit_catatan" name="catatan" placeholder="Masukkan catatan tambahan" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateRekamMedis"><i class="fas fa-save me-1"></i>Update Rekam Medis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-labelledby="hapusModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3"><i class="fas fa-trash-alt text-danger fa-3x mb-3"></i></div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus rekam medis:</p>
                    <h5 class="text-center text-danger" id="idRekamMedisHapus"></h5>
                    <p class="text-center text-muted mt-3"><small>Data yang dihapus tidak dapat dikembalikan.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Rekam Medis -->
    <div class="modal fade" id="detailRekamModal" tabindex="-1" aria-labelledby="detailRekamModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-dark fw-bold" id="detailRekamModalLabel"><i class="fas fa-file-medical me-2"></i>Detail Rekam Medis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1" id="detailIdRekam">-</h4>
                        <div class="text-muted mb-3"><i class="fas fa-calendar me-1"></i><span id="detailTanggalPeriksa">-</span></div>
                    </div>
                    
                    <!-- Informasi Pasien Lengkap -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-user me-2 text-primary"></i>Informasi Pasien</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-user text-muted"></i></div>
                                        <div><small class="text-muted d-block">Nama Pasien</small><span class="fw-medium" id="detailPasienNama">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-address-card text-muted"></i></div>
                                        <div><small class="text-muted d-block">NIK</small><span class="fw-medium" id="detailPasienNik">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-venus-mars text-muted"></i></div>
                                        <div><small class="text-muted d-block">Jenis Kelamin</small><span class="fw-medium" id="detailPasienJenisKelamin">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-calendar-alt text-muted"></i></div>
                                        <div><small class="text-muted d-block">Tanggal Lahir</small><span class="fw-medium" id="detailPasienTglLahir">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-phone text-muted"></i></div>
                                        <div><small class="text-muted d-block">Telepon</small><span class="fw-medium" id="detailPasienTelepon">-</span></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-map-marker-alt text-muted"></i></div>
                                        <div><small class="text-muted d-block">Alamat</small><span class="fw-medium" id="detailPasienAlamat">-</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Dokter -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-user-md me-2 text-primary"></i>Informasi Dokter</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-qrcode text-muted"></i></div>
                                        <div><small class="text-muted d-block">Kode Dokter</small><span class="fw-medium" id="detailKodeDokter">-</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-user-md text-muted"></i></div>
                                        <div><small class="text-muted d-block">Nama Dokter</small><span class="fw-medium" id="detailNamaDokter">-</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Kunjungan -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-clinic-medical me-2 text-primary"></i>Informasi Kunjungan</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light p-2 rounded me-3 detail-icon"><i class="fas fa-tag text-muted"></i></div>
                                        <div><small class="text-muted d-block">Jenis Kunjungan</small><span class="fw-medium" id="detailJenisKunjungan">-</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detail Pemeriksaan -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title fw-semibold mb-3"><i class="fas fa-file-medical-alt me-2 text-primary"></i>Detail Pemeriksaan</h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Keluhan</small>
                                        <div class="p-3 bg-light rounded mt-1" id="detailKeluhan">-</div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Diagnosa</small>
                                        <div class="p-3 bg-light rounded mt-1" id="detailDiagnosa">-</div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Catatan</small>
                                        <div class="p-3 bg-light rounded mt-1" id="detailCatatan">-</div>
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
        // Data pasien dan dokter untuk JavaScript
        var pasienData = <?= json_encode($all_pasien) ?>;
        var dokterData = <?= json_encode($all_dokter) ?>;

        function changeEntries() {
            const entries = document.getElementById('entriesPerPage').value;
            const search = '<?= addslashes($search_query) ?>';
            const sort = '<?= addslashes($sort_order) ?>';
            let url = 'datarekammedis.php?entries=' + entries + '&page=1&sort=' + sort;
            if (search) url += '&search=' + encodeURIComponent(search);
            window.location.href = url;
        }

        function showHapusModal(id) {
            document.getElementById('idRekamMedisHapus').textContent = 'ID ' + id;
            document.getElementById('hapusButton').href = 'datarekammedis.php?hapus=' + id;
            new bootstrap.Modal(document.getElementById('hapusModal')).show();
        }

        function showEditModal(id, pasien, dokter, jenis, tanggal_periksa, keluhan, diagnosa, catatan) {
            document.getElementById('edit_id_rekam').value = id;
            
            <?php if ($role != 'Dokter' && $role != 'dokter'): ?>
            document.getElementById('edit_kode_dokter').value = dokter;
            <?php endif; ?>
            
            document.getElementById('edit_jenis_kunjungan').value = jenis;
            document.getElementById('edit_tanggal_periksa_display').value = tanggal_periksa ? formatTanggal(tanggal_periksa) : '-';
            document.getElementById('edit_keluhan').value = keluhan;
            document.getElementById('edit_diagnosa').value = diagnosa;
            document.getElementById('edit_catatan').value = catatan;
            
            // Set value untuk select2
            if (pasien && $('#edit_id_pasien').find(`option[value="${pasien}"]`).length > 0) {
                $('#edit_id_pasien').val(pasien).trigger('change');
            }
            
            new bootstrap.Modal(document.getElementById('editRekamMedisModal')).show();
        }

        function showDetailRekamModal(id, pasienId, pasienNama, kodeDokter, jenisKunjungan, tanggal_periksa, keluhan, diagnosa, catatan) {
            document.getElementById('detailIdRekam').textContent = 'Rekam Medis #' + id;
            document.getElementById('detailTanggalPeriksa').textContent = tanggal_periksa ? formatTanggal(tanggal_periksa) : '-';
            document.getElementById('detailPasienNama').textContent = pasienNama || '-';
            document.getElementById('detailKodeDokter').textContent = kodeDokter || '-';
            document.getElementById('detailJenisKunjungan').textContent = jenisKunjungan || '-';
            document.getElementById('detailKeluhan').textContent = keluhan || '-';
            document.getElementById('detailDiagnosa').textContent = diagnosa || '-';
            document.getElementById('detailCatatan').textContent = catatan || '-';
            
            // Cari data pasien lengkap
            var pasienLengkap = null;
            for (var i = 0; i < pasienData.length; i++) {
                if (pasienData[i]['id_pasien'] == pasienId) {
                    pasienLengkap = pasienData[i];
                    break;
                }
            }
            
            // Cari nama dokter
            var namaDokter = 'Unknown';
            for (var i = 0; i < dokterData.length; i++) {
                if (dokterData[i]['kode_dokter'] == kodeDokter) {
                    namaDokter = dokterData[i]['nama_dokter'];
                    break;
                }
            }
            
            // Isi data lengkap pasien
            if (pasienLengkap) {
                document.getElementById('detailPasienNik').textContent = pasienLengkap['nik'] || '-';
                var jk = pasienLengkap['jenis_kelamin_pasien'];
                document.getElementById('detailPasienJenisKelamin').textContent = jk == 'L' ? 'Laki-laki' : (jk == 'P' ? 'Perempuan' : '-');
                document.getElementById('detailPasienTglLahir').textContent = pasienLengkap['tgl_lahir_pasien'] ? formatTanggalLahir(pasienLengkap['tgl_lahir_pasien']) : '-';
                document.getElementById('detailPasienTelepon').textContent = pasienLengkap['telepon_pasien'] || '-';
                document.getElementById('detailPasienAlamat').textContent = pasienLengkap['alamat_pasien'] || '-';
            } else {
                document.getElementById('detailPasienNik').textContent = '-';
                document.getElementById('detailPasienJenisKelamin').textContent = '-';
                document.getElementById('detailPasienTglLahir').textContent = '-';
                document.getElementById('detailPasienTelepon').textContent = '-';
                document.getElementById('detailPasienAlamat').textContent = '-';
            }
            
            // Isi nama dokter
            document.getElementById('detailNamaDokter').textContent = namaDokter;
            
            new bootstrap.Modal(document.getElementById('detailRekamModal')).show();
        }

        function formatTanggal(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                return isNaN(date.getTime()) ? dateString : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return dateString;
            }
        }

        function formatTanggalLahir(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                return isNaN(date.getTime()) ? dateString : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
            } catch (e) {
                return dateString;
            }
        }

        function handleFormSubmit(e, buttonId) {
            // Validasi pasien harus dipilih
            const idPasien = buttonId === 'btnTambahRekamMedis' 
                ? document.getElementById('id_pasien').value 
                : document.getElementById('edit_id_pasien').value;
            
            if (!idPasien) {
                e.preventDefault();
                alert('Silakan pilih pasien terlebih dahulu!');
                return false;
            }
            
            const submitButton = document.getElementById(buttonId);
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
            submitButton.disabled = true;
            setTimeout(() => e.target.submit(), 500);
        }

        function handleTambahFormSubmit(e) {
            handleFormSubmit(e, 'btnTambahRekamMedis');
        }

        function handleEditFormSubmit(e) {
            handleFormSubmit(e, 'btnUpdateRekamMedis');
        }

        // Inisialisasi Select2
        $(document).ready(function() {
            // Custom matcher untuk Select2 yang mencari di ID, NIK, dan Nama
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
            
            // Untuk modal tambah
            $('.select2-pasien-tambah').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#tambahRekamMedisModal'),
                placeholder: 'Ketik ID / NIK / Nama Pasien...',
                allowClear: true,
                width: '100%',
                matcher: customMatcher,
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
            
            // Untuk modal edit
            $('.select2-pasien-edit').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#editRekamMedisModal'),
                placeholder: 'Ketik ID / NIK / Nama Pasien...',
                allowClear: true,
                width: '100%',
                matcher: customMatcher,
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-hapus')) {
                    e.preventDefault();
                    showHapusModal(e.target.closest('.btn-hapus').getAttribute('data-id'));
                }
                if (e.target.closest('.btn-edit')) {
                    e.preventDefault();
                    const btn = e.target.closest('.btn-edit');
                    showEditModal(
                        btn.getAttribute('data-id'),
                        btn.getAttribute('data-pasien'),
                        btn.getAttribute('data-dokter'),
                        btn.getAttribute('data-jenis'),
                        btn.getAttribute('data-tanggal_periksa'),
                        btn.getAttribute('data-keluhan'),
                        btn.getAttribute('data-diagnosa'),
                        btn.getAttribute('data-catatan')
                    );
                }
            });

            const tambahForm = document.getElementById('tambahRekamMedisForm');
            if (tambahForm) tambahForm.addEventListener('submit', handleTambahFormSubmit);

            const editForm = document.getElementById('editRekamMedisForm');
            if (editForm) editForm.addEventListener('submit', handleEditFormSubmit);

            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && '<?= addslashes($search_query) ?>') {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }

            const tambahModal = document.getElementById('tambahRekamMedisModal');
            if (tambahModal) {
                tambahModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('tambahRekamMedisForm').reset();
                    $('#id_pasien').val(null).trigger('change');
                    document.getElementById('btnTambahRekamMedis').innerHTML = '<i class="fas fa-save me-1"></i>Simpan Rekam Medis';
                    document.getElementById('btnTambahRekamMedis').disabled = false;
                });
            }

            const editModal = document.getElementById('editRekamMedisModal');
            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function() {
                    $('#edit_id_pasien').val(null).trigger('change');
                    document.getElementById('btnUpdateRekamMedis').innerHTML = '<i class="fas fa-save me-1"></i>Update Rekam Medis';
                    document.getElementById('btnUpdateRekamMedis').disabled = false;
                });
            }
        });
    </script>

    <script>
        change_box_container('false');
        layout_caption_change('true');
        layout_rtl_change('false');
        preset_change("preset-1");
    </script>
</body>

</html>

<?php
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $params = [];
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    return 'datarekammedis.php?' . implode('&', $params);
}

function getSortUrl($current_sort, $entries, $search) {
    $params = [];
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    $params[] = 'sort=' . ($current_sort === 'asc' ? 'desc' : 'asc');
    return 'datarekammedis.php?' . implode('&', $params);
}
?>

<?php require_once "footer.php"; ?>