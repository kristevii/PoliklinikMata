<?php
session_start();
require_once "../../pages/koneksi.php";

$db = new database();

// PROSES CETAK
if (isset($_GET['cetak'])) {
    // Konfigurasi filter sama seperti di halaman
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
    $sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_pasien';
    
    // Ambil semua data pasien
    $all_pasien = $db->tampil_data_pasien();
    
    // Filter data berdasarkan search query
    if (!empty($search_query)) {
        $filtered_pasien = [];
        foreach ($all_pasien as $pasien) {
            // Cari di semua kolom yang relevan (termasuk NIK)
            if (stripos($pasien['id_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['nik'] ?? '', $search_query) !== false ||
                stripos($pasien['nama_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['jenis_kelamin_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['tgl_lahir_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['alamat_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['telepon_pasien'] ?? '', $search_query) !== false ||
                stripos($pasien['tanggal_registrasi_pasien'] ?? '', $search_query) !== false) {
                $filtered_pasien[] = $pasien;
            }
        }
        $all_pasien = $filtered_pasien;
    }
    
    // Filter berdasarkan jenis kelamin
    if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
        $jenis_kelamin = trim($_GET['jenis_kelamin']);
        $all_pasien = array_filter($all_pasien, function($pasien) use ($jenis_kelamin) {
            return ($pasien['jenis_kelamin_pasien'] ?? '') == $jenis_kelamin;
        });
    }
    
    // Filter berdasarkan rentang tanggal lahir
    if (isset($_GET['tgl_lahir_mulai']) && !empty($_GET['tgl_lahir_mulai'])) {
        $tgl_lahir_mulai = trim($_GET['tgl_lahir_mulai']);
        $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_lahir_mulai) {
            return strtotime($pasien['tgl_lahir_pasien'] ?? '') >= strtotime($tgl_lahir_mulai);
        });
    }
    
    if (isset($_GET['tgl_lahir_selesai']) && !empty($_GET['tgl_lahir_selesai'])) {
        $tgl_lahir_selesai = trim($_GET['tgl_lahir_selesai']);
        $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_lahir_selesai) {
            return strtotime($pasien['tgl_lahir_pasien'] ?? '') <= strtotime($tgl_lahir_selesai);
        });
    }
    
    // Filter berdasarkan rentang tanggal registrasi
    if (isset($_GET['tgl_registrasi_mulai']) && !empty($_GET['tgl_registrasi_mulai'])) {
        $tgl_registrasi_mulai = trim($_GET['tgl_registrasi_mulai']);
        $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_registrasi_mulai) {
            return strtotime($pasien['tanggal_registrasi_pasien'] ?? '') >= strtotime($tgl_registrasi_mulai);
        });
    }
    
    if (isset($_GET['tgl_registrasi_selesai']) && !empty($_GET['tgl_registrasi_selesai'])) {
        $tgl_registrasi_selesai = trim($_GET['tgl_registrasi_selesai']);
        $all_pasien = array_filter($all_pasien, function($pasien) use ($tgl_registrasi_selesai) {
            return strtotime($pasien['tanggal_registrasi_pasien'] ?? '') <= strtotime($tgl_registrasi_selesai . ' 23:59:59');
        });
    }
    
    // Reset array keys setelah filter
    $all_pasien = array_values($all_pasien);
    
    // Urutkan data berdasarkan kolom yang dipilih
    usort($all_pasien, function($a, $b) use ($sort_column, $sort_order) {
        $val_a = $a[$sort_column] ?? '';
        $val_b = $b[$sort_column] ?? '';
        
        // Handle numeric comparison untuk ID
        if ($sort_column == 'id_pasien') {
            if ($sort_order === 'desc') {
                return ($val_b - $val_a);
            } else {
                return ($val_a - $val_b);
            }
        }
        
        // String comparison untuk kolom lainnya
        if ($sort_order === 'desc') {
            return strcasecmp($val_b, $val_a);
        } else {
            return strcasecmp($val_a, $val_b);
        }
    });
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Laporan Data Pasien</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 10pt; 
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 100%; 
            margin: 0 auto; 
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4a90e2;
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #2c3e50;
            font-weight: bold;
        }
        .header h2 { 
            margin: 5px 0 10px 0; 
            font-size: 18px; 
            color: #4a90e2;
            font-weight: normal;
        }
        .header p { 
            margin: 5px 0; 
            color: #666;
        }
        .info { 
            margin-bottom: 20px; 
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #4a90e2;
        }
        .info p { 
            margin: 5px 0; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-size: 9pt;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        table th { 
            background-color: #4a90e2; 
            color: white;
            text-align: left; 
            padding: 8px 6px; 
            border: 1px solid #ddd;
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
        table tr:hover {
            background-color: #e9f7fe;
        }
        .total { 
            font-weight: bold; 
            text-align: right; 
            padding: 10px 8px;
            background-color: #f1f8ff;
            border-top: 2px solid #4a90e2;
        }
        .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 9pt; 
            color: #777;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .text-right {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9pt;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 4px;
        }
        .badge-laki-laki {
            background-color: #cfe2ff;
            color: #0d6efd;
        }
        .badge-perempuan {
            background-color: #f8d7da;
            color: #e83e8c;
        }
        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }
        .controls button {
            padding: 8px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            background-color: #4a90e2;
            color: white;
            cursor: pointer;
            font-size: 12pt;
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
        .info-sort {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
            font-style: italic;
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
            <h2>Laporan Data Pasien</h2>
            <p>Jl. Contoh No. 123, Kota Contoh</p>
            <p>Telepon: (021) 1234-5678 | Email: info@eyethica.com</p>
            <p>Dicetak pada: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        
        <div class="info">
            <p><strong>Informasi Laporan:</strong></p>
            <?php
            if (!empty($search_query)) echo "<p>Pencarian: <strong>" . htmlspecialchars($search_query) . "</strong></p>";
            if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
                $jk = $_GET['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
                echo "<p>Jenis Kelamin: <strong>$jk</strong></p>";
            }
            if (isset($_GET['tgl_lahir_mulai']) || isset($_GET['tgl_lahir_selesai'])) {
                echo "<p>Rentang Tanggal Lahir: ";
                if (isset($_GET['tgl_lahir_mulai'])) echo "<strong>" . date('d-m-Y', strtotime($_GET['tgl_lahir_mulai'])) . "</strong>";
                if (isset($_GET['tgl_lahir_mulai']) && isset($_GET['tgl_lahir_selesai'])) echo " s/d ";
                if (isset($_GET['tgl_lahir_selesai'])) echo "<strong>" . date('d-m-Y', strtotime($_GET['tgl_lahir_selesai'])) . "</strong>";
                echo "</p>";
            }
            if (isset($_GET['tgl_registrasi_mulai']) || isset($_GET['tgl_registrasi_selesai'])) {
                echo "<p>Rentang Tanggal Registrasi: ";
                if (isset($_GET['tgl_registrasi_mulai'])) echo "<strong>" . date('d-m-Y', strtotime($_GET['tgl_registrasi_mulai'])) . "</strong>";
                if (isset($_GET['tgl_registrasi_mulai']) && isset($_GET['tgl_registrasi_selesai'])) echo " s/d ";
                if (isset($_GET['tgl_registrasi_selesai'])) echo "<strong>" . date('d-m-Y', strtotime($_GET['tgl_registrasi_selesai'])) . "</strong>";
                echo "</p>";
            }
            ?>
            <p>Total Data: <strong><?= count($all_pasien) ?></strong> pasien</p>
            <div class="info-sort">
                <strong>Diurutkan berdasarkan:</strong> 
                <?php
                $column_names = [
                    'id_pasien' => 'ID Pasien',
                    'nama_pasien' => 'Nama Pasien',
                    'tgl_lahir_pasien' => 'Tanggal Lahir',
                    'tanggal_registrasi_pasien' => 'Tanggal Registrasi'
                ];
                $sort_text = $column_names[$sort_column] ?? 'ID Pasien';
                $sort_text .= $sort_order == 'asc' ? ' (A-Z / Terlama)' : ' (Z-A / Terbaru)';
                echo $sort_text;
                ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="7%">ID Pasien</th>
                    <th width="12%">NIK</th>
                    <th width="14%">Nama Pasien</th>
                    <th width="7%">Jenis Kelamin</th>
                    <th width="9%">Tanggal Lahir</th>
                    <th width="22%">Alamat</th>
                    <th width="9%">Telepon</th>
                    <th width="16%">Tanggal Registrasi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($all_pasien) > 0) {
                    $no = 1;
                    foreach ($all_pasien as $pasien) {
                        // Format NIK
                        $nik = $pasien['nik'] ?? '-';
                        
                        // Format jenis kelamin
                        $jenis_kelamin = $pasien['jenis_kelamin_pasien'] ?? '';
                        $jenis_kelamin_text = $jenis_kelamin == 'L' ? 'Laki-laki' : ($jenis_kelamin == 'P' ? 'Perempuan' : '-');
                        $jk_badge_class = $jenis_kelamin == 'L' ? 'badge-laki-laki' : ($jenis_kelamin == 'P' ? 'badge-perempuan' : '');
                        
                        // Format tanggal lahir
                        $tgl_lahir_formatted = !empty($pasien['tgl_lahir_pasien']) ? 
                            date('d-m-Y', strtotime($pasien['tgl_lahir_pasien'])) : '-';
                        
                        // Format tanggal registrasi
                        $tgl_registrasi_formatted = !empty($pasien['tanggal_registrasi_pasien']) ? 
                            date('d-m-Y H:i:s', strtotime($pasien['tanggal_registrasi_pasien'])) : '-';
                        
                        // Format alamat
                        $alamat = $pasien['alamat_pasien'] ?? '-';
                        
                        // Format telepon
                        $telepon = $pasien['telepon_pasien'] ?? '-';
                ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td align="center"><?= htmlspecialchars($pasien['id_pasien'] ?? '') ?></td>
                    <td><?= htmlspecialchars($nik) ?></td>
                    <td><?= htmlspecialchars($pasien['nama_pasien'] ?? '-') ?></td>
                    <td align="center">
                        <span class="badge <?= $jk_badge_class ?>"><?= htmlspecialchars($jenis_kelamin_text) ?></span>
                    </td>
                    <td align="center"><?= htmlspecialchars($tgl_lahir_formatted) ?></td>
                    <td><?= htmlspecialchars($alamat) ?></td>
                    <td align="center"><?= htmlspecialchars($telepon) ?></td>
                    <td align="center"><?= htmlspecialchars($tgl_registrasi_formatted) ?></td>
                </tr>
                <?php 
                    }
                } else {
                    echo '<tr><td colspan="9" align="center">Tidak ada data pasien ditemukan.</td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <?php if (count($all_pasien) > 0): ?>
                <tr>
                    <td colspan="9" class="total">
                        TOTAL PASIEN: <?= count($all_pasien) ?> orang
                    </td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>
        
        <?php if (count($all_pasien) > 0): ?>
        <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
            <p><strong>Ringkasan Data:</strong></p>
            <?php
            // Hitung statistik jenis kelamin
            $laki_laki = 0;
            $perempuan = 0;
            foreach ($all_pasien as $pasien) {
                if (($pasien['jenis_kelamin_pasien'] ?? '') == 'L') $laki_laki++;
                if (($pasien['jenis_kelamin_pasien'] ?? '') == 'P') $perempuan++;
            }
            ?>
            <table style="width: 50%; margin-top: 5px; font-size: 9pt;">
                <tr>
                    <td width="40%"><strong>Laki-laki:</strong></td>
                    <td><?= $laki_laki ?> orang</td>
                </tr>
                <tr>
                    <td><strong>Perempuan:</strong></td>
                    <td><?= $perempuan ?> orang</td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
            <p>Copyright © <?= date('Y') ?> Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Auto focus dan print saat halaman terbuka
        window.onload = function() {
            window.focus();
            // Auto print setelah 500ms
            setTimeout(function() {
                window.print();
            }, 500);
        }
        
        // Event listener untuk tombol cetak
        document.addEventListener('keydown', function(e) {
            // Ctrl+P untuk print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            // Esc untuk close
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
    // Redirect jika tidak ada parameter cetak
    header('Location: ../../pages/laporan-data-pasien.php');
    exit();
}
?>