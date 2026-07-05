<?php
require_once 'functions.php';
require_login();

$po=mysqli_query($mysqli,"
SELECT i.po_number,i.invoice_no,c.name
FROM invoices i
JOIN customers c ON i.customer_id=c.id
ORDER BY i.created_at DESC
");

include 'header.php';
?>

<div class="container-fluid py-4">
<h3>Create Admin Invoice</h3>
</section>

<section class="content">

<form method="post" action="admin_invoice_save.php">

<div class="card card-primary">

<div class="card-body">

<div class="row">

<div class="col-md-4">

<label>Admin Invoice No</label>

<input type="text"
name="admin_invoice_no"
class="form-control">

</div>

<div class="col-md-4">

<label>Date</label>

<input type="date"
name="created_at"
class="form-control"
value="<?= date('Y-m-d') ?>">

</div>

<div class="col-md-4">

<label>Due Date</label>

<input type="date"
name="due_date"
class="form-control">

</div>

</div>

<hr>

<h5>Items</h5>

<table class="table table-bordered" id="itemTable">

<thead>

<tr>

<th>PO</th>
<th>Customer</th>
<th>Invoice</th>
<th>Qty</th>
<th>Price</th>
<th>Total</th>
<th></th>

</tr>

</thead>

<tbody>

<tr>

<td>

<select name="po_number[]" class="form-control poSelect">

<option value="">Select PO</option>

<?php while($p=mysqli_fetch_assoc($po)): ?>

<option
value="<?= $p['po_number'] ?>"
data-customer="<?= $p['name'] ?>"
data-invoice="<?= $p['invoice_no'] ?>">

<?= $p['po_number'] ?>

</option>

<?php endwhile; ?>

</select>

</td>

<td>

<input type="text"
name="customer_name[]"
class="form-control customer"
readonly>

</td>

<td>

<input type="text"
name="invoice_no[]"
class="form-control invoice_no"
readonly>

</td>

<td>

<input type="number"
name="qty[]"
class="form-control qty"
value="1">

</td>

<td>

<input type="number"
name="price[]"
class="form-control price"
value="350000">

</td>

<td>

<input type="text"
class="form-control total"
readonly>

</td>

<td>

<button type="button"
class="btn btn-danger removeRow">
X
</button>

</td>

</tr>

</tbody>

</table>

<button type="button"
id="addRow"
class="btn btn-primary">

Add Item

</button>

</div>

<div class="card-footer">

<button class="btn btn-success">
Save Invoice
</button>

</div>

</div>

</form>

</section>
<script>

$(document).on("change",".poSelect",function(){

let row=$(this).closest("tr")

let opt=$(this).find(":selected")

row.find(".customer").val(opt.data("customer"))

row.find(".invoice_no").val(opt.data("invoice"))

})


function calc(row){

let qty=row.find(".qty").val()

let price=row.find(".price").val()

let total=qty*price

row.find(".total").val(
total.toLocaleString('id-ID')
)

}

$(document).on("keyup change",".qty,.price",function(){

let row=$(this).closest("tr")

calc(row)

})


$("#addRow").click(function(){

let row=$("#itemTable tbody tr:first").clone()

row.find("input").val("")

row.find(".qty").val(1)

row.find(".price").val(350000)

$("#itemTable tbody").append(row)

})


$(document).on("click",".removeRow",function(){

if($("#itemTable tbody tr").length>1){

$(this).closest("tr").remove()

}

})

</script>
</div>