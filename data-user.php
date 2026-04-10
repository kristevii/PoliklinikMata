<?php
session_start();
require_once "koneksi.php"; // Pastikan path ke file koneksi.php benar
$db = new database();

// Validasi akses: Hanya Admin dan Staff dengan jabatan IT Support yang bisa mengakses halaman ini
if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];
$jabatan_user = '';

// Ambil data jabatan user jika role adalah Staff
if ($role == 'Staff' || $role == 'staff') {
    $query_staff = "SELECT jabatan_staff FROM data_staff WHERE id_user = '$id_user'";
    $result_staff = $db->koneksi->query($query_staff);
    
    if ($result_staff && $result_staff->num_rows > 0) {
        $staff_data = $result_staff->fetch_assoc();
        $jabatan_user = $staff_data['jabatan_staff'];
    }
}

if ($jabatan_user != 'IT Support' && 
    $jabatan_user != 'Administrasi') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: unautorized.php");
    exit();
}

// Fungsi format tanggal sesuai bahasa
function formatTanggal($tanggal, $bahasa = 'id') {
    $formatter = new IntlDateFormatter(
        $bahasa,
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'Asia/Jakarta',
        IntlDateFormatter::GREGORIAN
    );
    return $formatter->format(new DateTime($tanggal));
}

// Fungsi untuk mendapatkan kode terakhir dari database
function getLastCode($db, $role) {
    if ($role === 'Dokter') {
        $query = "SELECT kode_dokter FROM data_dokter ORDER BY kode_dokter DESC LIMIT 1";
        $result = $db->koneksi->query($query);
    } elseif ($role === 'Staff') {
        $query = "SELECT kode_staff FROM data_staff ORDER BY kode_staff DESC LIMIT 1";
        $result = $db->koneksi->query($query);
    } else {
        return null;
    }
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $role === 'Dokter' ? $row['kode_dokter'] : $row['kode_staff'];
    }
    
    return null;
}

// Fungsi untuk generate kode berikutnya
function generateNextCode($lastCode, $prefix) {
    if (!$lastCode) {
        // Jika belum ada data, mulai dari 001
        return $prefix . '001';
    }
    
    // Ekstrak angka dari kode terakhir
    $number = (int) substr($lastCode, strlen($prefix));
    $nextNumber = $number + 1;
    
    // Format dengan leading zeros (3 digit)
    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

// Mendapatkan kode terakhir dan kode default untuk form
$lastDokterCode = getLastCode($db, 'Dokter');
$lastStaffCode = getLastCode($db, 'Staff');
$defaultDokterCode = generateNextCode($lastDokterCode, 'DRM');
$defaultStaffCode = generateNextCode($lastStaffCode, 'STF');

// PROSES HAPUS USER (DENGAN CASCADE DELETE)
if (isset($_GET['hapus'])) {
    $id_user = $_GET['hapus'];
    
    // Ambil data user sebelum dihapus untuk logging
    $users_data = $db->get_user_by_id($id_user);
    $nama_user = $users_data['nama'] ?? 'Unknown';
    
    // 1. Hapus data foto jika ada
    $role = $users_data['role'] ?? '';
    if ($role === 'Dokter') {
        // Cari data dokter untuk hapus foto
        $dokter_data = $db->koneksi->query("SELECT foto_dokter FROM data_dokter WHERE id_user = $id_user");
        if ($dokter_data && $dokter_data->num_rows > 0) {
            $dokter = $dokter_data->fetch_assoc();
            $foto_to_delete = $dokter['foto_dokter'] ?? null;
            if ($foto_to_delete && file_exists('image-dokter/' . $foto_to_delete)) {
                unlink('image-dokter/' . $foto_to_delete);
            }
        }
    } elseif ($role === 'Staff') {
        // Cari data staff untuk hapus foto
        $staff_data = $db->koneksi->query("SELECT foto_staff FROM data_staff WHERE id_user = $id_user");
        if ($staff_data && $staff_data->num_rows > 0) {
            $staff = $staff_data->fetch_assoc();
            $foto_to_delete = $staff['foto_staff'] ?? null;
            if ($foto_to_delete && file_exists('image-staff/' . $foto_to_delete)) {
                unlink('image-staff/' . $foto_to_delete);
            }
        }
    }
    
    // 2. Hapus data dari tabel detail sesuai role (CASCADE DELETE manual)
    $db->beginTransaction();
    
    try {
        // Hapus data dari tabel detail terlebih dahulu
        if ($role === 'Dokter') {
            $delete_detail = $db->koneksi->query("DELETE FROM data_dokter WHERE id_user = $id_user");
            if (!$delete_detail) {
                throw new Exception("Gagal menghapus data dokter");
            }
        } elseif ($role === 'Staff') {
            $delete_detail = $db->koneksi->query("DELETE FROM data_staff WHERE id_user = $id_user");
            if (!$delete_detail) {
                throw new Exception("Gagal menghapus data staff");
            }
        }
        
        // 3. Hapus data dari tabel users
        $delete_user = $db->koneksi->query("DELETE FROM users WHERE id_user = $id_user");
        if (!$delete_user) {
            throw new Exception("Gagal menghapus data user");
        }
        
        $db->commit();
        
        // Log aktivitas
        $username = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Hapus';
        $jenis = 'User';
        $keterangan = "User '$nama_user' berhasil dihapus beserta data detailnya oleh $username.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);

        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data user berhasil dihapus beserta data detailnya.';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menghapus data user: ' . $e->getMessage();
    }
    
    header("Location: data-user.php");
    exit();
}

// PROSES EDIT USER (DENGAN SYNC NAMA DAN EMAIL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id_user = $_POST['id_user'] ?? '';
    $role = $_POST['role'] ?? '';
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validasi data
    if (empty($id_user) || empty($role) || empty($nama_lengkap) || empty($username) || empty($email)) {
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Semua field wajib diisi!';
        header("Location: data-user.php");
        exit();
    }
    
    // Cek duplikat username (kecuali untuk user yang sedang diedit)
    $check_username = $db->koneksi->query("SELECT COUNT(*) as count FROM users WHERE username = '" . $db->koneksi->real_escape_string($username) . "' AND id_user != $id_user");
    if ($check_username && $check_username->num_rows > 0) {
        $row = $check_username->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Username "' . $username . '" sudah digunakan oleh user lain. Silakan gunakan username lain.';
            header("Location: data-user.php");
            exit();
        }
    }
    
    // Cek duplikat email (kecuali untuk user yang sedang diedit)
    $check_email = $db->koneksi->query("SELECT COUNT(*) as count FROM users WHERE email = '" . $db->koneksi->real_escape_string($email) . "' AND id_user != $id_user");
    if ($check_email && $check_email->num_rows > 0) {
        $row = $check_email->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Email "' . $email . '" sudah digunakan oleh user lain. Silakan gunakan email lain.';
            header("Location: data-user.php");
            exit();
        }
    }
    
    $db->beginTransaction();
    
    try {
        // 1. Update data user dengan email
        $update_user = $db->edit_data_user($id_user, $role, $nama_lengkap, $username, $email, $password);
        if (!$update_user) {
            throw new Exception("Gagal mengupdate data user");
        }
        
        // 2. Sync nama dan email ke tabel detail sesuai role
        if ($role === 'Dokter') {
            $sync_nama = $db->koneksi->query("UPDATE data_dokter SET 
                nama_dokter = '" . $db->koneksi->real_escape_string($nama_lengkap) . "',
                email = '" . $db->koneksi->real_escape_string($email) . "' 
                WHERE id_user = $id_user");
            if (!$sync_nama) {
                throw new Exception("Gagal sync nama dan email ke data dokter");
            }
        } elseif ($role === 'Staff') {
            $sync_nama = $db->koneksi->query("UPDATE data_staff SET 
                nama_staff = '" . $db->koneksi->real_escape_string($nama_lengkap) . "',
                email = '" . $db->koneksi->real_escape_string($email) . "' 
                WHERE id_user = $id_user");
            if (!$sync_nama) {
                throw new Exception("Gagal sync nama dan email ke data staff");
            }
        }
        
        $db->commit();
        
        // Log aktivitas
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Edit';
        $jenis = 'User';
        $keterangan = "User '$nama_lengkap' berhasil diupdate oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data user berhasil diupdate (termasuk sync nama dan email).';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal mengupdate data user: ' . $e->getMessage();
    }
    
    header("Location: data-user.php");
    exit();
}

// PROSES AJAX CHECK UNTUK VALIDASI REAL-TIME
if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    $check_type = $_GET['type'] ?? '';
    $value = $db->koneksi->real_escape_string($_GET['value'] ?? '');
    $user_id = isset($_GET['user_id']) && $_GET['user_id'] ? (int)$_GET['user_id'] : null;
    
    $response = ['exists' => false];
    
    switch ($check_type) {
        case 'username':
            $query = "SELECT COUNT(*) as count FROM users WHERE username = '$value'";
            if ($user_id) {
                $query .= " AND id_user != $user_id";
            }
            $result = $db->koneksi->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response['exists'] = $row['count'] > 0;
            }
            break;
            
        case 'email':
            $query = "SELECT COUNT(*) as count FROM users WHERE email = '$value'";
            if ($user_id) {
                $query .= " AND id_user != $user_id";
            }
            $result = $db->koneksi->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response['exists'] = $row['count'] > 0;
            }
            break;
            
        case 'phone_dokter':
            $query = "SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '$value'";
            if ($user_id) {
                $query .= " AND id_user != $user_id";
            }
            $result = $db->koneksi->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response['exists'] = $row['count'] > 0;
            }
            break;
            
        case 'phone_staff':
            $query = "SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '$value'";
            if ($user_id) {
                $query .= " AND id_user != $user_id";
            }
            $result = $db->koneksi->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response['exists'] = $row['count'] > 0;
            }
            break;
    }
    
    echo json_encode($response);
    exit();
}

