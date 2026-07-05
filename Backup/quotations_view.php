<?php
require_once 'functions.php';
require_login();
// Pastikan koneksi database di-include dan variabel $mysqli tersedia
// Jika Anda masih mengalami error koneksi, pastikan path di bawah sudah benar.


$id = intval($_GET['id'] ?? 0);
// Menggunakan $mysqli yang diinisialisasi dari file db_connection.php
$res = mysqli_query($mysqli, "SELECT q.*, c.name as customer_name, c.customer_no FROM quotations q LEFT JOIN customers c ON q.customer_id=c.id WHERE q.id=$id");

if(!$quote = mysqli_fetch_assoc($res)){ flash_set('Not found'); header('Location: quotations_list.php'); exit; }
$items = mysqli_query($mysqli, "
    SELECT 
        item_no,
        description_quot,
        qty,
        satuan_quot,
        unit_price,
        amount
    FROM quotation_items
    WHERE quotation_id=$id
    ORDER BY item_no ASC
");
include 'header.php';
?>
<style>
/* Kontainer Overlay */
#loadingOverlay {
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0, 0, 0, 0.6); 
    z-index: 1050; 
    display: none; 
    justify-content: center;
    align-items: center;
}

/* Loader Style Sederhana (Spinner) */
.loader {
    border: 5px solid #f3f3f3; 
    border-top: 5px solid #3498db; 
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 0.5s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
<div class="container-fluid">
    <h4>Quotation <?php echo htmlspecialchars($quote['quotation_no']); ?></h4>
    <p>Customer: <?php echo htmlspecialchars($quote['customer_name'].' ('.$quote['customer_no'].')'); ?></p>
    <table class="table table-bordered">
    <thead>
        <tr><th>No</th><th>Description</th><th>Qty</th><th>Satuan</th><th>Unit Price</th><th>Amount</th></tr>
    </thead>
    <tbody>
    <?php while($it = mysqli_fetch_assoc($items)): ?>
    <tr>
      <td><?php echo $it['item_no']; ?></td>
      <td><?php echo htmlspecialchars($it['description_quot']); ?></td>
      <td><?php echo $it['qty']; ?></td>
      <td><?php echo htmlspecialchars($it['satuan_quot']); ?></td>
      <td><?php echo number_format($it['unit_price'],0); ?></td>
      <td><?php echo number_format($it['amount'],0); ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
    </table>
    <p>Subtotal: Rp <?php echo number_format($quote['subtotal'],0,',','.'); ?></p>
    <p>Discount: Rp <?php echo number_format($quote['discount'],0,',','.'); ?></p>
    <p>PPN: Rp <?php echo number_format($quote['ppn'],0,',','.'); ?></p>
    <p>Total: Rp <?php echo number_format($quote['total'],0,',','.'); ?></p>
    <p>Note: <?php echo nl2br(htmlspecialchars($quote['note'])); ?></p>
    <p>
        <button type="button" class="btn btn-sm btn-success" id="printBtn" data-id="<?php echo $quote['id']; ?>">
            Print
        </button>
    </p>
</div>

<div id="loadingOverlay">
    <div class="loader" id="loader"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    
    // Fungsi untuk menampilkan loading overlay
    function showLoading() {
        $('#loadingOverlay').css('display', 'flex'); 
    }
    
    // Fungsi untuk mereset UI (Menghilangkan Loading dan Mengaktifkan Tombol)
    function resetUI() {
        $('#loadingOverlay').hide();
        $('#printBtn').prop('disabled', false).text('Print');
    }

    // 🌟 PERBAIKAN UTAMA: Event listener untuk menangani navigasi Balik (Back button) 🌟
    // Gunakan window.onpageshow untuk mereset UI ketika halaman dimuat atau dipulihkan dari cache.
    window.onpageshow = function(event) {
        // Cek jika halaman dipulihkan dari BFCache (persisted: true)
        if (event.persisted) {
            resetUI();
        }
        // Juga reset saat pemuatan biasa, jika diperlukan (tergantung browser)
        resetUI();
    };

    // Mengikat event click pada tombol Print
    $('#printBtn').on('click', function(e){
        e.preventDefault(); // Hentikan aksi default
        
        let quoteId = $(this).data('id');
        
        // 1. Tampilkan loading
        showLoading();
        
        // 2. Disable tombol
        $(this).prop('disabled', true).text('Processing...');

        // 3. Tunda aksi selama 3000 ms (3 detik)
        setTimeout(function() {
            // 4. Setelah 3 detik, lakukan navigasi
            window.location.href = `quotations_print.php?id=${quoteId}`;

        }, 1000); 
    });
});
</script>
<?php include 'footer.php'; ?>