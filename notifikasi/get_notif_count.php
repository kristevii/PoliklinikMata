<?php
// get_notif_count.php
// Endpoint AJAX: mengembalikan jumlah aktivitas baru yang belum dibaca
// Menggunakan tabel notifikasi_dibaca — persisten lintas sesi & login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['count' => 0]);
    exit;
}

include_once(dirname(__FILE__) . '/../koneksi.php');
$db      = new database();
$conn    = $db->koneksi;
$id_user = (int) $_SESSION['id_user'];

// Ambil waktu terakhir dibaca dari tabel notifikasi_dibaca
$stmt = $conn->prepare(
    "SELECT terakhir_dibaca FROM notifikasi_dibaca WHERE id_user = ? LIMIT 1"
);
$stmt->bind_param('i', $id_user);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$terakhir_dibaca = !empty($row['terakhir_dibaca'])
    ? $row['terakhir_dibaca']
    : '1970-01-01 00:00:00';

// Hitung aktivitas setelah waktu terakhir dibaca
$stmt2 = $conn->prepare(
    "SELECT COUNT(*) AS total FROM aktivitas_user WHERE waktu > ?"
);
$stmt2->bind_param('s', $terakhir_dibaca);
$stmt2->execute();
$row2  = $stmt2->get_result()->fetch_assoc();
$count = (int) $row2['total'];
$stmt2->close();

echo json_encode(['count' => $count]);