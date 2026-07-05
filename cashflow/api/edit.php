<?php
/**
 * Cashflow Operational - Edit Handler
 * 
 * Handles updating of a cashflow transaction record.
 * Requires authentication (admin only).
 * 
 * POST body: JSON { id: integer, technician_name: string, category: string, amount: number, description: string, transaction_date: string }
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

// Require authentication
require_once __DIR__ . '/../../functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Please login as admin'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID transaksi tidak valid'
    ]);
    exit;
}

$id = (int)$input['id'];
$technician_name = isset($input['technician_name']) ? trim($input['technician_name']) : '';
$category = isset($input['category']) ? trim($input['category']) : '';
$amount = isset($input['amount']) ? trim($input['amount']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$transaction_date = isset($input['transaction_date']) ? trim($input['transaction_date']) : '';

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

// Check if record exists
$checkSql = "SELECT id FROM cashflow_transactions WHERE id = ?";
$checkStmt = $mysqli->prepare($checkSql);
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak ditemukan'
    ]);
    exit;
}
$checkStmt->close();

// Update database
try {
    $sql = "UPDATE cashflow_transactions 
            SET transaction_date = ?, 
                technician_name = ?, 
                category = ?, 
                amount = ?, 
                description = ? 
            WHERE id = ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $amountFloat = floatval($amount);
    $stmt->bind_param("sssdsi", $transaction_date, $technician_name, $category, $amountFloat, $description, $id);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil diupdate',
        'data' => [
            'id' => $id,
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
        'message' => 'Gagal mengupdate data: ' . $e->getMessage()
    ]);
    exit;
}