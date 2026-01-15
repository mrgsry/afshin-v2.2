<?php
require_once 'functions.php';
require_login();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Cek akses module customer jika diperlukan
// require_module_access($mysqli, 'customer');

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $npwp = $_POST['npwp'] ?? '';
    
    // Validasi input
    if(empty($name)) {
        flash_set('error', 'Nama Customer harus diisi');
        header('Location: customers_create.php');
        exit;
    }
    
    $customer_no = gen_customer_no($mysqli);
    $stmt = mysqli_prepare($mysqli, "INSERT INTO customers (customer_no, name, address, telephone, pic, npwp) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssss', $customer_no, $name, $address, $telephone, $pic, $npwp);
    
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

                        <!-- Customer Info -->
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

                        <!-- Contact -->
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

                        <!-- Address -->
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-map-marker-alt mr-2"></i> Alamat
                        </h5>

                        <div class="form-group">
                            <label>Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" rows="4" class="form-control" required></textarea>
                        </div>

                        <hr>

                        <!-- Action -->
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

        </div>
    </div>
</div>
            
            <!-- Quick Tips -->
            <div class="card shadow-sm mt-4 border-left-info">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <i class="fas fa-lightbulb fa-2x text-info"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-info"><strong>Tips:</strong></h6>
                            <p class="mb-0 text-muted small">
                                Pastikan data customer diisi dengan lengkap untuk memudahkan proses pembuatan invoice, quotation, dan dokumen lainnya. Data yang lengkap juga membantu dalam pelacakan dan komunikasi dengan customer. <strong>Gunakan tombol "Bangun Alamat Lengkap"</strong> untuk menggabungkan detail alamat menjadi format standar.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
   // Format NPWP
    $('#npwp').on('input', function() {
        var npwp = $(this).val().replace(/[^\d]/g, '');
        if(npwp.length > 0) {
            // Format: 00.000.000.0-000.000
           
            for(var i = 0; i < parts.length; i++) {
                if(index >= npwp.length) break;
                
                formatted += npwp.substr(index, parts[i]);
                index += parts[i];
                
                if(i < parts.length - 8) {
                    if(i === 20) {
                        formatted += '-';
                    } else {
                        formatted += '.';
                    }
                }
            }
            $(this).val(formatted);
        }
    });
    
    // Format telephone
    $('#telephone').on('input', function() {
        var tel = $(this).val().replace(/[^\d]/g, '');
        if(tel.length > 0) {
            if(tel.length <= 4) {
                $(this).val(tel);
            } else if(tel.length <= 8) {
                $(this).val(tel.substring(0, 4) + '-' + tel.substring(4));
            } else {
                $(this).val(tel.substring(0, 4) + '-' + tel.substring(4, 8) + '-' + tel.substring(8, 12));
            }
        }
    });
    
    // Build full address from components
    $('#buildAddress').click(function() {
        var street = $('#street').val().trim();
        var rt_rw = $('#rt_rw').val().trim();
        var kelurahan = $('#kelurahan').val().trim();
        var kecamatan = $('#kecamatan').val().trim();
        var city = $('#city').val().trim();
        var province = $('#province').val().trim();
        var postal_code = $('#postal_code').val().trim();
        
        var addressParts = [];
        
        if (street) addressParts.push(street);
        if (rt_rw) addressParts.push("RT/RW " + rt_rw);
        if (kelurahan) addressParts.push("Kelurahan " + kelurahan);
        if (kecamatan) addressParts.push("Kecamatan " + kecamatan);
        if (city) addressParts.push(city);
        if (province) addressParts.push(province);
        if (postal_code) addressParts.push(postal_code);
        
        if (addressParts.length > 0) {
            var fullAddress = addressParts.join(', ');
            $('#address').val(fullAddress);
            
            // Show success message
            showToast('Alamat berhasil dibangun!', 'success');
        } else {
            showToast('Isi minimal satu field detail alamat', 'warning');
        }
    });
    
    // Auto-focus on name field
    $('#name').focus();
    
    // Form validation
    $('#customerForm').submit(function(e) {
        // Reset previous errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        var isValid = true;
        
        // Check name
        if($('#name').val().trim() === '') {
            $('#name').addClass('is-invalid');
            $('#name').after('<div class="invalid-feedback">Nama customer harus diisi</div>');
            isValid = false;
        }
        
        // Check address
        if($('#address').val().trim() === '') {
            $('#address').addClass('is-invalid');
            $('#address').after('<div class="invalid-feedback">Alamat customer harus diisi</div>');
            isValid = false;
        }
        
        // Check NPWP format if filled
        var npwp = $('#npwp').val().trim();
        if(npwp !== '' && !isValidNPWP(npwp)) {
            $('#npwp').addClass('is-invalid');
            $('#npwp').after('<div class="invalid-feedback">Format NPWP tidak valid. Format: 00.000.000.0-000.000</div>');
            isValid = false;
        }
        
        if(!isValid) {
            e.preventDefault();
            
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 500);
        }
    });
    
    
    
    function showToast(message, type = 'info') {
        // Create toast element
        var toast = $('<div class="toast-alert"></div>');
        var icon = type === 'success' ? 'check-circle' : 
                   type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        var bgColor = type === 'success' ? 'bg-success' : 
                      type === 'warning' ? 'bg-warning' : 'bg-info';
        
        toast.html(`
            <div class="toast ${bgColor} text-white show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 250px;">
                <div class="toast-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${icon} mr-2"></i>
                        <span>${message}</span>
                        <button type="button" class="btn-close btn-close-white ml-auto" onclick="$(this).closest('.toast').remove()"></button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        
        // Auto remove after 3 seconds
        setTimeout(function() {
            toast.remove();
        }, 3000);
    }
});
</script>

<style>
.card {
    border-radius: 10px;
    border: none;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.form-group label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.input-group-text {
    border-right: none;
    background-color: #f8f9fa;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.input-group:focus-within .input-group-text {
    border-color: #667eea;
    background-color: #e9ecef;
}

.btn-lg {
    padding: 0.75rem 2rem;
    font-size: 1rem;
    border-radius: 8px;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1e9e8a 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.font-weight-semibold {
    font-weight: 500;
}

/* Address breakdown card */
.card.border-light {
    border: 1px solid #e3e6f0 !important;
    border-radius: 8px;
}

.card.border-light .card-header {
    background-color: #f8f9fc !important;
    border-bottom: 1px solid #e3e6f0;
}

/* Toast notification */
.toast-alert .toast {
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-header .badge {
        margin-top: 0.5rem;
        align-self: flex-start;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between > div {
        width: 100%;
        text-align: center;
    }
    
    .btn-lg {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .col-md-12 .row > [class*="col-"] {
        margin-bottom: 1rem;
    }
    
    .col-md-12 .row > [class*="col-"]:last-child {
        margin-bottom: 0;
    }
}

/* Animation for form */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

/* Custom scrollbar */
.form-control::-webkit-scrollbar {
    width: 8px;
}

.form-control::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.form-control::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.form-control::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Hover effects */
#buildAddress:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
    transition: all 0.3s ease;
}

/* Invalid feedback styling */
.invalid-feedback {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #e74c3c;
}

.is-invalid {
    border-color: #e74c3c !important;
}

.is-invalid:focus {
    box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25) !important;
}
</style>

<?php include 'footer.php'; ?>