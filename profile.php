<?php
session_start();

include "koneksi.php";
$db = new database();

// Ambil data user dari session
$username = $_SESSION['username'] ?? 'Juna Arka';
$role = $_SESSION['role'] ?? 'Administrasi';
$email_session = $_SESSION['email'] ?? 'juna.arka@eyethica.com';
$id_user = $_SESSION['id_user'] ?? null;

// Cek role
$isStaff = ($role === 'staff' || $role === 'Staff');
$isDokter = ($role === 'dokter' || $role === 'Dokter');

// --- Ambil data spesifik berdasarkan role ---
$kode_dokter = null;
$kode_staff = null;
$nama_lengkap = $username;
$subspesialisasi = '-';
$jabatan = '-';
$tanggal_lahir = '-';
$jenis_kelamin = '-';
$alamat = '-';
$email = $email_session;
$telepon = '-';
$ruang = '-';
$foto_profile = 'assets/images/user/avatar-2.jpg';
$pasien_ditangani = 0;
$rekam_medis = 0;
$kontrol_terjadwal = 0;
$jadwal_praktik = [];
$aktivitas_terakhir = [];

if ($id_user) {
    // Ambil 5 aktivitas terakhir menggunakan function dari class database
    if (method_exists($db, 'tampil_5_aktivitas_profile_terakhir')) {
        $aktivitas_terakhir = $db->tampil_5_aktivitas_profile_terakhir($id_user);
    } else {
        // Fallback: query manual jika method tidak ada
        $query_aktivitas = "SELECT 
                            ap.id_user, 
                            u.nama_lengkap as nama_user,
                            ap.jenis,   
                            ap.entitas, 
                            ap.keterangan, 
                            ap.waktu 
                        FROM aktivitas_profile ap
                        LEFT JOIN users u ON ap.id_user = u.id_user
                        WHERE ap.id_user = '$id_user'
                        ORDER BY ap.waktu DESC 
                        LIMIT 5";
        
        $result_aktivitas = $db->koneksi->query($query_aktivitas);
        $aktivitas_terakhir = [];
        if ($result_aktivitas && $result_aktivitas->num_rows > 0) {
            while ($row = $result_aktivitas->fetch_assoc()) {
                $aktivitas_terakhir[] = $row;
            }
        }
    }
    
    if ($isDokter) {
        // Query untuk data dokter - SESUAI DENGAN STRUKTUR TABLE
        $query_dokter = "SELECT 
                            kode_dokter, 
                            nama_dokter, 
                            subspesialisasi,
                            tanggal_lahir_dokter, 
                            jenis_kelamin_dokter,
                            alamat_dokter,
                            email,
                            telepon_dokter,
                            ruang,
                            foto_dokter 
                        FROM data_dokter 
                        WHERE id_user = '$id_user'";
        
        $result_dokter = $db->koneksi->query($query_dokter);
        
        if ($result_dokter && $result_dokter->num_rows > 0) {
            $dokter_data = $result_dokter->fetch_assoc();
            
            // Mapping data dokter
            $kode_dokter = $dokter_data['kode_dokter'] ?? '';
            $nama_lengkap = $dokter_data['nama_dokter'] ?? $username;
            $subspesialisasi = $dokter_data['subspesialisasi'] ?? '-';
            $tanggal_lahir = $dokter_data['tanggal_lahir_dokter'] ?? '-';
            
            // Konversi jenis kelamin
            $jk = $dokter_data['jenis_kelamin_dokter'] ?? '-';
            if ($jk == 'L') $jenis_kelamin = 'Laki-laki';
            elseif ($jk == 'P') $jenis_kelamin = 'Perempuan';
            else $jenis_kelamin = '-';
            
            $alamat = $dokter_data['alamat_dokter'] ?? '-';
            $email = $dokter_data['email'] ?? $email_session;
            $telepon = $dokter_data['telepon_dokter'] ?? '-';
            $ruang = $dokter_data['ruang'] ?? '-';
            
            // Foto profile
            if (!empty($dokter_data['foto_dokter'])) {
                $foto_dokter = $dokter_data['foto_dokter'];
                if (strpos($foto_dokter, 'http') === 0) {
                    $foto_profile = $foto_dokter;
                } else if (file_exists('image-dokter/' . $foto_dokter)) {
                    $foto_profile = 'image-dokter/' . $foto_dokter;
                } else if (file_exists($foto_dokter)) {
                    $foto_profile = $foto_dokter;
                } else if (file_exists('../' . $foto_dokter)) {
                    $foto_profile = '../' . $foto_dokter;
                }
            }
            
            // Hitung statistik untuk dokter
            if (!empty($kode_dokter)) {
                // Hitung pasien ditangani (dari data_rekam_medis)
                $query_pasien = "SELECT COUNT(DISTINCT id_pasien) as total FROM data_rekam_medis WHERE kode_dokter = '$kode_dokter'";
                $result_pasien = $db->koneksi->query($query_pasien);
                if ($result_pasien && $result_pasien->num_rows > 0) {
                    $data_pasien = $result_pasien->fetch_assoc();
                    $pasien_ditangani = $data_pasien['total'] ?? 0;
                }
                
                // Hitung rekam medis (dari data_rekam_medis)
                $query_rekam = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE kode_dokter = '$kode_dokter'";
                $result_rekam = $db->koneksi->query($query_rekam);
                if ($result_rekam && $result_rekam->num_rows > 0) {
                    $data_rekam = $result_rekam->fetch_assoc();
                    $rekam_medis = $data_rekam['total'] ?? 0;
                }
                
                // PERBAIKAN: Hitung kontrol terjadwal dari data_antrian dengan jenis_antrian = 'Kontrol'
                // Menggunakan status yang sesuai dengan data_antrian (Menunggu, Dijadwalkan, dll)
                $query_kontrol = "SELECT COUNT(*) as total 
                                  FROM data_antrian 
                                  WHERE kode_dokter = '$kode_dokter' 
                                  AND jenis_antrian = 'Kontrol'
                                  AND status IN ('Dijadwalkan', 'Menunggu')";
                $result_kontrol = $db->koneksi->query($query_kontrol);
                if ($result_kontrol && $result_kontrol->num_rows > 0) {
                    $data_kontrol = $result_kontrol->fetch_assoc();
                    $kontrol_terjadwal = $data_kontrol['total'] ?? 0;
                }
                
                // Ambil jadwal praktik
                $query_jadwal = "SELECT * FROM data_jadwal_dokter 
                                WHERE kode_dokter = '$kode_dokter' 
                                ORDER BY 
                                    CASE hari 
                                        WHEN 'Senin' THEN 1
                                        WHEN 'Selasa' THEN 2
                                        WHEN 'Rabu' THEN 3
                                        WHEN 'Kamis' THEN 4
                                        WHEN 'Jumat' THEN 5
                                        WHEN 'Sabtu' THEN 6
                                        WHEN 'Minggu' THEN 7
                                    END,
                                    CASE shift
                                        WHEN 'Pagi' THEN 1
                                        WHEN 'Sore' THEN 2
                                        WHEN 'Malam' THEN 3
                                    END";
                $result_jadwal = $db->koneksi->query($query_jadwal);
                
                if ($result_jadwal && $result_jadwal->num_rows > 0) {
                    while ($row = $result_jadwal->fetch_assoc()) {
                        $jadwal_praktik[] = $row;
                    }
                }
            }
        }
    } elseif ($isStaff) {
        // Query untuk data staff - SESUAI DENGAN STRUKTUR TABLE
        $query_staff = "SELECT 
                            kode_staff,
                            nama_staff,
                            jabatan_staff,
                            jenis_kelamin_staff,
                            tanggal_lahir_staff,
                            alamat_staff,
                            telepon_staff,
                            email,
                            foto_staff
                        FROM data_staff 
                        WHERE id_user = '$id_user'";
        
        $result_staff = $db->koneksi->query($query_staff);
        
        if ($result_staff && $result_staff->num_rows > 0) {
            $staff_data = $result_staff->fetch_assoc();
            
            // Mapping data staff
            $kode_staff = $staff_data['kode_staff'] ?? '';
            $nama_lengkap = $staff_data['nama_staff'] ?? $username;
            $jabatan = $staff_data['jabatan_staff'] ?? '-';
            
            // Konversi jenis kelamin
            $jk = $staff_data['jenis_kelamin_staff'] ?? '-';
            if ($jk == 'L') $jenis_kelamin = 'Laki-laki';
            elseif ($jk == 'P') $jenis_kelamin = 'Perempuan';
            else $jenis_kelamin = '-';
            
            $tanggal_lahir = $staff_data['tanggal_lahir_staff'] ?? '-';
            $alamat = $staff_data['alamat_staff'] ?? '-';
            $telepon = $staff_data['telepon_staff'] ?? '-';
            $email = $staff_data['email'] ?? $email_session;
            
            // Foto profile
            if (!empty($staff_data['foto_staff'])) {
                $foto_staff = $staff_data['foto_staff'];
                if (strpos($foto_staff, 'http') === 0) {
                    $foto_profile = $foto_staff;
                } else if (file_exists('image-staff/' . $foto_staff)) {
                    $foto_profile = 'image-staff/' . $foto_staff;
                } else if (file_exists($foto_staff)) {
                    $foto_profile = $foto_staff;
                } else if (file_exists('../' . $foto_staff)) {
                    $foto_profile = '../' . $foto_staff;
                }
            }
        }
    } else {
        // Untuk role lain (administrasi, dll)
        $query_user = "SELECT nama_lengkap, foto FROM users WHERE id_user = '$id_user'";
        $result_user = $db->koneksi->query($query_user);
        if ($result_user && $result_user->num_rows > 0) {
            $user_data = $result_user->fetch_assoc();
            $nama_lengkap = $user_data['nama_lengkap'] ?? $username;
            
            if (!empty($user_data['foto'])) {
                $foto_user = $user_data['foto'];
                if (strpos($foto_user, 'http') === 0) {
                    $foto_profile = $foto_user;
                } else if (file_exists('assets/images/user/' . $foto_user)) {
                    $foto_profile = 'assets/images/user/' . $foto_user;
                } else if (file_exists($foto_user)) {
                    $foto_profile = $foto_user;
                }
            }
        }
    }
}

