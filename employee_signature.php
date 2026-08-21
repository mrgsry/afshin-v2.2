<?php

function employee_signature_directory() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'signatures';
}

function employee_signature_relative_path($filename) {
    return 'uploads/signatures/' . basename($filename);
}

function delete_employee_signature($relativePath) {
    if (!$relativePath || strpos($relativePath, 'uploads/signatures/') !== 0) return;
    $filename = basename($relativePath);
    $path = employee_signature_directory() . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) @unlink($path);
}

function save_employee_signature($employeeNo, $upload, $drawingData, $oldPath = '') {
    $source = null;
    $extension = 'png';

    if ($drawingData !== '') {
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\s]+)$/', $drawingData, $matches)) {
            throw new Exception('Format tanda tangan drawing harus berupa PNG.');
        }
        $source = base64_decode(str_replace(' ', '+', $matches[1]), true);
        if ($source === false || @getimagesizefromstring($source) === false) {
            throw new Exception('Gambar tanda tangan drawing tidak valid.');
        }
    } elseif (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new Exception('Upload tanda tangan gagal.');
        if (($upload['size'] ?? 0) > 2 * 1024 * 1024) throw new Exception('Ukuran tanda tangan maksimal 2 MB.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($upload['tmp_name']) !== 'image/png' || @getimagesize($upload['tmp_name']) === false) {
            throw new Exception('File tanda tangan harus berupa PNG yang valid.');
        }
        $source = file_get_contents($upload['tmp_name']);
    }

    if ($source === null) return $oldPath;
    $directory = employee_signature_directory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new Exception('Folder tanda tangan tidak dapat dibuat.');
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $employeeNo) . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (file_put_contents($path, $source) === false) throw new Exception('Tanda tangan gagal disimpan.');
    if ($oldPath) delete_employee_signature($oldPath);
    return employee_signature_relative_path($filename);
}
