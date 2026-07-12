<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Kas Operasional</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --brand-1: #610b0bad;
        --brand-2: #740202;
        --brand-bg: #eef1f8;
    }

    * {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    body {
        background: linear-gradient(160deg, var(--brand-bg) 0%, #e4e8f5 100%);
        min-height: 100vh;
        padding: clamp(16px, 4vw, 40px) 0;
    }

    .page-wrap {
        max-width: 560px;
        margin: 0 auto;
        padding: 0 16px;
    }

    .brand-logo-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 12px;
    }

    .brand-logo-wrap img {
        height: 52px;
        width: auto;
        object-fit: contain;
        filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.15));
        background: #fff;
        padding: 6px 10px;
        border-radius: 10px;
    }

    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 12px 32px -10px rgba(79, 70, 229, 0.25);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        color: #fff;
        border: none;
        padding: 28px 24px 22px;
        text-align: center;
        position: relative;
    }

    .card-header .header-icon {
        font-size: 1.9rem;
        display: block;
        margin-bottom: 6px;
    }

    .card-header h4 {
        font-weight: 700;
        margin: 0;
        font-size: 1.25rem;
    }

    .card-header p {
        margin: 4px 0 0;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .card-body {
        padding: clamp(20px, 5vw, 32px);
    }

    .form-label {
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
        margin-bottom: 6px;
    }

    .form-label .req {
        color: #ef4444;
    }

    .form-control,
    .form-select {
        border-radius: 12px;
        border: 1.5px solid #e5e7eb;
        padding: 10px 14px;
        font-size: 0.95rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--brand-1);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    textarea.form-control {
        resize: none;
    }

    .mb-field {
        margin-bottom: 1.2rem;
    }

    .photo-drop {
        position: relative;
    }

    .photo-preview {
        max-width: 100%;
        max-height: 220px;
        margin-top: 12px;
        border-radius: 12px;
        display: none;
        border: 1.5px solid #e5e7eb;
    }

    .form-text {
        font-size: 0.78rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        border: none;
        padding: 13px 30px;
        font-weight: 700;
        font-size: 1rem;
        border-radius: 12px;
        box-shadow: 0 8px 18px -6px rgba(79, 70, 229, 0.45);
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }

    .btn-primary:hover,
    .btn-primary:focus {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 10px 22px -6px rgba(79, 70, 229, 0.5);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 17, 27, 0.55);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
    }

    .loading-spinner {
        background: #fff;
        padding: 32px 40px;
        border-radius: 16px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
    }

    .modal-header {
        border: none;
        padding: 20px 24px;
    }

    .modal-header .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body {
        padding: 20px 24px;
        font-size: 0.95rem;
    }

    .modal-footer {
        border: none;
        padding: 0 24px 20px;
    }

    .modal-footer .btn {
        border-radius: 10px;
        font-weight: 600;
        padding: 8px 20px;
    }

    @media (max-width: 480px) {
        .card-header {
            padding: 24px 18px 18px;
        }

        .card-body {
            padding: 20px 16px;
        }

        .btn-primary {
            width: 100%;
        }
    }
    </style>
</head>

