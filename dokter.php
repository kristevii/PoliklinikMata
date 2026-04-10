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

// PROSES AJAX CHECK UNTUK VALIDASI EMAIL DAN TELEPON REAL-TIME (LINTAS TABEL)
if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    $check_type = $_GET['type'] ?? '';
    $value = $db->koneksi->real_escape_string($_GET['value'] ?? '');
    $id_user_ajax = isset($_GET['id_user']) && $_GET['id_user'] ? (int)$_GET['id_user'] : null;
    
    $response = ['exists' => false, 'duplicate_in' => []];
    
    switch ($check_type) {
        case 'email_dokter':
            // Cek di tabel dokter (kecuali dokter yang sedang diedit)
            $query_dokter = "SELECT COUNT(*) as count FROM data_dokter WHERE email = '$value'";
            if ($id_user_ajax) {
                $query_dokter .= " AND id_user != $id_user_ajax";
            }
            $result_dokter = $db->koneksi->query($query_dokter);
            $dokter_count = 0;
            if ($result_dokter && $result_dokter->num_rows > 0) {
                $row = $result_dokter->fetch_assoc();
                $dokter_count = $row['count'];
            }
            if ($dokter_count > 0) {
                $response['duplicate_in'][] = 'Dokter';
            }
            
            // Cek di tabel staff
            $query_staff = "SELECT COUNT(*) as count FROM data_staff WHERE email = '$value'";
            $result_staff = $db->koneksi->query($query_staff);
            $staff_count = 0;
            if ($result_staff && $result_staff->num_rows > 0) {
                $row = $result_staff->fetch_assoc();
                $staff_count = $row['count'];
            }
            if ($staff_count > 0) {
                $response['duplicate_in'][] = 'Staff';
            }
            
            $response['exists'] = ($dokter_count > 0 || $staff_count > 0);
            break;
            
        case 'phone_dokter':
            // Cek di tabel dokter (kecuali dokter yang sedang diedit)
            $query_dokter = "SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '$value'";
            if ($id_user_ajax) {
                $query_dokter .= " AND id_user != $id_user_ajax";
            }
            $result_dokter = $db->koneksi->query($query_dokter);
            $dokter_count = 0;
            if ($result_dokter && $result_dokter->num_rows > 0) {
                $row = $result_dokter->fetch_assoc();
                $dokter_count = $row['count'];
            }
            if ($dokter_count > 0) {
                $response['duplicate_in'][] = 'Dokter';
            }
            
            // Cek di tabel staff
            $query_staff = "SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '$value'";
            $result_staff = $db->koneksi->query($query_staff);
            $staff_count = 0;
            if ($result_staff && $result_staff->num_rows > 0) {
                $row = $result_staff->fetch_assoc();
                $staff_count = $row['count'];
            }
            if ($staff_count > 0) {
                $response['duplicate_in'][] = 'Staff';
            }
            
            $response['exists'] = ($dokter_count > 0 || $staff_count > 0);
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

// Ambil semua data dokter
$all_dokter = $db->tampil_data_dokter();

// Ambil data dokumen dari database
$all_dokumen = $db->tampil_data_dokumen(); 
$all_jenis_dokumen = $db->tampil_jenis_dokumen();

// Buat array dokumen per dokter
$dokumen_per_dokter = [];
foreach ($all_dokumen as $dokumen) {
    if (!empty($dokumen['kode_dokter'])) {
        $kode_dokter = $dokumen['kode_dokter'];
        if (!isset($dokumen_per_dokter[$kode_dokter])) {
            $dokumen_per_dokter[$kode_dokter] = [];
        }
        
        // Cari nama jenis dokumen
        $nama_dokumen = 'Tidak Diketahui';
        foreach ($all_jenis_dokumen as $jenis) {
            if ($jenis['id_dokumen'] == $dokumen['id_dokumen']) {
                $nama_dokumen = $jenis['nama_dokumen'];
                break;
            }
        }
        
        $dokumen_per_dokter[$kode_dokter][] = [
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
    $filtered_dokter = [];
    foreach ($all_dokter as $dokter) {
        if (stripos($dokter['id_user'] ?? '', $search_query) !== false ||
            stripos($dokter['kode_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['subspesialisasi'] ?? '', $search_query) !== false ||
            stripos($dokter['nama_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['tanggal_lahir_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['jenis_kelamin_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['alamat_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['email'] ?? '', $search_query) !== false ||
            stripos($dokter['telepon_dokter'] ?? '', $search_query) !== false ||
            stripos($dokter['ruang'] ?? '', $search_query) !== false) {
            $filtered_dokter[] = $dokter;
        }
    }
    $all_dokter = $filtered_dokter;
}

// Urutkan data berdasarkan ID User
if ($sort_order === 'desc') {
    usort($all_dokter, function($a, $b) {
        return ($b['id_user'] ?? 0) - ($a['id_user'] ?? 0);
    });
} else {
    usort($all_dokter, function($a, $b) {
        return ($a['id_user'] ?? 0) - ($b['id_user'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_dokter);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_dokter = array_slice($all_dokter, $offset, $entries_per_page);

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
        <title>Dokter - Sistem Informasi Poliklinik Mata Eyethica</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="description"
        content="Able Pro is a trending dashboard template built with the Bootstrap 5 design framework. It is available in multiple technologies, including Bootstrap, React, Vue, CodeIgniter, Angular, .NET, and more.">
        <meta name="keywords"
        content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel, react dashboard, angular dashboard">
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

            .foto-dokter {
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
            .btn-detail {
                transition: all 0.3s ease;
            }

            .btn-detail:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
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
                border-radius: 4px;
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
            
            /* Style untuk preview foto container */
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
            
            /* Styling untuk validasi telepon */
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
            
            /* Responsif untuk modal */
            @media (max-width: 768px) {
                .modal-dialog {
                    margin: 0.5rem;
                }
                
                .foto-preview, .foto-preview-placeholder {
                    max-width: 120px;
                    max-height: 120px;
                }
            }

            /* Styling untuk tanggal lahir */
            .date-input-container {
                position: relative;
            }

            .date-input-container .form-control.is-invalid {
                border-color: #dc3545;
            }

            .date-input-container .form-control.is-valid {
                border-color: #198754;
            }
        </style>
    </head>

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
                  <li class="breadcrumb-item" aria-current="page">Data Dokter</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Data Dokter</h2>
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
                                           placeholder="Cari data dokter..." 
                                           value="<?= htmlspecialchars($search_query) ?>"
                                           aria-label="Search">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                    <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="dokter.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger" type="button">
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
                        <table id="dokterTable" class="table table-hover">
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
                                    <th>Subspesialisasi</th>
                                    <th>Tgl Lahir</th>
                                    <th>JK</th>
                                    <th>Telepon</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_dokter) && is_array($data_dokter)) {
                                    foreach ($data_dokter as $dokter) {
                                        $id_user = htmlspecialchars($dokter['id_user'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $kode_dokter = htmlspecialchars($dokter['kode_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $subspesialisasi = htmlspecialchars($dokter['subspesialisasi'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $foto_dokter = htmlspecialchars($dokter['foto_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nama_dokter = htmlspecialchars($dokter['nama_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tanggal_lahir_dokter = htmlspecialchars($dokter['tanggal_lahir_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $jenis_kelamin_dokter = htmlspecialchars($dokter['jenis_kelamin_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $alamat_dokter = htmlspecialchars($dokter['alamat_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $email = htmlspecialchars($dokter['email'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $telepon_dokter = htmlspecialchars($dokter['telepon_dokter'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $ruang = htmlspecialchars($dokter['ruang'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $tanggal_lahir_formatted = !empty($tanggal_lahir_dokter) ? date('d/m/Y', strtotime($tanggal_lahir_dokter)) : '-';
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_user ?></td>
                                        <td>
                                            <div class="foto-container">
                                                <?php if (!empty($foto_dokter) && file_exists('image-dokter/' . $foto_dokter)): ?>
                                                    <img src="image-dokter/<?= $foto_dokter ?>" alt="Foto Dokter" class="foto-dokter">
                                                <?php else: ?>
                                                    <div class="foto-placeholder">
                                                        <i class="fas fa-user-md"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= $kode_dokter ?></td>
                                        <td><?= $nama_dokter ?></td>
                                        <td><?= $subspesialisasi ?></td>
                                        <td><?= $tanggal_lahir_formatted ?></td>
                                        <td>
                                            <?php 
                                            if ($jenis_kelamin_dokter == 'L') {
                                                echo 'Laki - laki';
                                            } elseif ($jenis_kelamin_dokter == 'P') {
                                                echo 'Perempuan';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?= $telepon_dokter ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn btn-info btn-sm btn-detail"
                                                        data-id="<?= $id_user ?>"
                                                        data-kode="<?= $kode_dokter ?>"
                                                        data-spesialisasi="<?= $subspesialisasi ?>"
                                                        data-foto="<?= $foto_dokter ?>"
                                                        data-nama="<?= $nama_dokter ?>"
                                                        data-tanggal_lahir="<?= $tanggal_lahir_dokter ?>"
                                                        data-jenis_kelamin="<?= $jenis_kelamin_dokter ?>"
                                                        data-alamat="<?= $alamat_dokter ?>"
                                                        data-email="<?= $email ?>"
                                                        data-telepon="<?= $telepon_dokter ?>"
                                                        data-ruang="<?= $ruang ?>"
                                                        title="Detail Dokter">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-warning btn-sm btn-edit"
                                                        data-id="<?= $id_user ?>"
                                                        data-kode="<?= $kode_dokter ?>"
                                                        data-spesialisasi="<?= $subspesialisasi ?>"
                                                        data-foto="<?= $foto_dokter ?>"
                                                        data-nama="<?= $nama_dokter ?>"
                                                        data-tanggal_lahir="<?= $tanggal_lahir_dokter ?>"
                                                        data-jenis_kelamin="<?= $jenis_kelamin_dokter ?>"
                                                        data-alamat="<?= $alamat_dokter ?>"
                                                        data-email="<?= $email ?>"
                                                        data-telepon="<?= $telepon_dokter ?>"
                                                        data-ruang="<?= $ruang ?>"
                                                        title="Edit Dokter">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-hapus"
                                                        data-id="<?= $id_user ?>"
                                                        data-nama="<?= $nama_dokter ?>"
                                                        title="Hapus Dokter">
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
                                    echo '<tr><td colspan="12" class="text-center text-muted">';
                                    if (!empty($search_query)) {
                                        echo 'Tidak ada data dokter yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                    } else {
                                        echo 'Tidak ada data dokter ditemukan.';
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
    <!-- [ Main Content ] end -->
    
    <!-- Modal Edit Dokter -->
    <div class="modal fade" id="editDokterModal" tabindex="-1" aria-labelledby="editDokterModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDokterModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Dokter
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="proses/edit/edit-data-dokter.php" id="editDokterForm" enctype="multipart/form-data">
                    <input type="hidden" name="edit_dokter" value="1">
                    <input type="hidden" id="edit_id_user" name="id_user">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kode_dokter" class="form-label">Kode Dokter <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_kode_dokter" name="kode_dokter" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nama_dokter" class="form-label">Nama Dokter <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_nama_dokter" name="nama_dokter" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_subspesialisasi" class="form-label">Subspesialisasi</label>
                                    <select class="form-select" id="edit_subspesialisasi" name="subspesialisasi">
                                        <option value="">Pilih Subspesialisasi</option>
                                        <option value="Vitreo-Retina">Vitreo-Retina</option>
                                        <option value="Glaukoma">Glaukoma</option>
                                        <option value="Katarak & Bedah Refraktif">Katarak & Bedah Refraktif</option>
                                        <option value="Kornea dan Bedah Refraktif">Kornea dan Bedah Refraktif</option>
                                        <option value="Mata Anak (Pediatric Ophthalmology)">Mata Anak (Pediatric Ophthalmology)</option>
                                        <option value="Okuploplastik (Plastik Rekonstruksi)">Okuploplastik (Plastik Rekonstruksi)</option>
                                        <option value="Infeksi dan Imunologi">Infeksi dan Imunologi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_foto_dokter" class="form-label">Foto Dokter</label>
                                    <input type="file" class="form-control" id="edit_foto_dokter" name="foto_dokter" accept="image/*" onchange="previewImage(this, 'edit')">
                                    <div class="form-text">Format: JPG, PNG, GIF, WEBP. Max: 2MB</div>
                                    <div class="preview-container mt-3">
                                        <div class="foto-preview-placeholder" id="placeholderEdit">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <img class="foto-preview" id="previewEdit" alt="Preview Foto Dokter" style="display:none;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Data Pribadi</h6>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_tanggal_lahir_dokter" class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" id="edit_tanggal_lahir_dokter" name="tanggal_lahir_dokter" 
                                           max="<?= date('Y-m-d', strtotime('-1 year')) ?>">
                                    <div class="invalid-feedback" id="errorTanggalLahirEdit">
                                        Tanggal lahir tidak boleh menggunakan tahun saat ini
                                    </div>
                                    <div class="form-text">Tanggal lahir tidak boleh menggunakan tahun <?= date('Y') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_jenis_kelamin_dokter" class="form-label">Jenis Kelamin</label>
                                    <select class="form-select" id="edit_jenis_kelamin_dokter" name="jenis_kelamin_dokter">
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
                                    <input type="email" class="form-control" id="edit_email" name="email" placeholder="Masukkan email dokter">
                                    <div id="edit-email-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Masukkan email yang valid
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telepon_dokter" class="form-label">Telepon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_telepon_dokter" name="telepon_dokter" required 
                                        placeholder="Masukkan telepon dokter"
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
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_alamat_dokter" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="edit_alamat_dokter" name="alamat_dokter" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_ruang" class="form-label">Ruang</label>
                                    <input type="text" class="form-control" id="edit_ruang" name="ruang">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateDokter">
                            <i class="fas fa-save me-1"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Dokter -->
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
                    <p class="text-center">Apakah Anda yakin ingin menghapus dokter:</p>
                    <h5 class="text-center text-danger" id="namaDokterHapus"></h5>
                    <p class="text-center text-muted mt-3">
                        <small>Data yang dihapus tidak dapat dikembalikan.</small>
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>PERINGATAN:</strong> Data dokter akan dihapus beserta semua data terkait!
                    </div>
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

    <!-- Modal Detail Dokter dengan Dokumen -->
    <div class="modal fade" id="detailDokterModal" tabindex="-1" aria-labelledby="detailDokterModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailDokterModalLabel">
                        <i class="fas fa-user-md me-2"></i>Detail Dokter
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Informasi Dokter -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <div id="detailFotoContainer" class="mb-3">
                                                <img src="" alt="Foto Dokter" id="detailFotoDokter" class="img-thumbnail rounded-circle" style="width: 200px; height: 200px; object-fit: cover; border-radius: 10px; display:none;">
                                                <div id="detailFotoPlaceholder" class="foto-placeholder-lg">
                                                    <i class="fas fa-user-md"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <h4 id="detailNamaDokter" class="mb-3"></h4>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Kode Dokter:</strong> <span id="detailKodeDokter"></span></p>
                                                    <p><strong>Subspesialisasi:</strong> <span id="detailSpesialisasi"></span></p>
                                                    <p><strong>Jenis Kelamin:</strong> <span id="detailJenisKelamin"></span></p>
                                                    <p><strong>Tanggal Lahir:</strong> <span id="detailTanggalLahir"></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Email:</strong> <span id="detailEmail"></span></p>
                                                    <p><strong>Telepon:</strong> <span id="detailTelepon"></span></p>
                                                    <p><strong>Ruang:</strong> <span id="detailRuang"></span></p>
                                                    <p><strong>Alamat:</strong> <span id="detailAlamat"></span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dokumen Dokter -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-alt me-2"></i>Dokumen Dokter
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
                                        <p>Dokter ini belum memiliki dokumen</p>
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
    
    <!-- Data dokumen dari PHP ke JavaScript -->
    <script>
    // Data dokumen dari PHP ke JavaScript
    var dokumenData = <?php echo json_encode($dokumen_per_dokter); ?>;
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
    
    // Function untuk cek duplikat email dengan AJAX (return response object)
    function checkDuplicateEmail(email, idUser, callback) {
        if (!email || email.length < 5 || !email.includes('@')) {
            if (callback) callback({exists: false, duplicate_in: []});
            return;
        }
        
        let url = 'dokter.php?ajax_check=1&type=email_dokter&value=' + encodeURIComponent(email);
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
    
    // Function untuk cek duplikat telepon dengan AJAX (return response object)
    function checkDuplicatePhone(telepon, idUser, callback) {
        if (!telepon || telepon.length < 10) {
            if (callback) callback({exists: false, duplicate_in: []});
            return;
        }
        
        let url = 'dokter.php?ajax_check=1&type=phone_dokter&value=' + encodeURIComponent(telepon);
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
    
    // Validasi tanggal lahir real-time
    $('#edit_tanggal_lahir_dokter').on('change', function() {
        validateBirthDate(this);
    });
    
    // Validasi email real-time untuk edit dokter
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
                    duplicateText = 'data Dokter dan Staff';
                }
                $('#edit-email-feedback').html('<i class="fas fa-times-circle"></i> Email "' + email + '" sudah terdaftar di ' + duplicateText + '. Silakan gunakan email lain.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#edit_email').addClass('is-valid').removeClass('is-invalid');
                $('#edit-email-feedback').html('<i class="fas fa-check-circle"></i> Email tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });
    
    // Validasi telepon real-time dengan AJAX
    $('#edit_telepon_dokter').on('input', function() {
        var telepon = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(telepon);
        var idUser = $('#edit_id_user').val();
        var errorElement = document.getElementById('errorTeleponEdit');
        var successElement = document.getElementById('successTeleponEdit');
        var duplicateElement = document.getElementById('duplicateTeleponEdit');
        
        // Validasi panjang (10-15 digit)
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
        
        // Cek duplikat via AJAX
        checkDuplicatePhone(telepon, idUser, function(response) {
            if (response.exists) {
                $('#edit_telepon_dokter').addClass('is-invalid').removeClass('is-valid');
                if (errorElement) errorElement.style.display = 'none';
                if (successElement) successElement.style.display = 'none';
                
                var duplicateText = '';
                if (response.duplicate_in.length === 1) {
                    duplicateText = 'data ' + response.duplicate_in[0];
                } else if (response.duplicate_in.length === 2) {
                    duplicateText = 'data Dokter dan Staff';
                }
                if (duplicateElement) {
                    duplicateElement.innerHTML = '<i class="fas fa-times-circle"></i> Nomor telepon "' + telepon + '" sudah terdaftar di ' + duplicateText + '. Silakan gunakan nomor telepon lain.';
                    duplicateElement.style.display = 'block';
                }
            } else {
                $('#edit_telepon_dokter').removeClass('is-invalid').addClass('is-valid');
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
            let url = 'dokter.php?entries=' + entries + '&page=1&sort=' + sort;
            
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            
            window.location.href = url;
        }
        
        // Function untuk preview image
        function previewImage(input, type) {
            const preview = document.getElementById('preview' + type.charAt(0).toUpperCase() + type.slice(1));
            const placeholder = document.getElementById('placeholder' + type.charAt(0).toUpperCase() + type.slice(1));
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                // Validasi ukuran file (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB');
                    input.value = '';
                    preview.style.display = 'none';
                    placeholder.style.display = 'flex';
                    return;
                }
                
                // Validasi tipe file
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
            document.getElementById('namaDokterHapus').textContent = nama;
            document.getElementById('hapusButton').href = 'proses/hapus/hapus-data-dokter.php?hapus=' + id;
            
            const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
            hapusModal.show();
        }
        
        function showEditModal(id, kode, spesialisasi, foto, nama, tanggal_lahir, jenis_kelamin, alamat, email, telepon, ruang) {
            document.getElementById('edit_id_user').value = id;
            document.getElementById('edit_kode_dokter').value = kode;
            document.getElementById('edit_subspesialisasi').value = spesialisasi;
            document.getElementById('edit_nama_dokter').value = nama;
            document.getElementById('edit_tanggal_lahir_dokter').value = tanggal_lahir;
            document.getElementById('edit_jenis_kelamin_dokter').value = jenis_kelamin;
            document.getElementById('edit_alamat_dokter').value = alamat;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_telepon_dokter').value = telepon;
            document.getElementById('edit_ruang').value = ruang;
            
            // Reset validasi tanggal lahir
            $('#edit_tanggal_lahir_dokter').removeClass('is-valid is-invalid');
            
            // Reset validasi email
            $('#edit_email').removeClass('is-valid is-invalid');
            $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            
            // Reset validasi telepon
            $('#edit_telepon_dokter').removeClass('is-valid is-invalid');
            var errorElement = document.getElementById('errorTeleponEdit');
            var successElement = document.getElementById('successTeleponEdit');
            var duplicateElement = document.getElementById('duplicateTeleponEdit');
            if (errorElement) errorElement.style.display = 'none';
            if (successElement) successElement.style.display = 'none';
            if (duplicateElement) duplicateElement.style.display = 'none';
            
            // Validasi tanggal lahir setelah diisi
            if (tanggal_lahir) {
                validateBirthDate(document.getElementById('edit_tanggal_lahir_dokter'));
            }
            
            // Set preview foto
            const preview = document.getElementById('previewEdit');
            const placeholder = document.getElementById('placeholderEdit');
            
            if (foto) {
                preview.src = 'image-dokter/' + foto;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
            }
            
            // Trigger validasi email jika ada
            if (email) {
                $('#edit_email').trigger('input');
            }
            
            // Trigger validasi telepon jika ada
            if (telepon) {
                $('#edit_telepon_dokter').trigger('input');
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editDokterModal'));
            editModal.show();
        }
        
        // Function untuk menampilkan modal detail
        function showDetailModal(id, kode, spesialisasi, foto, nama, tanggal_lahir, jenis_kelamin, alamat, email, telepon, ruang) {
            // Set informasi dasar dokter
            document.getElementById('detailNamaDokter').textContent = nama;
            document.getElementById('detailKodeDokter').textContent = kode;
            document.getElementById('detailSpesialisasi').textContent = spesialisasi || '-';
            document.getElementById('detailJenisKelamin').textContent = jenis_kelamin === 'L' ? 'Laki-laki' : (jenis_kelamin === 'P' ? 'Perempuan' : '-');
            document.getElementById('detailTanggalLahir').textContent = tanggal_lahir ? formatDate(tanggal_lahir) : '-';
            document.getElementById('detailEmail').textContent = email || '-';
            document.getElementById('detailTelepon').textContent = telepon || '-';
            document.getElementById('detailRuang').textContent = ruang || '-';
            document.getElementById('detailAlamat').textContent = alamat || '-';
            
            // Set foto dokter
            const fotoImg = document.getElementById('detailFotoDokter');
            const fotoPlaceholder = document.getElementById('detailFotoPlaceholder');
            
            if (foto) {
                fotoImg.src = 'image-dokter/' + foto;
                fotoImg.style.display = 'block';
                fotoPlaceholder.style.display = 'none';
            } else {
                fotoImg.style.display = 'none';
                fotoPlaceholder.style.display = 'flex';
            }
            
            // Tampilkan dokumen
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
            
            const detailModal = new bootstrap.Modal(document.getElementById('detailDokterModal'));
            detailModal.show();
            
            setTimeout(() => {
                filterAndDisplayDokumen(kode, dokumenList, noDokumenMessage);
            }, 100);
        }
        
        // Function untuk filter dan tampilkan dokumen
        function filterAndDisplayDokumen(kodeDokter, dokumenList, noDokumenMessage) {
            dokumenList.innerHTML = '';
            
            if (window.dokumenData && window.dokumenData[kodeDokter]) {
                const dokumenDokter = window.dokumenData[kodeDokter];
                
                if (dokumenDokter.length > 0) {
                    dokumenDokter.forEach(dokumen => {
                        const statusClass = dokumen.status === 'Berlaku' ? 'badge-berlaku' : 'badge-tidak-berlaku';
                        const row = document.createElement('tr');
                        
                        row.innerHTML = `
                            <tr>
                                <strong>${dokumen.nama_dokumen}</strong><br>
                                <small class="text-muted">ID: ${dokumen.id_data_dokumen}</small>
                            </td>
                            <td>
                                <span class="badge badge-file file-name-tooltip" title="${dokumen.file_dokumen}">
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
        
        // Function untuk format tanggal
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
        
        // Function untuk melihat file PDF
        function previewCurrentFile(idDokumen, fileName) {
            const folder = getFullFolderNameById(idDokumen);
            const fileUrl = `dokumen/${folder}/${fileName}`;
            window.open(fileUrl, '_blank');
        }
        
        // Function untuk download file PDF
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
        
        // Function untuk mendapatkan nama folder lengkap berdasarkan ID dokumen
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
        
        // Function untuk handle submit form edit
        function handleFormSubmit(e, buttonId) {
            e.preventDefault();

            const form = e.target.closest('form');
            if (!form) return;

            // Validasi tanggal lahir
            const tanggalLahirInput = document.getElementById('edit_tanggal_lahir_dokter');
            if (tanggalLahirInput && tanggalLahirInput.value) {
                if (!validateBirthDate(tanggalLahirInput)) {
                    tanggalLahirInput.focus();
                    return false;
                }
            }

            // Validasi telepon
            const teleponInput = document.getElementById('edit_telepon_dokter');
            const telepon = teleponInput.value;
            
            if (telepon.length > 0 && (telepon.length < 10 || telepon.length > 15)) {
                teleponInput.focus();
                return false;
            }
            
            // Cek apakah telepon sudah terdaftar
            const teleponIsValid = teleponInput.classList.contains('is-valid');
            if (telepon.length > 0 && !teleponIsValid && !teleponInput.classList.contains('is-invalid') === false) {
                teleponInput.focus();
                return false;
            }
            
            // Validasi email
            const emailInput = document.getElementById('edit_email');
            const email = emailInput.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email.length > 0 && !emailPattern.test(email)) {
                emailInput.focus();
                return false;
            }
            
            // Cek apakah email sudah terdaftar
            const emailIsValid = emailInput.classList.contains('is-valid');
            if (email.length > 0 && !emailIsValid && !emailInput.classList.contains('is-invalid') === false) {
                emailInput.focus();
                return false;
            }

            const submitButton = document.getElementById(buttonId);
            const originalText = submitButton.innerHTML;

            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
            submitButton.disabled = true;

            setTimeout(() => {
                form.submit();
            }, 300);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener untuk tombol hapus
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-hapus')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-hapus');
                    const id = button.getAttribute('data-id');
                    const nama = button.getAttribute('data-nama');
                    showHapusModal(id, nama);
                }
            });
            
            // Event listener untuk tombol edit
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-edit')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-edit');
                    const id = button.getAttribute('data-id');
                    const kode = button.getAttribute('data-kode');
                    const spesialisasi = button.getAttribute('data-spesialisasi');
                    const foto = button.getAttribute('data-foto');
                    const nama = button.getAttribute('data-nama');
                    const tanggal_lahir = button.getAttribute('data-tanggal_lahir');
                    const jenis_kelamin = button.getAttribute('data-jenis_kelamin');
                    const alamat = button.getAttribute('data-alamat');
                    const email = button.getAttribute('data-email');
                    const telepon = button.getAttribute('data-telepon');
                    const ruang = button.getAttribute('data-ruang');
                    
                    showEditModal(id, kode, spesialisasi, foto, nama,
                                tanggal_lahir, jenis_kelamin, alamat, email, telepon, ruang);
                }
            });
            
            // Event listener untuk tombol detail
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-detail')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-detail');
                    const id = button.getAttribute('data-id');
                    const kode = button.getAttribute('data-kode');
                    const spesialisasi = button.getAttribute('data-spesialisasi');
                    const foto = button.getAttribute('data-foto');
                    const nama = button.getAttribute('data-nama');
                    const tanggal_lahir = button.getAttribute('data-tanggal_lahir');
                    const jenis_kelamin = button.getAttribute('data-jenis_kelamin');
                    const alamat = button.getAttribute('data-alamat');
                    const email = button.getAttribute('data-email');
                    const telepon = button.getAttribute('data-telepon');
                    const ruang = button.getAttribute('data-ruang');
                    
                    showDetailModal(id, kode, spesialisasi, foto, nama,
                                tanggal_lahir, jenis_kelamin, alamat, email, telepon, ruang);
                }
            });
            
            // Event delegation untuk tombol lihat/download dokumen di modal detail
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

            // Event listener untuk form edit
            const editForm = document.getElementById('editDokterForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    handleFormSubmit(e, 'btnUpdateDokter');
                });
            }

            // Auto focus pada input search
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
    $url = 'dokter.php?';
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
    $url = 'dokter.php?';
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