<?php
/**
 * Cashflow Operational - Delete Handler
 * 
 * Handles deletion of a cashflow transaction record.
 * Requires authentication (admin only).
 * 
 * POST body: JSON { id: integer }
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

// Require authentication
require_once __DIR__ . '/../../functions.php';

if (!isset($_SESSION['user_id'])) {
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

// Get photo_path before deleting (to delete the file)
$checkSql = "SELECT photo_path FROM cashflow_transactions WHERE id = ?";
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

$row = $checkResult->fetch_assoc();
$photoPath = $row['photo_path'];
$checkStmt->close();

// Delete from database
$deleteSql = "DELETE FROM cashflow_transactions WHERE id = ?";
$deleteStmt = $mysqli->prepare($deleteSql);
$deleteStmt->bind_param("i", $id);

if (!$deleteStmt->execute()) {
    $deleteStmt->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus data: ' . $mysqli->error
    ]);
    exit;
}

$deleteStmt->close();

// Delete photo file if exists
if (!empty($photoPath) && file_exists(__DIR__ . '/../../' . $photoPath)) {
    unlink(__DIR__ . '/../../' . $photoPath);
}

echo json_encode([
    'success' => true,
    'message' => 'Data berhasil dihapus'
]);