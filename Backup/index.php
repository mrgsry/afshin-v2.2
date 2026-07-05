<?php
require_once 'functions.php';
require_once 'db.php';
require_login();
include 'header.php';

/* ================= BASIC KPI ================= */

$custCount = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT(*) c FROM customers"))['c'] ?? 0;
$invCount  = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT(*) c FROM invoices"))['c'] ?? 0;

$currentYear  = date('Y');
$currentMonth = date('Y-m');

$totalRevenueYear = mysqli_fetch_assoc(mysqli_query($mysqli,"
    SELECT SUM(total) s FROM invoices
    WHERE YEAR(created_at)='$currentYear'
"))['s'] ?? 0;

$monthRevenue = mysqli_fetch_assoc(mysqli_query($mysqli,"
    SELECT SUM(total) s FROM invoices
    WHERE DATE_FORMAT(created_at,'%Y-%m')='$currentMonth'
"))['s'] ?? 0;

/* ================= STATUS ================= */

$statusQuery = mysqli_query($mysqli,"
SELECT 
SUM(CASE WHEN faktur_inv=0 OR faktur_inv IS NULL OR faktur_inv='' THEN 1 ELSE 0 END) pending_count,
SUM(CASE WHEN faktur_inv!=0 AND faktur_inv IS NOT NULL AND faktur_inv!='' THEN 1 ELSE 0 END) complete_count
FROM invoices
");

$status = mysqli_fetch_assoc($statusQuery);
$pendingCount  = $status['pending_count'] ?? 0;
$completeCount = $status['complete_count'] ?? 0;

/* ================= REVENUE 6 BULAN ================= */

$revQuery = mysqli_query($mysqli,"
SELECT DATE_FORMAT(created_at,'%b %Y') bulan,
SUM(total) total
FROM invoices
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY YEAR(created_at), MONTH(created_at)
");

$revLabels=[]; 
$revData=[];
while($r=mysqli_fetch_assoc($revQuery)){
    $revLabels[]=$r['bulan'];
    $revData[]=$r['total'];
}

/* ================= TOP CUSTOMER PER TAHUN ================= */

$selectedYear = $_GET['year'] ?? $currentYear;

$topCustomerYearQuery = mysqli_query($mysqli,"
SELECT c.name, SUM(i.total) total
FROM invoices i
LEFT JOIN customers c ON i.customer_id = c.id
WHERE YEAR(i.created_at) = '$selectedYear'
GROUP BY c.name
ORDER BY total DESC
LIMIT 10
");

$topYearLabels=[]; 
$topYearData=[];
while($r=mysqli_fetch_assoc($topCustomerYearQuery)){
    $topYearLabels[]=$r['name'] ?? 'Unknown';
    $topYearData[]=$r['total'] ?? 0;
}

/* ================= CUSTOMER MONTHLY ================= */

$customerList = mysqli_query($mysqli,"SELECT id,name FROM customers ORDER BY name ASC");
$selectedCustomer = $_GET['customer_id'] ?? 0;

$monthlyLabels=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthlyData=array_fill(0,12,0);

if($selectedCustomer>0){
    $monthlyQuery = mysqli_query($mysqli,"
    SELECT MONTH(created_at) m, SUM(total) total
    FROM invoices
    WHERE customer_id=".(int)$selectedCustomer."
    AND YEAR(created_at)='$currentYear'
    GROUP BY MONTH(created_at)
    ");
    while($r=mysqli_fetch_assoc($monthlyQuery)){
        $monthlyData[$r['m']-1]=$r['total'];
    }
}
?>

<style>
.gradient-card{
border-radius:16px;
color:#fff;
padding:20px;
position:relative;
overflow:hidden;
transition:.3s;
}
.gradient-card:hover{
transform:translateY(-4px);
box-shadow:0 12px 30px rgba(0,0,0,0.15);
}
.gradient-blue{background:linear-gradient(135deg,#667eea,#764ba2);}
.gradient-green{background:linear-gradient(135deg,#11998e,#38ef7d);}
.gradient-orange{background:linear-gradient(135deg,#f7971e,#ffd200);}
.gradient-red{background:linear-gradient(135deg,#ee0979,#ff6a00);}
.gradient-warning{background:linear-gradient(135deg,#f46b45,#eea849);}
.gradient-success{background:linear-gradient(135deg,#56ab2f,#a8e063);}

.card-icon{
font-size:2rem;
opacity:.25;
position:absolute;
right:20px;
top:20px;
}

.chart-card{
border-radius:16px;
box-shadow:0 6px 20px rgba(0,0,0,0.05);
padding:20px;
background:#fff;
}

.chart-wrapper{
height:300px;
}

.section-title{
font-weight:600;
margin-bottom:15px;
}

/* ===== FIX MODAL Z-INDEX ===== */
.modal {
    z-index: 1060 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}

/* Loader harus dibawah modal */
#page-loader {
    z-index: 1040 !important;
}
</style>

<div class="container-fluid">

<!-- KPI ROW -->
<div class="row">

<div class="col-lg-3 col-md-6 mb-4">
<div class="gradient-card gradient-blue">
<i class="fas fa-users card-icon"></i>
<div>Customers</div>
<h4><?= $custCount ?></h4>
</div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
<div class="gradient-card gradient-green">
<i class="fas fa-file-invoice card-icon"></i>
<div>Total Invoices</div>
<h4><?= $invCount ?></h4>
</div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
<div class="gradient-card gradient-orange">
<i class="fas fa-chart-line card-icon"></i>
<div>Revenue This Month</div>
<h4>Rp <?= number_format($monthRevenue,0,',','.') ?></h4>
</div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
<div class="gradient-card gradient-red">
<i class="fas fa-coins card-icon"></i>
<div>Revenue <?= $currentYear ?></div>
<h4>Rp <?= number_format($totalRevenueYear,0,',','.') ?></h4>
</div>
</div>

</div>

<!-- STATUS ROW -->
<div class="row">

<div class="col-md-6 mb-4">
<div class="gradient-card gradient-warning"
     data-toggle="modal" data-target="#pendingModal"
     style="cursor:pointer;">
<i class="fas fa-clock card-icon"></i>
<div>Invoice Pending</div>
<h4><?= $pendingCount ?></h4>
<small>Klik untuk lihat detail</small>
</div>
</div>

<div class="col-md-6 mb-4">
<div class="gradient-card gradient-success"
     data-toggle="modal"
     data-target="#completeModal"
     style="cursor:pointer;">
<i class="fas fa-check-circle card-icon"></i>
<div>Invoice Complete</div>
<h4><?= $completeCount ?></h4>
<small>Klik untuk lihat detail</small>
</div>
</div>

</div>

<!-- CHART ROW 1 -->
<div class="row">

<div class="col-md-6 mb-4">
<div class="chart-card">
<div class="section-title">Revenue Trend (6 Months)</div>
<div class="chart-wrapper">
<canvas id="revenueChart"></canvas>
</div>
</div>
</div>

<div class="col-md-6 mb-4">
<div class="chart-card">
<div class="d-flex justify-content-between align-items-center mb-3">
<div class="section-title mb-0">
Top Customer (<?= $selectedYear ?>)
</div>
<form method="GET">
<select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
<?php for($y=date('Y');$y>=date('Y')-5;$y--): ?>
<option value="<?= $y ?>" <?= $selectedYear==$y?'selected':'' ?>>
<?= $y ?>
</option>
<?php endfor; ?>
</select>
</form>
</div>
<div class="chart-wrapper">
<canvas id="topCustomerChart"></canvas>
</div>
</div>
</div>

</div>

<!-- CHART ROW 2 -->
<div class="row">

<div class="col-md-12 mb-4">
<div class="chart-card">
<div class="section-title">
Customer Monthly Transaction (<?= $currentYear ?>)
</div>

<form method="GET" class="mb-3">
<select name="customer_id" class="form-control form-control-sm" onchange="this.form.submit()">
<option value="0">-- Select Customer --</option>
<?php while($c=mysqli_fetch_assoc($customerList)): ?>
<option value="<?= $c['id'] ?>" <?= $selectedCustomer==$c['id']?'selected':'' ?>>
<?= htmlspecialchars($c['name']) ?>
</option>
<?php endwhile; ?>
</select>
</form>

<div class="chart-wrapper">
<canvas id="customerMonthlyChart"></canvas>
</div>
</div>
</div>

</div>

</div>

<!-- MODAL -->
<div class="modal fade" id="pendingModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-warning">
<h5 class="modal-title">
<i class="fas fa-clock"></i> Invoice Pending
</h5>
<button type="button" class="close" data-dismiss="modal">
<span>&times;</span>
</button>
</div>

<div class="modal-body">
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead class="thead-light">
<tr>
<th>No</th>
<th>No Invoice</th>
<th>Tanggal</th>
<th>Customer</th>
<th>Total</th>
<th>Alasan</th>
</tr>
</thead>
<tbody id="pendingInvoiceBody">
<tr>
<td colspan="6" class="text-center">
<i class="fas fa-spinner fa-spin"></i> Memuat data...
</td>
</tr>
</tbody>
</table>
</div>
</div>

</div>
</div>
</div>

<!-- MODAL COMPLETE -->
<div class="modal fade" id="completeModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5 class="modal-title">
<i class="fas fa-check-circle"></i> Invoice Complete
</h5>
<button type="button" class="close text-white" data-dismiss="modal">
<span>&times;</span>
</button>
</div>

<div class="modal-body">
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead class="thead-light">
<tr>
<th>No</th>
<th>No Invoice</th>
<th>Tanggal</th>
<th>Customer</th>
<th>Total</th>
<th>Status</th>
</tr>
</thead>
<tbody id="completeInvoiceBody">
<tr>
<td colspan="6" class="text-center">
<i class="fas fa-spinner fa-spin"></i> Memuat data...
</td>
</tr>
</tbody>
</table>
</div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

// Revenue Chart
new Chart(document.getElementById('revenueChart'),{
type:'line',
data:{
labels:<?= json_encode($revLabels) ?>,
datasets:[{
data:<?= json_encode($revData) ?>,
borderColor:'#667eea',
backgroundColor:'rgba(102,126,234,0.2)',
fill:true,
tension:0.4
}]
},
options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}
});

// Top Customer
new Chart(document.getElementById('topCustomerChart'),{
type:'bar',
data:{
labels:<?= json_encode($topYearLabels) ?>,
datasets:[{
data:<?= json_encode($topYearData) ?>,
backgroundColor:'#38ef7d'
}]
},
options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}
});

// Monthly Chart
new Chart(document.getElementById('customerMonthlyChart'),{
type:'line',
data:{
labels:<?= json_encode($monthlyLabels) ?>,
datasets:[{
data:<?= json_encode($monthlyData) ?>,
borderColor:'#ee0979',
backgroundColor:'rgba(238,9,121,0.15)',
fill:true,
tension:0.4
}]
},
options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}
});

// Pending Modal AJAX
$('#pendingModal').on('show.bs.modal', function () {

$('#pendingInvoiceBody').html(`
<tr>
<td colspan="6" class="text-center">
<i class="fas fa-spinner fa-spin"></i> Memuat data...
</td>
</tr>
`);

$.get('get_pending_invoice.php', function(response){

let data = JSON.parse(response);
let html = '';

if(data.length === 0){
html = `<tr><td colspan="6" class="text-center text-success">
Tidak ada invoice pending 🎉
</td></tr>`;
}else{
data.forEach((item,index)=>{
html += `
<tr>
<td>${index+1}</td>
<td>${item.invoice_no}</td>
<td>${item.date}</td>
<td>${item.customer}</td>
<td>Rp ${item.total}</td>
<td><span class="badge badge-danger">${item.reason}</span></td>
</tr>`;
});
}

$('#pendingInvoiceBody').html(html);
});

});

/* ===== FIX MODAL FREEZE ===== */
$(document).on('show.bs.modal', function () {

    // Paksa hide loader kalau masih aktif
    const loader = document.getElementById("page-loader");
    if(loader){
        loader.classList.add("hide");
    }

});


// COMPLETE MODAL AJAX
$('#completeModal').on('show.bs.modal', function () {

$('#completeInvoiceBody').html(`
<tr>
<td colspan="6" class="text-center">
<i class="fas fa-spinner fa-spin"></i> Memuat data...
</td>
</tr>
`);

$.get('get_complete_invoice.php', function(response){

let data = JSON.parse(response);
let html = '';

if(data.length === 0){
html = `<tr><td colspan="6" class="text-center text-warning">
Belum ada invoice complete
</td></tr>`;
}else{
data.forEach((item,index)=>{
html += `
<tr>
<td>${index+1}</td>
<td>${item.invoice_no}</td>
<td>${item.date}</td>
<td>${item.customer}</td>
<td>Rp ${item.total}</td>
<td><span class="badge badge-success">${item.status}</span></td>
</tr>`;
});
}

$('#completeInvoiceBody').html(html);

});

});
/* Bersihkan backdrop dobel */
$(document).on('hidden.bs.modal', function () {
    if ($('.modal-backdrop').length > 1) {
        $('.modal-backdrop').not(':first').remove();
    }
    $('body').removeClass('modal-open');
});
</script>

<?php include 'footer.php'; ?>