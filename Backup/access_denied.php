<?php
require_once 'functions.php';
require_login();
include 'header.php';
?>
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card shadow-lg border-0">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="fas fa-ban fa-5x text-danger mb-3"></i>
                        <h1 class="text-danger">Access Denied</h1>
                    </div>
                    
                    <h4 class="mb-3">Anda tidak memiliki izin untuk mengakses halaman ini</h4>
                    
                    <p class="text-muted mb-4">
                        Akses Anda terbatas pada module tertentu yang telah diberikan oleh administrator.
                        Silakan hubungi administrator sistem jika Anda memerlukan akses tambahan.
                    </p>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home mr-2"></i> Kembali ke Dashboard
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            User: <strong><?php echo htmlspecialchars(current_user()['username']); ?></strong> | 
                            Role: <strong><?php echo htmlspecialchars(current_user()['role']); ?></strong>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>