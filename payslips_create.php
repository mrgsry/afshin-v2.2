<?php
require_once 'functions.php';
require_module_access('payslip', 'full');

const PAYSLIP_RATE_PER_INVOICE = 350000;

function generate_payslip_no($mysqli, $salaryPeriod) {
    $year = (int) date('Y', strtotime($salaryPeriod));
    $month = (int) date('n', strtotime($salaryPeriod));
    // Semua metode (by invoice dan custom) memakai satu nomor urut yang sama.
    // Jangan mengambil nomor dari payslip_invoices karena slip custom memang tidak
    // memiliki detail invoice.
    $result = mysqli_query($mysqli, "SELECT payslip_no FROM payslips WHERE YEAR(salary_period) = $year ORDER BY id DESC FOR UPDATE");
    if (!$result) {
        throw new Exception('Data nomor slip gaji gagal dibaca: ' . mysqli_error($mysqli));
    }
    $next = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        if (preg_match('/^(\d+)\/PS\/ART\/[IVX]+\/\d{4}$/', $row['payslip_no'], $matches)) {
            $next = max($next, (int) $matches[1] + 1);
        }
    }
    return sprintf('%03d/PS/ART/%s/%04d', $next, bulan_romawi($month), $year);
}

function get_waiting_invoices($mysqli, $forUpdate = false) {
    $sql = "SELECT i.id, i.invoice_no, c.name AS customer_name, COALESCE(i.po_number, '-') AS po_number
        FROM invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        LEFT JOIN admin_invoice_items aii ON aii.invoice_no COLLATE utf8mb4_unicode_ci = i.invoice_no COLLATE utf8mb4_unicode_ci
        LEFT JOIN payslip_invoices psi ON psi.invoice_id = i.id
        WHERE TRIM(COALESCE(i.faktur_inv, '')) NOT IN ('', '-', '0', '000', 'NULL', 'null')
          AND aii.id IS NULL AND psi.id IS NULL
        ORDER BY i.created_at ASC, i.id ASC";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    return mysqli_query($mysqli, $sql);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $periodInput = $_POST['salary_period'] ?? '';
    $period = preg_match('/^\d{4}-\d{2}$/', $periodInput) ? $periodInput . '-01' : '';
    $issuedDate = $_POST['issued_date'] ?? '';
    $salaryMethod = ($_POST['salary_method'] ?? 'invoice') === 'custom' ? 'custom' : 'invoice';
    $description = trim($_POST['description'] ?? '');
    $customDescription = trim($_POST['custom_description'] ?? '');
    $customSalary = filter_var(str_replace(',', '.', $_POST['custom_salary'] ?? ''), FILTER_VALIDATE_FLOAT);
    $selectedInvoiceIds = array_values(array_unique(array_filter(array_map('intval', $_POST['invoice_ids'] ?? []), function ($id) {
        return $id > 0;
    })));
    $employee = false;

    if ($employeeId > 0) {
        $stmt = mysqli_prepare($mysqli, 'SELECT employee_no, name, employee_level FROM employees WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    $baseDataIsValid = $employee && $period !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedDate);
    $methodIsValid = $salaryMethod === 'invoice'
        ? !empty($selectedInvoiceIds)
        : ($customDescription !== '' && $customSalary !== false && $customSalary > 0);

    if (!$baseDataIsValid || !$methodIsValid) {
        $_SESSION['flash_error'] = $salaryMethod === 'invoice'
            ? 'Pilih karyawan, periode, tanggal terbit, dan minimal satu invoice Waiting Admin Payment.'
            : 'Pilih karyawan, periode, tanggal terbit, lalu isi deskripsi dan nominal gaji custom yang valid.';
    } else {
        mysqli_begin_transaction($mysqli);
        try {
            $invoices = [];
            $invoiceCount = 0;
            $rate = 0.00;
            $grossSalary = (float) $customSalary;
            $savedDescription = $customDescription;

            if ($salaryMethod === 'invoice') {
                $invoiceResult = get_waiting_invoices($mysqli, true);
                $eligibleInvoices = mysqli_fetch_all($invoiceResult, MYSQLI_ASSOC);
                $invoices = array_values(array_filter($eligibleInvoices, function ($invoice) use ($selectedInvoiceIds) {
                    return in_array((int) $invoice['id'], $selectedInvoiceIds, true);
                }));
                if (count($invoices) !== count($selectedInvoiceIds)) {
                    throw new Exception('Ada invoice yang dipilih tidak lagi berstatus Waiting Admin Payment. Silakan pilih ulang.');
                }
                $invoiceCount = count($invoices);
                $rate = PAYSLIP_RATE_PER_INVOICE;
                $grossSalary = $invoiceCount * $rate;
                $savedDescription = $description;
            }

            $payslipNo = generate_payslip_no($mysqli, $period);
            $pph21 = 0.00;
            $stmt = mysqli_prepare($mysqli, 'INSERT INTO payslips (payslip_no, employee_id, employee_no, employee_name, employee_level, salary_period, issued_date, invoice_count, rate_per_invoice, gross_salary, pph21_amount, salary_method, net_salary, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sisssssidddsds', $payslipNo, $employeeId, $employee['employee_no'], $employee['name'], $employee['employee_level'], $period, $issuedDate, $invoiceCount, $rate, $grossSalary, $pph21, $salaryMethod, $grossSalary, $savedDescription);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Slip gaji gagal disimpan: ' . mysqli_stmt_error($stmt));
            }
            $payslipId = mysqli_insert_id($mysqli);
            if ($salaryMethod === 'invoice') {
                $detailStmt = mysqli_prepare($mysqli, 'INSERT INTO payslip_invoices (payslip_id, invoice_id, invoice_no, customer_name, po_number) VALUES (?, ?, ?, ?, ?)');
                foreach ($invoices as $invoice) {
                    mysqli_stmt_bind_param($detailStmt, 'iisss', $payslipId, $invoice['id'], $invoice['invoice_no'], $invoice['customer_name'], $invoice['po_number']);
                    if (!mysqli_stmt_execute($detailStmt)) {
                        throw new Exception('Detail invoice gagal disimpan: ' . mysqli_stmt_error($detailStmt));
                    }
                }
            }
            mysqli_commit($mysqli);
            $_SESSION['flash_success'] = $salaryMethod === 'invoice'
                ? 'Slip gaji berhasil dibuat dari ' . $invoiceCount . ' invoice Waiting Admin Payment.'
                : 'Slip gaji custom berhasil dibuat.';
            header('Location: payslips_list.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($mysqli);
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
}

$employees = mysqli_query($mysqli, 'SELECT id, employee_no, name, employee_level FROM employees ORDER BY name ASC');
$waitingInvoices = get_waiting_invoices($mysqli);
$invoiceCount = mysqli_num_rows($waitingInvoices);
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
include 'header.php';
?>
<div class="container-fluid py-4">
    <h3 class="mb-3">Buat Slip Gaji</h3>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="card card-primary"><div class="card-header"><h3 class="card-title">Buat Slip Gaji</h3></div>
            <form method="post"><div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6"><label for="employee_id">Karyawan</label><select id="employee_id" name="employee_id" class="form-control" required><option value="">Pilih karyawan</option><?php while ($employee = mysqli_fetch_assoc($employees)): ?><option value="<?= $employee['id'] ?>" <?= (int) ($_POST['employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['employee_no'] . ' - ' . $employee['name'] . ' (' . $employee['employee_level'] . ')') ?></option><?php endwhile; ?></select></div>
                    <div class="form-group col-md-3"><label for="salary_period">Periode Gaji</label><input id="salary_period" name="salary_period" type="month" class="form-control" required value="<?= htmlspecialchars($_POST['salary_period'] ?? date('Y-m')) ?>"></div>
                    <div class="form-group col-md-3"><label for="issued_date">Tanggal Terbit</label><input id="issued_date" name="issued_date" type="date" class="form-control" required value="<?= htmlspecialchars($_POST['issued_date'] ?? date('Y-m-d')) ?>"></div>
                </div>
                <div class="form-group"><label>Metode Perhitungan Gaji</label><div class="form-row"><div class="col-md-6"><div class="custom-control custom-radio"><input class="custom-control-input method-radio" id="method-invoice" name="salary_method" type="radio" value="invoice" <?= ($_POST['salary_method'] ?? 'invoice') === 'invoice' ? 'checked' : '' ?>><label class="custom-control-label" for="method-invoice"><strong>By Invoice</strong><br><small class="text-muted">Tarif tetap Rp <?= number_format(PAYSLIP_RATE_PER_INVOICE, 0, ',', '.') ?> per invoice.</small></label></div></div><div class="col-md-6"><div class="custom-control custom-radio"><input class="custom-control-input method-radio" id="method-custom" name="salary_method" type="radio" value="custom" <?= ($_POST['salary_method'] ?? '') === 'custom' ? 'checked' : '' ?>><label class="custom-control-label" for="method-custom"><strong>Nominal Custom</strong><br><small class="text-muted">Untuk gaji direktur atau pekerjaan di luar invoice.</small></label></div></div></div></div>
                <div id="invoice-section"><div class="alert <?= $invoiceCount ? 'alert-info' : 'alert-warning' ?> mb-3"><strong>Perhitungan invoice terpilih:</strong> <span id="selected-invoice-count">0</span> invoice x Rp <?= number_format(PAYSLIP_RATE_PER_INVOICE, 0, ',', '.') ?> = <strong id="selected-invoice-total">Rp 0</strong><?php if (!$invoiceCount): ?><br>Tidak ada invoice yang dapat diproses saat ini.<?php endif; ?></div>
                    <div class="form-group"><label for="invoice-search">Cari Nomor Invoice atau Nomor PO</label><input id="invoice-search" type="search" class="form-control" placeholder="Ketik nomor invoice atau nomor PO untuk memfilter daftar"></div>
                    <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th class="text-center" style="width: 52px;"><input id="select-all-invoices" type="checkbox" title="Pilih semua invoice"></th><th>No.</th><th>Nomor Invoice</th><th>Nama PT</th><th>No. PO</th><th class="text-right">Tarif / Upah Invoice</th></tr></thead><tbody><?php $no = 1; $previousSelected = array_map('intval', $_POST['invoice_ids'] ?? []); while ($invoice = mysqli_fetch_assoc($waitingInvoices)): ?><tr class="invoice-row" data-invoice-no="<?= htmlspecialchars(strtolower($invoice['invoice_no']), ENT_QUOTES) ?>" data-po-number="<?= htmlspecialchars(strtolower($invoice['po_number'] ?? ''), ENT_QUOTES) ?>"><td class="text-center"><input class="invoice-checkbox" name="invoice_ids[]" type="checkbox" value="<?= $invoice['id'] ?>" <?= in_array((int) $invoice['id'], $previousSelected, true) ? 'checked' : '' ?>></td><td><?= $no++ ?></td><td><?= htmlspecialchars($invoice['invoice_no']) ?></td><td><?= htmlspecialchars($invoice['customer_name'] ?: '-') ?></td><td><?= htmlspecialchars($invoice['po_number'] ?: '-') ?></td><td class="text-right">Rp <?= number_format(PAYSLIP_RATE_PER_INVOICE, 0, ',', '.') ?></td></tr><?php endwhile; ?><?php if ($no === 1): ?><tr id="no-invoice-row"><td colspan="6" class="text-center text-muted">Tidak ada invoice waiting.</td></tr><?php endif; ?></tbody></table></div>
                    <nav id="invoice-pagination" aria-label="Pagination invoice" class="mt-2"></nav>
                    <div class="form-group mt-3"><label for="description">Deskripsi Tambahan</label><textarea id="description" name="description" rows="3" class="form-control" placeholder="Opsional"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div></div>
                <div id="custom-section" class="card card-outline card-info d-none"><div class="card-header"><h3 class="card-title">Slip Custom</h3></div><div class="card-body"><div class="form-group"><label for="custom_description">Deskripsi Gaji</label><textarea id="custom_description" name="custom_description" rows="3" class="form-control" placeholder="Contoh: Gaji Direktur Periode Januari 2026"><?= htmlspecialchars($_POST['custom_description'] ?? '') ?></textarea></div><div class="form-group mb-0"><label for="custom_salary">Nominal Gaji</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">Rp</span></div><input id="custom_salary" name="custom_salary" type="number" min="1" step="0.01" class="form-control" placeholder="Masukkan nominal gaji" value="<?= htmlspecialchars($_POST['custom_salary'] ?? '') ?>"></div></div></div></div>
            </div><div class="card-footer"><a href="payslips_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Buat Slip Gaji</button></div></form>
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
    const pagination = document.getElementById('invoice-pagination');
    const noInvoiceRow = document.getElementById('no-invoice-row');
    const rowsPerPage = 15;
    let currentPage = 1;
    const methodRadios = Array.from(document.querySelectorAll('.method-radio'));
    const invoiceSection = document.getElementById('invoice-section');
    const customSection = document.getElementById('custom-section');
    function toggleMethod() {
        const custom = document.getElementById('method-custom').checked;
        invoiceSection.classList.toggle('d-none', custom);
        customSection.classList.toggle('d-none', !custom);
    }

    function updateSummary() {
        const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        count.textContent = selected;
        total.textContent = 'Rp ' + (selected * rate).toLocaleString('id-ID');
        if (all) {
            all.checked = checkboxes.length > 0 && selected === checkboxes.length;
            all.indeterminate = selected > 0 && selected < checkboxes.length;
        }
    }

    function getFilteredRows() {
        const keyword = search ? search.value.trim().toLowerCase() : '';
        return rows.filter(function (row) {
            return row.dataset.invoiceNo.includes(keyword) || row.dataset.poNumber.includes(keyword);
        });
    }

    function renderPagination(filteredRows) {
        const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
        if (!pagination) return;
        pagination.innerHTML = '';
        if (pageCount <= 1) return;

        const list = document.createElement('ul');
        list.className = 'pagination pagination-sm justify-content-center mb-0';
        function addPage(label, page, disabled, active) {
            const item = document.createElement('li');
            item.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
            const link = document.createElement('button');
            link.type = 'button';
            link.className = 'page-link';
            link.textContent = label;
            link.disabled = disabled;
            link.addEventListener('click', function () {
                currentPage = page;
                renderInvoicePage();
            });
            item.appendChild(link);
            list.appendChild(item);
        }
        addPage('Sebelumnya', currentPage - 1, currentPage === 1, false);
        for (let page = 1; page <= pageCount; page++) addPage(String(page), page, false, page === currentPage);
        addPage('Berikutnya', currentPage + 1, currentPage === pageCount, false);
        pagination.appendChild(list);
    }

    function renderInvoicePage() {
        const filteredRows = getFilteredRows();
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
        if (currentPage > pageCount) currentPage = pageCount;
        rows.forEach(function (row) { row.style.display = 'none'; });
        const start = (currentPage - 1) * rowsPerPage;
        filteredRows.slice(start, start + rowsPerPage).forEach(function (row) { row.style.display = ''; });
        if (noInvoiceRow) noInvoiceRow.style.display = filteredRows.length ? 'none' : '';
        renderPagination(filteredRows);
    }

    if (all) all.addEventListener('change', function () { checkboxes.forEach(function (checkbox) { checkbox.checked = all.checked; }); updateSummary(); });
    checkboxes.forEach(function (checkbox) { checkbox.addEventListener('change', updateSummary); });
    methodRadios.forEach(function (radio) { radio.addEventListener('change', toggleMethod); });
    if (search) search.addEventListener('input', function () {
        currentPage = 1;
        renderInvoicePage();
    });
    updateSummary();
    renderInvoicePage();
    toggleMethod();
});
</script>
<?php include 'footer.php'; ?>
