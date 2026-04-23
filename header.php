<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include koneksi database
include_once(dirname(__FILE__) . '/koneksi.php');
$db = new database();

// Inisialisasi variabel foto
$foto_sidebar = 'assets/images/user/avatar-1.jpg'; // default untuk sidebar
$foto_header = 'assets/images/user/avatar-2.jpg'; // default untuk header
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Guest';
$role_user = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$email_user = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$jabatan_user = ''; // Untuk menyimpan jabatan staff atau spesialisasi dokter

// Ambil data user dari session
$id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Inisialisasi hak akses menu
$menu_akses = [
    'dashboard' => false,
    'data_master' => false,
    'data_pelayanan_medis' => false,
    'antrian' => false,
    'transaksi' => false,
    'laporan' => false
];

// Jika user login, ambil data dan tentukan hak akses berdasarkan role
if ($id_user && $role) {
    // Untuk role Dokter
    if ($role == 'Dokter' || $role == 'dokter') {
        $query_dokter = "SELECT foto_dokter, nama_dokter, subspesialisasi FROM data_dokter WHERE id_user = '$id_user'";
        $result_dokter = $db->koneksi->query($query_dokter);
        
        if ($result_dokter && $result_dokter->num_rows > 0) {
            $dokter_data = $result_dokter->fetch_assoc();
            $foto_dokter = $dokter_data['foto_dokter'];
            
            // Update nama jika ada di tabel dokter
            if (!empty($dokter_data['nama_dokter'])) {
                $nama_user = $dokter_data['nama_dokter'];
            }
            
            // Ambil spesialisasi dokter
            if (!empty($dokter_data['subspesialisasi'])) {
                $jabatan_user = $dokter_data['subspesialisasi'];
            }
            
            if (!empty($foto_dokter)) {
                // Cek berbagai kemungkinan path
                if (strpos($foto_dokter, 'http') === 0) {
                    $foto_sidebar = $foto_dokter;
                    $foto_header = $foto_dokter;
                } else if (file_exists(dirname(__FILE__) . '/image-dokter/' . $foto_dokter)) {
                    $foto_sidebar = 'image-dokter/' . $foto_dokter;
                    $foto_header = 'image-dokter/' . $foto_dokter;
                } else if (file_exists(dirname(__FILE__) . '/image-dokter/' . $foto_dokter)) {
                    $foto_sidebar = 'image-dokter/' . $foto_dokter;
                    $foto_header = 'image-dokter/' . $foto_dokter;
                } else if (file_exists($foto_dokter)) {
                    $foto_sidebar = $foto_dokter;
                    $foto_header = $foto_dokter;
                } else if (file_exists($foto_dokter)) {
                    $foto_sidebar = $foto_dokter;
                    $foto_header = $foto_dokter;
                }
            }
        }
        
        // Hak akses untuk Dokter
        $menu_akses['dashboard'] = true;
        $menu_akses['data_pelayanan_medis'] = true;
        $menu_akses['antrian'] = true;
    }
    // Untuk role Staff
    elseif ($role == 'Staff' || $role == 'staff') {
        $query_staff = "SELECT foto_staff, nama_staff, jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
        $result_staff = $db->koneksi->query($query_staff);
        
        if ($result_staff && $result_staff->num_rows > 0) {
            $staff_data = $result_staff->fetch_assoc();
            $foto_staff = $staff_data['foto_staff'];
            
            // Update nama jika ada di tabel staff
            if (!empty($staff_data['nama_staff'])) {
                $nama_user = $staff_data['nama_staff'];
            }
            
            // Ambil jabatan staff
            if (!empty($staff_data['jabatan_staff'])) {
                $jabatan_user = $staff_data['jabatan_staff'];
            }
            
            if (!empty($foto_staff)) {
                // Cek berbagai kemungkinan path
                if (strpos($foto_staff, 'http') === 0) {
                    $foto_sidebar = $foto_staff;
                    $foto_header = $foto_staff;
                } else if (file_exists(dirname(__FILE__) . '/image-staff/' . $foto_staff)) {
                    $foto_sidebar = 'image-staff/' . $foto_staff;
                    $foto_header = 'image-staff/' . $foto_staff;
                } else if (file_exists(dirname(__FILE__) . '/image-staff/' . $foto_staff)) {
                    $foto_sidebar = 'image-staff/' . $foto_staff;
                    $foto_header = 'image-staff/' . $foto_staff;
                } else if (file_exists($foto_staff)) {
                    $foto_sidebar = $foto_staff;
                    $foto_header = $foto_staff;
                } else if (file_exists($foto_staff)) {
                    $foto_sidebar = $foto_staff;
                    $foto_header = $foto_staff;
                }
            }
        }
        
        // Hak akses untuk Staff - disesuaikan dengan jabatan
        $menu_akses['dashboard'] = true;
        
        // Mapping jabatan dengan hak akses
        switch ($jabatan_user) {
            case 'Perawat Spesialis Mata':
                $menu_akses['data_pelayanan_medis'] = true;
                $menu_akses['laporan'] = true;
                break;
                
            case 'Refaksionis/Optometris':
                // Tidak ada akses menu
                break;
                
            case 'Medical Record':
                $menu_akses['data_pelayanan_medis'] = true;
                $menu_akses['laporan'] = true;
                break;
                
            case 'IT Support':
                // IT Support dapat akses semua menu
                $menu_akses['data_master'] = true;
                $menu_akses['data_pelayanan_medis'] = true;
                $menu_akses['antrian'] = true;
                $menu_akses['transaksi'] = true;
                $menu_akses['laporan'] = true;
                break;
                
            case 'Kasir & Billing':
                // Kasir hanya akses transaksi
                $menu_akses['transaksi'] = true;
                break;
                
            case 'Administrasi':
                // Administrasi hanya akses antrian dan data pasien
                $menu_akses['antrian'] = true;
                $menu_akses['data_master'] = true;
                break;
                
            default:
                break;
        }
    }
    else {
        $query_user = "SELECT foto, nama_lengkap, email FROM users WHERE id_user = '$id_user'";
        $result_user = $db->koneksi->query($query_user);
        
        if ($result_user && $result_user->num_rows > 0) {
            $user_data = $result_user->fetch_assoc();
            $foto_user = $user_data['foto'];
            
            // Update nama dan email jika ada di tabel users
            if (!empty($user_data['nama_lengkap'])) {
                $nama_user = $user_data['nama_lengkap'];
            }
            if (!empty($user_data['email'])) {
                $email_user = $user_data['email'];
            }
            
            if (!empty($foto_user)) {
                if (strpos($foto_user, 'http') === 0) {
                    $foto_sidebar = $foto_user;
                    $foto_header = $foto_user;
                } else if (file_exists(dirname(__FILE__) . '/assets/images/user/' . $foto_user)) {
                    $foto_sidebar = 'assets/images/user/' . $foto_user;
                    $foto_header = 'assets/images/user/' . $foto_user;
                } else if (file_exists($foto_user)) {
                    $foto_sidebar = $foto_user;
                    $foto_header = $foto_user;
                }
            }
        }
    }
}

