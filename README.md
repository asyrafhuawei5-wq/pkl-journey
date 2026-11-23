<p align="center">
  <img src="assets/logo.svg" alt="Logo PKL Journey" width="96">
</p>

<h1 align="center">PKL Journey</h1>

<p align="center">
  Platform pengelolaan Praktik Kerja Lapangan untuk sekolah, siswa, dan industri.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white" alt="MySQL / MariaDB">
  <img src="https://img.shields.io/badge/Composer-2-885630?logo=composer&logoColor=white" alt="Composer 2">
  <img src="https://img.shields.io/badge/Frontend-HTML%20%7C%20CSS%20%7C%20JavaScript-F7DF1E?logo=javascript&logoColor=black" alt="HTML, CSS, JavaScript">
</p>

---

## Tentang Aplikasi

**PKL Journey** adalah aplikasi web untuk membantu sekolah mengelola kegiatan Praktik Kerja Lapangan (PKL) secara terpusat. Aplikasi menghubungkan lima jenis pengguna dalam satu alur kerja: admin sekolah, siswa, guru pembimbing sekolah, pembimbing industri/DUDI, dan penguji.

Setiap pengguna masuk melalui satu halaman login. Sistem mengenali role dari akun dan mengarahkan pengguna ke dashboard yang sesuai. Data jurnal, laporan ringkas, penempatan PKL, dan penilaian tersimpan dalam satu basis data.

Aplikasi ini dikembangkan untuk kebutuhan pengelolaan PKL di lingkungan **SMK Telekomunikasi Telesandi Bekasi**.

> Repository ini hanya berisi kode aplikasi dan contoh skema database. Dump database operasional, data siswa, dan konfigurasi lokal tidak disertakan.

---

## Fitur Terverifikasi

### Halaman publik dan autentikasi

- Landing page publik yang menjelaskan alur PKL dan lima role pengguna.
- Login menggunakan NIS/username atau email beserta password.
- Deteksi role otomatis dan redirect ke dashboard sesuai role.
- Proteksi halaman berdasarkan role melalui `auth/check_*.php`.
- Registrasi mandiri publik dinonaktifkan; akun dikelola Administrator.
- Logout dan pengelolaan sesi login.

### Ringkasan fitur per role

| Role | Dashboard | Fitur utama |
|---|---|---|
| **Admin** | `dashboard/admin.php` | Statistik, pengelolaan akun guru/pembimbing industri/penguji, data murid, import Excel siswa, master perusahaan, penempatan PKL, penugasan massal, penugasan penguji per kategori |
| **Murid/Siswa** | `dashboard/murid.php` | Statistik, profil dan tim pembimbing, jurnal harian, ringkasan laporan PKL, visualisasi progress 6 tahap, tampilan hasil penilaian, ganti password awal |
| **Guru Pembimbing Sekolah** | `dashboard/guru.php` | Statistik bimbingan, daftar siswa, verifikasi jurnal (setujui/revisi), verifikasi laporan, penilaian siswa, monitoring progress |
| **Pembimbing Industri/DUDI** | `dashboard/industri.php` | Daftar siswa bimbingan, monitoring aktivitas dan jurnal, penilaian kinerja industri |
| **Penguji** | `dashboard/penguji.php` | Daftar peserta sidang, telaah jurnal/laporan, penilaian sidang, penilaian penguji produktif, penilaian penguji jurnal |

### Detail fitur yang tersedia

- **Import Excel data siswa** — unduh template, unggah file, pratinjau, validasi, dan konfirmasi import. Akun siswa dibuat otomatis dengan password awal berupa NIS dan wajib diganti saat login pertama.
- **Penempatan PKL** — menghubungkan siswa dengan perusahaan, guru pembimbing sekolah, pembimbing industri, dan penguji.
- **Penugasan massal** — menetapkan pembimbing atau penguji untuk banyak siswa sekaligus.
- **Jurnal harian** — siswa mencatat kegiatan; guru pembimbing dapat memberi status `menunggu`, `disetujui`, atau `revisi`.
- **Laporan PKL ringkas** — berupa judul dan ringkasan/abstrak teks, bukan unggahan berkas.
- **Penilaian berlapis**:
  - Guru sekolah: disiplin, teknis, komunikasi.
  - Pembimbing industri: disiplin, kehadiran, kualitas kerja.
  - Penguji sidang: pemahaman, presentasi, tanya jawab.
  - Penguji produktif: lima kriteria kompetensi.
  - Penguji jurnal: lima kriteria dokumentasi.
