<?php
session_start();
require_once "../../koneksi.php";

$db = new database();

function getNamaPasienById($id_pasien, $all_pasien) {
    foreach ($all_pasien as $pasien) {
        if ($pasien['id_pasien'] == $id_pasien) return $pasien['nama_pasien'];
    }
    return '-';
}

function getNamaDokterByKode($kode_dokter, $all_dokter) {
    foreach ($all_dokter as $dokter) {
        if ($dokter['kode_dokter'] == $kode_dokter) return $dokter['nama_dokter'];
    }
    return '-';
}

if (isset($_GET['export_pdf'])) {
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';
    $sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'id_rekam';
    
    $all_rekam_medis = $db->tampil_data_rekam_medis();
    $all_pasien = $db->tampil_data_pasien();
    $all_dokter = $db->tampil_data_dokter();
    
    // Filter search
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
    
    // Filter lainnya
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
    if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) {
        $id_pasien = trim($_GET['id_pasien']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($id_pasien) {
            return ($rekam['id_pasien'] ?? '') == $id_pasien;
        });
    }
    if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) {
        $kode_dokter = trim($_GET['kode_dokter']);
        $all_rekam_medis = array_filter($all_rekam_medis, function($rekam) use ($kode_dokter) {
            return ($rekam['kode_dokter'] ?? '') == $kode_dokter;
        });
    }
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
    
    // Cek dompdf
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
        header('Location: cetak-laporan-rekam-medis-cetak.php?cetak=1' . getExportParams());
        exit();
    }
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Rekam Medis</title>
        <style>
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 8pt;
                margin: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 15px;
                border-bottom: 2px solid #4a90e2;
                padding-bottom: 8px;
            }
            .header h1 { margin: 0; font-size: 18pt; }
            .header h2 { margin: 3px 0; font-size: 14pt; color: #4a90e2; }
            .header p { margin: 2px 0; font-size: 8pt; }
            .info {
                margin-bottom: 10px;
                padding: 8px;
                background-color: #f8f9fa;
                border-left: 3px solid #4a90e2;
                font-size: 8pt;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 7pt;
            }
            table th {
                background-color: #4a90e2;
                color: white;
                padding: 5px 3px;
                border: 1px solid #357ae8;
                text-align: left;
            }
            table td {
                padding: 4px 3px;
                border: 1px solid #ddd;
                vertical-align: top;
            }
            .footer {
                margin-top: 15px;
                text-align: center;
                font-size: 7pt;
                border-top: 1px solid #ddd;
                padding-top: 8px;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            @page { margin: 8px; size: A4 landscape; }
            .badge-baru { color: #28a745; font-weight: bold; }
            .badge-kontrol { color: #17a2b8; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Poliklinik Mata Eyethica</h1>
            <h2>Laporan Rekam Medis</h2>
            <p>Dicetak pada: ' . date('d-m-Y H:i:s') . ' | Total Data: ' . count($all_rekam_medis) . ' rekam medis</p>
        </div>';
    
    // Info filter
    $filter_info = [];
    if (!empty($search_query)) $filter_info[] = "Pencarian: \"$search_query\"";
    if (isset($_GET['tgl_periksa_mulai']) || isset($_GET['tgl_periksa_selesai'])) {
        $range = "";
        if (isset($_GET['tgl_periksa_mulai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_periksa_mulai']));
        if (isset($_GET['tgl_periksa_mulai']) && isset($_GET['tgl_periksa_selesai'])) $range .= " s/d ";
        if (isset($_GET['tgl_periksa_selesai'])) $range .= date('d-m-Y', strtotime($_GET['tgl_periksa_selesai']));
        $filter_info[] = "Tanggal: $range";
    }
    if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) $filter_info[] = "ID Pasien: " . htmlspecialchars($_GET['id_pasien']);
    if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) $filter_info[] = "Kode Dokter: " . htmlspecialchars($_GET['kode_dokter']);
    if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) $filter_info[] = "Jenis: " . htmlspecialchars($_GET['jenis_kunjungan']);
    
    if (!empty($filter_info)) {
        $html .= '<div class="info"><strong>Filter:</strong><br>' . implode('<br>', $filter_info) . '</div>';
    }
    
    if (count($all_rekam_medis) > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th width="5%">No</th><th width="10%">ID Rekam</th><th width="10%">ID Pasien</th>
                    <th width="20%">Nama Pasien</th><th width="10%">Kode Dokter</th><th width="20%">Nama Dokter</th>
                    <th width="10%">Jenis</th><th width="15%">Tgl Periksa</th>
                </tr>
            </thead>
            <tbody>';
        
        $no = 1;
        foreach ($all_rekam_medis as $rekam) {
            $id_rekam = $rekam['id_rekam'] ?? '';
            $tanggal = !empty($rekam['tanggal_periksa']) ? date('d-m-Y H:i', strtotime($rekam['tanggal_periksa'])) : '-';
            $jenis_class = ($rekam['jenis_kunjungan'] ?? '') == 'Baru' ? 'badge-baru' : 'badge-kontrol';
            
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td class="text-center">' . htmlspecialchars($id_rekam) . '</td>
                <td class="text-center">' . htmlspecialchars($rekam['id_pasien'] ?? '-') . '</td>
                <td>' . htmlspecialchars(getNamaPasienById($rekam['id_pasien'] ?? '', $all_pasien)) . '</td>
                <td class="text-center">' . htmlspecialchars($rekam['kode_dokter'] ?? '-') . '</td>
                <td>' . htmlspecialchars(getNamaDokterByKode($rekam['kode_dokter'] ?? '', $all_dokter)) . '</td>
                <td class="text-center"><span class="' . $jenis_class . '">' . htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') . '</span></td>
                <td class="text-center">' . $tanggal . '</td>
            </tr>';
        }
        
        $html .= '<tr style="background-color: #e8f4fd;">
            <td colspan="8" class="text-center"><strong>TOTAL REKAM MEDIS: ' . count($all_rekam_medis) . ' record</strong></td>
        </tr>
        </tbody></table>';
        
        $pasien_unik = count(array_unique(array_column($all_rekam_medis, 'id_pasien')));
        $dokter_unik = count(array_unique(array_column($all_rekam_medis, 'kode_dokter')));
        
        $html .= '<div class="summary" style="margin-top: 10px; padding: 8px; background-color: #f1f8ff; font-size: 8pt;">
            <strong>RINGKASAN:</strong><br>
            • Total Pasien: ' . $pasien_unik . ' pasien<br>
            • Total Dokter: ' . $dokter_unik . ' dokter
        </div>';
    } else {
        $html .= '<p style="text-align:center;">Tidak ada data rekam medis ditemukan.</p>';
    }
    
    $html .= '<div class="footer">
        <p>Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica</p>
        <p>Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.</p>
    </div></body></html>';
    
    try {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream('laporan_rekam_medis_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
        exit();
    } catch (Exception $e) {
        header('Location: cetak-laporan-rekam-medis-cetak.php?cetak=1' . getExportParams());
        exit();
    }
}

function getExportParams() {
    $params = [];
    $filter_params = ['search', 'tgl_periksa_mulai', 'tgl_periksa_selesai', 'id_pasien', 'kode_dokter', 'jenis_kunjungan', 'sort', 'sort_column'];
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $params[] = $param . '=' . urlencode($_GET[$param]);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

if (!isset($_GET['export_pdf'])) {
    header('Location: ../../laporan-rekam-medis.php');
    exit();
}
?>