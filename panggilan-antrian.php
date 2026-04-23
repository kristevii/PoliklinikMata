<?php
session_start();
include "koneksi.php";
$db = new database();

// Validasi akses: Hanya Dokter dan Staff dengan jabatan tertentu yang bisa mengakses halaman ini (sesuai header.php)
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

// Cek hak akses sesuai header.php: Dokter, Administrasi, dan IT Support (Perawat hanya data pelayanan medis)
if ($role != 'Dokter' && $role != 'dokter' && 
    $jabatan_user != 'Administrasi' &&
    $jabatan_user != 'IT Support') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk mengelola panggilan antrian. Hanya Dokter, Administrasi, dan IT Support yang diizinkan.";
    header("Location: unautorized.php");
    exit();
}

$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

$today = date('Y-m-d');

// Fungsi untuk mendapatkan antrian yang sedang DIPANGGIL atau DILAYANI berdasarkan kode dokter (untuk Baru)
function getAntrianAktifByRuang($db, $kode_dokter, $today) {
    $kode_dokter = mysqli_real_escape_string($db->koneksi, $kode_dokter);
    
    // Prioritas: Dipanggil dulu, baru Dilayani
    $query = "
        SELECT da.*, dp.nama_pasien, dd.nama_dokter, dd.ruang 
        FROM data_antrian da 
        LEFT JOIN data_pasien dp ON da.id_pasien = dp.id_pasien
        LEFT JOIN data_dokter dd ON da.kode_dokter = dd.kode_dokter 
        WHERE da.kode_dokter = '$kode_dokter' 
        AND da.jenis_antrian = 'Baru'
        AND DATE(da.update_at) = '$today'
        AND da.status IN ('Dipanggil', 'Dilayani')
        ORDER BY FIELD(da.status, 'Dipanggil', 'Dilayani'), da.update_at DESC 
        LIMIT 1
    ";
    
    $result = mysqli_query($db->koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Fungsi untuk mendapatkan antrian kontrol yang sedang DIPANGGIL atau DILAYANI (dari data_antrian dengan jenis_antrian = 'Kontrol')
function getAntrianKontrolAktif($db, $today) {
    $query = "
        SELECT da.*, dp.nama_pasien, dd.nama_dokter, dd.ruang 
        FROM data_antrian da 
        LEFT JOIN data_pasien dp ON da.id_pasien = dp.id_pasien
        LEFT JOIN data_dokter dd ON da.kode_dokter = dd.kode_dokter
        WHERE da.jenis_antrian = 'Kontrol'
        AND DATE(da.tanggal_antrian) = '$today'
        AND da.status IN ('Dipanggil', 'Dilayani')
        ORDER BY FIELD(da.status, 'Dipanggil', 'Dilayani'), da.update_at DESC 
        LIMIT 1
    ";
    
    $result = mysqli_query($db->koneksi, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Fungsi untuk mendapatkan jumlah antrian MENUNGGU per ruang (untuk Baru)
function getJumlahMenungguByRuang($db, $kode_dokter, $today) {
    $kode_dokter = mysqli_real_escape_string($db->koneksi, $kode_dokter);
    
    $query = "
        SELECT COUNT(*) as total 
        FROM data_antrian 
        WHERE kode_dokter = '$kode_dokter' 
        AND jenis_antrian = 'Baru'
        AND DATE(update_at) = '$today'
        AND status = 'Menunggu'
    ";
    $result = mysqli_query($db->koneksi, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'] ?? 0;
    }
    return 0;
}

// Fungsi untuk mendapatkan jumlah antrian MENUNGGU untuk kontrol (dari data_antrian dengan jenis_antrian = 'Kontrol')
function getJumlahMenungguKontrol($db, $today) {
    $query = "
        SELECT COUNT(*) as total 
        FROM data_antrian 
        WHERE jenis_antrian = 'Kontrol'
        AND DATE(tanggal_antrian) = '$today'
        AND status = 'Menunggu'
    ";
    $result = mysqli_query($db->koneksi, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'] ?? 0;
    }
    return 0;
}

// Fungsi untuk mendapatkan 3 antrian berikutnya (menunggu) per ruang (untuk Baru)
function getNextAntrian($db, $kode_dokter, $today, $limit = 3) {
    $kode_dokter = mysqli_real_escape_string($db->koneksi, $kode_dokter);
    
    $query = "
        SELECT da.nomor_antrian, dp.nama_pasien
        FROM data_antrian da 
        LEFT JOIN data_pasien dp ON da.id_pasien = dp.id_pasien
        WHERE da.kode_dokter = '$kode_dokter' 
        AND da.jenis_antrian = 'Baru'
        AND DATE(da.update_at) = '$today'
        AND da.status = 'Menunggu'
        ORDER BY da.nomor_antrian ASC
        LIMIT $limit
    ";
    
    $result = mysqli_query($db->koneksi, $query);
    $antrian_list = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $antrian_list[] = $row;
        }
    }
    return $antrian_list;
}

// Fungsi untuk mendapatkan 3 antrian kontrol berikutnya (menunggu) dari data_antrian jenis Kontrol
function getNextAntrianKontrol($db, $today, $limit = 3) {
    $query = "
        SELECT da.nomor_antrian, dp.nama_pasien
        FROM data_antrian da 
        LEFT JOIN data_pasien dp ON da.id_pasien = dp.id_pasien
        WHERE da.jenis_antrian = 'Kontrol'
        AND DATE(da.tanggal_antrian) = '$today'
        AND da.status = 'Menunggu'
        ORDER BY da.nomor_antrian ASC
        LIMIT $limit
    ";
    
    $result = mysqli_query($db->koneksi, $query);
    $antrian_list = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $antrian_list[] = $row;
        }
    }
    return $antrian_list;
}

