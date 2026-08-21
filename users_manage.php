<?php
require_once 'functions.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    if ($id === (int) current_user()['id']) $error = 'User yang sedang login tidak dapat dihapus.';
    else {
        $oldUser = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT photo_path FROM users WHERE id = $id"));
        $stmt = mysqli_prepare($mysqli, 'DELETE FROM users WHERE id = ?'); mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) { delete_user_photo($oldUser['photo_path'] ?? ''); flash_set('User berhasil dihapus.'); } else $error = 'User gagal dihapus.';
        if ($error === '') { header('Location: users_manage.php'); exit; }
    }
}
$res = mysqli_query($mysqli, "SELECT u.*, COUNT(um.id) AS module_count FROM users u LEFT JOIN user_modules um ON um.user_id = u.id GROUP BY u.id ORDER BY u.id DESC");
$msg = flash_get();
include 'header.php';
?>
<div class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3>Daftar User</h3><a class="btn btn-primary" href="users_create.php"><i class="fas fa-plus mr-1"></i>Buat User</a></div>
<?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card"><div class="card-body table-responsive"><table class="table table-bordered table-hover"><thead class="thead-light"><tr><th>No</th><th>Foto</th><th>Username</th><th>Nama Lengkap</th><th>Job Position</th><th>Role</th><th>Modul Dipilih</th><th>Aksi</th></tr></thead><tbody><?php $no=1; while($row=mysqli_fetch_assoc($res)): ?><tr><td><?= $no++ ?></td><td><?php if (!empty($row['photo_path'])): ?><img src="<?= htmlspecialchars($row['photo_path']) ?>" alt="Foto user" class="user-table-photo"><?php else: ?><i class="fas fa-user-circle fa-2x text-muted"></i><?php endif; ?></td><td><?= htmlspecialchars($row['username']) ?></td><td><?= htmlspecialchars($row['full_name']) ?></td><td><?= htmlspecialchars($row['job_position'] ?? '-') ?></td><td><span class="badge badge-<?= $row['role'] === 'admin' ? 'danger' : ($row['role'] === 'staff' ? 'primary' : 'secondary') ?>"><?= htmlspecialchars(ucfirst($row['role'])) ?></span></td><td><?= $row['role'] === 'admin' ? 'Semua modul (Full Akses)' : (int) $row['module_count'] ?></td><td class="text-nowrap"><a class="btn btn-sm btn-warning" href="users_edit.php?id=<?= $row['id'] ?>" title="Edit user"><i class="fas fa-edit"></i></a><?php if ((int) $row['id'] !== (int) current_user()['id']): ?> <form class="d-inline" method="post" onsubmit="return confirm('Hapus user ini?');"><input type="hidden" name="delete_id" value="<?= $row['id'] ?>"><button class="btn btn-sm btn-danger" title="Hapus user"><i class="fas fa-trash"></i></button></form><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div></div>
<?php include 'footer.php'; ?>