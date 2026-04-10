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

// Cek hak akses sesuai header.php: IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang bisa akses data riwayat medis
if ($jabatan_user != 'IT Support' && 
    $role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Perawat Spesialis Mata' && 
    $jabatan_user != 'Medical Record') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola data riwayat medis. Hanya Staff dengan jabatan IT Support, Dokter, Perawat Spesialis Mata, dan Medical Record yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

// Proses hapus
if (isset($_GET['hapus'])) {
    $id_pasien = $_GET['hapus'];
    
    $db->beginTransaction();
    
    try {
        $db->hapus_data_antriankontrol_by_pasien($id_pasien);
        $db->hapus_data_antrian_by_pasien($id_pasien);
        $db->hapus_data_transaksi_by_pasien($id_pasien);
        $db->hapus_data_kontrol_by_pasien($id_pasien);
        $db->hapus_data_rekam_by_pasien($id_pasien);
        
        if (!$db->hapus_data_pasien($id_pasien)) {
            throw new Exception("Gagal menghapus data pasien");
        }
        
        $db->commit();
        
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'Pasien dan Seluruh Data Terkait';
        $keterangan = "Pasien ID '{$id_pasien}' beserta seluruh data terkait berhasil dihapus oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data pasien beserta seluruh data terkait berhasil dihapus.';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data pasien: ' . $e->getMessage();
    }
    
    header("Location: datariwayatmedis.php");
    exit();
}

// ==================== FUNGSI UNTUK MENGAMBIL DATA TERKAIT ====================