- **Progress 6 tahap** — visualisasi perjalanan PKL pada dashboard siswa dan guru.
- **Keamanan akun** — password disimpan menggunakan `password_hash()`, diverifikasi dengan `password_verify()`, dan akun hasil import wajib mengganti password awal.

### Fitur yang belum tersedia

Agar tidak menimbulkan ekspektasi yang keliru, fitur berikut **belum ada** di aplikasi ini:

- Modul absensi mandiri. Kehadiran hanya menjadi salah satu kriteria penilaian industri.
- Export Excel atau rekap otomatis. Fitur Excel yang tersedia hanya **import** data siswa dan unduh template.
- Unggah berkas laporan/PDF. Laporan hanya berupa judul dan ringkasan teks.
- QR Code di dalam aplikasi.
- Registrasi mandiri publik.
- Chat, notifikasi push, email, atau WhatsApp.
- Pengajuan tempat PKL sebagai workflow. Penempatan dilakukan oleh Admin; stepper hanya visualisasi progress.
- Sertifikat otomatis.
- UI pengelolaan multi-sekolah, meskipun struktur data memiliki `school_id`.

---

## Teknologi

| Komponen | Keterangan |
|---|---|
| Bahasa backend | PHP Native 8.2+ |
| Database | MySQL/MariaDB dengan ekstensi PDO |
| Library Excel | [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) 5.11 via Composer |
| Frontend | HTML5, CSS3, JavaScript vanilla |
| Autentikasi | Session PHP, `password_hash()`, dan `password_verify()` |
| Web server | Apache (direkomendasikan melalui XAMPP) |
| Ikon | SVG internal pada `includes/icons.php` |

Tidak ada framework PHP, proses build frontend, atau server Node.js yang diperlukan.

---

## Struktur Folder

```text
pkl_journey/
├── assets/                     # Logo dan aset gambar
├── auth/                       # Pemeriksaan sesi dan role per halaman
│   ├── check_admin.php
│   ├── check_guru.php
│   ├── check_industri.php
│   ├── check_murid.php
│   └── check_penguji.php
├── config/
│   ├── database.example.php    # Template konfigurasi database
│   └── database.php            # Konfigurasi lokal (tidak di-commit)
├── css/
│   └── style.css               # Gaya antarmuka aplikasi
├── dashboard/                  # Dashboard untuk lima role
│   ├── admin.php
│   ├── guru.php
│   ├── industri.php
│   ├── murid.php
│   └── penguji.php
├── database/
│   └── schema.example.sql      # Skema database tanpa data
├── includes/
│   ├── icons.php               # Helper ikon SVG
│   └── student_import.php      # Logika import Excel siswa
├── js/
│   └── script.js               # Interaksi halaman login dan UI
├── vendor/                     # Dependensi Composer (tidak di-commit)
├── index.php                   # Entry point login
├── landing.php                 # Landing page publik
├── login.php                   # Proses autentikasi
├── logout.php                  # Proses logout
├── register.php                # Pendaftaran internal khusus Admin
├── composer.json
├── composer.lock
├── PROMO_PKL_JOURNEY.md        # Dokumen konsep promosi
├── README.md
└── .gitignore
```

> Folder `pkl_journey/` di dalam project adalah salinan versi lama. Folder tersebut tidak digunakan oleh aplikasi utama dan dikecualikan dari Git.

---

## Instalasi

### Prasyarat

