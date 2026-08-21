<?php
require_once 'functions.php';
require_admin();
$modules = available_modules();
$error = '';
$old = ['username' => '', 'full_name' => '', 'role' => 'guest'];
$permissions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['username'] = trim($_POST['username'] ?? '');
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['role'] = $_POST['role'] ?? 'guest';
    $password = $_POST['password'] ?? '';
    $permissions = posted_permissions($modules);
    if ($old['role'] === 'admin') $permissions = array_fill_keys(array_keys($modules), 'full');
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $old['username']) || strlen($password) < 6) {
        $error = 'Username tidak valid atau password kurang dari 6 karakter.';
    } elseif (!in_array($old['role'], ['admin', 'staff', 'guest'], true)) {
        $error = 'Role user tidak valid.';
    } else {
        $check = mysqli_prepare($mysqli, 'SELECT id FROM users WHERE username = ?');
        mysqli_stmt_bind_param($check, 's', $old['username']); mysqli_stmt_execute($check); mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) $error = 'Username sudah digunakan.';
        mysqli_stmt_close($check);
    }
    if ($error === '') {
        mysqli_begin_transaction($mysqli);
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($mysqli, 'INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $old['username'], $passwordHash, $old['full_name'], $old['role']);
            if (!mysqli_stmt_execute($stmt)) throw new Exception('User gagal disimpan.');
            $userId = mysqli_insert_id($mysqli);
            $permissionStmt = mysqli_prepare($mysqli, 'INSERT INTO user_modules (user_id, module_name, policy) VALUES (?, ?, ?)');
            foreach ($permissions as $module => $policy) { mysqli_stmt_bind_param($permissionStmt, 'iss', $userId, $module, $policy); if (!mysqli_stmt_execute($permissionStmt)) throw new Exception('Permission gagal disimpan.'); }
            mysqli_commit($mysqli); flash_set('User berhasil dibuat.'); header('Location: users_manage.php'); exit;
        } catch (Throwable $e) { mysqli_rollback($mysqli); $error = $e->getMessage(); }
    }
}
include 'header.php';
?>
<div class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3>Buat User</h3><a href="users_manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i>Kembali</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="post">
<div class="form-row"><div class="form-group col-md-4"><label>Username</label><input name="username" class="form-control" required value="<?= htmlspecialchars($old['username']) ?>"></div><div class="form-group col-md-4"><label>Nama Lengkap</label><input name="full_name" class="form-control" required value="<?= htmlspecialchars($old['full_name']) ?>"></div><div class="form-group col-md-4"><label>Role</label><select name="role" id="role" class="form-control"><option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="staff" <?= $old['role'] === 'staff' ? 'selected' : '' ?>>Staff</option><option value="guest" <?= $old['role'] === 'guest' ? 'selected' : '' ?>>Guest</option></select></div></div>
<div class="form-group"><label>Password</label><input name="password" type="password" class="form-control" required minlength="6"></div><hr>
<div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Akses Modul</h5><div><button type="button" class="btn btn-sm btn-outline-primary" id="select-all">Pilih Semua</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all">Hapus Semua</button></div></div>
<div class="table-responsive"><table class="table table-bordered"><thead class="thead-light"><tr><th style="width:70px">Akses</th><th>Modul</th><th style="width:230px">Policy</th></tr></thead><tbody><?php foreach ($modules as $key => $label): ?><tr><td class="text-center"><input type="checkbox" class="module-check" name="modules[<?= $key ?>]" value="1" <?= isset($permissions[$key]) || $old['role'] === 'admin' ? 'checked' : '' ?>></td><td><?= htmlspecialchars($label) ?></td><td><select name="policies[<?= $key ?>]" class="form-control policy-select"><option value="full" <?= ($permissions[$key] ?? '') === 'full' || $old['role'] === 'admin' ? 'selected' : '' ?>>Full Akses</option><option value="read" <?= ($permissions[$key] ?? 'read') === 'read' && $old['role'] !== 'admin' ? 'selected' : '' ?>>Baca Saja</option></select></td></tr><?php endforeach; ?></tbody></table></div>
<button class="btn btn-success"><i class="fas fa-save mr-1"></i>Simpan User</button> <a href="users_manage.php" class="btn btn-secondary">Batal</a>
</form></div></div></div>
<script>$(function(){function toggleAdmin(){var admin=$('#role').val()==='admin';$('.module-check,.policy-select').prop('disabled',admin);if(admin){$('.module-check').prop('checked',true);$('.policy-select').val('full');}}$('#role').on('change',toggleAdmin);$('#select-all').on('click',function(){$('.module-check').prop('checked',true);});$('#clear-all').on('click',function(){$('.module-check').prop('checked',false);});toggleAdmin();});</script>
<?php include 'footer.php'; ?>