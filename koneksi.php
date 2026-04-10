<?php
class database {
    var $host = "localhost";
    var $username = "root";
    var $password = "";
    var $db = "poliklinik";
    public $koneksi;

    function __construct(){
        // Cek koneksi ke MySQL server
        $this->koneksi = mysqli_connect($this->host, $this->username, $this->password);

        if (mysqli_connect_errno()) {
            die("Koneksi database GAGAL: " . mysqli_connect_error());
        }

        // Cek pemilihan database
        $cekdb = mysqli_select_db($this->koneksi, $this->db);
        if (!$cekdb) {
            die("Database '{$this->db}' tidak ditemukan atau gagal dipilih.");
        }
        // PERBAIKAN TAMBAHAN: Set timezone MySQL ke Asia/Jakarta
        mysqli_query($this->koneksi, "SET time_zone = '+07:00'");
    }
    
    // === TRANSACTION WRAPPER UNTUK MYSQLI ===
    function beginTransaction() {
        mysqli_begin_transaction($this->koneksi);
    }
    function commit() {
        mysqli_commit($this->koneksi);
    }
    function rollback() {
        mysqli_rollback($this->koneksi);
    }

    // Metode untuk login dengan password plain text
    function login($username, $password) {
        // Lindungi input dari SQL Injection
        $username = mysqli_real_escape_string($this->koneksi, $username);
        $password = mysqli_real_escape_string($this->koneksi, $password);

        $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
        $result = mysqli_query($this->koneksi, $query); // Gunakan $this->koneksi

        if (!$result) {
            error_log("Login Query Error: " . mysqli_error($this->koneksi) . " Query: " . $query);
            return false;
        }

        if (mysqli_num_rows($result) == 1) {
            return mysqli_fetch_assoc($result); // Login berhasil, kembalikan data user
        }
        return false; // User tidak ditemukan atau password salah
    }

