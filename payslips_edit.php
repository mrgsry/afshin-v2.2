<?php
require_once 'functions.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$payslip = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM payslips WHERE id = $id"));
if (!$payslip) {
    $_SESSION['flash_error'] = 'Slip gaji tidak ditemukan.';
    header('Location: payslips_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issuedDate = $_POST['issued_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedDate)) {
        $_SESSION['flash_error'] = 'Tanggal terbit tidak valid.';
    } else {
        $stmt = mysqli_prepare($mysqli, 'UPDATE payslips SET issued_date = ?, description = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssi', $issuedDate, $description, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Data slip gaji berhasil diperbarui.';
            header('Location: payslips_list.php');
            exit;
        }
        $_SESSION['flash_error'] = 'Data slip gaji gagal diperbarui.';
    }
    $payslip['issued_date'] = $issuedDate;
    $payslip['description'] = $description;
}

$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
include 'header.php';
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Edit Slip Gaji</h1></div></section><section class="content"><div class="container-fluid"><div class="card card-warning"><div class="card-header"><h3 class="card-title"><?= htmlspecialchars($payslip['payslip_no']) ?></h3></div><form method="post"><div class="card-body">
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="form-row"><div class="form-group col-md-4"><label>Nama Karyawan</label><input class="form-control" readonly value="<?= htmlspecialchars($payslip['employee_name']) ?>"></div><div class="form-group col-md-4"><label>Periode Gaji</label><input class="form-control" readonly value="<?= date('F Y', strtotime($payslip['salary_period'])) ?>"></div><div class="form-group col-md-4"><label>Jumlah Invoice</label><input class="form-control" readonly value="<?= (int) $payslip['invoice_count'] ?> Invoice"></div></div>
<div class="form-row"><div class="form-group col-md-6"><label>Total Upah Neto</label><input class="form-control" readonly value="Rp <?= number_format($payslip['net_salary'], 0, ',', '.') ?>"></div><div class="form-group col-md-6"><label for="issued_date">Tanggal Terbit</label><input id="issued_date" name="issued_date" type="date" class="form-control" required value="<?= htmlspecialchars($payslip['issued_date']) ?>"></div></div>
<div class="form-group"><label for="description">Deskripsi Tambahan</label><textarea id="description" name="description" rows="4" class="form-control"><?= htmlspecialchars($payslip['description'] ?? '') ?></textarea></div>
</div><div class="card-footer"><a href="payslips_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-warning" type="submit"><i class="fas fa-save mr-1"></i>Perbarui Slip Gaji</button></div></form></div></div></section></div>
<?php include 'footer.php'; ?>
