<?php
require_once 'functions.php';

$id = $_GET['id'];

$q=mysqli_query($mysqli,"
SELECT * FROM admin_invoices
WHERE id=$id
");

$inv=mysqli_fetch_assoc($q);

$items=mysqli_query($mysqli,"
SELECT * FROM admin_invoice_items
WHERE admin_invoice_id=$id
");

?>
<!DOCTYPE html>
<html>

<head>

<style>

@page{
size:A4;
margin:0;
}

body{
font-family:Arial;
margin:0;
}

/* PRINT BUTTON */

.print-btn{
padding:10px 20px;
background:#2c1fa3;
color:white;
border:none;
margin:20px;
cursor:pointer;
}

@media print{
.print-btn{
display:none;
}
}

/* INVOICE */

.invoice{
width:210mm;
min-height:297mm;
padding:40px;
box-sizing:border-box;
}

/* HEADER */

.header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:20px;
}

.logo{
font-size:28px;
font-weight:bold;
color:#2a5db0;
}

.invoice-title{
font-size:50px;
font-weight:bold;
}

/* BILL */

.bill{
margin-top:10px;
line-height:1.6;
}

/* INFO BAR */

.info{
border:2px solid #333;
padding:10px;
display:flex;
justify-content:space-between;
margin-top:20px;
}

/* TABLE */

table{
width:100%;
border-collapse:collapse;
margin-top:25px;
}

th{
background:#2c1fa3;
color:white;
padding:12px;
text-align:left;
}

td{
padding:12px;
border-bottom:1px solid #ddd;
}

.right{
text-align:right;
}

/* TOTAL */

.total-box{
width:300px;
margin-left:auto;
margin-top:20px;
}

.total-row{
display:flex;
justify-content:space-between;
padding:10px;
}

.total-row:last-child{
background:#2c1fa3;
color:white;
font-weight:bold;
}

/* FOOTER */

.footer{
margin-top:60px;
display:flex;
justify-content:space-between;
}

.signature{
text-align:center;
}

</style>

</head>

<body>

<button onclick="window.print()" class="print-btn">
Print Invoice
</button>

<div class="invoice">

<!-- HEADER -->

<div class="header">

<div class="logo">
HNET SOLUTIONS
</div>

<div class="invoice-title">
INVOICE
</div>

</div>

<!-- BILL TO -->

<div class="bill">

<b>BILL TO</b><br>

CV Afshin Raya Teknik<br>

</div>

<!-- INFO -->

<div class="info">

<div>
Invoice # <?= $inv['admin_invoice_no'] ?>
</div>

<div>
Invoice Date : <?= $inv['created_at'] ?>
</div>

<div>
Due Date : <?= $inv['due_date'] ?>
</div>

</div>

<!-- TABLE -->

<table>

<tr>

<th width="50">NO</th>
<th>DESCRIPTION</th>
<th width="120">PRICE</th>
<th width="80">QTY</th>
<th width="120">TOTAL</th>

</tr>

<?php
$no=1;
$subtotal=0;

while($d=mysqli_fetch_assoc($items)):

$subtotal+=$d['total'];
?>

<tr>

<td><?= $no++ ?></td>

<td>
<?= $d['customer_name'] ?> -
<?= $d['po_number'] ?> -
<?= $d['invoice_no'] ?>
</td>

<td class="right">
Rp <?= number_format($d['price'],0,',','.') ?>
</td>

<td class="right">
<?= $d['qty'] ?>
</td>

<td class="right">
Rp <?= number_format($d['total'],0,',','.') ?>
</td>

</tr>

<?php endwhile; ?>

</table>

<!-- TOTAL -->

<div class="total-box">

<div class="total-row">

<div>SUBTOTAL</div>
<div>Rp <?= number_format($subtotal,0,',','.') ?></div>

</div>

<div class="total-row">

<div>Total</div>
<div>Rp <?= number_format($subtotal,0,',','.') ?></div>

</div>

</div>

<!-- FOOTER -->

<div class="footer">

<div>

<h4>TERM AND CONDITIONS</h4>

Please make the payment by the due date to the account below.

<h4>PAYMENT METHOD</h4>

Bank : Mandiri<br>
Account Name : Yussi Sanjaya<br>
Account Number : 1560021864543

</div>

<div class="signature">

<br><br><br>

Hormat Kami,<br><br><br><br><br><br>

<b>Yussi Sanjaya</b>

</div>

</div>

</div>

</body>

</html>