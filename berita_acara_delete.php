<?php
// =========================================================================
// BERITA ACARA - DELETE
// =========================================================================

require_once 'functions.php';
require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash_set('error', 'ID Berita Acara tidak valid!');
    header('Location: berita_acara_list.php');
    exit;
}

$id = (int)$_GET['id'];

// Query data berita acara untuk mendapatkan nomor BA
$query = "SELECT nomor_ba FROM berita_acara WHERE id = {$id}";
$result = mysqli_query($mysqli, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    flash_set('error', 'Berita Acara tidak ditemukan!');
    header('Location: berita_acara_list.php');
    exit;
}

$row = mysqli_fetch_assoc($result);
$nomor_ba = $row['nomor_ba'];

$mysqli->begin_transaction();
$success = false;

try {
    // 1. Hapus items terlebih dahulu (jika tabel ada)
    $check_items_table = mysqli_query($mysqli, "SHOW TABLES LIKE 'berita_acara_items'");
    if (mysqli_num_rows($check_items_table) > 0) {
        $delete_items = mysqli_query($mysqli, "DELETE FROM berita_acara_items WHERE berita_acara_id = {$id}");
        if (!$delete_items) {
            throw new Exception("Gagal menghapus items: " . mysqli_error($mysqli));
        }
    }
    
    // 2. Hapus berita acara
    $delete_ba = mysqli_query($mysqli, "DELETE FROM berita_acara WHERE id = {$id}");
    if (!$delete_ba) {
        throw new Exception("Gagal menghapus berita acara: " . mysqli_error($mysqli));
    }
    
    $mysqli->commit();
    $success = true;
    
} catch (Exception $e) {
    $mysqli->rollback();
    flash_set('error', 'Error saat menghapus Berita Acara: ' . $e->getMessage());
}

if ($success) {
    flash_set('success', "Berita Acara {$nomor_ba} berhasil dihapus!");
} else {
    flash_set('error', "Gagal menghapus Berita Acara {$nomor_ba}");
}

header('Location: berita_acara_list.php');
exit;
?>