// PROSES TAMBAH USER DENGAN VALIDASI LENGKAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $role     = $_POST['role'];
    $nama     = $_POST['nama_lengkap'];
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    
    $foto = null;
    
    // ================= VALIDASI USERNAME DUPLIKAT =================
    $check_username = $db->koneksi->query("SELECT COUNT(*) as count FROM users WHERE username = '" . $db->koneksi->real_escape_string($username) . "'");
    if ($check_username && $check_username->num_rows > 0) {
        $row = $check_username->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Username "' . $username . '" sudah terdaftar. Silakan gunakan username lain.';
            header("Location: data-user.php"); 
            exit();
        }
    }
    
    // ================= VALIDASI EMAIL DUPLIKAT =================
    $check_email = $db->koneksi->query("SELECT COUNT(*) as count FROM users WHERE email = '" . $db->koneksi->real_escape_string($email) . "'");
    if ($check_email && $check_email->num_rows > 0) {
        $row = $check_email->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Email "' . $email . '" sudah terdaftar. Silakan gunakan email lain.';
            header("Location: data-user.php"); 
            exit();
        }
    }
    
    // ================= VALIDASI NOMOR TELEPON =================
    $telepon = '';
    if ($role === 'Dokter') {
        $telepon = $_POST['telepon_dokter'] ?? '';
    } elseif ($role === 'Staff') {
        $telepon = $_POST['telepon_staff'] ?? '';
    }
    
    // Cek duplikat telepon di data_dokter
    $check_telepon_dokter = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE telepon_dokter = '" . $db->koneksi->real_escape_string($telepon) . "'");
    if ($check_telepon_dokter && $check_telepon_dokter->num_rows > 0) {
        $row = $check_telepon_dokter->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon "' . $telepon . '" sudah terdaftar untuk dokter. Silakan gunakan nomor lain.';
            header("Location: data-user.php"); 
            exit();
        }
    }
    
    // Cek duplikat telepon di data_staff
    $check_telepon_staff = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE telepon_staff = '" . $db->koneksi->real_escape_string($telepon) . "'");
    if ($check_telepon_staff && $check_telepon_staff->num_rows > 0) {
        $row = $check_telepon_staff->fetch_assoc();
        if ($row['count'] > 0) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Nomor telepon "' . $telepon . '" sudah terdaftar untuk staff. Silakan gunakan nomor lain.';
            header("Location: data-user.php"); 
            exit();
        }
    }
    
    // ================= VALIDASI TANGGAL LAHIR =================
    $tanggal_lahir = '';
    if ($role === 'Dokter') {
        $tanggal_lahir = $_POST['tanggal_lahir_dokter'] ?? '';
    } elseif ($role === 'Staff') {
        $tanggal_lahir = $_POST['tanggal_lahir_staff'] ?? '';
    }
    
    // Validasi tanggal lahir tidak boleh tahun saat ini
    if (!empty($tanggal_lahir)) {
        $tahun_lahir = date('Y', strtotime($tanggal_lahir));
        $tahun_sekarang = date('Y');
        
        if ($tahun_lahir == $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh menggunakan tahun ' . $tahun_sekarang . '. Silakan pilih tahun yang valid.';
            header("Location: data-user.php"); 
            exit();
        }
        
        // Validasi tambahan: tahun lahir tidak boleh lebih dari tahun sekarang
        if ($tahun_lahir > $tahun_sekarang) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Tanggal lahir tidak boleh lebih besar dari tahun sekarang.';
            header("Location: data-user.php"); 
            exit();
        }
    }

    // ================= VALIDASI KODE =================
    if ($role === 'Dokter') {
        $kode = $_POST['kode_dokter'] ?? '';
        // Validasi format kode
        if (!preg_match('/^DRM\d{3}$/', $kode)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format kode dokter tidak valid. Harus DRM diikuti 3 angka (contoh: DRM001)';
            header("Location: data-user.php"); 
            exit();
        }
        
        // Cek apakah kode sudah ada
        $check_kode = $db->koneksi->query("SELECT COUNT(*) as count FROM data_dokter WHERE kode_dokter = '$kode'");
        if ($check_kode && $check_kode->num_rows > 0) {
            $row = $check_kode->fetch_assoc();
            if ($row['count'] > 0) {
                $_SESSION['notif_status'] = 'error';
                $_SESSION['notif_message'] = 'Kode dokter "' . $kode . '" sudah digunakan. Silakan gunakan kode lain.';
                header("Location: data-user.php"); 
                exit();
            }
        }
    } elseif ($role === 'Staff') {
        $kode = $_POST['kode_staff'] ?? '';
        // Validasi format kode
        if (!preg_match('/^STF\d{3}$/', $kode)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format kode staff tidak valid. Harus STF diikuti 3 angka (contoh: STF001)';
            header("Location: data-user.php"); 
            exit();
        }
        
        // Cek apakah kode sudah ada
        $check_kode = $db->koneksi->query("SELECT COUNT(*) as count FROM data_staff WHERE kode_staff = '$kode'");
        if ($check_kode && $check_kode->num_rows > 0) {
            $row = $check_kode->fetch_assoc();
            if ($row['count'] > 0) {
                $_SESSION['notif_status'] = 'error';
                $_SESSION['notif_message'] = 'Kode staff "' . $kode . '" sudah digunakan. Silakan gunakan kode lain.';
                header("Location: data-user.php"); 
                exit();
            }
        }
    }

    // ================= PROSES UPLOAD FOTO (TIDAK WAJIB) =================
    if (($role === 'Dokter' && isset($_FILES['foto_dokter']) && $_FILES['foto_dokter']['error'] === UPLOAD_ERR_OK && $_FILES['foto_dokter']['size'] > 0) || 
        ($role === 'Staff' && isset($_FILES['foto_staff']) && $_FILES['foto_staff']['error'] === UPLOAD_ERR_OK && $_FILES['foto_staff']['size'] > 0)) {
        $file = ($role === 'Dokter') ? $_FILES['foto_dokter'] : $_FILES['foto_staff'];

        $folder = ($role === 'Dokter') ? 'image-dokter/' : 'image-staff/';
        if (!is_dir($folder)) mkdir($folder, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Format foto harus JPG, PNG, atau WEBP';
            header("Location: data-user.php"); 
            exit();
        }
        
        // Validasi ukuran file (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['notif_status'] = 'error';
            $_SESSION['notif_message'] = 'Ukuran file foto maksimal 2MB';
            header("Location: data-user.php"); 
            exit();
        }

        $nama_file = strtolower($role) . '_' . time() . '.' . $ext;
        $path = $folder . $nama_file;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $foto = $nama_file;
        }
    }

    $db->beginTransaction();

    try {
        // 1. TAMBAH USER - DAPATKAN ID (dengan email)
        $user_id = $db->tambah_data_user($role, $nama, $username, $password, $email);
        
        // Cek apakah user_id valid
        if ($user_id === false) {
            throw new Exception("Query INSERT ke users gagal");
        }
        
        if (!$user_id || $user_id <= 0) {
            throw new Exception("ID user tidak valid. user_id: " . $user_id);
        }
        
        // 2. TAMBAH DATA DETAIL BERDASARKAN ROLE
        if ($role === 'Dokter') {
            $result_dokter = $db->tambah_data_dokter(
                $user_id,
                $kode,
                $_POST['subspesialisasi'] ?? '',
                $foto,
                $nama,
                $_POST['tanggal_lahir_dokter'] ?? '',
                $_POST['jenis_kelamin_dokter'] ?? '',
                $_POST['alamat_dokter'] ?? '',
                $email,
                $_POST['telepon_dokter'] ?? ''
            );
            
            if (!$result_dokter) {
                throw new Exception("Gagal menambahkan data dokter");
            }
            
            // Update email di tabel users berdasarkan email dari form dokter
            $update_email = $db->koneksi->query("UPDATE users SET email = '" . $db->koneksi->real_escape_string($email) . "' WHERE id_user = $user_id");
            if (!$update_email) {
                throw new Exception("Gagal update email di tabel users");
            }
            
        } elseif ($role === 'Staff') {
            $result_staff = $db->tambah_data_staff(
                $user_id,
                $kode,
                $_POST['jabatan_staff'] ?? '',
                $foto,
                $nama,
                $_POST['jenis_kelamin_staff'] ?? '',
                $_POST['tanggal_lahir_staff'] ?? '',
                $_POST['alamat_staff'] ?? '',
                $email,
                $_POST['telepon_staff'] ?? ''
            );
            
            if (!$result_staff) {
                throw new Exception("Gagal menambahkan data staff");
            }
            
            // Update email di tabel users berdasarkan email dari form staff
            $update_email = $db->koneksi->query("UPDATE users SET email = '" . $db->koneksi->real_escape_string($email) . "' WHERE id_user = $user_id");
            if (!$update_email) {
                throw new Exception("Gagal update email di tabel users");
            }
        }

        $db->commit();
        
        // Log aktivitas user untuk proses tambah
        $username_session = $_SESSION['username'] ?? 'unknown user';
        $entitas = 'Tambah';
        $jenis = 'User';
        $keterangan = "User '$nama' dengan role '$role' berhasil ditambahkan oleh $username_session.";
        $waktu = date('Y-m-d H:i:s');
        $db->tambah_aktivitas_user($entitas, $jenis, $keterangan, $waktu);
        
        $_SESSION['notif_status'] = 'success';
        $_SESSION['notif_message'] = 'Data user berhasil ditambahkan. (ID: ' . $user_id . ', Kode: ' . $kode . ')';

    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['notif_status'] = 'error';
        $_SESSION['notif_message'] = 'Gagal menambahkan data user: ' . $e->getMessage();
    }

    header("Location: data-user.php");
    exit();
}

