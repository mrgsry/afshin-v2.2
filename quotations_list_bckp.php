<?php
require_once 'functions.php';
require_once 'db.php';
require_login();

/* ================= DELETE ================= */
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);
    mysqli_query($mysqli, "DELETE FROM quotations WHERE id=$id");
    flash_set('success','Quotation berhasil dihapus');
    header('Location: quotations_list.php');
    exit;
}

/* ================= FILTER ================= */
$search = $_GET['search'] ?? '';
$filter_customer = isset($_GET['customer']) ? intval($_GET['customer']) : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$where = [];

if($search!=''){
    $search = mysqli_real_escape_string($mysqli,$search);
    $where[]="(
        q.quotation_no LIKE '%$search%' OR
        q.po_number LIKE '%$search%' OR
        c.name LIKE '%$search%'
    )";
}

if($filter_customer>0){
    $where[]="q.customer_id=$filter_customer";
}

if($date_from!=''){
    $where[]="q.date_quot >= '$date_from'";
}
if($date_to!=''){
    $where[]="q.date_quot <= '$date_to'";
}

$where_sql = count($where)>0 ? "WHERE ".implode(" AND ",$where) : "";

/* ================= QUERY ================= */
$query = "
SELECT 
q.*,
c.name AS customer_name,
(SELECT COUNT(*) FROM quotation_items qi WHERE qi.quotation_id=q.id) as item_count,
(SELECT description_quot FROM quotation_items WHERE quotation_id=q.id ORDER BY item_no LIMIT 1) as first_description
FROM quotations q
LEFT JOIN customers c ON q.customer_id=c.id
$where_sql
ORDER BY q.date_quot DESC
";

$res = mysqli_query($mysqli,$query);

include 'header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-3">
<h3>📄 Daftar Quotation</h3>
<a href="quotations_create.php" class="btn btn-primary">
<i class="fas fa-plus"></i> Buat Quotation
</a>
</div>

<div class="card shadow-sm">
<div class="card-body">

<div class="table-responsive">
<table id="quotationTable" class="table table-bordered table-hover nowrap" width="100%">

<thead class="thead-light">
<tr>
<th>No</th>
<th>Quotation No</th>
<th>Tanggal</th>
<th>Customer</th>
<th>Description</th>
<th>Subtotal</th>
<th>PPN</th>
<th>Total</th>
<th>Items</th>
<th>Aksi</th>
</tr>
</thead>

<tbody>
<?php
$no=1;
while($row=mysqli_fetch_assoc($res)):

$subtotal=floatval($row['subtotal']);
$ppn=floatval($row['ppn'] ?? 0);
$total=floatval($row['total']);
$item_count = $row['item_count'] + 1;
?>

<tr>
<td><?= $no++ ?></td>

<td>
<a href="javascript:void(0)"
   class="text-primary font-weight-bold view-quotation-detail"
   data-id="<?= $row['id'] ?>"
   data-no="<?= htmlspecialchars($row['quotation_no']) ?>">
   <?= htmlspecialchars($row['quotation_no']) ?>
</a>
</td>

<td data-order="<?= strtotime($row['date_quot']) ?>">
<?= date('d/m/Y',strtotime($row['date_quot'])) ?>
</td>

<td><?= htmlspecialchars($row['customer_name']) ?></td>


<td><?= htmlspecialchars($row['first_description'] ?? '-') ?></td>

<td data-order="<?= $subtotal ?>">
Rp <?= number_format($subtotal,0,',','.') ?>
</td>

<td data-order="<?= $ppn ?>">
Rp <?= number_format($ppn,0,',','.') ?>
</td>

<td data-order="<?= $total ?>">
<strong>Rp <?= number_format($total,0,',','.') ?></strong>
</td>

<td class="text-center">
<span class="badge badge-info"><?= $item_count ?></span>
</td>

<td class="text-center align-middle">
    <div class="btn-group btn-group-sm" role="group">
        <a href="quotations_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
            <i class="fas fa-eye"></i>
        </a>
        <a href="quotations_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i>
        </a>
        <a href="quotations_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i>
        </a>
        <button type="button" class="btn btn-danger btn-sm"
            data-toggle="modal" data-target="#deleteModal"
            data-id="<?php echo $row['id']; ?>"
            data-quotation-no="<?php echo htmlspecialchars($row['quotation_no']); ?>">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</td>

