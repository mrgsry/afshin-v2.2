<?php
require_once 'functions.php';
require_module_access('payslip', 'full');

const PAYSLIP_RATE_PER_INVOICE = 350000;

function get_editable_invoices($mysqli, $payslipId, $forUpdate = false) {
    $sql = "SELECT i.id, i.invoice_no, c.name AS customer_name, COALESCE(i.po_number, '-') AS po_number
        FROM invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        LEFT JOIN admin_invoice_items aii ON aii.invoice_no COLLATE utf8mb4_unicode_ci = i.invoice_no COLLATE utf8mb4_unicode_ci
        LEFT JOIN payslip_invoices psi ON psi.invoice_id = i.id
        WHERE TRIM(COALESCE(i.faktur_inv, '')) NOT IN ('', '-', '0', '000', 'NULL', 'null')
          AND aii.id IS NULL AND (psi.id IS NULL OR psi.payslip_id = ?)
        ORDER BY i.created_at ASC, i.id ASC";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = mysqli_prepare($mysqli, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $payslipId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = mysqli_prepare($mysqli, 'SELECT * FROM payslips WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$payslip = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$payslip) {
    $_SESSION['flash_error'] = 'Slip gaji tidak ditemukan.';
    header('Location: payslips_list.php');
    exit;
}

