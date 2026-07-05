<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';
require_login();
require_once 'functions.php';
require_login();

$id = intval($_GET['id'] ?? 0);
$res = mysqli_query($mysqli, "SELECT * FROM customers WHERE id=$id LIMIT 1");

if(!$row = mysqli_fetch_assoc($res)) { 
    flash_set('error', 'Customer not found'); 
    header('Location: customers_list.php'); 
    exit; 
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name      = trim($_POST['name']      ?? '');
    $address   = trim($_POST['address']   ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $pic       = trim($_POST['pic']       ?? '');
    $npwp      = preg_replace('/[^\d]/', '', $_POST['npwp'] ?? ''); // simpan angka saja

    if(empty($name)) {
        flash_set('error', 'Nama Customer harus diisi');
        header('Location: customers_edit.php?id=' . $id);
        exit;
    }

    $stmt = mysqli_prepare($mysqli, "UPDATE customers SET name=?, address=?, telephone=?, pic=?, npwp=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $address, $telephone, $pic, $npwp, $id);

    if(mysqli_stmt_execute($stmt)) {
        flash_set('success', 'Customer berhasil diperbarui');
        header('Location: customers_list.php'); 
        exit;
    } else {
        flash_set('error', 'Gagal memperbarui customer: ' . mysqli_error($mysqli));
        header('Location: customers_edit.php?id=' . $id);
        exit;
    }
    mysqli_stmt_close($stmt);
}

$error_msg   = flash_get('error');
$success_msg = flash_get('success');
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">

            <!-- Tips -->
            <div class="card shadow-sm mt-0 border-left-info mb-3">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3"><i class="fas fa-lightbulb fa-2x text-info"></i></div>
                        <div>
                            <h6 class="mb-1 text-info"><strong>Tips:</strong></h6>
                            <p class="mb-0 text-muted small">
                                Pastikan data customer diperbarui dengan lengkap untuk memudahkan proses pembuatan invoice, quotation, dan dokumen lainnya.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Sistem -->
            <div class="card shadow-sm mt-0 border-left-warning mb-3">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3"><i class="fas fa-history fa-2x text-warning"></i></div>
                        <div>
                            <h6 class="mb-1 text-warning"><strong>Informasi Sistem:</strong></h6>
                            <ul class="mb-0 text-muted small pl-3">
                                <li>Customer Number: <strong><?php echo htmlspecialchars($row['customer_no']); ?></strong> (tidak dapat diubah)</li>
                                <li>Data dibuat pada: <strong><?php echo date('d/m/Y H:i', strtotime($row['created_at'] ?? 'now')); ?></strong></li>
                                <?php if(isset($row['updated_at'])): ?>
                                    <li>Terakhir diperbarui: <strong><?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?></strong></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="card shadow-lg">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="fas fa-user-edit mr-2"></i> Edit Customer</h4>
                            <p class="mb-0 opacity-75">Perbarui data customer <?php echo htmlspecialchars($row['name']); ?></p>
                        </div>
                        <span class="badge badge-light text-primary">
                            <i class="fas fa-hashtag mr-1"></i><?php echo htmlspecialchars($row['customer_no']); ?>
                        </span>
                    </div>
                </div>

                <div class="card-body">

                    <?php if($error_msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_msg); ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <?php if($success_msg): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success_msg); ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="customerForm" novalidate>

                        <!-- Informasi Customer -->
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-info-circle mr-2"></i> Informasi Customer
                        </h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-building text-primary"></i></span>
                                        </div>
                                        <input type="text" name="name" id="name" class="form-control"
                                               value="<?php echo htmlspecialchars($row['name']); ?>"
                                               placeholder="Masukkan nama customer" required>
                                    </div>
                                    <small class="form-text text-muted">Nama lengkap perusahaan atau perorangan</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pic" class="font-weight-bold">Person In Charge (PIC)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-user text-primary"></i></span>
                                        </div>
                                        <input type="text" name="pic" id="pic" class="form-control"
                                               value="<?php echo htmlspecialchars($row['pic'] ?? ''); ?>"
                                               placeholder="Masukkan nama PIC">
                                    </div>
                                    <small class="form-text text-muted">Nama orang yang bisa dihubungi</small>
                                </div>
                            </div>
                        </div>

                        <!-- Kontak & Legal -->
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-address-book mr-2"></i> Kontak & Informasi Legal
                        </h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telephone" class="font-weight-bold">Telephone</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-phone text-primary"></i></span>
                                        </div>
                                        <input type="tel" name="telephone" id="telephone" class="form-control"
                                               value="<?php echo htmlspecialchars($row['telephone'] ?? ''); ?>"
                                               placeholder="Contoh: 021-12345678"
                                               maxlength="20">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="npwp" class="font-weight-bold">NPWP</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-file-invoice-dollar text-primary"></i></span>
                                        </div>
                                        <input type="text"
                                               name="npwp"
                                               id="npwp"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars(preg_replace('/[^\d]/', '', $row['npwp'] ?? '')); ?>"
                                               placeholder="Contoh: 0009809327409372094"
                                               inputmode="numeric"
                                               maxlength="20"
                                               autocomplete="off">
                                    </div>
                                    <small class="form-text text-muted">Hanya angka, tanpa titik atau strip</small>
                                </div>
                            </div>
                        </div>

                        <!-- Alamat -->
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-map-marker-alt mr-2"></i> Alamat
                        </h5>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="address" class="font-weight-bold">Alamat Lengkap <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-map-pin text-primary"></i></span>
                                        </div>
                                        <textarea name="address" id="address" class="form-control" rows="5"
                                                  placeholder="Masukkan alamat lengkap (jalan, nomor, RT/RW, kelurahan, kecamatan, kota, provinsi, kode pos)"
                                                  required><?php echo htmlspecialchars($row['address'] ?? ''); ?></textarea>
                                    </div>
                                    <small class="form-text text-muted">Format: Jalan, No, RT/RW, Kelurahan, Kecamatan, Kota, Provinsi, Kode Pos</small>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="text-muted small">
                                <i class="fas fa-info-circle mr-1"></i> Field dengan tanda <span class="text-danger">*</span> wajib diisi
                            </span>
                            <div>
                                <a href="customers_list.php" class="btn btn-outline-secondary btn-lg mr-2">
                                    <i class="fas fa-times mr-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save mr-1"></i> Update Customer
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    /* ===================================================
       NPWP — hanya angka, TIDAK ada formatting apapun
       Semua handler ditulis bersih tanpa kode lama
    =================================================== */
    var $npwp = $('#npwp');

    // Blokir karakter non-angka saat keypress
    $npwp.on('keypress', function (e) {
        var ch = String.fromCharCode(e.which);
        if (!/[0-9]/.test(ch)) {
            e.preventDefault();
        }
    });

    // Bersihkan saat paste
    $npwp.on('paste', function (e) {
        e.preventDefault();
        var pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        var clean  = pasted.replace(/[^\d]/g, '').substring(0, 20);
        $(this).val(clean);
    });

    // Bersihkan saat input (misal drag-drop text)
    $npwp.on('input', function () {
        var cursor = this.selectionStart;
        var clean  = $(this).val().replace(/[^\d]/g, '').substring(0, 20);
        $(this).val(clean);
        // Kembalikan posisi kursor
        try { this.setSelectionRange(cursor, cursor); } catch(e) {}
    });

    /* ===================================================
       TELEPHONE — format xxxx-xxxx-xxxx
    =================================================== */
    $('#telephone').on('input', function () {
        var digits = $(this).val().replace(/[^\d]/g, '');
        var formatted = '';
        if (digits.length <= 4) {
            formatted = digits;
        } else if (digits.length <= 8) {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4);
        } else {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4, 8) + '-' + digits.substring(8, 12);
        }
        $(this).val(formatted);
    });

    /* ===================================================
       FORM VALIDATION
    =================================================== */
    $('#customerForm').on('submit', function (e) {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        var isValid = true;

        if ($('#name').val().trim() === '') {
            markInvalid('#name', 'Nama customer harus diisi');
            isValid = false;
        }

        if ($('#address').val().trim() === '') {
            markInvalid('#address', 'Alamat customer harus diisi');
            isValid = false;
        }

        var npwp = $('#npwp').val().trim();
        if (npwp !== '' && !/^\d{1,20}$/.test(npwp)) {
            markInvalid('#npwp', 'NPWP hanya boleh berisi angka (maks 20 digit)');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 120
            }, 400);
        }
    });

    function markInvalid(selector, msg) {
        $(selector).addClass('is-invalid');
        $(selector).closest('.input-group').after(
            '<div class="invalid-feedback">' + msg + '</div>'
        );
    }

    /* ===================================================
       AUTO FOCUS
    =================================================== */
    $('#name').focus();

});
</script>

