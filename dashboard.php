<?php
session_start();

include "koneksi.php";
$db = new database();

if (!isset($_SESSION['id_user'])) {
    header("Location: dashboard.php");
    exit();
}

// Cek role user
if ($_SESSION['role'] != 'Dokter' && $_SESSION['role'] != 'Staff') {
    header("Location: unauthorized.php");
    exit();
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// === DATA UNTUK DASHBOARD ===
$jumlahdata_users = $db->jumlahdata_users();
$jumlahdata_dokter = $db->jumlahdata_dokter();
$jumlahdata_staff = $db->jumlahdata_staff();
$jumlahdata_pasien = $db->jumlahdata_pasien();
$jumlahdata_antrian = $db->jumlahdata_antrian();
$jumlahdata_rekam = $db->jumlahdata_rekam();
$total_pendapatan_sukses = $db->getTotalPendapatanByStatus('Lunas');
$total_pendapatan_terjeda = $db->getTotalPendapatanByStatus('Belum Bayar');
$jumlahdata_transaksi = $db->jumlahdata_transaksi();

// DATA BARU UNTUK PERBANDINGAN
$perbandingan_pasien_baru = $db->get_perbandingan_pasien_baru();
$pasien_hari_ini = $db->get_pasien_hari_ini();
$perbandingan_harian = $db->get_perbandingan_pasien_harian();
$antrian_bulan_ini = $db->get_antrian_bulan_ini();
$perbandingan_antrian = $db->get_perbandingan_antrian();
$kontrol_bulan_ini = $db->get_kontrol_bulan_ini();
$perbandingan_kontrol = $db->get_perbandingan_kontrol();
$kunjungan_chart = $db->get_kunjungan_per_bulan_chart();

// Data untuk chart (encode ke JSON)
$chart_bulan = json_encode($kunjungan_chart['bulan']);
$chart_baru = json_encode($kunjungan_chart['baru']);
$chart_kontrol = json_encode($kunjungan_chart['kontrol']);

// Data untuk sparkline
$data_7_hari = $db->get_data_7_hari_terakhir();
$data_antrian_12_bulan = $db->get_data_antrian_12_bulan();

// Data untuk kunjungan bulan ini
$jumlahdata_kunjungan_bulan_ini = $db->jumlahdata_kunjungan_bulan_ini();
$jumlahdata_pasien_hari_ini = $db->jumlahdata_pasien_hari_ini();
$jumlahdata_kunjungan_hari_ini_by_jenis = $db->jumlahdata_kunjungan_hari_ini_by_jenis();
?>

<!DOCTYPE html>
<html lang="en">
  <!-- [Head] start -->

  <head>
    <title>Dashboard - Sistem Informasi Poliklinik Mata Eyethica</title>
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

<script src="assets/js/plugins/apexcharts.min.js"></script>
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
                  <li class="breadcrumb-item" aria-current="page">Home</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Home</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
          <div class="col-md-6 col-xxl-3">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="avtar avtar-s bg-light-primary">
                      <i class="ti ti-users" style="font-size: 25px;"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0">Total Pasien</h6>
                  </div>
                </div>
                <div class="bg-body p-3 mt-3 rounded">
                  <div class="mt-3 row align-items-center">
                    <div class="col-5">
                      <h2 class="mb-1"><?php echo number_format($jumlahdata_pasien); ?></h2>
                    </div>
                    <div class="col-7">
                      <p class="text-<?php echo $perbandingan_pasien_baru['arah'] == 'up' ? 'primary' : 'danger'; ?> mb-0">
                        <i class="ti ti-arrow-<?php echo $perbandingan_pasien_baru['arah'] == 'up' ? 'up' : 'down'; ?>-right"></i> 
                        <?php echo abs($perbandingan_pasien_baru['persen']); ?>%
                      </p>
                      <small class="text-muted">vs bulan lalu</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xxl-3">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="avtar avtar-s bg-light-warning">
                      <i class="ti ti-calendar" style="font-size: 25px;"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0">Pasien Hari Ini</h6>
                  </div>
                </div>
                <div class="bg-body p-3 mt-3 rounded">
                  <div class="mt-3 row align-items-center">
                    <div class="col-5">
                      <h2 class="mb-1"><?php echo $pasien_hari_ini; ?></h2>
                    </div>
                    <div class="col-7">
                      <p class="text-<?php echo $perbandingan_harian['arah'] == 'up' ? 'warning' : 'danger'; ?> mb-0">
                        <i class="ti ti-arrow-<?php echo $perbandingan_harian['arah'] == 'up' ? 'up' : 'down'; ?>-right"></i> 
                        <?php echo abs($perbandingan_harian['persen']); ?>%
                      </p>
                      <small class="text-muted">vs kemarin</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xxl-3">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="avtar avtar-s bg-light-success">
                      <i class="ti ti-ticket" style="font-size: 25px;"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0">Antrian Bulan Ini</h6>
                  </div>
                </div>
                <div class="bg-body p-3 mt-3 rounded">
                  <div class="mt-3 row align-items-center">
                    <div class="col-5">
                      <h2 class="mb-1"><?php echo number_format($antrian_bulan_ini); ?></h2>
                    </div>
                    <div class="col-7">
                      <p class="text-<?php echo $perbandingan_antrian['arah'] == 'up' ? 'success' : 'danger'; ?> mb-0">
                        <i class="ti ti-arrow-<?php echo $perbandingan_antrian['arah'] == 'up' ? 'up' : 'down'; ?>-right"></i> 
                        <?php echo abs($perbandingan_antrian['persen']); ?>%
                      </p>
                      <small class="text-muted">vs bulan lalu</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xxl-3">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="avtar avtar-s bg-light-danger">
                      <i class="ti ti-calendar-time" style="font-size: 25px;"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0">Kontrol Bulan Ini</h6>
                  </div>
                </div>
                <div class="bg-body p-3 mt-3 rounded">
                  <div class="mt-3 row align-items-center">
                    <div class="col-5">
                      <h2 class="mb-1"><?php echo number_format($kontrol_bulan_ini); ?></h2>
                    </div>
                    <div class="col-7">
                      <p class="text-<?php echo $perbandingan_kontrol['arah'] == 'up' ? 'danger' : 'success'; ?> mb-0">
                        <i class="ti ti-arrow-<?php echo $perbandingan_kontrol['arah'] == 'up' ? 'up' : 'down'; ?>-right"></i> 
                        <?php echo abs($perbandingan_kontrol['persen']); ?>%
                      </p>
                      <small class="text-muted">vs bulan lalu</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-12">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Kunjungan Pasien Per Bulan (<?php echo date('Y'); ?>)</h5>
              </div>
              <div class="card-body">
                <div id="customer-rate-graph"></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-body border-bottom pb-0">
                <div class="d-flex align-items-center justify-content-between">
                  <h5 class="mb-0">Transaksi</h5>
                </div>
                <ul class="nav nav-tabs analytics-tab" id="myTab" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button
                      class="nav-link active"
                      id="analytics-tab-1"
                      data-bs-toggle="tab"
                      data-bs-target="#analytics-tab-1-pane"
                      type="button"
                      role="tab"
                      aria-controls="analytics-tab-1-pane"
                      aria-selected="true"
                      >Semua Transaksi</button
                    >
                  </li>
                  <li class="nav-item" role="presentation">
                    <button
                      class="nav-link"
                      id="analytics-tab-2"
                      data-bs-toggle="tab"
                      data-bs-target="#analytics-tab-2-pane"
                      type="button"
                      role="tab"
                      aria-controls="analytics-tab-2-pane"
                      aria-selected="false"
                      >Sukses</button
                    >
                  </li>
                  <li class="nav-item" role="presentation">
                    <button
                      class="nav-link"
                      id="analytics-tab-3"
                      data-bs-toggle="tab"
                      data-bs-target="#analytics-tab-3-pane"
                      type="button"
                      role="tab"
                      aria-controls="analytics-tab-3-pane"
                      aria-selected="false"
                      >Terjeda</button
                    >
                  </li>
                </ul>
              </div>
              <div class="tab-content" id="myTabContent">
                <!-- Tab Semua Transaksi -->
                <div
                  class="tab-pane fade show active"
                  id="analytics-tab-1-pane"
                  role="tabpanel"
                  aria-labelledby="analytics-tab-1"
                  tabindex="0"
                >
                  <ul class="list-group list-group-flush">
                    <?php
                    $transaksi_semua = $db->tampil_5_transaksi_terakhir();
                    if (empty($transaksi_semua)): ?>
                      <li class="list-group-item text-center text-muted">
                        Tidak ada data transaksi
                      </li>
                    <?php else: ?>
                      <?php foreach ($transaksi_semua as $transaksi): ?>
                        <li class="list-group-item">
                          <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                              <div class="avtar avtar-s border"> 
                                <?php echo strtoupper(substr($transaksi['nama_pasien'], 0, 2)); ?>
                              </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="row g-1">
                                <div class="col-6">
                                  <h6 class="mb-0"><?php echo htmlspecialchars($transaksi['nama_pasien']); ?></h6>
                                  <p class="text-muted mb-0">
                                    <small>
                                        <?php 
                                        if (!empty($transaksi['tanggal_transaksi'])) {
                                            echo date('d M Y', strtotime($transaksi['tanggal_transaksi']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </small>
                                </p>
                                </div>
                                <div class="col-6 text-end">
                                    <h6 class="mb-1">
                                        Rp <?php 
                                        $grand_total = $transaksi['grand_total'] ?? 0;
                                        echo number_format(floatval($grand_total), 0, ',', '.'); 
                                        ?>
                                    </h6>
                                    <p class="text-success mb-0">
                                        <i class="ti ti-arrow-up-right"></i> 
                                        <?php echo htmlspecialchars($transaksi['metode_pembayaran'] ?? '-'); ?>
                                    </p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>

                <!-- Tab Sukses (Lunas) -->
                <div class="tab-pane fade" id="analytics-tab-2-pane" role="tabpanel" aria-labelledby="analytics-tab-2" tabindex="0">
                  <ul class="list-group list-group-flush">
                    <?php
                    $transaksi_lunas = $db->tampil_5_transaksi_terakhir_lunas();
                    if (empty($transaksi_lunas)): ?>
                      <li class="list-group-item text-center text-muted">
                        Tidak ada transaksi sukses
                      </li>
                    <?php else: ?>
                      <?php foreach ($transaksi_lunas as $transaksi): ?>
                        <li class="list-group-item">
                          <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                              <div class="avtar avtar-s border"> 
                                <?php echo strtoupper(substr($transaksi['nama_pasien'], 0, 2)); ?>
                              </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="row g-1">
                                <div class="col-6">
                                  <h6 class="mb-0"><?php echo htmlspecialchars($transaksi['nama_pasien']); ?></h6>
                                  <p class="text-muted mb-0">
                                    <small>
                                        <?php 
                                        if (!empty($transaksi['tanggal_transaksi'])) {
                                            echo date('d M Y', strtotime($transaksi['tanggal_transaksi']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </small>
                                </p>
                                </div>
                                <div class="col-6 text-end">
                                    <h6 class="mb-1">
                                        Rp <?php 
                                        $grand_total = $transaksi['grand_total'] ?? 0;
                                        echo number_format(floatval($grand_total), 0, ',', '.'); 
                                        ?>
                                    </h6>
                                    <p class="text-success mb-0">
                                        <i class="ti ti-arrow-up-right"></i> 
                                        <?php echo htmlspecialchars($transaksi['metode_pembayaran'] ?? '-'); ?>
                                    </p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>

                <!-- Tab Terjeda (Belum Bayar) -->
                <div class="tab-pane fade" id="analytics-tab-3-pane" role="tabpanel" aria-labelledby="analytics-tab-3" tabindex="0">
                  <ul class="list-group list-group-flush">
                    <?php
                    $transaksi_belum_bayar = $db->tampil_5_transaksi_terakhir_belum_bayar();
                    if (empty($transaksi_belum_bayar)): ?>
                      <li class="list-group-item text-center text-muted">
                        Tidak ada transaksi terjeda
                      </li>
                    <?php else: ?>
                      <?php foreach ($transaksi_belum_bayar as $transaksi): ?>
                        <li class="list-group-item">
                          <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                              <div class="avtar avtar-s border"> 
                                <?php echo strtoupper(substr($transaksi['nama_pasien'], 0, 2)); ?>
                              </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="row g-1">
                                <div class="col-6">
                                  <h6 class="mb-0"><?php echo htmlspecialchars($transaksi['nama_pasien']); ?></h6>
                                  <p class="text-muted mb-0">
                                      <small>
                                          <?php 
                                          if (!empty($transaksi['tanggal_transaksi'])) {
                                              echo date('d M Y', strtotime($transaksi['tanggal_transaksi']));
                                          } else {
                                              echo '-';
                                          }
                                          ?>
                                      </small>
                                  </p>
                                </div>
                                <div class="col-6 text-end">
                                    <h6 class="mb-1">
                                        Rp <?php 
                                        $grand_total = $transaksi['grand_total'] ?? 0;
                                        echo number_format(floatval($grand_total), 0, ',', '.'); 
                                        ?>
                                    </h6>
                                    <p class="text-danger mb-0">
                                        <i class="ti ti-arrow-down-left"></i> 
                                        <?php echo htmlspecialchars($transaksi['metode_pembayaran'] ?? '-'); ?>
                                    </p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
              <div class="card-footer">
                <div class="row g-2">
                  <div class="col-md-6">
                    <div class="d-grid">
                      <a href="datatransaksi.php" class="btn btn-outline-secondary d-grid">
                        <span class="text-truncate w-100">View all Transaction History</span>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <h5 class="mb-0">Total Pendapatan Bulan ini</h5>
                </div>
                <div id="income-distribution-chart" style="min-height: 400px;"></div>
                <div class="row g-3 mt-3">
                  <div class="col-sm-6">
                    <div class="bg-body p-3 rounded">
                      <div class="d-flex align-items-center mb-2">
                        <div class="flex-shrink-0">
                          <span class="p-1 d-block bg-primary rounded-circle">
                            <span class="visually-hidden">New alerts</span>
                          </span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                          <p class="mb-0">Pendapatan Transaksi Sukses</p>
                        </div>
                      </div>
                      <h6 class="mb-0"
                        ><?php echo formatRupiah($total_pendapatan_sukses); ?> <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small></h6
                      >
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="bg-body p-3 rounded">
                      <div class="d-flex align-items-center mb-2">
                        <div class="flex-shrink-0">
                          <span class="p-1 d-block bg-danger rounded-circle">
                            <span class="visually-hidden">New alerts</span>
                          </span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                          <p class="mb-0">Pendapatan Transaksi Terjeda</p>
                        </div>
                      </div>
                      <h6 class="mb-0"
                        ><?php echo formatRupiah($total_pendapatan_terjeda); ?> <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small></h6
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->
    <!-- [Page Specific JS] start -->
    <script src="assets/js/plugins/apexcharts.min.js"></script>
    <script src="assets/js/pages/dashboard-default.js"></script>
    <!-- [Page Specific JS] end -->
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
        // Data untuk chart dari PHP
        window.chartBulan = <?php echo $chart_bulan; ?>;
        window.chartBaru = <?php echo $chart_baru; ?>;
        window.chartKontrol = <?php echo $chart_kontrol; ?>;
        window.dataHarian = <?php echo json_encode($data_7_hari); ?>;
        window.dataAntrian12Bulan = <?php echo json_encode($data_antrian_12_bulan); ?>;
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Data dari PHP
    var pendapatanSukses = <?php echo $total_pendapatan_sukses ?: 0; ?>;
    var pendapatanTerjeda = <?php echo $total_pendapatan_terjeda ?: 0; ?>;
    var totalPendapatan = pendapatanSukses + pendapatanTerjeda;
    
    // Hitung persentase
    var persenSukses = totalPendapatan > 0 ? (pendapatanSukses / totalPendapatan * 100) : 0;
    var persenTerjeda = totalPendapatan > 0 ? (pendapatanTerjeda / totalPendapatan * 100) : 0;
    
    // Format angka untuk tooltip
    var formatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });

    // Options untuk chart
    var options = {
        series: [persenSukses, persenTerjeda],
        chart: {
            type: 'donut',
            height: 400
        },
        colors: ['#4680ff', '#dc2626'],
        labels: ['Transaksi Sukses', 'Transaksi Terjeda'],
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val.toFixed(1) + "%";
            },
            dropShadow: {
                enabled: false
            }
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '14px',
                            fontWeight: 600,
                            color: '#6B7280'
                        },
                        value: {
                            show: true,
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#111827',
                            formatter: function (val) {
                                return formatter.format(totalPendapatan);
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(value, { seriesIndex }) {
                    var amount = seriesIndex === 0 ? pendapatanSukses : pendapatanTerjeda;
                    return formatter.format(amount) + ' (' + value.toFixed(1) + '%)';
                }
            }
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    height: 180
                },
                legend: {
                    position: 'bottom'
                }
            }
        }]
    };

    // Render chart
    var chart = new ApexCharts(document.querySelector("#income-distribution-chart"), options);
    chart.render();
});
</script>

  </body>
  <!-- [Body] end -->
</html>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>