?>

<nav class="pc-sidebar">
  <div class="navbar-wrapper">
    <div class="m-header">
      <a href="dashboard.php" class="b-brand text-primary">
        <!-- ========   Change your logo from here   ============ -->
        <img src="assets/images/eyethicalogo3.png" class="img-fluid logo-lg" alt="logo" style="margin-bottom: 20px;">
      </a>
    </div>
    <div class="navbar-content">
      <div class="card pc-user-card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
              <img src="<?php echo $foto_sidebar; ?>" alt="user-image" class="user-avtar wid-45 rounded-circle" 
                   onerror="this.onerror=null; this.src='assets/images/user/avatar-1.jpg';" />
            </div>
            <div class="flex-grow-1 ms-3 me-2">
              <h6 class="mb-0"><?php echo htmlspecialchars(substr($nama_user, 0, 10)); ?></h6>
              <small><?php echo htmlspecialchars($jabatan_user); ?></small>
            </div>
            <a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
              <svg class="pc-icon">
                <use xlink:href="#custom-sort-outline"></use>
              </svg>
            </a>
          </div>
          <div class="collapse pc-user-links" id="pc_sidebar_userlink">
            <div class="pt-3">
              <a href="profile.php">
                <i class="ti ti-user"></i>
                <span>Akun Saya</span>
              </a>
              <!-- MODIFIED: Link logout di sidebar -->
              <a href="#" onclick="document.getElementById('logoutModal').style.display='flex'; return false;">
                <i class="ti ti-power"></i>
                <span>Keluar</span>
              </a>
            </div>
          </div>
        </div>
      </div>

      <ul class="pc-navbar">
        <li class="pc-item pc-caption">
          <label>Navigasi</label>
        </li>

        <?php if ($menu_akses['dashboard']): ?>
        <li class="pc-item">
          <a href="dashboard.php" class="pc-link">
            <span class="pc-micon">
              <svg class="pc-icon">
                <use xlink:href="#custom-status-up"></use>
              </svg>
            </span>
            <span class="pc-mtext">Dashboard</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($menu_akses['data_master']): ?>
        <li class="pc-item pc-caption">
          <label>Data Master</label>
        </li>
        <li class="pc-item">
          <a href="data-user.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-users"></i>
            </span>
            <span class="pc-mtext">Data User</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="dokter.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-users"></i>
            </span>
            <span class="pc-mtext">Data Dokter</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="staff.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-users"></i>
            </span>
            <span class="pc-mtext">Data Staff</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datapasien.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-users"></i>
            </span>
            <span class="pc-mtext">Data Pasien</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datadokumen.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-file-certificate"></i>
            </span>
            <span class="pc-mtext">Dokumen Dokter & Staff</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datajadwaldokter.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-calendar"></i></span
            ><span class="pc-mtext">Jadwal Dokter</span></a
          >
        </li>
        <li class="pc-item">
          <a href="dataobat.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-pill"></i>
            </span>
            <span class="pc-mtext">Data Obat</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="dataalatmedis.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-stethoscope"></i>
            </span>
            <span class="pc-mtext">Alat Medis</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datatindakanmedis.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-activity"></i>
            </span>
            <span class="pc-mtext">Tindakan Medis</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($menu_akses['data_pelayanan_medis']): ?>
        <li class="pc-item pc-caption">
          <label>Data Pelayanan Medis</label>
        </li>
        <li class="pc-item">
          <a href="datariwayatmedis.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-stethoscope"></i>
            </span>
            <span class="pc-mtext">Riwayat Medis</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datarekammedis.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-report-medical"></i>
            </span>
            <span class="pc-mtext">Rekam Medis</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="datapemeriksaanmata.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-eye"></i>
            </span>
            <span class="pc-mtext">Pemeriksaan Mata</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="dataresepkacamata.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-eyeglass"></i>
            </span>
            <span class="pc-mtext">Resep Kacamata</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="dataresepobat.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-file-text"></i>
            </span>
            <span class="pc-mtext">Resep Obat</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="data-detail-resep-obat.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-clipboard-list"></i>
            </span>
            <span class="pc-mtext">Detail Resep Obat</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="data-detail-tindakan-medis.php" class="pc-link">
            <span class="pc-micon">
              <i class="ti ti-activity"></i>
            </span>
            <span class="pc-mtext">Detail Tindakan Medis</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($menu_akses['antrian']): ?>
        <li class="pc-item pc-caption">
          <label>Antrian</label>
        </li>
        <li class="pc-item">
          <a href="dataantrian.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-report"></i></span
            ><span class="pc-mtext">Antrian</span></a
          >
        </li>
        <li class="pc-item">
          <a href="panggilan-antrian.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-ticket"></i></span
            ><span class="pc-mtext">Panggilan Antrian</span></a
          >
        </li>
        <?php endif; ?>

        <?php if ($menu_akses['transaksi']): ?>
        <li class="pc-item pc-caption">
          <label>Transaksi</label>
        </li>
        <li class="pc-item">
          <a href="transaksi.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-report-money"></i></span
            ><span class="pc-mtext">Data Transaksi</span></a
          >
        </li>
        <li class="pc-item">
          <a href="data-detail-transaksi.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-report-money"></i></span
            ><span class="pc-mtext">Detail Transaksi</span></a
          >
        </li>
        <li class="pc-item">
          <a href="laporan-transaksi.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-report-money"></i></span
            ><span class="pc-mtext">Laporan Transaksi</span></a
          >
        </li>
        <?php endif; ?>

        <?php if ($menu_akses['laporan']): ?>
        <li class="pc-item pc-caption">
          <label>Laporan</label>
        </li>
        <li class="pc-item">
          <a href="laporan-data-pasien.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-files"></i></span
            ><span class="pc-mtext">Laporan Data Pasien</span></a
          >
        </li>
        <li class="pc-item">
          <a href="laporan-rekam-medis.php" class="pc-link"
            ><span class="pc-micon">
              <i class="ti ti-files"></i></span
            ><span class="pc-mtext">Laporan Rekam Medis</span></a
          >
        </li>
        <?php endif; ?>
      </ul>
      <div class="card pc-user-card mt-3">
        <div class="card-body text-center">
          <img src="assets/images/faviconeyethica.png" alt="img" class="img-fluid w-50" />
          <h5 class="mb-0 mt-1">Eyethica</h5>
          <p>Sistem Informasi Poliklinik Mata</p>
          <div class="mt-2">
            <small class="text-muted">Version 1.0</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>
