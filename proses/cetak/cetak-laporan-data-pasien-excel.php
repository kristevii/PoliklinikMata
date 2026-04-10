<?php
session_start();
require_once "../../pages/koneksi.php";

$db = new database();

// PROSES EXPORT EXCEL
if (isset($_GET['export_excel'])) {
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
    
    // Set headers untuk download Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_pasien_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Output Excel
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Laporan Data Pasien - ' . date('d-m-Y') . '</title>';
    echo '<style>';
    echo 'th { background-color: #4a90e2; color: white; font-weight: bold; }';
    echo 'td, th { padding: 8px; border: 1px solid #ddd; }';
    echo '.header-title { font-size: 16pt; font-weight: bold; text-align: center; }';
    echo '.header-info { font-size: 10pt; text-align: center; margin-bottom: 20px; }';
    echo '.summary { background-color: #f8f9fa; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header informasi
    echo '<div class="header-title">Poliklinik Mata Eyethica</div>';
    echo '<div class="header-info">Laporan Data Pasien</div>';
    echo '<div class="header-info">Dicetak pada: ' . date('d-m-Y H:i:s') . '</div>';
    
    // Informasi filter
    if (!empty($search_query) || isset($_GET['jenis_kelamin']) || isset($_GET['tgl_lahir_mulai']) || isset($_GET['tgl_lahir_selesai']) || isset($_GET['tgl_registrasi_mulai']) || isset($_GET['tgl_registrasi_selesai'])) {
        echo '<div style="margin: 15px 0; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #4a90e2;">';
        echo '<strong>Filter yang diterapkan:</strong><br>';
        
        if (!empty($search_query)) echo '- Pencarian: ' . htmlspecialchars($search_query) . '<br>';
        if (isset($_GET['jenis_kelamin']) && !empty($_GET['jenis_kelamin'])) {
            $jk = $_GET['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
            echo '- Jenis Kelamin: ' . $jk . '<br>';
        }
        if (isset($_GET['tgl_lahir_mulai']) || isset($_GET['tgl_lahir_selesai'])) {
            echo '- Rentang Tanggal Lahir: ';
            if (isset($_GET['tgl_lahir_mulai'])) echo date('d-m-Y', strtotime($_GET['tgl_lahir_mulai']));
            if (isset($_GET['tgl_lahir_mulai']) && isset($_GET['tgl_lahir_selesai'])) echo ' s/d ';
            if (isset($_GET['tgl_lahir_selesai'])) echo date('d-m-Y', strtotime($_GET['tgl_lahir_selesai']));
            echo '<br>';
        }
        if (isset($_GET['tgl_registrasi_mulai']) || isset($_GET['tgl_registrasi_selesai'])) {
            echo '- Rentang Tanggal Registrasi: ';
            if (isset($_GET['tgl_registrasi_mulai'])) echo date('d-m-Y', strtotime($_GET['tgl_registrasi_mulai']));
            if (isset($_GET['tgl_registrasi_mulai']) && isset($_GET['tgl_registrasi_selesai'])) echo ' s/d ';
            if (isset($_GET['tgl_registrasi_selesai'])) echo date('d-m-Y', strtotime($_GET['tgl_registrasi_selesai']));
            echo '<br>';
        }
        
        // Informasi sorting
        $column_names = [
            'id_pasien' => 'ID Pasien',
            'nama_pasien' => 'Nama Pasien',
            'tgl_lahir_pasien' => 'Tanggal Lahir',
            'tanggal_registrasi_pasien' => 'Tanggal Registrasi'
        ];
        $sort_text = $column_names[$sort_column] ?? 'ID Pasien';
        $sort_text .= $sort_order == 'asc' ? ' (A-Z / Terlama)' : ' (Z-A / Terbaru)';
        echo '- Diurutkan berdasarkan: ' . $sort_text . '<br>';
        
        echo '</div>';
    }
    
    // Tabel data
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    
    // Header tabel
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>ID Pasien</th>';
    echo '<th>NIK</th>';
    echo '<th>Nama Pasien</th>';
    echo '<th>Jenis Kelamin</th>';
    echo '<th>Tanggal Lahir</th>';
    echo '<th>Alamat</th>';
    echo '<th>Telepon</th>';
    echo '<th>Tanggal Registrasi</th>';
    echo '</tr>';
    
    $no = 1;
    $laki_laki = 0;
    $perempuan = 0;
    
    if (count($all_pasien) > 0) {
        foreach ($all_pasien as $pasien) {
            // Hitung statistik
            if (($pasien['jenis_kelamin_pasien'] ?? '') == 'L') $laki_laki++;
            if (($pasien['jenis_kelamin_pasien'] ?? '') == 'P') $perempuan++;
            
            // Format NIK
            $nik = $pasien['nik'] ?? '-';
            
            // Format jenis kelamin
            $jenis_kelamin = $pasien['jenis_kelamin_pasien'] ?? '';
            $jenis_kelamin_text = $jenis_kelamin == 'L' ? 'Laki-laki' : ($jenis_kelamin == 'P' ? 'Perempuan' : '-');
            
            // Format tanggal lahir
            $tgl_lahir_formatted = !empty($pasien['tgl_lahir_pasien']) ? 
                date('d-m-Y', strtotime($pasien['tgl_lahir_pasien'])) : '-';
            
            // Format tanggal registrasi
            $tgl_registrasi_formatted = !empty($pasien['tanggal_registrasi_pasien']) ? 
                date('d-m-Y H:i:s', strtotime($pasien['tanggal_registrasi_pasien'])) : '-';
            
            echo '<tr>';
            echo '<td align="center">' . $no++ . '</td>';
            echo '<td align="center">' . htmlspecialchars($pasien['id_pasien'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($nik) . '</td>';
            echo '<td>' . htmlspecialchars($pasien['nama_pasien'] ?? '-') . '</td>';
            echo '<td align="center">' . htmlspecialchars($jenis_kelamin_text) . '</td>';
            echo '<td align="center">' . htmlspecialchars($tgl_lahir_formatted) . '</td>';
            echo '<td>' . htmlspecialchars($pasien['alamat_pasien'] ?? '-') . '</td>';
            echo '<td align="center">' . htmlspecialchars($pasien['telepon_pasien'] ?? '-') . '</td>';
            echo '<td align="center">' . htmlspecialchars($tgl_registrasi_formatted) . '</td>';
            echo '</tr>';
        }
        
        // Total
        echo '<tr class="summary">';
        echo '<td colspan="9" align="center"><strong>TOTAL PASIEN: ' . count($all_pasien) . ' orang</strong></td>';
        echo '</tr>';
        
        // Statistik jenis kelamin
        echo '<tr>';
        echo '<td colspan="9">';
        echo '<strong>Ringkasan:</strong> ';
        echo 'Laki-laki: ' . $laki_laki . ' orang, ';
        echo 'Perempuan: ' . $perempuan . ' orang';
        echo '</td>';
        echo '</tr>';
        
    } else {
        echo '<tr><td colspan="9" align="center">Tidak ada data pasien ditemukan</td></tr>';
    }
    
    echo '</table>';
    
    // Footer
    echo '<div style="margin-top: 20px; font-size: 9pt; color: #777; text-align: center;">';
    echo 'Laporan ini dicetak oleh sistem Poliklinik Mata Eyethica<br>';
    echo 'Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica. All rights reserved.';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    
    exit();
} else {
    // Redirect jika tidak ada parameter export_excel
    header('Location: ../../pages/laporan-data-pasien.php');
    exit();
}
?>