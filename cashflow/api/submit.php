<?php
/**
 * Cashflow Operational - Submit Handler
 * 
 * Handles the submission of technician cashflow data via POST request.
 * Validates required fields, processes file uploads (optional), and
 * inserts the record into the cashflow_transactions table.
 * 
 * POST fields: technician_name, category, amount, description, photo, transaction_date
 * Response: JSON { success: true/false, message: string }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Note: upload_max_filesize and post_max_size are PHP_INI_PERDIR directives
// They CANNOT be changed with ini_set() at runtime.
// They must be set in php.ini or .htaccess (see ../. htaccess)

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Detect if post_max_size was exceeded
// When post_max_size is exceeded, PHP silently drops all POST data
// $_POST and $_FILES become empty, but CONTENT_LENGTH still has the original value
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = ini_get('post_max_size');
    
    if ($contentLength > 0) {
        http_response_code(413);
        echo json_encode([
            'success' => false,
            'message' => "File terlalu besar! Ukuran upload ({$contentLength} bytes) melebihi batas server ({$postMaxSize}). Silakan kompres gambar terlebih dahulu."
        ]);
        exit;
    }
}

// Database connection
require_once __DIR__ . '/../../db.php';

// Collect and sanitize inputs
// Use isset() to properly handle FormData with file uploads
$technician_name = isset($_POST['technician_name']) ? trim($_POST['technician_name']) : '';
$category        = isset($_POST['category']) ? trim($_POST['category']) : '';
$amount          = isset($_POST['amount']) ? trim($_POST['amount']) : '';
$description     = isset($_POST['description']) ? trim($_POST['description']) : '';
$transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : '';

// Valid categories
$validCategories = ['BBM', 'Tol', 'Sparepart', 'Lainnya'];

// Validation
$errors = [];

// Validate technician_name (required, max 100 chars)
if (empty($technician_name)) {
    $errors[] = 'Nama teknisi wajib diisi';
} elseif (strlen($technician_name) > 100) {
    $errors[] = 'Nama teknisi maksimal 100 karakter';
}

// Validate category (required, must be valid enum)
if (empty($category)) {
    $errors[] = 'Kategori wajib dipilih';
} elseif (!in_array($category, $validCategories)) {
    $errors[] = 'Kategori tidak valid';
}

// Validate amount (required, must be numeric, >= 0)
if (empty($amount)) {
    $errors[] = 'Nominal wajib diisi';
} elseif (!is_numeric($amount)) {
    $errors[] = 'Nominal harus berupa angka';
} elseif (floatval($amount) <= 0) {
    $errors[] = 'Nominal harus lebih dari 0';
}

// Validate description (optional, max 500 chars)
if (!empty($description) && strlen($description) > 500) {
    $errors[] = 'Keterangan maksimal 500 karakter';
}

// Validate transaction_date (required, must be valid date)
if (empty($transaction_date)) {
    $errors[] = 'Tanggal transaksi wajib diisi';
} else {
    $date = DateTime::createFromFormat('Y-m-d', $transaction_date);
    if (!$date || $date->format('Y-m-d') !== $transaction_date) {
        $errors[] = 'Format tanggal harus YYYY-MM-DD';
    }
}

// If validation errors, return them
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validasi gagal',
        'errors'  => $errors
    ]);
    exit;
}

// Process file upload (optional)
$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Log file upload error for debugging
    $uploadError = $_FILES['photo']['error'];
    error_log("File upload error code: " . $uploadError);
    error_log("File info: " . print_r($_FILES['photo'], true));
    
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize di php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE di form)',
            UPLOAD_ERR_PARTIAL => 'File hanya ter-upload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        $errorMsg = $errorMessages[$uploadError] ?? 'Gagal mengupload file (error code: ' . $uploadError . ')';
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 25 * 1024 * 1024; // 25MB

    $file = $_FILES['photo'];

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, WEBP yang diperbolehkan'
        ]);
        exit;
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ukuran file maksimal 25MB'
        ]);
        exit;
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan file upload'
        ]);
        exit;
    }

    $photoPath = $filename;
}

// Insert into database
try {
    $sql = "INSERT INTO cashflow_transactions 
            (transaction_date, technician_name, category, amount, description, photo_path) 
            VALUES 
            (?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $amountFloat = floatval($amount);
    // Fixed bind_param: 6 parameters need 6 type characters
    // s=string, s=string, s=string, d=double, s=string, s=string
    $stmt->bind_param("sssdss", $transaction_date, $technician_name, $category, $amountFloat, $description, $photoPath);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil disimpan',
        'data' => [
            'id' => $newId,
            'technician_name' => $technician_name,
            'category' => $category,
            'amount' => $amountFloat,
            'transaction_date' => $transaction_date
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan data: ' . $e->getMessage()
    ]);
    exit;
}