<?php
require_once 'functions.php';
require_admin();
$modules = available_modules();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = mysqli_prepare($mysqli, 'SELECT id, username, full_name, role FROM users WHERE id = ?'); mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user) { flash_set('User tidak ditemukan.'); header('Location: users_manage.php'); exit; }
$permissionMap = [];
$stmt = mysqli_prepare($mysqli, 'SELECT module_name, policy FROM user_modules WHERE user_id = ?'); mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); $result = mysqli_stmt_get_result($stmt); while ($row = mysqli_fetch_assoc($result)) $permissionMap[$row['module_name']] = $row['policy'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['full_name'] = trim($_POST['full_name'] ?? ''); $user['role'] = $_POST['role'] ?? 'guest'; $password = $_POST['password'] ?? '';
    $permissionMap = posted_permissions($modules); if ($user['role'] === 'admin') $permissionMap = array_fill_keys(array_keys($modules), 'full');
    if (!in_array($user['role'], ['admin', 'staff', 'guest'], true)) $error = 'Role user tidak valid.';
    elseif ($password !== '' && strlen($password) < 6) $error = 'Password minimal 6 karakter.';
    elseif ($user['full_name'] === '') $error = 'Nama lengkap wajib diisi.';
    if ($error === '') {
        mysqli_begin_transaction($mysqli);
        try {
            if ($password !== '') { $hash = password_hash($password, PASSWORD_DEFAULT); $stmt = mysqli_prepare($mysqli, 'UPDATE users SET full_name = ?, role = ?, password = ? WHERE id = ?'); mysqli_stmt_bind_param($stmt, 'sssi', $user['full_name'], $user['role'], $hash, $id); }
            else { $stmt = mysqli_prepare($mysqli, 'UPDATE users SET full_name = ?, role = ? WHERE id = ?'); mysqli_stmt_bind_param($stmt, 'ssi', $user['full_name'], $user['role'], $id); }
            if (!mysqli_stmt_execute($stmt)) throw new Exception('User gagal diperbarui.');
            $stmt = mysqli_prepare($mysqli, 'DELETE FROM user_modules WHERE user_id = ?'); mysqli_stmt_bind_param($stmt, 'i', $id); if (!mysqli_stmt_execute($stmt)) throw new Exception('Permission lama gagal dihapus.');
            $stmt = mysqli_prepare($mysqli, 'INSERT INTO user_modules (user_id, module_name, policy) VALUES (?, ?, ?)');
            foreach ($permissionMap as $module => $policy) { mysqli_stmt_bind_param($stmt, 'iss', $id, $module, $policy); if (!mysqli_stmt_execute($stmt)) throw new Exception('Permission gagal disimpan.'); }
            mysqli_commit($mysqli);
            if ($id === (int) current_user()['id']) { $_SESSION['user']['role'] = $user['role']; $_SESSION['user']['full_name'] = $user['full_name']; }
            flash_set('User berhasil diperbarui.'); header('Location: users_manage.php'); exit;
        } catch (Throwable $e) { mysqli_rollback($mysqli); $error = $e->getMessage(); }
    }
}
include 'header.php';
?>
<div class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3>Edit User</h3><a href="users_manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i>Kembali</a></div><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><div class="card"><div class="card-body"><form method="post"><input type="hidden" name="id" value="<?= $id ?>"><div class="form-row"><div class="form-group col-md-4"><label>Username</label><input class="form-control" readonly value="<?= htmlspecialchars($user['username']) ?>"></div><div class="form-group col-md-4"><label>Nama Lengkap</label><input name="full_name" class="form-control" required value="<?= htmlspecialchars($user['full_name']) ?>"></div><div class="form-group col-md-4"><label>Role</label><select name="role" id="role" class="form-control"><option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option><option value="staff" <?= $user['role']==='staff'?'selected':'' ?>>Staff</option><option value="guest" <?= $user['role']==='guest'?'selected':'' ?>>Guest</option></select></div></div><div class="form-group"><label>Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label><input name="password" type="password" class="form-control" minlength="6"></div><hr><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Akses Modul</h5><div><button type="button" class="btn btn-sm btn-outline-primary" id="select-all">Pilih Semua</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all">Hapus Semua</button></div></div><div class="table-responsive"><table class="table table-bordered"><thead class="thead-light"><tr><th style="width:70px">Akses</th><th>Modul</th><th style="width:230px">Policy</th></tr></thead><tbody><?php foreach ($modules as $key => $label): ?><tr><td class="text-center"><input type="checkbox" class="module-check" name="modules[<?= $key ?>]" value="1" <?= isset($permissionMap[$key]) || $user['role']==='admin' ? 'checked' : '' ?>></td><td><?= htmlspecialchars($label) ?></td><td><select name="policies[<?= $key ?>]" class="form-control policy-select"><option value="full" <?= ($permissionMap[$key] ?? '')==='full' || $user['role']==='admin'?'selected':'' ?>>Full Akses</option><option value="read" <?= ($permissionMap[$key] ?? 'read')==='read' && $user['role']!=='admin'?'selected':'' ?>>Baca Saja</option></select></td></tr><?php endforeach; ?></tbody></table></div><button class="btn btn-warning"><i class="fas fa-save mr-1"></i>Simpan Perubahan</button> <a href="users_manage.php" class="btn btn-secondary">Batal</a></form></div></div></div><script>$(function(){function toggleAdmin(){var admin=$('#role').val()==='admin';$('.module-check,.policy-select').prop('disabled',admin);if(admin){$('.module-check').prop('checked',true);$('.policy-select').val('full');}}$('#role').on('change',toggleAdmin);$('#select-all').on('click',function(){$('.module-check').prop('checked',true);});$('#clear-all').on('click',function(){$('.module-check').prop('checked',false);});toggleAdmin();});</script>
<?php include 'footer.php'; ?>