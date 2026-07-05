================================================================================
  PANDUAN UPLOAD FILE KE HOSTING - CRM AFSHIN v2
  Fitur Baru: Email Quotation Otomatis dengan PDF
================================================================================

Tanggal: 11 Juni 2026
Prepared by: SelfCoding Bot

================================================================================
DAFTAR FILE YANG SUDAH DIMODIFIKASI / DITAMBAHKAN:
================================================================================

1. FILE PHP YANG DIMODIFIKASI:
   - send_quotation_email.php (BARU)
   - generate_quotation_pdf.php (BARU)
   - quotations_list.php (MODIFIED)
   - customers_create.php (MODIFIED)
   - customers_edit.php (MODIFIED)

2. FOLDER VENDOR (Library Dependencies):
   - vendor/phpmailer/ (PHPMailer v7.1.1)
   - vendor/dompdf/ (DomPDF v3.1.5)
   - vendor/composer/ (Autoloader)
   - vendor/masterminds/ (HTML5 Parser)
   - vendor/thecodingmachine/ (Safe Functions)

3. FOLDER IMG (Logo & Assets):
   - img/afshin2.png (Logo untuk PDF)
   - img/cap2.png (Stempel untuk PDF)
   - img/ (semua file gambar lainnya)

================================================================================
LANGKAH-LANGKAH UPLOAD KE HOSTING:
================================================================================

STEP 1: UPLOAD FILE
-------------------
1. Upload SEMUA file dalam folder ini ke hosting Anda via FTP/cPanel File Manager
2. Letakkan di folder yang sesuai (misal: public_html/CRM-Afshin-v2/)
3. Pastikan struktur folder tetap sama:
   - CRM-Afshin-v2/
     ├── vendor/
     ├── img/
     ├── send_quotation_email.php
     ├── generate_quotation_pdf.php
     ├── quotations_list.php
     ├── customers_create.php
     ├── customers_edit.php
     └── (file lainnya dari proyek asli)

STEP 2: KONFIGURASI DATABASE
-----------------------------
1. Buat database baru di hosting (misal: crm_afshin)
2. Import database dari server lokal Anda
3. PENTING: Pastikan tabel quotations memiliki kolom:
   - date_quot (DATE)
   - po_number (VARCHAR 50)
   
   Jika belum ada, jalankan query ini di phpMyAdmin hosting:
   
   ALTER TABLE quotations 
     ADD COLUMN date_quot DATE NULL AFTER quotation_no,
     ADD COLUMN po_number VARCHAR(50) NULL AFTER date_quot;

4. Edit file db.php dengan kredensial database hosting:
   - DB_HOST (biasanya: localhost)
   - DB_USER (username database hosting)
   - DB_PASS (password database hosting)
   - DB_NAME (nama database: crm_afshin)

STEP 3: KONFIGURASI EMAIL SMTP
-------------------------------
File: send_quotation_email.php (Baris 14-15)

Pastikan kredensial email sudah benar:
$SMTP_USER = 'cvafshinrayateknik@gmail.com';
$SMTP_PASS = 'isch kxdm blsl xwxv';

CATATAN:
- Jika hosting memblokir port 465 (SSL), coba gunakan port 587 (TLS)
- Ubah baris 56: $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
- Ubah baris 57: $mail->Port = 587;

STEP 4: SET PERMISSION (CHMOD)
-------------------------------
Jika menggunakan Linux hosting, set permission folder:
- chmod 755 untuk semua folder
- chmod 644 untuk semua file .php

Khusus folder /tmp (jika ada) set ke 777 agar PDF bisa digenerate.

STEP 5: TEST APLIKASI
----------------------
1. Akses via browser: http://domain-anda.com/CRM-Afshin-v2/
2. Login dengan user yang ada
3. Masuk ke menu Customer → Create Customer
4. Pastikan field Email dan CC Email sudah muncul
5. Masuk ke menu Quotation → Daftar Quotation
6. Pastikan tombol hijau (ikon envelope) sudah muncul di kolom Aksi
7. Klik tombol envelope → Modal harus muncul
8. Isi Subject dan Body → Klik "Kirim Sekarang"
9. Cek email penerima → PDF harus terkirim dengan lampiran

