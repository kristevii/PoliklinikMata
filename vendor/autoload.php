<?php
// autoload.php - Autoloader untuk dompdf dan fallback (TANPA COMPOSER)

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Path ke vendor folder
define('VENDOR_PATH', __DIR__ . '/vendor');

// ========== AUTO LOAD DOMPDF ==========
if (!class_exists('Dompdf\Dompdf')) {
    // Coba beberapa lokasi dompdf
    $dompdfPaths = [
        VENDOR_PATH . '/dompdf/dompdf/src/Dompdf.php',
        VENDOR_PATH . '/dompdf/dompdf/lib/html5lib/Parser.php',
        VENDOR_PATH . '/dompdf/autoload.inc.php',
        VENDOR_PATH . '/dompdf/dompdf/autoload.inc.php',
        __DIR__ . '/dompdf/autoload.inc.php',
        __DIR__ . '/dompdf/dompdf/autoload.inc.php'
    ];
    
    $dompdfLoaded = false;
    
    foreach ($dompdfPaths as $dompdfPath) {
        if (file_exists($dompdfPath)) {
            require_once $dompdfPath;
            $dompdfLoaded = true;
            
            // Jika file autoload.inc.php, tidak perlu load lagi
            if (basename($dompdfPath) === 'autoload.inc.php') {
                break;
            }
            
            // Coba load file penting lainnya
            $dompdfDir = dirname($dompdfPath);
            $importantFiles = [
                '/src/Options.php',
                '/src/Canvas.php',
                '/src/CanvasFactory.php',
                '/src/Adapter/CPDF.php',
                '/src/FrameDecorator/AbstractFrameDecorator.php',
                '/src/Renderer.php',
                '/src/Renderer/Block.php'
            ];
            
            foreach ($importantFiles as $file) {
                $fullPath = $dompdfDir . $file;
                if (file_exists($fullPath)) {
                    require_once $fullPath;
                }
            }
            break;
        }
    }
    
    // Jika dompdf tidak ditemukan, buat fallback class
    if (!$dompdfLoaded) {
        // Buat fallback class untuk dompdf
        if (!class_exists('Dompdf\Dompdf')) {
            class DompdfFallback {
                private $paper = 'A4';
                private $orientation = 'portrait';
                private $html = '';
                
                public function __construct($options = null) {
                    // Constructor kosong
                }
                
                public function setPaper($size, $orientation = 'portrait') {
                    $this->paper = $size;
                    $this->orientation = $orientation;
                    return $this;
                }
                
                public function loadHtml($html) {
                    $this->html = $html;
                    return $this;
                }
                
                public function render() {
                    // Method kosong
                    return $this;
                }
                
                public function stream($filename = 'document.pdf', $options = []) {
                    // Untuk mode download, redirect ke halaman error
                    $attachment = $options['Attachment'] ?? true;
                    
                    if ($attachment) {
                        header('Content-Type: text/html; charset=utf-8');
                        echo '<!DOCTYPE html>
                        <html lang="id">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Error - PDF Export</title>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    line-height: 1.6;
                                    color: #333;
                                    max-width: 800px;
                                    margin: 0 auto;
                                    padding: 20px;
                                }
                                .error-container {
                                    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
                                    color: white;
                                    padding: 30px;
                                    border-radius: 10px;
                                    margin-bottom: 20px;
                                    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                                }
                                .error-container h2 {
                                    margin-top: 0;
                                    font-size: 24px;
                                }
                                .solution-container {
                                    background: #e8f4fd;
                                    padding: 25px;
                                    border-radius: 10px;
                                    border-left: 5px solid #4a90e2;
                                    margin-bottom: 20px;
                                }
                                .solution-container h3 {
                                    color: #2c3e50;
                                    margin-top: 0;
                                }
                                .code-block {
                                    background: #2c3e50;
                                    color: #ecf0f1;
                                    padding: 15px;
                                    border-radius: 5px;
                                    font-family: Consolas, monospace;
                                    margin: 10px 0;
                                    overflow-x: auto;
                                }
                                .btn {
                                    display: inline-block;
                                    padding: 12px 24px;
                                    background: #4a90e2;
                                    color: white;
                                    text-decoration: none;
                                    border-radius: 5px;
                                    font-weight: bold;
                                    transition: all 0.3s ease;
                                    border: none;
                                    cursor: pointer;
                                    margin: 5px;
                                }
                                .btn:hover {
                                    background: #357ae8;
                                    transform: translateY(-2px);
                                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                                }
                                .btn-success {
                                    background: #28a745;
                                }
                                .btn-success:hover {
                                    background: #218838;
                                }
                                .btn-group {
                                    margin-top: 20px;
                                    text-align: center;
                                }
                                ol {
                                    padding-left: 20px;
                                }
                                li {
                                    margin-bottom: 10px;
                                }
                                .note {
                                    background: #fff3cd;
                                    border-left: 4px solid #ffc107;
                                    padding: 15px;
                                    border-radius: 5px;
                                    margin-top: 20px;
                                }
                            </style>
                        </head>
                        <body>
                            <div class="error-container">
                                <h2>⚠️ Library DOMPDF Tidak Ditemukan!</h2>
                                <p>Sistem tidak dapat menemukan library DOMPDF yang diperlukan untuk membuat file PDF.</p>
                            </div>
                            
                            <div class="solution-container">
                                <h3>Solusi 1: Install via Composer (Rekomendasi)</h3>
                                <div class="code-block">
                                    cd C:\\laragon\\www\\eyethicaklinik<br>
                                    composer require dompdf/dompdf
                                </div>
                                
                                <h3>Solusi 2: Download Manual</h3>
                                <ol>
                                    <li>Download dompdf dari: <a href="https://github.com/dompdf/dompdf/releases" target="_blank">https://github.com/dompdf/dompdf/releases</a></li>
                                    <li>Pilih versi terbaru (contoh: dompdf-2.0.3.zip)</li>
                                    <li>Extract ke folder: <strong>C:\\laragon\\www\\eyethicaklinik\\vendor\\dompdf\\</strong></li>
                                    <li>Pastikan struktur folder: vendor/dompdf/dompdf/autoload.inc.php</li>
                                </ol>
                                
                                <h3>Solusi 3: Gunakan Fitur Lain</h3>
                                <p>Anda dapat menggunakan fitur <strong>Excel</strong> atau <strong>Cetak</strong> sebagai alternatif.</p>
                            </div>
                            
                            <div class="btn-group">
                                <button onclick="window.history.back()" class="btn">← Kembali</button>
                                <button onclick="location.reload()" class="btn">🔄 Refresh Halaman</button>
                                <a href="?cetak=1" class="btn btn-success" target="_blank">🖨️ Gunakan Cetak</a>
                            </div>
                            
                            <div class="note">
                                <strong>Catatan:</strong> Setelah install, refresh halaman ini atau kembali ke laporan lalu klik tombol PDF lagi.
                            </div>
                            
                            <script>
                                // Auto close setelah 30 detik
                                setTimeout(function() {
                                    if (window.opener) {
                                        window.close();
                                    }
                                }, 30000);
                            </script>
                        </body>
                        </html>';
                        exit();
                    }
                }
            }
            
            // Alias class
            class_alias('DompdfFallback', 'Dompdf\Dompdf');
            
            // Buat class Options jika belum ada
            if (!class_exists('Dompdf\Options')) {
                class OptionsFallback {
                    private $options = [];
                    
                    public function __construct() {
                        $this->setDefaultOptions();
                    }
                    
                    private function setDefaultOptions() {
                        $this->options = [
                            'defaultFont' => 'sans-serif',
                            'isRemoteEnabled' => true,
                            'isHtml5ParserEnabled' => true,
                            'isPhpEnabled' => false,
                            'isJavascriptEnabled' => false
                        ];
                    }
                    
                    public function set($key, $value) {
                        $this->options[$key] = $value;
                        return $this;
                    }
                    
                    public function get($key) {
                        return $this->options[$key] ?? null;
                    }
                }
                class_alias('OptionsFallback', 'Dompdf\Options');
            }
        }
    }
}

