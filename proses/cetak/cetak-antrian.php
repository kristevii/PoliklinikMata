<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../../koneksi.php";
$db = new database();

// FUNGSI UTAMA
function formatTanggal($tanggal, $bahasa = 'id') {
    if (empty($tanggal)) return '-';
    
    try {
        $formatter = new IntlDateFormatter(
            $bahasa,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'Asia/Jakarta',
            IntlDateFormatter::GREGORIAN
        );
        return $formatter->format(new DateTime($tanggal));
    } catch (Exception $e) {
        return date('d-m-Y', strtotime($tanggal));
    }
}

function formatJam($jam) {
    if (empty($jam)) return '-';
    return date('H:i', strtotime($jam));
}

function formatTanggalIndonesia($tanggal) {
    if (empty($tanggal)) return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $tgl = date('d', $timestamp);
    $bln = (int)date('m', $timestamp);
    $thn = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$tgl {$bulan[$bln]} $thn, $jam WIB";
}

// Fungsi untuk mendapatkan data pasien berdasarkan ID
function getPasienById($db, $id_pasien) {
    try {
        $pasien = $db->get_pasien_by_id($id_pasien);
        if ($pasien) {
            return $pasien;
        }
    } catch (Exception $e) {
        error_log("Error getPasienById: " . $e->getMessage());
    }
    
    return [
        'nama_pasien' => 'Tidak Diketahui',
        'no_rm' => '-',
        'tgl_lahir' => '-',
        'jenis_kelamin' => '-'
    ];
}

// Fungsi untuk mendapatkan data dokter berdasarkan kode
function getDokterByKode($db, $kode_dokter) {
    if (empty($kode_dokter)) {
        return [
            'nama_dokter' => '-',
            'ruang' => 'Umum',
            'spesialis' => '-'
        ];
    }
    
    $all_dokter = $db->tampil_data_dokter();
    
    if (!is_array($all_dokter)) {
        return ['nama_dokter' => '-', 'ruang' => 'Umum', 'spesialis' => '-'];
    }
    
    foreach ($all_dokter as $dokter) {
        if ($dokter['kode_dokter'] == $kode_dokter) {
            return $dokter;
        }
    }
    
    return ['nama_dokter' => '-', 'ruang' => 'Umum', 'spesialis' => '-'];
}