================================================================================
FITUR BARU YANG DITAMBAHKAN:
================================================================================

✅ 1. FORM CUSTOMER (Create & Edit)
   - Field Email (type: email)
   - Field CC Email (text, support multiple email separated by comma)

✅ 2. QUOTATION LIST
   - Tombol "Kirim Email" (hijau dengan icon envelope)
   - Modal popup untuk input Subject & Body
   - Support multiple recipients (To & CC)
   - AJAX submit (tidak reload halaman)

✅ 3. BACKEND EMAIL SERVICE
   - File: send_quotation_email.php
   - Library: PHPMailer v7.1.1
   - SMTP: Gmail (smtp.gmail.com:465)
   - Sender: cvafshinrayateknik@gmail.com
   - Support multiple email To & CC (comma separated)
   - Validasi email dengan filter_var()

✅ 4. PDF GENERATOR SERVICE
   - File: generate_quotation_pdf.php
   - Library: DomPDF v3.1.5
   - Format: A4 Portrait
   - Layout: Persis sama dengan quotations_print.php
   - Include: Kop Surat, Logo, Tabel Items, Signature, Cap/Stempel
   - Cap diperbesar (220px) dan posisi optimal (overlap Best Regards & Manisah)
   - Box Note & Control Model sejajar dengan jarak 2%

✅ 5. DATABASE SCHEMA UPDATE
   - Tabel quotations: +date_quot, +po_number
   - Tabel customers: email & cc_email (sudah ada)

================================================================================
TROUBLESHOOTING:
================================================================================

MASALAH 1: Tombol Email tidak bisa diklik
SOLUSI: Clear browser cache, atau cek JavaScript console untuk error

MASALAH 2: Error 500 saat kirim email
SOLUSI: 
- Cek error log hosting (biasanya di /logs/ atau cPanel Error Log)
- Pastikan PHPMailer dan DomPDF sudah terupload
- Pastikan vendor/autoload.php ada dan bisa diakses

MASALAH 3: PDF tidak muncul / blank
SOLUSI:
- Pastikan folder img/ sudah terupload dengan file afshin2.png & cap2.png
- Cek permission folder /tmp (harus writable)
- Pastikan DomPDF library lengkap (folder vendor/dompdf/)

MASALAH 4: Email tidak terkirim / SMTP Error
SOLUSI:
- Cek apakah hosting memblokir port 465 (ganti ke 587 TLS)
- Pastikan App Password Gmail masih valid
- Cek apakah email sender terblokir oleh Gmail (cek inbox Gmail)
- Pastikan OpenSSL extension PHP aktif di hosting

MASALAH 5: Customer email tidak tersimpan
SOLUSI:
- Pastikan kolom email & cc_email ada di tabel customers
- Cek query INSERT/UPDATE di customers_create.php & customers_edit.php

================================================================================
KONTAK & SUPPORT:
================================================================================

Jika ada kendala saat upload atau konfigurasi, hubungi:
- Email: muhamadhabib.work@gmail.com
- Telegram: @SelfCoding_bot

Dokumentasi lengkap: README.md di folder proyek asli

================================================================================
CHECKLIST SEBELUM GO-LIVE:
================================================================================

□ Semua file sudah diupload ke hosting
□ Folder vendor/ lengkap (PHPMailer & DomPDF)
□ Folder img/ lengkap (afshin2.png & cap2.png)
□ Database sudah diimport & kolom date_quot/po_number ada
□ File db.php sudah dikonfigurasi dengan kredensial hosting
□ File send_quotation_email.php sudah ada kredensial Gmail yang benar
□ Permission folder sudah di-set (755 / 644)
□ Test login berhasil
□ Form customer menampilkan field Email & CC Email
□ Tombol Email muncul di Quotation List
□ Modal Email bisa dibuka
□ Test kirim email berhasil & PDF terlampir
□ Cek inbox penerima → email + PDF diterima dengan baik

================================================================================
SELAMAT! FITUR EMAIL QUOTATION SUDAH SIAP DIGUNAKAN DI HOSTING! 🎉📧
================================================================================