// Konfigurasi pagination, search, dan sorting
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

// Ambil semua data users (termasuk email)
$all_users = $db->tampil_data_users();

// Filter data berdasarkan search query
if (!empty($search_query)) {
    $filtered_users = [];
    foreach ($all_users as $user) {
        // Cari di semua kolom yang relevan termasuk email
        if (stripos($user['id_user'] ?? '', $search_query) !== false ||
            stripos($user['role'] ?? '', $search_query) !== false ||
            stripos($user['nama'] ?? '', $search_query) !== false ||
            stripos($user['username'] ?? '', $search_query) !== false ||
            stripos($user['email'] ?? '', $search_query) !== false) {
            $filtered_users[] = $user;
        }
    }
    $all_users = $filtered_users;
}

// Urutkan data berdasarkan ID User
if ($sort_order === 'desc') {
    // Urutkan dari ID terbesar ke terkecil (terakhir ke terawal)
    usort($all_users, function($a, $b) {
        return ($b['id_user'] ?? 0) - ($a['id_user'] ?? 0);
    });
} else {
    // Urutkan dari ID terkecil ke terbesar (terawal ke terakhir) - default
    usort($all_users, function($a, $b) {
        return ($a['id_user'] ?? 0) - ($b['id_user'] ?? 0);
    });
}

// Hitung total data
$total_entries = count($all_users);

// Hitung total halaman
$total_pages = ceil($total_entries / $entries_per_page);

// Pastikan current page valid
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Hitung offset
$offset = ($current_page - 1) * $entries_per_page;

// Ambil data untuk halaman saat ini
$data_users = array_slice($all_users, $offset, $entries_per_page);

// Hitung nomor urut yang benar berdasarkan sorting
if ($sort_order === 'desc') {
    // Untuk descending: nomor urut dari total_entries ke bawah
    $start_number = $total_entries - $offset;
} else {
    // Untuk ascending: nomor urut dari 1 ke atas (default)
    $start_number = $offset + 1;
}

// Tampilkan notifikasi jika ada
$notif_status = $_SESSION['notif_status'] ?? null;
$notif_message = $_SESSION['notif_message'] ?? null;
unset($_SESSION['notif_status'], $_SESSION['notif_message']);

// Cek apakah data user kosong untuk memicu modal
$is_data_empty = empty($data_users);
?>

<!DOCTYPE html>
<html lang="en">
  <!-- [Head] start -->
  <head>
    <title>User - Sistem Informasi Poliklinik Mata Eyethica</title>
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description"
      content="Able Pro is a trending dashboard template built with the Bootstrap 5 design framework. It is available in multiple technologies, including Bootstrap, React, Vue, CodeIgniter, Angular, .NET, and more.">
    <meta name="keywords"
      content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel, react dashboard, angular dashboard">
    <meta name="author" content="Phoenixcoded">

    <!-- [Favicon] icon -->
    <link rel="icon" href="assets/images/faviconeyethica.png" type="image/x-icon"> <!-- [Font] Family -->
<link rel="stylesheet" href="assets/fonts/inter/inter.css" id="main-font-link" />
<!-- [Tabler Icons] https://tablericons.com -->
<link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" >
<!-- [Feather Icons] https://feathericons.com -->
<link rel="stylesheet" href="assets/fonts/feather.css" >
<!-- [Font Awesome Icons] https://fontawesome.com/icons -->
<link rel="stylesheet" href="assets/fonts/fontawesome.css" >
<!-- [Material Icons] https://fonts.google.com/icons -->
<link rel="stylesheet" href="assets/fonts/material.css" >
<!-- [Template CSS Files] -->
<link rel="stylesheet" href="assets/css/style.css" id="main-style-link" >
<link rel="stylesheet" href="assets/css/style-preset.css" >

<!-- Tambahkan jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
/* Memusatkan modal secara vertikal */
.modal-dialog {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}

@media (min-width: 576px) {
    .modal-dialog {
        min-height: calc(100% - 3.5rem);
    }
}

/* Optional: Tambahkan animasi yang lebih smooth */
.modal.fade .modal-dialog {
    transform: translate(0, -50px);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: none;
}

/* Memastikan modal konten memiliki margin otomatis */
.modal-content {
    margin: auto;
}

/* Styling untuk modal hapus */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal {
    backdrop-filter: blur(2px);
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

/* Animasi modal */
.modal.fade .modal-dialog {
    transform: translateY(-50px);
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: translateY(0);
}

/* Tombol hapus dan edit */
.btn-hapus, .btn-edit {
    transition: all 0.3s ease;
}

.btn-hapus:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-edit:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

/* Loading spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Form text untuk password */
.form-text {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Styling untuk password field di tabel */
.password-field {
    position: relative;
    display: inline-block;
}

.password-mask {
    letter-spacing: 2px;
    font-family: monospace;
}

.password-toggle {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0 4px;
    font-size: 0.875rem;
}

.password-toggle:hover {
    color: #495057;
}

/* Styling untuk eye icon */
.password-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Styling untuk form staff dokter */
#formStaff, #formDokter {
    background-color: #f8f9fa;
    padding: 1.5rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
    border: 1px solid #dee2e6;
}

#formDokter h5, #formStaff h5 {
    color: #17a2b8;
    border-bottom: 2px solid #17a2b8;
    padding-bottom: 0.5rem;
}

/* Styling untuk kode otomatis */
.form-text .fas {
    margin-right: 4px;
}

.form-text.info-auto {
    color: #0dcaf0 !important;
}

.form-text.info-manual {
    color: #dc3545 !important;
}

/* Highlight untuk input kode */
input[pattern]:valid {
    border-color: #198754 !important;
    background-color: #f8fff9 !important;
}

input[pattern]:invalid:not(:placeholder-shown) {
    border-color: #dc3545 !important;
    background-color: #fff8f8 !important;
}

/* Styling khusus untuk kode input */
.kode-input-container {
    position: relative;
}

.kode-auto-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #0dcaf0;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    display: none;
}

.kode-manual-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    display: none;
}

/* Styling untuk foto preview */
.foto-preview-container {
    margin-top: 10px;
    text-align: center;
}

.foto-preview {
    max-width: 200px;
    max-height: 200px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 5px;
    background-color: white;
    display: none;
}

.foto-preview-placeholder {
    width: 200px;
    height: 200px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    background-color: #f8f9fa;
}

.foto-preview-placeholder i {
    font-size: 3rem;
    color: #adb5bd;
}

/* Styling untuk validasi telepon */
.form-text.text-danger {
    font-weight: 500;
    padding: 5px;
    border-radius: 3px;
    background-color: rgba(220, 53, 69, 0.1);
}

.form-text.text-success {
    font-weight: 500;
    padding: 5px;
    border-radius: 3px;
    background-color: rgba(25, 135, 84, 0.1);
}

