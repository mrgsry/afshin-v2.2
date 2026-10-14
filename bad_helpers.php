<?php
require_once __DIR__ . '/functions.php';

function bad_upload_dir() { return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bad'; }
function bad_public_path($filename) { return 'uploads/bad/' . basename($filename); }

function bad_delete_file($relative_path) {
    if (!$relative_path || strpos($relative_path, 'uploads/bad/') !== 0) return;
    $path = bad_upload_dir() . DIRECTORY_SEPARATOR . basename($relative_path);
    if (is_file($path)) @unlink($path);
}

function bad_save_photo($upload) {
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload foto BAD gagal.');
    }
    if (!is_uploaded_file($upload['tmp_name'])) throw new Exception('File foto BAD tidak valid.');
    $info = @getimagesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new Exception('Foto harus berformat JPG, PNG, atau WEBP.');
    }
    $dir = bad_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new Exception('Folder foto BAD tidak dapat dibuat.');
    $filename = 'bad-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.jpg';
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $quality = 82;
    do {
        if (is_file($path)) @unlink($path);
        if (!compressImage($upload['tmp_name'], $path, $quality, 1600, 1200)) {
            throw new Exception('Foto BAD gagal diproses.');
        }
        $quality -= 8;
    } while (filesize($path) > 1024 * 1024 && $quality >= 42);
    if (filesize($path) > 1024 * 1024) {
        @unlink($path);
        throw new Exception('Foto tetap lebih dari 1 MB setelah kompresi.');
    }
    return bad_public_path($filename);
}

function bad_generate_no($mysqli) {
    $year = date('Y');
    $result = mysqli_query($mysqli, "SELECT document_no FROM bad_documents WHERE document_no LIKE '%/BAD/{$year}' ORDER BY id DESC LIMIT 1");
    $last = 0;
    if ($result && ($row = mysqli_fetch_assoc($result))) $last = (int) explode('/', $row['document_no'])[0];
    return str_pad($last + 1, 4, '0', STR_PAD_LEFT) . '/BAD/' . $year;
}

function bad_po_rows($mysqli) {
    $rows = [];
    $result = mysqli_query($mysqli, "SELECT i.id, i.customer_id, i.po_number, c.name AS customer_name, c.address AS customer_address, c.pic AS customer_pic, 'Pending' AS status_label FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id WHERE i.po_number IS NOT NULL AND i.po_number <> '' AND TRIM(COALESCE(i.faktur_inv, '')) IN ('', '-', '0', '000', 'NULL', 'null') ORDER BY i.created_at DESC");
    while ($result && ($row = mysqli_fetch_assoc($result))) $rows[] = $row;
    return $rows;
}