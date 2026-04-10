<?php
session_start();
require_once "../../koneksi.php";

$db = new database();

// Fungsi untuk mendapatkan nama pasien
function getNamaPasienById($id_pasien, $all_pasien) {
    foreach ($all_pasien as $pasien) {
        if ($pasien['id_pasien'] == $id_pasien) return $pasien['nama_pasien'];
    }
    return '-';
}

// Fungsi untuk mendapatkan nama dokter
function getNamaDokterByKode($kode_dokter, $all_dokter) {
    foreach ($all_dokter as $dokter) {
        if ($dokter['kode_dokter'] == $kode_dokter) return $dokter['nama_dokter'];
    }
    return '-';
}

// PROSES CETAK
if (isset($_GET['cetak'])) {
    // Konfigurasi filter
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
    $sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_rekam';
    
    // Ambil semua data
    $all_rekam_medis = $db->tampil_data_rekam_medis();
    $all_pasien = $db->tampil_data_pasien();
    $all_dokter = $db->tampil_data_dokter();
    
    // Filter berdasarkan search query
    if (!empty($search_query)) {
        $filtered = [];
        foreach ($all_rekam_medis as $rekam) {
            $nama_pasien = getNamaPasienById($rekam['id_pasien'] ?? '', $all_pasien);
            $nama_dokter = getNamaDokterByKode($rekam['kode_dokter'] ?? '', $all_dokter);
            
            if (stripos($rekam['id_rekam'] ?? '', $search_query) !== false ||
                stripos($rekam['id_pasien'] ?? '', $search_query) !== false ||
                stripos($nama_pasien, $search_query) !== false ||
                stripos($rekam['kode_dokter'] ?? '', $search_query) !== false ||
                stripos($nama_dokter, $search_query) !== false ||
                stripos($rekam['jenis_kunjungan'] ?? '', $search_query) !== false ||
                stripos($rekam['tanggal_periksa'] ?? '', $search_query) !== false) {
                $filtered[] = $rekam;
            }
        }
        $all_rekam_medis = $filtered;
    }
    
    // Filter tanggal
    if (isset($_GET['tgl_periksa_mulai']) && !empty($_GET['tgl_periksa_mulai'])) {
        $tgl_mulai = trim($_GET['tgl_periksa_mulai']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($tgl_mulai) {
            return strtotime($rekam['tanggal_periksa'] ?? '') >= strtotime($tgl_mulai);
        });
    }
    if (isset($_GET['tgl_periksa_selesai']) && !empty($_GET['tgl_periksa_selesai'])) {
        $tgl_selesai = trim($_GET['tgl_periksa_selesai']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($tgl_selesai) {
            return strtotime($rekam['tanggal_periksa'] ?? '') <= strtotime($tgl_selesai . ' 23:59:59');
        });
    }
    
    // Filter ID Pasien
    if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) {
        $id_pasien = trim($_GET['id_pasien']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($id_pasien) {
            return ($rekam['id_pasien'] ?? '') == $id_pasien;
        });
    }
    
    // Filter Kode Dokter
    if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) {
        $kode_dokter = trim($_GET['kode_dokter']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($kode_dokter) {
            return ($rekam['kode_dokter'] ?? '') == $kode_dokter;
        });
    }
    
    // Filter Jenis Kunjungan
    if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) {
        $jenis = trim($_GET['jenis_kunjungan']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($jenis) {
            return ($rekam['jenis_kunjungan'] ?? '') == $jenis;
        });
    }
    
    $all_rekam_medis = array_values($all_rekam_medis);
    
    // Sorting
    usort($all_rekam_medis, function($a, $b) use ($sort_column, $sort_order) {
        $val_a = $a[$sort_column] ?? '';
        $val_b = $b[$sort_column] ?? '';
        
        if (in_array($sort_column, ['id_rekam', 'id_pasien'])) {
            $val_a = (int) $val_a;
            $val_b = (int) $val_b;
            return $sort_order === 'desc' ? ($val_b - $val_a) : ($val_a - $val_b);
        }
        
        if ($sort_column == 'tanggal_periksa') {
            $time_a = strtotime($val_a);
            $time_b = strtotime($val_b);
            return $sort_order === 'desc' ? ($time_b - $time_a) : ($time_a - $time_b);
        }
        
        return $sort_order === 'desc' ? strcasecmp($val_b, $val_a) : strcasecmp($val_a, $val_b);
    });
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Laporan Rekam Medis</title>
    <style>
        @media print {
            .no-print { display: none !important; }
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 10pt; 
            line-height: 1.4;
            margin: 0;
            padding: 15px;
            color: #333;
        }
        .container { max-width: 100%; margin: 0 auto; }
        .header { 
            text-align: center; 
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4a90e2;
        }
        .header h1 { margin: 0; font-size: 22px; color: #2c3e50; }
        .header h2 { margin: 5px 0; font-size: 16px; color: #4a90e2; }
        .header p { margin: 3px 0; color: #666; font-size: 9pt; }
        .info { 
            margin-bottom: 15px; 
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #4a90e2;
            font-size: 9pt;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-size: 8pt;
        }
        table th { 
            background-color: #4a90e2; 
            color: white;
            padding: 6px 4px; 
            border: 1px solid #357ae8;
            text-align: left;
            font-weight: 600;
        }
        table td { 
            padding: 5px 4px; 
            border: 1px solid #ddd; 
            vertical-align: top;
        }
        table tr:nth-child(even) { background-color: #f8f9fa; }
        .footer { 
            margin-top: 20px; 
            text-align: center; 
            font-size: 8pt; 
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-baru { background-color: #28a745; color: white; padding: 2px 8px; border-radius: 20px; font-size: 8pt; }
        .badge-kontrol { background-color: #17a2b8; color: white; padding: 2px 8px; border-radius: 20px; font-size: 8pt; }
        .controls { margin-bottom: 15px; text-align: center; }
        .controls button { padding: 6px 15px; margin: 0 5px; border: none; border-radius: 4px; cursor: pointer; }
        .controls button#print { background-color: #28a745; color: white; }
        .controls button#close { background-color: #dc3545; color: white; }
        @page { margin: 10px; size: A4 landscape; }
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
            <h2>Laporan Rekam Medis</h2>
            <p>Jl. Contoh No. 123, Kota Contoh | Telp: (021) 1234-5678</p>
            <p>Dicetak pada: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        
        <div class="info">
            <strong>Informasi Laporan:</strong><br>
            <?php
            if (!empty($search_query)) echo "• Pencarian: " . htmlspecialchars($search_query) . "<br>";
            if (isset($_GET['tgl_periksa_mulai']) || isset($_GET['tgl_periksa_selesai'])) {
                echo "• Rentang Tanggal: ";
                if (isset($_GET['tgl_periksa_mulai'])) echo date('d-m-Y', strtotime($_GET['tgl_periksa_mulai']));
                if (isset($_GET['tgl_periksa_mulai']) && isset($_GET['tgl_periksa_selesai'])) echo " s/d ";
                if (isset($_GET['tgl_periksa_selesai'])) echo date('d-m-Y', strtotime($_GET['tgl_periksa_selesai']));
                echo "<br>";
            }
            if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) echo "• ID Pasien: " . htmlspecialchars($_GET['id_pasien']) . "<br>";
            if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) echo "• Kode Dokter: " . htmlspecialchars($_GET['kode_dokter']) . "<br>";
            if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) echo "• Jenis Kunjungan: " . htmlspecialchars($_GET['jenis_kunjungan']) . "<br>";
            ?>
            • Total Data: <strong><?= count($all_rekam_medis) ?></strong> rekam medis
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="10%">ID Rekam</th>
                    <th width="10%">ID Pasien</th>
                    <th width="20%">Nama Pasien</th>
                    <th width="10%">Kode Dokter</th>
                    <th width="20%">Nama Dokter</th>
                    <th width="10%">Jenis</th>
                    <th width="15%">Tgl Periksa</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($all_rekam_medis) > 0) {
                    $no = 1;
                    foreach ($all_rekam_medis as $rekam) {
                        $id_rekam = $rekam['id_rekam'] ?? '';
                        $nama_pasien = getNamaPasienById($rekam['id_pasien'] ?? '', $all_pasien);
                        $nama_dokter = getNamaDokterByKode($rekam['kode_dokter'] ?? '', $all_dokter);
                        
                        $tanggal = !empty($rekam['tanggal_periksa']) ? date('d-m-Y H:i', strtotime($rekam['tanggal_periksa'])) : '-';
                        $jenis_class = ($rekam['jenis_kunjungan'] ?? '') == 'Baru' ? 'badge-baru' : 'badge-kontrol';
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= htmlspecialchars($id_rekam) ?></td>
                    <td class="text-center"><?= htmlspecialchars($rekam['id_pasien'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($nama_pasien) ?></td>
                    <td class="text-center"><?= htmlspecialchars($rekam['kode_dokter'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($nama_dokter) ?></td>
                    <td class="text-center"><span class="<?= $jenis_class ?>"><?= htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') ?></span></td>
                    <td class="text-center"><?= $tanggal ?></td>
                </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center">Tidak ada data rekam medis ditemukan.</td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #e8f4fd;">
                    <td colspan="8" class="text-center"><strong>TOTAL REKAM MEDIS: <?= count($all_rekam_medis) ?> record</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <?php if (count($all_rekam_medis) > 0): 
            $pasien_list = array_unique(array_column($all_rekam_medis, 'id_pasien'));
            $dokter_list = array_unique(array_column($all_rekam_medis, 'kode_dokter'));
        ?>
        <div class="summary" style="margin-top: 15px; padding: 10px; background-color: #f1f8ff; font-size: 9pt;">
            <strong>RINGKASAN DATA:</strong><br>
            • Total Pasien Dilayani: <?= count($pasien_list) ?> pasien<br>
            • Total Dokter Bertugas: <?= count($dokter_list) ?> dokter
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
            <p>Copyright © <?= date('Y') ?> Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.focus();
            setTimeout(function() { window.print(); }, 500);
        }
    </script>
</body>
</html>
<?php
    exit();
} else {
    header('Location: ../../laporan-rekam-medis.php');
    exit();
}
?>