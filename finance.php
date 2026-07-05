<?php
require_once 'functions.php';
require_once 'db.php';
require_login();

/* ================= EXPORT HANDLER ================= */

if(isset($_GET['export'])){

    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to'] ?? date('Y-m-d');

    $where = "WHERE DATE(i.created_at) BETWEEN '$from' AND '$to'";

    $query = mysqli_query($mysqli,"
        SELECT i.invoice_no,i.created_at,c.name,i.subtotal,i.ppn,i.pph,i.total
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id=c.id
        $where
        ORDER BY i.created_at DESC
    ");

    if($_GET['export']=='xls'){

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=finance_report.xls");

        echo "<table border='1'>";
        echo "<tr>
                <th>Invoice</th>
                <th>Tanggal</th>
                <th>Customer</th>
                <th>Subtotal</th>
                <th>PPN</th>
                <th>PPH</th>
                <th>Total</th>
              </tr>";

        while($row=mysqli_fetch_assoc($query)){
            echo "<tr>
                <td>{$row['invoice_no']}</td>
                <td>{$row['created_at']}</td>
                <td>{$row['name']}</td>
                <td>{$row['subtotal']}</td>
                <td>{$row['ppn']}</td>
                <td>{$row['pph']}</td>
                <td>{$row['total']}</td>
            </tr>";
        }

        echo "</table>";
        exit;
    }

    if($_GET['export']=='pdf'){

        require_once('tcpdf/tcpdf.php');

        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica','',9);

        $html="<h3>Finance Report</h3>
        <table border='1' cellpadding='4'>
        <tr>
        <th>Invoice</th>
        <th>Tanggal</th>
        <th>Customer</th>
        <th>Total</th>
        </tr>";

        while($row=mysqli_fetch_assoc($query)){
            $html.="<tr>
            <td>{$row['invoice_no']}</td>
            <td>{$row['created_at']}</td>
            <td>{$row['name']}</td>
            <td>{$row['total']}</td>
            </tr>";
        }

        $html.="</table>";

        $pdf->writeHTML($html);
        $pdf->Output('finance_report.pdf','I');
        exit;
    }
}

include 'header.php';

/* ================= FILTER ================= */

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$customer_id = $_GET['customer_id'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE DATE(i.created_at) BETWEEN '$from' AND '$to'";

if($customer_id!=''){
    $where .= " AND i.customer_id=".(int)$customer_id;
}

if($status=='pending'){
    $where .= " AND (i.faktur_inv IS NULL OR i.faktur_inv='')";
}
elseif($status=='complete'){
    $where .= " AND (i.faktur_inv IS NOT NULL AND i.faktur_inv!='')";
}

/* ================= QUERY ================= */

$query = mysqli_query($mysqli,"
SELECT i.*,c.name AS customer
FROM invoices i
LEFT JOIN customers c ON i.customer_id=c.id
$where
ORDER BY i.created_at DESC
");

$data=[];
$totalSubtotal=0;
$totalPPN=0;
$totalPPH=0;
$grandTotal=0;

while($row=mysqli_fetch_assoc($query)){
    $data[]=$row;
    $totalSubtotal+=$row['subtotal'];
    $totalPPN+=$row['ppn'];
    $totalPPH+=$row['pph'];
    $grandTotal+=$row['total'];
}

$customerList = mysqli_query($mysqli,"SELECT id,name FROM customers ORDER BY name ASC");
?>

<div class="container-fluid py-4">
<div class="container-fluid">
<h3><i class="fas fa-coins"></i> Finance Enterprise Report</h3>
</div>
</section>

<section class="content">
<div class="container-fluid">

<!-- FILTER -->
<div class="card card-outline card-primary">
<div class="card-body">
<form method="GET" class="row">

<div class="col-md-2 mb-2">
<label>Dari</label>
<input type="date" name="from" value="<?= $from ?>" class="form-control">
</div>

<div class="col-md-2 mb-2">
<label>Sampai</label>
<input type="date" name="to" value="<?= $to ?>" class="form-control">
</div>

<div class="col-md-3 mb-2">
<label>Customer</label>
<select name="customer_id" class="form-control">
<option value="">-- Semua --</option>
<?php while($c=mysqli_fetch_assoc($customerList)): ?>
<option value="<?= $c['id'] ?>" <?= $customer_id==$c['id']?'selected':'' ?>>
<?= htmlspecialchars($c['name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div class="col-md-2 mb-2">
<label>Status</label>
<select name="status" class="form-control">
<option value="">-- Semua --</option>
<option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
<option value="complete" <?= $status=='complete'?'selected':'' ?>>Complete</option>
</select>
</div>

<div class="col-md-3 mb-2 d-flex align-items-end">
<button class="btn btn-primary mr-2">
<i class="fas fa-search"></i>
</button>

<a href="finance_enterprise.php" class="btn btn-secondary mr-2">Reset</a>

<a href="?export=pdf&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-danger mr-2">
<i class="fas fa-file-pdf"></i>
</a>

<a href="?export=xls&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-success">
<i class="fas fa-file-excel"></i>
</a>
</div>

</form>
</div>
</div>

<!-- SUMMARY -->
<div class="row">
<div class="col-md-3">
<div class="small-box bg-info">
<div class="inner">
<h4>Rp <?= number_format($totalSubtotal,0,',','.') ?></h4>
<p>Total Subtotal</p>
</div>
</div>
</div>

<div class="col-md-3">
<div class="small-box bg-warning">
<div class="inner">
<h4>Rp <?= number_format($totalPPN,0,',','.') ?></h4>
<p>Total PPN</p>
</div>
</div>
</div>

<div class="col-md-3">
<div class="small-box bg-danger">
<div class="inner">
<h4>Rp <?= number_format($totalPPH,0,',','.') ?></h4>
<p>Total PPH</p>
</div>
</div>
</div>

<div class="col-md-3">
<div class="small-box bg-success">
<div class="inner">
<h4>Rp <?= number_format($grandTotal,0,',','.') ?></h4>
<p>Grand Total</p>
</div>
</div>
</div>
</div>

<!-- TABLE -->
<div class="card">
<div class="card-body table-responsive">

<table id="financeTable" class="table table-bordered table-striped nowrap" style="width:100%">
<thead>
<tr>
<th>No</th>
<th>Invoice</th>
<th>Tanggal</th>
<th>Customer</th>
<th>Subtotal</th>
<th>PPN</th>
<th>PPH</th>
<th>Total</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach($data as $i=>$row): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= $row['invoice_no'] ?></td>
<td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
<td><?= htmlspecialchars($row['customer']) ?></td>
<td>Rp <?= number_format($row['subtotal'],0,',','.') ?></td>
<td>Rp <?= number_format($row['ppn'],0,',','.') ?></td>
<td>Rp <?= number_format($row['pph'],0,',','.') ?></td>
<td><b>Rp <?= number_format($row['total'],0,',','.') ?></b></td>
<td>
<?php if(empty($row['faktur_inv'])): ?>
<span class="badge badge-warning">Pending</span>
<?php else: ?>
<span class="badge badge-success">Complete</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

</div>
</section>
</div>

<script>
$(function(){
$('#financeTable').DataTable({
responsive:true,
autoWidth:false,
scrollX:true,
pageLength:25
});
});
</script>

<?php include 'footer.php'; ?>