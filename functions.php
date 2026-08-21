<?php
require_once __DIR__ . '/db.php';

/* ===============================
   START SESSION (SAFE MODE)
=================================*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===============================
   SESSION TIMEOUT CONFIG
=================================*/
$session_timeout = 600; // 10 menit

if (isset($_SESSION['user'])) {

    if (isset($_SESSION['LAST_ACTIVITY']) &&
        (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {

        session_unset();
        session_destroy();
        header("Location: login.php?expired=1");
        exit;
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}

/* ===============================
   AUTH FUNCTIONS
=================================*/
function is_logged_in(){
    return isset($_SESSION['user']);
}

function require_login(){
    if(!is_logged_in()){
        header('Location: login.php');
        exit;
    }
}

function current_user(){
    return $_SESSION['user'] ?? null;
}

function available_modules(){
    return [
        'dashboard' => 'Dashboard',
        'customer' => 'Customer',
        'employee' => 'Karyawan',
        'payslip' => 'Slip Gaji',
        'quotation' => 'Quotation',
        'invoice' => 'Invoice',
        'travel_document' => 'Travel Document / Surat Jalan',
        'service_report' => 'Service Report',
        'berita_acara' => 'Berita Acara',
        'data_po' => 'Data PO',
        'operational' => 'Operational',
        'finance' => 'Finance',
        'document_history' => 'Document History',
        'user_management' => 'User Management'
    ];
}

function user_policy($module, $user = null){
    $user = $user ?: current_user();
    if (!$user) return null;
    if (($user['role'] ?? '') === 'admin') return 'full';
    static $permissions = null;
    if ($permissions === null) {
        $permissions = [];
        $userId = (int) ($user['id'] ?? 0);
        if ($userId > 0) {
            $stmt = mysqli_prepare($GLOBALS['mysqli'], 'SELECT module_name, policy FROM user_modules WHERE user_id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) $permissions[$row['module_name']] = $row['policy'];
            }
        }
    }
    return $permissions[$module] ?? null;
}

function can_access_module($module, $requiredPolicy = 'read'){
    $policy = user_policy($module);
    return $policy === 'full' || ($requiredPolicy === 'read' && $policy === 'read');
}

function require_module_access($module, $requiredPolicy = 'read'){
    require_login();
    if (!can_access_module($module, $requiredPolicy)) {
        header('Location: access_denied.php');
        exit;
    }
}

function require_admin(){
    require_login();
    if ((current_user()['role'] ?? '') !== 'admin') {
        header('Location: access_denied.php');
        exit;
    }
}

function posted_permissions($modules){
    $result = [];
    foreach ((array) ($_POST['modules'] ?? []) as $module => $enabled) {
        if (isset($modules[$module])) {
            $policy = $_POST['policies'][$module] ?? 'read';
            $result[$module] = in_array($policy, ['full', 'read'], true) ? $policy : 'read';
        }
    }
    return $result;
}

/* ===============================
   FLASH MESSAGE
=================================*/
function flash_set($msg){
    $_SESSION['flash'] = $msg;
}

function flash_get(){
    $msg = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $msg;
}

/* ===============================
   TERBILANG FUNCTION
=================================*/
function terbilang($angka) {
    $angka = abs($angka);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima",
              "Enam", "Tujuh", "Delapan", "Sembilan",
              "Sepuluh", "Sebelas"];

    if ($angka < 12)
        return " " . $huruf[$angka];
    elseif ($angka < 20)
        return terbilang($angka - 10) . " Belas";
    elseif ($angka < 100)
        return terbilang(floor($angka / 10)) . " Puluh" . terbilang($angka % 10);
    elseif ($angka < 200)
        return " Seratus" . terbilang($angka - 100);
    elseif ($angka < 1000)
        return terbilang(floor($angka / 100)) . " Ratus" . terbilang($angka % 100);
    elseif ($angka < 2000)
        return " Seribu" . terbilang($angka - 1000);
    elseif ($angka < 1000000)
        return terbilang(floor($angka / 1000)) . " Ribu" . terbilang($angka % 1000);
    elseif ($angka < 1000000000)
        return terbilang(floor($angka / 1000000)) . " Juta" . terbilang($angka % 1000000);
    else
        return "Angka terlalu besar";
}

/* ===============================
   BULAN ROMAWI
=================================*/
if (!function_exists('bulan_romawi')) {
    function bulan_romawi($bulan){
        $romawi = [
            1=>'I',2=>'II',3=>'III',4=>'IV',
            5=>'V',6=>'VI',7=>'VII',8=>'VIII',
            9=>'IX',10=>'X',11=>'XI',12=>'XII'
        ];
        return $romawi[(int)$bulan] ?? '';
    }
}

/* ===============================
   GENERATE CUSTOMER NUMBER
=================================*/
function gen_customer_no($mysqli)
{
    $sql = "
        SELECT AUTO_INCREMENT 
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
    ";

    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    $nextId = $row['AUTO_INCREMENT'] ?? 1;

    return 'CUST-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

/* ===============================
   GENERATE REFERENCE
=================================*/
function gen_reference($prefix, $mysqli, $table){

    $table = mysqli_real_escape_string($mysqli, $table);

    $sql = "
        SELECT AUTO_INCREMENT 
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
    ";

    $res = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($res);

    $next = $row ? intval($row['AUTO_INCREMENT']) : time();

    return strtoupper($prefix) . str_pad($next, 6, '0', STR_PAD_LEFT);
}

/* ===============================
   COMPRESS IMAGE (RESIZE & OPTIMIZE)
=================================*/
/**
 * Kompres dan resize gambar jika terlalu besar
 * 
 * @param string $source Path file asli
 * @param string $destination Path untuk menyimpan file hasil kompresi
 * @param int $quality Kualitas (0-100)
 * @param int $maxWidth Maksimal lebar gambar
 * @param int $maxHeight Maksimal tinggi gambar
 * @return bool Berhasil atau tidak
 */
function compressImage($source, $destination, $quality = 75, $maxWidth = 1200, $maxHeight = 1200) {
    $info = getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    // Buat resource gambar berdasarkan mime type
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    if (!$image) return false;

    // Kalkulasi ukuran baru dengan mempertahankan rasio aspek
    $newWidth = $width;
    $newHeight = $height;

    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
    }

    // Jika ukuran berubah atau ini bukan jpeg, kita buat gambar baru
    // Kita simpan semuanya sebagai JPEG untuk kompresi terbaik
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Untuk PNG dan GIF dengan transparansi, beri background putih
    if ($mime == 'image/png' || $mime == 'image/gif') {
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);
    }

    // Resize
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Simpan gambar (selalu disimpan sebagai JPEG)
    $result = imagejpeg($newImage, $destination, $quality);

    // Bebaskan memory
    imagedestroy($image);
    imagedestroy($newImage);

    return $result;
}