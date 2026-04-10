<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// FUNGSI UTAMA
function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    return date('d/m/Y', strtotime($tanggal));
}

function formatRupiah($angka) {
    if (empty($angka) && $angka != 0) return '0';
    return number_format($angka, 0, ',', '.');
}

// Fungsi untuk mendapatkan data pasien berdasarkan ID pasien
function getPasienById($db, $id_pasien) {
    if (empty($id_pasien)) {
        return [
            'nama_pasien' => '-',
            'alamat_pasien' => '-',
            'telepon_pasien' => '-',
            'no_rm' => '-'
        ];
    }
    
    try {
        $pasien = $db->get_pasien_by_id($id_pasien);
        if ($pasien) {
            return $pasien;
        }
    } catch (Exception $e) {
        error_log("Error getPasienById: " . $e->getMessage());
    }
    
    return [
        'nama_pasien' => '-',
        'alamat_pasien' => '-',
        'telepon_pasien' => '-',
        'no_rm' => '-'
    ];
}

// Fungsi untuk mendapatkan data rekam medis berdasarkan ID rekam
function getRekamMedisById($db, $id_rekam) {
    if (empty($id_rekam)) {
        return null;
    }
    
    try {
        $query = "SELECT * FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
        $result = $db->koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log("Error getRekamMedisById: " . $e->getMessage());
    }
    
    return null;
}

// Fungsi untuk mendapatkan data staff berdasarkan kode
function getStaffByKode($db, $kode_staff) {
    if (empty($kode_staff)) {
        return ['nama_staff' => '-', 'kode_staff' => ''];
    }
    
    $all_staff = $db->tampil_data_staff();
    
    if (!is_array($all_staff)) {
        return ['nama_staff' => '-', 'kode_staff' => $kode_staff];
    }
    
    foreach ($all_staff as $staff) {
        if ($staff['kode_staff'] == $kode_staff) {
            return $staff;
        }
    }
    
    return ['nama_staff' => '-', 'kode_staff' => $kode_staff];
}

// Fungsi untuk mendapatkan detail transaksi (item tindakan & obat)
function getDetailTransaksi($db, $id_transaksi) {
    try {
        $query = "SELECT * FROM data_detail_transaksi WHERE id_transaksi = '$id_transaksi' ORDER BY id_detail_transaksi ASC";
        $result = $db->koneksi->query($query);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error getDetailTransaksi: " . $e->getMessage());
        return [];
    }
}

