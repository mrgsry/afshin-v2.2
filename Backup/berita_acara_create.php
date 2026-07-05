<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'functions.php';
require_login();

if(!$mysqli){
die("Database connection failed");
}

/* ===============================
   GENERATE NOMOR BA
================================*/

function bulan_romawi($bulan){
$romawi=[1=>'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
return $romawi[$bulan];
}

function generate_ba_no($mysqli){

$year=date('Y');
$month=bulan_romawi(date('n'));

$q=mysqli_query($mysqli,"
SELECT nomor_ba
FROM berita_acara
ORDER BY id DESC
LIMIT 1
");

$num=1;

if($r=mysqli_fetch_assoc($q)){
$parts=explode('/',$r['nomor_ba']);
$num=(int)$parts[0]+1;
}

$num=str_pad($num,3,'0',STR_PAD_LEFT);

return "$num/BAST-ART/$month/$year";
}

$nomor_ba=generate_ba_no($mysqli);


/* ===============================
   SAVE DATA
================================*/

if($_SERVER['REQUEST_METHOD']=='POST'){

$nomor_ba=$_POST['nomor_ba'];
$tanggal_ba=$_POST['tanggal_ba'];
$customer=$_POST['customer_name'];
$alamat=$_POST['customer_alamat'];
$lokasi=$_POST['lokasi'];
$pekerjaan=$_POST['pekerjaan'];
$po=$_POST['po_number'];
$invoice_id=$_POST['invoice_id'];
$pelaksana=$_POST['pelaksana'];
$prod_code=$_POST['prod_code'];
$ship_by=$_POST['ship_by'];

$items=json_decode($_POST['items_json'],true);

$mysqli->begin_transaction();

try{

$first=$items[0];

$stmt=$mysqli->prepare("
INSERT INTO berita_acara
(
nomor_ba,
tanggal_ba,
pekerjaan,
description,
qty,
um,
keterangan,
po_number,
invoice_id,
customer_name,
customer_alamat,
lokasi,
pelaksana,
prod_code,
ship_by
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
'sssssisssssssss',
$nomor_ba,
$tanggal_ba,
$pekerjaan,
$first['description'],
$first['qty'],
$first['unit'],
$first['keterangan'],
$po,
$invoice_id,
$customer,
$alamat,
$lokasi,
$pelaksana,
$prod_code,
$ship_by
);

$stmt->execute();

$ba_id=$mysqli->insert_id;


/* INSERT ITEM TAMBAHAN */

if(count($items)>1){

$stmt_item=$mysqli->prepare("
INSERT INTO berita_acara_items
(
berita_acara_id,
item_no,
description,
qty,
unit,
keterangan
)
VALUES (?,?,?,?,?,?)
");

foreach($items as $i=>$item){

if($i==0) continue;

$no=$i+1;

$stmt_item->bind_param(
'iisiss',
$ba_id,
$no,
$item['description'],
$item['qty'],
$item['unit'],
$item['keterangan']
);

$stmt_item->execute();

}

}

$mysqli->commit();

header("Location: berita_acara_list.php");
exit;

}catch(Exception $e){

$mysqli->rollback();
echo $e->getMessage();

}

}


/* ===============================
   AMBIL DATA PO
================================*/

$po_query=mysqli_query($mysqli,"
SELECT
i.id,
i.po_number,
i.created_at,
c.name,
c.address
FROM invoices i
LEFT JOIN customers c ON c.id=i.customer_id
WHERE i.po_number IS NOT NULL
ORDER BY i.created_at DESC
");

$po_list=[];
$po_header=[];
$po_items=[];

while($row=mysqli_fetch_assoc($po_query)){

$po_list[$row['po_number']]=$row['id'];

$po_header[$row['po_number']]=[
'customer'=>$row['name'],
'alamat'=>$row['address'],
'invoice'=>$row['id'],
'tanggal'=>date('Y-m-d',strtotime($row['created_at']))
];

}


if(!empty($po_list)){

$ids=implode(',',array_values($po_list));

$q=mysqli_query($mysqli,"
SELECT
invoice_id,
description,
qty,
satuan
FROM invoice_items
WHERE invoice_id IN ($ids)
");

while($r=mysqli_fetch_assoc($q)){

$po=array_search($r['invoice_id'],$po_list);

$po_items[$po][]=[
'description'=>$r['description'],
'qty'=>$r['qty'],
'unit'=>$r['satuan'],
'keterangan'=>'OK'
];

}

}

include 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-4">

<div class="card shadow-lg border-0">

<div class="card-header bg-primary text-white">
<h5 class="mb-0">Buat Berita Acara Serah Terima</h5>
</div>

<div class="card-body">

<form method="POST" id="formBA">

<input type="hidden" name="items_json" id="items_json">
<input type="hidden" name="invoice_id" id="invoice_id">

<div class="row g-3">

<div class="col-md-4">
<label>Nomor BA</label>
<input name="nomor_ba" class="form-control" value="<?= $nomor_ba ?>" readonly>
</div>

<div class="col-md-4">
<label>Tanggal BA</label>
<input type="date" name="tanggal_ba" id="tanggal_ba" class="form-control">
</div>

<div class="col-md-4">
<label>PO Number</label>
<select id="po_select" name="po_number" class="form-select">
<option value="">-- Pilih PO --</option>
<?php foreach($po_list as $po=>$id): ?>
<option value="<?= $po ?>"><?= $po ?></option>
<?php endforeach ?>
</select>
</div>

</div>

<hr>

<div class="row g-3">

<div class="col-md-6">
<label>Customer</label>
<input name="customer_name" id="customer" class="form-control">
</div>

<div class="col-md-6">
<label>Lokasi</label>
<input name="lokasi" id="lokasi" class="form-control">
</div>

</div>

<div class="mt-3">
<label>Alamat</label>
<textarea name="customer_alamat" id="alamat" class="form-control"></textarea>
</div>

<div class="mt-3">
<label>Pekerjaan</label>
<input name="pekerjaan" id="pekerjaan" class="form-control">
</div>

<div class="row g-3 mt-2">

<div class="col-md-6">
<label>Prod Code</label>
<input name="prod_code" class="form-control">
</div>

<div class="col-md-6">
<label>Ship By</label>
<input name="ship_by" class="form-control">
</div>

</div>

<div class="mt-3">
<label>Pelaksana</label>
<input name="pelaksana" class="form-control" value="CV. Afshin Raya Teknik">
</div>

<hr>

<div class="table-responsive">

<table class="table table-bordered align-middle" id="itemsTable">

<thead class="table-light">

<tr>
<th width="5%">No</th>
<th>Description</th>
<th width="10%">Qty</th>
<th width="12%">U/M</th>
<th width="15%">Keterangan</th>
</tr>

</thead>

<tbody>

<tr>

<td>1</td>

<td>
<textarea class="form-control item-desc"></textarea>
</td>

<td>
<input type="number" class="form-control item-qty" value="1">
</td>

<td>

<select class="form-select item-unit">

<option>Unit</option>
<option>Pcs</option>
<option>Set</option>
<option>Lot</option>
<option>Buah</option>
<option>Kg</option>
<option>Meter</option>

</select>

</td>

<td>
<input class="form-control item-ket" value="OK">
</td>

</tr>

</tbody>

</table>

</div>

<div class="text-end mt-4">

<button type="button" class="btn btn-success px-4" onclick="saveBA()">
Simpan Berita Acara
</button>

</div>

</form>

</div>

</div>

</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>

const PO_HEADER=<?php echo json_encode($po_header); ?>;
const PO_ITEMS=<?php echo json_encode($po_items); ?>;

$("#po_select").change(function(){

let po=$(this).val();

let h=PO_HEADER[po];

$("#customer").val(h.customer);
$("#alamat").val(h.alamat);
$("#lokasi").val(h.alamat);
$("#invoice_id").val(h.invoice);

$("#tanggal_ba").val(h.tanggal);

loadItems(po);

});


function loadItems(po){

let items=PO_ITEMS[po];

if(!items) return;

$("#itemsTable tbody").empty();

items.forEach((i,index)=>{

let row=`

<tr>

<td>${index+1}</td>

<td>
<textarea class="form-control item-desc">${i.description}</textarea>
</td>

<td>
<input type="number" class="form-control item-qty" value="${i.qty}">
</td>

<td>

<select class="form-select item-unit">

<option ${i.unit=="Unit"?"selected":""}>Unit</option>
<option ${i.unit=="Pcs"?"selected":""}>Pcs</option>
<option ${i.unit=="Set"?"selected":""}>Set</option>
<option ${i.unit=="Lot"?"selected":""}>Lot</option>
<option ${i.unit=="Buah"?"selected":""}>Buah</option>
<option ${i.unit=="Kg"?"selected":""}>Kg</option>
<option ${i.unit=="Meter"?"selected":""}>Meter</option>

</select>

</td>

<td>
<input class="form-control item-ket" value="${i.keterangan}">
</td>

</tr>

`;

$("#itemsTable tbody").append(row);

});

if(items.length>0){
$("#pekerjaan").val(items[0].description);
}

}


function saveBA(){

let items=[];

$("#itemsTable tbody tr").each(function(){

items.push({

description:$(this).find(".item-desc").val(),
qty:$(this).find(".item-qty").val(),
unit:$(this).find(".item-unit").val(),
keterangan:$(this).find(".item-ket").val()

});

});

$("#items_json").val(JSON.stringify(items));

$("#formBA").submit();

}

</script>

<?php include 'footer.php'; ?>