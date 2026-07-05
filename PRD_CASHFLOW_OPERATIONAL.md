# 📄 PRD - CASHFLOW OPERATIONAL MODULE

## 1. OVERVIEW

Modul untuk mencatat dan memantau biaya operasional teknisi di lapangan tanpa perlu login, dengan halaman admin untuk monitoring.

---

## 2. USER STORIES

### 2.1 Teknisi (Public Page)
- Sebagai teknisi, saya ingin input biaya operasional tanpa ribet login
- Sebagai teknisi, saya ingin upload foto struk/bukti transaksi langsung dari HP
- Sebagai teknisi, saya ingin form yang mudah digunakan di mobile

### 2.2 Admin/Management (Admin Page)
- Sebagai admin, saya ingin melihat semua transaksi operasional
- Sebagai admin, saya ingin filter transaksi berdasarkan tanggal/kategori/teknisi
- Sebagai admin, saya ingin export data untuk laporan

---

## 3. FITUR

### A. PUBLIC PAGE (Teknisi - Tanpa Login)

| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Form Input Transaksi** | Input biaya operasional | P0 |
| - Nama Teknisi | Text input (wajib diisi) | P0 |
| - Kategori Biaya | Dropdown: BBM, Tol, Sparepart, Lainnya | P0 |
| - Nominal | Number input (Rp) | P0 |
| - Keterangan | Textarea (detail transaksi) | P0 |
| - Upload Foto | Input file (foto struk/bukti) | P0 |
| - Tanggal Transaksi | Date picker (default: hari ini) | P1 |
| **Konfirmasi** | Modal konfirmasi sebelum submit | P1 |
| **Success Message** | Notifikasi setelah berhasil input | P0 |

### B. ADMIN PAGE (Login Required)

| Fitur | Deskripsi | Priority |
|-------|-----------|----------|
| **Datatable Transaksi** | Tabel dengan semua transaksi | P0 |
| **Filter** | Filter: Tanggal, Teknisi, Kategori | P1 |
| **Search** | Pencarian text bebas | P1 |
| **Pagination** | Navigasi halaman | P1 |
| **View Foto** | Modal/lightbox untuk lihat foto | P1 |
| **Summary Card** | Total pengeluaran per kategori/hari | P2 |
| **Export Excel** | Download data ke Excel | P2 |

---

## 4. DATABASE SCHEMA

```sql
-- Tabel cashflow_transactions
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

---

## 5. STRUKTUR FILE

```
afshin_v3/
├── cashflow/
│   ├── public/
│   │   └── index.php          # Form input untuk teknisi
│   ├── admin/
│   │   ├── index.php          # Dashboard & datatable
│   │   └── view_photo.php     # Lightbox foto
│   ├── uploads/               # Folder foto transaksi
│   │   └── .htaccess          # Proteksi folder
│   └── api/
│       ├── submit.php         # Handle form submit
│       └── delete.php         # Hapus transaksi
├── db.php                     # (sudah ada)
└── functions.php              # (akan ditambah function)
```

---

## 6. API ENDPOINTS

### POST /cashflow/api/submit.php
Submit transaksi baru

**Request:**
```json
{
  "technician_name": "John Doe",
  "category": "BBM",
  "amount": 150000,
  "description": "Isi ulang solar untuk genset",
  "transaction_date": "2026-05-07"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Transaksi berhasil disimpan",
  "data": { "id": 1, "transaction_date": "2026-05-07", ... }
}
```

### DELETE /cashflow/api/delete.php
Hapus transaksi (admin only)

**Request:**
```json
{ "id": 1 }
```

---

## 7. VALIDASI

### Form Input (Public)
| Field | Validasi |
|-------|----------|
| Nama Teknisi | Required, max 100 char |
| Kategori | Required, must be valid enum |
| Nominal | Required, numeric, min 1 |
| Keterangan | Optional, max 500 char |
| Foto | Optional, max 5MB, jpg/png/pdf |
| Tanggal | Required, valid date |

---

## 8. SECURITY NOTES

1. **Public Page:**
   - Rate limiting untuk mencegah spam
   - File upload validation (type, size)
   - Sanitize semua input

2. **Admin Page:**
   - Session-based authentication
   - CSRF protection
   - Input validation

3. **File Upload:**
   - Rename file dengan unique name
   - Store outside webroot jika possible
   - Validate MIME type, bukan hanya extension

---

## 9. ACCEPTANCE CRITERIA

- [ ] Teknisi bisa input transaksi tanpa login
- [ ] Upload foto berfungsi di mobile
- [ ] Admin bisa melihat semua transaksi
- [ ] Filter dan search berfungsi
- [ ] Foto bisa dilihat di modal
- [ ] Form responsive di mobile
- [ ] Data tersimpan dengan benar di database

---

## 10. METRIK SUKSES

| Metrik | Target |
|--------|--------|
| Form submit time | < 2 detik |
| Mobile usability | 100% responsive |
| Upload success rate | > 95% |
| Admin page load time | < 3 detik |

---

*Dibuat: 2026-05-07*
*Versi: 1.0*