function getRekanKerja($db, $current_role) {
    $rekan_kerja = [];
    
    // Ambil data dokter
    $query_dokter = "SELECT 
                        nama_dokter as nama, 
                        subspesialisasi as jabatan, 
                        foto_dokter as foto 
                    FROM data_dokter 
                    WHERE foto_dokter IS NOT NULL 
                    LIMIT 2";
    $result_dokter = $db->koneksi->query($query_dokter);
    
    if ($result_dokter && $result_dokter->num_rows > 0) {
        while ($row = $result_dokter->fetch_assoc()) {
            $row['tipe'] = 'dokter';
            $rekan_kerja[] = $row;
        }
    }
    
    // Ambil data staff
    $query_staff = "SELECT 
                        nama_staff as nama, 
                        jabatan_staff as jabatan, 
                        foto_staff as foto 
                    FROM data_staff 
                    WHERE foto_staff IS NOT NULL 
                    LIMIT 2";
    $result_staff = $db->koneksi->query($query_staff);
    
    if ($result_staff && $result_staff->num_rows > 0) {
        while ($row = $result_staff->fetch_assoc()) {
            $row['tipe'] = 'staff';
            $rekan_kerja[] = $row;
        }
    }
    
    // Proses path foto untuk setiap rekan kerja
    foreach ($rekan_kerja as &$rk) {
        if (empty($rk['foto'])) {
            $rk['foto'] = 'assets/images/user/avatar-1.jpg';
        } else {
            if ($rk['tipe'] == 'dokter') {
                if (file_exists('image-dokter/' . $rk['foto'])) {
                    $rk['foto'] = 'image-dokter/' . $rk['foto'];
                } elseif (file_exists($rk['foto'])) {
                    // Path sudah lengkap
                } else {
                    $rk['foto'] = 'assets/images/user/avatar-1.jpg';
                }
            } else {
                if (file_exists('image-staff/' . $rk['foto'])) {
                    $rk['foto'] = 'image-staff/' . $rk['foto'];
                } elseif (file_exists($rk['foto'])) {
                    // Path sudah lengkap
                } else {
                    $rk['foto'] = 'assets/images/user/avatar-2.jpg';
                }
            }
        }
    }
    
    // Jika masih kosong, gunakan data dummy
    if (empty($rekan_kerja)) {
        $rekan_kerja = [
            [
                'nama' => 'Dr. Sarah Wijaya', 
                'jabatan' => 'Dokter Mata', 
                'foto' => 'assets/images/user/avatar-1.jpg',
                'tipe' => 'dokter'
            ],
            [
                'nama' => 'Budi Santoso', 
                'jabatan' => 'Staff Administrasi', 
                'foto' => 'assets/images/user/avatar-2.jpg',
                'tipe' => 'staff'
            ],
            [
                'nama' => 'Dr. Ahmad Rasyid', 
                'jabatan' => 'Dokter Umum', 
                'foto' => 'assets/images/user/avatar-3.jpg',
                'tipe' => 'dokter'
            ],
            [
                'nama' => 'Siti Nurhaliza', 
                'jabatan' => 'Perawat', 
                'foto' => 'assets/images/user/avatar-4.jpg',
                'tipe' => 'staff'
            ]
        ];
    }
    
    return $rekan_kerja;
}

