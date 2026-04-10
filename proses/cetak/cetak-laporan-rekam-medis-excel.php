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

if (isset($_GET['export_excel'])) {
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
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_rekam_medis_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8"><title>Laporan Rekam Medis</title>';
    echo '<style>';
    echo 'th { background-color: #4a90e2; color: white; font-weight: bold; }';
    echo 'td, th { padding: 6px; border: 1px solid #ddd; }';
    echo '.header-title { font-size: 16pt; font-weight: bold; text-align: center; }';
    echo '.header-info { font-size: 10pt; text-align: center; }';
    echo '.text-right { text-align: right; }';
    echo '.text-center { text-align: center; }';
    echo '</style></head><body>';
    
    echo '<div class="header-title">Poliklinik Mata Eyethica</div>';
    echo '<div class="header-info">Laporan Rekam Medis</div>';
    echo '<div class="header-info">Dicetak pada: ' . date('d-m-Y H:i:s') . '</div>';
    
    if (!empty($search_query) || isset($_GET['tgl_periksa_mulai']) || isset($_GET['tgl_periksa_selesai']) || 
        isset($_GET['id_pasien']) || isset($_GET['kode_dokter']) || isset($_GET['jenis_kunjungan'])) {
        echo '<div style="margin: 10px 0; padding: 8px; background-color: #f8f9fa;">';
        echo '<strong>Filter:</strong><br>';
        if (!empty($search_query)) echo '- Pencarian: ' . htmlspecialchars($search_query) . '<br>';
        if (isset($_GET['tgl_periksa_mulai']) || isset($_GET['tgl_periksa_selesai'])) {
            echo '- Tanggal: ';
            if (isset($_GET['tgl_periksa_mulai'])) echo date('d-m-Y', strtotime($_GET['tgl_periksa_mulai']));
            if (isset($_GET['tgl_periksa_mulai']) && isset($_GET['tgl_periksa_selesai'])) echo ' s/d ';
            if (isset($_GET['tgl_periksa_selesai'])) echo date('d-m-Y', strtotime($_GET['tgl_periksa_selesai']));
            echo '<br>';
        }
        if (isset($_GET['id_pasien']) && !empty($_GET['id_pasien'])) echo '- ID Pasien: ' . htmlspecialchars($_GET['id_pasien']) . '<br>';
        if (isset($_GET['kode_dokter']) && !empty($_GET['kode_dokter'])) echo '- Kode Dokter: ' . htmlspecialchars($_GET['kode_dokter']) . '<br>';
        if (isset($_GET['jenis_kunjungan']) && !empty($_GET['jenis_kunjungan'])) echo '- Jenis Kunjungan: ' . htmlspecialchars($_GET['jenis_kunjungan']) . '<br>';
        echo '</div>';
    }
    
    echo '<table border="1">';
    echo '<thead><tr>';
    echo '<th>No</th><th>ID Rekam</th><th>ID Pasien</th><th>Nama Pasien</th>';
    echo '<th>Kode Dokter</th><th>Nama Dokter</th><th>Jenis Kunjungan</th><th>Tanggal Periksa</th>';
    echo '</table></thead><tbody>';
    
    if (count($all_rekam_medis) > 0) {
        $no = 1;
        foreach ($all_rekam_medis as $rekam) {
            $id_rekam = $rekam['id_rekam'] ?? '';
            $tanggal = !empty($rekam['tanggal_periksa']) ? date('d-m-Y H:i', strtotime($rekam['tanggal_periksa'])) : '-';
            
            echo '<tr>';
            echo '<td class="text-center">' . $no++ . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($id_rekam) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($rekam['id_pasien'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars(getNamaPasienById($rekam['id_pasien'] ?? '', $all_pasien)) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($rekam['kode_dokter'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars(getNamaDokterByKode($rekam['kode_dokter'] ?? '', $all_dokter)) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($rekam['jenis_kunjungan'] ?? '-') . '</td>';
            echo '<td class="text-center">' . $tanggal . '</td>';
            echo '</tr>';
        }
        
        echo '<tr style="background-color: #e8f4fd;">';
        echo '<td colspan="8" class="text-center">TOTAL REKAM MEDIS: ' . count($all_rekam_medis) . ' record</td>';
        echo '</tr>';
        
        $pasien_unik = count(array_unique(array_column($all_rekam_medis, 'id_pasien')));
        $dokter_unik = count(array_unique(array_column($all_rekam_medis, 'kode_dokter')));
        
        echo '<tr>';
        echo '<td colspan="8">';
        echo '<strong>Ringkasan:</strong> Total Pasien: ' . $pasien_unik . ', ';
        echo 'Total Dokter: ' . $dokter_unik;
        echo '</td>';
        echo '</tr>';
    } else {
        echo '<tr><td colspan="8" class="text-center">Tidak ada data rekam medis ditemukan.</td></tr>';
    }
    
    echo '</tbody></table>';
    echo '<div style="margin-top: 15px; text-align: center; font-size: 9pt;">';
    echo 'Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica<br>';
    echo 'Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.';
    echo '</div></body></html>';
    
    exit();
} else {
    header('Location: ../../laporan-rekam-medis.php');
    exit();
}
?>