// ========== AUTO LOAD PHP SPREADSHEET ==========
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // Cari file autoload PHPOffice
    $phpOfficePaths = [
        VENDOR_PATH . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
        VENDOR_PATH . '/phpoffice/phpspreadsheet/src/Spreadsheet.php',
        __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php'
    ];
    
    $phpOfficeLoaded = false;
    foreach ($phpOfficePaths as $phpOfficePath) {
        if (file_exists($phpOfficePath)) {
            require_once $phpOfficePath;
            
            // Juga load class-class penting lainnya
            $classDir = dirname($phpOfficePath) . '/';
            $importantClasses = [
                'Style/Color.php',
                'Style/Alignment.php',
                'Style/Fill.php',
                'Style/Border.php',
                'Cell/Coordinate.php',
                'Writer/Xlsx.php'
            ];
            
            foreach ($importantClasses as $classFile) {
                $fullPath = $classDir . $classFile;
                if (file_exists($fullPath)) {
                    require_once $fullPath;
                }
            }
            
            $phpOfficeLoaded = true;
            break;
        }
    }
    
    // Jika PHPOffice tidak ditemukan, buat fallback untuk Excel
    if (!$phpOfficeLoaded) {
        // Buat fallback class untuk Excel export
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            class SpreadsheetFallback {
                public function generateExcelFallback($data, $filename) {
                    // Generate simple HTML table for Excel
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
                    
                    echo '<html>';
                    echo '<head>';
                    echo '<meta charset="UTF-8">';
                    echo '<style>';
                    echo 'table { border-collapse: collapse; width: 100%; }';
                    echo 'th { background-color: #4a90e2; color: white; padding: 10px; border: 1px solid #357ae8; }';
                    echo 'td { padding: 8px; border: 1px solid #ddd; }';
                    echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
                    echo '</style>';
                    echo '</head>';
                    echo '<body>';
                    echo '<table>';
                    
                    // Header
                    echo '<tr>';
                    echo '<th>No</th>';
                    echo '<th>ID Transaksi</th>';
                    echo '<th>ID Rekam</th>';
                    echo '<th>ID Kontrol</th>';
                    echo '<th>ID Pasien</th>';
                    echo '<th>Nama Pasien</th>';
                    echo '<th>Kode Staff</th>';
                    echo '<th>Tanggal Transaksi</th>';
                    echo '<th>Metode Bayar</th>';
                    echo '<th>Total Biaya</th>';
                    echo '<th>Status</th>';
                    echo '</tr>';
                    
                    // Data
                    if (is_array($data) && count($data) > 0) {
                        $no = 1;
                        foreach ($data as $row) {
                            echo '<tr>';
                            echo '<td>' . $no++ . '</td>';
                            echo '<td>' . ($row['id_transaksi'] ?? '') . '</td>';
                            echo '<td>' . ($row['id_rekam'] ?? '-') . '</td>';
                            echo '<td>' . ($row['id_kontrol'] ?? '-') . '</td>';
                            echo '<td>' . ($row['id_pasien'] ?? '') . '</td>';
                            echo '<td>' . ($row['nama_pasien'] ?? '') . '</td>';
                            echo '<td>' . ($row['kode_staff'] ?? '-') . '</td>';
                            
                            // Format tanggal
                            $tanggal = !empty($row['tanggal_transaksi']) ? 
                                date('d-m-Y H:i', strtotime($row['tanggal_transaksi'])) : '-';
                            echo '<td>' . $tanggal . '</td>';
                            
                            echo '<td>' . ($row['metode_pembayaran'] ?? '-') . '</td>';
                            
                            // Format biaya
                            $biaya = !empty($row['biaya']) ? 
                                'Rp ' . number_format($row['biaya'], 0, ',', '.') : '-';
                            echo '<td align="right">' . $biaya . '</td>';
                            
                            echo '<td>' . ($row['status_pembayaran'] ?? '') . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="11" align="center">Tidak ada data</td></tr>';
                    }
                    
                    echo '</table>';
                    echo '</body>';
                    echo '</html>';
                    exit();
                }
            }
            class_alias('SpreadsheetFallback', 'PhpOffice\PhpSpreadsheet\Spreadsheet');
        }
    }
}