<!-- [ Sidebar Menu ] end --> <!-- [ Header Topbar ] start -->
<header class="pc-header">
  <div class="header-wrapper"> <!-- [Mobile Media Block] start -->
<div class="me-auto pc-mob-drp">
  <ul class="list-unstyled">
    <!-- ======= Menu collapse Icon ===== -->
    <li class="pc-h-item pc-sidebar-collapse">
      <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
        <i class="ti ti-menu-2"></i>
      </a>
    </li>
    <li class="pc-h-item pc-sidebar-popup">
      <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
        <i class="ti ti-menu-2"></i>
      </a>
    </li>
  </ul>
</div>
<!-- [Mobile Media Block end] -->
<div class="ms-auto">
  <ul class="list-unstyled">
    <li class="dropdown pc-h-item">
      <?php
      // Hitung notifikasi belum dibaca — menggunakan tabel notifikasi_dibaca
      $notif_count_awal = 0;
      if ($id_user) {
          // Ambil waktu terakhir dibaca dari tabel notifikasi_dibaca
          $stmt_lr = $db->koneksi->prepare(
              "SELECT terakhir_dibaca FROM notifikasi_dibaca WHERE id_user = ? LIMIT 1"
          );
          $stmt_lr->bind_param('i', $id_user);
          $stmt_lr->execute();
          $row_lr = $stmt_lr->get_result()->fetch_assoc();
          $stmt_lr->close();

          if (!empty($row_lr['terakhir_dibaca'])) {
              // Sudah pernah membuka notifikasi sebelumnya
              $terakhir_dibaca_awal = $row_lr['terakhir_dibaca'];
          } else {
              // Belum pernah membuka notifikasi — tampilkan semua aktivitas sebagai baru
              $terakhir_dibaca_awal = '1970-01-01 00:00:00';
          }

          // Hitung aktivitas setelah waktu terakhir dibaca
          $stmt_nc = $db->koneksi->prepare(
              "SELECT COUNT(*) AS total FROM aktivitas_user WHERE waktu > ?"
          );
          $stmt_nc->bind_param('s', $terakhir_dibaca_awal);
          $stmt_nc->execute();
          $res_nc = $stmt_nc->get_result()->fetch_assoc();
          $notif_count_awal = (int) $res_nc['total'];
          $stmt_nc->close();
      }
      ?>
      <a
        class="pc-head-link dropdown-toggle arrow-none me-0"
        data-bs-toggle="dropdown"
        href="#"
        role="button"
        aria-haspopup="false"
        aria-expanded="false"
        id="notif-bell-toggle"
      >
        <svg class="pc-icon">
          <use xlink:href="#custom-notification"></use>
        </svg>
        <span
          class="badge bg-success pc-h-badge"
          id="notif-badge"
          <?php echo ($notif_count_awal === 0) ? 'style="display:none;"' : ''; ?>
        ><?php echo $notif_count_awal; ?></span>
      </a>
      <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
        <div class="dropdown-header d-flex align-items-center justify-content-between">
          <h5 class="m-0">Notifikasi Aktivitas Pengguna</h5>
          <a href="#!" class="btn btn-link btn-sm">Lihat Semua</a>
        </div>
        <div class="dropdown-body text-wrap header-notification-scroll position-relative" style="max-height: calc(100vh - 215px)">
          <?php
          // Ambil data aktivitas user dari database
          $aktivitas = $db->tampil_aktivitas_user_berdasarkan_waktu();
          
          if ($aktivitas['tidak_ada']) {
          ?>
          <div class="text-center py-4">
            <svg class="pc-icon text-muted" style="width: 48px; height: 48px;">
              <use xlink:href="#custom-notification-off"></use>
            </svg>
            <p class="text-muted mt-3">Tidak ada aktivitas terbaru</p>
          </div>
          <?php
          } else {
            // Tampilkan aktivitas hari ini
            if (!empty($aktivitas['hari_ini'])) {
          ?>
          <p class="text-span">Hari Ini</p>
          <?php
              foreach ($aktivitas['hari_ini'] as $item) {
                  $waktu = new DateTime($item['waktu']);
                  $time_ago = $waktu->format('H:i');
          ?>
          <div class="card mb-2">
            <div class="card-body">
              <div class="d-flex">
                <div class="flex-shrink-0">
                  <?php
                  // Tentukan icon berdasarkan jenis aktivitas
                  $jenis_aktivitas = strtolower($item['jenis'] ?? '');
                  $icon_class = '';
                  
                  switch($jenis_aktivitas) {
                    case 'edit':
                    case 'update':
                    case 'ubah':
                      $icon_class = 'fas fa-edit';
                      break;
                    case 'tambah':
                    case 'create':
                    case 'insert':
                    case 'baru':
                      $icon_class = 'fas fa-plus';
                      break;
                    case 'hapus':
                    case 'delete':
                    case 'remove':
                      $icon_class = 'fas fa-trash';
                      break;
                    default:
                      $icon_class = 'fas fa-info-circle';
                      break;
                  }
                  ?>
                  <i class="<?php echo $icon_class; ?> text-primary"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                  <span class="float-end text-sm text-muted"><?php echo $time_ago; ?></span>
                  <p class="mb-0">
                    <?php echo htmlspecialchars($item['keterangan'] ?? 'Tidak ada keterangan'); ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <?php
              }
            }
            
            // Tampilkan aktivitas kemarin
            if (!empty($aktivitas['kemarin'])) {
          ?>
          <p class="text-span">Kemarin</p>
          <?php
              foreach ($aktivitas['kemarin'] as $item) {
                  $waktu = new DateTime($item['waktu']);
                  $time_ago = $waktu->format('H:i');
          ?>
          <div class="card mb-2">
            <div class="card-body">
              <div class="d-flex">
                <div class="flex-shrink-0">
                  <?php
                  // Tentukan icon berdasarkan jenis aktivitas
                  $jenis_aktivitas = strtolower($item['jenis'] ?? '');
                  $icon_class = '';
                  
                  switch($jenis_aktivitas) {
                    case 'edit':
                    case 'update':
                    case 'ubah':
                      $icon_class = 'fas fa-edit';
                      break;
                    case 'tambah':
                    case 'create':
                    case 'insert':
                    case 'baru':
                      $icon_class = 'fas fa-plus';
                      break;
                    case 'hapus':
                    case 'delete':
                    case 'remove':
                      $icon_class = 'fas fa-trash';
                      break;
                    default:
                      $icon_class = 'fas fa-info-circle';
                      break;
                  }
                  ?>
                  <i class="<?php echo $icon_class; ?> text-primary"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                  <span class="float-end text-sm text-muted"><?php echo $time_ago; ?></span>
                  <h6 class="text-body mb-1"><?php echo htmlspecialchars($item['nama_user'] ?? 'Unknown User'); ?></h6>
                  <p class="mb-0">
                    <strong><?php echo htmlspecialchars(ucfirst($item['jenis'] ?? 'Aktivitas')); ?>:</strong> 
                    <?php echo htmlspecialchars($item['keterangan'] ?? 'Tidak ada keterangan'); ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <?php
              }
            }
          }
          ?>
        </div>
        <div class="text-center py-2">
          <a href="#!" class="link-danger">Hapus Semua Notifikasi</a>
        </div>
      </div>
    </li>
    <li class="dropdown pc-h-item header-user-profile">
      <a
        class="pc-head-link dropdown-toggle arrow-none me-0"
        data-bs-toggle="dropdown"
        href="#"
        role="button"
        aria-haspopup="false"
        data-bs-auto-close="outside"
        aria-expanded="false"
      >
        <img src="<?php echo $foto_header; ?>" alt="user-image" class="user-avtar" 
             onerror="this.onerror=null; this.src='assets/images/user/avatar-2.jpg';" />
      </a>
      <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
        <div class="dropdown-header d-flex align-items-center justify-content-between">
          <h5 class="m-0">Profile</h5>
        </div>
        <div class="dropdown-body">
          <div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">
            <div class="d-flex mb-1">
              <div class="flex-shrink-0">
                <img src="<?php echo $foto_header; ?>" alt="user-image" class="user-avtar wid-35" 
                     onerror="this.onerror=null; this.src='assets/images/user/avatar-2.jpg';" />
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="mb-1"><?php echo htmlspecialchars($nama_user); ?></h6>
                <span><?php echo htmlspecialchars($jabatan_user); ?></span>
              </div>
            </div>
            <hr class="border-secondary border-opacity-50" />
            <p class="text-span">Manage</p>
            <a href="profile.php" class="dropdown-item">
              <span>
                <svg class="pc-icon text-muted me-2">
                  <use xlink:href="#custom-setting-outline"></use>
                </svg>
                <span>Settings</span>
              </span>
            </a>
            <a href="profile.php" class="dropdown-item">
              <span>
                <svg class="pc-icon text-muted me-2">
                  <use xlink:href="#custom-lock-outline"></use>
                </svg>
                <span>Change Password</span>
              </span>
            </a>
            <hr class="border-secondary border-opacity-50" />
            <!-- MODIFIED: Tombol logout di dropdown profile -->
            <div class="d-grid mb-3">
              <a href="#" onclick="document.getElementById('logoutModal').style.display='flex'; return false;" class="btn btn-primary">
                <svg class="pc-icon me-2">
                  <use xlink:href="#custom-logout-1-outline"></use>
                </svg>
                Logout
              </a>
            </div>
          </div>
        </div>
      </div>
    </li>
  </ul>
