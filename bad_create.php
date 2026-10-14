<?php
require_once __DIR__ . '/bad_helpers.php';
require_module_access('bad', 'full');

$po_rows = bad_po_rows($mysqli);
$po_data = [];
foreach ($po_rows as $row) $po_data[(string)$row['id']] = $row;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_doc = trim($_POST['date_doc'] ?? date('Y-m-d'));
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $selected = $po_data[(string)$invoice_id] ?? null;
    $files = [];
    try {
        if (!$selected || !$date_doc) throw new Exception('PO dan tanggal dokumen wajib diisi.');
        foreach (['before', 'after'] as $type) {
            $names = $_FILES[$type]['name'] ?? [];
            if (count(array_filter($names)) > 5) throw new Exception('Maksimal 5 foto untuk ' . ucfirst($type) . '.');
            foreach ($names as $index => $name) {
                if (!$name) continue;
                $files[$type][] = bad_save_photo([
                    'name' => $name,
                    'type' => $_FILES[$type]['type'][$index],
                    'tmp_name' => $_FILES[$type]['tmp_name'][$index],
                    'error' => $_FILES[$type]['error'][$index],
                    'size' => $_FILES[$type]['size'][$index]
                ]);
            }
        }
        if (empty($files['before']) && empty($files['after'])) throw new Exception('Minimal satu foto Before atau After harus diupload.');
        $mysqli->begin_transaction();
        $document_no = bad_generate_no($mysqli);
        $stmt = $mysqli->prepare('INSERT INTO bad_documents (document_no,date_doc,invoice_id,po_number,customer_id,customer_name,customer_address,customer_pic) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssisisss', $document_no, $date_doc, $invoice_id, $selected['po_number'], $selected['customer_id'], $selected['customer_name'], $selected['customer_address'], $selected['customer_pic']);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $bad_id = $mysqli->insert_id;
        $photo_stmt = $mysqli->prepare('INSERT INTO bad_photos (bad_id,photo_type,file_path,sort_order) VALUES (?,?,?,?)');
        foreach ($files as $type => $paths) foreach ($paths as $index => $path) {
            $order = $index + 1;
            $photo_stmt->bind_param('issi', $bad_id, $type, $path, $order);
            if (!$photo_stmt->execute()) throw new Exception($photo_stmt->error);
        }
        $mysqli->commit();
        flash_set('success', 'BAD berhasil dibuat.');
        header('Location: bad_list.php'); exit;
    } catch (Throwable $e) {
        @$mysqli->rollback();
        foreach ($files as $paths) foreach ($paths as $path) bad_delete_file($path);
        $error = $e->getMessage();
    }
}
include __DIR__ . '/header.php';
?>
<div class="container-fluid"><div class="card shadow-sm"><div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-images mr-2"></i>Create BAD</h5></div><div class="card-body">
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<form method="post" enctype="multipart/form-data">
    <div class="form-row"><div class="form-group col-md-4"><label>PO <span class="text-danger">*</span></label><select id="invoice_id" name="invoice_id" class="form-control" required><option value="">-- Pilih PO --</option><?php foreach ($po_rows as $row): ?><option value="<?= (int)$row['id'] ?>" <?= ((int)($_POST['invoice_id'] ?? 0)==$row['id'])?'selected':'' ?>><?= htmlspecialchars($row['po_number'] . ' — ' . $row['status_label']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-4"><label>Date <span class="text-danger">*</span></label><input type="date" name="date_doc" class="form-control" value="<?= htmlspecialchars($_POST['date_doc'] ?? date('Y-m-d')) ?>" required></div></div>
    <div class="form-row"><div class="form-group col-md-4"><label>Customer</label><input id="customer_name" class="form-control" readonly></div><div class="form-group col-md-4"><label>PIC</label><input id="customer_pic" class="form-control" readonly></div><div class="form-group col-md-4"><label>Alamat</label><textarea id="customer_address" class="form-control" rows="2" readonly></textarea></div></div>
    <hr><div class="form-row"><div class="form-group col-md-6"><label>Foto Before <small class="text-muted">(maks. 5, masing-masing &lt; 1 MB)</small></label><input type="file" name="before[]" class="form-control-file" accept="image/jpeg,image/png,image/webp" multiple><small class="form-text text-muted">Pilih maksimal 5 foto.</small></div><div class="form-group col-md-6"><label>Foto After <small class="text-muted">(maks. 5, masing-masing &lt; 1 MB)</small></label><input type="file" name="after[]" class="form-control-file" accept="image/jpeg,image/png,image/webp" multiple><small class="form-text text-muted">Pilih maksimal 5 foto.</small></div></div>
    <a href="bad_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i>Kembali</a> <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Simpan BAD</button>
</form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script>const badPO=<?= json_encode($po_data, JSON_UNESCAPED_UNICODE) ?>;function fillBadPO(){const r=badPO[$('#invoice_id').val()]||{};$('#customer_name').val(r.customer_name||'');$('#customer_pic').val(r.customer_pic||'');$('#customer_address').val(r.customer_address||'');}$('#invoice_id').select2({placeholder:'-- Pilih PO --',allowClear:true,width:'100%',language:{noResults:function(){return 'PO tidak ditemukan';}}}).on('change',fillBadPO);$(fillBadPO);</script>
<?php include __DIR__ . '/footer.php'; ?>