// Ambil data untuk setiap ruang (Baru)
$antrianRuang1 = getAntrianAktifByRuang($db, 'DRM001', $today);
$antrianRuang2 = getAntrianAktifByRuang($db, 'DRM002', $today);
$antrianRuang3 = getAntrianAktifByRuang($db, 'DRM003', $today);

// Ambil data kontrol (dari data_antrian jenis Kontrol)
$antrianKontrol = getAntrianKontrolAktif($db, $today);

// Hitung antrian MENUNGGU (Baru)
$menungguRuang1 = getJumlahMenungguByRuang($db, 'DRM001', $today);
$menungguRuang2 = getJumlahMenungguByRuang($db, 'DRM002', $today);
$menungguRuang3 = getJumlahMenungguByRuang($db, 'DRM003', $today);
$menungguKontrol = getJumlahMenungguKontrol($db, $today);

// Ambil antrian berikutnya
$nextAntrianRuang1 = getNextAntrian($db, 'DRM001', $today, 3);
$nextAntrianRuang2 = getNextAntrian($db, 'DRM002', $today, 3);
$nextAntrianRuang3 = getNextAntrian($db, 'DRM003', $today, 3);
$nextAntrianKontrol = getNextAntrianKontrol($db, $today, 3);

// Tambahkan variabel untuk menyimpan data antrian lengkap dalam format JSON
$allAntrianData = [
    'ruang1' => $antrianRuang1,
    'ruang2' => $antrianRuang2,
    'ruang3' => $antrianRuang3,
    'kontrol' => $antrianKontrol,
    'menunggu' => [
        'ruang1' => $nextAntrianRuang1,
        'ruang2' => $nextAntrianRuang2,
        'ruang3' => $nextAntrianRuang3,
        'kontrol' => $nextAntrianKontrol
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Panggilan Antrian - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="refresh" content="10">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
    <link rel="stylesheet" href="assets/fonts/feather.css" >
    <link rel="stylesheet" href="assets/fonts/fontawesome.css" >
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css" >
    <style>
        .antrian-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .antrian-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .card-header-ruang {
            background: #2c80ff;
            color: white;
            padding: 15px;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
        }
        
        .ruang-2 .card-header-ruang {
            background: #10b981;
        }
        
        .ruang-3 .card-header-ruang {
            background: #f59e0b;
        }
        
        .kontrol .card-header-ruang {
            background: #ef4444;
        }
        
        .ruang-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .ruang-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .antrian-nomor {
            font-size: 3rem;
            font-weight: 800;
            color: #2d3748;
            line-height: 1;
            margin: 15px 0;
            font-family: monospace;
            letter-spacing: 2px;
        }
        
        .pasien-info {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .pasien-nama {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .dokter-nama {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .ruang-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            margin: 10px 0;
        }
        
        .status-dilayani {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-dipanggil {
            background-color: #fecaca;
            color: #991b1b;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
        
        .stats-box {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #718096;
            text-transform: uppercase;
        }
        
        .empty-state {
            padding: 30px 15px;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 10px;
        }
        
        .empty-text {
            color: #a0aec0;
            font-size: 0.9rem;
        }
        
        .refresh-info {
            position: fixed;
            bottom: 70px;
            right: 20px;
            background: rgba(45, 55, 72, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            z-index: 1000;
        }
        
        .last-updated {
            font-size: 0.7rem;
            color: #a0aec0;
            text-align: center;
            margin-top: 10px;
        }
        
        .datetime-display {
            font-size: 0.9rem;
        }
        
        .queue-list {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        
        .queue-title {
            font-size: 0.7rem;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.85rem;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .queue-item:last-child {
            border-bottom: none;
        }
        
        .queue-nomor {
            font-weight: 700;
            font-family: monospace;
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        .queue-pasien {
            font-size: 0.75rem;
            color: #718096;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .badge-menunggu-kecil {
            background-color: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .jenis-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .jenis-baru {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .jenis-kontrol {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-body {
            padding: 1.25rem;
        }

        /* Tombol Ulang Panggilan */
        .btn-repeat-call {
            background-color: #ff9800;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.7rem;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-repeat-call:hover {
            background-color: #e68900;
            transform: scale(1.02);
        }
        
        .btn-repeat-call:active {
            transform: scale(0.98);
        }
        
        /* Tombol suara utama */
        .sound-toggle-btn {
            position: fixed;
            bottom: 140px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #2c80ff;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .sound-toggle-btn:hover {
            transform: scale(1.1);
        }
        
        .sound-toggle-btn.sound-off {
            background: #6c757d;
        }
        
        .sound-toggle-btn.sound-on {
            background: #10b981;
            animation: soundPulse 1s infinite;
        }
        
        @keyframes soundPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        /* Animasi saat dipanggil */
        .speaking {
            animation: speakGlow 0.5s ease-in-out 3;
        }
        
        @keyframes speakGlow {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { box-shadow: 0 0 0 15px rgba(16, 185, 129, 0.3); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme="light">
    
    <?php include 'header.php'; ?>

    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">Panggilan Antrian</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-2">Panggilan Antrian</h2>
                                <p class="text-muted mb-1 datetime-display">
                                    <i class="ti ti-calendar me-1"></i> 
                                    <span id="current-date"><?php echo date('d F Y'); ?></span>
                                    | <i class="ti ti-clock me-1"></i> 
                                    <span id="current-time"><?php echo date('H:i:s'); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Ruang 1 - DRM001 (Jenis Baru) -->
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card antrian-card" id="card-ruang1">
                        <div class="card-header card-header-ruang">
                            <h3 class="ruang-title mb-0">Ruang 1</h3>
                            <div class="ruang-subtitle">Kode: DRM001</div>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($antrianRuang1 && !empty($antrianRuang1['nomor_antrian'])): ?>
                                <div class="antrian-nomor">
                                    <?php echo htmlspecialchars($antrianRuang1['nomor_antrian']); ?>
                                </div>
                                
                                <div class="pasien-info">
                                    <div class="pasien-nama">
                                        <i class="ti ti-user me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang1['nama_pasien'] ?? 'Pasien'); ?>
                                    </div>
                                    <div class="dokter-nama">
                                        <i class="ti ti-stethoscope me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang1['nama_dokter'] ?? 'Dokter'); ?>
                                    </div>
                                    <?php if (!empty($antrianRuang1['ruang'])): ?>
                                    <div class="ruang-info">
                                        <i class="ti ti-door me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang1['ruang']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                $isDipanggil = ($antrianRuang1['status'] == 'Dipanggil');
                                $status_label = $isDipanggil ? 'DIPANGGIL' : 'SEDANG DILAYANI';
                                $status_class = $isDipanggil ? 'status-dipanggil' : 'status-dilayani';
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="ti ti-bell-ringing me-1"></i>
                                    <?php echo $status_label; ?>
                                </div>
                                
                                <!-- Tombol Ulang Panggilan -->
                                <button class="btn-repeat-call" onclick="repeatCall('ruang1', '<?php echo htmlspecialchars($antrianRuang1['nomor_antrian']); ?>', '<?php echo htmlspecialchars($antrianRuang1['nama_pasien'] ?? 'Pasien'); ?>', '<?php echo htmlspecialchars($antrianRuang1['ruang'] ?? 'Ruang 1'); ?>')">
                                    <i class="ti ti-volume-2"></i> Ulang Panggilan
                                </button>
                                
                                <div class="stats-box">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $menungguRuang1; ?></div>
                                        <div class="stat-label">Menunggu</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($nextAntrianRuang1)): ?>
                                <div class="queue-list">
                                    <div class="queue-title">
                                        <i class="ti ti-clock me-1"></i> Antrian Berikutnya
                                    </div>
                                    <?php foreach ($nextAntrianRuang1 as $next): ?>
                                    <div class="queue-item">
                                        <span class="queue-nomor"><?php echo htmlspecialchars($next['nomor_antrian']); ?></span>
                                        <span class="queue-pasien"><?php echo htmlspecialchars(substr($next['nama_pasien'] ?? '', 0, 20)); ?></span>
                                        <span class="badge-menunggu-kecil">Menunggu</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="last-updated">
                                    <i class="ti ti-clock me-1"></i>
                                    <?php echo date('H:i:s', strtotime($antrianRuang1['update_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ti ti-bell-off"></i>
                                    </div>
                                    <div class="empty-text">
                                        Tidak ada antrian aktif
                                    </div>
                                    <div class="stats-box mt-3">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $menungguRuang1; ?></div>
                                            <div class="stat-label">Menunggu</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ruang 2 - DRM002 (Jenis Baru) -->
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card antrian-card ruang-2" id="card-ruang2">
                        <div class="card-header card-header-ruang">
                            <h3 class="ruang-title mb-0">Ruang 2</h3>
                            <div class="ruang-subtitle">Kode: DRM002</div>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($antrianRuang2 && !empty($antrianRuang2['nomor_antrian'])): ?>
                                <div class="antrian-nomor">
                                    <?php echo htmlspecialchars($antrianRuang2['nomor_antrian']); ?>
                                </div>
                                
                                <div class="pasien-info">
                                    <div class="pasien-nama">
                                        <i class="ti ti-user me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang2['nama_pasien'] ?? 'Pasien'); ?>
                                    </div>
                                    <div class="dokter-nama">
                                        <i class="ti ti-stethoscope me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang2['nama_dokter'] ?? 'Dokter'); ?>
                                    </div>
                                    <?php if (!empty($antrianRuang2['ruang'])): ?>
                                    <div class="ruang-info">
                                        <i class="ti ti-door me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang2['ruang']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                $isDipanggil = ($antrianRuang2['status'] == 'Dipanggil');
                                $status_label = $isDipanggil ? 'DIPANGGIL' : 'SEDANG DILAYANI';
                                $status_class = $isDipanggil ? 'status-dipanggil' : 'status-dilayani';
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="ti ti-bell-ringing me-1"></i>
                                    <?php echo $status_label; ?>
                                </div>
                                
                                <button class="btn-repeat-call" onclick="repeatCall('ruang2', '<?php echo htmlspecialchars($antrianRuang2['nomor_antrian']); ?>', '<?php echo htmlspecialchars($antrianRuang2['nama_pasien'] ?? 'Pasien'); ?>', '<?php echo htmlspecialchars($antrianRuang2['ruang'] ?? 'Ruang 2'); ?>')">
                                    <i class="ti ti-volume-2"></i> Ulang Panggilan
                                </button>
                                
                                <div class="stats-box">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $menungguRuang2; ?></div>
                                        <div class="stat-label">Menunggu</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($nextAntrianRuang2)): ?>
                                <div class="queue-list">
                                    <div class="queue-title">
                                        <i class="ti ti-clock me-1"></i> Antrian Berikutnya
                                    </div>
                                    <?php foreach ($nextAntrianRuang2 as $next): ?>
                                    <div class="queue-item">
                                        <span class="queue-nomor"><?php echo htmlspecialchars($next['nomor_antrian']); ?></span>
                                        <span class="queue-pasien"><?php echo htmlspecialchars(substr($next['nama_pasien'] ?? '', 0, 20)); ?></span>
                                        <span class="badge-menunggu-kecil">Menunggu</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="last-updated">
                                    <i class="ti ti-clock me-1"></i>
                                    <?php echo date('H:i:s', strtotime($antrianRuang2['update_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ti ti-bell-off"></i>
                                    </div>
                                    <div class="empty-text">
                                        Tidak ada antrian aktif
                                    </div>
                                    <div class="stats-box mt-3">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $menungguRuang2; ?></div>
                                            <div class="stat-label">Menunggu</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ruang 3 - DRM003 (Jenis Baru) -->
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card antrian-card ruang-3" id="card-ruang3">
                        <div class="card-header card-header-ruang">
                            <h3 class="ruang-title mb-0">Ruang 3</h3>
                            <div class="ruang-subtitle">Kode: DRM003</div>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($antrianRuang3 && !empty($antrianRuang3['nomor_antrian'])): ?>
                                <div class="antrian-nomor">
                                    <?php echo htmlspecialchars($antrianRuang3['nomor_antrian']); ?>
                                </div>
                                
                                <div class="pasien-info">
                                    <div class="pasien-nama">
                                        <i class="ti ti-user me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang3['nama_pasien'] ?? 'Pasien'); ?>
                                    </div>
                                    <div class="dokter-nama">
                                        <i class="ti ti-stethoscope me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang3['nama_dokter'] ?? 'Dokter'); ?>
                                    </div>
                                    <?php if (!empty($antrianRuang3['ruang'])): ?>
                                    <div class="ruang-info">
                                        <i class="ti ti-door me-1"></i>
                                        <?php echo htmlspecialchars($antrianRuang3['ruang']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                $isDipanggil = ($antrianRuang3['status'] == 'Dipanggil');
                                $status_label = $isDipanggil ? 'DIPANGGIL' : 'SEDANG DILAYANI';
                                $status_class = $isDipanggil ? 'status-dipanggil' : 'status-dilayani';
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="ti ti-bell-ringing me-1"></i>
                                    <?php echo $status_label; ?>
                                </div>
                                
                                <button class="btn-repeat-call" onclick="repeatCall('ruang3', '<?php echo htmlspecialchars($antrianRuang3['nomor_antrian']); ?>', '<?php echo htmlspecialchars($antrianRuang3['nama_pasien'] ?? 'Pasien'); ?>', '<?php echo htmlspecialchars($antrianRuang3['ruang'] ?? 'Ruang 3'); ?>')">
                                    <i class="ti ti-volume-2"></i> Ulang Panggilan
                                </button>
                                
                                <div class="stats-box">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $menungguRuang3; ?></div>
                                        <div class="stat-label">Menunggu</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($nextAntrianRuang3)): ?>
                                <div class="queue-list">
                                    <div class="queue-title">
                                        <i class="ti ti-clock me-1"></i> Antrian Berikutnya
                                    </div>
                                    <?php foreach ($nextAntrianRuang3 as $next): ?>
                                    <div class="queue-item">
                                        <span class="queue-nomor"><?php echo htmlspecialchars($next['nomor_antrian']); ?></span>
                                        <span class="queue-pasien"><?php echo htmlspecialchars(substr($next['nama_pasien'] ?? '', 0, 20)); ?></span>
                                        <span class="badge-menunggu-kecil">Menunggu</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="last-updated">
                                    <i class="ti ti-clock me-1"></i>
                                    <?php echo date('H:i:s', strtotime($antrianRuang3['update_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ti ti-bell-off"></i>
                                    </div>
                                    <div class="empty-text">
                                        Tidak ada antrian aktif
                                    </div>
                                    <div class="stats-box mt-3">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $menungguRuang3; ?></div>
                                            <div class="stat-label">Menunggu</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Kontrol (dari data_antrian dengan jenis_antrian = 'Kontrol') -->
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card antrian-card kontrol" id="card-kontrol">
                        <div class="card-header card-header-ruang">
                            <h3 class="ruang-title mb-0">Kontrol</h3>
                            <div class="ruang-subtitle">Pasien Kontrol</div>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($antrianKontrol && !empty($antrianKontrol['nomor_antrian'])): ?>
                                <div class="antrian-nomor">
                                    <?php echo htmlspecialchars($antrianKontrol['nomor_antrian']); ?>
                                </div>
                                
                                <div class="pasien-info">
                                    <div class="pasien-nama">
                                        <i class="ti ti-user me-1"></i>
                                        <?php echo htmlspecialchars($antrianKontrol['nama_pasien'] ?? 'Pasien'); ?>
                                    </div>
                                    <div class="dokter-nama">
                                        <i class="ti ti-stethoscope me-1"></i>
                                        <?php echo htmlspecialchars($antrianKontrol['nama_dokter'] ?? 'Dokter'); ?>
                                    </div>
                                </div>
                                
                                <?php 
                                $isDipanggil = ($antrianKontrol['status'] == 'Dipanggil');
                                $status_label = $isDipanggil ? 'DIPANGGIL' : 'SEDANG DILAYANI';
                                $status_class = $isDipanggil ? 'status-dipanggil' : 'status-dilayani';
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="ti ti-bell-ringing me-1"></i>
                                    <?php echo $status_label; ?>
                                </div>
                                
                                <button class="btn-repeat-call" onclick="repeatCall('kontrol', '<?php echo htmlspecialchars($antrianKontrol['nomor_antrian']); ?>', '<?php echo htmlspecialchars($antrianKontrol['nama_pasien'] ?? 'Pasien'); ?>', '<?php echo htmlspecialchars($antrianKontrol['ruang'] ?? 'Ruang Kontrol'); ?>', true)">
                                    <i class="ti ti-volume-2"></i> Ulang Panggilan
                                </button>
                                
                                <div class="stats-box">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $menungguKontrol; ?></div>
                                        <div class="stat-label">Menunggu</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($nextAntrianKontrol)): ?>
                                <div class="queue-list">
                                    <div class="queue-title">
                                        <i class="ti ti-clock me-1"></i> Antrian Berikutnya
                                    </div>
                                    <?php foreach ($nextAntrianKontrol as $next): ?>
                                    <div class="queue-item">
                                        <span class="queue-nomor"><?php echo htmlspecialchars($next['nomor_antrian']); ?></span>
                                        <span class="queue-pasien"><?php echo htmlspecialchars(substr($next['nama_pasien'] ?? '', 0, 20)); ?></span>
                                        <span class="badge-menunggu-kecil">Menunggu</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="last-updated">
                                    <i class="ti ti-clock me-1"></i>
                                    <?php echo date('H:i:s', strtotime($antrianKontrol['update_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ti ti-bell-off"></i>
                                    </div>
                                    <div class="empty-text">
                                        Tidak ada kontrol aktif
                                    </div>
                                    <div class="stats-box mt-3">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $menungguKontrol; ?></div>
                                            <div class="stat-label">Menunggu</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Refresh -->
            <div class="refresh-info">
                <i class="ti ti-refresh me-1"></i>
                Auto refresh: <span id="countdown">10</span>s
            </div>
        </div>
    </div>

    <!-- Tombol Suara -->
    <button class="sound-toggle-btn" id="soundToggleBtn" title="Aktifkan/Nonaktifkan Suara (Tekan lama untuk test)">
        <i class="ti ti-volume-3" id="soundIcon"></i>
    </button>

    <!-- Scripts -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>

    <script>
        // Data antrian dari PHP
        const antrianData = <?php echo json_encode($allAntrianData); ?>;
        
        // Konfigurasi suara
        let soundEnabled = localStorage.getItem('soundEnabled') === 'true';
        let lastCalledAntrian = {
            ruang1: '',
            ruang2: '',
            ruang3: '',
            kontrol: ''
        };
        
        // Fungsi untuk memainkan suara
        function speakText(text) {
            if (!soundEnabled) return;
            
            if (!('speechSynthesis' in window)) {
                console.warn('Browser tidak mendukung Web Speech API');
                return;
            }
            
            // Hentikan suara yang sedang berjalan
            window.speechSynthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 0.9;
            utterance.pitch = 1;
            utterance.volume = 1;
            
            // Pilih suara terbaik untuk bahasa Indonesia
            const voices = window.speechSynthesis.getVoices();
            const preferredVoice = voices.find(voice => 
                voice.lang === 'id-ID' && (voice.name.includes('Google') || voice.name.includes('Microsoft'))
            );
            if (preferredVoice) {
                utterance.voice = preferredVoice;
            }
            
            window.speechSynthesis.speak(utterance);
        }
        
        // Fungsi panggilan otomatis (1x saja)
        function callQueueSound(ruang, nomorAntrian, namaPasien, ruangName, isKontrol = false) {
            if (!soundEnabled) return;
            
            let jenisAntrian = isKontrol ? 'Kontrol ' : '';
            let message = `Kepada ${jenisAntrian}Antrian dengan Nomor ${nomorAntrian}, ${namaPasien} silahkan menuju ke ${ruangName}.`;
            
            // Animasi pada card
            const cardId = `card-${ruang}`;
            const cardElement = document.getElementById(cardId);
            if (cardElement) {
                cardElement.classList.add('speaking');
                setTimeout(() => cardElement.classList.remove('speaking'), 2000);
            }
            
            speakText(message);
        }
        
        // Fungsi untuk mengulang panggilan (dipanggil dari tombol)
        function repeatCall(ruang, nomorAntrian, namaPasien, ruangName, isKontrol = false) {
            if (!soundEnabled) {
                return;
            }
            
            if (!nomorAntrian) {
                return;
            }
            
            let jenisAntrian = isKontrol ? 'Kontrol ' : '';
            let message = `Perhatian, Saya Ulangi Panggilan. Kepada ${jenisAntrian}Antrian dengan Nomor ${nomorAntrian}, ${namaPasien} silahkan menuju ke ${ruangName}.`;
            
            // Animasi pada card
            const cardId = `card-${ruang}`;
            const cardElement = document.getElementById(cardId);
            if (cardElement) {
                cardElement.classList.add('speaking');
                setTimeout(() => cardElement.classList.remove('speaking'), 2000);
            }
            
            speakText(message);
        }
        
        // Fungsi untuk mengecek perubahan antrian dan memanggil suara
        function checkAndCallQueue() {
            const ruangs = ['ruang1', 'ruang2', 'ruang3', 'kontrol'];
            
            ruangs.forEach(ruang => {
                const currentAntrian = antrianData[ruang];
                const currentNomor = currentAntrian && currentAntrian.nomor_antrian ? currentAntrian.nomor_antrian : '';
                const lastNomor = lastCalledAntrian[ruang];
                
                // Jika ada antrian baru dengan status Dipanggil
                if (currentNomor && currentNomor !== lastNomor && currentAntrian.status === 'Dipanggil') {
                    let ruangName = currentAntrian.ruang || '';
                    let isKontrol = (ruang === 'kontrol');
                    
                    if (ruang === 'ruang1') ruangName = ruangName || 'Ruang 1';
                    else if (ruang === 'ruang2') ruangName = ruangName || 'Ruang 2';
                    else if (ruang === 'ruang3') ruangName = ruangName || 'Ruang 3';
                    else ruangName = ruangName || 'Ruang Kontrol';
                    
                    callQueueSound(ruang, currentNomor, currentAntrian.nama_pasien || 'Pasien', ruangName, isKontrol);
                    lastCalledAntrian[ruang] = currentNomor;
                }
            });
            
            // Simpan ke localStorage
            localStorage.setItem('lastCalledAntrian', JSON.stringify(lastCalledAntrian));
        }
        
        // Event listener untuk tombol suara
        const soundToggleBtn = document.getElementById('soundToggleBtn');
        const soundIcon = document.getElementById('soundIcon');
        
        function updateSoundButton() {
            if (soundEnabled) {
                soundToggleBtn.classList.remove('sound-off');
                soundToggleBtn.classList.add('sound-on');
                soundIcon.className = 'ti ti-volume-2';
                soundToggleBtn.title = 'Suara Aktif - Klik untuk nonaktifkan';
            } else {
                soundToggleBtn.classList.remove('sound-on');
                soundToggleBtn.classList.add('sound-off');
                soundIcon.className = 'ti ti-volume-3';
                soundToggleBtn.title = 'Suara Nonaktif - Klik untuk aktifkan';
            }
        }
        
        soundToggleBtn.addEventListener('click', function() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('soundEnabled', soundEnabled);
            updateSoundButton();
            
            if (soundEnabled) {
                // Cek antrian saat ini
                setTimeout(() => checkAndCallQueue(), 500);
            } else {
                // Hentikan suara yang sedang berjalan
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                }
            }
        });
        
        // Countdown timer untuk refresh
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            countdown--;
            if (countdown < 0) {
                countdown = 10;
                // Setiap kali refresh, cek antrian baru
                setTimeout(() => {
                    location.reload();
                }, 100);
            }
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
        }
        
        setInterval(updateCountdown, 1000);
        
        // Update waktu real-time
        function updateRealTime() {
            const now = new Date();
            
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            const dateStr = `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
            
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const timeStr = `${hours}:${minutes}:${seconds}`;
            
            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');
            
            if (dateElement) dateElement.textContent = dateStr;
            if (timeElement) timeElement.textContent = timeStr;
        }
        
        setInterval(updateRealTime, 1000);
        
        // Inisialisasi
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTime();
            feather.replace();
            
            // Load last called antrian dari localStorage
            const savedLastCalled = localStorage.getItem('lastCalledAntrian');
            if (savedLastCalled) {
                try {
                    lastCalledAntrian = JSON.parse(savedLastCalled);
                } catch(e) {}
            }
            
            // Set initial sound button state
            updateSoundButton();
            
            // Cek antrian pertama kali
            setTimeout(() => {
                checkAndCallQueue();
            }, 1000);
        });
        
        // Dapatkan voices setelah loaded
        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = () => {
                console.log('Voices loaded');
            };
        }
    </script>
</body>
</html>

<?php require_once "footer.php"; ?>