$selectedInvoiceIds = [];
$detailStmt = mysqli_prepare($mysqli, 'SELECT invoice_id FROM payslip_invoices WHERE payslip_id = ? ORDER BY id ASC');
mysqli_stmt_bind_param($detailStmt, 'i', $id);
mysqli_stmt_execute($detailStmt);
$detailResult = mysqli_stmt_get_result($detailStmt);
while ($detail = mysqli_fetch_assoc($detailResult)) $selectedInvoiceIds[] = (int) $detail['invoice_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issuedDate = $_POST['issued_date'] ?? '';
    $salaryMethod = ($_POST['salary_method'] ?? ($payslip['salary_method'] ?? 'invoice')) === 'custom' ? 'custom' : 'invoice';
    $description = trim($_POST['description'] ?? '');
    $customDescription = trim($_POST['custom_description'] ?? '');
    $customSalary = filter_var(str_replace(',', '.', $_POST['custom_salary'] ?? ''), FILTER_VALIDATE_FLOAT);
    $selectedInvoiceIds = array_values(array_unique(array_filter(array_map('intval', $_POST['invoice_ids'] ?? []), function ($invoiceId) { return $invoiceId > 0; })));

    $methodIsValid = $salaryMethod === 'invoice'
        ? !empty($selectedInvoiceIds)
        : ($customDescription !== '' && $customSalary !== false && $customSalary > 0);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedDate) || !$methodIsValid) {
        $_SESSION['flash_error'] = $salaryMethod === 'invoice'
            ? 'Tanggal terbit valid dan minimal satu invoice harus dipilih.'
            : 'Isi deskripsi dan nominal gaji custom yang valid.';
    } else {
        mysqli_begin_transaction($mysqli);
        try {
            $invoices = [];
            $invoiceCount = 0;
            $rate = 0.00;
            $grossSalary = (float) $customSalary;
            $savedDescription = $customDescription;
            if ($salaryMethod === 'invoice') {
                $invoiceResult = get_editable_invoices($mysqli, $id, true);
                $eligibleInvoices = mysqli_fetch_all($invoiceResult, MYSQLI_ASSOC);
                $invoices = array_values(array_filter($eligibleInvoices, function ($invoice) use ($selectedInvoiceIds) { return in_array((int) $invoice['id'], $selectedInvoiceIds, true); }));
                if (count($invoices) !== count($selectedInvoiceIds)) throw new Exception('Ada invoice yang sudah tidak tersedia untuk slip ini. Silakan pilih ulang.');
                $invoiceCount = count($invoices);
                $rate = PAYSLIP_RATE_PER_INVOICE;
                $grossSalary = $invoiceCount * $rate;
                $savedDescription = $description;
            }
            $pph21 = 0.00;
            $stmt = mysqli_prepare($mysqli, 'UPDATE payslips SET issued_date = ?, invoice_count = ?, rate_per_invoice = ?, gross_salary = ?, pph21_amount = ?, salary_method = ?, net_salary = ?, description = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sidddsdsi', $issuedDate, $invoiceCount, $rate, $grossSalary, $pph21, $salaryMethod, $grossSalary, $savedDescription, $id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception('Data slip gaji gagal diperbarui: ' . mysqli_stmt_error($stmt));

            $deleteStmt = mysqli_prepare($mysqli, 'DELETE FROM payslip_invoices WHERE payslip_id = ?');
            mysqli_stmt_bind_param($deleteStmt, 'i', $id);
            if (!mysqli_stmt_execute($deleteStmt)) throw new Exception('Invoice lama gagal diperbarui: ' . mysqli_stmt_error($deleteStmt));

            if ($salaryMethod === 'invoice') {
                $detailStmt = mysqli_prepare($mysqli, 'INSERT INTO payslip_invoices (payslip_id, invoice_id, invoice_no, customer_name, po_number) VALUES (?, ?, ?, ?, ?)');
                foreach ($invoices as $invoice) {
                    mysqli_stmt_bind_param($detailStmt, 'iisss', $id, $invoice['id'], $invoice['invoice_no'], $invoice['customer_name'], $invoice['po_number']);
                    if (!mysqli_stmt_execute($detailStmt)) throw new Exception('Detail invoice gagal disimpan: ' . mysqli_stmt_error($detailStmt));
                }
            }
            mysqli_commit($mysqli);
            $_SESSION['flash_success'] = 'Slip gaji dan invoice berhasil diperbarui.';
            header('Location: payslips_list.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($mysqli);
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    $payslip['issued_date'] = $issuedDate;
    $payslip['description'] = $description;
    $payslip['salary_method'] = $salaryMethod;
    $payslip['custom_description'] = $customDescription;
    $payslip['gross_salary'] = $customSalary !== false ? $customSalary : $payslip['gross_salary'];
}

$waitingInvoices = get_editable_invoices($mysqli, $id);
$invoiceOptions = mysqli_fetch_all($waitingInvoices, MYSQLI_ASSOC);
$invoiceCount = count($invoiceOptions);
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
include 'header.php';
?>
<div class="container-fluid py-4">
    <h3 class="mb-3">Edit Slip Gaji</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card card-warning"><div class="card-header"><h3 class="card-title"><?= htmlspecialchars($payslip['payslip_no']) ?></h3></div>
        <form method="post"><input type="hidden" name="id" value="<?= $id ?>"><div class="card-body">
            <div class="form-row"><div class="form-group col-md-4"><label>Nama Karyawan</label><input class="form-control" readonly value="<?= htmlspecialchars($payslip['employee_name']) ?>"></div><div class="form-group col-md-4"><label>Periode Gaji</label><input class="form-control" readonly value="<?= date('F Y', strtotime($payslip['salary_period'])) ?>"></div><div class="form-group col-md-4"><label for="issued_date">Tanggal Terbit</label><input id="issued_date" name="issued_date" type="date" class="form-control" required value="<?= htmlspecialchars($payslip['issued_date']) ?>"></div></div>
            <div class="form-group"><label>Metode Perhitungan Gaji</label><div class="form-row"><div class="col-md-6"><div class="custom-control custom-radio"><input class="custom-control-input method-radio" id="method-invoice" name="salary_method" type="radio" value="invoice" <?= ($payslip['salary_method'] ?? 'invoice') === 'invoice' ? 'checked' : '' ?>><label class="custom-control-label" for="method-invoice"><strong>By Invoice</strong></label></div></div><div class="col-md-6"><div class="custom-control custom-radio"><input class="custom-control-input method-radio" id="method-custom" name="salary_method" type="radio" value="custom" <?= ($payslip['salary_method'] ?? '') === 'custom' ? 'checked' : '' ?>><label class="custom-control-label" for="method-custom"><strong>Nominal Custom</strong></label></div></div></div></div>
            <div id="invoice-section"><div class="form-row"><div class="form-group col-md-6"><label>Jumlah Invoice</label><input id="selected-invoice-count" class="form-control" readonly value="<?= count($selectedInvoiceIds) ?> Invoice"></div><div class="form-group col-md-6"><label>Total Upah Neto</label><input id="selected-invoice-total" class="form-control" readonly value="Rp <?= number_format(count($selectedInvoiceIds) * PAYSLIP_RATE_PER_INVOICE, 0, ',', '.') ?>"></div></div>
            <div class="form-group"><label for="invoice-search">Cari Nomor Invoice atau Nomor PO</label><input id="invoice-search" type="search" class="form-control" placeholder="Ketik nomor invoice atau nomor PO untuk memfilter daftar"></div>
            <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th class="text-center" style="width: 52px;"><input id="select-all-invoices" type="checkbox" title="Pilih semua invoice"></th><th>No.</th><th>Nomor Invoice</th><th>Nama PT</th><th>No. PO</th><th class="text-right">Tarif / Upah Invoice</th></tr></thead><tbody>
                <?php $no = 1; foreach ($invoiceOptions as $invoice): ?><tr class="invoice-row" data-invoice-no="<?= htmlspecialchars(strtolower($invoice['invoice_no']), ENT_QUOTES) ?>" data-po-number="<?= htmlspecialchars(strtolower($invoice['po_number'] ?? ''), ENT_QUOTES) ?>"><td class="text-center"><input class="invoice-checkbox" name="invoice_ids[]" type="checkbox" value="<?= $invoice['id'] ?>" <?= in_array((int) $invoice['id'], $selectedInvoiceIds, true) ? 'checked' : '' ?>></td><td><?= $no++ ?></td><td><?= htmlspecialchars($invoice['invoice_no']) ?></td><td><?= htmlspecialchars($invoice['customer_name'] ?: '-') ?></td><td><?= htmlspecialchars($invoice['po_number'] ?: '-') ?></td><td class="text-right">Rp <?= number_format(PAYSLIP_RATE_PER_INVOICE, 0, ',', '.') ?></td></tr><?php endforeach; ?>
                <?php if (!$invoiceOptions): ?><tr><td colspan="6" class="text-center text-muted">Tidak ada invoice yang tersedia.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div class="form-group mt-3"><label for="description">Deskripsi Tambahan</label><textarea id="description" name="description" rows="4" class="form-control"><?= htmlspecialchars($payslip['description'] ?? '') ?></textarea></div></div>
            <div id="custom-section" class="card card-outline card-info d-none"><div class="card-header"><h3 class="card-title">Slip Custom</h3></div><div class="card-body"><div class="form-group"><label for="custom_description">Deskripsi Gaji</label><textarea id="custom_description" name="custom_description" rows="3" class="form-control"><?= htmlspecialchars($payslip['custom_description'] ?? (($payslip['salary_method'] ?? '') === 'custom' ? ($payslip['description'] ?? '') : '')) ?></textarea></div><div class="form-group mb-0"><label for="custom_salary">Nominal Gaji</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">Rp</span></div><input id="custom_salary" name="custom_salary" type="number" min="1" step="0.01" class="form-control" value="<?= ($payslip['salary_method'] ?? '') === 'custom' ? htmlspecialchars($payslip['gross_salary']) : '' ?>"></div></div></div></div>
        </div><div class="card-footer"><a href="payslips_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-warning" type="submit" <?= !$invoiceCount ? 'disabled' : '' ?>><i class="fas fa-save mr-1"></i>Perbarui Slip Gaji</button></div></form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rate = <?= PAYSLIP_RATE_PER_INVOICE ?>;
    const all = document.getElementById('select-all-invoices');
    const checkboxes = Array.from(document.querySelectorAll('.invoice-checkbox'));
    const search = document.getElementById('invoice-search');
    const rows = Array.from(document.querySelectorAll('.invoice-row'));
    const count = document.getElementById('selected-invoice-count');
    const total = document.getElementById('selected-invoice-total');
    const invoiceSection = document.getElementById('invoice-section');
    const customSection = document.getElementById('custom-section');
    function toggleMethod() {
        const custom = document.getElementById('method-custom').checked;
        invoiceSection.classList.toggle('d-none', custom);
        customSection.classList.toggle('d-none', !custom);
    }
    function updateSummary() {
        const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        count.value = selected + ' Invoice';
        total.value = 'Rp ' + (selected * rate).toLocaleString('id-ID');
        all.checked = checkboxes.length > 0 && selected === checkboxes.length;
        all.indeterminate = selected > 0 && selected < checkboxes.length;
    }
    all.addEventListener('change', function () { checkboxes.forEach(function (checkbox) { checkbox.checked = all.checked; }); updateSummary(); });
    checkboxes.forEach(function (checkbox) { checkbox.addEventListener('change', updateSummary); });
    document.querySelectorAll('.method-radio').forEach(function (radio) { radio.addEventListener('change', toggleMethod); });
    search.addEventListener('input', function () { const keyword = search.value.trim().toLowerCase(); rows.forEach(function (row) { row.style.display = row.dataset.invoiceNo.includes(keyword) || row.dataset.poNumber.includes(keyword) ? '' : 'none'; }); });
    updateSummary();
    toggleMethod();
});
</script>
<?php include 'footer.php'; ?>