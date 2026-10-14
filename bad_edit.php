<?php
require_once __DIR__ . '/bad_helpers.php';
require_module_access('bad', 'full');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT * FROM bad_documents WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $doc=$stmt->get_result()->fetch_assoc();
if (!$doc) { flash_set('error','Dokumen BAD tidak ditemukan.'); header('Location: bad_list.php'); exit; }
$photos=['before'=>[],'after'=>[]];
$stmt=$mysqli->prepare('SELECT * FROM bad_photos WHERE bad_id=? ORDER BY photo_type,sort_order,id'); $stmt->bind_param('i',$id); $stmt->execute(); $res=$stmt->get_result(); while($p=$res->fetch_assoc()) $photos[$p['photo_type']][]=$p;
$po_rows=bad_po_rows($mysqli); $po_data=[]; foreach($po_rows as $r) $po_data[(string)$r['id']]=$r;
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    $date=trim($_POST['date_doc']??''); $invoice_id=(int)($_POST['invoice_id']??0); $selected=$po_data[(string)$invoice_id]??null; $new_paths=[]; $remove_ids=[];
    try {
        if(!$date || !$selected) throw new Exception('PO dan tanggal dokumen wajib diisi.');
        foreach(['before','after'] as $type) {
            $existing_ids=array_map('intval',$_POST['keep_'.$type]??[]);
            $current=array_filter($photos[$type],fn($p)=>in_array((int)$p['id'],$existing_ids,true));
            $names=$_FILES[$type]['name']??[]; $new_count=count(array_filter($names));
            if(count($current)+$new_count>5) throw new Exception('Maksimal 5 foto untuk '.ucfirst($type).'.');
            foreach($photos[$type] as $p) if(!in_array((int)$p['id'],$existing_ids,true)) $remove_ids[]=$p;
            foreach($names as $n=>$name) if($name) $new_paths[$type][]=bad_save_photo(['name'=>$name,'type'=>$_FILES[$type]['type'][$n],'tmp_name'=>$_FILES[$type]['tmp_name'][$n],'error'=>$_FILES[$type]['error'][$n],'size'=>$_FILES[$type]['size'][$n]]);
        }
        if(count($photos['before'])+count($photos['after'])-count($remove_ids)+count($new_paths['before']??[])+count($new_paths['after']??[])===0) throw new Exception('Minimal satu foto Before atau After harus tersedia.');
        $mysqli->begin_transaction();
        $stmt=$mysqli->prepare('UPDATE bad_documents SET date_doc=?,invoice_id=?,po_number=?,customer_id=?,customer_name=?,customer_address=?,customer_pic=? WHERE id=?');
        $stmt->bind_param('sisisssi',$date,$invoice_id,$selected['po_number'],$selected['customer_id'],$selected['customer_name'],$selected['customer_address'],$selected['customer_pic'],$id);
        if(!$stmt->execute()) throw new Exception($stmt->error);
        $delete_all=$mysqli->prepare('DELETE FROM bad_photos WHERE bad_id=?'); $delete_all->bind_param('i',$id); $delete_all->execute();
        foreach(['before','after'] as $type) {
            $kept=array_values(array_filter($photos[$type],fn($p)=>!in_array($p,$remove_ids,true)));
            $insert=$mysqli->prepare('INSERT INTO bad_photos (bad_id,photo_type,file_path,sort_order) VALUES (?,?,?,?)');
            foreach(array_merge(array_column($kept,'file_path'),$new_paths[$type]??[]) as $n=>$path) { $order=$n+1; $insert->bind_param('issi',$id,$type,$path,$order); if(!$insert->execute() && $n>=count($kept)) throw new Exception($insert->error); }
        }
        $mysqli->commit(); foreach($remove_ids as $p) bad_delete_file($p['file_path']); flash_set('success','BAD berhasil diperbarui.'); header('Location: bad_list.php'); exit;
    } catch(Throwable $e) { @$mysqli->rollback(); foreach($new_paths as $paths) foreach($paths as $path) bad_delete_file($path); $error=$e->getMessage(); }
}
include __DIR__.'/header.php';
?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"><div class="container-fluid"><div class="card shadow-sm"><div class="card-header bg-primary text-white"><h5 class="mb-0">Edit BAD - <?= htmlspecialchars($doc['document_no']) ?></h5></div><div class="card-body"><?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="id" value="<?= $id ?>"><div class="form-row"><div class="form-group col-md-4"><label>PO</label><select id="invoice_id" name="invoice_id" class="form-control" required><option value="">-- Pilih PO --</option><?php foreach($po_rows as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)$r['id']===(int)$doc['invoice_id']?'selected':'' ?>><?= htmlspecialchars($r['po_number'].' — '.$r['status_label']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-4"><label>Date</label><input type="date" name="date_doc" class="form-control" value="<?= htmlspecialchars($doc['date_doc']) ?>" required></div></div><div class="form-row"><div class="form-group col-md-4"><label>Customer</label><input id="customer_name" class="form-control" value="<?= htmlspecialchars($doc['customer_name']) ?>" readonly></div><div class="form-group col-md-4"><label>PIC</label><input id="customer_pic" class="form-control" value="<?= htmlspecialchars($doc['customer_pic']??'') ?>" readonly></div><div class="form-group col-md-4"><label>Alamat</label><textarea id="customer_address" class="form-control" rows="2" readonly><?= htmlspecialchars($doc['customer_address']??'') ?></textarea></div></div>
<?php foreach(['before'=>'Before','after'=>'After'] as $type=>$label): ?><section class="mb-4"><h6><?= $label ?> Photos</h6><div class="form-row"><?php foreach($photos[$type] as $p): ?><div class="col-md-3 mb-3"><div class="border p-2 h-100"><img src="<?= htmlspecialchars($p['file_path']) ?>" class="img-fluid mb-2" style="height:130px;width:100%;object-fit:contain"><label class="mb-0"><input type="checkbox" name="keep_<?= $type ?>[]" value="<?= (int)$p['id'] ?>" checked> Pertahankan foto</label></div></div><?php endforeach; ?></div><input type="file" name="<?= $type ?>[]" class="form-control-file" accept="image/jpeg,image/png,image/webp" multiple><small class="text-muted">Total maksimal 5 foto termasuk foto yang dipertahankan.</small></section><?php endforeach; ?>
<a href="bad_list.php" class="btn btn-secondary">Kembali</a> <button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Simpan Perubahan</button></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script>const badPO=<?= json_encode($po_data,JSON_UNESCAPED_UNICODE) ?>;$('#invoice_id').select2({placeholder:'-- Pilih PO --',allowClear:true,width:'100%',language:{noResults:function(){return 'PO tidak ditemukan';}}}).on('change',function(){const r=badPO[this.value]||{};$('#customer_name').val(r.customer_name||'');$('#customer_pic').val(r.customer_pic||'');$('#customer_address').val(r.customer_address||'')});</script><?php include __DIR__.'/footer.php'; ?></body>