</tr>

<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</div>
</div>


<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal">
<div class="modal-dialog modal-dialog-centered">
<form method="post">
<input type="hidden" name="delete_id" id="delete_id">
<div class="modal-content">
<div class="modal-header bg-danger text-white">
<h5>Konfirmasi Hapus</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
Yakin hapus quotation <strong id="delNo"></strong> ?
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button type="submit" class="btn btn-danger">Hapus</button>
</div>
</div>
</form>
</div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function(){

$('#quotationTable').DataTable({
responsive:true,
pageLength:10,
lengthMenu:[10,25,50,100],
order:[[2,'desc']],
dom:'Bfrtip',
buttons:[
{extend:'excel',className:'btn btn-success btn-sm'},
{extend:'print',className:'btn btn-secondary btn-sm'}
],
language:{
search:"Cari:",
lengthMenu:"Tampilkan _MENU_",
info:"Menampilkan _START_ - _END_ dari _TOTAL_ data",
paginate:{previous:"Prev",next:"Next"}
}
});

$('#deleteModal').on('show.bs.modal',function(e){
var btn=$(e.relatedTarget);
$('#delete_id').val(btn.data('id'));
$('#delNo').text(btn.data('no'));
});

});
$(document).on('click','.view-quotation-detail',function(){

var id=$(this).data('id');
var no=$(this).data('no');

$('#detailQuotationNo').text(no);
$('#quotationDetailModal').modal('show');

$('#detailItemsBody').html(
'<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>'
);

$.get('get_quotation_detail.php',{id:id},function(res){

var data=JSON.parse(res);

$('#detailCustomer').text(data.customer);
$('#detailDate').text(data.date);

$('#detailSubtotal').text("Rp "+data.subtotal);
$('#detailPPN').text("Rp "+data.ppn);
$('#detailTotal').text("Rp "+data.total);

var html='';
data.items.forEach(function(item,index){
html+=`
<tr>
<td>${index+1}</td>
<td>${item.description}</td>
<td>${item.qty}</td>
<td>${item.unit}</td>
<td class="text-right">Rp ${item.unit_price}</td>
<td class="text-right">Rp ${item.amount}</td>
</tr>
`;
});

$('#detailItemsBody').html(html);

});

});
</script>
<!-- DETAIL MODAL -->
<div class="modal fade" id="quotationDetailModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-primary text-white">
<h5 class="modal-title">
<i class="fas fa-file-invoice"></i>
Detail Items - <span id="detailQuotationNo"></span>
</h5>
<button type="button" class="close text-white" data-dismiss="modal">
<span>&times;</span>
</button>
</div>

<div class="modal-body">

<div class="row mb-3">
<div class="col-md-6">
<strong>Customer</strong><br>
<span id="detailCustomer"></span>
</div>
<div class="col-md-6">
<strong>Invoice Date</strong><br>
<span id="detailDate"></span>
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered">
<thead class="thead-light">
<tr>
<th>No</th>
<th>Description</th>
<th>Qty</th>
<th>Unit</th>
<th>Unit Price</th>
<th>Amount</th>
</tr>
</thead>
<tbody id="detailItemsBody"></tbody>

<tfoot>
<tr>
<td colspan="5" class="text-right font-weight-bold">Subtotal:</td>
<td class="text-right font-weight-bold" id="detailSubtotal"></td>
</tr>
<tr>
<td colspan="5" class="text-right font-weight-bold">PPN:</td>
<td class="text-right font-weight-bold" id="detailPPN"></td>
</tr>
<tr class="bg-primary text-white">
<td colspan="5" class="text-right font-weight-bold">Total:</td>
<td class="text-right font-weight-bold" id="detailTotal"></td>
</tr>
</tfoot>

</table>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">
Tutup
</button>
</div>

</div>
</div>
</div>
<?php include 'footer.php'; ?>