- PHP **8.2 atau lebih baru**
- MySQL 5.7+ atau MariaDB 10.4+
- Apache dengan modul PHP aktif (XAMPP/Laragon)
- Composer 2
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`, `fileinfo`

### Langkah instalasi

1. **Letakkan project di web root**

   Contoh untuk XAMPP:

   ```text
   C:\xampp\htdocs\pkl_journey
   ```

2. **Pasang dependensi**

   ```bash
   composer install
   ```

3. **Impor skema database**

   Jalankan dari folder project:

   ```bash
   mysql -u root -p < database/schema.example.sql
   ```

   Atau impor `database/schema.example.sql` melalui phpMyAdmin.

   File tersebut hanya berisi struktur tabel, tanpa akun atau data siswa. Secara default skema menggunakan database `smart_pkl`; jika ingin nama lain, sesuaikan bagian `CREATE DATABASE`/`USE` dan konfigurasi aplikasi.

4. **Buat konfigurasi database lokal**

   Salin template:

   ```bash
   copy config\database.example.php config\database.php
   ```

   Di Linux/macOS:

   ```bash
   cp config/database.example.php config/database.php
   ```

   Kemudian sesuaikan `$host`, `$db`, `$user`, dan `$pass` pada `config/database.php`.

5. **Buat sekolah dan akun Admin pertama**

   Tambahkan satu sekolah:

   ```sql
   INSERT INTO schools (name) VALUES ('Nama Sekolah');
   ```

   Buat hash password dengan PHP:

   ```bash
   php -r "echo password_hash('GANTI_DENGAN_PASSWORD_KUAT', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Ganti teks placeholder dengan password Admin yang kuat sebelum menjalankan perintah. Salin hasil hash ke query berikut (ganti placeholder dan data contoh):

   ```sql
   INSERT INTO users (username, email, password, role, school_id)
   VALUES ('admin', 'admin@sekolah.example', 'HASIL_HASH_DI_SINI', 'admin', 1);
   ```

6. **Jalankan aplikasi**

   Aktifkan Apache dan MySQL, lalu buka:

   ```text
   http://localhost/pkl_journey/
   ```

7. **Siapkan data awal**

   Login sebagai Admin, lalu:

   - buat akun guru, pembimbing industri, dan penguji;
   - tambahkan master perusahaan;
   - tempatkan siswa atau import data siswa dari Excel;
   - tugaskan pembimbing dan penguji.

---

## Konfigurasi dan Keamanan

- `config/database.php` berisi kredensial lokal dan **tidak boleh di-commit**.
- Semua dump database (`database/*.sql`), backup, file Excel siswa, dan arsip lokal dikecualikan melalui `.gitignore`.
- Hanya `database/schema.example.sql` yang boleh dipublikasikan karena berisi struktur tabel tanpa data.
- Jangan mengunggah folder `pkl_journey/` (salinan lama) ke repository.
- Gunakan password database yang kuat dan akun MySQL khusus aplikasi, bukan `root` tanpa password.
- Untuk produksi, aktifkan HTTPS, nonaktifkan `display_errors`, dan batasi akses langsung ke folder konfigurasi serta file SQL.
- Ganti password awal akun hasil import saat login pertama. Fitur ini sudah diwajibkan oleh aplikasi.

---

## Alur Penggunaan Singkat

1. Admin menyiapkan akun staff, perusahaan mitra, dan penempatan siswa.
2. Siswa mencatat jurnal harian dan mengirim ringkasan laporan.
3. Guru pembimbing sekolah memverifikasi jurnal/laporan dan memberi penilaian.
4. Pembimbing industri memantau aktivitas serta menilai kinerja siswa.
5. Penguji menelaah jurnal/laporan dan memberi nilai sidang, produktif, atau jurnal.
6. Siswa melihat hasil penilaian pada dashboard masing-masing.

---

## Catatan Repository

Repository ini disiapkan untuk publikasi kode sumber. Database operasional, akun asli, data pribadi siswa, serta file backup tidak disertakan. Sebelum melakukan publikasi, pastikan kembali bahwa:

- `config/database.php` tidak ikut ter-commit;
- tidak ada file `.sql` selain `database/schema.example.sql`;
- tidak ada file Excel/CSV berisi data siswa;
- tidak ada kredensial, token, atau kunci API di dalam kode.

---

<p align="center">
  <strong>PKL Journey</strong><br>
  SMK Telekomunikasi Telesandi Bekasi
</p>