// PROSES CETAK TRANSAKSI
if (isset($_GET['id_transaksi'])) {
    $id_transaksi = $_GET['id_transaksi'];
    
    // Ambil data transaksi
    $transaksi = $db->get_transaksi_by_id($id_transaksi);
    if (!$transaksi) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data transaksi tidak ditemukan';
        header("Location: ../../transaksi.php");
        exit();
    }
    
    // Ambil detail transaksi (item tindakan & obat)
    $detail_transaksi = getDetailTransaksi($db, $id_transaksi);
    
    // Ambil data rekam medis berdasarkan id_rekam dari transaksi
    $id_rekam = $transaksi['id_rekam'] ?? '';
    $rekam_medis = getRekamMedisById($db, $id_rekam);
    
    // Ambil id_pasien dari rekam medis
    $id_pasien = $rekam_medis['id_pasien'] ?? '';
    
    // Ambil data pasien berdasarkan id_pasien
    $pasien = getPasienById($db, $id_pasien);
    
    // Ambil data staff
    $kode_staff = $transaksi['kode_staff'] ?? '';
    $staff = getStaffByKode($db, $kode_staff);
    
    // ID Kasir = kode_staff (jika kosong, isi KASIR001)
    $id_kasir = !empty($kode_staff) ? $kode_staff : 'KASIR001';
    $nama_kasir = $staff['nama_staff'] ?? ($id_kasir == 'KASIR001' ? 'Kasir' : 'ADMIN');
    
    // Gunakan grand_total dari transaksi
    $grand_total = $transaksi['grand_total'] ?? 0;
    
    // Format tanggal
    $tanggal_transaksi = !empty($transaksi['tanggal_transaksi']) ? 
        date('Y-m-d', strtotime($transaksi['tanggal_transaksi'])) : date('Y-m-d');
    $jam_transaksi = !empty($transaksi['tanggal_transaksi']) ? 
        date('H:i', strtotime($transaksi['tanggal_transaksi'])) : date('H:i');
    
    // Status pembayaran
    $status_pembayaran = $transaksi['status_pembayaran'] ?? 'Belum Bayar';
    
    // Metode pembayaran
    $metode_pembayaran = $transaksi['metode_pembayaran'] ?? '-';
    
    // Nama pasien dari data pasien
    $nama_pasien = $pasien['nama_pasien'] ?? '-';
    
    // Alamat pasien dari data pasien
    $alamat_pasien = $pasien['alamat_pasien'] ?? '-';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Tagihan - <?= htmlspecialchars($id_transaksi) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            @page {
                size: A4;
                margin: 8mm;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1a1a2e;
            background: #f5f5f5;
            padding: 10mm;
        }
        
        .container { 
            max-width: 190mm;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        /* Inner Content */
        .inner-content {
            padding: 8mm 10mm;
        }
        
        /* Header Klinik */
        .header {
            text-align: center;
            margin-bottom: 8mm;
            padding-bottom: 6mm;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .header .klinik-name { 
            font-size: 22pt;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 3mm;
            color: #1a1a2e;
        }
        
        .header .klinik-address { 
            font-size: 9pt;
            color: #666666;
            margin-bottom: 1mm;
            line-height: 1.4;
        }
        
        .header .klinik-contact {
            font-size: 9pt;
            color: #666666;
            margin-top: 2mm;
        }
        
        /* Title Section */
        .title-section {
            text-align: center;
            margin-bottom: 8mm;
        }
        
        .title-tagihan {
            font-size: 18pt;
            font-weight: 800;
            margin-bottom: 5mm;
            letter-spacing: -0.3px;
            color: #1a1a2e;
        }
        
        .status-badges {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 30px;
            font-size: 9pt;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge-lunas {
            background: #1a1a2e;
            color: #ffffff;
        }
        
        .badge-belum {
            background: #e0e0e0;
            color: #666666;
        }
        
        .badge-metode {
            background: #f0f0f0;
            color: #333333;
            border: 1px solid #cccccc;
        }
        
        /* Info Card */
        .info-card {
            background: #fafafa;
            border-radius: 12px;
            margin-bottom: 8mm;
            overflow: hidden;
            border: 1px solid #eeeeee;
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
        }
        
        .info-col {
            flex: 1;
            min-width: 220px;
            padding: 5mm 6mm;
        }
        
        .info-col:first-child {
            border-right: 1px solid #eeeeee;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 3mm;
            align-items: flex-start;
        }
        
        .info-label {
            width: 85px;
            font-weight: 600;
            color: #666666;
            font-size: 9pt;
        }
        
        .info-value {
            flex: 1;
            color: #1a1a2e;
            font-weight: 500;
            font-size: 10pt;
        }
        
        /* Section Header */
        .section-header {
            display: flex;
            align-items: center;
            margin: 6mm 0 4mm 0;
        }
        
        .section-header h3 {
            font-size: 12pt;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.2px;
        }
        
        .section-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, #e0e0e0, #ffffff);
            margin-left: 10px;
        }
        
        /* Table Styling Modern */
        .table-wrapper {
            overflow-x: auto;
            margin: 4mm 0 6mm 0;
            border-radius: 10px;
            border: 1px solid #eeeeee;
        }
        
        .table-modern {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        
        .table-modern th {
            background: #f5f5f5;
            padding: 3.5mm 3mm;
            text-align: left;
            font-weight: 700;
            color: #1a1a2e;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-modern td {
            padding: 3mm 3mm;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }
        
        .table-modern tr:last-child td {
            border-bottom: none;
        }
        
        .table-modern .text-right {
            text-align: right;
        }
        
        .table-modern .text-center {
            text-align: center;
        }
        
        .table-modern .jenis-item {
            font-weight: 500;
        }
        
        .table-footer {
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
        }
        
        .table-footer td {
            padding: 3.5mm 3mm;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        /* Finance Card */
        .finance-card {
            background: #fafafa;
            border-radius: 10px;
            margin: 4mm 0 6mm 0;
            border: 1px solid #eeeeee;
        }
        
        .finance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .finance-table td {
            padding: 3mm 5mm;
        }
        
        .finance-table td.label {
            font-weight: 500;
            color: #666666;
            width: 70%;
        }
        
        .finance-table td.nilai {
            text-align: right;
            font-weight: 600;
            color: #1a1a2e;
        }
        
        .finance-table tr.total td {
            border-top: 1px solid #e0e0e0;
            padding-top: 4mm;
            margin-top: 2mm;
            font-weight: 800;
            font-size: 11pt;
        }
        
        .finance-table tr.total td.label,
        .finance-table tr.total td.nilai {
            color: #1a1a2e;
        }
        
        /* Signature Area */
        .signature-area {
            margin-top: 12mm;
            padding: 5mm 0;
        }
        
        .signature-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10mm;
        }
        
        .signature-box {
            flex: 1;
            min-width: 180px;
            text-align: center;
        }
        
        .signature-title {
            font-weight: 600;
            margin-bottom: 3mm;
            color: #666666;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .signature-line {
            width: 80%;
            margin: 10mm auto 2mm auto;
            border-top: 1px dashed #cccccc;
        }
        
        .signature-name {
            font-weight: 600;
            margin-top: 2mm;
            color: #1a1a2e;
            font-size: 10pt;
        }
        
        .signature-date {
            font-size: 8pt;
            color: #999999;
            margin-top: 1mm;
        }
        
        /* Footer */
        .footer {
            margin-top: 10mm;
            padding: 5mm 8mm;
            text-align: center;
            font-size: 8pt;
            color: #999999;
            border-top: 1px solid #eeeeee;
            background: #fafafa;
        }
        
        /* Print Controls */
        .print-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 1px solid #e0e0e0;
            display: flex;
            gap: 8px;
        }
        
        .print-controls button {
            padding: 8px 20px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 9pt;
            font-weight: 600;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .print-controls button#print {
            background: #1a1a2e;
            color: white;
        }
        
        .print-controls button#print:hover {
            background: #2d2d44;
            transform: translateY(-1px);
        }
        
        .print-controls button#close {
            background: #f0f0f0;
            color: #666666;
        }
        
        .print-controls button#close:hover {
            background: #e0e0e0;
            transform: translateY(-1px);
        }
        
        /* Utility Classes */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .mt-1 { margin-top: 1mm; }
        .mt-2 { margin-top: 2mm; }
        .mb-1 { margin-bottom: 1mm; }
        .mb-2 { margin-bottom: 2mm; }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()" id="print">🖨️ Cetak Tagihan</button>
        <button onclick="window.close()" id="close">✖️ Tutup</button>
    </div>
    
    <div class="container">
        <div class="inner-content">
            <!-- Header Klinik -->
            <div class="header">
                <div class="klinik-name">Poliklinik Mata Eyethica</div>
                <div class="klinik-address">Jl. Trans Kalimantan Km. 04, Nanga Bulik 57126</div>
                <div class="klinik-address">Kabupaten Lamandau - Kalimantan Tengah</div>
                <div class="klinik-contact">📞 0812-3456-7890 | ✉️ info@eyethica.id</div>
            </div>
            
            <!-- Title -->
            <div class="title-section">
                <div class="title-tagihan">TAGIHAN RAWAT JALAN</div>
                <div class="status-badges">
                    <span class="badge-status <?= $status_pembayaran == 'Lunas' ? 'badge-lunas' : 'badge-belum' ?>">
                        <?= htmlspecialchars($status_pembayaran) ?>
                    </span>
                    <?php if ($metode_pembayaran != '-'): ?>
                    <span class="badge-status badge-metode"><?= htmlspecialchars($metode_pembayaran) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Informasi Pasien & Transaksi -->
            <div class="info-card">
                <div class="info-row">
                    <div class="info-col">
                        <div class="info-item">
                            <span class="info-label">ID Rekam</span>
                            <span class="info-value"><?= htmlspecialchars($id_rekam ?: '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Pasien</span>
                            <span class="info-value"><?= htmlspecialchars($nama_pasien) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Alamat</span>
                            <span class="info-value"><?= htmlspecialchars($alamat_pasien) ?></span>
                        </div>
                    </div>
                    <div class="info-col">
                        <div class="info-item">
                            <span class="info-label">ID Transaksi</span>
                            <span class="info-value"><?= htmlspecialchars($id_transaksi) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tanggal</span>
                            <span class="info-value"><?= formatTanggalIndo($tanggal_transaksi) ?> / <?= $jam_transaksi ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ID Kasir</span>
                            <span class="info-value"><?= htmlspecialchars($id_kasir) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detail Transaksi -->
            <div class="section-header">
                <h3>Detail Transaksi</h3>
                <div class="section-line"></div>
            </div>
            
            <!-- Tabel Item Transaksi -->
            <div class="table-wrapper">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Jenis</th>
                            <th width="40%">Nama Item</th>
                            <th width="10%" class="text-center">Qty</th>
                            <th width="15%" class="text-right">Harga</th>
                            <th width="15%" class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($detail_transaksi)): ?>
                            <?php $no = 1; foreach ($detail_transaksi as $item): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td class="jenis-item"><?= htmlspecialchars($item['jenis_item'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($item['nama_item'] ?? '-') ?></td>
                                    <td class="text-center"><?= $item['qty'] ?? 1 ?></td>
                                    <td class="text-right">Rp <?= formatRupiah($item['harga'] ?? 0) ?></td>
                                    <td class="text-right">Rp <?= formatRupiah($item['subtotal'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 8mm; color: #999;">Tidak ada item transaksi</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-footer">
                        <tr>
                            <td colspan="5" class="text-right">GRAND TOTAL</td>
                            <td class="text-right">Rp <?= formatRupiah($grand_total) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Rincian Pembayaran -->
            <div class="section-header">
                <h3>Rincian Pembayaran</h3>
                <div class="section-line"></div>
            </div>
            
            <div class="finance-card">
                <table class="finance-table">
                    <tr>
                        <td class="label">Total Tagihan</td>
                        <td class="nilai">Rp <?= formatRupiah($grand_total) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Metode Pembayaran</td>
                        <td class="nilai"><?= htmlspecialchars($metode_pembayaran ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Status</td>
                        <td class="nilai"><?= htmlspecialchars($status_pembayaran) ?></td>
                    </tr>
                    <tr class="total">
                        <td class="label">Total Dibayar</td>
                        <td class="nilai">Rp <?= formatRupiah($status_pembayaran == 'Lunas' ? $grand_total : 0) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Signature Area -->
            <div class="signature-area">
                <div class="signature-row">
                    <div class="signature-box">
                        <div class="signature-title">Petugas Pemungut</div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?= htmlspecialchars($nama_kasir) ?></div>
                        <div class="signature-date">(<?= formatTanggalIndo($tanggal_transaksi) ?>)</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-title">Keluarga Pasien</div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?= htmlspecialchars($nama_pasien) ?></div>
                        <div class="signature-date">&nbsp;</div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div>Dokumen ini dicetak secara otomatis oleh sistem Poliklinik Mata Eyethica</div>
                <div class="mt-1"><?= date('d-m-Y H:i:s') ?></div>
                <div class="mt-1">Terima kasih atas kunjungan Anda</div>
            </div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.focus();
        }
        
        document.getElementById('print')?.addEventListener('click', function() {
            window.print();
        });
        
        document.getElementById('close')?.addEventListener('click', function() {
            window.close();
        });
    </script>
</body>
</html>
<?php
    exit();
}

// Jika tidak ada parameter yang valid, redirect ke halaman utama
header("Location: ../../transaksi.php");
exit();
?>