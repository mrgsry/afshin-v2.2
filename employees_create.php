<?php
require_once 'functions.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $level = trim($_POST['employee_level'] ?? '');
    if ($name === '' || $level === '') {
        $_SESSION['flash_error'] = 'Nama dan level karyawan wajib diisi.';
    } else {
        $employeeNo = gen_reference('EMP-', $mysqli, 'employees');
        $stmt = mysqli_prepare($mysqli, 'INSERT INTO employees (employee_no, name, employee_level) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sss', $employeeNo, $name, $level);
        if (mysqli_stmt_execute($stmt)) { $_SESSION['flash_success'] = 'Karyawan berhasil ditambahkan.'; header('Location: employees_list.php'); exit; }
        $_SESSION['flash_error'] = 'Karyawan gagal ditambahkan: ' . mysqli_error($mysqli);
    }
}
$error = $_SESSION['flash_error'] ?? ''; unset($_SESSION['flash_error']); include 'header.php';
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Tambah Karyawan</h1></div></section><section class="content"><div class="container-fluid"><div class="card card-primary"><div class="card-header"><h3 class="card-title">Data Karyawan</h3></div><form method="post"><div class="card-body">
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="form-group"><label for="name">Nama Karyawan</label><input id="name" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
<div class="form-group"><label for="employee_level">Level / Jabatan</label><input id="employee_level" name="employee_level" class="form-control" required placeholder="Contoh: Staff Administrasi" value="<?= htmlspecialchars($_POST['employee_level'] ?? '') ?>"></div>
</div><div class="card-footer"><a href="employees_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Simpan Karyawan</button></div></form></div></div></section></div>
<?php include 'footer.php'; ?>
