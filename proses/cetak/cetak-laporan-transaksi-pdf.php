<?php
session_start();
require_once "../../koneksi.php";

$db = new database();

// PROSES EXPORT PDF dengan dompdf
if (isset($_GET['export_pdf'])) {
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
        $all_transaksi = array_filter($all_transaksi, function($transaksi) use ($id_pasien, $db) {
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
    
    // Sorting
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
    
    // Reset array keys setelah filter
    $all_transaksi = array_values($all_transaksi);
    
    // Cek apakah dompdf sudah diinstall
    $dompdf_installed = false;
    
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
    
    if (!$dompdf_installed) {
        $redirect_url = '../../laporan-transaksi.php?cetak=1';
        $params = getExportParams();
        if (!empty($params)) {
            $redirect_url .= $params;
        }
        header('Location: ' . $redirect_url);
        exit();
    }
    
    function getExportParams() {
        $params = [];
        $filter_params = [
            'search', 'status', 'metode', 'pasien', 'staff', 
            'tanggal_mulai', 'tanggal_selesai', 'sort'
        ];
        
        foreach ($filter_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $params[] = $param . '=' . urlencode($_GET[$param]);
            }
        }
        
        return $params ? '&' . implode('&', $params) : '';
    }
    
    // Buat HTML untuk PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Data Transaksi</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 9pt;
                margin: 0;
                padding: 12px;
            }
            .header {
                text-align: center;
                margin-bottom: 18px;
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
                margin: 3px 0;
                font-size: 8pt;
                color: #666;
            }
            .info {
                margin-bottom: 12px;
                padding: 8px 12px;
                background-color: #f8f9fa;
                border-radius: 5px;
                font-size: 8pt;
                border-left: 3px solid #4a90e2;
            }
            .info p {
                margin: 3px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
                font-size: 7.5pt;
            }
            table th {
                background-color: #4a90e2;
                color: white;
                padding: 6px 5px;
                border: 1px solid #357ae8;
                text-align: left;
            }
            table td {
                padding: 5px;
                border: 1px solid #ddd;
            }
            table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .total {
                font-weight: bold;
                text-align: right;
                padding: 8px;
                background-color: #f1f8ff;
                border-top: 2px solid #4a90e2;
            }
            .footer {
                margin-top: 18px;
                text-align: center;
                font-size: 7pt;
                color: #666;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }
            .text-right {
                text-align: right;
            }
            .badge {
                padding: 2px 5px;
                border-radius: 3px;
                font-size: 7pt;
                font-weight: bold;
                display: inline-block;
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
            
            @page {
                margin: 12mm;
                size: A4 landscape;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Poliklinik Mata Eyethica</h1>
            <h2>Laporan Data Transaksi</h2>
            <p>Dicetak pada: ' . date('d-m-Y H:i:s') . ' | Total Data: ' . count($all_transaksi) . '</p>
        </div>';
    
    // Info filter jika ada
    $filter_info = [];
    if (!empty($search_query)) $filter_info[] = "Pencarian: $search_query";
    if (isset($_GET['status']) && !empty($_GET['status'])) $filter_info[] = "Status: " . $_GET['status'];
    if (isset($_GET['metode']) && !empty($_GET['metode'])) $filter_info[] = "Metode: " . $_GET['metode'];
    if (isset($_GET['pasien']) && !empty($_GET['pasien'])) {
        foreach ($all_pasien as $pasien) {
            if ($pasien['id_pasien'] == $_GET['pasien']) {
                $filter_info[] = "Pasien: " . $pasien['nama_pasien'];
                break;
            }
        }
    }
    if (isset($_GET['staff']) && !empty($_GET['staff'])) {
        foreach ($all_staff as $staff) {
            if ($staff['kode_staff'] == $_GET['staff']) {
                $filter_info[] = "Staff: " . $staff['nama_staff'];
                break;
            }
        }
    }
    if (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) $filter_info[] = "Dari: " . $_GET['tanggal_mulai'];
    if (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) $filter_info[] = "Sampai: " . $_GET['tanggal_selesai'];
    if ($sort_order === 'desc') $filter_info[] = "Urutan: Terbaru";
    else $filter_info[] = "Urutan: Terlama";
    
    if (!empty($filter_info)) {
        $html .= '<div class="info"><strong>Filter Aktif:</strong> ' . implode(' | ', $filter_info) . '</div>';
    }
    
    if (count($all_transaksi) > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="11%">ID Transaksi</th>
                    <th width="10%">ID Rekam</th>
                    <th width="14%">Nama Pasien</th>
                    <th width="9%">Kode Staff</th>
                    <th width="13%">Tanggal</th>
                    <th width="10%">Metode</th>
                    <th width="11%">Biaya</th>
                    <th width="8%">Status</th>
                </tr>
            </thead>
            <tbody>';
        
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
                date('d-m-Y H:i', strtotime($transaksi['tanggal_transaksi'])) : '-';
            
            $biaya = $transaksi['grand_total'] ?? 0;
            $biaya_formatted = 'Rp ' . number_format($biaya, 0, ',', '.');
            $total_biaya += $biaya;
            
            $status_badge = ($transaksi['status_pembayaran'] == 'Lunas') ? 
                '<span class="badge badge-success">LUNAS</span>' : 
                '<span class="badge badge-warning">BELUM</span>';
            
            $metode_class = '';
            switch($transaksi['metode_pembayaran']) {
                case 'Tunai': $metode_class = 'badge-tunai'; break;
                case 'Transfer': $metode_class = 'badge-transfer'; break;
                case 'QRIS': $metode_class = 'badge-qris'; break;
                case 'Debit': $metode_class = 'badge-debit'; break;
                default: $metode_class = 'badge-secondary';
            }
            
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($transaksi['id_transaksi'] ?? '') . '</td>
                <td>' . htmlspecialchars($transaksi['id_rekam'] ?? '-') . '</td>
                <td>' . htmlspecialchars($nama_pasien) . '</td>
                <td>' . htmlspecialchars($transaksi['kode_staff'] ?? '-') . '</td>
                <td>' . htmlspecialchars($tanggal_formatted) . '</td>
                <td><span class="badge ' . $metode_class . '">' . htmlspecialchars($transaksi['metode_pembayaran'] ?? '-') . '</span></td>
                <td class="text-right">' . htmlspecialchars($biaya_formatted) . '</td>
                <td>' . $status_badge . '</td>
            </tr>';
        }
        
        $html .= '<tr>
            <td colspan="7" class="total">TOTAL BIAYA:</td>
            <td class="total">Rp ' . number_format($total_biaya, 0, ',', '.') . '</td>
            <td class="total"></td>
        </tr>';
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<p style="text-align: center; padding: 20px; color: #666;">Tidak ada data transaksi ditemukan.</p>';
    }
    
    $html .= '
        <div class="footer">
            <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
            <p>Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    try {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream('laporan_transaksi_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
        exit();
    } catch (Exception $e) {
        $redirect_url = '../../laporan-transaksi.php?cetak=1';
        $params = getExportParams();
        if (!empty($params)) {
            $redirect_url .= $params;
        }
        header('Location: ' . $redirect_url);
        exit();
    }
} else {
    header('Location: ../../laporan-transaksi.php');
    exit();
}
?>