<?php
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
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $npwp = $_POST['npwp'] ?? '';
    
    // Validasi input
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

$error_msg = flash_get('error');
$success_msg = flash_get('success');
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
      
        <div class="col-xl-8 col-lg-10">
           <!-- Quick Tips -->
            <div class="card shadow-sm mt-0 border-left-info">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <i class="fas fa-lightbulb fa-2x text-info"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-info"><strong>Tips:</strong></h6>
                            <p class="mb-0 text-muted small">
                                Pastikan data customer diperbarui dengan lengkap untuk memudahkan proses pembuatan invoice, quotation, dan dokumen lainnya. Gunakan tombol <strong>"Parse Alamat"</strong> untuk memecah alamat yang sudah ada menjadi detail, atau <strong>"Bangun Alamat Lengkap"</strong> untuk menggabungkan detail menjadi format standar.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Tambahan -->
            <div class="card shadow-sm mt-4 border-left-warning">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <i class="fas fa-history fa-2x text-warning"></i>
                        </div>
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
            <div class="card shadow-lg">
              
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-user-edit mr-2"></i> Edit Customer
                            </h4>
                            <p class="mb-0 opacity-75">Perbarui data customer <?php echo htmlspecialchars($row['name']); ?></p>
                        </div>
                        <div>
                            <span class="badge badge-light text-primary">
                                <i class="fas fa-hashtag mr-1"></i>
                                <?php echo htmlspecialchars($row['customer_no']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if($error_msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_msg); ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success_msg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success_msg); ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" id="customerForm">
                        <!-- Informasi Customer -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-info-circle mr-2"></i> Informasi Customer
                                </h5>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="font-weight-bold">Customer Name *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-building text-primary"></i>
                                            </span>
                                        </div>
                                        <input type="text" name="name" id="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($row['name']); ?>"
                                               placeholder="Masukkan nama customer" required
                                               oninvalid="this.setCustomValidity('Nama customer harus diisi')"
                                               oninput="this.setCustomValidity('')">
                                    </div>
                                    <small class="form-text text-muted">Nama lengkap perusahaan atau perorangan</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pic" class="font-weight-bold">Person In Charge (PIC)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-user text-primary"></i>
                                            </span>
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
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-address-book mr-2"></i> Kontak & Informasi Legal
                                </h5>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telephone" class="font-weight-bold">Telephone</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-phone text-primary"></i>
                                            </span>
                                        </div>
                                        <input type="tel" name="telephone" id="telephone" class="form-control" 
                                               value="<?php echo htmlspecialchars($row['telephone'] ?? ''); ?>"
                                               placeholder="Contoh: 021-12345678">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="npwp" class="font-weight-bold">NPWP</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-file-invoice-dollar text-primary"></i>
                                            </span>
                                        </div>
                                        <input type="text" name="npwp" id="npwp" class="form-control" 
                                               value="<?php echo htmlspecialchars($row['npwp'] ?? ''); ?>"
                                               placeholder="00.000.000.0-000.000">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alamat -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-map-marker-alt mr-2"></i> Alamat
                                </h5>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="address" class="font-weight-bold">Alamat Lengkap *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-map-pin text-primary"></i>
                                            </span>
                                        </div>
                                        <textarea name="address" id="address" class="form-control" 
                                                  rows="5" placeholder="Masukkan alamat lengkap (jalan, nomor, RT/RW, kelurahan, kecamatan, kota, provinsi, kode pos)" required
                                                  oninvalid="this.setCustomValidity('Alamat customer harus diisi')"
                                                  oninput="this.setCustomValidity('')"><?php echo htmlspecialchars($row['address'] ?? ''); ?></textarea>
                                    </div>
                                    <small class="form-text text-muted">Untuk keperluan pengiriman dan penagihan. Format: Jalan, No, RT/RW, Kelurahan, Kecamatan, Kota, Provinsi, Kode Pos</small>
                                </div>
                            </div>
                            
                            <!-- Break Down Alamat (Opsional untuk detail lebih lanjut) -->
                           
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-md-12">
                                <hr class="my-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-muted">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Field dengan tanda * wajib diisi
                                        </span>
                                    </div>
                                    <div>
                                        <a href="customers_list.php" class="btn btn-outline-secondary btn-lg mr-2">
                                            <i class="fas fa-times mr-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-save mr-1"></i> Update Customer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
           
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                
                if(i < parts.length - 1) {
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
    
    // Parse existing address into components
    $('#parseAddress').click(function() {
        var address = $('#address').val().trim();
        if (!address) {
            showToast('Tidak ada alamat untuk di-parse', 'warning');
            return;
        }
        
        // Simple parsing logic - adjust as needed
        var addressLower = address.toLowerCase();
        
        // Try to extract street (first part before comma)
        var parts = address.split(',');
        if (parts.length > 0) {
            $('#street').val(parts[0].trim());
        }
        
        // Try to extract RT/RW
        var rtRwMatch = address.match(/(rt|rw)[\s:]*(\d+\s*\/?\s*\d+)/i);
        if (rtRwMatch) {
            $('#rt_rw').val(rtRwMatch[2].replace(/\s/g, ''));
        }
        
        // Try to extract kelurahan
        var kelurahanMatch = address.match(/kel[.\s]*(.+?)(?=,|\s+kec|$)/i);
        if (kelurahanMatch) {
            $('#kelurahan').val(kelurahanMatch[1].trim());
        }
        
        // Try to extract kecamatan
        var kecamatanMatch = address.match(/kec[.\s]*(.+?)(?=,|\s+kota|$)/i);
        if (kecamatanMatch) {
            $('#kecamatan').val(kecamatanMatch[1].trim());
        }
        
        // Try to extract city
        var cityMatch = address.match(/kota\s*(.+?)(?=,|\s+prov|$)/i);
        if (cityMatch) {
            $('#city').val(cityMatch[1].trim());
        }
        
        // Try to extract province
        var provinceMatch = address.match(/prov[.\s]*(.+?)(?=,|\s+\d|$)/i);
        if (provinceMatch) {
            $('#province').val(provinceMatch[1].trim());
        }
        
        // Try to extract postal code (5 digits)
        var postalMatch = address.match(/\b\d{5}\b/);
        if (postalMatch) {
            $('#postal_code').val(postalMatch[0]);
        }
        
        showToast('Alamat berhasil di-parse! Periksa dan sesuaikan jika perlu.', 'success');
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
        // Remove existing toasts
        $('.toast-alert').remove();
        
        // Create toast element
        var toast = $('<div class="toast-alert"></div>');
        var icon = type === 'success' ? 'check-circle' : 
                   type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        var bgColor = type === 'success' ? 'bg-success' : 
                      type === 'warning' ? 'bg-warning' : 'bg-info';
        
        toast.html(`
            <div class="toast ${bgColor} text-white show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
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
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            toast.remove();
        }, 5000);
    }
    
    // Auto-parse existing address on page load
    if ($('#address').val().trim()) {
        // Optionally auto-parse on load
        // $('#parseAddress').click();
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

.border-left-warning {
    border-left: 4px solid #f6c23e !important;
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

/* Alert styling */
.alert {
    border: none;
    border-radius: 8px;
}

.alert-danger {
    background-color: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
    border-left: 4px solid #e74c3c;
}

.alert-success {
    background-color: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
    border-left: 4px solid #2ecc71;
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
#buildAddress:hover, #parseAddress:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

#buildAddress:hover {
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
}

#parseAddress:hover {
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
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

/* Additional info styling */
.border-left-warning .card-body ul {
    margin-bottom: 0;
}

.border-left-warning .card-body li {
    margin-bottom: 0.25rem;
}

.border-left-warning .card-body li:last-child {
    margin-bottom: 0;
}
</style>

<?php include 'footer.php'; ?>