<body>
    <div class="page-wrap">

        <div class="card">
            <div class="card-header">
                <div class="brand-logo-wrap">
                    <img src="../../img/afshin2.png" alt="Logo">
                </div>
                <h4 class="mt-2">Input Kas Operasional</h4>
                <p>CV Afshin Raya Teknik</p>
            </div>
            <div class="card-body">
                <form id="cashflowForm" enctype="multipart/form-data">
                    <div class="mb-field">
                        <label for="transaction_date" class="form-label">Tanggal Transaksi <span
                                class="req">*</span></label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" required>
                    </div>

                    <div class="mb-field">
                        <label for="technician_name" class="form-label">Nama Teknisi <span class="req">*</span></label>
                        <input type="text" class="form-control" id="technician_name" name="technician_name"
                            placeholder="Masukkan nama teknisi" required maxlength="100">
                    </div>

                    <div class="mb-field">
                        <label for="category" class="form-label">Kategori <span class="req">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="BBM">⛽ BBM</option>
                            <option value="Tol">🛣️ Tol</option>
                            <option value="Sparepart">🔧 Sparepart</option>
                            <option value="Lainnya">📦 Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-field">
                        <label for="amount" class="form-label">Nominal (Rp) <span class="req">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="border-radius: 12px 0 0 12px;">Rp</span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                placeholder="Contoh: 50000" required min="1" style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>

                    <div class="mb-field">
                        <label for="description" class="form-label">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Keterangan tambahan..." maxlength="500"></textarea>
                    </div>

                    <div class="mb-field photo-drop">
                        <label for="photo" class="form-label">Foto Bukti (Opsional)</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-outline-secondary flex-fill"
                                onclick="selectPhotoSource('camera')">
                                <i class="bi bi-camera me-1"></i> Ambil Foto
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-fill"
                                onclick="selectPhotoSource('gallery')">
                                <i class="bi bi-images me-1"></i> Galeri
                            </button>
                        </div>
                        <input type="file" class="d-none" id="photoInput" name="photo" accept="image/*">
                        <img id="photoPreview" class="photo-preview" alt="Preview">
                        <div class="form-text text-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i>Maksimal 25MB. Format: JPG, PNG, GIF, WEBP
                        </div>
                    </div>

                    <div class="d-grid mt-4 gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save2 me-1"></i> Simpan Data
                        </button>
                        <a href="data.php" class="btn btn-outline-secondary">
                            <i class="bi bi-table me-1"></i> Lihat Transaksi Saya
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 mb-0 fw-semibold text-secondary">Menyimpan data...</p>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> Berhasil!</h5>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Data berhasil disimpan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal"
                        onclick="resetForm()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle-fill"></i> Error</h5>
                </div>
                <div class="modal-body">
                    <p id="errorMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Set today's date as default
    document.getElementById('transaction_date').valueAsDate = new Date();

    // Bootstrap 5 modal instances (no jQuery needed)
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));

    // Select photo source: 'camera' or 'gallery'
    function selectPhotoSource(source) {
        const photoInput = document.getElementById('photoInput');

        if (source === 'camera') {
            photoInput.setAttribute('capture', 'environment');
        } else {
            photoInput.removeAttribute('capture');
        }

        photoInput.click();
    }

    // Compressed file storage
    let compressedFile = null;

    /**
     * Compress image using Canvas API
     * Resizes to max 1920px and compresses to JPEG ~80% quality
     * This ensures uploads stay well under server limits
     */
    function compressImage(file) {
        return new Promise((resolve, reject) => {
            // Skip compression for small files (< 1MB) and non-compressible formats
            if (file.size < 1 * 1024 * 1024) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Calculate new dimensions (max 1920px on longest side)
                    const MAX_DIM = 1920;
                    let width = img.width;
                    let height = img.height;

                    if (width > MAX_DIM || height > MAX_DIM) {
                        if (width > height) {
                            height = Math.round((height * MAX_DIM) / width);
                            width = MAX_DIM;
                        } else {
                            width = Math.round((width * MAX_DIM) / height);
                            height = MAX_DIM;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert to JPEG blob with 80% quality
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            // Create a new File from the blob
                            const compressedFileName = file.name.replace(/\.[^.]+$/, '') +
                                '_compressed.jpg';
                            const compressedFileObj = new File([blob], compressedFileName, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            console.log('Compressed: ' + formatFileSize(file.size) + ' → ' +
                                formatFileSize(compressedFileObj.size));
                            resolve(compressedFileObj);
                        } else {
                            // Fallback to original if compression fails
                            resolve(file);
                        }
                    }, 'image/jpeg', 0.80);
                };
                img.onerror = function() {
                    resolve(file); // fallback to original
                };
                img.src = e.target.result;
            };
            reader.onerror = function() {
                resolve(file); // fallback to original
            };
            reader.readAsDataURL(file);
        });
    }

    // Photo preview, validation, and compression
    document.getElementById('photoInput').addEventListener('change', async function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('photoPreview');
        compressedFile = null; // Reset

        if (file) {
            // Validate file type first
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipe file tidak diizinkan! Hanya JPG, PNG, GIF, WEBP yang diperbolehkan.');
                this.value = '';
                preview.style.display = 'none';
                return;
            }

            // Validate original file size (max 50MB before compression)
            const maxOriginalSize = 50 * 1024 * 1024;
            if (file.size > maxOriginalSize) {
                alert('Ukuran file terlalu besar! Maksimal 50MB. File: ' + formatFileSize(file.size));
                this.value = '';
                preview.style.display = 'none';
                return;
            }

            // Compress the image
            try {
                compressedFile = await compressImage(file);

                // Show preview from compressed file
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(compressedFile);
            } catch (err) {
                // Fallback: use original file
                compressedFile = file;
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        } else {
            preview.style.display = 'none';
        }
    });

    // Helper function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    // Form submission
    document.getElementById('cashflowForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Build FormData manually to ensure all fields are included
        const form = this;
        const formData = new FormData();

        // Explicitly append each field
        formData.append('technician_name', form.technician_name.value.trim());
        formData.append('category', form.category.value.trim());
        formData.append('amount', form.amount.value.trim());
        formData.append('description', form.description.value.trim());
        formData.append('transaction_date', form.transaction_date.value.trim());

        // Append photo - use compressed version if available, otherwise original
        const photoInput = document.getElementById('photoInput');
        if (photoInput.files && photoInput.files[0]) {
            if (compressedFile) {
                formData.append('photo', compressedFile);
                console.log('Uploading compressed file: ' + formatFileSize(compressedFile.size));
            } else {
                formData.append('photo', photoInput.files[0]);
                console.log('Uploading original file: ' + formatFileSize(photoInput.files[0].size));
            }
        }

        // Show loading
        document.getElementById('loadingOverlay').style.display = 'flex';

        try {
            const response = await fetch('../api/submit.php', {
                method: 'POST',
                body: formData
                // Don't set Content-Type header - browser will set it automatically with boundary for multipart/form-data
            });

            const result = await response.json();

            // Hide loading
            document.getElementById('loadingOverlay').style.display = 'none';

            if (result.success) {
                successModal.show();
            } else {
                const errorMsg = result.errors ? result.errors.join('<br>') : result.message;
                document.getElementById('errorMessage').innerHTML = errorMsg;
                errorModal.show();
            }
        } catch (error) {
            // Hide loading
            document.getElementById('loadingOverlay').style.display = 'none';

            document.getElementById('errorMessage').innerHTML =
                'Terjadi kesalahan koneksi. Silakan coba lagi.';
            errorModal.show();
        }
    });

    function resetForm() {
        document.getElementById('cashflowForm').reset();
        document.getElementById('photoPreview').style.display = 'none';
        document.getElementById('transaction_date').valueAsDate = new Date();
    }
    </script>
</body>

</html>