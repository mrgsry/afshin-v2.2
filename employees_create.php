<?php
require_once 'functions.php';
require_once 'employee_signature.php';
require_module_access('employee', 'full');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $level = trim($_POST['employee_level'] ?? '');
    try {
        if ($name === '' || $level === '') throw new Exception('Nama dan level karyawan wajib diisi.');
        $employeeNo = gen_reference('EMP-', $mysqli, 'employees');
        $signaturePath = save_employee_signature($employeeNo, $_FILES['signature_file'] ?? [], trim($_POST['signature_data'] ?? ''));
        $stmt = mysqli_prepare($mysqli, 'INSERT INTO employees (employee_no, name, employee_level, signature_path) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'ssss', $employeeNo, $name, $level, $signaturePath);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Karyawan gagal ditambahkan: ' . mysqli_stmt_error($stmt));
        $_SESSION['flash_success'] = 'Karyawan berhasil ditambahkan.'; header('Location: employees_list.php'); exit;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
}
$error = $_SESSION['flash_error'] ?? ''; unset($_SESSION['flash_error']); include 'header.php';
?>
<div class="container-fluid py-4"><h3 class="mb-3">Tambah Karyawan</h3><div class="card card-primary"><div class="card-header"><h3 class="card-title">Data Karyawan</h3></div><form method="post" enctype="multipart/form-data"><div class="card-body">
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="form-group"><label for="name">Nama Karyawan</label><input id="name" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
<div class="form-group"><label for="employee_level">Level / Jabatan</label><input id="employee_level" name="employee_level" class="form-control" required placeholder="Contoh: Staff Administrasi" value="<?= htmlspecialchars($_POST['employee_level'] ?? '') ?>"></div>
<div class="form-group"><label for="signature_file">Upload Tanda Tangan PNG</label><input id="signature_file" name="signature_file" type="file" accept="image/png" class="form-control-file"><small class="form-text text-muted">Maksimal 2 MB. Pilih upload atau gambar langsung di kotak tanda tangan.</small></div>
<div class="form-group"><label>Gambar Tanda Tangan</label><div class="border rounded bg-light p-2" style="max-width:620px"><canvas id="signature-canvas" width="600" height="220" style="width:100%;height:auto;background:#fff;touch-action:none;cursor:crosshair"></canvas></div><input type="hidden" name="signature_data" id="signature_data"><button type="button" id="clear-signature" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-eraser mr-1"></i>Bersihkan Drawing</button></div>
</div><div class="card-footer"><a href="employees_list.php" class="btn btn-secondary">Batal</a><button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Simpan Karyawan</button></div></form></div></div>
<?php include 'footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('signature-canvas'), ctx = canvas.getContext('2d'), output = document.getElementById('signature_data'); let drawing = false, hasDrawing = false;
    ctx.strokeStyle = '#000'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    function point(e) { const r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return { x: (t.clientX-r.left)*canvas.width/r.width, y: (t.clientY-r.top)*canvas.height/r.height }; }
    function start(e) { e.preventDefault(); drawing=true; const p=point(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
    function move(e) { if(!drawing) return; e.preventDefault(); const p=point(e); ctx.lineTo(p.x,p.y); ctx.stroke(); hasDrawing=true; }
    function end() { drawing=false; if(hasDrawing) output.value=canvas.toDataURL('image/png'); }
    canvas.addEventListener('mousedown',start); canvas.addEventListener('mousemove',move); canvas.addEventListener('mouseup',end); canvas.addEventListener('mouseleave',end); canvas.addEventListener('touchstart',start,{passive:false}); canvas.addEventListener('touchmove',move,{passive:false}); canvas.addEventListener('touchend',end);
    document.getElementById('clear-signature').addEventListener('click',function(){ctx.clearRect(0,0,canvas.width,canvas.height); output.value=''; hasDrawing=false;});
});
</script>
