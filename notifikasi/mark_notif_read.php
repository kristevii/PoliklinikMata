<?php
// mark_notif_read.php
// Endpoint AJAX: simpan waktu sekarang ke tabel notifikasi_dibaca
// INSERT jika belum ada, UPDATE jika sudah ada (UPSERT)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false]);
    exit;
}

include_once(dirname(__FILE__) . '/../koneksi.php');
$db      = new database();
$conn    = $db->koneksi;
$id_user = (int) $_SESSION['id_user'];
$now     = date('Y-m-d H:i:s');

// UPSERT: insert jika belum ada, update jika sudah ada
$stmt = $conn->prepare(
    "INSERT INTO notifikasi_dibaca (id_user, terakhir_dibaca)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE terakhir_dibaca = VALUES(terakhir_dibaca)"
);
$stmt->bind_param('is', $id_user, $now);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$stmt->close();