function getRekamMedisByPasien($db, $id_pasien) {
    $query = "SELECT * FROM data_rekam_medis WHERE id_pasien = '$id_pasien' ORDER BY id_rekam DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getResepObatByRekam($db, $id_rekam) {
    $query = "SELECT * FROM data_resep_obat WHERE id_rekam = '$id_rekam' ORDER BY id_resep_obat DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getDetailResepObatByResep($db, $id_resep_obat) {
    $query = "SELECT ddr.*, do.nama_obat, do.harga as harga_obat 
              FROM data_detail_resep_obat ddr
              JOIN data_obat do ON ddr.id_obat = do.id_obat
              WHERE ddr.id_resep_obat = '$id_resep_obat' 
              ORDER BY ddr.id_detail_resep DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getDetailTindakanByRekam($db, $id_rekam) {
    $query = "SELECT dtm.*, tm.nama_tindakan, tm.tarif as harga_tindakan 
              FROM data_detail_tindakan_medis dtm
              JOIN data_tindakan_medis tm ON dtm.id_tindakan_medis = tm.id_tindakan_medis
              WHERE dtm.id_rekam = '$id_rekam' 
              ORDER BY dtm.id_detail_tindakanmedis DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getResepKacamataByRekam($db, $id_rekam) {
    $query = "SELECT * FROM data_resep_kacamata WHERE id_rekam = '$id_rekam' ORDER BY id_resep_kacamata DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPemeriksaanMataByRekam($db, $id_rekam) {
    $query = "SELECT * FROM data_pemeriksaan_mata WHERE id_rekam = '$id_rekam' ORDER BY id_pemeriksaan DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getTransaksiByRekam($db, $id_rekam) {
    $query = "SELECT * FROM data_transaksi WHERE id_rekam = '$id_rekam' ORDER BY id_transaksi DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getDetailTransaksiByTransaksi($db, $id_transaksi) {
    $query = "SELECT * FROM data_detail_transaksi WHERE id_transaksi = '$id_transaksi' ORDER BY id_detail_transaksi DESC";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Fungsi untuk mendapatkan semua data detail pasien (untuk modal) - LENGKAP dengan semua data terkait
function getAllDetailDataByPasien($db, $id_pasien) {
    // Ambil data pasien
    $query_pasien = "SELECT * FROM data_pasien WHERE id_pasien = '$id_pasien'";
    $result_pasien = $db->koneksi->query($query_pasien);
    $pasien_data = ($result_pasien && $result_pasien->num_rows > 0) ? $result_pasien->fetch_assoc() : null;
    
    if (!$pasien_data) return null;
    
    // Ambil semua rekam medis
    $rekam_medis_list = getRekamMedisByPasien($db, $id_pasien);
    
    $rekam_details = [];
    $all_resep_obat_details = [];
    $all_transaksi_details = [];
    
    foreach ($rekam_medis_list as $rekam) {
        $id_rekam = $rekam['id_rekam'];
        
        // Ambil semua data terkait rekam medis
        $pemeriksaan_mata = getPemeriksaanMataByRekam($db, $id_rekam);
        $detail_tindakan = getDetailTindakanByRekam($db, $id_rekam);
        $resep_kacamata = getResepKacamataByRekam($db, $id_rekam);
        $resep_obat = getResepObatByRekam($db, $id_rekam);
        $transaksi = getTransaksiByRekam($db, $id_rekam);
        
        $rekam_details[] = [
            'rekam_medis' => $rekam,
            'pemeriksaan_mata' => $pemeriksaan_mata,
            'detail_tindakan' => $detail_tindakan,
            'resep_kacamata' => $resep_kacamata,
            'resep_obat' => $resep_obat,
            'transaksi' => $transaksi
        ];
        
        // Kumpulkan semua detail resep obat
        foreach ($resep_obat as $ro) {
            $details = getDetailResepObatByResep($db, $ro['id_resep_obat']);
            foreach ($details as $detail) {
                $detail['id_rekam'] = $id_rekam;
                $all_resep_obat_details[] = $detail;
            }
        }
        
        // Kumpulkan semua detail transaksi
        foreach ($transaksi as $tr) {
            $details = getDetailTransaksiByTransaksi($db, $tr['id_transaksi']);
            foreach ($details as $detail) {
                $detail['id_rekam'] = $id_rekam;
                $all_transaksi_details[] = $detail;
            }
        }
    }
    
    return [
        'pasien' => $pasien_data,
        'rekam_details' => $rekam_details,
        'all_resep_obat_details' => $all_resep_obat_details,
        'all_transaksi_details' => $all_transaksi_details
    ];
}

// Fungsi utama untuk mendapatkan semua data riwayat pasien (untuk tabel)
function getAllRiwayatPasien($db) {
    $pasien = $db->tampil_data_pasien();
    
    if (!is_array($pasien)) {
        $pasien = [];
    }
    
    $riwayat_pasien = [];
    
    foreach ($pasien as $p) {
        $id_pasien = $p['id_pasien'];
        $rekam_medis_list = getRekamMedisByPasien($db, $id_pasien);
        
        // Kumpulkan semua ID terkait per pasien
        $all_id_rekam = [];
        $all_id_resep_obat = [];
        $all_id_detail_tindakan = [];
        $all_id_resep_kacamata = [];
        $all_id_pemeriksaan = [];
        $all_id_transaksi = [];
        
        foreach ($rekam_medis_list as $rekam) {
            $id_rekam = $rekam['id_rekam'];
            $all_id_rekam[] = $id_rekam;
            
            $resep_obat = getResepObatByRekam($db, $id_rekam);
            foreach ($resep_obat as $ro) {
                $all_id_resep_obat[] = $ro['id_resep_obat'];
            }
            
            $detail_tindakan = getDetailTindakanByRekam($db, $id_rekam);
            foreach ($detail_tindakan as $dt) {
                $all_id_detail_tindakan[] = $dt['id_detail_tindakanmedis'];
            }
            
            $resep_kacamata = getResepKacamataByRekam($db, $id_rekam);
            foreach ($resep_kacamata as $rk) {
                $all_id_resep_kacamata[] = $rk['id_resep_kacamata'];
            }
            
            $pemeriksaan = getPemeriksaanMataByRekam($db, $id_rekam);
            foreach ($pemeriksaan as $pm) {
                $all_id_pemeriksaan[] = $pm['id_pemeriksaan'];
            }
            
            $transaksi = getTransaksiByRekam($db, $id_rekam);
            foreach ($transaksi as $tr) {
                $all_id_transaksi[] = $tr['id_transaksi'];
            }
        }
        
        $riwayat_pasien[] = [
            'id_pasien' => $id_pasien,
            'nik' => $p['nik'] ?? '',
            'nama_pasien' => $p['nama_pasien'] ?? '',
            'jenis_kelamin_pasien' => $p['jenis_kelamin_pasien'] ?? '',
            'tgl_lahir_pasien' => $p['tgl_lahir_pasien'] ?? '',
            'alamat_pasien' => $p['alamat_pasien'] ?? '',
            'telepon_pasien' => $p['telepon_pasien'] ?? '',
            'tanggal_registrasi_pasien' => $p['tanggal_registrasi_pasien'] ?? '',
            'id_rekam_list' => $all_id_rekam,
            'id_resep_obat_list' => $all_id_resep_obat,
            'id_detail_tindakan_list' => $all_id_detail_tindakan,
            'id_resep_kacamata_list' => $all_id_resep_kacamata,
            'id_pemeriksaan_list' => $all_id_pemeriksaan,
            'id_transaksi_list' => $all_id_transaksi,
            'rekam_medis_data' => $rekam_medis_list
        ];
    }
    
    return $riwayat_pasien;
}

// Konfigurasi pagination, search, sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

$all_riwayat = getAllRiwayatPasien($db);

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_riwayat = [];
    foreach ($all_riwayat as $riwayat) {
        if (stripos($riwayat['id_pasien'] ?? '', $search_query) !== false ||
            stripos($riwayat['nik'] ?? '', $search_query) !== false ||
            stripos($riwayat['nama_pasien'] ?? '', $search_query) !== false ||
            stripos(implode(',', $riwayat['id_rekam_list'] ?? []), $search_query) !== false ||
            stripos(implode(',', $riwayat['id_resep_obat_list'] ?? []), $search_query) !== false ||
            stripos(implode(',', $riwayat['id_detail_tindakan_list'] ?? []), $search_query) !== false ||
            stripos(implode(',', $riwayat['id_resep_kacamata_list'] ?? []), $search_query) !== false ||
            stripos(implode(',', $riwayat['id_pemeriksaan_list'] ?? []), $search_query) !== false ||
            stripos(implode(',', $riwayat['id_transaksi_list'] ?? []), $search_query) !== false) {
            $filtered_riwayat[] = $riwayat;
        }
    }
    $all_riwayat = $filtered_riwayat;
}

// Sorting
if ($sort_order === 'desc') {
    usort($all_riwayat, function($a, $b) {
        return ($b['id_pasien'] ?? 0) - ($a['id_pasien'] ?? 0);
    });
} else {
    usort($all_riwayat, function($a, $b) {
        return ($a['id_pasien'] ?? 0) - ($b['id_pasien'] ?? 0);
    });
}

// Pagination
$total_entries = count($all_riwayat);
$total_pages = ceil($total_entries / $entries_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $entries_per_page;
$data_riwayat = array_slice($all_riwayat, $offset, $entries_per_page);

if ($sort_order === 'desc') {
    $start_number = $total_entries - $offset;
} else {
    $start_number = $offset + 1;
}

$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

// Helper functions untuk URL
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'datariwayatmedis.php?';
    $params = [];
    
    if ($page > 1) $params[] = 'page=' . $page;
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    if ($sort != 'asc') $params[] = 'sort=' . $sort;
    
    return $url . implode('&', $params);
}

function getSortUrl($current_sort) {
    $url = 'datariwayatmedis.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if ($entries != 10) $params[] = 'entries=' . $entries;
    if (!empty($search)) $params[] = 'search=' . urlencode($search);
    
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}

// Fungsi untuk format tampilan list ID
function formatIdList($ids) {
    if (empty($ids)) return '<span class="text-muted">-</span>';
    $badges = '';
    foreach ($ids as $id) {
        $badges .= '<span class="badge-id">' . htmlspecialchars($id) . '</span>';
    }
    return $badges;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Riwayat Pasien - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css">
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/fonts/feather.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome.css">
    <link rel="stylesheet" href="assets/fonts/material.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/style-preset.css">

    <style>
    .btn-hapus, .btn-view, .btn-cetak, .btn-pdf {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .btn-hapus:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }
    .btn-view:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
    }
    .btn-cetak:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }
    .btn-pdf:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }
    .badge-id {
        display: inline-block;
        background-color: #6c757d;
        color: #fff;
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        margin: 0.1rem;
        border-radius: 10px;
        font-weight: 500;
    }
    .badge-rekam { background-color: #17a2b8; }
    .badge-resep-obat { background-color: #28a745; }
    .badge-tindakan { background-color: #fd7e14; }
    .badge-kacamata { background-color: #6f42c1; }
    .badge-pemeriksaan { background-color: #20c997; }
    .badge-transaksi { background-color: #dc3545; }
    
    .table th {
        border-top: none;
        font-weight: 600;
        background-color: #f8f9fa;
        white-space: nowrap;
    }
    .table td {
        vertical-align: middle;
    }
    .modal-content {
        border-radius: 12px;
        border: none;
    }
    .card {
        border-radius: 10px;
        overflow: hidden;
    }
    
    /* Styling untuk detail rekam medis */
    .rekam-card {
        background: white;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid #e9ecef;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .rekam-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    .rekam-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #0d6efd;
    }
    .rekam-id {
        font-size: 1rem;
        font-weight: 700;
        color: #0d6efd;
    }
    .rekam-date {
        font-size: 0.75rem;
        color: #6c757d;
        background: white;
        padding: 4px 12px;
        border-radius: 20px;
    }
    .rekam-body {
        padding: 20px;
    }
    .section-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 12px;
        padding-bottom: 6px;
        border-bottom: 2px solid #e9ecef;
    }
    .section-title i {
        color: #0d6efd;
        margin-right: 8px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .info-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 10px 12px;
    }
    .info-label {
        font-size: 0.7rem;
        color: #6c757d;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 0.85rem;
        color: #212529;
        font-weight: 500;
    }
    .subsection {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px dashed #dee2e6;
    }
    .subsection-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #0d6efd;
        margin-bottom: 12px;
    }
    .table-detail {
        font-size: 0.75rem;
        margin-bottom: 0;
    }
    .table-detail th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.7rem;
        padding: 8px;
    }
    .table-detail td {
        padding: 8px;
        vertical-align: middle;
    }
    .badge-obat-detail {
        background-color: #28a745;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        display: inline-block;
        margin: 2px;
    }
    .badge-tindakan-detail {
        background-color: #fd7e14;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        display: inline-block;
        margin: 2px;
    }
    .detail-icon {
        min-width: 40px;
        text-align: center;
    }
    .modal-body-custom {
        max-height: 80vh;
        overflow-y: auto;
        padding: 20px;
    }
    
    /* Button group styling */
    .btn-group-aksi {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
    }
    .btn-group-aksi .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
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
                                <li class="breadcrumb-item" aria-current="page">Data Riwayat Medis</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Data Riwayat Medis</h2>
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
                                        <input type="text" class="form-control" name="search" placeholder="Cari ID Pasien, Nama, ID Rekam, ID Resep, ID Tindakan..." value="<?= htmlspecialchars($search_query) ?>">
                                        <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                        <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="datariwayatmedis.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($search_query)): ?>
                        <div class="alert alert-info alert-dismissible fade show mb-3">
                            <i class="fas fa-info-circle me-2"></i>Menampilkan hasil pencarian untuk: <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table id="riwayatTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><a href="<?= getSortUrl($sort_order) ?>" class="text-decoration-none text-dark">No <?php if ($sort_order === 'asc'): ?><i class="fas fa-sort-up ms-1"></i><?php else: ?><i class="fas fa-sort-down ms-1"></i><?php endif; ?></a></th>
                                        <th>ID</th>
                                        <th>NIK</th>
                                        <th>Nama</th>
                                        <th>Rekam</th>
                                        <th>Resep Obat</th>
                                        <th>Tindakan Medis</th>
                                        <th>Resep Kacamata</th>
                                        <th>Pemeriksaan</th>
                                        <th>Transaksi</th>
                                        <th>Tgl Registrasi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_riwayat) && is_array($data_riwayat)): ?>
                                        <?php foreach ($data_riwayat as $riwayat): 
                                            // Siapkan data detail untuk dikirim ke modal via data attribute
                                            $detail_data = getAllDetailDataByPasien($db, $riwayat['id_pasien']);
                                            $modal_data = htmlspecialchars(json_encode($detail_data), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td><?= $start_number ?></td>
                                            <td><?= htmlspecialchars($riwayat['id_pasien'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($riwayat['nik'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($riwayat['nama_pasien'] ?? '') ?></td>
                                            <td>
                                                <?php if (!empty($riwayat['id_rekam_list'])): ?>
                                                    <?php foreach ($riwayat['id_rekam_list'] as $id): ?>
                                                        <span class="badge-id badge-rekam"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($riwayat['id_resep_obat_list'])): ?>
                                                    <?php foreach ($riwayat['id_resep_obat_list'] as $id): ?>
                                                        <span class="badge-id badge-resep-obat"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($riwayat['id_detail_tindakan_list'])): ?>
                                                    <?php foreach ($riwayat['id_detail_tindakan_list'] as $id): ?>
                                                        <span class="badge-id badge-tindakan"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($riwayat['id_resep_kacamata_list'])): ?>
                                                    <?php foreach ($riwayat['id_resep_kacamata_list'] as $id): ?>
                                                        <span class="badge-id badge-kacamata"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($riwayat['id_pemeriksaan_list'])): ?>
                                                    <?php foreach ($riwayat['id_pemeriksaan_list'] as $id): ?>
                                                        <span class="badge-id badge-pemeriksaan"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($riwayat['id_transaksi_list'])): ?>
                                                    <?php foreach ($riwayat['id_transaksi_list'] as $id): ?>
                                                        <span class="badge-id badge-transaksi"><?= htmlspecialchars($id) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($riwayat['tanggal_registrasi_pasien']) ? date('d/m/Y H:i:s', strtotime($riwayat['tanggal_registrasi_pasien'])) : '-' ?></td>
                                            <td class="text-nowrap">
                                                <div class="btn-group-aksi">
                                                    <button type="button" class="btn btn-info btn-sm btn-view" data-detail='<?= $modal_data ?>' title="Lihat Detail Lengkap">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="proses/cetak/cetak-riwayat-medis-cetak.php?cetak_riwayat=1&id_pasien=<?= $riwayat['id_pasien'] ?>" target="_blank" class="btn btn-warning btn-sm btn-cetak" title="Cetak Riwayat">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    <a href="proses/cetak/cetak-riwayat-medis-pdf.php?export_pdf=1&id_pasien=<?= $riwayat['id_pasien'] ?>" class="btn btn-danger btn-sm btn-pdf" title="Download PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm btn-hapus" data-id="<?= $riwayat['id_pasien'] ?>" data-nama="<?= htmlspecialchars($riwayat['nama_pasien']) ?>" title="Hapus Pasien dan Riwayat">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($sort_order === 'desc'): $start_number--; else: $start_number++; endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="13" class="text-center text-muted"><?= !empty($search_query) ? 'Tidak ada data yang sesuai dengan pencarian' : 'Tidak ada data riwayat pasien' ?></td></tr>
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

    <!-- Modal Hapus -->
    <div class="modal fade" id="hapusModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3"><i class="fas fa-trash-alt text-danger fa-3x mb-3"></i></div>
                    <p class="text-center">Apakah Anda yakin ingin menghapus pasien dan seluruh data terkait:</p>
                    <h5 class="text-center text-danger" id="namaPasienHapus"></h5>
                    <p class="text-center text-muted" id="idPasienHapus"></p>
                    <div class="alert alert-danger mt-4">
                        <i class="fas fa-exclamation-circle me-2"></i><strong>PERINGATAN!</strong>
                        <hr>
                        <p class="mb-2">Data yang akan dihapus secara permanen:</p>
                        <ul class="text-start mb-0">
                            <li><i class="fas fa-user text-muted me-2"></i>Data diri pasien</li>
                            <li><i class="fas fa-file-medical text-muted me-2"></i>Seluruh rekam medis</li>
                            <li><i class="fas fa-prescription-bottle text-muted me-2"></i>Seluruh resep obat & detailnya</li>
                            <li><i class="fas fa-syringe text-muted me-2"></i>Seluruh detail tindakan medis</li>
                            <li><i class="fas fa-glasses text-muted me-2"></i>Seluruh resep kacamata</li>
                            <li><i class="fas fa-eye text-muted me-2"></i>Seluruh pemeriksaan mata</li>
                            <li><i class="fas fa-money-bill-wave text-muted me-2"></i>Seluruh transaksi & detailnya</li>
                        </ul>
                        <p class="mt-3 mb-0 fw-bold">Data TIDAK DAPAT dipulihkan!</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
                    <a href="#" id="hapusButton" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Pasien Lengkap -->
    <div class="modal fade" id="detailModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-dark fw-bold">
                        <i class="fas fa-user me-2"></i>Detail Lengkap Pasien & Riwayat Medis
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="detailModalBody">
                    <!-- Content akan diisi oleh JavaScript -->
                </div>
                <div class="modal-footer bg-light">
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
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        let url = 'datariwayatmedis.php?entries=' + entries + '&page=1&sort=' + sort;
        if (search) url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    function formatTanggalIndo(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString('id-ID', { 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch(e) {
            return dateString;
        }
    }

    function formatTanggalLahir(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString('id-ID', { 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric'
            });
        } catch(e) {
            return dateString;
        }
    }

    function formatRupiah(angka) {
        if (!angka && angka !== 0) return '-';
        return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function showDetailModal(data) {
        const pasien = data.pasien;
        const rekamDetails = data.rekam_details || [];
        const allResepObatDetails = data.all_resep_obat_details || [];
        const allTransaksiDetails = data.all_transaksi_details || [];
        
        let gender = pasien.jenis_kelamin_pasien || '-';
        if (gender === 'L') gender = 'Laki-laki';
        else if (gender === 'P') gender = 'Perempuan';
        
        let html = `
        <!-- Header Pasien -->
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1">${escapeHtml(pasien.nama_pasien || '-')}</h3>
            <div class="text-muted">
                <i class="fas fa-id-card me-1"></i>ID Pasien: ${escapeHtml(pasien.id_pasien || '-')}
            </div>
        </div>

        <!-- Informasi Pasien -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="card-title fw-semibold mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>Informasi Pasien
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-address-card text-muted"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">NIK</small>
                                <span class="fw-medium">${escapeHtml(pasien.nik || '-')}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-venus-mars text-muted"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Jenis Kelamin</small>
                                <span class="fw-medium">${escapeHtml(gender)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-calendar-alt text-muted"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Tanggal Lahir</small>
                                <span class="fw-medium">${formatTanggalLahir(pasien.tgl_lahir_pasien)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-phone text-muted"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Telepon</small>
                                <span class="fw-medium">${escapeHtml(pasien.telepon_pasien || '-')}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-calendar-check text-muted"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Tanggal Registrasi</small>
                                <span class="fw-medium">${formatTanggalIndo(pasien.tanggal_registrasi_pasien)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3" style="min-width: 40px; text-align: center;">
                                <i class="fas fa-map-marker-alt text-muted"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small class="text-muted d-block">Alamat</small>
                                <span class="fw-medium">${escapeHtml(pasien.alamat_pasien || '-')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="card-title fw-semibold mb-0">
                    <i class="fas fa-file-medical me-2 text-primary"></i>Riwayat Rekam Medis & Data Terkait
                </h6>
            </div>
            <div class="card-body">
        `;
        
        if (rekamDetails.length === 0) {
            html += '<div class="text-center py-4 text-muted"><i class="fas fa-folder-open fa-2x mb-2"></i><p>Tidak ada data rekam medis</p></div>';
        } else {
            for (let i = 0; i < rekamDetails.length; i++) {
                const item = rekamDetails[i];
                const rekam = item.rekam_medis;
                const pemeriksaanMata = item.pemeriksaan_mata || [];
                const detailTindakan = item.detail_tindakan || [];
                const resepKacamata = item.resep_kacamata || [];
                const resepObat = item.resep_obat || [];
                const transaksi = item.transaksi || [];
                
                html += `
                <div class="rekam-card">
                    <div class="rekam-header">
                        <div class="rekam-id">
                            <i class="fas fa-file-medical-alt me-2"></i>REKAM MEDIS #${escapeHtml(rekam.id_rekam)}
                        </div>
                        <div class="rekam-date">
                            <i class="far fa-calendar me-1"></i>${formatTanggalIndo(rekam.tanggal_periksa)}
                        </div>
                    </div>
                    <div class="rekam-body">
                        
                        <!-- Informasi Dasar Rekam Medis -->
                        <div class="section-title">
                            <i class="fas fa-stethoscope"></i>Informasi Kunjungan
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-user-md me-1"></i>Dokter</div>
                                <div class="info-value">${escapeHtml(rekam.kode_dokter || '-')}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-tag me-1"></i>Jenis Kunjungan</div>
                                <div class="info-value"><span class="badge ${rekam.jenis_kunjungan === 'Baru' ? 'bg-success' : 'bg-info'}">${escapeHtml(rekam.jenis_kunjungan || '-')}</span></div>
                            </div>
                        </div>
                        
                        ${rekam.keluhan ? `
                        <div class="info-item mb-3">
                            <div class="info-label"><i class="fas fa-notes-medical me-1"></i>Keluhan</div>
                            <div class="info-value">${escapeHtml(rekam.keluhan)}</div>
                        </div>

                        ` : ''}
                        ${rekam.diagnosa ? `
                        <div class="info-item mb-3">
                            <div class="info-label"><i class="fas fa-notes-medical me-1"></i>Diagnosa</div>
                            <div class="info-value">${escapeHtml(rekam.diagnosa)}</div>
                        </div>
                        ` : ''}
                        
                        ${rekam.catatan ? `
                        <div class="info-item mb-3">
                            <div class="info-label"><i class="fas fa-clipboard-list me-1"></i>Catatan</div>
                            <div class="info-value">${escapeHtml(rekam.catatan)}</div>
                        </div>
                        ` : ''}
                `;
                
                // Pemeriksaan Mata
                if (pemeriksaanMata.length > 0) {
                    html += `
                        <div class="subsection">
                            <div class="subsection-title"><i class="fas fa-eye me-1"></i>Pemeriksaan Mata</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-detail">
                                    <thead>
                                        <tr><th>ID</th><th>Visus OD</th><th>Visus OS</th><th>OD (Sph/Cyl/Axis)</th><th>OS (Sph/Cyl/Axis)</th><th>TIO OD/OS</th><th>Tanggal</th></tr>
                                    </thead>
                                    <tbody>
                    `;
                    for (let j = 0; j < pemeriksaanMata.length; j++) {
                        const pm = pemeriksaanMata[j];
                        html += `<tr>
                            <td>${escapeHtml(pm.id_pemeriksaan)}</div>
                            <td>${escapeHtml(pm.visus_od || '-')}</div>
                            <td>${escapeHtml(pm.visus_os || '-')}</div>
                            <td><small>${pm.sph_od || 0} / ${pm.cyl_od || 0} / ${pm.axis_od || 0}°</small></div>
                            <td><small>${pm.sph_os || 0} / ${pm.cyl_os || 0} / ${pm.axis_os || 0}°</small></div>
                            <td><small>${pm.tio_od || '-'} / ${pm.tio_os || '-'} mmHg</small></div>
                            <td><small>${formatTanggalIndo(pm.created_at)}</small></div>
                        </tr>`;
                    }
                    html += `</tbody>}</div></div>`;
                }
                
                // Detail Tindakan Medis
                if (detailTindakan.length > 0) {
                    html += `
                        <div class="subsection">
                            <div class="subsection-title"><i class="fas fa-syringe me-1"></i>Detail Tindakan Medis</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-detail">
                                    <thead><tr><th>ID</th><th>Tindakan</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Tanggal</th></tr></thead>
                                    <tbody>
                    `;
                    for (let j = 0; j < detailTindakan.length; j++) {
                        const dt = detailTindakan[j];
                        html += `<tr>
                            <td>${escapeHtml(dt.id_detail_tindakanmedis)}</div>
                            <td>${escapeHtml(dt.nama_tindakan || '-')}</div>
                            <td>${dt.qty || 0}</div>
                            <td>${formatRupiah(dt.harga)}</div>
                            <td>${formatRupiah(dt.subtotal)}</div>
                            <td><small>${formatTanggalIndo(dt.created_at)}</small></div>
                        </tr>`;
                    }
                    html += `</tbody>}</div></div>`;
                }
                
                // Resep Kacamata
                if (resepKacamata.length > 0) {
                    html += `
                        <div class="subsection">
                            <div class="subsection-title"><i class="fas fa-glasses me-1"></i>Resep Kacamata</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-detail">
                                    <thead><tr><th>ID</th><th>OD (Sph/Cyl/Axis)</th><th>OS (Sph/Cyl/Axis)</th><th>PD</th><th>Tanggal</th></tr></thead>
                                    <tbody>
                    `;
                    for (let j = 0; j < resepKacamata.length; j++) {
                        const rk = resepKacamata[j];
                        html += `<tr>
                            <td>${escapeHtml(rk.id_resep_kacamata)}</div>
                            <td><small>${rk.sph_od || 0} / ${rk.cyl_od || 0} / ${rk.axis_od || 0}°</small></div>
                            <td><small>${rk.sph_os || 0} / ${rk.cyl_os || 0} / ${rk.axis_os || 0}°</small></div>
                            <td>${rk.pd ? rk.pd + ' mm' : '-'}</div>
                            <td><small>${formatTanggalIndo(rk.created_at)}</small></div>
                        </tr>`;
                    }
                    html += `</tbody>}</div></div>`;
                }
                
                // Resep Obat & Detail Resep Obat
                if (resepObat.length > 0) {
                    html += `
                        <div class="subsection">
                            <div class="subsection-title"><i class="fas fa-prescription-bottle me-1"></i>Resep Obat</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-detail">
                                    <thead><tr><th>ID Resep</th><th>Tanggal Resep</th><th>Catatan</th><th>Detail Obat</th></tr></thead>
                                    <tbody>
                    `;
                    for (let j = 0; j < resepObat.length; j++) {
                        const ro = resepObat[j];
                        // Cari detail resep obat untuk resep ini
                        const detailObat = allResepObatDetails.filter(function(d) { return d.id_resep_obat == ro.id_resep_obat; });
                        let detailHtml = '';
                        if (detailObat.length > 0) {
                            detailHtml = '<ul class="list-unstyled mb-0" style="font-size:0.7rem">';
                            for (let k = 0; k < detailObat.length; k++) {
                                const det = detailObat[k];
                                detailHtml += `<li><span class="badge-obat-detail">${escapeHtml(det.nama_obat)}</span> x${det.jumlah} - ${formatRupiah(det.subtotal)}</li>`;
                            }
                            detailHtml += '</ul>';
                        } else {
                            detailHtml = '<span class="text-muted">Tidak ada detail obat</span>';
                        }
                        
                        html += `<tr>
                            <td>${escapeHtml(ro.id_resep_obat)}</div>
                            <td><small>${formatTanggalIndo(ro.tanggal_resep)}</small></div>
                            <td><small>${escapeHtml(ro.catatan || '-')}</small></div>
                            <td>${detailHtml}</div>
                        </tr>`;
                    }
                    html += `</tbody>}</div></div>`;
                }
                
                // Transaksi & Detail Transaksi
                if (transaksi.length > 0) {
                    html += `
                        <div class="subsection">
                            <div class="subsection-title"><i class="fas fa-receipt me-1"></i>Transaksi</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-detail">
                                    <thead><tr><th>ID Transaksi</th><th>Tanggal</th><th>Grand Total</th><th>Metode</th><th>Status</th><th>Detail Item</th></tr></thead>
                                    <tbody>
                    `;
                    for (let j = 0; j < transaksi.length; j++) {
                        const tr = transaksi[j];
                        // Cari detail transaksi untuk transaksi ini
                        const detailTrans = allTransaksiDetails.filter(function(d) { return d.id_transaksi == tr.id_transaksi; });
                        let detailHtml = '';
                        if (detailTrans.length > 0) {
                            detailHtml = '<ul class="list-unstyled mb-0" style="font-size:0.7rem">';
                            for (let k = 0; k < detailTrans.length; k++) {
                                const det = detailTrans[k];
                                const itemClass = det.jenis_item === 'Tindakan' ? 'badge-tindakan-detail' : 'badge-obat-detail';
                                detailHtml += `<li><span class="${itemClass}">${escapeHtml(det.jenis_item)}</span> ${escapeHtml(det.nama_item)} x${det.qty} - ${formatRupiah(det.subtotal)}</li>`;
                            }
                            detailHtml += '</ul>';
                        } else {
                            detailHtml = '<span class="text-muted">Tidak ada detail item</span>';
                        }
                        
                        html += `<tr>
                            <td>${escapeHtml(tr.id_transaksi)}</div>
                            <td><small>${formatTanggalIndo(tr.tanggal_transaksi)}</small></div>
                            <td class="fw-bold text-success">${formatRupiah(tr.grand_total)}</div>
                            <td>${escapeHtml(tr.metode_pembayaran || '-')}</div>
                            <td><span class="badge ${tr.status_pembayaran === 'Lunas' ? 'bg-success' : 'bg-warning'}">${escapeHtml(tr.status_pembayaran || 'Belum Bayar')}</span></div>
                            <td>${detailHtml}</div>
                        </tr>`;
                    }
                    html += `</tbody>}</div></div>`;
                }
                
                html += `
                    </div>
                </div>`;
            }
        }
        
        html += `
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('detailModalBody').innerHTML = html;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    // Event handlers
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-hapus')) {
                e.preventDefault();
                const btn = e.target.closest('.btn-hapus');
                document.getElementById('namaPasienHapus').textContent = btn.getAttribute('data-nama');
                document.getElementById('idPasienHapus').textContent = 'ID: ' + btn.getAttribute('data-id');
                document.getElementById('hapusButton').href = 'datariwayatmedis.php?hapus=' + btn.getAttribute('data-id');
                new bootstrap.Modal(document.getElementById('hapusModal')).show();
            }
            if (e.target.closest('.btn-view')) {
                e.preventDefault();
                const btn = e.target.closest('.btn-view');
                const detailData = btn.getAttribute('data-detail');
                if (detailData) {
                    try {
                        const data = JSON.parse(detailData);
                        showDetailModal(data);
                    } catch(e) {
                        console.error('Error parsing detail data:', e);
                        alert('Terjadi kesalahan saat memuat data detail');
                    }
                }
            }
        });
    });
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
</body>
</html>

<?php require_once "footer.php"; ?>