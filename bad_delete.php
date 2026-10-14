<?php
require_once __DIR__ . '/bad_helpers.php';
require_module_access('bad', 'full');
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    $paths = [];
    $stmt = $mysqli->prepare('SELECT file_path FROM bad_photos WHERE bad_id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
    while ($row=mysqli_fetch_assoc($result)) $paths[]=$row['file_path'];
    $stmt=$mysqli->prepare('DELETE FROM bad_documents WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute();
    foreach ($paths as $path) bad_delete_file($path);
    flash_set('success', 'BAD berhasil dihapus.');
}
header('Location: bad_list.php'); exit;