$rekan_kerja = getRekanKerja($db, $role);

// Format tanggal lahir untuk tampilan
if ($tanggal_lahir != '-' && $tanggal_lahir != '') {
    $tanggal_lahir_formatted = date('d F Y', strtotime($tanggal_lahir));
} else {
    $tanggal_lahir_formatted = '-';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Profile - Sistem Informasi Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/fonts/feather.css">
    <link rel="stylesheet" href="assets/fonts/fontawesome.css">
    <link rel="stylesheet" href="assets/fonts/material.css" >
    <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
    <link rel="stylesheet" href="assets/css/style-preset.css">
    <style>
        /* CSS untuk informasi personal dengan gaya baru */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            font-size: 1.25rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .info-content {
            flex: 1;
        }
        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
        }
        .info-value.small {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .profile-avatar {
            flex-shrink: 0;
            position: relative;
        }
        .profile-avatar .avtar {
            position: absolute;
            bottom: 0;
            right: 0;
            margin-bottom: -0.5rem;
            margin-right: -0.5rem;
        }
        .profile-title h4 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .profile-badge {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        .profile-badge i {
            margin-right: 0.3rem;
        }

        /* Jadwal table styles (tetap) */
        .jadwal-table th {
            font-weight: 600;
            font-size: 0.85rem;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .jadwal-table td {
            vertical-align: middle;
            border-bottom: 1px ;
            text-align: center;
            font-size: 0.9rem;
        }
        .shift-badge {
            background-color: #e7f1ff;
            color: #0d6efd;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            min-width: 60px;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            min-width: 60px;
        }
        .status-aktif {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-libur {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-cuti {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .jadwal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .jadwal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343a40;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-data-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        .time-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #343a40;
        }
        .hari-cell {
            font-weight: 600;
            color: #343a40;
        }
        .table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .info-divider {
            margin: 1.5rem 0;
            border-top: 1px dashed #dee2e6;
        }
        .wid-100 {
            width: 100px;
            height: 100px;
            object-fit: cover;
        }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme="light">
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
                                <li class="breadcrumb-item" aria-current="page">Profile</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-4">Profil Saya</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Cards - Hanya untuk Dokter -->
            <?php if ($isDokter): ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0"><i class="ti ti-users f-30"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="text-white mb-0"><?php echo number_format($pasien_ditangani); ?></h4>
                                    <p class="mb-0 opacity-75">Pasien Ditangani</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0"><i class="ti ti-report-medical f-30"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="text-white mb-0"><?php echo number_format($rekam_medis); ?></h4>
                                    <p class="mb-0 opacity-75">Rekam Medis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0"><i class="ti ti-clock f-30"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="text-white mb-0"><?php echo number_format($kontrol_terjadwal); ?></h4>
                                    <p class="mb-0 opacity-75">Kontrol Terjadwal</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Personal</h5>
                        </div>
                        <div class="card-body">
                            <!-- Profile Header dengan avatar dan role -->
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <img src="<?php echo htmlspecialchars($foto_profile); ?>" 
                                         alt="user" 
                                         class="wid-100 rounded-circle border border-primary p-1"
                                         onerror="this.src='assets/images/user/avatar-2.jpg'">
                                    <label for="upload" class="avtar avtar-s btn-primary rounded-circle position-absolute bottom-0 end-0 mb-n2 me-n2" style="cursor:pointer">
                                        <input type="file" id="upload" class="d-none" accept="image/*">
                                    </label>
                                </div>
                                <div class="profile-title">
                                    <h4><?php echo htmlspecialchars($nama_lengkap); ?></h4>
                                    <?php if ($isDokter): ?>
                                        <span class="profile-badge"><i class="ti ti-stethoscope"></i> <?php echo htmlspecialchars($subspesialisasi); ?></span>
                                    <?php elseif ($isStaff): ?>
                                        <span class="profile-badge"><i class="ti ti-briefcase"></i> <?php echo htmlspecialchars($jabatan); ?></span>
                                    <?php else: ?>
                                        <span class="profile-badge"><?php echo htmlspecialchars($role); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Grid informasi personal dengan gaya baru -->
                            <div class="info-grid">
                                <!-- Nama Lengkap -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-user"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Nama Lengkap</div>
                                        <div class="info-value"><?php echo htmlspecialchars($nama_lengkap); ?></div>
                                    </div>
                                </div>
                                
                                <!-- ID Pegawai / Kode -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-id"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">ID Pegawai</div>
                                        <div class="info-value">
                                            <?php 
                                            if ($isDokter && !empty($kode_dokter)) {
                                                echo htmlspecialchars($kode_dokter);
                                            } elseif ($isStaff && !empty($kode_staff)) {
                                                echo htmlspecialchars($kode_staff);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Spesialisasi / Jabatan (khusus) -->
                                <?php if ($isDokter): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-stethoscope"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Subspesialisasi</div>
                                        <div class="info-value"><?php echo htmlspecialchars($subspesialisasi); ?></div>
                                    </div>
                                </div>
                                <?php elseif ($isStaff): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-briefcase"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Jabatan</div>
                                        <div class="info-value"><?php echo htmlspecialchars($jabatan); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Ruang Praktek (khusus dokter) -->
                                <?php if ($isDokter): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-layout-2"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Ruang Praktek</div>
                                        <div class="info-value"><?php echo htmlspecialchars($ruang); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Tanggal Lahir -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-calendar"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Tanggal Lahir</div>
                                        <div class="info-value"><?php echo htmlspecialchars($tanggal_lahir_formatted); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Jenis Kelamin -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-venus-mars"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Jenis Kelamin</div>
                                        <div class="info-value"><?php echo htmlspecialchars($jenis_kelamin); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Email -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-mail"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Email</div>
                                        <div class="info-value small"><?php echo htmlspecialchars($email); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Telepon -->
                                <div class="info-item">
                                    <div class="info-icon"><i class="ti ti-phone"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Nomor Telepon</div>
                                        <div class="info-value"><?php echo htmlspecialchars($telepon); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Alamat (full width) -->
                                <div class="info-item" style="grid-column: span 2;">
                                    <div class="info-icon"><i class="ti ti-map-pin"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">Alamat</div>
                                        <div class="info-value"><?php echo htmlspecialchars($alamat); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Jadwal Praktik - Hanya untuk Dokter -->
                    <?php if ($isDokter): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <div class="jadwal-header">
                                <h5 class="jadwal-title">Jadwal Praktik Mingguan</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($jadwal_praktik)): ?>
                            <div class="no-data">
                                <div class="no-data-icon">
                                    <i class="ti ti-calendar"></i>
                                </div>
                                <h5 class="mb-2">Belum Ada Jadwal Praktik</h5>
                                <p class="text-muted">Jadwal praktik Anda belum diatur. Silakan hubungi administrasi.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table jadwal-table">
                                    <thead>
                                        <tr>
                                            <th>Hari</th>
                                            <th>Shift</th>
                                            <th>Status</th>
                                            <th>Jam Mulai</th>
                                            <th>Jam Selesai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jadwal_praktik as $jadwal): 
                                            $status = $jadwal['status'] ?? 'Aktif';
                                            $shift = $jadwal['shift'] ?? 'Pagi';
                                            $jam_mulai = !empty($jadwal['jam_mulai']) ? date('H:i', strtotime($jadwal['jam_mulai'])) : '08:00';
                                            $jam_selesai = !empty($jadwal['jam_selesai']) ? date('H:i', strtotime($jadwal['jam_selesai'])) : '14:00';
                                            
                                            // Tentukan class status
                                            $status_class = 'status-aktif';
                                            if ($status === 'Libur') {
                                                $status_class = 'status-libur';
                                            } elseif ($status === 'Cuti') {
                                                $status_class = 'status-cuti';
                                            }
                                        ?>
                                        <tr>
                                            <td class="hari-cell"><?php echo htmlspecialchars($jadwal['hari']); ?></td>
                                            <td>
                                                <span class="shift-badge"><?php echo htmlspecialchars($shift); ?></span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td class="time-cell"><?php echo $jam_mulai; ?></td>
                                            <td class="time-cell"><?php echo $jam_selesai; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Aktivitas Terakhir -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Aktivitas Profil Terbaru</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($aktivitas_terakhir)): ?>
                                    <?php foreach ($aktivitas_terakhir as $act): ?>
                                    <div class="list-group-item border-0">
                                        <div class="d-flex align-items-start">
                                            <div class="avtar avtar-s bg-light-secondary text-secondary flex-shrink-0">
                                                <?php 
                                                // Icon berdasarkan jenis aktivitas
                                                $icon = 'ti ti-circle-check';
                                                if (isset($act['jenis'])) {
                                                    if (strpos(strtolower($act['jenis']), 'login') !== false) {
                                                        $icon = 'ti ti-login';
                                                    } elseif (strpos(strtolower($act['jenis']), 'logout') !== false || strpos(strtolower($act['jenis']), 'logout') !== false) {
                                                        $icon = 'ti ti-logout';
                                                    } elseif (strpos(strtolower($act['jenis']), 'update') !== false || strpos(strtolower($act['jenis']), 'ubah') !== false) {
                                                        $icon = 'ti ti-edit';
                                                    } 
                                                }
                                                ?>
                                                <i class="<?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <p class="mb-0 text-dark">
                                                    <?php echo htmlspecialchars($act['keterangan'] ?? '-'); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php 
                                                    if (!empty($act['waktu'])) {
                                                        $waktu = strtotime($act['waktu']);
                                                        echo date('d M Y H:i', $waktu);
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item border-0 text-center py-4">
                                        <i class="ti ti-history f-30 text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Belum ada aktivitas</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rekan Kerja -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Rekan Kerja Aktif</h5>
                        </div>
                        <div class="card-body">
                            <div class="user-group">
                                <?php 
                                $count = 0;
                                foreach($rekan_kerja as $rk): 
                                    if ($count >= 4) break;
                                ?>
                                    <img src="<?php echo htmlspecialchars($rk['foto']); ?>" 
                                         alt="user" 
                                         class="avtar avtar-s" 
                                         title="<?php echo htmlspecialchars($rk['nama'] . ' - ' . $rk['jabatan']); ?>"
                                         onerror="this.src='assets/images/user/avatar-1.jpg'">
                                <?php 
                                    $count++;
                                endforeach; 
                                ?>
                            </div>
                            <p class="text-muted small mt-3">Tim medis dan staff yang aktif hari ini.</p>
                        </div>
                    </div>

                    <!-- Keamanan -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Keamanan</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-0">Ganti Password</h6>
                                    <p class="text-muted small mb-0">Amankan akun Anda dengan password yang kuat.</p>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" onclick="alert('Fitur ganti password akan segera tersedia.')">
                                    Ganti
                                </button>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-0">Verifikasi Dua Langkah</h6>
                                    <p class="text-muted small mb-0">Tingkatkan keamanan akun Anda.</p>
                                </div>
                                <span class="badge bg-light-secondary text-secondary">Coming Soon</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Preview foto sebelum upload
        document.getElementById('upload')?.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    // Tampilkan preview (implementasi sesuai kebutuhan)
                    alert('Foto berhasil dipilih. Fitur upload akan segera tersedia.');
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    </script>
</body>
</html>

<?php require_once "footer.php"; ?>