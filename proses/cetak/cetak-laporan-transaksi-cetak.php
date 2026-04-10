<?php
session_start();
require_once "../../koneksi.php";

$db = new database();

// PROSES CETAK
if (isset($_GET['cetak'])) {
    // Konfigurasi filter sama seperti di halaman
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Ambil semua data transaksi
    $all_transaksi = $db->tampil_data_transaksi();
    $all_pasien = $db->tampil_data_pasien();
    $all_staff = $db->tampil_data_staff();
    
    // Filter data berdasarkan search query
    if (!empty($search_query)) {
        $filtered_transaksi = [];
        foreach ($all_transaksi as $transaksi) {
            if (stripos($transaksi['id_transaksi'] ?? '', $search_query) !== false ||
                stripos($transaksi['id_rekam'] ?? '', $search_query) !== false ||
                stripos($transaksi['id_pasien'] ?? '', $search_query) !== false ||
                stripos($transaksi['kode_staff'] ?? '', $search_query) !== false ||
                stripos($transaksi['tanggal_transaksi'] ?? '', $search_query) !== false ||
                stripos($transaksi['metode_pembayaran'] ?? '', $search_query) !== false ||
                stripos($transaksi['grand_total'] ?? '', $search_query) !== false ||
                stripos($transaksi['status_pembayaran'] ?? '', $search_query) !== false) {
                $filtered_transaksi[] = $transaksi;
            }
        }
        $all_transaksi = $filtered_transaksi;
    }
    
    // Filter berdasarkan status pembayaran
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status = trim($_GET['status']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($status) {
            return ($transaksi['status_pembayaran'] ?? '') == $status;
        });
    }
    
    // Filter berdasarkan metode pembayaran
    if (isset($_GET['metode']) && !empty($_GET['metode'])) {
        $metode = trim($_GET['metode']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($metode) {
            return ($transaksi['metode_pembayaran'] ?? '') == $metode;
        });
    }
    
    // Filter berdasarkan pasien
    if (isset($_GET['pasien']) && !empty($_GET['pasien'])) {
        $id_pasien = trim($_GET['pasien']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($id_pasien, $all_pasien, $db) {
            $rekam = $db->tampil_data_rekam_medis_by_id($transaksi['id_rekam'] ?? '');
            return ($rekam['id_pasien'] ?? '') == $id_pasien;
        });
    }
    
    // Filter berdasarkan staff
    if (isset($_GET['staff']) && !empty($_GET['staff'])) {
        $kode_staff = trim($_GET['staff']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($kode_staff) {
            return ($transaksi['kode_staff'] ?? '') == $kode_staff;
        });
    }
    
    // Filter berdasarkan rentang tanggal
    if (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) {
        $tanggal_mulai = trim($_GET['tanggal_mulai']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($tanggal_mulai) {
            return strtotime($transaksi['tanggal_transaksi'] ?? '') >= strtotime($tanggal_mulai);
        });
    }
    
    if (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) {
        $tanggal_selesai = trim($_GET['tanggal_selesai']);
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($tanggal_selesai) {
            return strtotime($transaksi['tanggal_transaksi'] ?? '') <= strtotime($tanggal_selesai . ' 23:59:59');
        });
    }
    
    // Reset array keys setelah filter
    $all_transaksi = array_values($all_transaksi);
    
    // Sorting berdasarkan ID Transaksi
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
    if ($sort_order === 'desc') {
        usort($all_transaksi, function($a, $b) {
            return ($b['id_transaksi'] ?? 0) - ($a['id_transaksi'] ?? 0);
        });
    } else {
        usort($all_transaksi, function($a, $b) {
            return ($a['id_transaksi'] ?? 0) - ($b['id_transaksi'] ?? 0);
        });
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Laporan Data Transaksi</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 10pt; 
            line-height: 1.4;
            margin: 0;
            padding: 15px;
            color: #333;
            background: #fff;
        }
        .container { 
            max-width: 100%; 
            margin: 0 auto; 
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #4a90e2;
        }
        .header h1 { 
            margin: 0; 
            font-size: 20pt; 
            color: #2c3e50;
            font-weight: bold;
        }
        .header h2 { 
            margin: 5px 0 8px 0; 
            font-size: 14pt; 
            color: #4a90e2;
            font-weight: normal;
        }
        .header p { 
            margin: 4px 0; 
            color: #666;
            font-size: 9pt;
        }
        .info { 
            margin-bottom: 15px; 
            padding: 10px 12px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #4a90e2;
            font-size: 9pt;
        }
        .info p { 
            margin: 4px 0; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-size: 8.5pt;
        }
        table th { 
            background-color: #4a90e2; 
            color: white;
            text-align: left; 
            padding: 8px 6px; 
            border: 1px solid #357ae8;
            font-weight: 600;
        }
        table td { 
            padding: 6px; 
            border: 1px solid #ddd; 
            vertical-align: top;
        }
        table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total { 
            font-weight: bold; 
            text-align: right; 
            padding: 8px 6px;
            background-color: #f1f8ff;
            border-top: 2px solid #4a90e2;
        }
        .footer { 
            margin-top: 25px; 
            text-align: center; 
            font-size: 8pt; 
            color: #777;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }
        .status-lunas {
            color: #28a745;
            font-weight: bold;
        }
        .status-belum {
            color: #ffc107;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8pt;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 3px;
        }
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .badge-tunai { background-color: #d1ecf1; color: #0c5460; }
        .badge-transfer { background-color: #e7d6f5; color: #4a0e6e; }
        .badge-qris { background-color: #f8d7da; color: #721c24; }
        .badge-debit { background-color: #ffe5d0; color: #cc7a00; }
        .controls {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }
        .controls button {
            padding: 6px 18px;
            margin: 0 4px;
            border: none;
            border-radius: 4px;
            background-color: #4a90e2;
            color: white;
            cursor: pointer;
            font-size: 10pt;
        }
        .controls button:hover {
            background-color: #357ae8;
        }
        .controls button#print {
            background-color: #28a745;
        }
        .controls button#print:hover {
            background-color: #218838;
        }
        .controls button#close {
            background-color: #dc3545;
        }
        .controls button#close:hover {
            background-color: #c82333;
        }
        
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="controls no-print">
            <button onclick="window.print()" id="print"><i class="fas fa-print"></i> Cetak</button>
            <button onclick="window.close()" id="close"><i class="fas fa-times"></i> Tutup</button>
        </div>
        
        <div class="header">
            <h1>Poliklinik Mata Eyethica</h1>
            <h2>Laporan Data Transaksi</h2>
            <p>Jl. Contoh No. 123, Kota Contoh</p>
            <p>Telepon: (021) 1234-5678 | Email: info@eyethica.com</p>
            <p>Dicetak pada: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        
        <div class="info">
            <p><strong>Informasi Laporan:</strong></p>
            <?php
            if (!empty($search_query)) echo "<p>Pencarian: <strong>$search_query</strong></p>";
            if (isset($_GET['status']) && !empty($_GET['status'])) echo "<p>Status Pembayaran: <strong>" . htmlspecialchars($_GET['status']) . "</strong></p>";
            if (isset($_GET['metode']) && !empty($_GET['metode'])) echo "<p>Metode Pembayaran: <strong>" . htmlspecialchars($_GET['metode']) . "</strong></p>";
            if (isset($_GET['tanggal_mulai']) || isset($_GET['tanggal_selesai'])) {
                echo "<p>Periode: ";
                if (isset($_GET['tanggal_mulai'])) echo "<strong>" . htmlspecialchars($_GET['tanggal_mulai']) . "</strong>";
                if (isset($_GET['tanggal_mulai']) && isset($_GET['tanggal_selesai'])) echo " s/d ";
                if (isset($_GET['tanggal_selesai'])) echo "<strong>" . htmlspecialchars($_GET['tanggal_selesai']) . "</strong>";
                echo "</p>";
            }
            if (isset($_GET['pasien']) && !empty($_GET['pasien'])) {
                foreach ($all_pasien as $pasien) {
                    if ($pasien['id_pasien'] == $_GET['pasien']) {
                        echo "<p>Pasien: <strong>" . htmlspecialchars($pasien['nama_pasien']) . "</strong></p>";
                        break;
                    }
                }
            }
            if (isset($_GET['staff']) && !empty($_GET['staff'])) {
                foreach ($all_staff as $staff) {
                    if ($staff['kode_staff'] == $_GET['staff']) {
                        echo "<p>Staff: <strong>" . htmlspecialchars($staff['nama_staff']) . "</strong></p>";
                        break;
                    }
                }
            }
            if ($sort_order === 'desc') {
                echo "<p>Urutan: <strong>Terbaru ke Terlama</strong></p>";
            } else {
                echo "<p>Urutan: <strong>Terlama ke Terbaru</strong></p>";
            }
            ?>
            <p>Total Data: <strong><?= count($all_transaksi) ?></strong> transaksi</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="10%">ID Transaksi</th>
                    <th width="9%">ID Rekam</th>
                    <th width="12%">Nama Pasien</th>
                    <th width="8%">Kode Staff</th>
                    <th width="12%">Tanggal Transaksi</th>
                    <th width="9%">Metode Bayar</th>
                    <th width="10%">Total Biaya</th>
                    <th width="8%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($all_transaksi) > 0) {
                    $no = 1;
                    $total_biaya = 0;
                    foreach ($all_transaksi as $transaksi) {
                        $nama_pasien = 'Pasien Tidak Diketahui';
                        $id_rekam = $transaksi['id_rekam'] ?? '';
                        if (!empty($id_rekam)) {
                            $rekam = $db->tampil_data_rekam_medis_by_id($id_rekam);
                            if ($rekam) {
                                foreach ($all_pasien as $pasien) {
                                    if ($pasien['id_pasien'] == $rekam['id_pasien']) {
                                        $nama_pasien = $pasien['nama_pasien'];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $tanggal_formatted = !empty($transaksi['tanggal_transaksi']) ? 
                            date('d-m-Y H:i:s', strtotime($transaksi['tanggal_transaksi'])) : '-';
                        
                        $biaya = $transaksi['grand_total'] ?? 0;
                        $biaya_formatted = 'Rp ' . number_format($biaya, 0, ',', '.');
                        $total_biaya += $biaya;
                        
                        $status_badge = ($transaksi['status_pembayaran'] == 'Lunas') ? 'badge-success' : 'badge-warning';
                        
                        $metode_class = '';
                        switch($transaksi['metode_pembayaran']) {
                            case 'Tunai': $metode_class = 'badge-tunai'; break;
                            case 'Transfer': $metode_class = 'badge-transfer'; break;
                            case 'QRIS': $metode_class = 'badge-qris'; break;
                            case 'Debit': $metode_class = 'badge-debit'; break;
                            default: $metode_class = 'badge-secondary';
                        }
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($transaksi['id_transaksi'] ?? '') ?></td>
                    <td><?= htmlspecialchars($transaksi['id_rekam'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($nama_pasien) ?></td>
                    <td><?= htmlspecialchars($transaksi['kode_staff'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($tanggal_formatted) ?></td>
                    <td><span class="badge <?= $metode_class ?>"><?= htmlspecialchars($transaksi['metode_pembayaran'] ?? '-') ?></span></td>
                    <td class="text-right"><?= htmlspecialchars($biaya_formatted) ?></td>
                    <td><span class="badge <?= $status_badge ?>"><?= htmlspecialchars($transaksi['status_pembayaran'] ?? '') ?></span></td>
                </tr>
                <?php 
                    }
                } else {
                    echo '<tr><td colspan="9" class="text-center text-muted">Tidak ada data transaksi ditemukan.</td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <?php if (count($all_transaksi) > 0): ?>
                <tr>
                    <td colspan="7" class="total">TOTAL BIAYA:</td>
                    <td class="total">Rp <?= number_format($total_biaya, 0, ',', '.') ?></td>
                    <td class="total"></td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>
        
        <div class="footer">
            <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
            <p>Copyright © <?= date('Y') ?> Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.focus();
            setTimeout(function() {
                window.print();
            }, 500);
        }
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>
<?php
    exit();
} else {
    header('Location: ../../laporan-transaksi.php');
    exit();
}
?>