/* Styling untuk validation feedback */
.invalid-feedback {
    display: none;
    font-size: 0.875rem;
}

.form-control.is-invalid ~ .invalid-feedback {
    display: block;
}

/* Styling untuk preview foto container */
.preview-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

/* Responsif untuk modal */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .foto-preview, .foto-preview-placeholder {
        max-width: 150px;
        max-height: 150px;
    }
}

/* Styling untuk feedback validasi */
#username-feedback, #email-feedback, #edit-username-feedback, #edit-email-feedback {
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

.text-success {
    color: #198754 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.text-muted {
    color: #6c757d !important;
}

/* Validasi styling */
.is-valid {
    border-color: #198754 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right calc(0.375em + 0.1875rem) center !important;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
}

.is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right calc(0.375em + 0.1875rem) center !important;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
}
</style>
  </head>
  <!-- [Head] end -->
  <!-- [Body] Start -->

  <body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
<div class="loader-bg">
  <div class="loader-track">
    <div class="loader-fill"></div>
  </div>
</div>
<!-- [ Pre-loader ] End -->
<?php include 'header.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
      <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                  <li class="breadcrumb-item" aria-current="page">Data User</li>
                </ul>
              </div>
              <div class="col-md-12">
                <div class="page-header-title">
                  <h2 class="mb-0">Data User</h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
         <div class="container-fluid">
        <?php if ($notif_message): ?>
        <div class="alert alert-<?= htmlspecialchars($notif_status) ?> alert-dismissible fade show" role="alert" id="autoDismissAlert">
            <i class="fas fa-<?= $notif_status === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($notif_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        
        <script>
            // Auto dismiss notifikasi setelah 5 detik dengan animasi
            setTimeout(function() {
                var alert = document.getElementById('autoDismissAlert');
                if (alert) {
                    // Tambahkan class fade out
                    alert.classList.remove('show');
                    
                    // Hapus element setelah animasi selesai
                    setTimeout(function() {
                        if (alert && alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 150);
                    
                    // Atau gunakan Bootstrap alert jika tersedia
                    if (typeof bootstrap !== 'undefined') {
                        try {
                            var bsAlert = bootstrap.Alert.getInstance(alert);
                            if (bsAlert) {
                                bsAlert.close();
                            } else {
                                bsAlert = new bootstrap.Alert(alert);
                                bsAlert.close();
                            }
                        } catch (e) {
                            // Fallback sudah di atas
                        }
                    }
                }
            }, 5000);
        </script>
        <?php endif; ?>

            <div class="d-flex justify-content-start mb-4">
                <!-- Tombol Tambah User dengan Modal -->
                <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
                    <i class="fas fa-plus me-1"></i> Tambah User
                </button>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <!-- Show Entries dan Search -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <label class="me-2 mb-0">Show</label>
                                <select class="form-select form-select-sm w-auto" id="entriesPerPage" onchange="changeEntries()">
                                    <option value="5" <?= $entries_per_page == 5 ? 'selected' : '' ?>>5</option>
                                    <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $entries_per_page == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <label class="ms-2 mb-0">entries</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="" class="d-flex justify-content-end">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           placeholder="Cari data user..." 
                                           value="<?= htmlspecialchars($search_query) ?>"
                                           aria-label="Search">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                    <input type="hidden" name="sort" value="<?= $sort_order ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="data-user.php?entries=<?= $entries_per_page ?>&sort=<?= $sort_order ?>" class="btn btn-outline-danger" type="button">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($search_query)): ?>
                    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Menampilkan hasil pencarian untuk: <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="mitraTable" class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <a href="<?= getSortUrl($sort_order) ?>" class="text-decoration-none text-dark">
                                            No 
                                            <?php if ($sort_order === 'asc'): ?>
                                                <i class="fas fa-sort-up ms-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort-down ms-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>ID</th>
                                    <th>Role</th>
                                    <th>Nama Lengkap</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Password</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_users) && is_array($data_users)) {
                                    foreach ($data_users as $users) {
                                        $id_user = htmlspecialchars($users['id_user'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $role = htmlspecialchars($users['role'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $nama = htmlspecialchars($users['nama'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $username = htmlspecialchars($users['username'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $email = htmlspecialchars($users['email'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $password = htmlspecialchars($users['password'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $password_length = strlen($password);
                                        $password_mask = str_repeat('•', $password_length);
                                ?>
                                    <tr>
                                        <td><?= $start_number ?></td>
                                        <td><?= $id_user ?></td>
                                        <td><?= $role ?></td>
                                        <td><?= $nama ?></td>
                                        <td><?= $username ?></td>
                                        <td><?= $email ?></td>
                                        <td>
                                            <div class="password-container">
                                                <span class="password-mask" id="password-mask-<?= $id_user ?>"><?= $password_mask ?></span>
                                                <button type="button" 
                                                        class="password-toggle" 
                                                        onclick="togglePasswordVisibility('<?= $id_user ?>', '<?= $password ?>')"
                                                        title="Tampilkan Password">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn btn-warning btn-sm btn-edit"
                                                        data-id="<?= $id_user ?>"
                                                        data-role="<?= $role ?>"
                                                        data-nama="<?= $nama ?>"
                                                        data-username="<?= $username ?>"
                                                        data-email="<?= $email ?>"
                                                        data-password="<?= $password ?>"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-hapus"
                                                        data-id="<?= $id_user ?>"
                                                        data-nama="<?= $nama ?>"
                                                        data-role="<?= $role ?>"
                                                        title="Hapus User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                        // Update nomor urut berdasarkan sorting
                                        if ($sort_order === 'desc') {
                                            $start_number--; // Untuk descending: turun
                                        } else {
                                            $start_number++; // Untuk ascending: naik
                                        }
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center text-muted">';
                                    if (!empty($search_query)) {
                                        echo 'Tidak ada data user yang sesuai dengan pencarian "' . htmlspecialchars($search_query) . '"';
                                    } else {
                                        echo 'Tidak ada data user ditemukan.';
                                    }
                                    echo '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination - SELALU TAMPIL JIKA ADA DATA -->
                    <?php if ($total_entries > 0): ?>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-0">
                                Menampilkan <?= $total_entries > 0 ? ($offset + 1) : 0 ?> 
                                sampai <?= min($offset + $entries_per_page, $total_entries) ?> 
                                dari <?= $total_entries ?> entri
                                <?php if (!empty($search_query)): ?>
                                <span class="text-info">(hasil pencarian)</span>
                                <?php endif; ?>
                                <?php if ($sort_order === 'desc'): ?>
                                <span class="text-warning">(diurutkan dari terbaru)</span>
                                <?php else: ?>
                                <span class="text-warning">(diurutkan dari terlama)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <!-- Previous Page -->
                                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page > 1 ? getPaginationUrl($current_page - 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                            Sebelumnya
                                        </a>
                                    </li>
                                    
                                    <!-- Page Numbers -->
                                    <?php
                                    echo '<li class="page-item ' . ($current_page == 1 ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="' . getPaginationUrl(1, $entries_per_page, $search_query, $sort_order) . '">1</a>';
                                    echo '</li>';
                                    
                                    $start = 2;
                                    $end = min(5, $total_pages - 1);
                                    
                                    if ($current_page > 3) {
                                        $start = $current_page - 1;
                                        $end = min($current_page + 2, $total_pages - 1);
                                    }
                                    
                                    if ($start > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    for ($i = $start; $i <= $end; $i++) {
                                        if ($i < $total_pages) {
                                            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="' . getPaginationUrl($i, $entries_per_page, $search_query, $sort_order) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                    }
                                    
                                    if ($end < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    if ($total_pages > 1) {
                                        echo '<li class="page-item ' . ($current_page == $total_pages ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl($total_pages, $entries_per_page, $search_query, $sort_order) . '">' . $total_pages . '</a>';
                                        echo '</li>';
                                    }
                                    ?>
                                    
                                    <!-- Next Page -->
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $current_page < $total_pages ? getPaginationUrl($current_page + 1, $entries_per_page, $search_query, $sort_order) : '#' ?>">
                                            Selanjutnya
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <?php else: ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Sebelumnya</a>
                                    </li>
                                    <li class="page-item active">
                                        <a class="page-link" href="#">1</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Selanjutnya</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
      </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Tambah User Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="data-user.php" id="tambahUserForm" enctype="multipart/form-data">
                    <input type="hidden" name="tambah_user" value="1">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Kode dokter/staff akan di-generate otomatis melanjutkan dari kode terakhir.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required onchange="toggleRoleForm()">
                                        <option value="">Pilih Role</option>
                                        <option value="Dokter">Dokter</option>
                                        <option value="Staff">Staff</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required 
                                           placeholder="Masukkan nama lengkap">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required 
                                           placeholder="Masukkan username" minlength="3">
                                    <div id="username-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Username minimal 3 karakter
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required 
                                           placeholder="Masukkan email">
                                    <div id="email-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Masukkan email yang valid
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required 
                                               placeholder="Masukkan password">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Dokter -->
                        <div id="formDokter" style="display:none;">
                            <hr>
                            <h5 class="mb-3"><i class="fas fa-calendar-check me-2"></i>Detail Data Dokter</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="kode_dokter" class="form-label">Kode Dokter <span class="text-danger">*</span></label>
                                        <div class="kode-input-container">
                                            <input type="text" class="form-control" id="kode_dokter" name="kode_dokter" required 
                                                   value="<?= $defaultDokterCode ?>"
                                                   placeholder="Masukkan kode dokter"
                                                   pattern="^DRM\d{3}$"
                                                   title="Format kode: DRM diikuti 3 angka (contoh: DRM001)">
                                            <span class="kode-auto-badge" id="kodeDokterBadge">OTOMATIS</span>
                                        </div>
                                        <div class="form-text info-auto" id="kodeDokterInfo">
                                            <i class="fas fa-info-circle"></i> Format: DRM diikuti 3 angka
                                            <?php if ($lastDokterCode): ?>
                                                <br>Kode terakhir: <strong><?= $lastDokterCode ?></strong> → Kode baru: <strong><?= $defaultDokterCode ?></strong>
                                            <?php else: ?>
                                                <br>Data pertama: Kode mulai dari <strong><?= $defaultDokterCode ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="subspesialisasi" class="form-label">Subspesialisasi Dokter <span class="text-danger">*</span></label>
                                        <select class="form-select" id="subspesialisasi" name="subspesialisasi" required>
                                            <option value="">Pilih Subspesialisasi</option>
                                            <option value="Vitreo-Retina">Vitreo-Retina</option>
                                            <option value="Glaukoma">Glaukoma</option>
                                            <option value="Katarak & Bedah Refraktif">Katarak & Bedah Refraktif</option>
                                            <option value="Kornea dan Bedah Refraktif">Kornea dan Bedah Refraktif</option>
                                            <option value="Mata Anak (Pediatric Ophthalmology)">Mata Anak (Pediatric Ophthalmology)</option>
                                            <option value="Okuploplastik (Plastik Rekonstruksi)">Okuploplastik (Plastik Rekonstruksi)</option>
                                            <option value="Infeksi dan Imunologi">Infeksi dan Imunologi</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="foto_dokter" class="form-label">Foto Dokter</label>
                                        <input type="file" class="form-control" id="foto_dokter" name="foto_dokter" accept="image/*" onchange="previewImage(this, 'dokter')">
                                        <div class="form-text">Format: JPG, PNG, WEBP. Max: 2MB (Opsional)</div>
                                        <div class="preview-container mt-3">
                                            <div class="foto-preview-placeholder" id="placeholderDokter">
                                                <i class="fas fa-user-md"></i>
                                            </div>
                                            <img class="foto-preview" id="previewDokter" alt="Preview Foto Dokter">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telepon_dokter" class="form-label">Telepon Dokter <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="telepon_dokter" name="telepon_dokter" required 
                                            placeholder="Masukkan telepon dokter"
                                            pattern="[0-9]*"
                                            minlength="10"
                                            maxlength="13"
                                            oninput="validatePhone(this, 'dokter')">
                                        <div class="invalid-feedback" id="errorTeleponDokter">
                                            Hanya boleh memasukkan angka (10-13 digit)
                                        </div>
                                        <div class="valid-feedback" id="successTeleponDokter" style="display:none;">
                                            Nomor telepon valid
                                        </div>
                                        <div class="form-text">Contoh: 081234567890 (10-13 digit angka)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tanggal_lahir_dokter" class="form-label">Tanggal Lahir Dokter <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="tanggal_lahir_dokter" name="tanggal_lahir_dokter" required 
                                            placeholder="Masukkan tanggal lahir dokter"
                                            max="<?= date('Y-m-d', strtotime('-1 year')) ?>"
                                            onchange="validateBirthDate(this, 'dokter')">
                                        <div class="invalid-feedback" id="errorTanggalLahirDokter">
                                            Tanggal lahir tidak boleh menggunakan tahun saat ini
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenis_kelamin_dokter" class="form-label">Jenis Kelamin Dokter <span class="text-danger">*</span></label>
                                        <select class="form-select" id="jenis_kelamin_dokter" name="jenis_kelamin_dokter" required>
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="L">Laki - laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="alamat_dokter" class="form-label">Alamat Dokter <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="alamat_dokter" name="alamat_dokter" required rows="3" placeholder="Masukkan alamat dokter"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Staff -->
                        <div id="formStaff" style="display:none;">
                            <hr>
                            <h5 class="mb-3"><i class="fas fa-calendar-check me-2"></i>Detail Data Staff</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="kode_staff" class="form-label">Kode Staff <span class="text-danger">*</span></label>
                                        <div class="kode-input-container">
                                            <input type="text" class="form-control" id="kode_staff" name="kode_staff" required 
                                                   value="<?= $defaultStaffCode ?>"
                                                   placeholder="Masukkan kode staff"
                                                   pattern="^STF\d{3}$"
                                                   title="Format kode: STF diikuti 3 angka (contoh: STF001)">
                                            <span class="kode-auto-badge" id="kodeStaffBadge">OTOMATIS</span>
                                        </div>
                                        <div class="form-text info-auto" id="kodeStaffInfo">
                                            <i class="fas fa-info-circle"></i> Format: STF diikuti 3 angka
                                            <?php if ($lastStaffCode): ?>
                                                <br>Kode terakhir: <strong><?= $lastStaffCode ?></strong> → Kode baru: <strong><?= $defaultStaffCode ?></strong>
                                            <?php else: ?>
                                                <br>Data pertama: Kode mulai dari <strong><?= $defaultStaffCode ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jabatan_staff" class="form-label">Jabatan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="jabatan_staff" name="jabatan_staff" required>
                                            <option value="">Pilih Jabatan Staff</option>
                                            <option value="Perawat Spesialis Mata">Perawat Spesialis Mata</option>
                                            <option value="Refaksionis/Optometris">Refaksionis/Optometris</option>
                                            <option value="Teknisi Alat Kesehatan">Teknisi Alat Kesehatan</option>
                                            <option value="Medical Record">Medical Record</option>
                                            <option value="IT Support">IT Support</option>
                                            <option value="Kasir & Billing">Kasir & Billing</option>
                                            <option value="Administrasi">Administrasi</option>
                                            <option value="Cleaning Service">Cleaning Service</option>
                                            <option value="Security">Security</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="foto_staff" class="form-label">Foto Staff</label>
                                        <input type="file" class="form-control" id="foto_staff" name="foto_staff" accept="image/*" onchange="previewImage(this, 'staff')">
                                        <div class="form-text">Format: JPG, PNG, WEBP. Max: 2MB (Opsional)</div>
                                        <div class="preview-container mt-3">
                                            <div class="foto-preview-placeholder" id="placeholderStaff">
                                                <i class="fas fa-user-tie"></i>
                                            </div>
                                            <img class="foto-preview" id="previewStaff" alt="Preview Foto Staff">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telepon_staff" class="form-label">Telepon Staff <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="telepon_staff" name="telepon_staff" required 
                                            placeholder="Masukkan telepon staff"
                                            pattern="[0-9]*"
                                            minlength="10"
                                            maxlength="13"
                                            oninput="validatePhone(this, 'staff')">
                                        <div class="invalid-feedback" id="errorTeleponStaff">
                                            Hanya boleh memasukkan angka (10-13 digit)
                                        </div>
                                        <div class="valid-feedback" id="successTeleponStaff" style="display:none;">
                                            Nomor telepon valid
                                        </div>
                                        <div class="form-text">Contoh: 081234567890 (10-13 digit angka)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tanggal_lahir_staff" class="form-label">Tanggal Lahir Staff <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="tanggal_lahir_staff" name="tanggal_lahir_staff" required 
                                            placeholder="Masukkan tanggal lahir staff"
                                            max="<?= date('Y-m-d', strtotime('-1 year')) ?>"
                                            onchange="validateBirthDate(this, 'staff')">
                                        <div class="invalid-feedback" id="errorTanggalLahirStaff">
                                            Tanggal lahir tidak boleh menggunakan tahun saat ini
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenis_kelamin_staff" class="form-label">Jenis Kelamin Staff <span class="text-danger">*</span></label>
                                        <select class="form-select" id="jenis_kelamin_staff" name="jenis_kelamin_staff" required>
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="L">Laki - laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="alamat_staff" class="form-label">Alamat Staff <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="alamat_staff" name="alamat_staff" required rows="3" placeholder="Masukkan alamat staff"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnTambahUser">
                            <i class="fas fa-save me-1"></i>Simpan User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="data-user.php" id="editUserForm">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" id="edit_id_user" name="id_user">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_role" name="role" required>
                                        <option value="">Pilih Role</option>
                                        <option value="Dokter">Dokter</option>
                                        <option value="Staff">Staff</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" required 
                                           placeholder="Masukkan nama lengkap">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_username" name="username" required 
                                           placeholder="Masukkan username" minlength="3">
                                    <div id="edit-username-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Username minimal 3 karakter
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required 
                                           placeholder="Masukkan email">
                                    <div id="edit-email-feedback" class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Masukkan email yang valid
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="edit_password" name="password" 
                                               placeholder="Masukkan password baru">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('edit_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnUpdateUser">
                            <i class="fas fa-save me-1"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus User -->
    <div class="modal fade" id="hapusModal" tabindex="-1" aria-labelledby="hapusModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    </div>
                    
                    <p class="text-center mt-3">Apakah Anda yakin ingin menghapus user:</p>
                    <h5 class="text-center text-danger" id="namaUserHapus"></h5>
                    <p class="text-center text-muted" id="userRoleHapus"></p>
                    <p class="text-center text-muted mt-3">
                        <small>Data yang dihapus tidak dapat dikembalikan.</small>
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>PERINGATAN:</strong> Data user akan dihapus beserta data detailnya (data_dokter/data_staff) dan foto yang terkait!
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <a href="#" id="hapusButton" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Ya, Hapus Semua
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>

    <script>
    // Function untuk mengubah jumlah entri per halaman
    function changeEntries() {
        const entries = document.getElementById('entriesPerPage').value;
        const search = '<?= $search_query ?>';
        const sort = '<?= $sort_order ?>';
        let url = 'data-user.php?entries=' + entries + '&page=1&sort=' + sort;
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }

    // Fungsi untuk cek duplikat dengan AJAX
    function checkDuplicate(type, value, userId = null, callback) {
        if (!value || (type === 'username' && value.length < 3) || (type === 'email' && value.length < 5)) {
            if (callback) callback(false);
            return;
        }
        
        let url = 'data-user.php?ajax_check=1&type=' + type + '&value=' + encodeURIComponent(value);
        if (userId) {
            url += '&user_id=' + userId;
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response.exists);
            },
            error: function() {
                if (callback) callback(false);
            }
        });
    }

    // Validasi username real-time (tambah)
    $('#username').on('input', function() {
        var username = $(this).val();
        var userId = null;
        
        if (username.length < 3) {
            $('#username').removeClass('is-valid is-invalid');
            $('#username-feedback').html('<i class="fas fa-info-circle"></i> Username minimal 3 karakter').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        checkDuplicate('username', username, userId, function(exists) {
            if (exists) {
                $('#username').addClass('is-invalid').removeClass('is-valid');
                $('#username-feedback').html('<i class="fas fa-times-circle"></i> Username "' + username + '" sudah digunakan. Silakan gunakan username lain.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#username').addClass('is-valid').removeClass('is-invalid');
                $('#username-feedback').html('<i class="fas fa-check-circle"></i> Username tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });

    // Validasi email real-time (tambah)
    $('#email').on('input', function() {
        var email = $(this).val();
        var userId = null;
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email.length < 5 || !emailPattern.test(email)) {
            $('#email').removeClass('is-valid is-invalid');
            $('#email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        checkDuplicate('email', email, userId, function(exists) {
            if (exists) {
                $('#email').addClass('is-invalid').removeClass('is-valid');
                $('#email-feedback').html('<i class="fas fa-times-circle"></i> Email "' + email + '" sudah terdaftar. Silakan gunakan email lain.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#email').addClass('is-valid').removeClass('is-invalid');
                $('#email-feedback').html('<i class="fas fa-check-circle"></i> Email tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });

    // Validasi username real-time (edit)
    $('#edit_username').on('input', function() {
        var username = $(this).val();
        var userId = $('#edit_id_user').val();
        
        if (username.length < 3) {
            $('#edit_username').removeClass('is-valid is-invalid');
            $('#edit-username-feedback').html('<i class="fas fa-info-circle"></i> Username minimal 3 karakter').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        checkDuplicate('username', username, userId, function(exists) {
            if (exists) {
                $('#edit_username').addClass('is-invalid').removeClass('is-valid');
                $('#edit-username-feedback').html('<i class="fas fa-times-circle"></i> Username "' + username + '" sudah digunakan.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#edit_username').addClass('is-valid').removeClass('is-invalid');
                $('#edit-username-feedback').html('<i class="fas fa-check-circle"></i> Username tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });

    // Validasi email real-time (edit)
    $('#edit_email').on('input', function() {
        var email = $(this).val();
        var userId = $('#edit_id_user').val();
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email.length < 5 || !emailPattern.test(email)) {
            $('#edit_email').removeClass('is-valid is-invalid');
            $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            return;
        }
        
        checkDuplicate('email', email, userId, function(exists) {
            if (exists) {
                $('#edit_email').addClass('is-invalid').removeClass('is-valid');
                $('#edit-email-feedback').html('<i class="fas fa-times-circle"></i> Email "' + email + '" sudah terdaftar.').removeClass('text-success text-muted').addClass('text-danger');
            } else {
                $('#edit_email').addClass('is-valid').removeClass('is-invalid');
                $('#edit-email-feedback').html('<i class="fas fa-check-circle"></i> Email tersedia').removeClass('text-danger text-muted').addClass('text-success');
            }
        });
    });

    // Validasi telepon dokter real-time
    $('#telepon_dokter').on('input', function() {
        var phone = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(phone);
        var userId = null;
        
        if (phone.length < 10 || phone.length > 13) {
            $('#errorTeleponDokter').show();
            $('#successTeleponDokter').hide();
            $('#telepon_dokter').addClass('is-invalid').removeClass('is-valid');
            return;
        }
        
        checkDuplicate('phone_dokter', phone, userId, function(exists) {
            if (exists) {
                $('#errorTeleponDokter').html('<i class="fas fa-times-circle"></i> Nomor telepon "' + phone + '" sudah terdaftar untuk dokter lain.').show();
                $('#successTeleponDokter').hide();
                $('#telepon_dokter').addClass('is-invalid').removeClass('is-valid');
            } else {
                $('#errorTeleponDokter').hide();
                $('#successTeleponDokter').html('<i class="fas fa-check-circle"></i> Nomor telepon tersedia').show();
                $('#telepon_dokter').addClass('is-valid').removeClass('is-invalid');
            }
        });
    });

    // Validasi telepon staff real-time
    $('#telepon_staff').on('input', function() {
        var phone = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(phone);
        var userId = null;
        
        if (phone.length < 10 || phone.length > 13) {
            $('#errorTeleponStaff').show();
            $('#successTeleponStaff').hide();
            $('#telepon_staff').addClass('is-invalid').removeClass('is-valid');
            return;
        }
        
        checkDuplicate('phone_staff', phone, userId, function(exists) {
            if (exists) {
                $('#errorTeleponStaff').html('<i class="fas fa-times-circle"></i> Nomor telepon "' + phone + '" sudah terdaftar untuk staff lain.').show();
                $('#successTeleponStaff').hide();
                $('#telepon_staff').addClass('is-invalid').removeClass('is-valid');
            } else {
                $('#errorTeleponStaff').hide();
                $('#successTeleponStaff').html('<i class="fas fa-check-circle"></i> Nomor telepon tersedia').show();
                $('#telepon_staff').addClass('is-valid').removeClass('is-invalid');
            }
        });
    });

    // Function untuk toggle role form
    function toggleRoleForm() {
        const role = document.getElementById('role').value;
        const formDokter = document.getElementById('formDokter');
        const formStaff = document.getElementById('formStaff');
        
        formDokter.style.display = role === 'Dokter' ? 'block' : 'none';
        formStaff.style.display = role === 'Staff' ? 'block' : 'none';
        
        // Toggle required attribute
        toggleRequired(formDokter, role === 'Dokter');
        toggleRequired(formStaff, role === 'Staff');
        
        // Jika role Dokter dipilih, set kode otomatis
        if (role === 'Dokter') {
            setAutoKodeDokter();
        }
        
        // Jika role Staff dipilih, set kode otomatis
        if (role === 'Staff') {
            setAutoKodeStaff();
        }
    }

    function toggleRequired(container, enable) {
        const inputs = container.querySelectorAll('input, select, textarea');
        inputs.forEach(el => {
            if (enable) {
                if (el.id !== 'foto_dokter' && el.id !== 'foto_staff') {
                    el.setAttribute('required', 'required');
                }
                el.removeAttribute('disabled');
            } else {
                el.removeAttribute('required');
                el.setAttribute('disabled', 'disabled');
            }
        });
    }

    // Function untuk set kode dokter otomatis
    function setAutoKodeDokter() {
        const kodeDokterInput = document.getElementById('kode_dokter');
        if (kodeDokterInput) {
            const lastCode = '<?= $lastDokterCode ?>';
            const defaultCode = '<?= $defaultDokterCode ?>';
            const infoText = document.getElementById('kodeDokterInfo');
            const badge = document.getElementById('kodeDokterBadge');
            
            // Set nilai default jika kosong atau masih default
            if (kodeDokterInput.value === '' || kodeDokterInput.value === defaultCode) {
                kodeDokterInput.value = defaultCode;
                if (infoText) {
                    infoText.innerHTML = `<i class="fas fa-info-circle"></i> Format: DRM diikuti 3 angka`;
                    if (lastCode) {
                        infoText.innerHTML += `<br>Kode terakhir: <strong>${lastCode}</strong> → Kode baru: <strong>${defaultCode}</strong>`;
                    } else {
                        infoText.innerHTML += `<br>Data pertama: Kode mulai dari <strong>${defaultCode}</strong>`;
                    }
                    infoText.className = 'form-text info-auto';
                }
                if (badge) {
                    badge.style.display = 'inline-block';
                    badge.className = 'kode-auto-badge';
                    badge.textContent = 'OTOMATIS';
                }
            }
        }
    }

    // Function untuk set kode staff otomatis
    function setAutoKodeStaff() {
        const kodeStaffInput = document.getElementById('kode_staff');
        if (kodeStaffInput) {
            const lastCode = '<?= $lastStaffCode ?>';
            const defaultCode = '<?= $defaultStaffCode ?>';
            const infoText = document.getElementById('kodeStaffInfo');
            const badge = document.getElementById('kodeStaffBadge');
            
            // Set nilai default jika kosong atau masih default
            if (kodeStaffInput.value === '' || kodeStaffInput.value === defaultCode) {
                kodeStaffInput.value = defaultCode;
                if (infoText) {
                    infoText.innerHTML = `<i class="fas fa-info-circle"></i> Format: STF diikuti 3 angka`;
                    if (lastCode) {
                        infoText.innerHTML += `<br>Kode terakhir: <strong>${lastCode}</strong> → Kode baru: <strong>${defaultCode}</strong>`;
                    } else {
                        infoText.innerHTML += `<br>Data pertama: Kode mulai dari <strong>${defaultCode}</strong>`;
                    }
                    infoText.className = 'form-text info-auto';
                }
                if (badge) {
                    badge.style.display = 'inline-block';
                    badge.className = 'kode-auto-badge';
                    badge.textContent = 'OTOMATIS';
                }
            }
        }
    }

    // Function untuk toggle password visibility di form
    function togglePassword(fieldId) {
        const passwordInput = document.getElementById(fieldId);
        const toggleButton = event.currentTarget;
        const icon = toggleButton.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Function untuk toggle password visibility di tabel
    function togglePasswordVisibility(userId, realPassword) {
        const passwordMask = document.getElementById('password-mask-' + userId);
        const toggleButton = event.currentTarget;
        const icon = toggleButton.querySelector('i');
        
        if (passwordMask.textContent.includes('•')) {
            // Tampilkan password asli
            passwordMask.textContent = realPassword;
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            toggleButton.title = "Sembunyikan Password";
            
            // Auto hide setelah 5 detik
            setTimeout(() => {
                if (passwordMask.textContent === realPassword) {
                    const passwordLength = realPassword.length;
                    passwordMask.textContent = '•'.repeat(passwordLength);
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    toggleButton.title = "Tampilkan Password";
                }
            }, 5000);
        } else {
            // Sembunyikan password
            const passwordLength = realPassword.length;
            passwordMask.textContent = '•'.repeat(passwordLength);
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            toggleButton.title = "Tampilkan Password";
        }
    }

    // Function untuk preview image
    function previewImage(input, type) {
        const preview = document.getElementById('preview' + type.charAt(0).toUpperCase() + type.slice(1));
        const placeholder = document.getElementById('placeholder' + type.charAt(0).toUpperCase() + type.slice(1));
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            // Validasi ukuran file (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB');
                input.value = '';
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
                return;
            }
            
            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format file harus JPG, PNG, atau WEBP');
                input.value = '';
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
                return;
            }
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            placeholder.style.display = 'flex';
        }
    }

    // Function untuk validasi telepon
    function validatePhone(input, type) {
        const errorElement = document.getElementById('errorTelepon' + type.charAt(0).toUpperCase() + type.slice(1));
        const successElement = document.getElementById('successTelepon' + type.charAt(0).toUpperCase() + type.slice(1));
        
        // Hapus karakter non-angka
        input.value = input.value.replace(/[^0-9]/g, '');
        
        // Validasi panjang
        if (input.value.length < 10 || input.value.length > 13) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            if (errorElement) errorElement.style.display = 'block';
            if (successElement) successElement.style.display = 'none';
            return false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            if (errorElement) errorElement.style.display = 'none';
            if (successElement) successElement.style.display = 'block';
            return true;
        }
    }

    // Function untuk validasi tanggal lahir
    function validateBirthDate(input, type) {
        if (input.value) {
            const birthYear = new Date(input.value).getFullYear();
            const currentYear = new Date().getFullYear();
            const errorElement = document.getElementById('errorTanggalLahir' + type.charAt(0).toUpperCase() + type.slice(1));
            
            if (birthYear === currentYear) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                if (errorElement) errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                if (errorElement) errorElement.style.display = 'none';
                return true;
            }
        }
        return true;
    }

    // Function untuk menampilkan modal hapus
    function showHapusModal(id, nama, role) {
        document.getElementById('namaUserHapus').textContent = nama;
        document.getElementById('userRoleHapus').textContent = `Role: ${role}`;
        document.getElementById('hapusButton').href = 'data-user.php?hapus=' + id;
        
        // Tampilkan modal
        const hapusModal = new bootstrap.Modal(document.getElementById('hapusModal'));
        hapusModal.show();
    }

    // Function untuk menampilkan modal edit
    function showEditModal(id, role, nama, username, email, password) {
        // Isi form dengan data yang ada
        document.getElementById('edit_id_user').value = id;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_nama_lengkap').value = nama;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_password').value = ''; // Kosongkan password untuk keamanan
        
        // Reset validasi feedback
        $('#edit_username').removeClass('is-valid is-invalid');
        $('#edit_email').removeClass('is-valid is-invalid');
        $('#edit-username-feedback').html('<i class="fas fa-info-circle"></i> Username minimal 3 karakter').removeClass('text-success text-danger').addClass('text-muted');
        $('#edit-email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
        
        // Tampilkan modal
        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
    }

    // Function untuk handle form submit
    function handleFormSubmit(e, buttonId) {
        e.preventDefault();

        const form = e.target.closest('form');
        if (!form) return;

        // Validasi telepon dan tanggal lahir
        const role = document.getElementById('role').value;
        let isValid = true;
        
        if (role === 'Dokter') {
            const teleponDokter = document.getElementById('telepon_dokter');
            const tanggalLahirDokter = document.getElementById('tanggal_lahir_dokter');
            
            if (!validatePhone(teleponDokter, 'dokter')) {
                teleponDokter.focus();
                isValid = false;
            }
            
            if (!validateBirthDate(tanggalLahirDokter, 'dokter')) {
                tanggalLahirDokter.focus();
                isValid = false;
            }
        } else if (role === 'Staff') {
            const teleponStaff = document.getElementById('telepon_staff');
            const tanggalLahirStaff = document.getElementById('tanggal_lahir_staff');
            
            if (!validatePhone(teleponStaff, 'staff')) {
                teleponStaff.focus();
                isValid = false;
            }
            
            if (!validateBirthDate(tanggalLahirStaff, 'staff')) {
                tanggalLahirStaff.focus();
                isValid = false;
            }
        }
        
        // Cek apakah username valid (tidak duplikat)
        const username = $('#username').val();
        const usernameIsValid = $('#username').hasClass('is-valid');
        if (username && username.length >= 3 && !usernameIsValid) {
            $('#username').focus();
            isValid = false;
        }
        
        // Cek apakah email valid (tidak duplikat)
        const email = $('#email').val();
        const emailIsValid = $('#email').hasClass('is-valid');
        if (email && email.length >= 5 && !emailIsValid) {
            $('#email').focus();
            isValid = false;
        }
        
        // Jika validasi gagal, tampilkan pesan dan hentikan proses
        if (!isValid) {
            const submitButton = document.getElementById(buttonId);
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Perbaiki data yang salah';
            submitButton.disabled = false;
            setTimeout(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }, 2000);
            return false;
        }

        const submitButton = document.getElementById(buttonId);
        const originalText = submitButton.innerHTML;

        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
        submitButton.disabled = true;

        setTimeout(() => {
            form.submit();
        }, 300);
    }

    // Setup modal dengan event delegation
    document.addEventListener('DOMContentLoaded', function() {
        // Event delegation untuk tombol hapus
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-hapus')) {
                e.preventDefault();
                const button = e.target.closest('.btn-hapus');
                const id = button.getAttribute('data-id');
                const nama = button.getAttribute('data-nama');
                const role = button.getAttribute('data-role');
                showHapusModal(id, nama, role);
            }
        });

        // Event delegation untuk tombol edit
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit')) {
                e.preventDefault();
                const button = e.target.closest('.btn-edit');
                const id = button.getAttribute('data-id');
                const role = button.getAttribute('data-role');
                const nama = button.getAttribute('data-nama');
                const username = button.getAttribute('data-username');
                const email = button.getAttribute('data-email');
                const password = button.getAttribute('data-password');
                showEditModal(id, role, nama, username, email, password);
            }
        });

        // Event listener untuk form tambah
        const tambahForm = document.getElementById('tambahUserForm');
        if (tambahForm) {
            tambahForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnTambahUser');
            });
        }

        // Event listener untuk form edit
        const editForm = document.getElementById('editUserForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                handleFormSubmit(e, 'btnUpdateUser');
            });
        }

        // Setup listeners untuk kode input
        const kodeDokterInput = document.getElementById('kode_dokter');
        const kodeStaffInput = document.getElementById('kode_staff');
        
        if (kodeDokterInput) {
            const defaultDokterCode = '<?= $defaultDokterCode ?>';
            const infoText = document.getElementById('kodeDokterInfo');
            const badge = document.getElementById('kodeDokterBadge');
            
            kodeDokterInput.addEventListener('input', function() {
                const currentValue = this.value.trim();
                
                if (currentValue === defaultDokterCode || currentValue === '') {
                    if (infoText) {
                        infoText.className = 'form-text info-auto';
                        const lastCode = '<?= $lastDokterCode ?>';
                        infoText.innerHTML = `<i class="fas fa-info-circle"></i> Format: DRM diikuti 3 angka`;
                        if (lastCode) {
                            infoText.innerHTML += `<br>Kode terakhir: <strong>${lastCode}</strong> → Kode baru: <strong>${defaultDokterCode}</strong>`;
                        } else {
                            infoText.innerHTML += `<br>Data pertama: Kode mulai dari <strong>${defaultDokterCode}</strong>`;
                        }
                    }
                    if (badge) {
                        badge.style.display = 'inline-block';
                        badge.className = 'kode-auto-badge';
                        badge.textContent = 'OTOMATIS';
                    }
                } else {
                    if (infoText) {
                        infoText.className = 'form-text info-manual';
                        infoText.innerHTML = `<i class="fas fa-edit"></i> Kode diubah manual`;
                    }
                    if (badge) {
                        badge.style.display = 'inline-block';
                        badge.className = 'kode-manual-badge';
                        badge.textContent = 'MANUAL';
                    }
                }
            });
            
            kodeDokterInput.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.value = defaultDokterCode;
                    setAutoKodeDokter();
                }
            });
        }
        
        if (kodeStaffInput) {
            const defaultStaffCode = '<?= $defaultStaffCode ?>';
            const infoText = document.getElementById('kodeStaffInfo');
            const badge = document.getElementById('kodeStaffBadge');
            
            kodeStaffInput.addEventListener('input', function() {
                const currentValue = this.value.trim();
                
                if (currentValue === defaultStaffCode || currentValue === '') {
                    if (infoText) {
                        infoText.className = 'form-text info-auto';
                        const lastCode = '<?= $lastStaffCode ?>';
                        infoText.innerHTML = `<i class="fas fa-info-circle"></i> Format: STF diikuti 3 angka`;
                        if (lastCode) {
                            infoText.innerHTML += `<br>Kode terakhir: <strong>${lastCode}</strong> → Kode baru: <strong>${defaultStaffCode}</strong>`;
                        } else {
                            infoText.innerHTML += `<br>Data pertama: Kode mulai dari <strong>${defaultStaffCode}</strong>`;
                        }
                    }
                    if (badge) {
                        badge.style.display = 'inline-block';
                        badge.className = 'kode-auto-badge';
                        badge.textContent = 'OTOMATIS';
                    }
                } else {
                    if (infoText) {
                        infoText.className = 'form-text info-manual';
                        infoText.innerHTML = `<i class="fas fa-edit"></i> Kode diubah manual`;
                    }
                    if (badge) {
                        badge.style.display = 'inline-block';
                        badge.className = 'kode-manual-badge';
                        badge.textContent = 'MANUAL';
                    }
                }
            });
            
            kodeStaffInput.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.value = defaultStaffCode;
                    setAutoKodeStaff();
                }
            });
        }

        // Auto focus pada input search
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && '<?= $search_query ?>') {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }

        // Reset form modal ketika ditutup
        const tambahUserModal = document.getElementById('tambahUserModal');
        if (tambahUserModal) {
            tambahUserModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('tambahUserForm').reset();
                const submitButton = document.getElementById('btnTambahUser');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Simpan User';
                submitButton.disabled = false;
                // Reset form tambahan
                document.getElementById('formDokter').style.display = 'none';
                document.getElementById('formStaff').style.display = 'none';
                
                // Reset kode ke default
                const kodeDokterInput = document.getElementById('kode_dokter');
                const kodeStaffInput = document.getElementById('kode_staff');
                if (kodeDokterInput) kodeDokterInput.value = '<?= $defaultDokterCode ?>';
                if (kodeStaffInput) kodeStaffInput.value = '<?= $defaultStaffCode ?>';
                
                // Reset preview foto
                const previewDokter = document.getElementById('previewDokter');
                const placeholderDokter = document.getElementById('placeholderDokter');
                const previewStaff = document.getElementById('previewStaff');
                const placeholderStaff = document.getElementById('placeholderStaff');
                
                if (previewDokter) previewDokter.style.display = 'none';
                if (placeholderDokter) placeholderDokter.style.display = 'flex';
                if (previewStaff) previewStaff.style.display = 'none';
                if (placeholderStaff) placeholderStaff.style.display = 'flex';
                
                // Reset validasi
                const teleponDokter = document.getElementById('telepon_dokter');
                const teleponStaff = document.getElementById('telepon_staff');
                const tanggalLahirDokter = document.getElementById('tanggal_lahir_dokter');
                const tanggalLahirStaff = document.getElementById('tanggal_lahir_staff');
                
                if (teleponDokter) {
                    teleponDokter.classList.remove('is-valid', 'is-invalid');
                }
                if (teleponStaff) {
                    teleponStaff.classList.remove('is-valid', 'is-invalid');
                }
                if (tanggalLahirDokter) {
                    tanggalLahirDokter.classList.remove('is-valid', 'is-invalid');
                }
                if (tanggalLahirStaff) {
                    tanggalLahirStaff.classList.remove('is-valid', 'is-invalid');
                }
                
                // Reset username dan email feedback
                $('#username').removeClass('is-valid is-invalid');
                $('#email').removeClass('is-valid is-invalid');
                $('#username-feedback').html('<i class="fas fa-info-circle"></i> Username minimal 3 karakter').removeClass('text-success text-danger').addClass('text-muted');
                $('#email-feedback').html('<i class="fas fa-info-circle"></i> Masukkan email yang valid').removeClass('text-success text-danger').addClass('text-muted');
            });
        }

        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('hidden.bs.modal', function () {
                const submitButton = document.getElementById('btnUpdateUser');
                submitButton.innerHTML = '<i class="fas fa-save me-1"></i>Update User';
                submitButton.disabled = false;
            });
        }
    });
    </script>
    
    <script>change_box_container('false');</script>
    <script>layout_caption_change('true');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
    
  </body>
  <!-- [Body] end -->
</html>

<?php
// Fungsi untuk membuat URL pagination
function getPaginationUrl($page, $entries, $search = '', $sort = 'asc') {
    $url = 'data-user.php?';
    $params = [];
    
    if ($page > 1) {
        $params[] = 'page=' . $page;
    }
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if ($sort != 'asc') {
        $params[] = 'sort=' . $sort;
    }
    
    return $url . implode('&', $params);
}

// Fungsi untuk membuat URL sorting
function getSortUrl($current_sort) {
    $url = 'data-user.php?';
    $params = [];
    
    $entries = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if ($entries != 10) {
        $params[] = 'entries=' . $entries;
    }
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    // Toggle sort order
    $new_sort = $current_sort === 'asc' ? 'desc' : 'asc';
    $params[] = 'sort=' . $new_sort;
    
    return $url . implode('&', $params);
}
?>

<!-- Include Footer -->
<?php require_once "footer.php"; ?>