    // --- Function Jumlah Data di Dashboard ---
    function jumlahdata_users(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from users");
        if (!$data) {
            error_log("Query error jumlahdata_users: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_dokter(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_dokter");
        if (!$data) {
            error_log("Query error jumlahdata_dokter: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_staff(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_staff");
        if (!$data) {
            error_log("Query error jumlahdata_staff: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_pasien(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_pasien");
        if (!$data) {
            error_log("Query error jumlahdata_pasien: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_antrian(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_antrian");
        if (!$data) {
            error_log("Query error jumlahdata_antrian: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_rekam(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_rekam_medis");
        if (!$data) {
            error_log("Query error jumlahdata_rekam: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_kontrol(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_kontrol");
        if (!$data) {
            error_log("Query error jumlahdata_kontrol: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_transaksi(){
        $data = mysqli_query($this->koneksi, "SELECT COUNT(*) as total from data_transaksi");
        if (!$data) {
            error_log("Query error jumlahdata_transaksi: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }

    // --- Function Tampil Data ---
    function tampil_data_users(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_user, role, nama, username, password, email from users");
        if (!$data) {
            error_log("Query error tampil_data_users: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_array($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_dokter(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_user, kode_dokter, subspesialisasi, foto_dokter, nama_dokter, tanggal_lahir_dokter, jenis_kelamin_dokter, alamat_dokter, email, telepon_dokter, ruang from data_dokter");
        if (!$data) {
            error_log("Query error tampil_data_dokter: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_array($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_staff_medis(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_user, kode_staff, jabatan_staff, foto_staff, nama_staff, jenis_kelamin_staff, tanggal_lahir_staff, alamat_staff, email, telepon_staff from data_staff");
        if (!$data) {
            error_log("Query error tampil_data_staff: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){ // Mengubah ke mysqli_fetch_assoc untuk konsistensi
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_jenis_dokumen(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_dokumen, nama_dokumen from jenis_dokumen");
        if (!$data) {
            error_log("Query error tampil_jenis_dokumen: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){ // Mengubah ke mysqli_fetch_assoc untuk konsistensi
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_dokumen(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_data_dokumen, id_dokumen, kode_dokter, kode_staff, file_dokumen, status from data_dokumen");
        if (!$data) {
            error_log("Query error tampil_data_dokumen: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){ // Mengubah ke mysqli_fetch_assoc untuk konsistensi
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_staff(){
        $hasil = [];
        // Menambahkan `id_bisnis` ke dalam query
        $data = mysqli_query($this->koneksi, "select id_user, kode_staff, jabatan_staff, foto_staff, nama_staff, jenis_kelamin_staff, tanggal_lahir_staff, alamat_staff, email, telepon_staff from data_staff");
        if (!$data) {
            error_log("Query error tampil_data_staff: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){ // Mengubah ke mysqli_fetch_assoc untuk konsistensi
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_pasien(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_pasien, nik, nama_pasien, jenis_kelamin_pasien, tgl_lahir_pasien, alamat_pasien, telepon_pasien, tanggal_registrasi_pasien from data_pasien");
        if (!$data) {
            error_log("Query error tampil_data_pasien: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_antrian(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_antrian, nomor_antrian, id_pasien, kode_dokter, jenis_antrian, status, tanggal_antrian, waktu_daftar, update_at from data_antrian");
        if (!$data) {
            error_log("Query error tampil_data_antrian: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_rekam_medis(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_rekam, id_pasien, kode_dokter, tanggal_periksa, diagnosa, keluhan, catatan, jenis_kunjungan from data_rekam_medis");
        if (!$data) {
            error_log("Query error tampil_data_rekam_medis: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_jadwal_dokter(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_jadwal, kode_dokter, hari, shift, jam_mulai, jam_selesai, status from data_jadwal_dokter");
        if (!$data) {
            error_log("Query error tampil_data_jadwal_dokter: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_alat_medis(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_alat, kode_alat, nama_alat, jenis_alat, lokasi, kondisi, tanggal_beli, status, deskripsi, created_at from data_alat_medis");
        if (!$data) {
            error_log("Query error tampil_data_alat_medis: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_tindakan_medis(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_tindakan_medis, kode_tindakan, nama_tindakan, kategori, tarif, deskripsi, created_at from data_tindakan_medis");
        if (!$data) {
            error_log("Query error tampil_data_tindakan_medis: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_obat(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_obat, kode_obat, nama_obat, jenis_obat, satuan, stok, harga, expired_date, deskripsi, created_at from data_obat");
        if (!$data) {
            error_log("Query error tampil_data_obat: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_pemeriksaan_mata(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_pemeriksaan, id_rekam, visus_od, visus_os, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, tio_od, tio_os, slit_lamp, catatan, created_at, updated_at from data_pemeriksaan_mata");
        if (!$data) {
            error_log("Query error tampil_data_pemeriksaan_mata: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_resep_kacamata(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_resep_kacamata, id_rekam, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, pd, catatan, created_at from data_resep_kacamata");
        if (!$data) {
            error_log("Query error tampil_data_resep_kacamata: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_detail_resep_obat(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_detail_resep_obat, id_resep_obat, id_obat, jumlah, dosis, aturan_pakai, harga, subtotal, created_at from data_detail_resep_obat");
        if (!$data) {
            error_log("Query error tampil_data_detail_resep_obat: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_detail_tindakan_medis(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_detail_tindakanmedis, id_rekam, id_tindakan_medis, qty, harga, subtotal, created_at from data_detail_tindakan_medis");
        if (!$data) {
            error_log("Query error tampil_data_detail_tindakan_medis: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_resep_obat(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_resep_obat, id_rekam, tanggal_resep catatan, created_at from data_resep_obat");
        if (!$data) {
            error_log("Query error tampil_data_resep_obat: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_transaksi(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_transaksi, id_rekam, kode_staff, tanggal_transaksi, grand_total, metode_pembayaran, status_pembayaran, created_at from data_transaksi");
        if (!$data) {
            error_log("Query error tampil_data_transaksi: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_detail_transaksi(){
        $hasil = [];
        $data = mysqli_query($this->koneksi, "select id_detail_transaksi, id_transaksi, jenis_item, nama_item, qty, harga, subtotal, created_at from data_detail_transaksi");
        if (!$data) {
            error_log("Query error tampil_data_detail_transaksi: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_kunjungan() {
        $kunjungan = [];
        
        // Ambil data dari rekam medis
        $query_rekam = "SELECT 
                            tanggal_periksa as tanggal,
                            id_pasien,
                            id_rekam,
                            NULL as id_kontrol,
                            kode_dokter,
                            'Rekam Medis' as jenis_kunjungan
                        FROM data_rekam_medis
                        WHERE tanggal_periksa IS NOT NULL";
        
        $result_rekam = mysqli_query($this->koneksi, $query_rekam);
        if ($result_rekam) {
            while ($row = mysqli_fetch_assoc($result_rekam)) {
                $kunjungan[] = $row;
            }
        }
        
        // Ambil data dari kontrol
        $query_kontrol = "SELECT 
                            tanggal_kontrol as tanggal,
                            id_pasien,
                            NULL as id_rekam,
                            id_kontrol,
                            kode_dokter,
                            'Kontrol' as jenis_kunjungan
                        FROM data_kontrol
                        WHERE tanggal_kontrol IS NOT NULL";
        
        $result_kontrol = mysqli_query($this->koneksi, $query_kontrol);
        if ($result_kontrol) {
            while ($row = mysqli_fetch_assoc($result_kontrol)) {
                $kunjungan[] = $row;
            }
        }
        
        return $kunjungan;
    }
    function tampil_5_transaksi_terakhir_lunas() {
        $hasil = [];
        $query = "SELECT 
                    dt.id_transaksi,
                    dt.id_rekam,
                    dt.kode_staff,
                    dt.tanggal_transaksi,
                    dt.grand_total,
                    dt.metode_pembayaran,
                    dt.status_pembayaran,
                    p.nama_pasien
                FROM data_transaksi dt
                LEFT JOIN data_rekam_medis rm ON dt.id_rekam = rm.id_rekam
                LEFT JOIN data_pasien p ON rm.id_pasien = p.id_pasien
                WHERE dt.status_pembayaran = 'Lunas'
                ORDER BY dt.tanggal_transaksi DESC 
                LIMIT 5";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error tampil_5_transaksi_terakhir_lunas: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)) {
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_5_transaksi_terakhir_belum_bayar() {
        $hasil = [];
        $query = "SELECT 
                    dt.id_transaksi,
                    dt.id_rekam,
                    dt.kode_staff,
                    dt.tanggal_transaksi,
                    dt.grand_total,
                    dt.metode_pembayaran,
                    dt.status_pembayaran,
                    p.nama_pasien
                FROM data_transaksi dt
                LEFT JOIN data_rekam_medis rm ON dt.id_rekam = rm.id_rekam
                LEFT JOIN data_pasien p ON rm.id_pasien = p.id_pasien
                WHERE dt.status_pembayaran = 'Belum Bayar'
                ORDER BY dt.tanggal_transaksi DESC 
                LIMIT 5";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error tampil_5_transaksi_terakhir_belum_bayar: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)) {
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_5_transaksi_terakhir() {
        $hasil = [];
        $query = "SELECT 
                    dt.id_transaksi,
                    dt.id_rekam,
                    dt.kode_staff,
                    dt.tanggal_transaksi,
                    dt.grand_total,
                    dt.metode_pembayaran,
                    dt.status_pembayaran,
                    p.nama_pasien
                FROM data_transaksi dt
                LEFT JOIN data_rekam_medis rm ON dt.id_rekam = rm.id_rekam
                LEFT JOIN data_pasien p ON rm.id_pasien = p.id_pasien
                ORDER BY dt.tanggal_transaksi DESC 
                LIMIT 5";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error tampil_5_transaksi_terakhir: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)) {
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_data_rekam_medis_by_id($id_rekam) {
        $query = "SELECT * FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
        $result = $this->koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    function jumlahtransaksisukses() {
        $query = "SELECT 
                    SUM(dt.biaya) as total_pendapatan
                FROM data_transaksi dt
                WHERE dt.status_pembayaran = 'Lunas'";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error jumlahtransaksisukses: " . mysqli_error($this->koneksi));
            return 0;
        }
        
        $row = mysqli_fetch_assoc($data);
        return $row['total_pendapatan'] ? $row['total_pendapatan'] : 0;
    }
    function jumlahtransaksiterjeda() {
        $query = "SELECT 
                    SUM(dt.biaya) as total_pendapatan
                FROM data_transaksi dt
                WHERE dt.status_pembayaran = 'Belum bayar'";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error jumlahtransaksiterjeda: " . mysqli_error($this->koneksi));
            return 0;
        }
        
        $row = mysqli_fetch_assoc($data);
        return $row['total_pendapatan'] ? $row['total_pendapatan'] : 0;
    }
    function tampil_5_aktivitas_profile_terakhir($id_user){
        $hasil = [];
        
        // Lindungi dari SQL Injection
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        
        $query = "SELECT 
                    ap.id_user, 
                    u.nama as nama_user,
                    ap.jenis,   
                    ap.entitas, 
                    ap.keterangan, 
                    ap.waktu 
                FROM aktivitas_profile ap
                LEFT JOIN users u ON ap.id_user = u.id_user
                WHERE ap.id_user = '$id_user'
                ORDER BY ap.waktu DESC 
                LIMIT 5";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error tampil_5_aktivitas_profile_terakhir: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_5_aktivitas_user_terakhir(){
        $hasil = [];
        $query = "SELECT 
                    au.id_user, 
                    u.nama as nama_user,
                    au.jenis, 
                    au.entitas, 
                    au.keterangan, 
                    au.waktu 
                FROM aktivitas_user au
                LEFT JOIN users u ON au.id_user = u.id_user
                ORDER BY au.waktu DESC 
                LIMIT 5";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error tampil_5_aktivitas_user_terakhir: " . mysqli_error($this->koneksi));
            return [];
        }
        while ($row = mysqli_fetch_assoc($data)){
            $hasil[] = $row;
        }
        return $hasil;
    }
    function tampil_aktivitas_user_berdasarkan_waktu() {
        $hasil = [
            'hari_ini' => [],
            'kemarin' => [],
            'tidak_ada' => false
        ];
        
        // Query untuk aktivitas hari ini
        $query_hari_ini = "SELECT 
                            au.id_user, 
                            u.nama as nama_user,
                            au.jenis, 
                            au.entitas, 
                            au.keterangan, 
                            au.waktu 
                        FROM aktivitas_user au
                        LEFT JOIN users u ON au.id_user = u.id_user
                        WHERE DATE(au.waktu) = CURDATE()
                        ORDER BY au.waktu DESC";
        
        $data_hari_ini = mysqli_query($this->koneksi, $query_hari_ini);
        if ($data_hari_ini) {
            while ($row = mysqli_fetch_assoc($data_hari_ini)) {
                $hasil['hari_ini'][] = $row;
            }
        }
        
        // Query untuk aktivitas kemarin
        $query_kemarin = "SELECT 
                            au.id_user, 
                            u.nama as nama_user,
                            au.jenis, 
                            au.entitas, 
                            au.keterangan, 
                            au.waktu 
                        FROM aktivitas_user au
                        LEFT JOIN users u ON au.id_user = u.id_user
                        WHERE DATE(au.waktu) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                        ORDER BY au.waktu DESC";
        
        $data_kemarin = mysqli_query($this->koneksi, $query_kemarin);
        if ($data_kemarin) {
            while ($row = mysqli_fetch_assoc($data_kemarin)) {
                $hasil['kemarin'][] = $row;
            }
        }
        
        // Cek jika tidak ada aktivitas sama sekali
        if (empty($hasil['hari_ini']) && empty($hasil['kemarin'])) {
            $hasil['tidak_ada'] = true;
        }
        
        return $hasil;
    }
    function jumlahdata_pasien_hari_ini() {
        $query = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE DATE(tanggal_periksa) = CURDATE()";
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error jumlahdata_pasien_hari_ini: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_kunjungan_bulan_ini() {
        $query = "SELECT COUNT(*) as total FROM data_rekam_medis 
                WHERE MONTH(tanggal_periksa) = MONTH(CURDATE()) 
                AND YEAR(tanggal_periksa) = YEAR(CURDATE())";
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error jumlahdata_kunjungan_bulan_ini: " . mysqli_error($this->koneksi));
            return 0;
        }
        $hasil = mysqli_fetch_assoc($data);
        return $hasil['total'];
    }
    function jumlahdata_kunjungan_hari_ini_by_jenis() {
        $hasil = ['baru' => 0, 'kontrol' => 0];
        
        $query = "SELECT jenis_kunjungan, COUNT(*) as total 
                FROM data_rekam_medis 
                WHERE DATE(tanggal_periksa) = CURDATE() 
                GROUP BY jenis_kunjungan";
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error jumlahdata_kunjungan_hari_ini_by_jenis: " . mysqli_error($this->koneksi));
            return $hasil;
        }
        
        while ($row = mysqli_fetch_assoc($data)) {
            if ($row['jenis_kunjungan'] == 'Baru') {
                $hasil['baru'] = $row['total'];
            } elseif ($row['jenis_kunjungan'] == 'Kontrol') {
                $hasil['kontrol'] = $row['total'];
            }
        }
        return $hasil;
    }

    // TAMBAH DATA
    function tambah_data_user($role, $nama, $username, $password, $email) {
        $role = mysqli_real_escape_string($this->koneksi, $role);
        $nama = mysqli_real_escape_string($this->koneksi, $nama);
        $username = mysqli_real_escape_string($this->koneksi, $username);
        $password = mysqli_real_escape_string($this->koneksi, $password);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        $sql = "INSERT INTO users(role, nama, username, password, email) VALUES ('$role', '$nama', '$username', '$password', '$email')";
        error_log("DEBUG tambah_data_user - Query: " . $sql);
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            $last_id = mysqli_insert_id($this->koneksi);
            error_log("DEBUG tambah_data_user - Last insert ID: " . $last_id);
            return $last_id;
        } else {
            error_log("Error tambah_data_user: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_dokter($id_user, $kode_dokter, $subspesialisasi, $foto_dokter, $nama_dokter, $tanggal_lahir_dokter, $jenis_kelamin_dokter, $alamat_dokter, $email, $telepon_dokter ) {
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $subspesialisasi = mysqli_real_escape_string($this->koneksi, $subspesialisasi);
        $foto_dokter = mysqli_real_escape_string($this->koneksi, $foto_dokter);
        $nama_dokter = mysqli_real_escape_string($this->koneksi, $nama_dokter);
        $tanggal_lahir_dokter = mysqli_real_escape_string($this->koneksi, $tanggal_lahir_dokter);
        $jenis_kelamin_dokter = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_dokter);
        $alamat_dokter = mysqli_real_escape_string($this->koneksi, $alamat_dokter);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        $telepon_dokter = mysqli_real_escape_string($this->koneksi, $telepon_dokter);
        $sql = "INSERT INTO data_dokter(id_user, kode_dokter, subspesialisasi, foto_dokter, nama_dokter, tanggal_lahir_dokter, jenis_kelamin_dokter, alamat_dokter, email, telepon_dokter) 
                VALUES ('$id_user', '$kode_dokter', '$subspesialisasi', '$foto_dokter', '$nama_dokter', '$tanggal_lahir_dokter', '$jenis_kelamin_dokter', '$alamat_dokter', '$email', '$telepon_dokter')";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error tambah_data_dokter: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_staff($id_user, $kode_staff, $jabatan_staff, $foto_staff, $nama_staff, $jenis_kelamin_staff, $tanggal_lahir_staff, $alamat_staff, $email, $telepon_staff) {
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $jabatan_staff = mysqli_real_escape_string($this->koneksi, $jabatan_staff);
        $foto_staff = mysqli_real_escape_string($this->koneksi, $foto_staff);
        $nama_staff = mysqli_real_escape_string($this->koneksi, $nama_staff);
        $jenis_kelamin_staff = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_staff);
        $tanggal_lahir_staff = mysqli_real_escape_string($this->koneksi, $tanggal_lahir_staff);
        $alamat_staff = mysqli_real_escape_string($this->koneksi, $alamat_staff);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        $telepon_staff = mysqli_real_escape_string($this->koneksi, $telepon_staff);
        $sql = "INSERT INTO data_staff(id_user, kode_staff, jabatan_staff, foto_staff, nama_staff, jenis_kelamin_staff, tanggal_lahir_staff, alamat_staff, email, telepon_staff) 
                VALUES ('$id_user', '$kode_staff', '$jabatan_staff', '$foto_staff', '$nama_staff', '$jenis_kelamin_staff', '$tanggal_lahir_staff', '$alamat_staff', '$email', '$telepon_staff')";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error tambah_data_staff: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_dokumen($id_dokumen, $kode_dokter, $kode_staff, $file_dokumen, $status) {
        $id_dokumen = mysqli_real_escape_string($this->koneksi, $id_dokumen);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $file_dokumen = mysqli_real_escape_string($this->koneksi, $file_dokumen);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $sql = "INSERT INTO data_dokumen(id_dokumen, kode_dokter, kode_staff, file_dokumen, status) 
                VALUES ('$id_dokumen', '$kode_dokter', '$kode_staff', '$file_dokumen', '$status')";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error tambah_data_dokumen: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_pasien($nik, $nama_pasien, $jenis_kelamin_pasien, $tgl_lahir_pasien, $alamat_pasien, $telepon_pasien) {
        $nik = mysqli_real_escape_string($this->koneksi, $nik);
        $nama_pasien = mysqli_real_escape_string($this->koneksi, $nama_pasien);
        $jenis_kelamin_pasien = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_pasien);
        $tgl_lahir_pasien = mysqli_real_escape_string($this->koneksi, $tgl_lahir_pasien);
        $alamat_pasien = mysqli_real_escape_string($this->koneksi, $alamat_pasien);
        $telepon_pasien = mysqli_real_escape_string($this->koneksi, $telepon_pasien);
        $sql = "INSERT INTO data_pasien(nik, nama_pasien, jenis_kelamin_pasien, tgl_lahir_pasien, alamat_pasien, telepon_pasien) 
                VALUES ('$nik', '$nama_pasien', '$jenis_kelamin_pasien', '$tgl_lahir_pasien', '$alamat_pasien', '$telepon_pasien')";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error tambah_data_pasien: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_rekam($id_pasien, $kode_dokter, $jenis_kunjungan, $tanggal_periksa, $keluhan, $diagnosa, $catatan) {
        // Escape string untuk keamanan
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $tanggal_periksa = mysqli_real_escape_string($this->koneksi, $tanggal_periksa);
        $diagnosa = mysqli_real_escape_string($this->koneksi, $diagnosa);
        $jenis_kunjungan = mysqli_real_escape_string($this->koneksi, $jenis_kunjungan);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $keluhan = mysqli_real_escape_string($this->koneksi, $keluhan);
        
        $sql = "INSERT INTO data_rekam_medis(id_pasien, kode_dokter, jenis_kunjungan, tanggal_periksa, keluhan, diagnosa, catatan) 
                VALUES ('$id_pasien', '$kode_dokter', '$jenis_kunjungan', NOW(), '$keluhan', '$diagnosa', '$catatan')";
        
        $result = mysqli_query($this->koneksi, $sql);
        
        if ($result) {
            // ✅ PERBAIKAN: Kembalikan ID yang baru di-insert
            return mysqli_insert_id($this->koneksi);
        } else {
            error_log("Error tambah_data_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_data_antrian($nomor_antrian, $id_pasien, $kode_dokter, $jenis_antrian, $status, $tanggal_antrian = null) {
        $waktu_daftar = date('Y-m-d H:i:s');
        $update_at = date('Y-m-d H:i:s');
        
        if ($tanggal_antrian) {
            $tanggal_antrian_sql = "'$tanggal_antrian'";
        } else {
            $tanggal_antrian_sql = "NULL";
        }
        
        if ($kode_dokter && $kode_dokter != '') {
            $kode_dokter_sql = "'$kode_dokter'";
        } else {
            $kode_dokter_sql = "NULL";
        }
        
        $query = "INSERT INTO data_antrian (nomor_antrian, id_pasien, kode_dokter, jenis_antrian, status, tanggal_antrian, waktu_daftar, update_at) 
                  VALUES ('$nomor_antrian', '$id_pasien', $kode_dokter_sql, 
                          '$jenis_antrian', '$status', $tanggal_antrian_sql, NOW(), NOW())";
        
        return mysqli_query($this->koneksi, $query);
    }
    function tambah_data_transaksi($id_rekam, $kode_staff, $tanggal_transaksi, $grand_total, $metode_pembayaran, $status_pembayaran) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $tanggal_transaksi = mysqli_real_escape_string($this->koneksi, $tanggal_transaksi);
        $grand_total = mysqli_real_escape_string($this->koneksi, $grand_total);
        $metode_pembayaran = mysqli_real_escape_string($this->koneksi, $metode_pembayaran);
        $status_pembayaran = mysqli_real_escape_string($this->koneksi, $status_pembayaran);
        $query = "INSERT INTO data_transaksi (id_rekam, kode_staff, tanggal_transaksi, grand_total, metode_pembayaran, status_pembayaran) 
                VALUES ($id_rekam, '$kode_staff', NOW(), '$grand_total', '$metode_pembayaran', '$status_pembayaran')";
        $result = mysqli_query($this->koneksi, $query);
        if ($result) {
            return mysqli_insert_id($this->koneksi); // Return ID transaksi baru
        } else {
            error_log("Database error: " . mysqli_error($this->koneksi) . " | Query: " . $query);
            return false;
        }
    }
    function tambah_data_transaksi_by_rekam($id_rekam) {    
        $status_pembayaran = 'Belum Bayar';
        $metode_pembayaran = 'Tunai'; // atau default lain
        $id_kontrol = NULL;
        $sql = "INSERT INTO data_transaksi(id_rekam,kode_staff, tanggal_transaksi, grand_total, metode_pembayaran, status_pembayaran
                ) VALUES ('$id_rekam',NULL, NULL, NULL, '$metode_pembayaran','$status_pembayaran')";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error tambah_data_transaksi: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function tambah_aktivitas_user($jenis, $entitas, $keterangan = '') {
        // Ambil id_user dari session
        $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : null;
        
        $jenis = mysqli_real_escape_string($this->koneksi, $jenis);
        $entitas = mysqli_real_escape_string($this->koneksi, $entitas);
        $keterangan = mysqli_real_escape_string($this->koneksi, $keterangan);
        $sql = "INSERT INTO aktivitas_user (id_user, jenis, entitas, keterangan) VALUES ('$id_user', '$jenis', '$entitas', '$keterangan')";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_aktivitas_user: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_jadwal_dokter($kode_dokter, $hari, $shift, $status, $jam_mulai, $jam_selesai) {
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $hari = mysqli_real_escape_string($this->koneksi, $hari);
        $shift = mysqli_real_escape_string($this->koneksi, $shift);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $jam_mulai = mysqli_real_escape_string($this->koneksi, $jam_mulai);
        $jam_selesai = mysqli_real_escape_string($this->koneksi, $jam_selesai);
        $sql = "INSERT INTO data_jadwal_dokter (kode_dokter, hari, shift, status, jam_mulai, jam_selesai) 
                VALUES ('$kode_dokter', '$hari', '$shift', '$status', '$jam_mulai', '$jam_selesai')";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_jadwal_dokter: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_alat_medis($kode_alat, $nama_alat, $jenis_alat, $lokasi, $kondisi, $tanggal_beli, $status, $deskripsi) {
        $kode_alat = mysqli_real_escape_string($this->koneksi, $kode_alat);
        $nama_alat = mysqli_real_escape_string($this->koneksi, $nama_alat);
        $jenis_alat = mysqli_real_escape_string($this->koneksi, $jenis_alat);
        $lokasi = mysqli_real_escape_string($this->koneksi, $lokasi);
        $kondisi = mysqli_real_escape_string($this->koneksi, $kondisi);
        $tanggal_beli = mysqli_real_escape_string($this->koneksi, $tanggal_beli);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "INSERT INTO data_alat_medis (kode_dokter, hari, shift, status, jam_mulai, jam_selesai) 
                VALUES ('$kode_dokter', '$hari', '$shift', '$status', '$jam_mulai', '$jam_selesai')";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_alat_medis: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_tindakan_medis($kode_tindakan, $nama_tindakan, $kategori, $tarif, $deskripsi) {
        $kode_tindakan = mysqli_real_escape_string($this->koneksi, $kode_tindakan);
        $nama_tindakan = mysqli_real_escape_string($this->koneksi, $nama_tindakan);
        $kategori = mysqli_real_escape_string($this->koneksi, $kategori);
        $tarif = mysqli_real_escape_string($this->koneksi, $tarif);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "INSERT INTO data_tindakan_medis (kode_tindakan, nama_tindakan, kategori, tarif, deskripsi) 
                VALUES ('$kode_tindakan', '$nama_tindakan', '$kategori', '$tarif', '$deskripsi')";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_tindakan_medis: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_obat($kode_obat, $nama_obat, $jenis_obat, $satuan, $stok, $harga, $expired_date, $deskripsi) {
        $kode_obat = mysqli_real_escape_string($this->koneksi, $kode_obat);
        $nama_obat = mysqli_real_escape_string($this->koneksi, $nama_obat);
        $jenis_obat = mysqli_real_escape_string($this->koneksi, $jenis_obat);
        $satuan = mysqli_real_escape_string($this->koneksi, $satuan);
        $stok = mysqli_real_escape_string($this->koneksi, $stok);
        $harga = mysqli_real_escape_string($this->koneksi, $harga);
        $expired_date = mysqli_real_escape_string($this->koneksi, $expired_date);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "INSERT INTO data_obat (kode_obat, nama_obat, jenis_obat, satuan, stok, harga, expired_date, deskripsi) 
                VALUES ('$kode_obat', '$nama_obat', '$jenis_obat', '$satuan', '$stok', '$harga', '$expired_date', '$deskripsi')";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_obat: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_pemeriksaan_mata($id_rekam, $visus_od, $visus_os, $sph_od, $cyl_od, $axis_od, $sph_os, $cyl_os, $axis_os, $tio_od, $tio_os, $slit_lamp, $catatan, $created_at, $updated_at) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $visus_od = mysqli_real_escape_string($this->koneksi, $visus_od);
        $visus_os = mysqli_real_escape_string($this->koneksi, $visus_os);
        $sph_od = mysqli_real_escape_string($this->koneksi, $sph_od);
        $cyl_od = mysqli_real_escape_string($this->koneksi, $cyl_od);
        $axis_od = mysqli_real_escape_string($this->koneksi, $axis_od);
        $sph_os = mysqli_real_escape_string($this->koneksi, $sph_os);
        $cyl_os = mysqli_real_escape_string($this->koneksi, $cyl_os);
        $axis_os = mysqli_real_escape_string($this->koneksi, $axis_os);
        $tio_od = mysqli_real_escape_string($this->koneksi, $tio_od);
        $tio_os = mysqli_real_escape_string($this->koneksi, $tio_os);
        $slit_lamp = mysqli_real_escape_string($this->koneksi, $slit_lamp);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $created_at = mysqli_real_escape_string($this->koneksi, $created_at);
        $updated_at = mysqli_real_escape_string($this->koneksi, $updated_at);
        $sql = "INSERT INTO data_pemeriksaan_mata (id_rekam, visus_od, visus_os, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, tio_od, tio_os, slit_lamp, catatan, created_at, updated_at) 
                VALUES ('$id_rekam', '$visus_od', '$visus_os', '$sph_od', '$cyl_od', '$axis_od', '$sph_os', '$cyl_os', '$axis_os', '$tio_os', '$tio_os', '$slit_lamp', '$catatan', NOW(), NOW())";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_pemeriksaan_mata: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_resep_kacamata($id_rekam, $sph_od, $cyl_od, $axis_od, $sph_os, $cyl_os, $axis_os, $pd, $catatan, $created_at) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $sph_od = mysqli_real_escape_string($this->koneksi, $sph_od);
        $cyl_od = mysqli_real_escape_string($this->koneksi, $cyl_od);
        $axis_od = mysqli_real_escape_string($this->koneksi, $axis_od);
        $sph_os = mysqli_real_escape_string($this->koneksi, $sph_os);
        $cyl_os = mysqli_real_escape_string($this->koneksi, $cyl_os);
        $axis_os = mysqli_real_escape_string($this->koneksi, $axis_os);
        $pd = mysqli_real_escape_string($this->koneksi, $pd);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $created_at = mysqli_real_escape_string($this->koneksi, $created_at);
        $sql = "INSERT INTO data_resep_kacamata (id_rekam, sph_od, cyl_od, axis_od, sph_os, cyl_os, axis_os, pd, catatan, created_at) 
                VALUES ('$id_rekam', '$sph_od', '$cyl_od', '$axis_od', '$sph_os', '$cyl_os', '$axis_os','$pd', '$catatan', NOW())";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_resep_kacamata: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_data_resep_obat($id_rekam, $tanggal_resep, $catatan, $created_at) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $tanggal_resep = mysqli_real_escape_string($this->koneksi, $tanggal_resep);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $created_at = mysqli_real_escape_string($this->koneksi, $created_at);
        $sql = "INSERT INTO data_resep_obat (id_rekam, tanggal_resep, catatan, created_at) 
                VALUES ('$id_rekam', '$tanggal_resep','$catatan', NOW())";
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_data_resep_obat: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }
    function tambah_aktivitas_profile($id_user, $jenis, $entitas, $keterangan = '', $waktu = null) {
        // Escape string untuk keamanan
        $id_user = (int)$id_user; // pastikan integer
        $jenis = mysqli_real_escape_string($this->koneksi, $jenis);
        $entitas = mysqli_real_escape_string($this->koneksi, $entitas);
        $keterangan = mysqli_real_escape_string($this->koneksi, $keterangan);
        
        // Jika waktu tidak disediakan, gunakan NOW()
        if ($waktu) {
            $waktu = mysqli_real_escape_string($this->koneksi, $waktu);
            $sql = "INSERT INTO aktivitas_profile (id_user, jenis, entitas, keterangan, waktu) 
                    VALUES ('$id_user', '$jenis', '$entitas', '$keterangan', '$waktu')";
        } else {
            $sql = "INSERT INTO aktivitas_profile (id_user, jenis, entitas, keterangan, waktu) 
                    VALUES ('$id_user', '$jenis', '$entitas', '$keterangan', NOW())";
        }
        
        $result = mysqli_query($this->koneksi, $sql);
        if (!$result) {
            error_log("Error tambah_aktivitasprofile: " . mysqli_error($this->koneksi));
            return false;
        }
        return true;
    }

    // EDIT DATA
    function update_nama_user($id_user, $nama) {
        try {
            $sql = "UPDATE users SET nama = ? WHERE id_user = ?";
            $stmt = $this->koneksi->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("si", $nama, $id_user);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            } else {
                error_log("Error preparing statement: " . $this->conn->error);
                return false;
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error updating user name: " . $e->getMessage());
            return false;
        }
    }
    function edit_data_user($id_user, $role, $nama, $username, $email, $password = '') {
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        $role = mysqli_real_escape_string($this->koneksi, $role);
        $nama = mysqli_real_escape_string($this->koneksi, $nama);
        $username = mysqli_real_escape_string($this->koneksi, $username);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        if (!empty($password)) {
            $password = mysqli_real_escape_string($this->koneksi, $password);
            $sql = "UPDATE users SET role = '$role', nama = '$nama', username = '$username', email = '$email', password = '$password' WHERE id_user = '$id_user'";
        } else {
            $sql = "UPDATE users SET role = '$role', nama = '$nama', username = '$username', email = '$email' WHERE id_user = '$id_user'";
        }
        error_log("DEBUG edit_data_user - Query: " . $sql);
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_user: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_dokter($id_user, $kode_dokter, $subspesialisasi, $foto_dokter, $nama_dokter, $tanggal_lahir_dokter, $jenis_kelamin_dokter, $alamat_dokter, $email, $telepon_dokter, $ruang) {
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $subspesialisasi = mysqli_real_escape_string($this->koneksi, $subspesialisasi);
        $nama_dokter = mysqli_real_escape_string($this->koneksi, $nama_dokter);
        $tanggal_lahir_dokter = mysqli_real_escape_string($this->koneksi, $tanggal_lahir_dokter);
        $jenis_kelamin_dokter = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_dokter);
        $alamat_dokter = mysqli_real_escape_string($this->koneksi, $alamat_dokter);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        $telepon_dokter = mysqli_real_escape_string($this->koneksi, $telepon_dokter);
        $ruang = mysqli_real_escape_string($this->koneksi, $ruang);
        if (empty($foto_dokter)) {
            $sql_check = "SELECT foto_dokter FROM data_dokter WHERE id_user = '$id_user'";
            $result = mysqli_query($this->koneksi, $sql_check);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $foto_dokter = mysqli_real_escape_string($this->koneksi, $row['foto_dokter']);
            }
        } else {
            $foto_dokter = mysqli_real_escape_string($this->koneksi, $foto_dokter);
        }
        $sql = "UPDATE data_dokter SET kode_dokter = '$kode_dokter', subspesialisasi = '$subspesialisasi', foto_dokter = '$foto_dokter', 
                    nama_dokter = '$nama_dokter', tanggal_lahir_dokter = '$tanggal_lahir_dokter', jenis_kelamin_dokter = '$jenis_kelamin_dokter', 
                    alamat_dokter = '$alamat_dokter', email = '$email', telepon_dokter = '$telepon_dokter', 
                    ruang = '$ruang' WHERE id_user = '$id_user'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_dokter: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_staff($id_user, $kode_staff, $jabatan_staff, $foto_staff, $nama_staff, $jenis_kelamin_staff, $tanggal_lahir_staff, $alamat_staff, $email, $telepon_staff) {
        $id_user = mysqli_real_escape_string($this->koneksi, $id_user);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $jabatan_staff = mysqli_real_escape_string($this->koneksi, $jabatan_staff);
        $nama_staff = mysqli_real_escape_string($this->koneksi, $nama_staff);
        $jenis_kelamin_staff = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_staff);
        $tanggal_lahir_staff = mysqli_real_escape_string($this->koneksi, $tanggal_lahir_staff);
        $alamat_staff = mysqli_real_escape_string($this->koneksi, $alamat_staff);
        $email = mysqli_real_escape_string($this->koneksi, $email);
        $telepon_staff = mysqli_real_escape_string($this->koneksi, $telepon_staff);
        if (empty($foto_staff)) {
            $sql_check = "SELECT foto_staff FROM data_staff WHERE id_user = '$id_user'";
            $result = mysqli_query($this->koneksi, $sql_check);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $foto_staff = mysqli_real_escape_string($this->koneksi, $row['foto_staff']);
            }
        } else {
            $foto_staff = mysqli_real_escape_string($this->koneksi, $foto_staff);
        }
        $sql = "UPDATE data_staff SET kode_staff = '$kode_staff', jabatan_staff = '$jabatan_staff', foto_staff = '$foto_staff', 
                    nama_staff = '$nama_staff', jenis_kelamin_staff = '$jenis_kelamin_staff', tanggal_lahir_staff = '$tanggal_lahir_staff', 
                    alamat_staff = '$alamat_staff', email = '$email', telepon_staff = '$telepon_staff' WHERE id_user = '$id_user'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_staff: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_dokumen($id_data_dokumen, $id_dokumen, $kode_dokter, $kode_staff, $file_dokumen, $status) {
        $id_data_dokumen = mysqli_real_escape_string($this->koneksi, $id_data_dokumen);
        $id_dokumen = mysqli_real_escape_string($this->koneksi, $id_dokumen);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $file_dokumen = mysqli_real_escape_string($this->koneksi, $file_dokumen);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $sql = "UPDATE data_dokumen SET id_dokumen = '$id_dokumen', kode_dokter = '$kode_dokter', kode_staff = '$kode_staff',  
                    file_dokumen = '$file_dokumen', status = '$status' WHERE id_data_dokumen = '$id_data_dokumen'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_dokumen: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_pasien($id_pasien, $nik, $nama_pasien, $jenis_kelamin_pasien, $tgl_lahir_pasien, $alamat_pasien, $telepon_pasien) {
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $nik = mysqli_real_escape_string($this->koneksi, $nik);
        $nama_pasien = mysqli_real_escape_string($this->koneksi, $nama_pasien);
        $jenis_kelamin_pasien = mysqli_real_escape_string($this->koneksi, $jenis_kelamin_pasien);
        $tgl_lahir_pasien = mysqli_real_escape_string($this->koneksi, $tgl_lahir_pasien);
        $alamat_pasien = mysqli_real_escape_string($this->koneksi, $alamat_pasien);
        $telepon_pasien = mysqli_real_escape_string($this->koneksi, $telepon_pasien);
        $sql = "UPDATE data_pasien SET nik = '$nik', nama_pasien = '$nama_pasien', jenis_kelamin_pasien = '$jenis_kelamin_pasien', 
                    tgl_lahir_pasien = '$tgl_lahir_pasien', alamat_pasien= '$alamat_pasien', telepon_pasien= '$telepon_pasien'  
                    WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_pasien: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_rekam($id_rekam, $id_pasien, $kode_dokter, $jenis_kunjungan, $keluhan, $diagnosa, $catatan) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $diagnosa = mysqli_real_escape_string($this->koneksi, $diagnosa);
        $jenis_kunjungan = mysqli_real_escape_string($this->koneksi, $jenis_kunjungan);
        $keluhan = mysqli_real_escape_string($this->koneksi, $keluhan);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $sql = "UPDATE data_rekam_medis SET id_pasien = '$id_pasien', kode_dokter = '$kode_dokter', jenis_kunjungan = '$jenis_kunjungan', 
                keluhan = '$keluhan',  diagnosa = '$diagnosa', catatan= '$catatan' WHERE id_rekam = '$id_rekam'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_antrian($id_antrian, $nomor_antrian, $id_pasien, $kode_dokter, $status, $jenis_antrian, $update_at, $tanggal_antrian = null) {
        if ($tanggal_antrian) {
            $tanggal_antrian_sql = "'$tanggal_antrian'";
        } else {
            $tanggal_antrian_sql = "NULL";
        }
        
        if ($kode_dokter && $kode_dokter != '') {
            $kode_dokter_sql = "'$kode_dokter'";
        } else {
            $kode_dokter_sql = "NULL";
        }
        
        $query = "UPDATE data_antrian SET nomor_antrian = '$nomor_antrian', id_pasien = '$id_pasien', kode_dokter = $kode_dokter_sql, status = '$status', jenis_antrian = '$jenis_antrian',
                tanggal_antrian = $tanggal_antrian_sql, update_at = NOW() WHERE id_antrian = '$id_antrian'";
        
        return mysqli_query($this->koneksi, $query);
    }
    function edit_data_transaksi($id_transaksi, $id_rekam, $kode_staff, $tanggal_transaksi, $grand_total, $metode_pembayaran, $status_pembayaran) {
        $id_transaksi = mysqli_real_escape_string($this->koneksi, $id_transaksi);
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $kode_staff = mysqli_real_escape_string($this->koneksi, $kode_staff);
        $tanggal_transaksi = date('Y-m-d H:i:s');
        $metode_pembayaran = mysqli_real_escape_string($this->koneksi, $metode_pembayaran);
        $grand_total = (int)$grand_total;
        $status_pembayaran = mysqli_real_escape_string($this->koneksi, $status_pembayaran);
        $query = "UPDATE data_transaksi SET id_rekam = $id_rekam, kode_staff = '$kode_staff', tanggal_transaksi = NOW(), 
                    metode_pembayaran = '$metode_pembayaran', grand_total = $grand_total, status_pembayaran = '$status_pembayaran' WHERE id_transaksi = $id_transaksi";
        $result = mysqli_query($this->koneksi, $query);
        if (!$result) {
            error_log("Database error: " . mysqli_error($this->koneksi) . " | Query: " . $query);
        }
        return $result;
    }
    function edit_data_transaksi_rekam($id_transaksi, $id_pasien, $biaya, $id_rekam = null, $id_kontrol = null) {
        $id_transaksi = mysqli_real_escape_string($this->koneksi, $id_transaksi);
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $biaya = mysqli_real_escape_string($this->koneksi, $biaya);
        $id_rekam_value = "NULL";
        if (!empty($id_rekam) && $id_rekam !== '') {
            $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
            $id_rekam_value = "'$id_rekam'";
        }
        $id_kontrol_value = "NULL";
        if (!empty($id_kontrol) && $id_kontrol !== '') {
            $id_kontrol = mysqli_real_escape_string($this->koneksi, $id_kontrol);
            $id_kontrol_value = "'$id_kontrol'";
        }
        $sql = "UPDATE data_transaksi SET id_rekam = $id_rekam_value, id_kontrol = $id_kontrol_value, id_pasien = '$id_pasien', 
                    biaya = '$biaya' WHERE id_transaksi = '$id_transaksi'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_transaksi_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_transaksi_kontrol($id_transaksi, $id_pasien, $biaya, $id_rekam = null, $id_kontrol = null) {
        $id_transaksi = mysqli_real_escape_string($this->koneksi, $id_transaksi);
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $biaya = mysqli_real_escape_string($this->koneksi, $biaya);
        $id_rekam_value = $id_rekam ? "'" . mysqli_real_escape_string($this->koneksi, $id_rekam) . "'" : "NULL";
        $id_kontrol_value = $id_kontrol ? "'" . mysqli_real_escape_string($this->koneksi, $id_kontrol) . "'" : "NULL";

        $sql = "UPDATE data_transaksi SET 
                    id_rekam = $id_rekam_value, 
                    id_kontrol = $id_kontrol_value, 
                    id_pasien = '$id_pasien', 
                    biaya = '$biaya' 
                WHERE id_transaksi = '$id_transaksi'";
        
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_transaksi_kontrol: " . mysqli_error($this->koneksi));
            return false;
        }
    }
    function edit_data_jadwal_dokter($id_jadwal, $kode_dokter, $hari, $shift, $status, $jam_mulai, $jam_selesai) {
        $id_jadwal = mysqli_real_escape_string($this->koneksi, $id_jadwal);
        $kode_dokter = mysqli_real_escape_string($this->koneksi, $kode_dokter);
        $hari = mysqli_real_escape_string($this->koneksi, $hari);
        $shift = mysqli_real_escape_string($this->koneksi, $shift);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $jam_mulai = mysqli_real_escape_string($this->koneksi, $jam_mulai);
        $jam_selesai = mysqli_real_escape_string($this->koneksi, $jam_selesai);
        $sql = "UPDATE data_jadwal_dokter SET kode_dokter = '$kode_dokter', hari = '$hari', shift = '$shift', status = '$status',
                    jam_mulai = '$jam_mulai', jam_selesai = '$jam_selesai' WHERE id_jadwal = '$id_jadwal'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_jadwal_dokter: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_alat_medis($id_alat, $kode_alat, $nama_alat, $jenis_alat, $lokasi, $kondisi, $tanggal_beli, $status, $deskripsi) {
        $id_alat = mysqli_real_escape_string($this->koneksi, $id_alat);
        $kode_alat = mysqli_real_escape_string($this->koneksi, $kode_alat);
        $nama_alat = mysqli_real_escape_string($this->koneksi, $nama_alat);
        $jenis_alat = mysqli_real_escape_string($this->koneksi, $jenis_alat);
        $lokasi = mysqli_real_escape_string($this->koneksi, $lokasi);
        $kondisi = mysqli_real_escape_string($this->koneksi, $kondisi);
        $tanggal_beli = mysqli_real_escape_string($this->koneksi, $tanggal_beli);
        $status = mysqli_real_escape_string($this->koneksi, $status);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "UPDATE data_alat_medis SET kode_alat = '$kode_alat', nama_alat = '$nama_alat', jenis_alat = '$jenis_alat', lokasi = '$lokasi', 
                kondisi = '$kondisi', tanggal_beli = '$tanggal_beli', status = '$status', deskripsi = '$deskripsi' WHERE id_alat = '$id_alat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_alat_medis: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_tindakan_medis($id_tindakan_medis, $kode_tindakan, $nama_tindakan, $kategori, $tarif, $deskripsi) {
        $id_tindakan_medis = mysqli_real_escape_string($this->koneksi, $id_tindakan_medis);
        $kode_tindakan = mysqli_real_escape_string($this->koneksi, $kode_tindakan);
        $nama_tindakan = mysqli_real_escape_string($this->koneksi, $nama_tindakan);
        $kategori = mysqli_real_escape_string($this->koneksi, $kategori);
        $tarif = mysqli_real_escape_string($this->koneksi, $tarif);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "UPDATE data_tindakan_medis SET kode_tindakan = '$kode_tindakan', nama_tindakan = '$nama_tindakan', 
                kategori = '$kategori', tarif = '$tarif', deskripsi = '$deskripsi' WHERE id_tindakan_medis = '$id_tindakan_medis'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_tindakan_medis: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_obat($id_obat, $kode_obat, $nama_obat, $jenis_obat, $satuan, $stok, $harga, $expired_date, $deskripsi) {
        $id_obat = mysqli_real_escape_string($this->koneksi, $id_obat);
        $kode_obat = mysqli_real_escape_string($this->koneksi, $kode_obat);
        $nama_obat = mysqli_real_escape_string($this->koneksi, $nama_obat);
        $jenis_obat = mysqli_real_escape_string($this->koneksi, $jenis_obat);
        $satuan = mysqli_real_escape_string($this->koneksi, $satuan);
        $stok = mysqli_real_escape_string($this->koneksi, $stok);
        $harga = mysqli_real_escape_string($this->koneksi, $harga);
        $expired_date = mysqli_real_escape_string($this->koneksi, $expired_date);
        $deskripsi = mysqli_real_escape_string($this->koneksi, $deskripsi);
        $sql = "UPDATE data_obat SET kode_obat = '$kode_obat', nama_obat = '$nama_obat', jenis_obat = '$jenis_obat', satuan = '$satuan',
                stok = '$stok', harga = '$harga', expired_date = '$expired_date', deskripsi = '$deskripsi' WHERE id_obat = '$id_obat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_obat: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_pemeriksaan_mata($id_pemeriksaan, $id_rekam, $visus_od, $visus_os, $sph_od, $cyl_od, $axis_od, $sph_os, $cyl_os, $axis_os, $tio_od, $tio_os, $slit_lamp, $catatan, $updated_at) {
        $id_resep_kacamata = mysqli_real_escape_string($this->koneksi, $id_pemeriksaan);
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $visus_od = mysqli_real_escape_string($this->koneksi, $visus_od);
        $visus_os = mysqli_real_escape_string($this->koneksi, $visus_os);
        $sph_od = mysqli_real_escape_string($this->koneksi, $sph_od);
        $cyl_od = mysqli_real_escape_string($this->koneksi, $cyl_od);
        $axis_od = mysqli_real_escape_string($this->koneksi, $axis_od);
        $sph_os = mysqli_real_escape_string($this->koneksi, $sph_os);
        $cyl_os = mysqli_real_escape_string($this->koneksi, $cyl_os);
        $axis_os = mysqli_real_escape_string($this->koneksi, $axis_os);
        $tio_od = mysqli_real_escape_string($this->koneksi, $tio_od);
        $tio_os = mysqli_real_escape_string($this->koneksi, $tio_os);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $updated_at = mysqli_real_escape_string($this->koneksi, $updated_at);
        $sql = "UPDATE data_pemeriksaan_medis SET id_rekam = '$id_rekam', visus_od = '$visus_od', visus_os = '$visus_od', sph_od = '$sph_od', cyl_od = '$cyl_od', axis_od = '$axis_od', sph_os = '$sph_os',
                cyl_os = '$cyl_os', axis_os = '$axis_os', tio_od = '$tio_od', tio_os = '$tio_os', catatan = '$catatan, updated_at = NOW() WHERE id_pemeriksaan = '$id_pemeriksaan'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_pemeriksaan_mata: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_resep_kacamata($id_resep_kacamata, $id_rekam, $sph_od, $cyl_od, $axis_od, $sph_os, $cyl_os, $axis_os, $pd, $catatan) {
        $id_resep_kacamata = mysqli_real_escape_string($this->koneksi, $id_resep_kacamata);
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $sph_od = mysqli_real_escape_string($this->koneksi, $sph_od);
        $cyl_od = mysqli_real_escape_string($this->koneksi, $cyl_od);
        $axis_od = mysqli_real_escape_string($this->koneksi, $axis_od);
        $sph_os = mysqli_real_escape_string($this->koneksi, $sph_os);
        $cyl_os = mysqli_real_escape_string($this->koneksi, $cyl_os);
        $axis_os = mysqli_real_escape_string($this->koneksi, $axis_os);
        $pd = mysqli_real_escape_string($this->koneksi, $pd);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $sql = "UPDATE data_resep_kacamata SET id_rekam = '$id_rekam', sph_od = '$sph_od', cyl_od = '$cyl_od', axis_od = '$axis_od', sph_os = '$sph_os',
                cyl_os = '$cyl_os', axis_os = '$axis_os', pd = '$pd', catatan = '$catatan' WHERE id_resep_kacamata = '$id_resep_kacamata'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_resep_kacamata: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function edit_data_resep_obat($id_resep_kacamata, $id_rekam, $tanggal_resep, $catatan) {
        $id_resep_obat = mysqli_real_escape_string($this->koneksi, $id_resep_obat);
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $tanggal_resep = mysqli_real_escape_string($this->koneksi, $tanggal_resep);
        $catatan = mysqli_real_escape_string($this->koneksi, $catatan);
        $sql = "UPDATE data_resep_obat SET id_rekam = '$id_rekam', tanggal_resep = '$tanggal_resep', catatan = '$catatan' WHERE id_resep_obat = '$id_resep_obat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error edit_data_resep_obat: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }

    // HAPUS DATA
    function hapus_data_user($id_user) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_user);
        $sql = "DELETE FROM users WHERE id_user = '$id_user'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_user: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_dokter($id_user) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_user);
        $sql = "DELETE FROM data_dokter WHERE id_user = '$id_user'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_dokter: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_staff($id_user) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_user);
        $sql = "DELETE FROM data_staff WHERE id_user = '$id_user'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_staff: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_dokumen($id_data_dokumen) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_data_dokumen);
        $sql = "DELETE FROM data_dokumen WHERE id_data_dokumen = '$id_data_dokumen'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_dokumen: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_pasien($id_pasien) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $sql = "DELETE FROM data_pasien WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_pasien: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_rekam($id_rekam) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $sql = "DELETE FROM data_rekam_medis WHERE id_rekam = '$id_rekam'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_rekam_by_pasien($id_pasien) {
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $sql = "DELETE FROM data_rekam_medis WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_kontrol: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_transaksi($id_transaksi) {
        $id_transaksi = mysqli_real_escape_string($this->koneksi, $id_transaksi);
        $sql = "DELETE FROM data_transaksi WHERE id_transaksi = '$id_transaksi'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_transaksi: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_transaksi_by_pasien($id_pasien) {
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $sql = "DELETE FROM data_transaksi WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_transaksi_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_transaksi_by_rekam($id_rekam) {
        $id_rekam = mysqli_real_escape_string($this->koneksi, $id_rekam);
        $sql = "DELETE FROM data_transaksi WHERE id_rekam = '$id_rekam'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_transaksi_rekam: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_transaksi_by_kontrol($id_kontrol) {
        $id_kontrol = mysqli_real_escape_string($this->koneksi, $id_kontrol);
        $sql = "DELETE FROM data_transaksi WHERE id_kontrol = '$id_kontrol'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_transaksi_by_kontrol: " . mysqli_error($this->koneksi));
            return false;
        }
    }
    function hapus_data_antrian($id_antrian) {
        $id_antrian = mysqli_real_escape_string($this->koneksi, $id_antrian);
        $sql = "DELETE FROM data_antrian WHERE id_antrian = '$id_antrian'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_mitra: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_antrian_by_pasien($id_pasien) {
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $sql = "DELETE FROM data_antrian WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_mitra: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_antrian_kontrol($id_antrian_kontrol) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_antrian_kontrol);
        $sql = "DELETE FROM data_antrian_kontrol WHERE id_antrian_kontrol = '$id_antrian_kontrol'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_mitra: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_antriankontrol_by_pasien($id_pasien) {
        $id_pasien = mysqli_real_escape_string($this->koneksi, $id_pasien);
        $sql = "DELETE FROM data_antrian_kontrol WHERE id_pasien = '$id_pasien'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_transaksi_kontrol: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_jadwal_dokter($id_jadwal) {
        $id_mitra = mysqli_real_escape_string($this->koneksi, $id_jadwal);
        $sql = "DELETE FROM data_jadwal_dokter WHERE id_jadwal = '$id_jadwal'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_jadwal_dokter: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_alat_medis($id_alat) {
        $id_alat = mysqli_real_escape_string($this->koneksi, $id_alat);
        $sql = "DELETE FROM data_alat_medis WHERE id_alat = '$id_alat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_alat_medis: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_tindakan_medis($id_tindakan_medis) {
        $id_tindakan_medis = mysqli_real_escape_string($this->koneksi, $id_tindakan_medis);
        $sql = "DELETE FROM data_tindakan_medis WHERE id_tindakan_medis = '$id_tindakan_medis'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_tindakan_medis: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_obat($id_obat) {
        $id_obat = mysqli_real_escape_string($this->koneksi, $id_obat);
        $sql = "DELETE FROM data_obat WHERE id_obat = '$id_obat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_obat: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_resep_kacamata($id_resep_kacamata) {
        $id_resep_kacamata = mysqli_real_escape_string($this->koneksi, $id_resep_kacamata);
        $sql = "DELETE FROM data_resep_kacamata WHERE id_resep_kacamata = '$id_resep_kacamata'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_resep_kacamata: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }
    function hapus_data_resep_obat($id_resep_obat) {
        $id_resep_obat = mysqli_real_escape_string($this->koneksi, $id_resep_obat);
        $sql = "DELETE FROM data_resep_obat WHERE id_resep_obat = '$id_resep_obat'";
        $result = mysqli_query($this->koneksi, $sql);
        if ($result) {
            return true;
        } else {
            error_log("Error hapus_data_resep_obat: " . mysqli_error($this->koneksi) . " Query: " . $sql);
            return false;
        }
    }

    // Dapatkan data
    function get_recent_activities($limit = 5) {
        $activities = [];
        $limit = (int)$limit;
        $query = "SELECT * FROM aktivitas ORDER BY waktu DESC LIMIT $limit";
        $result = mysqli_query($this->koneksi, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $activities[] = [
                    'waktu' => $row['waktu'],
                    'pengguna' => $row['pengguna'],
                    'aktivitas' => $row['aktivitas'],
                    'detail' => $row['detail']
                ];
            }
        } else {
            error_log("Error in get_recent_activities: " . mysqli_error($this->koneksi));
            $activities = [
                [
                    'waktu' => date('Y-m-d H:i:s'),
                    'pengguna' => 'Admin',
                    'aktivitas' => 'Login',
                    'detail' => 'Admin logged in'
                ]
            ];
        }
        return $activities;
    }
    function get_user_by_id($id_user) {
        $query = "SELECT * FROM users WHERE id_user = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_user);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_dokter_by_id($id_user) {
        $query = "SELECT * FROM data_dokter WHERE id_user = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_user);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_staff_by_id($id_user) {
        $query = "SELECT * FROM data_staff WHERE id_user = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_user);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_dokumen_by_id($id_data_dokumen) {
        $query = "SELECT * FROM data_dokumen WHERE id_data_dokumen = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_data_dokumen);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_jenis_dokumen_by_id($id_dokumen) {
        $query = "SELECT * FROM jenis_dokumen WHERE id_dokumen = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_dokumen);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_pasien_by_id($id_pasien) {
        $query = "SELECT * FROM data_pasien WHERE id_pasien = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_pasien);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_rekam_by_id($id_rekam) {
        $query = "SELECT * FROM data_rekam_medis WHERE id_rekam = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_rekam);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_antrian_by_id($id_antrian) {
        $query = "SELECT * FROM data_antrian WHERE id_antrian = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_antrian);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_transaksi_by_id($id_transaksi) {
        $query = "SELECT * FROM data_transaksi WHERE id_transaksi = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_transaksi);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_transaksi_by_rekam_id($id_rekam) {
        $query = "SELECT * FROM data_transaksi WHERE id_rekam = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_rekam);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_jadwal_by_id($id_jadwal) {
        $query = "SELECT * FROM data_jadwal_dokter WHERE id_jadwal = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_jadwal);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_alat_medis_by_id($id_alat) {
        $query = "SELECT * FROM data_alat_medis WHERE id_alat = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_alat);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_tindakan_medis_by_id($id_tindakan_medis) {
        $query = "SELECT * FROM data_tindakan_medis WHERE id_tindakan_medis = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_tindakan_medis);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_obat_by_id($id_obat) {
        $query = "SELECT * FROM data_obat WHERE id_obat = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_obat);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_resep_kacamata_by_id($id_resep_kacamata) {
        $query = "SELECT * FROM data_resep_kacamata WHERE id_resep_kacamata = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_resep_kacamata);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_resep_obat_by_id($id_resep_obat) {
        $query = "SELECT * FROM data_resep_obat WHERE id_resep_obat = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_resep_obat);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_assoc($result);
            }
        }
        return null;
    }
    function get_rekam_by_pasien($id_pasien) {
        $query = "SELECT id_rekam, kode_dokter, tanggal_periksa, diagnosa, resep_obat, catatan, biaya FROM data_rekam_medis 
                  WHERE id_pasien = ? ORDER BY id_rekam ASC";
        $stmt = $this->koneksi->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $id_pasien);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $rekam_medis = [];
                while ($row = $result->fetch_assoc()) {
                    $rekam_medis[] = $row;
                }
                return $rekam_medis; 
            }
        }
        return [];
    }
    function get_transaksi_by_month($tahun, $bulan) {
        $query = "SELECT t.*, p.nama_pasien 
                FROM data_transaksi t 
                LEFT JOIN data_pasien p ON t.id_pasien = p.id_pasien 
                WHERE YEAR(t.tanggal_transaksi) = ? 
                AND MONTH(t.tanggal_transaksi) = ? 
                ORDER BY t.tanggal_transaksi ASC";
        
        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("ss", $tahun, $bulan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    function getLastNomorAntrianBaruHariIni() {
        $tanggal_hari_ini = date('Y-m-d');
        $query = "SELECT nomor_antrian FROM data_antrian 
                  WHERE jenis_antrian = 'Baru' 
                  AND DATE(waktu_daftar) = '$tanggal_hari_ini'
                  ORDER BY id_antrian DESC LIMIT 1";
        $result = mysqli_query($this->koneksi, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['nomor_antrian'];
        }
        return null;
    }
    function getLastNomorAntrianKontrolByTanggal($tanggal_antrian) {
        $tanggal_only = date('Y-m-d', strtotime($tanggal_antrian));
        $query = "SELECT nomor_antrian FROM data_antrian 
                  WHERE jenis_antrian = 'Kontrol' 
                  AND DATE(tanggal_antrian) = '$tanggal_only'
                  ORDER BY id_antrian DESC LIMIT 1";
        $result = mysqli_query($this->koneksi, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['nomor_antrian'];
        }
        return null;
    }
    function generateNomorAntrian($jenis_antrian, $tanggal_antrian = null) {
        if ($jenis_antrian == 'Baru') {
            // Format: B001, B002, dst (reset harian)
            $last_nomor = $this->getLastNomorAntrianBaruHariIni();
            if ($last_nomor) {
                $number = (int)substr($last_nomor, 1) + 1;
            } else {
                $number = 1;
            }
            return 'B' . str_pad($number, 3, '0', STR_PAD_LEFT);
        } 
        elseif ($jenis_antrian == 'Kontrol') {
            // Format: K-YYYYMMDD-001, K-YYYYMMDD-002, dst
            $tanggal_format = date('Ymd', strtotime($tanggal_antrian));
            $last_nomor = $this->getLastNomorAntrianKontrolByTanggal($tanggal_antrian);
            
            if ($last_nomor) {
                // Extract number from format K-YYYYMMDD-XXX
                $parts = explode('-', $last_nomor);
                $number = (int)end($parts) + 1;
            } else {
                $number = 1;
            }
            return 'K-' . $tanggal_format . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
        }
        
        return null;
    }
    function getTotalPendapatanByStatus($status_pembayaran) {
        $status_pembayaran = mysqli_real_escape_string($this->koneksi, $status_pembayaran);
        $query = "SELECT COALESCE(SUM(grand_total), 0) as total FROM data_transaksi WHERE status_pembayaran = '$status_pembayaran'";
            $result = mysqli_query($this->koneksi, $query);
            
            if (!$result) {
                error_log("Error getTotalPendapatanByStatus: " . mysqli_error($this->koneksi));
                return 0;
            }
            
            $data = mysqli_fetch_assoc($result);
            return $data['total'] ?? 0;
    }
    function get_kunjungan_per_bulan() {
        $hasil = [];
        
        $query = "SELECT 
                    DATE_FORMAT(tanggal_periksa, '%Y-%m') as bulan,
                    SUM(CASE WHEN jenis_kunjungan = 'Baru' THEN 1 ELSE 0 END) as baru,
                    SUM(CASE WHEN jenis_kunjungan = 'Kontrol' THEN 1 ELSE 0 END) as kontrol
                FROM data_rekam_medis 
                WHERE tanggal_periksa IS NOT NULL 
                    AND tanggal_periksa >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                GROUP BY DATE_FORMAT(tanggal_periksa, '%Y-%m')
                ORDER BY bulan ASC";
        
        $data = mysqli_query($this->koneksi, $query);
        if (!$data) {
            error_log("Query error get_kunjungan_per_bulan: " . mysqli_error($this->koneksi));
            return [];
        }
        
        while ($row = mysqli_fetch_assoc($data)) {
            $hasil[] = $row;
        }
        return $hasil;
    }


    function get_pasien_baru_per_bulan($tahun = null) {
        if (!$tahun) $tahun = date('Y');
        $hasil = [];
        $query = "SELECT 
                    MONTH(tanggal_periksa) as bulan,
                    COUNT(*) as jumlah
                  FROM data_rekam_medis 
                  WHERE jenis_kunjungan = 'Baru' 
                    AND YEAR(tanggal_periksa) = '$tahun'
                  GROUP BY MONTH(tanggal_periksa)
                  ORDER BY bulan ASC";
        $data = mysqli_query($this->koneksi, $query);
        if ($data) {
            while ($row = mysqli_fetch_assoc($data)) {
                $hasil[$row['bulan']] = $row['jumlah'];
            }
        }
        return $hasil;
    }

    // 2. Mendapatkan jumlah pasien baru bulan ini vs bulan lalu
    function get_perbandingan_pasien_baru() {
        $bulan_ini = date('m');
        $bulan_lalu = date('m', strtotime('-1 month'));
        $tahun_ini = date('Y');
        $tahun_lalu = date('Y', strtotime('-1 month'));
        
        $query_ini = "SELECT COUNT(*) as total FROM data_rekam_medis 
                      WHERE jenis_kunjungan = 'Baru' 
                      AND MONTH(tanggal_periksa) = '$bulan_ini' 
                      AND YEAR(tanggal_periksa) = '$tahun_ini'";
        $result_ini = mysqli_query($this->koneksi, $query_ini);
        $data_ini = mysqli_fetch_assoc($result_ini);
        $jumlah_ini = $data_ini['total'] ?? 0;
        
        $query_lalu = "SELECT COUNT(*) as total FROM data_rekam_medis 
                       WHERE jenis_kunjungan = 'Baru' 
                       AND MONTH(tanggal_periksa) = '$bulan_lalu' 
                       AND YEAR(tanggal_periksa) = '$tahun_lalu'";
        $result_lalu = mysqli_query($this->koneksi, $query_lalu);
        $data_lalu = mysqli_fetch_assoc($result_lalu);
        $jumlah_lalu = $data_lalu['total'] ?? 0;
        
        $persen = 0;
        if ($jumlah_lalu > 0) {
            $persen = (($jumlah_ini - $jumlah_lalu) / $jumlah_lalu) * 100;
        } elseif ($jumlah_ini > 0) {
            $persen = 100;
        }
        
        return [
            'sekarang' => $jumlah_ini,
            'sebelumnya' => $jumlah_lalu,
            'persen' => round($persen, 1),
            'arah' => $persen >= 0 ? 'up' : 'down'
        ];
    }

    // 3. Jumlah pasien berkunjung hari ini
    function get_pasien_hari_ini() {
        $query = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE DATE(tanggal_periksa) = CURDATE()";
        $result = mysqli_query($this->koneksi, $query);
        $data = mysqli_fetch_assoc($result);
        return $data['total'] ?? 0;
    }

    // 4. Perbandingan pasien hari ini vs kemarin
    function get_perbandingan_pasien_harian() {
        $query_hari_ini = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE DATE(tanggal_periksa) = CURDATE()";
        $query_kemarin = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE DATE(tanggal_periksa) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        
        $result_ini = mysqli_query($this->koneksi, $query_hari_ini);
        $result_kemarin = mysqli_query($this->koneksi, $query_kemarin);
        
        $hari_ini = mysqli_fetch_assoc($result_ini)['total'] ?? 0;
        $kemarin = mysqli_fetch_assoc($result_kemarin)['total'] ?? 0;
        
        $persen = 0;
        if ($kemarin > 0) {
            $persen = (($hari_ini - $kemarin) / $kemarin) * 100;
        } elseif ($hari_ini > 0) {
            $persen = 100;
        }
        
        return [
            'hari_ini' => $hari_ini,
            'kemarin' => $kemarin,
            'persen' => round($persen, 1),
            'arah' => $persen >= 0 ? 'up' : 'down'
        ];
    }

    // 5. Jumlah antrian bulan ini
    function get_antrian_bulan_ini() {
        $query = "SELECT COUNT(*) as total FROM data_antrian 
                  WHERE MONTH(tanggal_antrian) = MONTH(CURDATE()) 
                  AND YEAR(tanggal_antrian) = YEAR(CURDATE())";
        $result = mysqli_query($this->koneksi, $query);
        $data = mysqli_fetch_assoc($result);
        return $data['total'] ?? 0;
    }

    // 6. Perbandingan antrian bulan ini vs bulan lalu
    function get_perbandingan_antrian() {
        $bulan_ini = date('m');
        $bulan_lalu = date('m', strtotime('-1 month'));
        $tahun_ini = date('Y');
        $tahun_lalu = date('Y', strtotime('-1 month'));
        
        $query_ini = "SELECT COUNT(*) as total FROM data_antrian 
                      WHERE MONTH(tanggal_antrian) = '$bulan_ini' AND YEAR(tanggal_antrian) = '$tahun_ini'";
        $query_lalu = "SELECT COUNT(*) as total FROM data_antrian 
                       WHERE MONTH(tanggal_antrian) = '$bulan_lalu' AND YEAR(tanggal_antrian) = '$tahun_lalu'";
        
        $result_ini = mysqli_query($this->koneksi, $query_ini);
        $result_lalu = mysqli_query($this->koneksi, $query_lalu);
        
        $jumlah_ini = mysqli_fetch_assoc($result_ini)['total'] ?? 0;
        $jumlah_lalu = mysqli_fetch_assoc($result_lalu)['total'] ?? 0;
        
        $persen = 0;
        if ($jumlah_lalu > 0) {
            $persen = (($jumlah_ini - $jumlah_lalu) / $jumlah_lalu) * 100;
        } elseif ($jumlah_ini > 0) {
            $persen = 100;
        }
        
        return [
            'sekarang' => $jumlah_ini,
            'sebelumnya' => $jumlah_lalu,
            'persen' => round($persen, 1),
            'arah' => $persen >= 0 ? 'up' : 'down'
        ];
    }

    // 7. Jumlah kontrol terjadwal bulan ini (dari data_antrian)
    function get_kontrol_bulan_ini() {
        $query = "SELECT COUNT(*) as total FROM data_antrian 
                  WHERE jenis_antrian = 'Kontrol' 
                  AND status = 'Dijadwalkan'
                  AND MONTH(tanggal_antrian) = MONTH(CURDATE()) 
                  AND YEAR(tanggal_antrian) = YEAR(CURDATE())";
        $result = mysqli_query($this->koneksi, $query);
        $data = mysqli_fetch_assoc($result);
        return $data['total'] ?? 0;
    }

    // 8. Perbandingan kontrol bulan ini vs bulan lalu
    function get_perbandingan_kontrol() {
        $bulan_ini = date('m');
        $bulan_lalu = date('m', strtotime('-1 month'));
        $tahun_ini = date('Y');
        $tahun_lalu = date('Y', strtotime('-1 month'));
        
        $query_ini = "SELECT COUNT(*) as total FROM data_antrian 
                      WHERE jenis_antrian = 'Kontrol' AND status = 'Dijadwalkan'
                      AND MONTH(tanggal_antrian) = '$bulan_ini' AND YEAR(tanggal_antrian) = '$tahun_ini'";
        $query_lalu = "SELECT COUNT(*) as total FROM data_antrian 
                       WHERE jenis_antrian = 'Kontrol' AND status = 'Dijadwalkan'
                       AND MONTH(tanggal_antrian) = '$bulan_lalu' AND YEAR(tanggal_antrian) = '$tahun_lalu'";
        
        $result_ini = mysqli_query($this->koneksi, $query_ini);
        $result_lalu = mysqli_query($this->koneksi, $query_lalu);
        
        $jumlah_ini = mysqli_fetch_assoc($result_ini)['total'] ?? 0;
        $jumlah_lalu = mysqli_fetch_assoc($result_lalu)['total'] ?? 0;
        
        $persen = 0;
        if ($jumlah_lalu > 0) {
            $persen = (($jumlah_ini - $jumlah_lalu) / $jumlah_lalu) * 100;
        } elseif ($jumlah_ini > 0) {
            $persen = 100;
        }
        
        return [
            'sekarang' => $jumlah_ini,
            'sebelumnya' => $jumlah_lalu,
            'persen' => round($persen, 1),
            'arah' => $persen >= 0 ? 'up' : 'down'
        ];
    }

    // 9. Data kunjungan per bulan untuk chart (tahun ini)
    function get_kunjungan_per_bulan_chart() {
        $tahun = date('Y');
        $hasil = [
            'bulan' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            'baru' => array_fill(0, 12, 0),
            'kontrol' => array_fill(0, 12, 0)
        ];
        
        $query = "SELECT 
                    MONTH(tanggal_periksa) as bulan,
                    jenis_kunjungan,
                    COUNT(*) as jumlah
                  FROM data_rekam_medis 
                  WHERE YEAR(tanggal_periksa) = '$tahun'
                  GROUP BY MONTH(tanggal_periksa), jenis_kunjungan";
        
        $data = mysqli_query($this->koneksi, $query);
        if ($data) {
            while ($row = mysqli_fetch_assoc($data)) {
                $index = (int)$row['bulan'] - 1;
                if ($row['jenis_kunjungan'] == 'Baru') {
                    $hasil['baru'][$index] = (int)$row['jumlah'];
                } elseif ($row['jenis_kunjungan'] == 'Kontrol') {
                    $hasil['kontrol'][$index] = (int)$row['jumlah'];
                }
            }
        }
        
        return $hasil;
    }

    // 10. Mendapatkan data 7 hari terakhir untuk sparkline
    function get_data_7_hari_terakhir() {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = date('Y-m-d', strtotime("-$i days"));
            $query = "SELECT COUNT(*) as total FROM data_rekam_medis WHERE DATE(tanggal_periksa) = '$tgl'";
            $result = mysqli_query($this->koneksi, $query);
            $row = mysqli_fetch_assoc($result);
            $data[] = $row['total'] ?? 0;
        }
        return $data;
    }

    // 11. Mendapatkan data antrian 12 bulan terakhir untuk sparkline
    function get_data_antrian_12_bulan() {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $bulan = date('m', strtotime("-$i months"));
            $tahun = date('Y', strtotime("-$i months"));
            $query = "SELECT COUNT(*) as total FROM data_antrian 
                      WHERE MONTH(tanggal_antrian) = '$bulan' AND YEAR(tanggal_antrian) = '$tahun'";
            $result = mysqli_query($this->koneksi, $query);
            $row = mysqli_fetch_assoc($result);
            $data[] = $row['total'] ?? 0;
        }
        return $data;
    }
}    
?>