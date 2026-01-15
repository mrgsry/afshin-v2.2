<?php
require_once 'functions.php';
require_login();

// Cek apakah user adalah admin
if(current_user()['role'] !== 'admin'){ 
    flash_set('Access denied'); 
    header('Location: index.php'); 
    exit; 
}

// Daftar module yang tersedia
$available_modules = [
    'quotation' => 'Quotation',
    'invoice' => 'Invoice', 
    'travel_document' => 'Travel Document (Surat Jalan)',
    'service_report' => 'Service Report',
    'berita_acara' => 'Berita Acara'
];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = $_POST['username']; 
    $full_name = $_POST['full_name']; 
    $role = $_POST['role']; 
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    // Ambil module yang dipilih
    $selected_modules = isset($_POST['modules']) ? $_POST['modules'] : [];
    
    // Validasi input
    if(empty($username) || empty($password)) {
        flash_set('error', 'Username dan Password harus diisi');
        header('Location: users_create.php');
        exit;
    }
    
    // Cek apakah username sudah ada
    $check_stmt = mysqli_prepare($mysqli, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($check_stmt, 's', $username);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if(mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_close($check_stmt);
        flash_set('error', 'Username sudah digunakan');
        header('Location: users_create.php');
        exit;
    }
    mysqli_stmt_close($check_stmt);
    
    // Mulai transaksi
    $mysqli->begin_transaction();
    
    try {
        // Insert user ke tabel users
        $stmt = mysqli_prepare($mysqli, "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $username, $password, $full_name, $role);
        
        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menyimpan user: " . mysqli_stmt_error($stmt));
        }
        
        $user_id = mysqli_insert_id($mysqli);
        mysqli_stmt_close($stmt);
        
        // Insert module akses ke tabel user_modules (jika ada tabelnya)
        if(!empty($selected_modules)) {
            // Cek apakah tabel user_modules ada
            $check_table = mysqli_query($mysqli, "SHOW TABLES LIKE 'user_modules'");
            if(mysqli_num_rows($check_table) > 0) {
                // Insert setiap module yang dipilih
                foreach($selected_modules as $module) {
                    if(in_array($module, array_keys($available_modules))) {
                        $module_stmt = mysqli_prepare($mysqli, "INSERT INTO user_modules (user_id, module_name) VALUES (?, ?)");
                        mysqli_stmt_bind_param($module_stmt, 'is', $user_id, $module);
                        mysqli_stmt_execute($module_stmt);
                        mysqli_stmt_close($module_stmt);
                    }
                }
            } else {
                // Jika tabel tidak ada, buat dulu
                $create_table = mysqli_query($mysqli, "
                    CREATE TABLE IF NOT EXISTS user_modules (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        module_name VARCHAR(50) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                
                // Insert module akses setelah tabel dibuat
                if($create_table) {
                    foreach($selected_modules as $module) {
                        if(in_array($module, array_keys($available_modules))) {
                            $module_stmt = mysqli_prepare($mysqli, "INSERT INTO user_modules (user_id, module_name) VALUES (?, ?)");
                            mysqli_stmt_bind_param($module_stmt, 'is', $user_id, $module);
                            mysqli_stmt_execute($module_stmt);
                            mysqli_stmt_close($module_stmt);
                        }
                    }
                }
            }
        }
        
        $mysqli->commit();
        flash_set('success', 'User berhasil dibuat');
        header('Location: users_manage.php'); 
        exit;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set('error', 'Terjadi kesalahan: ' . $e->getMessage());
        header('Location: users_create.php');
        exit;
    }
}

include 'header.php';
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus"></i> Create New User
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" id="createUserForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" name="username" id="username" class="form-control" required 
                                           placeholder="Masukkan username" autocomplete="off">
                                    <small class="form-text text-muted">Username untuk login</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" name="password" id="password" class="form-control" required 
                                           placeholder="Masukkan password" autocomplete="new-password">
                                    <small class="form-text text-muted">Minimal 6 karakter</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name">Full Name</label>
                                    <input type="text" name="full_name" id="full_name" class="form-control" 
                                           placeholder="Masukkan nama lengkap">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">Role *</label>
                                    <select name="role" id="role" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <small class="form-text text-muted">Admin memiliki akses penuh</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Module Access Selection -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="modules">Module Access *</label>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row">
                                                <?php foreach($available_modules as $key => $module): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input module-checkbox" 
                                                               id="module_<?php echo $key; ?>" 
                                                               name="modules[]" 
                                                               value="<?php echo $key; ?>">
                                                        <label class="custom-control-label" for="module_<?php echo $key; ?>">
                                                            <i class="fas 
                                                                <?php 
                                                                switch($key) {
                                                                    case 'quotation': echo 'fa-file-contract'; break;
                                                                    case 'invoice': echo 'fa-file-invoice-dollar'; break;
                                                                    case 'travel_document': echo 'fa-truck'; break;
                                                                    case 'service_report': echo 'fa-clipboard-list'; break;
                                                                    case 'berita_acara': echo 'fa-file-alt'; break;
                                                                    default: echo 'fa-folder';
                                                                }
                                                                ?> 
                                                                mr-2"></i>
                                                            <?php echo htmlspecialchars($module); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-sm btn-outline-primary select-all-btn">
                                                    <i class="fas fa-check-square"></i> Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary deselect-all-btn">
                                                    <i class="fas fa-square"></i> Deselect All
                                                </button>
                                            </div>
                                            <small class="form-text text-muted mt-2">Pilih module yang dapat diakses user. Admin otomatis memiliki semua akses.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Save User
                                    </button>
                                    <a href="users_manage.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select all modules
    $('.select-all-btn').click(function() {
        $('.module-checkbox').prop('checked', true);
    });
    
    // Deselect all modules
    $('.deselect-all-btn').click(function() {
        $('.module-checkbox').prop('checked', false);
    });
    
    // Validasi form
    $('#createUserForm').submit(function(e) {
        // Validasi password minimal 6 karakter
        var password = $('#password').val();
        if(password.length < 6) {
            alert('Password minimal 6 karakter');
            e.preventDefault();
            return false;
        }
        
        // Validasi minimal 1 module dipilih untuk role user
        var role = $('#role').val();
        if(role === 'user') {
            var modulesChecked = $('.module-checkbox:checked').length;
            if(modulesChecked === 0) {
                alert('Harap pilih minimal 1 module untuk user');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
    
    // Auto select all modules for admin role
    $('#role').change(function() {
        var role = $(this).val();
        if(role === 'admin') {
            $('.module-checkbox').prop('checked', true);
            $('.module-checkbox').prop('disabled', true);
        } else {
            $('.module-checkbox').prop('disabled', false);
        }
    });
    
    // Username validation - no spaces
    $('#username').on('input', function() {
        var username = $(this).val();
        if(username.indexOf(' ') >= 0) {
            $(this).val(username.replace(/\s/g, ''));
            alert('Username tidak boleh mengandung spasi');
        }
    });
});
</script>

<style>
.card {
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.form-group label {
    font-weight: 600;
    color: #495057;
}

.module-checkbox:checked + label {
    color: #28a745;
    font-weight: 600;
}

.custom-control-input:focus ~ .custom-control-label::before {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.custom-control-input:checked ~ .custom-control-label::before {
    border-color: #28a745;
    background-color: #28a745;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
}

.select-all-btn, .deselect-all-btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}
</style>

<?php include 'footer.php'; ?>