<?php
require_once 'functions.php';
require_once 'db.php';
require_login();
include 'header.php';

/* ================= QUERY DATA PO ================= */

$query = mysqli_query($mysqli,"
SELECT 
    i.id,
    i.po_number,
    i.created_at,
    i.subtotal,
    i.ppn,
    i.pph,
    i.total,
    c.name as customer_name
FROM invoices i
LEFT JOIN customers c ON i.customer_id = c.id
WHERE i.po_number IS NOT NULL
AND i.po_number != ''
ORDER BY i.created_at DESC
");
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<style>
.details-control {
cursor:pointer;
color:#667eea;
font-weight:bold;
}

.child-box{
background:#f8f9fa;
padding:20px;
border-radius:12px;
}
</style>

<div class="container-fluid">

<div class="card shadow-sm">
<div class="card-header bg-primary text-white">
<h5 class="mb-0"><i class="fas fa-file-contract"></i> Data PO</h5>
</div>

<div class="card-body">

<div class="table-responsive">
<table id="poTable" class="table table-bordered table-striped">
<thead class="thead-light">
<tr>
<th width="5%"></th>
<th>PO Number</th>
<th>Tanggal</th>
<th>Customer</th>
<th>Total</th>
</tr>
</thead>
<tbody>

<?php while($row=mysqli_fetch_assoc($query)): ?>
<tr data-id="<?= $row['id'] ?>">
<td class="details-control text-center">
<i class="fas fa-chevron-right"></i>
</td>
<td><?= htmlspecialchars($row['po_number']) ?></td>
<td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
<td><?= htmlspecialchars($row['customer_name']) ?></td>
<td class="text-right">
Rp <?= number_format($row['total'],0,',','.') ?>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</div>
</div>
</div>

<script>
function format(data){
return `
<div class="child-box">
<div id="detail-${data}">Loading...</div>
</div>
`;
}

$(document).ready(function(){

var table = $('#poTable').DataTable({
responsive:true,
pageLength:10
});

$('#poTable tbody').on('click','td.details-control',function(){

var tr = $(this).closest('tr');
var row = table.row(tr);
var id = tr.data('id');

if(row.child.isShown()){
row.child.hide();
tr.removeClass('shown');
$(this).html('<i class="fas fa-chevron-right"></i>');
}else{

row.child(format(id)).show();
tr.addClass('shown');
$(this).html('<i class="fas fa-chevron-down"></i>');

$.get('get_po_detail.php',{id:id},function(res){
$('#detail-'+id).html(res);
});

}

});

});
</script>

<?php include 'footer.php'; ?>