// PROSES CETAK ANTRIAN
if (isset($_GET['id_antrian'])) {
    $id_antrian = $_GET['id_antrian'];
    
    // Ambil data antrian
    $antrian = $db->get_antrian_by_id($id_antrian);
    if (!$antrian) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Data antrian tidak ditemukan';
        header("Location: ../../dataantrian.php");
        exit();
    }
    
    // Ambil data pasien
    $id_pasien = $antrian['id_pasien'];
    $pasien = getPasienById($db, $id_pasien);
    
    // Ambil data dokter berdasarkan kode dokter
    $kode_dokter = $antrian['kode_dokter'] ?? '';
    $dokter = getDokterByKode($db, $kode_dokter);
    
    // Ambil ruang dari data dokter
    $ruang = !empty($dokter['ruang']) ? $dokter['ruang'] : 'Umum';
    
    // Format tanggal
    $waktu_daftar_formatted = !empty($antrian['waktu_daftar']) ? 
        formatTanggalIndonesia($antrian['waktu_daftar']) : '-';
    
    // Tentukan jenis antrian
    $jenis_antrian = $antrian['jenis_antrian'] ?? 'Baru';
    $is_kontrol = ($jenis_antrian == 'Kontrol');
    
    // Format tanggal kontrol jika ada
    $tanggal_kontrol_formatted = '';
    if ($is_kontrol && !empty($antrian['tanggal_antrian'])) {
        $tanggal_kontrol_formatted = formatTanggalIndonesia($antrian['tanggal_antrian']);
    }
    
    // Tentukan warna berdasarkan jenis antrian
    $gradient_start = $is_kontrol ? '#11998e' : '#667eea';
    $gradient_end = $is_kontrol ? '#38ef7d' : '#764ba2';
    $badge_color = $is_kontrol ? '#11998e' : '#667eea';
    $badge_text = $is_kontrol ? 'Jadwal Kontrol' : 'Pendaftaran Baru';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tiket Antrian - <?= htmlspecialchars($antrian['nomor_antrian']) ?> | Poliklinik Mata Eyethica</title>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @media print {
            .no-print { 
                display: none !important; 
            }
            @page {
                size: 80mm auto;
                margin: 0mm;
            }
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .ticket {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
        }
        
        body { 
            font-family: 'Segoe UI', 'Arial', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Ticket Container */
        .ticket {
            width: 350px;
            max-width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        /* Ticket Header with Gradient */
        .ticket-header {
            background: #000;
            padding: 25px 20px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .clinic-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .clinic-name {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        
        .clinic-sub {
            font-size: 11px;
            opacity: 0.9;
        }
        
        /* Ticket Body */
        .ticket-body {
            padding: 20px;
        }
        
        /* Queue Number Section */
        .queue-section {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #e0e0e0;
        }
        
        .queue-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .queue-number {
            font-size: 48px;
            font-weight: bold;
            -webkit-background-clip: text;
            background-clip: text;
            letter-spacing: 3px;
            margin: 5px 0;
        }
        
        .queue-badge {
            display: inline-block;
            padding: 5px 15px;
            background:  #000;
            color: white;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 8px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 12px 8px;
            margin-bottom: 20px;
        }
        
        .info-label {
            font-size: 11px;
            color: #888;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 13px;
            color: #333;
            font-weight: 500;
            word-break: break-word;
        }
        
        .info-value strong {
            color: <?= $gradient_start ?>;
        }
        
        /* Doctor Card */
        .doctor-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .doctor-name {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .doctor-room {
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Control Date Section */
        .control-section {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .control-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .control-date {
            font-size: 13px;
            font-weight: bold;
        }
        
        /* Footer */
        .ticket-footer {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        
        .footer-text {
            font-size: 9px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .barcode {
            font-family: 'Courier New', monospace;
            font-size: 28px;
            letter-spacing: 2px;
            color: #333;
            margin-top: 8px;
        }
        
        /* Print Controls */
        .print-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .print-controls button {
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-close {
            background: #6c757d;
            color: white;
        }
        
        .btn-close:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e0e0e0, transparent);
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ Cetak Tiket
        </button>
        <button class="btn-close" onclick="window.close()">
            ✖️ Tutup
        </button>
    </div>
    
    <div class="ticket">
        <!-- Header -->
        <div class="ticket-header">
            <div class="clinic-name">EYETHICA</div>
            <div class="clinic-sub">Poliklinik Mata</div>
            <div class="clinic-sub">Menteng Dalam, Jakarta Timur</div>
        </div>
        
        <!-- Body -->
        <div class="ticket-body">
            <!-- Queue Number -->
            <div class="queue-section">
                <div class="queue-label">Nomor Antrian Anda</div>
                <div class="queue-number"><?= htmlspecialchars($antrian['nomor_antrian']) ?></div>
                <div class="queue-badge"><?= $badge_text ?></div>
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-label">Nama Pasien</div>
                <div class="info-value"><?= htmlspecialchars($pasien['nama_pasien']) ?></div>
                
                <?php if (!empty($pasien['no_rm']) && $pasien['no_rm'] != '-'): ?>
                <div class="info-label">No. RM</div>
                <div class="info-value"><?= htmlspecialchars($pasien['no_rm']) ?></div>
                <?php endif; ?>
                
                <div class="info-label">Waktu Daftar</div>
                <div class="info-value"><?= $waktu_daftar_formatted ?></div>
                
                <?php if (!empty($kode_dokter)): ?>
                <div class="info-label">Kode Dokter</div>
                <div class="info-value"><?= htmlspecialchars($kode_dokter) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Doctor Info -->
            <div class="doctor-card">
                <div class="doctor-name"><?= htmlspecialchars($dokter['nama_dokter']) ?></div>
                <div class="doctor-room"><?= htmlspecialchars($ruang) ?></div>
                <?php if (!empty($dokter['spesialis']) && $dokter['spesialis'] != '-'): ?>
                <div style="font-size: 10px; color: #888; margin-top: 5px;"><?= htmlspecialchars($dokter['spesialis']) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Control Date (only for Kontrol type) -->
            <?php if ($is_kontrol && !empty($tanggal_kontrol_formatted)): ?>
            <div class="control-section">
                <div class="control-title">Jadwal Kontrol</div>
                <div class="control-date"><?= $tanggal_kontrol_formatted ?></div>
                <div style="font-size: 10px; margin-top: 5px;">
                    Harap datang sesuai jadwal kontrol
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="ticket-footer">
            <div class="footer-text">Tiket ini berlaku pada tanggal pendaftaran</div>
            <div class="footer-text">Terima kasih telah mempercayakan kesehatan mata Anda kepada kami</div>
            <div class="footer-text" style="margin-top: 8px;">Poliklinik Mata Eyethica - Melihat Masa Depan Lebih Jelas</div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.focus();
            setTimeout(function() {
                window.print();
            }, 300);
        }
        
        document.querySelector('.btn-print')?.addEventListener('click', function() {
            window.print();
        });
        
        document.querySelector('.btn-close')?.addEventListener('click', function() {
            window.close();
        });
        
        // Auto close after print (optional)
        window.onafterprint = function() {
            // Uncomment if you want auto close after print
            // setTimeout(function() { window.close(); }, 1000);
        };
    </script>
</body>
</html>
<?php
    exit();
}

// Jika tidak ada parameter yang valid, redirect ke halaman utama
header("Location: ../../dataantrian.php");
exit();
?>