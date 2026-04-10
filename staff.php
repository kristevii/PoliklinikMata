<?php
session_start();
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

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi format tanggal
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

// PROSES AJAX CHECK UNTUK VALIDASI EMAIL DAN TELEPON REAL-TIME (LINTAS TABEL)
if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    $check_type = $_GET['type'] ?? '';
    $value = $db->koneksi->real_escape_string($_GET['value'] ?? '');
    $id_user_ajax = isset($_GET['id_user']) && $_GET['id_user'] ? (int)$_GET['id_user'] : null;
    
    $response = ['exists' => false, 'duplicate_in' => []];
    
    switch ($check_type) {
        case 'email_staff':
            // Cek di tabel staff (kecuali staff yang sedang diedit)
            $query_staff = "SELECT COUNT(*) as count FROM data_staff WHERE email = '$value'";
            if ($id_user_ajax) {
                $query_staff .= " AND id_user != $id_user_ajax";
            }
            $result_staff = $db->koneksi->query($query_staff);
            $staff_count = 0;
            if ($result_staff && $result_staff->num_rows > 0) {
                $row = $result_staff->fetch_assoc();
                $staff_count = $row['count'];
            }
            if ($staff_count > 0) {
                $response['duplicate_in'][] = 'Staff';
            }
            
            // Cek di tabel dokter
            $query_dokter = "SELECT COUNT(*) as count FROM data_dokter WHERE email = '$value'";
            $result_dokter = $db->koneksi->query($query_dokter);
            $dokter_count = 0;
            if ($result_dokter && $result_dokter->num_rows > 0) {
                $row = $result_dokter->fetch_assoc();
                $dokter_count = $row['count'];
            }
            if ($dokter_count > 0) {
                $response['duplicate_in'][] = 'Dokter';
            }
            
            $response['exists'] = ($staff_count > 0 || $dokter_count > 0);
            break;
            
        case 'phone_staff':
            // Cek di tabel staff (kecuali staff yang sedang diedit)
            $query_staff = "SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '$value'";
            if ($id_user_ajax) {
                $query_staff .= " AND id_user != $id_user_ajax";
            }
            $result_staff = $db->koneksi->query($query_staff);
            $staff_count = 0;
            if ($result_staff && $result_staff->num_rows > 0) {
                $row = $result_staff->fetch_assoc();
                $staff_count = $row['count'];
            }
            if ($staff_count > 0) {
                $response['duplicate_in'][] = 'Staff';
            }
            
            // Cek di tabel dokter
            $query_dokter = "SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '$value'";
            $result_dokter = $db->koneksi->query($query_dokter);
            $dokter_count = 0;
            if ($result_dokter && $result_dokter->num_rows > 0) {
                $row = $result_dokter->fetch_assoc();
                $dokter_count = $row['count'];
            }
            if ($dokter_count > 0) {
                $response['duplicate_in'][] = 'Dokter';
            }
            
            $response['exists'] = ($staff_count > 0 || $dokter_count > 0);
            break;
    }
    
    echo json_encode($response);
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data staff
$all_staff = $db->tampil_data_staff();

// Ambil data dokumen dari database
$all_dokumen = $db->tampil_data_dokumen(); 
$all_jenis_dokumen = $db->tampil_jenis_dokumen();

// Buat array dokumen per staff
$dokumen_per_staff = [];
foreach ($all_dokumen as $dokumen) {
    if (!empty($dokumen['kode_staff'])) {
        $kode_staff = $dokumen['kode_staff'];
        if (!isset($dokumen_per_staff[$kode_staff])) {
            $dokumen_per_staff[$kode_staff] = [];
        }
        
        $nama_dokumen = 'Tidak Diketahui';
        foreach ($all_jenis_dokumen as $jenis) {
            if ($jenis['id_dokumen'] == $dokumen['id_dokumen']) {
                $nama_dokumen = $jenis['nama_dokumen'];
                break;
            }
        }
        
        $dokumen_per_staff[$kode_staff][] = [
            'id_data_dokumen' => $dokumen['id_data_dokumen'],
            'id_dokumen' => $dokumen['id_dokumen'],
            'nama_dokumen' => $nama_dokumen,
            'file_dokumen' => $dokumen['file_dokumen'],
            'status' => $dokumen['status']
        ];
    }
}

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_staff = [];
    foreach ($all_staff as $staff) {
        if (stripos($staff['id_user'] ?? '', $search_query) !== false ||
            stripos($staff['kode_staff'] ?? '', $search_query) !== false ||
            stripos($staff['jabatan_staff'] ?? '', $search_query) !== false ||
            stripos($staff['nama_staff'] ?? '', $search_query) !== false ||
            stripos($staff['jenis_kelamin_staff'] ?? '', $search_query) !== false ||
            stripos($staff['tanggal_lahir_staff'] ?? '', $search_query) !== false ||
            stripos($staff['alamat_staff'] ?? '', $search_query) !== false ||
            stripos($staff['email'] ?? '', $search_query) !== false ||
            stripos($staff['telepon_staff'] ?? '', $search_query) !== false) {
            $filtered_staff[] = $staff;
        }
    }
    $all_staff = $filtered_staff;
}

