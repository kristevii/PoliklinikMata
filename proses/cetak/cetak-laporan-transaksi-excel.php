<?php
session_start();
require_once "../../koneksi.php";

$db = new database();

// PROSES EXPORT EXCEL
if (isset($_GET['export_excel'])) {
    // Konfigurasi filter sama seperti di halaman
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Ambil semua data transaksi
    $all_transaksi = $db->tampil_data_transaksi();
    $all_pasien = $db->tampil_data_pasien();
    
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
    
    // Set headers untuk download Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_transaksi_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Output Excel
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Laporan Transaksi - ' . date('d-m-Y') . '</title>';
    echo '</head>';
    echo '<body>';
    
    // Header laporan
    echo '<h2 align="center">Poliklinik Mata Eyethica</h2>';
    echo '<h3 align="center">Laporan Data Transaksi</h3>';
    echo '<p align="center">Dicetak pada: ' . date('d-m-Y H:i:s') . '</p>';
    echo '<hr>';
    
    // Info filter
    $filter_info = [];
    if (!empty($search_query)) $filter_info[] = "Pencarian: $search_query";
    if (isset($_GET['status']) && !empty($_GET['status'])) $filter_info[] = "Status: " . $_GET['status'];
    if (isset($_GET['metode']) && !empty($_GET['metode'])) $filter_info[] = "Metode: " . $_GET['metode'];
    if (isset($_GET['tanggal_mulai'])) $filter_info[] = "Dari: " . $_GET['tanggal_mulai'];
    if (isset($_GET['tanggal_selesai'])) $filter_info[] = "Sampai: " . $_GET['tanggal_selesai'];
    
    if (!empty($filter_info)) {
        echo '<p><strong>Filter:</strong> ' . implode(' | ', $filter_info) . '</p>';
    }
    
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr style="background-color: #4a90e2; color: white;">';
    echo '<th>No</th>';
    echo '<th>ID Transaksi</th>';
    echo '<th>ID Rekam Medis</th>';
    echo '<th>Nama Pasien</th>';
    echo '<th>Kode Staff</th>';
    echo '<th>Tanggal Transaksi</th>';
    echo '<th>Metode Bayar</th>';
    echo '<th>Total Biaya</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    
    $no = 1;
    $total_biaya = 0;
    
    if (count($all_transaksi) > 0) {
        foreach ($all_transaksi as $transaksi) {
            // Cari nama pasien dari id_rekam
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
            
            // Format tanggal
            $tanggal_formatted = !empty($transaksi['tanggal_transaksi']) ? 
                date('d-m-Y H:i:s', strtotime($transaksi['tanggal_transaksi'])) : '-';
            
            // Format biaya
            $biaya = $transaksi['grand_total'] ?? 0;
            $biaya_formatted = 'Rp ' . number_format($biaya, 0, ',', '.');
            $total_biaya += $biaya;
            
            echo '<tr>';
            echo '<td align="center">' . $no++ . '</td>';
            echo '<td>' . htmlspecialchars($transaksi['id_transaksi'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($transaksi['id_rekam'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($nama_pasien) . '</td>';
            echo '<td>' . htmlspecialchars($transaksi['kode_staff'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($tanggal_formatted) . '</td>';
            echo '<td>' . htmlspecialchars($transaksi['metode_pembayaran'] ?? '-') . '</td>';
            echo '<td align="right">' . htmlspecialchars($biaya_formatted) . '</td>';
            echo '<td>' . htmlspecialchars($transaksi['status_pembayaran'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        // Total
        echo '<tr style="background-color: #f1f8ff; font-weight: bold;">';
        echo '<td colspan="7" align="right">TOTAL BIAYA:</td>';
        echo '<td align="right">Rp ' . number_format($total_biaya, 0, ',', '.') . '</td>';
        echo '<td></td>';
        echo '</tr>';
    } else {
        echo '<tr><td colspan="9" align="center">Tidak ada data transaksi</td></tr>';
    }
    
    echo '</table>';
    echo '<p align="center" style="margin-top: 20px;">Copyright © ' . date('Y') . ' Sistem Informasi Poliklinik Mata Eyethica</p>';
    echo '</body>';
    echo '</html>';
    exit();
} else {
    header('Location: ../../laporan-transaksi.php');
    exit();
}
?>