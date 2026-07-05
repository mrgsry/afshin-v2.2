<?php
require_once 'functions.php';
require_login();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $email = $_POST['email'] ?? '';
    // Mendukung multiple email yang dipisahkan koma
    $email = $_POST['email'] ?? '';
    $cc_emails_raw = $_POST['cc_email'] ?? '';
    if (empty($cc_emails_raw)) {
        $cc_email = '';
    } else {
        // Hapus spasi berlebih dan simpan sebagai string CSV
        $cc_email = trim(preg_replace('/\s+/', '', $cc_emails_raw));
    }
    $npwp = $_POST['npwp'] ?? '';
    
    if(empty($name)) {
        flash_set('error', 'Nama Customer harus diisi');
        header('Location: customers_create.php');
        exit;
    }
    
    $customer_no = gen_customer_no($mysqli);
    $stmt = mysqli_prepare($mysqli, "INSERT INTO customers (customer_no, name, address, telephone, pic, email, cc_email, npwp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssssss', $customer_no, $name, $address, $telephone, $pic, $email, $cc_email, $npwp);
    
    if(mysqli_stmt_execute($stmt)) {
        flash_set('success', 'Customer berhasil disimpan');
        header('Location: customers_list.php'); 
        exit;
    } else {
        flash_set('error', 'Gagal menyimpan customer: ' . mysqli_error($mysqli));
    }
    mysqli_stmt_close($stmt);
}

include 'header.php';
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">

            <div class="card shadow">
                <div class="card-header text-white bg-primary">
                    <h4 class="mb-0">
                        <i class="fas fa-user-plus mr-2"></i> Create New Customer
                    </h4>
                    <small>Tambah data customer baru ke sistem</small>
                </div>

                <div class="card-body">
                    <form method="post" id="customerForm">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-info-circle mr-2"></i> Informasi Customer
                        </h5>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>

                            <div class="form-group col-md-6">
                                <label>PIC</label>
                                <input type="text" name="pic" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Email(s)</label>
                                <input type="text" name="email" class="form-control" placeholder="example@mail.com, other@mail.com">
                                <small class="text-muted">Separate multiple emails with comma</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label>CC Email(s)</label>
                                <input type="text" name="cc_email" class="form-control" placeholder="cc1@mail.com, cc2@mail.com">
                                <small class="text-muted">Separate multiple emails with comma</small>
                            </div>
                        </div>

                        <h5 class="text-primary mb-3">
                            <i class="fas fa-address-book mr-2"></i> Kontak
                        </h5>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Telephone</label>
                                <input type="text" name="telephone" id="telephone" class="form-control">
                            </div>

                            <div class="form-group col-md-6">
                                <label>NPWP</label>
                                <input type="text" name="npwp" id="npwp" class="form-control">
                            </div>
                        </div>

                        <h5 class="text-primary mb-3">
                            <i class="fas fa-map-marker-alt mr-2"></i> Alamat
                        </h5>

                        <div class="form-group">
                            <label>Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" rows="4" class="form-control" required></textarea>
                        </div>

                        <hr>

                        <div class="text-right">
                            <a href="customers_list.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4 border-left-info">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <i class="fas fa-lightbulb fa-2x text-info"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-info"><strong>Tips:</strong></h6>
                            <p class="mb-0 text-muted small">
                                Pastikan data customer diisi dengan lengkap untuk memudahkan proses pembuatan invoice, quotation, dan dokumen lainnya. Data yang lengkap juga membantu dalam pelacakan dan komunikasi dengan customer. Gunakan tombol "Bangun Alamat Lengkap" untuk menggabungkan detail alamat menjadi format standar.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
