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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Database connection
require_once __DIR__ . '/../../db.php';

// Collect and sanitize inputs
$technician_name = trim($_POST['technician_name'] ?? '');
$category        = trim($_POST['category'] ?? '');
$amount          = trim($_POST['amount'] ?? '');
$description     = trim($_POST['description'] ?? '');
$transaction_date = trim($_POST['transaction_date'] ?? '');

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
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengupload file'
        ]);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

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
            'message' => 'Ukuran file maksimal 5MB'
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

    $photoPath = 'cashflow/uploads/' . $filename;
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