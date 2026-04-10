<?php
session_start();
require_once "../../pages/koneksi.php";

$db = new database();

// PROSES EXPORT PDF dengan dompdf
if (isset($_GET['export_pdf'])) {
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
    
    // Cek apakah dompdf sudah diinstall
    $dompdf_installed = false;
    
    // Cek beberapa kemungkinan path
    $possible_paths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
        __DIR__ . '/../../vendor/dompdf/dompdf/autoload.inc.php',
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $dompdf_installed = true;
            break;
        }
    }
    
    // Jika dompdf tidak ditemukan, beri opsi alternatif
    if (!$dompdf_installed) {
        // Redirect ke halaman cetak sebagai alternatif
        $redirect_url = '../../pages/laporan-data-pasien.php?cetak=1';
        $params = getExportParams();
        if (!empty($params)) {
            $redirect_url .= $params;
        }
        header('Location: ' . $redirect_url);
        exit();
    }
    
    // Fungsi untuk mendapatkan parameter filter
    function getExportParams() {
        $params = [];
        
        // Parameter filter yang mungkin ada
        $filter_params = [
            'search', 'jenis_kelamin', 'tgl_lahir_mulai', 'tgl_lahir_selesai',
            'tgl_registrasi_mulai', 'tgl_registrasi_selesai', 'sort', 'sort_column'
        ];
        
        foreach ($filter_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $params[] = $param . '=' . urlencode($_GET[$param]);
            }
        }
        
        return $params ? '&' . implode('&', $params) : '';
    }
    
    // Hitung statistik untuk ringkasan
    $laki_laki = 0;
    $perempuan = 0;
    foreach ($all_pasien as $pasien) {
        if (($pasien['jenis_kelamin_pasien'] ?? '') == 'L') $laki_laki++;
        if (($pasien['jenis_kelamin_pasien'] ?? '') == 'P') $perempuan++;
    }
    
    // Buat HTML untuk PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Data Pasien</title>
        <style>
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 9pt;
                margin: 0;
                padding: 15px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #4a90e2;
                padding-bottom: 10px;
            }
            .header h1 {
                margin: 0;
                font-size: 18pt;
                color: #2c3e50;
            }
            .header h2 {
                margin: 5px 0;
                font-size: 14pt;
                color: #4a90e2;
            }
            .header p {
                margin: 5px 0;
                font-size: 8pt;
                color: #666;
            }
            .info {
                margin-bottom: 15px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 5px;
                font-size: 8pt;
                border-left: 4px solid #4a90e2;
            }
            .info strong {
                color: #2c3e50;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                font-size: 7.5pt;
            }
            table th {
                background-color: #4a90e2;
                color: white;
                padding: 6px 4px;
                border: 1px solid #357ae8;
                text-align: left;
                font-weight: bold;
            }
            table td {
                padding: 5px 4px;
                border: 1px solid #ddd;
            }
            table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .total {
                font-weight: bold;
                text-align: center;
                padding: 8px;
                background-color: #f1f8ff;
                border-top: 2px solid #4a90e2;
            }
            .summary {
                margin-top: 15px;
                padding: 10px;
                background-color: #f1f8ff;
                border-radius: 5px;
                font-size: 8pt;
            }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 7pt;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .badge {
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 7pt;
                font-weight: bold;
                display: inline-block;
            }
            .badge-laki {
                background-color: #cfe2ff;
                color: #0d6efd;
            }
            .badge-perempuan {
                background-color: #f8d7da;
                color: #e83e8c;
            }
            @page {
                margin: 15px;
                size: A4 landscape;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Poliklinik Mata Eyethica</h1>
            <h2>Laporan Data Pasien</h2>
            <p>Jl. Contoh No. 123, Kota Contoh | Telp: (021) 1234-5678</p>
            <p>Dicetak pada: ' . date('d-m-Y H:i:s') . ' | Total Data: ' . count($all_pasien) . ' pasien</p>
        </div>';
    
    // Info filter jika ada
    $filter_info = [];
    if (!empty($search_query)) $filter_info[] = "Pencarian: \"$search_query\"";
    if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
        $jk = $_GET['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
        $filter_info[] = "Jenis Kelamin: $jk";
    }
    if (isset($_GET['tgl_lahir_mulai']) || isset($_GET['tgl_lahir_selesai'])) {
        $range = "";
        if (isset($_GET['tgl_lahir_mulai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_lahir_mulai']));
        if (isset($_GET['tgl_lahir_mulai']) && isset($_GET['tgl_lahir_selesai'])) $range .= " s/d ";
        if (isset($_GET['tgl_lahir_selesai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_lahir_selesai']));
        $filter_info[] = "Tanggal Lahir: $range";
    }
    if (isset($_GET['tgl_registrasi_mulai']) || isset($_GET['tgl_registrasi_selesai'])) {
        $range = "";
        if (isset($_GET['tgl_registrasi_mulai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_registrasi_mulai']));
        if (isset($_GET['tgl_registrasi_mulai']) && isset($_GET['tgl_registrasi_selesai'])) $range .= " s/d ";
        if (isset($_GET['tgl_registrasi_selesai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_registrasi_selesai']));
        $filter_info[] = "Registrasi: $range";
    }
    
    // Info sorting
    $column_names = [
        'id_pasien' => 'ID Pasien',
        'nama_pasien' => 'Nama Pasien',
        'tgl_lahir_pasien' => 'Tanggal Lahir',
        'tanggal_registrasi_pasien' => 'Tanggal Registrasi'
    ];
    $sort_text = $column_names[$sort_column] ?? 'ID Pasien';
    $sort_text .= $sort_order == 'asc' ? ' (A-Z / Terlama)' : ' (Z-A / Terbaru)';
    $filter_info[] = "Diurutkan berdasarkan: $sort_text";
    
    if (!empty($filter_info)) {
        $html .= '<div class="info"><strong>Informasi Laporan:</strong><br>';
        foreach ($filter_info as $info) {
            $html .= '• ' . $info . '<br>';
        }
        $html .= '</div>';
    }
    
    if (count($all_pasien) > 0) {
        $html .= '<table>
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
            <tbody>';
        
        $no = 1;
        
        foreach ($all_pasien as $pasien) {
            // Format NIK
            $nik = $pasien['nik'] ?? '-';
            
            // Format jenis kelamin
            $jenis_kelamin = $pasien['jenis_kelamin_pasien'] ?? '';
            $jenis_kelamin_text = $jenis_kelamin == 'L' ? 'Laki-laki' : ($jenis_kelamin == 'P' ? 'Perempuan' : '-');
            $jk_badge_class = $jenis_kelamin == 'L' ? 'badge-laki' : ($jenis_kelamin == 'P' ? 'badge-perempuan' : '');
            
            // Format tanggal lahir
            $tgl_lahir_formatted = !empty($pasien['tgl_lahir_pasien']) ? 
                date('d-m-Y', strtotime($pasien['tgl_lahir_pasien'])) : '-';
            
            // Format tanggal registrasi
            $tgl_registrasi_formatted = !empty($pasien['tanggal_registrasi_pasien']) ? 
                date('d-m-Y H:i:s', strtotime($pasien['tanggal_registrasi_pasien'])) : '-';
            
            // Format alamat (potong jika terlalu panjang)
            $alamat = $pasien['alamat_pasien'] ?? '-';
            $alamat_display = strlen($alamat) > 35 ? substr($alamat, 0, 35) . '...' : $alamat;
            
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td class="text-center">' . htmlspecialchars($pasien['id_pasien'] ?? '') . '</td>
                <td class="text-center">' . htmlspecialchars($nik) . '</td>
                <td>' . htmlspecialchars($pasien['nama_pasien'] ?? '-') . '</td>
                <td class="text-center"><span class="badge ' . $jk_badge_class . '">' . htmlspecialchars($jenis_kelamin_text) . '</span></td>
                <td class="text-center">' . htmlspecialchars($tgl_lahir_formatted) . '</td>
                <td>' . htmlspecialchars($alamat_display) . '</td>
                <td class="text-center">' . htmlspecialchars($pasien['telepon_pasien'] ?? '-') . '</td>
                <td class="text-center">' . htmlspecialchars($tgl_registrasi_formatted) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Ringkasan statistik
        $html .= '<div class="summary">
            <strong>RINGKASAN DATA PASIEN:</strong><br>
            <table style="width: 50%; margin-top: 5px; border: none;">
                <tr>
                    <td width="40%"><strong>Total Pasien:</strong></td>
                    <td>' . count($all_pasien) . ' orang</td>
                </tr>
                <tr>
                    <td><strong>Laki-laki:</strong></td>
                    <td>' . $laki_laki . ' orang (' . round(($laki_laki/count($all_pasien))*100, 1) . '%)</td>
                </tr>
                <tr>
                    <td><strong>Perempuan:</strong></td>
                    <td>' . $perempuan . ' orang (' . round(($perempuan/count($all_pasien))*100, 1) . '%)</td>
                </tr>
            </table>
        </div>';
        
    } else {
        $html .= '<p style="text-align: center; padding: 30px; color: #666; border: 1px solid #ddd; border-radius: 5px;">Tidak ada data pasien ditemukan.</p>';
    }
    
    $html .= '
        <div class="footer">
            <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
            <p>Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    // Buat instance dompdf
    try {
        $dompdf = new Dompdf\Dompdf();
        
        // Set paper ke landscape karena tabel cukup lebar (9 kolom)
        $dompdf->setPaper('A4', 'landscape');
        
        // Load HTML
        $dompdf->loadHtml($html);
        
        // Render PDF
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream('laporan_pasien_' . date('Y-m-d') . '.pdf', [
            'Attachment' => true // true untuk download, false untuk preview
        ]);
        
        exit();
        
    } catch (Exception $e) {
        // Jika error, redirect ke halaman cetak
        $redirect_url = '../../pages/laporan-data-pasien.php?cetak=1';
        $params = getExportParams();
        if (!empty($params)) {
            $redirect_url .= $params;
        }
        header('Location: ' . $redirect_url);
        exit();
    }
} else {
    // Redirect jika tidak ada parameter export_pdf
    header('Location: ../../pages/laporan-data-pasien.php');
    exit();
}
?>