</div>
 </div>
</header>
<!-- [ Modal Konfirmasi Logout ] -->
<div id="logoutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; padding:24px; max-width:400px; width:90%; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
    <div style="margin-bottom:16px;">
      <i class="ti ti-logout" style="font-size:48px; color:#dc3545;"></i>
    </div>
    <h5 style="margin-bottom:8px;">Konfirmasi Logout</h5>
    <p style="color:#6c757d; margin-bottom:24px;">Apakah Anda yakin ingin keluar dari sistem?</p>
    <div style="display:flex; gap:12px; justify-content:center;">
      <button onclick="document.getElementById('logoutModal').style.display='none';" style="padding:8px 24px; border:1px solid #dee2e6; background:#fff; border-radius:6px; cursor:pointer; font-size:14px;">Batal</button>
      <a href="autentikasi/sign-out.php" style="padding:8px 24px; background:#dc3545; color:#fff; border-radius:6px; text-decoration:none; font-size:14px;">Ya, Logout</a>
    </div>
  </div>
</div>
<!-- [ Modal Konfirmasi Logout ] end -->
<!-- [ Header ] end -->

<!-- ============================================================ -->
<!-- Script Notifikasi Dinamis                                     -->
<!-- ============================================================ -->
<script>
(function () {
  'use strict';

  const badge       = document.getElementById('notif-badge');
  const bellToggle  = document.getElementById('notif-bell-toggle');
  const POLL_INTERVAL = 30000; // cek setiap 30 detik

  // ── Perbarui tampilan badge ────────────────────────────────────
  function updateBadge(count) {
    if (!badge) return;
    if (count > 0) {
      badge.textContent   = count;
      badge.style.display = '';       // tampilkan
    } else {
      badge.style.display = 'none';   // sembunyikan jika 0
    }
  }

  // ── Ambil jumlah notifikasi dari server ───────────────────────
  function fetchNotifCount() {
    fetch('notifikasi/get_notif_count.php', { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) { updateBadge(data.count || 0); })
      .catch(function () { /* abaikan error jaringan */ });
  }

  // ── Tandai notifikasi sudah dibaca ────────────────────────────
  function markAsRead() {
    fetch('notifikasi/mark_notif_read.php', {
      method      : 'POST',
      credentials : 'same-origin',
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          updateBadge(0); // langsung reset badge ke 0
        }
      })
      .catch(function () {});
  }

  // ── Klik bel: tandai dibaca saat dropdown dibuka ──────────────
  if (bellToggle) {
    bellToggle.addEventListener('click', function () {
      // aria-expanded masih "false" sebelum Bootstrap mengubahnya
      // artinya dropdown baru akan DIBUKA
      if (bellToggle.getAttribute('aria-expanded') === 'false') {
        markAsRead();
      }
    });
  }

  // ── Polling tiap 30 detik untuk notifikasi baru ───────────────
  fetchNotifCount(); // cek segera saat halaman dimuat
  setInterval(fetchNotifCount, POLL_INTERVAL);
})();
</script>