// Urutkan data berdasarkan ID User
if ($sort_order === 'desc') {
    usort($all_staff, function($a, $b) {
        return ($b['id_user'] ?? 0) - ($a['id_user'] ?? 0);
    });
} else {
    usort($all_staff, function($a, $b) {
        return ($a['id_user'] ?? 0) - ($b['id_user'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_staff);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_staff = array_slice($all_staff, $offset, $entries_per_page);

// Hitung nomor urut
if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

// Tampilkan notifikasi jika ada
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Able Pro is a trending dashboard template built with the Bootstrap 5 design framework.">
    <meta name="keywords" content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel">
    <meta name="author" content="Phoenixcoded">

    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
    <link rel="stylesheet" href="assets/fonts/feather.css" >
    <link rel="stylesheet" href="assets/fonts/fontawesome.css" >
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css" >

    <!-- Tambahkan jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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

        .modal-content {
            margin: auto;
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

        .modal.fade .modal-dialog {
            transform: translateY(-50px);
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: translateY(0);
        }

        .btn-hapus, .btn-edit, .btn-detail {
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

        .btn-detail:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .foto-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            margin-top: 10px;
            object-fit: cover;
        }

        .foto-container {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .foto-staff {
            width: 50px !important;
            height: 50px !important;
            object-fit: cover !important;
            border-radius: 50% !important;
            border: 2px solid #dee2e6 !important;
        }

        .foto-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .foto-placeholder i {
            color: #6c757d;
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        /* Style untuk dokumen */
        .badge-berlaku {
            background-color: #28a745;
            color: #fff;
        }

        .badge-tidak-berlaku {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-file {
            background-color: #17a2b8;
            color: #fff;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
            display: inline-block;
        }

        .badge-file:hover {
            overflow: visible;
            white-space: normal;
            word-break: break-all;
            z-index: 1000;
            position: relative;
        }
        
        /* Style untuk modal detail */
        .foto-placeholder-lg {
            width: 200px;
            height: 200px;
            border-radius: 10px;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .foto-placeholder-lg i {
            color: #6c757d;
            font-size: 4rem;
        }
        
        .invalid-feedback {
            display: none;
            font-size: 0.875rem;
        }
        
        .valid-feedback {
            display: none;
            font-size: 0.875rem;
        }
        
        .preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .foto-preview-placeholder {
            width: 150px;
            height: 150px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            background-color: #f8f9fa;
        }
        
        .foto-preview-placeholder i {
            font-size: 2rem;
            color: #adb5bd;
        }
        
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
        
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .foto-preview, .foto-preview-placeholder {
                max-width: 120px;
                max-height: 120px;
            }
        }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    
    <?php include 'header.php'; ?>

    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">Data Staff</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Staff</h2>
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
                                               placeholder="Cari data staff..." 
                                               value="<?= htmlspecialchars($search_query) ?>"
                                               aria-label="Search">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="staff.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger" type="button">
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
                            <table id="staffTable" class="table table-hover">
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
                                        <th>Foto</th>
                                        <th>Kode</th>
                                        <th>Nama Lengkap</th>
                                        <th>Jabatan</th>
                                        <th>JK</th>
                                        <th>Tgl Lahir</th>
                                        <th>Telepon</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_staff) && is_array($data_staff)) {
                                        foreach ($data_staff as $staff) {
                                            $id_user = htmlspecialchars($staff['id_user'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $kode_staff = htmlspecialchars($staff['kode_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $jabatan_staff = htmlspecialchars($staff['jabatan_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $foto_staff = htmlspecialchars($staff['foto_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $nama_staff = htmlspecialchars($staff['nama_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $jenis_kelamin_staff = htmlspecialchars($staff['jenis_kelamin_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $tanggal_lahir_staff = htmlspecialchars($staff['tanggal_lahir_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $alamat_staff = htmlspecialchars($staff['alamat_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $email = htmlspecialchars($staff['email'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $telepon_staff = htmlspecialchars($staff['telepon_staff'] ?? '', ENT_QUOTES, 'UTF-8');
                                            
                                            $tanggal_lahir_formatted = !empty($tanggal_lahir_staff) ? date('d/m/Y', strtotime($tanggal_lahir_staff)) : '-';
                                            
                                            $foto_path = 'image-staff/' . $foto_staff;
                                            $foto_exists = !empty($foto_staff) && file_exists($foto_path);
                                    ?>
                                        <tr>
                                            <td><?= $start_number ?></td>
                                            <td><?= $id_user ?></td>
                                            <td>
                                                <div class="foto-container">
                                                    <?php if ($foto_exists): ?>
                                                        <img src="<?= $foto_path ?>" alt="Foto Staff" class="foto-staff">
                                                    <?php else: ?>
                                                        <div class="foto-placeholder">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= $kode_staff ?></td>
                                            <td><?= $nama_staff ?></td>
                                            <td><?= $jabatan_staff ?></td>
                                            <td>
                                                <?php 
                                                if ($jenis_kelamin_staff == 'L') {
                                                    echo 'Laki-laki';
                                                } elseif ($jenis_kelamin_staff == 'P') {
                                                    echo 'Perempuan';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?= $tanggal_lahir_formatted ?></td>
                                            <td><?= $telepon_staff ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button"
                                                            class="btn btn-info btn-sm btn-detail"
                                                            data-id="<?= $id_user ?>"
                                                            data-kode="<?= $kode_staff ?>"
                                                            data-jabatan="<?= $jabatan_staff ?>"
                                                            data-foto="<?= $foto_staff ?>"
                                                            data-nama="<?= $nama_staff ?>"
                                                            data-jenis_kelamin="<?= $jenis_kelamin_staff ?>"
                                                            data-tanggal_lahir="<?= $tanggal_lahir_staff ?>"
                                                            data-alamat="<?= $alamat_staff ?>"
                                                            data-email="<?= $email ?>"
                                                            data-telepon="<?= $telepon_staff ?>"
                                                            title="Detail Staff">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-warning btn-sm btn-edit"
                                                            data-id="<?= $id_user ?>"
                                                            data-kode="<?= $kode_staff ?>"
                                                            data-jabatan="<?= $jabatan_staff ?>"
                                                            data-foto="<?= $foto_staff ?>"
                                                            data-nama="<?= $nama_staff ?>"
                                                            data-jenis_kelamin="<?= $jenis_kelamin_staff ?>"
                                                            data-tanggal_lahir="<?= $tanggal_lahir_staff ?>"
                                                            data-alamat="<?= $alamat_staff ?>"
                                                            data-email="<?= $email ?>"
                                                            data-telepon="<?= $telepon_staff ?>"
                                                            title="Edit Staff">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-danger btn-sm btn-hapus"
                                                            data-id="<?= $id_user ?>"
                                                            data-nama="<?= $nama_staff ?>"
                                                            title="Hapus Staff">
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
                                        echo '<td><td colspan="11" class="text-center text-muted">';
                                        if (!empty($search_query)) {
                                            echo 'Tidak ada data staff yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                        } else {
                                            echo 'Tidak ada data staff ditemukan.';
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
        </div>
    </div>

    <!-- Modal Edit Staff -->
    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStaffModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Staff
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-staff.php" id="editStaffForm" enctype="multipart/form-data">
                    <input type="hidden" name="edit_staff" value="1">
                    <input type="hidden" id="edit_id_user" name="id_user">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_staff" class="form-label">Kode Staff <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_kode_staff" name="kode_staff" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nama_staff" class="form-label">Nama Staff <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_nama_staff" name="nama_staff" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jabatan_staff" class="form-label">Jabatan</label>
                                    <select class="form-select" id="edit_jabatan_staff" name="jabatan_staff">
                                        <option value="">Pilih Jabatan</option>
                                        <option value="Perawat Spesialis Mata">Perawat Spesialis Mata</option>
                                        <option value="Refaksionis/Optometris">Refaksionis/Optometris</option>
                                        <option value="Teknisi Alat Kesehatan">Teknisi Alat Kesehatan</option>
                                        <option value="Medical Record">Medical Record</option>
                                        <option value="IT Support">IT Support</option>
                                        <option value="Kasir & Billing">Kasir & Billing</option>
                                        <option value="Administrasi">Administrasi</option>
                                        <option value="Cleaning Service">Cleaning Service</option>
                                        <option value="Security">Security</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_foto_staff" class="form-label">Foto Staff</label>
                                    <input type="file" class="form-control" id="edit_foto_staff" name="foto_staff" accept="image/*" onchange="previewImage(this, 'edit')">
                                    <div class="form-text">Format: JPG, PNG, GIF, WEBP. Max: 2MB</div>
                                    <div class="preview-container mt-3">
                                        <div class="foto-preview-placeholder" id="placeholderEdit">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <img class="foto-preview" id="previewEdit" alt="Preview Foto Staff" style="display:none;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_tanggal_lahir_staff" class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" id="edit_tanggal_lahir_staff" name="tanggal_lahir_staff" 
                                           max="<?= date('Y-m-d', strtotime('-1 year')) ?>">
                                    <div class="invalid-feedback" id="errorTanggalLahirEdit">
                                        Tanggal lahir tidak boleh menggunakan tahun saat ini
                                    </div>
                                    <div class="form-text">Tanggal lahir tidak boleh menggunakan tahun <?= date('Y') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jenis_kelamin_staff" class="form-label">Jenis Kelamin</label>
                                    <select class="form-select" id="edit_jenis_kelamin_staff" name="jenis_kelamin_staff">
                                        <option value="">Pilih</option>
                                        <option value="L">Laki - laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" 
                                           placeholder="Masukkan email staff">
                                    <div id="edit-email-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Masukkan email yang valid
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telepon_staff" class="form-label">Telepon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_telepon_staff" name="telepon_staff" required 
                                        placeholder="Masukkan telepon staff"
                                        pattern="[0-9]*"
                                        minlength="10"
                                        maxlength="15">
                                    <div class="invalid-feedback" id="errorTeleponEdit">
                                        Hanya boleh memasukkan angka (10-15 digit)
                                    </div>
                                    <div class="valid-feedback" id="successTeleponEdit" style="display:none;">
                                        Nomor telepon valid
                                    </div>
                                    <div id="duplicateTeleponEdit" class="form-text text-danger" style="display:none;"></div>
                                    <div class="form-text">Contoh: 081234567890 (10-15 digit angka)</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="edit_alamat_staff" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="edit_alamat_staff" name="alamat_staff" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateStaff">
                            <i class="fas fa-save me-1"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Staff -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus staff:</p>
                    <h5 class="text-center text-danger" id="namaStaffHapus"></h5>
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

    <!-- Modal Detail Staff dengan Dokumen -->
    <div class="modal fade" id="detailStaffModal" tabindex="-1" aria-labelledby="detailStaffModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailStaffModalLabel">
                        <i class="fas fa-user me-2"></i>Detail Staff
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Informasi Staff -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <div id="detailFotoContainer" class="mb-3">
                                                <img src="" alt="Foto Staff" id="detailFotoStaff" class="img-thumbnail rounded-circle" style="width: 200px; height: 200px; object-fit: cover; border-radius: 10px; display:none;">
                                                <div id="detailFotoPlaceholder" class="foto-placeholder-lg">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <h4 id="detailNamaStaff" class="mb-3"></h4>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Kode Staff:</strong> <span id="detailKodeStaff"></span></p>
                                                    <p><strong>Jabatan:</strong> <span id="detailJabatan"></span></p>
                                                    <p><strong>Jenis Kelamin:</strong> <span id="detailJenisKelamin"></span></p>
                                                    <p><strong>Tanggal Lahir:</strong> <span id="detailTanggalLahir"></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Email:</strong> <span id="detailEmail"></span></p>
                                                    <p><strong>Telepon:</strong> <span id="detailTelepon"></span></p>
                                                    <p><strong>Alamat:</strong> <span id="detailAlamat"></span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dokumen Staff -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-alt me-2"></i>Dokumen Staff
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Jenis Dokumen</th>
                                                    <th>File</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detailDokumenList">
                                                <!-- Data dokumen akan diisi oleh JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="noDokumenMessage" class="text-center text-muted py-4 d-none">
                                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                                        <h5>Tidak ada dokumen ditemukan</h5>
                                        <p>Staff ini belum memiliki dokumen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Tutup
                    </button>
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
    var dokumenData = <?php echo json_encode($dokumen_per_staff); ?>;
    var jenisDokumenMap = {
        '1': 'Surat Tanda Registrasi',
        '2': 'Surat Izin Praktik', 
        '3': 'Ijazah & Transkrip',
        '4': 'Sertifikasi Kompetensi',
        '5': 'Sertifikat Pelatihan Khusus',
        '6': 'Sertifikat Kalibrasi K3',
        '7': 'Sertifikat GP/KA Satpam',
        '8': 'Portofolio',
        '9': 'Surat Pengalaman Kerja',
        '10': 'SK Sehat Bebas Narkoba'
    };

    // Function untuk validasi tanggal lahir
    function validateBirthDate(input) {
        if (input.value) {
            const birthYear = new Date(input.value).getFullYear();
            const currentYear = new Date().getFullYear();
            const errorElement = document.getElementById('errorTanggalLahirEdit');
            
            if (birthYear === currentYear) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                if (errorElement) errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                if (errorElement) errorElement.style.display = 'none';
                return true;
            }
        }
        return true;
    }

    // Function untuk cek duplikat email dengan AJAX
    function checkDuplicateEmail(email, idUser, callback) {
        if (!email || email.length < 5 || !email.includes('@')) {
            if (callback) callback({exists: false, duplicate_in: []});
            return;
        }
        
        let url = 'staff.php?ajax_check=1&type=email_staff&value=' + encodeURIComponent(email);
        if (idUser) {
            url += '&id_user=' + idUser;
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response);
            },
            error: function() {
                if (callback) callback({exists: false, duplicate_in: []});
            }
        });
    }

    // Function untuk cek duplikat telepon dengan AJAX
    function checkDuplicatePhone(telepon, idUser, callback) {
        if (!telepon || telepon.length < 10) {
            if (callback) callback({exists: false, duplicate_in: []});
            return;
        }
        
        let url = 'staff.php?ajax_check=1&type=phone_staff&value=' + encodeURIComponent(telepon);
        if (idUser) {
            url += '&id_user=' + idUser;
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response);
            },
            error: function() {
                if (callback) callback({exists: false, duplicate_in: []});
            }
        });
    }

    // Event listener untuk validasi tanggal lahir real-time
    $('#edit_tanggal_lahir_staff').on('change', function() {
        validateBirthDate(this);
    });

    // Validasi email real-time (edit)
    $('#edit_email').on('input', function() {
        var email = $(this).val();
        var idUser = $('#edit_id_user').val();
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email.length > 0 && !emailPattern.test(email)) {
            $('#edit_email').removeClass('is-valid is-invalid');
            $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        if (email.length === 0) {
            $('#edit_email').removeClass('is-valid is-invalid');
            $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        checkDuplicateEmail(email, idUser, function(response) {
            if (response.exists) {
                $('#edit_email').addClass('is-invalid').removeClass('is-valid');
                var duplicateText = '';
                if (response.duplicate_in.length === 1) {
                    duplicateText = 'data ' + response.duplicate_in[0];
                } else if (response.duplicate_in.length === 2) {
                    duplicateText = 'data Staff dan Dokter';
                }
                $('#edit-email-feedback').html('<i class="fas fa-times-circle"></i> Email "' + email + '" sudah terdaftar di ' + duplicateText + '. Silakan gunakan email lain.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#edit_email').addClass('is-valid').removeClass('is-invalid');
                $('#edit-email-feedback').html('<i class="fas fa-check-circle"></i> Email tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });

    // Validasi telepon real-time (edit)
    $('#edit_telepon_staff').on('input', function() {
        var telepon = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(telepon);
        var idUser = $('#edit_id_user').val();
        var errorElement = document.getElementById('errorTeleponEdit');
        var successElement = document.getElementById('successTeleponEdit');
        var duplicateElement = document.getElementById('duplicateTeleponEdit');
        
        if (telepon.length > 0 && (telepon.length < 10 || telepon.length > 15)) {
            $(this).addClass('is-invalid').removeClass('is-valid');
            if (errorElement) errorElement.style.display = 'block';
            if (successElement) successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return;
        } else if (telepon.length === 0) {
            $(this).removeClass('is-invalid is-valid');
            if (errorElement) errorElement.style.display = 'none';
            if (successElement) successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            return;
        }
        
        checkDuplicatePhone(telepon, idUser, function(response) {
            if (response.exists) {
                $('#edit_telepon_staff').addClass('is-invalid').removeClass('is-valid');
                if (errorElement) errorElement.style.display = 'none';
                if (successElement) successElement.style.display = 'none';
                
                var duplicateText = '';
                if (response.duplicate_in.length === 1) {
                    duplicateText = 'data ' + response.duplicate_in[0];
                } else if (response.duplicate_in.length === 2) {
                    duplicateText = 'data Staff dan Dokter';
                }
                if (duplicateElement) {
                    duplicateElement.innerHTML = '<i class="fas fa-times-circle"></i> Nomor telepon "' + telepon + '" sudah terdaftar di ' + duplicateText + '. Silakan gunakan nomor telepon lain.';
                    duplicateElement.style.display = 'block';
                }
            } else {
                $('#edit_telepon_staff').removeClass('is-invalid').addClass('is-valid');
                if (errorElement) errorElement.style.display = 'none';
                if (successElement) successElement.style.display = 'block';
                if (duplicateElement) duplicateElement.style.display = 'none';
            }
        });
    });
    </script>

    <script>
        function changeEntries() {
            const entries = document.getElementById('entriesPerPage').value;
            const search = '<?= $search_query ?>';
            const sort = '<?= $sort_order ?>';
            let url = 'staff.php?entries=' + entries + '&page=1&sort=' + sort;
            
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            
            window.location.href = url;
        }
        
        function previewImage(input, type) {
            const preview = document.getElementById('preview' + type.charAt(0).toUpperCase() + type.slice(1));
            const placeholder = document.getElementById('placeholder' + type.charAt(0).toUpperCase() + type.slice(1));
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB');
                    input.value = '';
                    preview.style.display = 'none';
                    placeholder.style.display = 'flex';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file harus JPG, PNG, GIF, atau WEBP');
                    input.value = '';
                    preview.style.display = 'none';
                    placeholder.style.display = 'flex';
                    return;
                }
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        }
        
        function showHapusModal(id, nama) {
            document.getElementById('namaStaffHapus').textContent = nama;
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-staff.php?hapus=' + id;
            
            const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
            hapusModal.show();
        }
        
        function showEditModal(id, kode, jabatan, foto, nama, jenis_kelamin, tanggal_lahir, alamat, email, telepon) {
            document.getElementById('edit_id_user').value = id;
            document.getElementById('edit_kode_staff').value = kode;
            document.getElementById('edit_jabatan_staff').value = jabatan;
            document.getElementById('edit_nama_staff').value = nama;
            document.getElementById('edit_jenis_kelamin_staff').value = jenis_kelamin;
            document.getElementById('edit_tanggal_lahir_staff').value = tanggal_lahir;
            document.getElementById('edit_alamat_staff').value = alamat;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_telepon_staff').value = telepon;
            
            // Reset validasi tanggal lahir
            $('#edit_tanggal_lahir_staff').removeClass('is-valid is-invalid');
            
            // Reset validasi email
            $('#edit_email').removeClass('is-valid is-invalid');
            $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            
            // Reset validasi telepon
            $('#edit_telepon_staff').removeClass('is-valid is-invalid');
            var errorElement = document.getElementById('errorTeleponEdit');
            var successElement = document.getElementById('successTeleponEdit');
            var duplicateElement = document.getElementById('duplicateTeleponEdit');
            if (errorElement) errorElement.style.display = 'none';
            if (successElement) successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            
            // Validasi tanggal lahir setelah diisi
            if (tanggal_lahir) {
                validateBirthDate(document.getElementById('edit_tanggal_lahir_staff'));
            }
            
            const preview = document.getElementById('previewEdit');
            const placeholder = document.getElementById('placeholderEdit');
            
            if (foto) {
                const img = new Image();
                img.onload = function() {
                    preview.src = 'image-staff/' + foto;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                img.onerror = function() {
                    preview.style.display = 'none';
                    placeholder.style.display = 'flex';
                };
                img.src = 'image-staff/' + foto;
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
            }
            
            if (email) {
                $('#edit_email').trigger('input');
            }
            
            if (telepon) {
                $('#edit_telepon_staff').trigger('input');
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
            editModal.show();
        }
        
        function showDetailModal(id, kode, jabatan, foto, nama, jenis_kelamin, tanggal_lahir, alamat, email, telepon) {
            document.getElementById('detailNamaStaff').textContent = nama;
            document.getElementById('detailKodeStaff').textContent = kode;
            document.getElementById('detailJabatan').textContent = jabatan || '-';
            document.getElementById('detailJenisKelamin').textContent = jenis_kelamin === 'L' ? 'Laki-laki' : (jenis_kelamin === 'P' ? 'Perempuan' : '-');
            document.getElementById('detailTanggalLahir').textContent = tanggal_lahir ? formatDate(tanggal_lahir) : '-';
            document.getElementById('detailEmail').textContent = email || '-';
            document.getElementById('detailTelepon').textContent = telepon || '-';
            document.getElementById('detailAlamat').textContent = alamat || '-';
            
            const fotoImg = document.getElementById('detailFotoStaff');
            const fotoPlaceholder = document.getElementById('detailFotoPlaceholder');
            
            if (foto) {
                const img = new Image();
                img.onload = function() {
                    fotoImg.src = 'image-staff/' + foto;
                    fotoImg.style.display = 'block';
                    fotoPlaceholder.style.display = 'none';
                };
                img.onerror = function() {
                    fotoImg.style.display = 'none';
                    fotoPlaceholder.style.display = 'flex';
                };
                img.src = 'image-staff/' + foto;
            } else {
                fotoImg.style.display = 'none';
                fotoPlaceholder.style.display = 'flex';
            }
            
            const dokumenList = document.getElementById('detailDokumenList');
            const noDokumenMessage = document.getElementById('noDokumenMessage');
            
            dokumenList.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat dokumen...</p>
                    </td>
                </tr>
            `;
            
            const detailModal = new bootstrap.Modal(document.getElementById('detailStaffModal'));
            detailModal.show();
            
            setTimeout(() => {
                filterAndDisplayDokumen(kode, dokumenList, noDokumenMessage);
            }, 100);
        }
        
        function filterAndDisplayDokumen(kodeStaff, dokumenList, noDokumenMessage) {
            dokumenList.innerHTML = '';
            
            if (window.dokumenData && window.dokumenData[kodeStaff]) {
                const dokumenStaff = window.dokumenData[kodeStaff];
                
                if (dokumenStaff.length > 0) {
                    dokumenStaff.forEach(dokumen => {
                        const statusClass = dokumen.status === 'Berlaku' ? 'badge-berlaku' : 'badge-tidak-berlaku';
                        const row = document.createElement('tr');
                        
                        row.innerHTML = `
                            <tr>
                                <strong>${dokumen.nama_dokumen}</strong><br>
                                <small class="text-muted">ID: ${dokumen.id_data_dokumen}</small>
                            </td>
                            <td>
                                <span class="badge badge-file" title="${dokumen.file_dokumen}">
                                    <i class="fas fa-file-pdf me-1"></i>${dokumen.file_dokumen}
                                </span>
                            </td>
                            <td>
                                <span class="badge ${statusClass}">
                                    ${dokumen.status === 'Berlaku' ? '<i class="fas fa-check-circle me-1"></i>' : '<i class="fas fa-times-circle me-1"></i>'}
                                    ${dokumen.status}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-view-dokumen" 
                                            data-id="${dokumen.id_dokumen}"
                                            data-file="${dokumen.file_dokumen}"
                                            title="Lihat Dokumen">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-download-dokumen"
                                            data-id="${dokumen.id_dokumen}"
                                            data-file="${dokumen.file_dokumen}"
                                            title="Download Dokumen">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                        dokumenList.appendChild(row);
                    });
                    
                    noDokumenMessage.classList.add('d-none');
                } else {
                    noDokumenMessage.classList.remove('d-none');
                }
            } else {
                noDokumenMessage.classList.remove('d-none');
            }
        }
        
        function formatDate(dateString) {
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return dateString;
                }
                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            } catch (e) {
                return dateString;
            }
        }
        
        function previewCurrentFile(idDokumen, fileName) {
            const folder = getFullFolderNameById(idDokumen);
            const fileUrl = `dokumen/${folder}/${fileName}`;
            window.open(fileUrl, '_blank');
        }
        
        function downloadCurrentFile(idDokumen, fileName) {
            const folder = getFullFolderNameById(idDokumen);
            const fileUrl = `dokumen/${folder}/${fileName}`;
            
            const link = document.createElement('a');
            link.href = fileUrl;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function getFullFolderNameById(idDokumen) {
            const fullFolderMapping = {
                '1': 'surat-tanda-registrasi',
                '2': 'surat-izin-praktik',
                '3': 'ijazah-transkrip',
                '4': 'sertifikasi-kompetensi',
                '5': 'sertifikat-pelatihan-khusus',
                '6': 'sertifikat-kalibrasi-k3',
                '7': 'sertifikat-gp-ka-satpam',
                '8': 'portofolio',
                '9': 'surat-pengalaman-kerja',
                '10': 'sk-sehat-bebas-narkoba'
            };
            
            return fullFolderMapping[idDokumen] || 'dokumen-umum';
        }
        
        function handleFormSubmit(e, buttonId) {
            e.preventDefault();

            const form = e.target.closest('form');
            if (!form) return;

            // Validasi tanggal lahir
            const tanggalLahirInput = document.getElementById('edit_tanggal_lahir_staff');
            if (tanggalLahirInput && tanggalLahirInput.value) {
                if (!validateBirthDate(tanggalLahirInput)) {
                    tanggalLahirInput.focus();
                    return false;
                }
            }

            const teleponInput = document.getElementById('edit_telepon_staff');
            const telepon = teleponInput.value;
            
            if (telepon.length > 0 && (telepon.length < 10 || telepon.length > 15)) {
                teleponInput.focus();
                return false;
            }
            
            const teleponIsValid = teleponInput.classList.contains('is-valid');
            if (telepon.length > 0 && !teleponIsValid && !teleponInput.classList.contains('is-invalid') === false) {
                teleponInput.focus();
                return false;
            }
            
            const emailInput = document.getElementById('edit_email');
            const email = emailInput.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email.length > 0 && !emailPattern.test(email)) {
                emailInput.focus();
                return false;
            }
            
            const emailIsValid = emailInput.classList.contains('is-valid');
            if (email.length > 0 && !emailIsValid && !emailInput.classList.contains('is-invalid') === false) {
                emailInput.focus();
                return false;
            }

            const submitButton = document.getElementById(buttonId);
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
            submitButton.disabled = true;

            setTimeout(() => {
                form.submit();
            }, 300);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-hapus')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-hapus');
                    const id = button.getAttribute('data-id');
                    const nama = button.getAttribute('data-nama');
                    showHapusModal(id, nama);
                }
            });
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-edit')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-edit');
                    const id = button.getAttribute('data-id');
                    const kode = button.getAttribute('data-kode');
                    const jabatan = button.getAttribute('data-jabatan');
                    const foto = button.getAttribute('data-foto');
                    const nama = button.getAttribute('data-nama');
                    const jenis_kelamin = button.getAttribute('data-jenis_kelamin');
                    const tanggal_lahir = button.getAttribute('data-tanggal_lahir');
                    const alamat = button.getAttribute('data-alamat');
                    const email = button.getAttribute('data-email');
                    const telepon = button.getAttribute('data-telepon');
                    
                    showEditModal(id, kode, jabatan, foto, nama, jenis_kelamin, tanggal_lahir, alamat, email, telepon);
                }
            });
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-detail')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-detail');
                    const id = button.getAttribute('data-id');
                    const kode = button.getAttribute('data-kode');
                    const jabatan = button.getAttribute('data-jabatan');
                    const foto = button.getAttribute('data-foto');
                    const nama = button.getAttribute('data-nama');
                    const jenis_kelamin = button.getAttribute('data-jenis_kelamin');
                    const tanggal_lahir = button.getAttribute('data-tanggal_lahir');
                    const alamat = button.getAttribute('data-alamat');
                    const email = button.getAttribute('data-email');
                    const telepon = button.getAttribute('data-telepon');
                    
                    showDetailModal(id, kode, jabatan, foto, nama, jenis_kelamin, tanggal_lahir, alamat, email, telepon);
                }
            });
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-view-dokumen')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-view-dokumen');
                    const idDokumen = button.getAttribute('data-id');
                    const fileName = button.getAttribute('data-file');
                    previewCurrentFile(idDokumen, fileName);
                }
                
                if (e.target.closest('.btn-download-dokumen')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-download-dokumen');
                    const idDokumen = button.getAttribute('data-id');
                    const fileName = button.getAttribute('data-file');
                    downloadCurrentFile(idDokumen, fileName);
                }
            });

            const editForm = document.getElementById('editStaffForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    handleFormSubmit(e, 'btnUpdateStaff');
                });
            }

            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && '<?= $search_query ?>') {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }
        });
    </script>
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
</body>
</html>

<?php
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'staff.php?';
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

function getSortUrl($current_sort) {
    $url = 'staff.php?';
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

<?php require_once "footer.php"; ?>