<style>
.card {
    border-radius: 10px;
    border: none;
    animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.border-left-info    { border-left: 4px solid #17a2b8 !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }

.form-group label { font-weight: 600; color: #495057; margin-bottom: .5rem; }

.input-group-text {
    border-right: none;
    background-color: #f8f9fa;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 .2rem rgba(102,126,234,.25);
}

.input-group:focus-within .input-group-text {
    border-color: #667eea;
    background-color: #e9ecef;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.btn-lg { padding: .75rem 2rem; font-size: 1rem; border-radius: 8px; }

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    transition: all .25s;
}
.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1e9e8a 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(40,167,69,.3);
}

.btn-outline-secondary:hover { transform: translateY(-1px); }

/* Alerts */
.alert { border: none; border-radius: 8px; }
.alert-danger  { background: rgba(231,76,60,.1);  color: #e74c3c; border-left: 4px solid #e74c3c; }
.alert-success { background: rgba(46,204,113,.1); color: #2ecc71; border-left: 4px solid #2ecc71; }

/* Validation */
.invalid-feedback { display: block; margin-top: .25rem; font-size: .85rem; color: #e74c3c; }
.is-invalid       { border-color: #e74c3c !important; }
.is-invalid:focus { box-shadow: 0 0 0 .2rem rgba(231,76,60,.25) !important; }

/* Custom scrollbar on textarea */
.form-control::-webkit-scrollbar       { width: 6px; }
.form-control::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
.form-control::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
.form-control::-webkit-scrollbar-thumb:hover { background: #999; }

@media (max-width: 768px) {
    .d-flex.justify-content-between { flex-direction: column; gap: 1rem; }
    .d-flex.justify-content-between > div { width: 100%; text-align: center; }
    .btn-lg { width: 100%; margin-bottom: .5rem; }
}
</style>

<?php include 'footer.php'; ?>