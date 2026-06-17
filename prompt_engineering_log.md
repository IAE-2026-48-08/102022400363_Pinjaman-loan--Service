# Prompt Engineering & AI Exploration Log - IAE Tugas 3 (Loan Service)

Dokumen ini mendokumentasikan eksplorasi teknis dan proses engineering secara mandiri menggunakan AI Assistant dalam merancang, mengembangkan, dan mengintegrasikan **Service 3 (Pinjaman/Loan)** untuk memenuhi rubrikasi **Tugas 3 IAE 2026**.

---

## 📅 Log Iterasi & Eksplorasi

### 🔍 Fase 1: Probing & Pemahaman SSO (Federated SSO)
- **Aktivitas**: Menguji status kesehatan dan struktur endpoint `https://iae-sso.virtualfri.id`.
- **Eksplorasi AI**: 
  - Melakukan probing secara synchronous menggunakan PHP `file_get_contents()` ke `https://iae-sso.virtualfri.id/health`.
  - Ditemukan respon yang berisi informasi bahwa server menggunakan RabbitMQ dengan status `connected`.
  - Melakukan probing JWKS ke `https://iae-sso.virtualfri.id/api/v1/auth/jwks` untuk mendapatkan kunci publik RSA (RS256) guna melakukan decoding token JWT secara lokal.
  - Meminta token otentikasi menggunakan API-KEY `KEY-MHS-391` (untuk M2M) dan `warga13@ktp.iae.id` (untuk User) untuk memeriksa format payload JWT.
- **Hasil Payload**:
  - Tipe **M2M**: Memiliki sub `KEY-MHS-391` dan tipe token `m2m` dengan properti nama aplikasi serta team (`TEAM-04`).
  - Tipe **User**: Memiliki sub email warga, tipe token `user`, dan profil warga (`name`, `nim`, `email`).

### 🛠️ Fase 2: Implementasi database & Migrasi Peran Lokal
- **Aktivitas**: Membuat struktur database untuk mendukung pemetaan role lokal.
- **Keputusan Desain**:
  - Dibuat tabel `roles` (menyimpan id, name, slug) dan tabel pivot `role_user` (relasi Many-to-Many).
  - Dibuat seeder `RoleSeeder` untuk menyisipkan role `warga`, `staf`, dan `admin`.
  - Menghubungkan basis data lokal Laravel ke kontainer Docker MySQL yang berjalan di port `33061` dengan memperbarui berkas `.env` lokal, lalu menjalankan migrasi:
    ```bash
    php artisan migrate --seed
    ```

### 🔒 Fase 3: Pembuatan Middleware `VerifySSOToken`
- **Aktivitas**: Mengembangkan filter otentikasi JWT yang terhubung dengan tabel peran lokal.
- **Eksplorasi AI**:
  - Menginstal dependensi `firebase/php-jwt` untuk penanganan RS256 JWT yang aman dan standar.
  - Middleware membaca token dari header `Authorization: Bearer <token>`, memverifikasinya terhadap JWKS secara dinamis (dengan caching selama 24 jam untuk kinerja optimal).
  - Melakukan sinkronisasi user: mendaftarkan user SSO ke tabel `users` lokal jika belum ada, dan memetakan user ke role `warga` (untuk token bertipe `user`) atau `staf` (untuk token bertipe `m2m`).

### 📝 Fase 4: Integrasi SOAP Legacy Audit (Transformasi XML)
- **Aktivitas**: Membuat client SOAP yang mengubah data transaksi JSON ke format XML Envelope kaku.
- **Eksplorasi AI**:
  - Dibuat service `SoapAuditService` yang merakit XML Envelope secara manual (kaku) sesuai dengan template yang diberikan dosen.
  - Mengirimkan data transaksi (JSON) di dalam tag `<![CDATA[ ... ]]>`.
  - Mengirim request POST ke `https://iae-sso.virtualfri.id/soap/v1/audit` menggunakan Bearer Token (token user yang di-forward atau M2M token sebagai fallback).
  - Menggunakan class `SimpleXMLElement` PHP dan query XPath untuk mengekstrak `<iae:ReceiptNumber>` secara andal dari namespace XML.
  - Menyimpan `ReceiptNumber` tersebut di kolom baru `receipt_number` pada tabel `loans`.

### ✉️ Fase 5: AMQP Asynchronous Publisher
- **Aktivitas**: Pengiriman event notification dalam bentuk JSON ke RabbitMQ secara asinkron.
- **Eksplorasi AI**:
  - Menggunakan arsitektur Laravel Jobs (`PublishLoanEvent`) yang berjalan di antrean (*queue*) latar belakang (menggunakan database queue driver).
  - Ketika pengajuan pinjaman sukses disimpan, Job dimasukkan ke antrean dan diolah oleh queue worker secara non-blocking.
  - Job memposting data event JSON ke endpoint `POST https://iae-sso.virtualfri.id/api/v1/messages/publish` dengan routing key `loan.applied`.

---

## 🗂️ Struktur Kelas Hasil Refactoring & Integrasi

1. **Middleware**: `App\Http\Middleware\VerifySSOToken`
   - Melindungi rute API dengan memverifikasi token dari SSO, menyinkronkan user ke DB lokal, dan login secara lokal.
2. **Services**:
   - `App\Services\External\SSOService`: Manajemen JWKS dan M2M Token.
   - `App\Services\External\SoapAuditService`: Client SOAP Legacy Audit.
   - `App\Services\LoanService`: Logika bisnis pinjaman, Credit Scoring, pemanggilan SOAP Audit, dan pemicu antrean RabbitMQ.
3. **Queue Jobs**: `App\Jobs\PublishLoanEvent`
   - Mengirim event JSON ke RabbitMQ secara asinkron tanpa menahan respons API utama.
4. **Controllers & Resources**:
   - `App\Http\Controllers\Api\V1\LoanController`: Otorisasi hak akses (RBAC) (hanya role `warga` yang bisa mengajukan pinjaman; warga hanya bisa melihat data milik sendiri; staf/admin bisa melihat semua data).
   - `App\Http\Resources\Api\V1\LoanResource`: Penambahan field `receipt_number` di response JSON.
