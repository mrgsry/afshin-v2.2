<?php
require_once 'functions.php'; require_login();
$id = (int) ($_GET['id'] ?? 0); $employee = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM employees WHERE id = $id"));
if (!$employee) { $_SESSION['flash_error'] = 'Karyawan tidak ditemukan.'; header('Location: employees_list.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $level = trim($_POST['employee_level'] ?? '');
    if ($name === '' || $level === '') { $_SESSION['flash_error'] = 'Nama dan level karyawan wajib diisi.'; }
    else { $stmt = mysqli_prepare($mysqli, 'UPDATE employees SET name = ?, employee_level = ? WHERE id = ?'); mysqli_stmt_bind_param($stmt, 'ssi', $name, $level, $id); if (mysqli_stmt_execute($stmt)) { $_SESSION['flash_success'] = 'Data karyawan berhasil diperbarui.'; header('Location: employees_list.php'); exit; } $_SESSION['flash_error'] = 'Data karyawan gagal diperbarui.'; }
    $employee['name'] = $name; $employee['employee_level'] = $level;
}
$error = $_SESSION['flash_error'] ?? ''; unset($_SESSION['flash_error']); include 'header.php';
?>
<div class="container-fluid py-4"><h3 class="mb-3">Edit Karyawan</h3><div class="card card-warning"><div class="card-header"><h3 class="card-title">Data <?= htmlspecialchars($employee['employee_no']) ?></h3></div><form method="post"><div class="card-body">
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="form-group"><label>Nomor Karyawan</label><input class="form-control" readonly value="<?= htmlspecialchars($employee['employee_no']) ?>"></div>
<div class="form-group"><label for="name">Nama Karyawan</label><input id="name" name="name" class="form-control" required value="<?= htmlspecialchars($employee['name']) ?>"></div>
<div class="form-group"><label for="employee_level">Level / Jabatan</label><input id="employee_level" name="employee_level" class="form-control" required value="<?= htmlspecialchars($employee['employee_level']) ?>"></div>
</div><div class="card-footer"><a href="employees_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-warning" type="submit"><i class="fas fa-save mr-1"></i>Perbarui Karyawan</button></div></form></div></div>
<?php include 'footer.php'; ?>
