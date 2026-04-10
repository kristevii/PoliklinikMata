<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

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
              WHERE ddr.id_resep_obat = '$id_resep_obat'";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getDetailTindakanByRekam($db, $id_rekam) {
    $query = "SELECT dtm.*, tm.nama_tindakan, tm.tarif as harga_tindakan 
              FROM data_detail_tindakan_medis dtm
              JOIN data_tindakan_medis tm ON dtm.id_tindakan_medis = tm.id_tindakan_medis
              WHERE dtm.id_rekam = '$id_rekam'";
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
    $query = "SELECT * FROM data_detail_transaksi WHERE id_transaksi = '$id_transaksi'";
    $result = $db->koneksi->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function formatRupiah($angka) {
    if (empty($angka) && $angka !== 0) return '-';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    return date('d-m-Y', strtotime($tanggal));
}

function formatTanggalWaktu($tanggal) {
    if (empty($tanggal)) return '-';
    return date('d-m-Y H:i', strtotime($tanggal));
}

// PROSES CETAK
if (isset($_GET['cetak_riwayat']) && isset($_GET['id_pasien'])) {
    $id_pasien = $_GET['id_pasien'];
    
    // Ambil data pasien
    $pasien = $db->get_pasien_by_id($id_pasien);
    if (!$pasien) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Pasien tidak ditemukan';
        header("Location: ../../datariwayatmedis.php");
        exit();
    }
    
    // Ambil semua data rekam medis dan terkait
    $rekam_medis_list = getRekamMedisByPasien($db, $id_pasien);
    
    $all_resep_obat_details = [];
    $all_transaksi_details = [];
    
    foreach ($rekam_medis_list as $key => $rekam) {
        $id_rekam = $rekam['id_rekam'];
        $rekam_medis_list[$key]['pemeriksaan_mata'] = getPemeriksaanMataByRekam($db, $id_rekam);
        $rekam_medis_list[$key]['detail_tindakan'] = getDetailTindakanByRekam($db, $id_rekam);
        $rekam_medis_list[$key]['resep_kacamata'] = getResepKacamataByRekam($db, $id_rekam);
        $rekam_medis_list[$key]['resep_obat'] = getResepObatByRekam($db, $id_rekam);
        $rekam_medis_list[$key]['transaksi'] = getTransaksiByRekam($db, $id_rekam);
        
        foreach ($rekam_medis_list[$key]['resep_obat'] as $ro) {
            $details = getDetailResepObatByResep($db, $ro['id_resep_obat']);
            foreach ($details as $detail) {
                $detail['id_rekam'] = $id_rekam;
                $all_resep_obat_details[] = $detail;
            }
        }
        
        foreach ($rekam_medis_list[$key]['transaksi'] as $tr) {
            $details = getDetailTransaksiByTransaksi($db, $tr['id_transaksi']);
            foreach ($details as $detail) {
                $detail['id_rekam'] = $id_rekam;
                $all_transaksi_details[] = $detail;
            }
        }
    }
    
    $total_biaya = 0;
    foreach ($rekam_medis_list as $rekam) {
        $total_biaya += $rekam['biaya'] ?? 0;
        foreach ($rekam['transaksi'] as $tr) {
            $total_biaya += $tr['grand_total'] ?? 0;
        }
    }
    
    $jenis_kelamin = $pasien['jenis_kelamin_pasien'] ?? '-';
    if ($jenis_kelamin == 'L') $jenis_kelamin = 'Laki-laki';
    elseif ($jenis_kelamin == 'P') $jenis_kelamin = 'Perempuan';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Riwayat Pasien - <?= htmlspecialchars($pasien['nama_pasien']) ?></title>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @media print {
            .no-print { display: none !important; }
            @page {
                size: A4;
                margin: 8mm;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 0;
                padding: 0;
            }
        }
        
        body { 
            font-family: 'Arial', 'Helvetica', sans-serif; 
            font-size: 9pt; 
            line-height: 1.3;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        
        .container { 
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }
        
        /* Header */
        .header-klinik { 
            text-align: center; 
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 2px solid #000;
        }
        
        .klinik-name { 
            font-size: 14pt; 
            font-weight: bold;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }
        
        .klinik-address, .klinik-contact { 
            font-size: 8pt; 
            margin-bottom: 0.5mm;
        }
        
        /* Report Header */
        .report-header { 
            text-align: center; 
            margin-bottom: 5mm;
        }
        
        .report-title { 
            font-size: 12pt; 
            font-weight: bold; 
            margin: 2mm 0;
            text-decoration: underline;
        }
        
        .patient-id { 
            font-size: 10pt; 
            margin-bottom: 1mm;
            font-weight: bold;
        }
        
        .print-date {
            font-size: 8pt;
        }
        
        /* Tabel Informasi Pasien */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 5mm;
        }
        
        .info-table td {
            padding: 1mm 2mm;
            border: 0.5pt solid #000;
            vertical-align: top;
        }
        
        .info-table td.label {
            width: 18%;
            font-weight: bold;
            background-color: #f5f5f5;
        }
        
        /* Sections */
        .section { 
            margin-bottom: 5mm;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 1.5mm 3mm;
            margin-bottom: 3mm;
            border-left: 3px solid #000;
        }
        
        .subsection-title {
            font-size: 9pt;
            font-weight: bold;
            margin: 3mm 0 2mm 0;
            padding-left: 2mm;
            border-left: 2px solid #666;
        }
        
        /* Tabel Data */
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 8pt;
            margin-bottom: 3mm;
        }
        
        .data-table th { 
            background-color: #333;
            color: white;
            text-align: left; 
            padding: 1.5mm; 
            border: 0.5pt solid #000;
            font-weight: bold;
        }
        
        .data-table td { 
            padding: 1.5mm; 
            border: 0.5pt solid #000; 
            vertical-align: top;
        }
        
        /* Kartu Rekam Medis */
        .rekam-card {
            margin-bottom: 5mm;
            border: 1px solid #ccc;
            page-break-inside: avoid;
        }
        
        .rekam-header {
            background-color: #e9ecef;
            padding: 2mm 3mm;
            font-weight: bold;
            border-bottom: 1px solid #ccc;
        }
        
        .rekam-body {
            padding: 3mm;
        }
        
        /* Info Row */
        .info-row {
            margin-bottom: 1.5mm;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
            vertical-align: top;
        }
        
        .info-value {
            display: inline-block;
            vertical-align: top;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5mm 2mm;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 2px;
        }
        
        .status-selesai { background-color: #28a745; color: white; }
        .status-dijadwalkan { background-color: #ffc107; color: #000; }
        .status-ditunda { background-color: #17a2b8; color: white; }
        .status-batal { background-color: #dc3545; color: white; }
        .status-lunas { background-color: #28a745; color: white; }
        .status-belum { background-color: #ffc107; color: #000; }
        
        /* Text Alignment */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        
        /* No Data */
        .no-data {
            text-align: center;
            padding: 3mm;
            color: #666;
            font-style: italic;
            background-color: #f9f9f9;
            border: 1px dashed #666;
        }
        
        /* Footer */
        .footer { 
            margin-top: 5mm; 
            padding-top: 2mm;
            border-top: 1px solid #000;
            font-size: 7pt; 
            text-align: center;
        }
        
        /* Total */
        .grand-total {
            margin-top: 5mm;
            padding: 2mm;
            background-color: #333;
            color: white;
            text-align: center;
            font-weight: bold;
        }
        
        /* Print Controls */
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 6px 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            border: 1px solid #ccc;
        }
        
        .print-controls button {
            padding: 4px 10px;
            margin: 0 3px;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
            font-size: 9pt;
            background: white;
        }
        
        .print-controls button:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()">🖨️ Cetak</button>
        <button onclick="window.close()">✖️ Tutup</button>
    </div>
    
    <div class="container">
        <!-- Header Klinik -->
        <div class="header-klinik">
            <div class="klinik-name">Poliklinik Mata Eyethica</div>
            <div class="klinik-address">Klinik Kesehatan Mata</div>
            <div class="klinik-address">Menteng Dalam, Kec. Tebet, Jakarta Timur</div>
            <div class="klinik-contact">Telp: (021) 12345678 | Email: info@eyethica.id</div>
        </div>
        
        <!-- Report Header -->
        <div class="report-header">
            <div class="report-title">LAPORAN RIWAYAT MEDIS PASIEN</div>
            <div class="patient-id"><?= htmlspecialchars($pasien['nama_pasien']) ?> (ID: <?= htmlspecialchars($pasien['id_pasien']) ?>)</div>
            <div class="print-date">Dicetak: <?= date('d-m-Y H:i:s') ?></div>
        </div>
        
        <!-- Informasi Pasien -->
        <table class="info-table">
            <tr>
                <td class="label">No. Rekam Medis</td>
                <td><?= htmlspecialchars($pasien['id_pasien']) ?></td>
                <td class="label">Nama Pasien</td>
                <td><?= htmlspecialchars($pasien['nama_pasien']) ?></td>
            </tr>
            <tr>
                <td class="label">NIK</td>
                <td><?= htmlspecialchars($pasien['nik'] ?? '-') ?></td>
                <td class="label">Jenis Kelamin</td>
                <td><?= htmlspecialchars($jenis_kelamin) ?></td>
            </tr>
            <tr>
                <td class="label">Tempat, Tgl Lahir</td>
                <td colspan="3"><?= htmlspecialchars($pasien['tempat_lahir'] ?? '-') ?>, <?= formatTanggal($pasien['tgl_lahir_pasien'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label">Alamat</td>
                <td colspan="3"><?= htmlspecialchars($pasien['alamat_pasien'] ?? '-') ?></td>
            </tr>
            <tr>
                <td class="label">Telepon</td>
                <td><?= htmlspecialchars($pasien['telepon_pasien'] ?? '-') ?></td>
                <td class="label">Tanggal Registrasi</td>
                <td><?= formatTanggalWaktu($pasien['tanggal_registrasi_pasien'] ?? '') ?></td>
            </tr>
        </table>
        
        <!-- Data Rekam Medis -->
        <div class="section">
            <div class="section-title">RIWAYAT REKAM MEDIS</div>
            
            <?php if (!empty($rekam_medis_list)): ?>
                <?php foreach ($rekam_medis_list as $rekam): ?>
                <div class="rekam-card">
                    <div class="rekam-header">
                        REKAM MEDIS #<?= htmlspecialchars($rekam['id_rekam']) ?> - <?= formatTanggalWaktu($rekam['tanggal_periksa'] ?? '') ?>
                    </div>
                    <div class="rekam-body">
                        <!-- Informasi Dasar -->
                        <div class="info-row">
                            <span class="info-label">Dokter:</span>
                            <span class="info-value"><?= htmlspecialchars($rekam['kode_dokter'] ?? '-') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Jenis Kunjungan:</span>
                            <span class="info-value"><?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') ?></span>
                        </div>
                        <?php if (!empty($rekam['keluhan'])): ?>
                        <div class="info-row">
                            <span class="info-label">Keluhan:</span>
                            <span class="info-value"><?= nl2br(htmlspecialchars($rekam['keluhan'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($rekam['diagnosa'])): ?>
                        <div class="info-row">
                            <span class="info-label">Diagnosa:</span>
                            <span class="info-value"><?= nl2br(htmlspecialchars($rekam['diagnosa'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($rekam['catatan'])): ?>
                        <div class="info-row">
                            <span class="info-label">Catatan:</span>
                            <span class="info-value"><?= nl2br(htmlspecialchars($rekam['catatan'])) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Pemeriksaan Mata -->
                        <?php if (!empty($rekam['pemeriksaan_mata'])): ?>
                        <div class="subsection-title">Pemeriksaan Mata</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="8%">ID</th>
                                    <th width="12%">Visus OD</th>
                                    <th width="12%">Visus OS</th>
                                    <th width="25%">OD (Sph/Cyl/Axis)</th>
                                    <th width="25%">OS (Sph/Cyl/Axis)</th>
                                    <th width="18%">TIO OD/OS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekam['pemeriksaan_mata'] as $pm): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($pm['id_pemeriksaan']) ?></td>
                                    <td><?= htmlspecialchars($pm['visus_od'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($pm['visus_os'] ?? '-') ?></td>
                                    <td><?= ($pm['sph_od'] ?? 0) . ' / ' . ($pm['cyl_od'] ?? 0) . ' / ' . ($pm['axis_od'] ?? 0) . '°' ?></td>
                                    <td><?= ($pm['sph_os'] ?? 0) . ' / ' . ($pm['cyl_os'] ?? 0) . ' / ' . ($pm['axis_os'] ?? 0) . '°' ?></td>
                                    <td><?= ($pm['tio_od'] ?? '-') . ' / ' . ($pm['tio_os'] ?? '-') . ' mmHg' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                        
                        <!-- Tindakan Medis -->
                        <?php if (!empty($rekam['detail_tindakan'])): ?>
                        <div class="subsection-title">Tindakan Medis</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="15%">ID</th>
                                    <th width="40%">Tindakan</th>
                                    <th width="10%">Qty</th>
                                    <th width="20%">Harga</th>
                                    <th width="15%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekam['detail_tindakan'] as $dt): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($dt['id_detail_tindakanmedis']) ?></td>
                                    <td><?= htmlspecialchars($dt['nama_tindakan'] ?? '-') ?></td>
                                    <td class="text-center"><?= $dt['qty'] ?? 0 ?></td>
                                    <td class="text-right"><?= formatRupiah($dt['harga_tindakan'] ?? 0) ?></td>
                                    <td class="text-right"><?= formatRupiah($dt['subtotal'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                        
                        <!-- Resep Kacamata -->
                        <?php if (!empty($rekam['resep_kacamata'])): ?>
                        <div class="subsection-title">Resep Kacamata</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="10%">ID</th>
                                    <th width="30%">OD (Sph/Cyl/Axis)</th>
                                    <th width="30%">OS (Sph/Cyl/Axis)</th>
                                    <th width="10%">PD</th>
                                    <th width="20%">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekam['resep_kacamata'] as $rk): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($rk['id_resep_kacamata']) ?></td>
                                    <td><?= ($rk['sph_od'] ?? 0) . ' / ' . ($rk['cyl_od'] ?? 0) . ' / ' . ($rk['axis_od'] ?? 0) . '°' ?></td>
                                    <td><?= ($rk['sph_os'] ?? 0) . ' / ' . ($rk['cyl_os'] ?? 0) . ' / ' . ($rk['axis_os'] ?? 0) . '°' ?></td>
                                    <td class="text-center"><?= $rk['pd'] ? $rk['pd'] . ' mm' : '-' ?></td>
                                    <td><?= htmlspecialchars($rk['keterangan'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                        
                        <!-- Resep Obat -->
                        <?php if (!empty($rekam['resep_obat'])): ?>
                        <div class="subsection-title">Resep Obat</div>
                        <?php foreach ($rekam['resep_obat'] as $ro): ?>
                        <table class="data-table" style="margin-bottom: 2mm;">
                            <thead>
                                <tr style="background-color: #e0e0e0;">
                                    <th colspan="4">Resep #<?= htmlspecialchars($ro['id_resep_obat']) ?> - <?= formatTanggal($ro['tanggal_resep'] ?? '') ?></th>
                                 </tr>
                                <tr>
                                    <th width="50%">Nama Obat</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Harga</th>
                                    <th width="15%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $detail_resep = array_filter($all_resep_obat_details, function($d) use ($ro) {
                                    return $d['id_resep_obat'] == $ro['id_resep_obat'];
                                });
                                ?>
                                <?php if (!empty($detail_resep)): ?>
                                    <?php foreach ($detail_resep as $dr): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dr['nama_obat'] ?? '-') ?></td>
                                        <td class="text-center"><?= $dr['jumlah'] ?? 0 ?></td>
                                        <td class="text-right"><?= formatRupiah($dr['harga_obat'] ?? 0) ?></td>
                                        <td class="text-right"><?= formatRupiah($dr['subtotal'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada detail obat</td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($ro['catatan'])): ?>
                                <tr>
                                    <td colspan="4"><em>Catatan: <?= htmlspecialchars($ro['catatan']) ?></em></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Transaksi -->
                        <?php if (!empty($rekam['transaksi'])): ?>
                        <div class="subsection-title">Transaksi</div>
                        <?php foreach ($rekam['transaksi'] as $tr): ?>
                        <table class="data-table" style="margin-bottom: 2mm;">
                            <thead>
                                <tr style="background-color: #e0e0e0;">
                                    <th colspan="5">Transaksi #<?= htmlspecialchars($tr['id_transaksi']) ?> - <?= formatTanggalWaktu($tr['tanggal_transaksi'] ?? '') ?></th>
                                 </tr>
                                <tr>
                                    <th width="15%">Jenis Item</th>
                                    <th width="45%">Nama Item</th>
                                    <th width="10%">Qty</th>
                                    <th width="15%">Harga</th>
                                    <th width="15%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $detail_trans = array_filter($all_transaksi_details, function($d) use ($tr) {
                                    return $d['id_transaksi'] == $tr['id_transaksi'];
                                });
                                ?>
                                <?php if (!empty($detail_trans)): ?>
                                    <?php foreach ($detail_trans as $dt): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dt['jenis_item'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($dt['nama_item'] ?? '-') ?></td>
                                        <td class="text-center"><?= $dt['qty'] ?? 0 ?></td>
                                        <td class="text-right"><?= formatRupiah($dt['harga'] ?? 0) ?></td>
                                        <td class="text-right"><?= formatRupiah($dt['subtotal'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada detail transaksi</td>
                                    </tr>
                                <?php endif; ?>
                                <tr style="background-color: #f0f0f0; font-weight: bold;">
                                    <td colspan="4" class="text-right">Grand Total:</td>
                                    <td class="text-right"><?= formatRupiah($tr['grand_total'] ?? 0) ?></td>
                                 </tr>
                                <tr>
                                    <td colspan="2">Metode: <?= htmlspecialchars($tr['metode_pembayaran'] ?? '-') ?></td>
                                    <td colspan="3">Status: <span class="status-badge <?= ($tr['status_pembayaran'] == 'Lunas') ? 'status-lunas' : 'status-belum' ?>"><?= htmlspecialchars($tr['status_pembayaran'] ?? 'Belum Bayar') ?></span></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Total Keseluruhan -->
                <div class="grand-total">
                    TOTAL BIAYA KESELURUHAN: <?= formatRupiah($total_biaya) ?>
                </div>
                
            <?php else: ?>
                <div class="no-data">Tidak ada data rekam medis</div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Dokumen ini dicetak secara otomatis oleh sistem Poliklinik Mata Eyethica
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.focus();
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
<?php
    exit();
}

header("Location: ../../datariwayatmedis.php");
exit();
?>