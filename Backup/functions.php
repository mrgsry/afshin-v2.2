<?php
require_once __DIR__ . '/db.php';

if (!isset($mysqli)) {
    die('DB not initialized');
}
session_start();

$session_timeout = 600; // 10 menit (60 detik * 10)

// Pastikan sesi sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cek apakah sesi user sudah login
if (isset($_SESSION['user'])) {
    // 2. Cek apakah timestamp terakhir ada dan sudah kedaluwarsa
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {
        // Session telah kedaluwarsa (lebih dari 10 menit tidak aktif)
        session_unset();     // Menghapus semua variabel sesi
        session_destroy();   // Menghancurkan sesi
        // Redirect ke halaman login dengan pesan
        header("Location: login.php?expired=1"); 
        exit;
    }
    
    // 3. Perbarui timestamp aktivitas saat ini
    // Logika ini memperpanjang sesi setiap kali pengguna memuat halaman
    $_SESSION['LAST_ACTIVITY'] = time();
}
// =================================================================
// END LOGIKA SESSION TIMEOUT
// =================================================================

function is_logged_in(){
    return isset($_SESSION['user']);
}

function require_login(){
    if(!is_logged_in()){
        header('Location: login.php'); exit;
    }
}

function terbilang($angka) {
    $angka = abs($angka);
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");

    if ($angka < 12)
        return " " . $huruf[$angka];
    elseif ($angka < 20)
        return terbilang($angka - 10) . " Belas";
    elseif ($angka < 100)
        return terbilang($angka / 10) . " Puluh" . terbilang($angka % 10);
    elseif ($angka < 200)
        return " Seratus" . terbilang($angka - 100);
    elseif ($angka < 1000)
        return terbilang($angka / 100) . " Ratus" . terbilang($angka % 100);
    elseif ($angka < 2000)
        return " Seribu" . terbilang($angka - 1000);
    elseif ($angka < 1000000)
        return terbilang($angka / 1000) . " Ribu" . terbilang($angka % 1000);
    elseif ($angka < 1000000000)
        return terbilang($angka / 1000000) . " Juta" . terbilang($angka % 1000000);
    else
        return "Angka terlalu besar";
}

if (!function_exists('bulan_romawi')) {
    function bulan_romawi($bulan)
    {
        $romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $romawi[(int)$bulan] ?? '';
    }
}

function current_user(){
    return $_SESSION['user'] ?? null;
}

function flash_set($msg){
    $_SESSION['flash'] = $msg;
}

function flash_get(){
    $m = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $m;
}

function gen_customer_no($mysqli)
{
    $sql = "
        SELECT AUTO_INCREMENT 
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
    ";

    $result = mysqli_query($mysqli, $sql);

    if (!$result) {
        die('Query error: ' . mysqli_error($mysqli));
    }

    $row = mysqli_fetch_assoc($result);
    $nextId = $row['AUTO_INCREMENT'] ?? 1;

    return 'CUST-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

function gen_reference($prefix, $mysqli, $table){
    $res = mysqli_query($mysqli, "SELECT AUTO_INCREMENT FROM hnetdiih_afshin_app.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".mysqli_real_escape_string($mysqli,$table)."'");
    $row = mysqli_fetch_assoc($res);
    $next = $row ? intval($row['AUTO_INCREMENT']) : time();
    return strtoupper($prefix) . str_pad($next, 6, '0', STR_PAD_LEFT);
}
?>