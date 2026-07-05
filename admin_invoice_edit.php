<?php
require_once 'functions.php';
require_login();

/* ================= VALIDASI ID ================= */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    flash_set('error', 'ID Invoice tidak valid');
    header('Location: admin_invoice_list.php');
    exit;
}

/* ================= AMBIL DATA ADMIN INVOICE ================= */
$q_invoice = mysqli_query($mysqli, "
    SELECT * FROM admin_invoices
    WHERE id = $id
    LIMIT 1
");

if (mysqli_num_rows($q_invoice) === 0) {
    flash_set('error', 'Admin Invoice tidak ditemukan');
    header('Location: admin_invoice_list.php');
    exit;
}

$invoice = mysqli_fetch_assoc($q_invoice);

/* ================= AMBIL ITEMS EXISTING ================= */
$q_items = mysqli_query($mysqli, "
    SELECT * FROM admin_invoice_items
    WHERE admin_invoice_id = $id
    ORDER BY id ASC
");

$existing_items = [];
while ($item = mysqli_fetch_assoc($q_items)) {
    $existing_items[] = $item;
}

/* ================= AMBIL SEMUA PO (untuk dropdown) ================= */
$po = mysqli_query($mysqli, "
    SELECT i.po_number, i.invoice_no, c.name
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    ORDER BY i.created_at DESC
");

$po_list = [];
while ($p = mysqli_fetch_assoc($po)) {
    $po_list[] = $p;
}

include 'header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Admin Invoice</h3>
        <a href="admin_invoice_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <section class="content">

        <form method="post" action="admin_invoice_save.php">

            <!-- Kirim ID untuk mode UPDATE -->
            <input type="hidden" name="edit_id" value="<?= $invoice['id'] ?>">

            <div class="card card-warning">

                <div class="card-header bg-warning">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-edit"></i>
                        Edit Invoice: <?= htmlspecialchars($invoice['admin_invoice_no']) ?>
                    </h5>
                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-4">
                            <label>Admin Invoice No</label>
                            <input type="text" name="admin_invoice_no" class="form-control"
                                value="<?= htmlspecialchars($invoice['admin_invoice_no']) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label>Date</label>
                            <input type="date" name="created_at" class="form-control"
                                value="<?= date('Y-m-d', strtotime($invoice['created_at'])) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control"
                                value="<?= date('Y-m-d', strtotime($invoice['due_date'])) ?>" required>
                        </div>

                    </div>

                    <hr>

                    <h5>Items</h5>

                    <table class="table table-bordered" id="itemTable">

                        <thead class="thead-light">
                            <tr>
                                <th>PO</th>
                                <th>Customer</th>
                                <th>Invoice</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th width="50"></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!empty($existing_items)): ?>

                            <?php foreach ($existing_items as $item): ?>
                            <tr>

                                <td>
                                    <select name="po_number[]" class="form-control poSelect">
                                        <option value="">Select PO</option>
                                        <?php foreach ($po_list as $p): ?>
                                        <option value="<?= htmlspecialchars($p['po_number']) ?>"
                                            data-customer="<?= htmlspecialchars($p['name']) ?>"
                                            data-invoice="<?= htmlspecialchars($p['invoice_no']) ?>"
                                            <?= $p['po_number'] === $item['po_number'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['po_number']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <input type="text" name="customer_name[]" class="form-control customer"
                                        value="<?= htmlspecialchars($item['customer_name']) ?>" readonly>
                                </td>

                                <td>
                                    <input type="text" name="invoice_no[]" class="form-control invoice_no"
                                        value="<?= htmlspecialchars($item['invoice_no']) ?>" readonly>
                                </td>

                                <td>
                                    <input type="number" name="qty[]" class="form-control qty"
                                        value="<?= intval($item['qty']) ?>">
                                </td>

                                <td>
                                    <input type="number" name="price[]" class="form-control price"
                                        value="<?= intval($item['price']) ?>">
                                </td>

                                <td>
                                    <input type="text" class="form-control total"
                                        value="<?= number_format($item['total'], 0, ',', '.') ?>" readonly>
                                </td>

                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>

                            </tr>
                            <?php endforeach; ?>

                            <?php else: ?>

                            <!-- Row kosong jika belum ada items -->
                            <tr>
                                <td>
                                    <select name="po_number[]" class="form-control poSelect">
                                        <option value="">Select PO</option>
                                        <?php foreach ($po_list as $p): ?>
                                        <option value="<?= htmlspecialchars($p['po_number']) ?>"
                                            data-customer="<?= htmlspecialchars($p['name']) ?>"
                                            data-invoice="<?= htmlspecialchars($p['invoice_no']) ?>">
                                            <?= htmlspecialchars($p['po_number']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="customer_name[]" class="form-control customer" readonly>
                                </td>
                                <td><input type="text" name="invoice_no[]" class="form-control invoice_no" readonly>
                                </td>
                                <td><input type="number" name="qty[]" class="form-control qty" value="1"></td>
                                <td><input type="number" name="price[]" class="form-control price" value="350000"></td>
                                <td><input type="text" class="form-control total" readonly></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                    <button type="button" id="addRow" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Item
                    </button>

                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Invoice
                    </button>
                    <a href="admin_invoice_list.php" class="btn btn-secondary ml-2">
                        Cancel
                    </a>
                </div>

            </div>

        </form>

    </section>

</div>

<!-- ===================== TEMPLATE ROW (hidden) ===================== -->
<table style="display:none">
    <tbody id="rowTemplate">
        <tr>
            <td>
                <select name="po_number[]" class="form-control poSelect">
                    <option value="">Select PO</option>
                    <?php foreach ($po_list as $p): ?>
                    <option value="<?= htmlspecialchars($p['po_number']) ?>"
                        data-customer="<?= htmlspecialchars($p['name']) ?>"
                        data-invoice="<?= htmlspecialchars($p['invoice_no']) ?>">
                        <?= htmlspecialchars($p['po_number']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="customer_name[]" class="form-control customer" readonly></td>
            <td><input type="text" name="invoice_no[]" class="form-control invoice_no" readonly></td>
            <td><input type="number" name="qty[]" class="form-control qty" value="1"></td>
            <td><input type="number" name="price[]" class="form-control price" value="350000"></td>
            <td><input type="text" class="form-control total" readonly></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm removeRow">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    </tbody>
</table>

<script>
$(document).ready(function() {

    /* ---- Hitung total semua row yang sudah ada saat load ---- */
    $('#itemTable tbody tr').each(function() {
        calcRow($(this));
    });

});

/* ---- Auto-fill customer & invoice_no saat PO dipilih ---- */
$(document).on('change', '.poSelect', function() {
    let row = $(this).closest('tr');
    let opt = $(this).find(':selected');
    row.find('.customer').val(opt.data('customer'));
    row.find('.invoice_no').val(opt.data('invoice'));
    calcRow(row);
});

/* ---- Hitung total per baris ---- */
function calcRow(row) {
    let qty = parseFloat(row.find('.qty').val()) || 0;
    let price = parseFloat(row.find('.price').val()) || 0;
    let total = qty * price;
    row.find('.total').val(total.toLocaleString('id-ID'));
}

$(document).on('keyup change', '.qty, .price', function() {
    calcRow($(this).closest('tr'));
});

/* ---- Tambah baris baru dari template ---- */
$('#addRow').on('click', function() {
    let newRow = $('#rowTemplate tr').first().clone();
    newRow.find('input').val('');
    newRow.find('.qty').val(1);
    newRow.find('.price').val(350000);
    $('#itemTable tbody').append(newRow);
});

/* ---- Hapus baris ---- */
$(document).on('click', '.removeRow', function() {
    if ($('#itemTable tbody tr').length > 1) {
        $(this).closest('tr').remove();
    } else {
        alert('Minimal harus ada 1 item.');
    }
});
</script>

<?php include 'footer.php'; ?>