// ========== HELPER FUNCTIONS ==========

/**
 * Cek status library
 */
function checkLibraryStatus() {
    $status = [
        'dompdf' => class_exists('Dompdf\Dompdf') ? 'OK' : 'NOT FOUND',
        'phpspreadsheet' => class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet') ? 'OK' : 'NOT FOUND'
    ];
    
    // Cek apakah fallback atau asli
    if ($status['dompdf'] === 'OK') {
        $reflection = new ReflectionClass('Dompdf\Dompdf');
        $status['dompdf'] .= ' (' . (strpos($reflection->getName(), 'Fallback') !== false ? 'FALLBACK' : 'ASLI') . ')';
    }
    
    if ($status['phpspreadsheet'] === 'OK') {
        $reflection = new ReflectionClass('PhpOffice\PhpSpreadsheet\Spreadsheet');
        $status['phpspreadsheet'] .= ' (' . (strpos($reflection->getName(), 'Fallback') !== false ? 'FALLBACK' : 'ASLI') . ')';
    }
    
    return $status;
}

/**
 * Get library path information
 */
function getLibraryInfo() {
    $info = [];
    
    // DOMPDF paths
    $dompdfPaths = [
        'vendor/dompdf/dompdf/src/Dompdf.php',
        'vendor/dompdf/dompdf/autoload.inc.php',
        'vendor/dompdf/autoload.inc.php',
        'dompdf/autoload.inc.php'
    ];
    
    foreach ($dompdfPaths as $path) {
        $fullPath = __DIR__ . '/' . $path;
        $info['dompdf_paths'][$path] = file_exists($fullPath) ? 'EXISTS' : 'NOT FOUND';
    }
    
    // PHP Spreadsheet paths
    $phpspreadsheetPaths = [
        'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
        'vendor/phpoffice/phpspreadsheet/src/Spreadsheet.php'
    ];
    
    foreach ($phpspreadsheetPaths as $path) {
        $fullPath = __DIR__ . '/' . $path;
        $info['phpspreadsheet_paths'][$path] = file_exists($fullPath) ? 'EXISTS' : 'NOT FOUND';
    }
    
    return $info;
}

