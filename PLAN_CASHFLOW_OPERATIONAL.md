# 📋 IMPLEMENTATION PLAN - CASHFLOW OPERATIONAL MODULE

## Timeline Estimasi: 2-3 Hari

---

## PHASE 1: DATABASE SETUP

### 1.1 Buat Tabel Database
```sql
CREATE TABLE cashflow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    technician_name VARCHAR(100) NOT NULL,
    category ENUM('BBM', 'Tol', 'Sparepart', 'Lainnya') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    photo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (transaction_date),
    INDEX idx_category (category),
    INDEX idx_technician (technician_name)
);
```

### 1.2 Tambah Function ke functions.php
```php
// Get all cashflow transactions
function get_cashflow_transactions($mysqli, $filters = []) {
    // Implementation
}

// Insert cashflow transaction
function insert_cashflow_transaction($mysqli, $data) {
    // Implementation
}

// Delete cashflow transaction
function delete_cashflow_transaction($mysqli, $id) {
    // Implementation
}

// Upload photo helper
function upload_cashflow_photo($file) {
    // Implementation
}
```

**Status:** [ ] PENDING

---

## PHASE 2: BACKEND API

### 2.1 cashflow/api/submit.php
- [ ] Handle POST request
- [ ] Validate input (nama, kategori, nominal, tanggal)
- [ ] Handle file upload
- [ ] Insert to database
- [ ] Return JSON response

**Status:** [ ] PENDING

### 2.2 cashflow/api/delete.php
- [ ] Check admin authentication
- [ ] Validate ID
- [ ] Delete from database
- [ ] Delete photo file (if exists)
- [ ] Return JSON response

**Status:** [ ] PENDING

---

## PHASE 3: PUBLIC PAGE (Teknisi)

### 3.1 cashflow/public/index.php - Form Input
**UI Components:**
- [ ] Header dengan logo/nama perusahaan
- [ ] Form dengan Bootstrap 4:
  - [ ] Input Nama Teknisi
  - [ ] Dropdown Kategori (BBM, Tol, Sparepart, Lainnya)
  - [ ] Input Nominal (Rp)
  - [ ] Textarea Keterangan
  - [ ] Input File (dengan camera capture)
  - [ ] Date Picker (default: today)
- [ ] Submit Button
- [ ] Success/Error Modal
- [ ] Footer

**Features:**
- [ ] Client-side validation (JavaScript)
- [ ] Image preview before upload
- [ ] Auto-format currency (Rp)
- [ ] Camera capture support (mobile)
- [ ] Responsive design

**Status:** [ ] PENDING

---

## PHASE 4: ADMIN PAGE

### 4.1 cashflow/admin/index.php - Dashboard & Datatable
**UI Components:**
- [ ] Sidebar/Navbar dengan logout
- [ ] Summary Cards:
  - [ ] Total Hari Ini
  - [ ] Total Minggu Ini
  - [ ] Total Bulan Ini
  - [ ] Total Per Kategori
- [ ] Filter Section:
  - [ ] Date Range Picker
  - [ ] Dropdown Kategori
  - [ ] Search Input (nama teknisi)
- [ ] DataTable:
  - [ ] Kolom: No, Tanggal, Teknisi, Kategori, Nominal, Keterangan, Foto, Aksi
  - [ ] Sortable columns
  - [ ] Pagination
- [ ] Modal View Foto
- [ ] Delete Button (dengan konfirmasi)
- [ ] Export Excel Button

**Status:** [ ] PENDING

### 4.2 cashflow/admin/view_photo.php
- [ ] Check authentication
- [ ] Load photo by ID
- [ ] Display in modal/lightbox

**Status:** [ ] PENDING

---

## PHASE 5: STYLING & ASSETS

### 5.1 CSS
- [ ] cashflow/public/style.css (mobile-first)
- [ ] cashflow/admin/style.css (dashboard)

### 5.2 JavaScript
- [ ] cashflow/public/script.js (form validation, preview)
- [ ] cashflow/admin/script.js (datatables init, filters)

### 5.3 CDN Libraries
```html
<!-- Bootstrap 4 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
```

**Status:** [ ] PENDING

---

## PHASE 6: SECURITY & PROTECTION

### 6.1 File Upload Security
- [ ] .htaccess di folder uploads/
- [ ] Validate MIME type
- [ ] Rename file dengan unique hash
- [ ] Max size limit (5MB)

### 6.2 API Security
- [ ] CSRF Token (admin pages)
- [ ] Rate limiting (public API)
- [ ] Input sanitization
- [ ] SQL Injection prevention (prepared statements)

**Status:** [ ] PENDING

---

## PHASE 7: TESTING

### 7.1 Functional Testing
- [ ] Test form submit (desktop)
- [ ] Test form submit (mobile)
- [ ] Test photo upload (camera)
- [ ] Test photo upload (gallery)
- [ ] Test datatable rendering
- [ ] Test filter functionality
- [ ] Test search functionality
- [ ] Test delete transaction
- [ ] Test export excel

### 7.2 Responsive Testing
- [ ] Mobile (320px - 480px)
- [ ] Tablet (768px - 1024px)
- [ ] Desktop (1280px+)

### 7.3 Browser Testing
- [ ] Chrome
- [ ] Firefox
- [ ] Safari (iOS)
- [ ] Chrome (Android)

**Status:** [ ] PENDING

---

## PHASE 8: DEPLOYMENT

### 8.1 Pre-Deployment
- [ ] Backup database
- [ ] Test di local/staging
- [ ] Review code

### 8.2 Deployment
- [ ] Upload files ke server
- [ ] Run SQL migration
- [ ] Set folder permissions (uploads/)
- [ ] Test di production

### 8.3 Post-Deployment
- [ ] Monitoring error log
- [ ] Test semua fitur di production
- [ ] Dokumentasi user

**Status:** [ ] PENDING

---

## CHECKLIST FILES YANG DIBUAT

| File | Status |
|------|--------|
| `cashflow/public/index.php` | [ ] |
| `cashflow/public/style.css` | [ ] |
| `cashflow/public/script.js` | [ ] |
| `cashflow/admin/index.php` | [ ] |
| `cashflow/admin/view_photo.php` | [ ] |
| `cashflow/admin/style.css` | [ ] |
| `cashflow/admin/script.js` | [ ] |
| `cashflow/api/submit.php` | [ ] |
| `cashflow/api/delete.php` | [ ] |
| `cashflow/uploads/.htaccess` | [ ] |
| `cashflow/uploads/.gitkeep` | [ ] |

---

## NOTES

1. **Mobile First:** Form publik harus optimal di HP karena teknisi pakai mobile
2. **Camera Capture:** Gunakan `capture="environment"` untuk akses kamera belakang
3. **Performance:** Optimasi gambar sebelum upload (compress client-side jika perlu)
4. **UX:** Berikan feedback jelas saat submit (loading, success, error)

---

*Dibuat: 2026-05-07*
*Versi: 1.0*