// ========== DEBUG MODE ==========
if (isset($_GET['debug_libs'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Library Status Debug</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .status-ok { color: #28a745; font-weight: bold; }
            .status-error { color: #dc3545; font-weight: bold; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f8f9fa; }
            .container { max-width: 1000px; margin: 0 auto; }
            .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Library Status Debug</h1>
            
            <div class="card">
                <h2>Library Status</h2>';
    
    $status = checkLibraryStatus();
    echo '<table>';
    foreach ($status as $lib => $stat) {
        $class = strpos($stat, 'OK') !== false ? 'status-ok' : 'status-error';
        echo '<tr>';
        echo '<td><strong>' . strtoupper($lib) . '</strong></td>';
        echo '<td class="' . $class . '">' . $stat . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '</div>
    
            <div class="card">
                <h2>File Paths</h2>';
    
    $info = getLibraryInfo();
    foreach ($info as $section => $paths) {
        echo '<h3>' . ucfirst($section) . '</h3>';
        echo '<table>';
        foreach ($paths as $path => $exists) {
            $class = $exists === 'EXISTS' ? 'status-ok' : 'status-error';
            echo '<tr>';
            echo '<td>' . $path . '</td>';
            echo '<td class="' . $class . '">' . $exists . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '</div>
            
            <div class="card">
                <h2>Quick Actions</h2>
                <p>
                    <a href="?debug_libs=1" class="btn">🔄 Refresh</a>
                    <button onclick="window.history.back()" class="btn">← Kembali</button>
                </p>
                <p><small>Hapus parameter <code>?debug_libs=1</code> dari URL untuk kembali normal.</small></p>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

// ========== AUTO LOAD CUSTOM CLASSES ==========
spl_autoload_register(function ($className) {
    $baseDir = __DIR__ . '/';
    
    // Convert namespace to path
    $classFile = $baseDir . str_replace('\\', '/', $className) . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    // Coba di folder classes
    $classFile = $baseDir . 'classes/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    // Coba di folder includes
    $classFile = $baseDir . 'includes/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    return false;
});

// Log bahwa autoloader telah dimuat
if (!defined('AUTOLOAD_LOADED')) {
    define('AUTOLOAD_LOADED', true);
}
?>