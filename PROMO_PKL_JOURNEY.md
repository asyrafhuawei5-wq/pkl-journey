# Konsep Video Promosi PKL Journey

**Judul konsep:** *PKL Journey — Perjalanan PKL Lebih Terarah*  
**Durasi:** 55 detik (batas 50–55 detik, maksimal 59 detik)  
**Format:** Video horizontal 16:9, 1920 × 1080 px, 30 fps (direkomendasikan 60 fps untuk rekaman layar)  
**Bahasa:** Indonesia, natural dan profesional  
**Gaya:** Motion graphic modern, minimalis, biru-putih  
**Basis data konsep:** Hasil pemeriksaan terarah pada project PKL Journey per 23 November 2026  
**Penutup:** Logo PKL Journey, identitas SMK Telekomunikasi Telesandi Bekasi, dan area QR Code yang akan diisi kemudian

> Catatan: dokumen ini hanya konsep video. Tidak ada kode, database, konfigurasi, atau fitur aplikasi yang diubah.

---

## 1. Gambaran Aplikasi Berdasarkan Hasil Pemeriksaan

### 1.1 Identitas dan teknologi

PKL Journey adalah **aplikasi web** berbasis **PHP Native + MySQL**, dijalankan melalui web server (misalnya Apache/XAMPP) dan diakses lewat browser. Aplikasi **bukan aplikasi desktop**. Pengelolaan data menggunakan PDO, autentikasi berbasis session, dan library PhpSpreadsheet untuk import Excel.

Aplikasi memiliki satu landing page publik, satu halaman login, dan lima dashboard sesuai role:

| Role | Dashboard | Fokus utama |
|---|---|---|
| Admin | `dashboard/admin.php` | Akun, data murid, perusahaan mitra, penempatan, pembimbing, penguji |
| Murid | `dashboard/murid.php` | Jurnal harian, progres, laporan, hasil penilaian |
| Guru Pembimbing Sekolah | `dashboard/guru.php` | Verifikasi jurnal, review laporan, penilaian, monitoring progres |
| Pembimbing Industri / DUDI | `dashboard/industri.php` | Monitoring aktivitas dan penilaian kinerja |
| Penguji | `dashboard/penguji.php` | Telaah jurnal/laporan, penilaian sidang, penilaian produktif dan jurnal |

### 1.2 Alur kerja aplikasi yang benar-benar ada

1. **Admin menyiapkan data.** Admin membuat akun guru, pembimbing industri, dan penguji; menambahkan perusahaan mitra; serta mengatur penempatan siswa.
2. **Admin mengimpor siswa dari Excel.** Tersedia template, upload, pratinjau, validasi, dan konfirmasi. Akun siswa dibuat otomatis dengan password awal berupa NIS dan wajib diganti saat login pertama.
3. **Admin menempatkan siswa.** Satu siswa dihubungkan ke perusahaan, guru pembimbing sekolah, pembimbing industri, dan penguji sidang.
4. **Siswa mengisi jurnal harian dan mengirim ringkasan laporan.** Jurnal memiliki status `menunggu`, `disetujui`, atau `revisi`.
5. **Guru memverifikasi jurnal dan laporan, lalu memberi penilaian.** Guru juga dapat memantau peta progres enam tahap.
6. **Pembimbing industri memantau jurnal dan menilai kinerja.** Penilaian mencakup kedisiplinan, kehadiran/kerajinan, dan kualitas hasil kerja.
7. **Penguji menelaah jurnal/laporan dan memberi nilai.** Ada penilaian sidang, penilaian penguji produktif, dan penilaian penguji jurnal.
8. **Siswa melihat hasilnya.** Dashboard siswa menampilkan seluruh jenis penilaian yang sudah masuk.

### 1.3 Hubungan data dan role

```
schools
  └── users (role: admin, murid, guru, guru_industri, penguji)
        ├── internships (1 siswa → 1 penempatan)
        │     ├── guru_id        → users (guru)
        │     ├── industri_id    → users (guru_industri)
        │     ├── penguji_id     → users (penguji)
        │     └── company_id     → companies
        ├── pkl_journals         (jurnal harian siswa)
        ├── pkl_reports          (ringkasan laporan akhir)
        ├── pkl_assessments      (penilaian guru sekolah)
        ├── industry_assessments (penilaian pembimbing industri)
        ├── sidang_assessments   (penilaian sidang)
        └── examiner_assignments (kategori: produktif / jurnal)
              ├── produktif_assessments (5 kriteria)
              └── jurnal_assessments    (5 kriteria)
```

**Pemisahan akses:** setiap dashboard dilindungi `auth/check_*.php` yang memverifikasi role langsung ke database. Login tidak memakai pilihan role manual; sistem mengenali role dari akun, lalu mengarahkan ke dashboard yang sesuai.

### 1.4 Status fitur: tersedia, terbatas, atau tidak ditemukan

| Area | Status | Bukti / catatan |
|---|---|---|
| Landing page publik | **Tersedia** | `landing.php` |
| Login dengan NIS/username + password | **Tersedia** | `index.php`, `login.php`, `js/script.js` |
| Deteksi role otomatis dan redirect dashboard | **Tersedia** | `login.php`, `js/script.js` |
| Proteksi halaman per role | **Tersedia** | `auth/check_admin.php`, `check_murid.php`, `check_guru.php`, `check_industri.php`, `check_penguji.php` |
| Dashboard Admin | **Tersedia** | Statistik, kelola guru/staff, data murid, penempatan, perusahaan, penguji |
| Dashboard Murid | **Tersedia** | Statistik, profil, tim pembimbing, journey, jurnal, penilaian, laporan |
| Dashboard Guru | **Tersedia** | Statistik, data siswa, jurnal, progress, penilaian, laporan |
| Dashboard Pembimbing Industri | **Tersedia** | Statistik, siswa, jurnal, penilaian industri |
| Dashboard Penguji | **Tersedia** | Statistik, peserta sidang, telaah jurnal/laporan, penilaian sidang, penilaian produktif/jurnal |
| Jurnal harian siswa | **Tersedia** | Siswa membuat jurnal; guru memberi status setuju/revisi |
| Laporan PKL | **Tersedia (terbatas)** | Berupa judul + ringkasan/abstrak teks, bukan upload file |
| Penempatan PKL | **Tersedia** | Admin mengatur perusahaan, guru, pembimbing industri, penguji |
| Penugasan massal pembimbing | **Tersedia** | Admin dapat menugaskan banyak siswa sekaligus |
| Penugasan penguji per kategori | **Tersedia** | Kategori Produktif dan Jurnal |
| Penilaian guru sekolah | **Tersedia** | 3 kriteria: disiplin, teknis, komunikasi |
| Penilaian pembimbing industri | **Tersedia** | 3 kriteria: disiplin, kehadiran, kualitas kerja |
| Penilaian sidang | **Tersedia** | 3 kriteria: pemahaman, presentasi, tanya jawab |
| Penilaian penguji produktif | **Tersedia** | 5 kriteria, tanpa nilai gabungan otomatis |
| Penilaian penguji jurnal | **Tersedia** | 5 kriteria, tanpa nilai gabungan otomatis |
| Progress/Journey 6 tahap | **Tersedia (visualisasi)** | Stepper informatif; bukan modul pengajuan/approval tempat PKL |
| Import Excel data siswa | **Tersedia** | Template, upload, pratinjau, validasi, konfirmasi, akun otomatis |
| Export Excel data/rekap | **Tidak ditemukan** | Yang ada hanya **download template** untuk import |
| Modul absensi mandiri | **Tidak ditemukan** | Kehadiran hanya muncul sebagai **kriteria penilaian industri** (`score_attendance`) |
| Upload file laporan/PDF | **Tidak ditemukan** | Laporan hanya berupa teks judul + ringkasan |
| QR Code di dalam aplikasi | **Tidak ditemukan** | QR hanya akan dipakai di end card video |
| Registrasi mandiri publik | **Tidak aktif** | `register.php` menolak non-admin; akun dikelola Administrator |
| Notifikasi push/email/WhatsApp | **Tidak ditemukan** | Hanya ringkasan dan penghitung di dashboard |
| Chat / komunikasi langsung | **Tidak ditemukan** | Tidak ada fitur pesan |
| Catatan bimbingan khusus | **Tidak ditemukan** | Umpan balik hanya ada di form penilaian |
| Multi-sekolah | **Terbatas** | Ada `school_id` dan data 2 sekolah, tetapi belum ada UI kelola sekolah |
| Reset password siswa oleh admin | **Tidak ditemukan** | Admin dapat mereset password staff; siswa wajib ganti password awal sendiri |
| Pencarian/filter data | **Tersedia** | Pada tabel admin dan guru |
| Tampilan responsif browser | **Tersedia** | Media query di `css/style.css` |

### 1.5 Catatan keakuratan yang wajib dijaga

1. **Jangan mempromosikan absensi sebagai fitur.** Landing page memiliki kartu “Pencatatan Absensi”, tetapi tidak ada modul absensi mandiri. Dalam video, kehadiran hanya boleh disebut sebagai **kriteria penilaian pembimbing industri**.
2. **Jangan menyebut export Excel.** Fitur Excel yang tersedia adalah **import** siswa dan **download template**, bukan export rekap.
3. **Jangan menyebut upload laporan.** Siswa mengirim **ringkasan laporan berbentuk teks**, bukan mengunggah dokumen.
4. **Jangan menyebut pengajuan tempat PKL sebagai alur aplikasi.** Stepper “Pengajuan/Diterima” adalah visualisasi progres; penempatan sebenarnya dilakukan Admin.
5. **Jangan menampilkan QR Code palsu.** Area QR di end card harus kosong atau diisi QR asli yang diberikan pengguna.
6. **Jangan menampilkan data pribadi.** Gunakan akun demo, samarkan email/NIS asli, dan sembunyikan password yang tampil setelah import.
7. **Gunakan aplikasi dari root project.** Folder `pkl_journey/` di dalam project tampak seperti salinan lama; jangan memakai tampilan dari folder tersebut.
8. **Untuk data demo tambahan, gunakan salinan database terpisah.** Jangan mengubah database produksi hanya untuk keperluan rekaman.

---

## 2. Target Penonton Video

### 2.1 Target utama

| Segmen | Kebutuhan yang ingin dijawab video |
|---|---|
| Siswa SMK | “Bagaimana saya mencatat jurnal dan melihat progres PKL saya?” |
| Guru pembimbing sekolah | “Bagaimana saya memantau dan memverifikasi tanpa banyak berkas manual?” |
| Pembimbing industri/DUDI | “Bagaimana saya memantau aktivitas siswa dan memberi nilai dengan cepat?” |
| Penguji | “Bagaimana saya menelaah jurnal/laporan dan mengisi nilai sidang?” |
| Admin sekolah | “Bagaimana saya menyiapkan akun, mitra, dan penempatan secara terpusat?” |

### 2.2 Target sekunder

- Kepala sekolah dan wakil kurikulum yang ingin melihat tata kelola PKL.
- Orang tua yang ingin memahami proses pemantauan PKL.
- Calon perusahaan mitra/DUDI.
- Sekolah lain yang ingin melihat contoh pengelolaan PKL digital.

### 2.3 Konteks penayangan

- Halaman depan website sekolah.
- Layar saat sosialisasi/orientasi PKL.
- Presentasi rapat sekolah dan mitra industri.
- Media sosial sekolah (versi 16:9 dapat dipotong ulang ke 9:16).
- Materi pembuka saat demo aplikasi.

### 2.4 Pesan utama

> PKL Journey menyatukan lima peran dalam satu alur PKL digital: persiapan admin, jurnal siswa, verifikasi guru, penilaian industri, dan penilaian penguji — semuanya tercatat dan mudah dipantau.

Nada komunikasi: **membangun kepercayaan, rapi, dan tidak berlebihan**. Hindari klaim “semua otomatis”, “real-time”, atau “tanpa kertas” secara mutlak.

---

## 3. Daftar Fitur yang Layak Dipromosikan

### 3.1 Fitur prioritas untuk video

| No | Fitur | Role yang diuntungkan | Bukti di project | Nilai yang ditonjolkan |
|---|---|---|---|---|
| 1 | Satu platform, lima role | Semua | Landing `#akses`, lima dashboard | Semua pihak berada dalam satu ekosistem |
| 2 | Login otomatis sesuai role | Semua | `login.php`, `js/script.js` | Masuk sekali, langsung ke dashboard yang tepat |
| 3 | Admin mengelola akun dan mitra | Admin | Tab Kelola Guru, Data Perusahaan | Persiapan PKL lebih tertata |
| 4 | Penempatan siswa ke perusahaan + pembimbing + penguji | Admin | Tab Atur Guru Pembimbing, modal penetapan | Penugasan jelas dan tidak tercecer |
| 5 | Penugasan massal | Admin | `assign_guru_bulk`, `assign_penguji_bulk` | Banyak siswa, satu proses |
| 6 | Import Excel siswa | Admin | `includes/student_import.php` | Data siswa masuk cepat dengan pratinjau dan validasi |
| 7 | Jurnal harian digital | Murid, Guru, Industri | `pkl_journals`, modal jurnal | Aktivitas harian tercatat rapi |
| 8 | Verifikasi jurnal | Guru | Tombol Setujui/Revisi | Guru dapat menindaklanjuti dengan jelas |
| 9 | Laporan PKL ringkas | Murid, Guru, Penguji | `pkl_reports` | Ringkasan akhir terdokumentasi |
| 10 | Penilaian berlapis | Guru, Industri, Penguji | 5 skema penilaian | Setiap pihak menilai dari sudut pandangnya |
| 11 | Progress 6 tahap | Murid, Guru | Stepper journey | Progres PKL mudah dipahami |
| 12 | Dashboard monitoring | Semua | Statistik di setiap dashboard | Kondisi PKL terlihat sekilas |
| 13 | Keamanan akun | Semua | `password_hash`, role guard, wajib ganti password awal | Data dan akses lebih terkendali |
| 14 | Akses lewat browser | Semua | CSS responsif | Tidak perlu instalasi aplikasi desktop |

### 3.2 Fitur yang sengaja tidak ditampilkan

- **Absensi** sebagai modul mandiri — tidak ditemukan.
- **Export Excel/rekap** — tidak ditemukan.
- **Upload file laporan/PDF** — tidak ditemukan.
- **QR Code di dalam aplikasi** — tidak ditemukan.
- **Registrasi mandiri** — dinonaktifkan.
- **Chat/notifikasi push** — tidak ditemukan.
- **Pengajuan tempat PKL sebagai workflow** — hanya visualisasi stepper.
- **Sertifikat/PDF otomatis** — tidak ditemukan.

### 3.3 Rumusan manfaat per role untuk narasi

| Role | Kalimat manfaat yang aman |
|---|---|
| Admin | “Menyiapkan akun, mitra, dan penempatan PKL dari satu panel.” |
| Murid | “Mencatat jurnal harian dan memantau progres enam tahap.” |
| Guru | “Memverifikasi jurnal, meninjau laporan, dan memberi penilaian.” |
| Pembimbing Industri | “Memantau aktivitas kerja dan menilai kinerja siswa.” |
| Penguji | “Menelaah jurnal/laporan serta memberi nilai sidang dan kompetensi.” |

---

## 4. Storyboard Lengkap

### 4.1 Ringkasan timeline

| Scene | Waktu | Durasi | Fokus | Role | Fitur yang terlihat |
|---|---|---:|---|---|---|
| S1 | 00:00–00:04 | 4 s | Brand hook | — | Logo dan tagline |
| S2 | 00:04–00:10 | 6 s | Masalah → solusi | — | Visual metafora, transisi ke platform |
| S3 | 00:10–00:15 | 5 s | Identitas + lima peran | Semua | Landing page, kartu akses pengguna |
| S4 | 00:15–00:19 | 4 s | Admin menyiapkan | Admin | Dashboard admin, penempatan |
| S5 | 00:19–00:23 | 4 s | Import Excel | Admin | Template, pratinjau, validasi, akun dibuat |
| S6 | 00:23–00:28 | 5 s | Jurnal dan progres | Murid | Dashboard murid, modal jurnal, journey |
| S7 | 00:28–00:33 | 5 s | Verifikasi dan penilaian | Guru | Jurnal, laporan, form penilaian |
| S8 | 00:33–00:38 | 5 s | Monitoring industri | Pembimbing Industri | Jurnal, penilaian kinerja |
| S9 | 00:38–00:44 | 6 s | Telaah dan penilaian penguji | Penguji | Jurnal/laporan, sidang, produktif/jurnal |
| S10 | 00:44–00:48 | 4 s | Rekap lima peran | Semua | Lima kartu role |
| S11 | 00:48–00:55 | 7 s | Penutup dan QR | — | Logo, sekolah, area QR |
| **Total** | **00:00–00:55** | **55 s** | | | |

### 4.2 Scene 1 — Brand Hook

| Elemen | Detail |
|---|---|
| **Waktu** | 00:00–00:04 (4 detik) |
| **Visual** | Kanvas putih dengan pola titik halus seperti background aplikasi. Logo PKL Journey muncul di tengah, diikuti wordmark dan tagline. Aksen biru langit bergerak lembut di belakang logo. |
| **Screenshot/aset** | Tidak memakai screenshot aplikasi. Gunakan `assets/logo.svg`. |
| **Animasi** | Logo scale 92% → 100% dengan ease-out 0.6 s; garis journey pada logo digambar (stroke draw); wordmark muncul dengan mask reveal; glow biru berdenyut satu kali. Transisi ke S2: radial wipe biru dari tengah logo. |
| **Teks layar** | Baris 1: **PKL JOURNEY** (huruf kapital, tracking lebar). Baris 2: **Perjalanan PKL Lebih Terarah**. |
| **Voice-over** | “Praktik Kerja Lapangan. Kini dalam satu perjalanan yang lebih terarah.” |
| **Audio** | Pad synthesizer lembut, whoosh halus saat logo muncul, klik ringan saat wordmark selesai. Musik belum masuk beat. |
| **Catatan akurasi** | Tagline diambil dari hero landing page. Tidak ada klaim fitur. |

### 4.3 Scene 2 — Dari Tersebar ke Terintegrasi

| Elemen | Detail |
|---|---|
| **Waktu** | 00:04–00:10 (6 detik) |
| **Visual** | Tiga kartu metafora melayang: spreadsheet, buku jurnal, dan gelembung komunikasi. Kartu-kartu tampak terpisah, lalu garis biru menghubungkannya dan semuanya menyusut ke dalam satu bingkai browser. Di dalam bingkai, potongan dashboard muncul samar lalu menajam. |
| **Screenshot/aset** | Komposit potongan UI dari dashboard admin, murid, dan guru. Boleh diblur pada 1 detik pertama. |
| **Animasi** | Kartu masuk dari kiri/kanan dengan stagger 120 ms; garis koneksi digambar; kamera push-in ke bingkai browser; transisi match cut ke landing page. |
| **Teks layar** | **Tersebar** (dicoret halus) → **Terintegrasi**. Subteks: “Satu platform untuk seluruh proses PKL”. |
| **Voice-over** | “Data siswa, jurnal, bimbingan, hingga penilaian. Dulu tersebar, kini menyatu dalam satu platform.” |
| **Audio** | Suara kertas, keyboard halus, whoosh saat kartu menyatu. Beat musik mulai masuk di detik ke-8. |
| **Catatan akurasi** | Gunakan ikon generik. Jangan menampilkan fitur yang tidak ada, seperti absensi atau export. |

### 4.4 Scene 3 — Identitas dan Lima Peran

| Elemen | Detail |
|---|---|
| **Waktu** | 00:10–00:15 (5 detik) |
| **Visual** | Landing page dalam bingkai browser. Kamera scroll dari hero ke bagian “Hak Akses untuk Setiap Peran”. Lima chip role muncul mengelilingi logo: Admin, Murid, Guru Sekolah, Pembimbing Industri, Penguji. |
| **Screenshot/aset** | `landing.php` bagian hero dan `landing.php#akses`. Hindari bagian “Fitur Utama” yang memuat kartu absensi. |
| **Animasi** | Bingkai browser naik 24 px; kursor halus melakukan scroll; chip role muncul bergantian dengan ikon dari `includes/icons.php`; transisi card wipe biru ke kanan. |
| **Teks layar** | **5 Peran, 1 Ekosistem**. Chip: **Admin**, **Murid**, **Guru Sekolah**, **Pembimbing Industri**, **Penguji**. |
| **Voice-over** | “PKL Journey. Platform PKL digital yang menghubungkan sekolah, siswa, dan dunia industri.” |
| **Audio** | Pop lembut setiap chip, pulse musik ringan. |
| **Catatan akurasi** | Jangan memperlihatkan kartu “Pencatatan Absensi”. Role yang ditampilkan harus sesuai lima role di database. |

### 4.5 Scene 4 — Admin Menyiapkan PKL

| Elemen | Detail |
|---|---|
| **Waktu** | 00:15–00:19 (4 detik) |
| **Visual** | Dashboard Admin dengan lima kartu statistik: Total Murid, Total Guru, Sudah Ada Pembimbing, Belum Ada Pembimbing, Perusahaan PKL. Potong cepat ke tabel “Penetapan Pembimbing Sekolah, Industri & Penguji Sidang” dan modal penetapan. Sorot field perusahaan, guru sekolah, pembimbing industri, dan penguji. |
| **Screenshot/aset** | `dashboard/admin.php` (dashboard); `dashboard/admin.php?tab=section-pembimbing`; modal “Penetapan Pembimbing, Penguji & Lokasi PKL”. |
| **Animasi** | Angka statistik count-up; baris tabel muncul stagger; kursor memilih siswa; modal slide-up; field menyala bergantian dengan outline biru; transisi horizontal wipe. |
| **Teks layar** | **Admin**. Subteks: “Akun • Mitra • Penempatan”. |
| **Voice-over** | “Admin mengelola akun, perusahaan mitra, dan penempatan PKL dari satu panel.” |
| **Audio** | Klik UI halus, konfirmasi pendek saat field terisi. |
| **Catatan akurasi** | Penempatan dilakukan Admin. Jangan menyebut “pengajuan tempat PKL oleh siswa”. |

### 4.6 Scene 5 — Import Excel Data Siswa

| Elemen | Detail |
|---|---|
| **Waktu** | 00:19–00:23 (4 detik) |
| **Visual** | Tab Data Murid. Panel “Import Data Murid dari Excel” dengan tombol “Download Template Excel” dan area upload. Sebuah kartu file `.xlsx` masuk ke area upload. Muncul ringkasan pratinjau: Total, Valid, Tidak Valid. Lalu banner sukses “Import Data Murid Berhasil” dengan daftar akun baru. |
| **Screenshot/aset** | `dashboard/admin.php?tab=section-murid`; state upload; state preview; state sukses import. Samarkan password yang ditampilkan. |
| **Animasi** | Kartu file drag-and-drop; progress bar 0–100%; baris valid diberi tanda centang hijau; cap “Berhasil” dengan scale 90% → 100%; transisi zoom melalui ikon centang. |
| **Teks layar** | **Import Excel**. Subteks: “Template → Pratinjau → Validasi → Akun dibuat”. |
| **Voice-over** | “Data siswa diimpor dari Excel, lengkap dengan pratinjau dan validasi.” |
| **Audio** | Suara file drop, tick validasi, chime sukses. |
| **Catatan akurasi** | Hanya import dan download template. Tidak ada tombol export. Jangan menampilkan password asli; blur atau ganti dengan “••••••••”. |

### 4.7 Scene 6 — Murid Mencatat Jurnal dan Melihat Progres

| Elemen | Detail |
|---|---|
| **Waktu** | 00:23–00:28 (5 detik) |
| **Visual** | Dashboard Murid: kartu Status PKL, Progress PKL, Tempat PKL, Jumlah Jurnal; panel tim pembimbing; stepper enam tahap. Modal “Catat Jurnal Kegiatan Baru” terbuka, kursor mengisi tanggal, judul, dan deskripsi, lalu menyimpan. Potong ke tabel jurnal dengan status “Menunggu”. |
| **Screenshot/aset** | `dashboard/murid.php` (dashboard); modal jurnal; `#section-jurnal`; `#section-journey`. |
| **Animasi** | Garis stepper terisi dari kiri ke kanan; modal slide-up; teks diketik dengan cursor blink; status pill “Menunggu” berdenyut satu kali; transisi card slide. |
| **Teks layar** | **Murid**. Subteks: “Jurnal harian & progres 6 tahap”. |
| **Voice-over** | “Siswa mencatat jurnal harian dan memantau progres enam tahap PKL dari satu dashboard.” |
| **Audio** | Keyboard, klik simpan, whoosh progres halus. |
| **Catatan akurasi** | Jurnal dapat dibuat; belum ada edit/hapus jurnal oleh siswa. Stepper adalah visualisasi progres, bukan workflow pengajuan. |

### 4.8 Scene 7 — Guru Memverifikasi dan Menilai

| Elemen | Detail |
|---|---|
| **Waktu** | 00:28–00:33 (5 detik) |
| **Visual** | Dashboard Guru dengan kartu statistik dan panel “Notifikasi & Aktivitas Bimbingan”. Kursor menekan “Setujui” pada jurnal; status berubah menjadi “Disetujui”. Potong ke tab Laporan, tekan “Setujui Laporan PKL”. Potong ke modal penilaian: tiga skor diisi, nilai akhir dihitung otomatis. |
| **Screenshot/aset** | `dashboard/guru.php` (dashboard); `#section-jurnal`; `#section-laporan`; modal penilaian. |
| **Animasi** | Kursor klik; status pill flip dengan warna hijau; toast sukses; field skor terisi; angka nilai akhir count-up; transisi match cut. |
| **Teks layar** | **Guru Pembimbing**. Subteks: “Verifikasi • Review • Nilai”. |
| **Voice-over** | “Guru pembimbing memverifikasi jurnal, meninjau laporan, dan memberi penilaian.” |
| **Audio** | Klik, chime konfirmasi, tick halus setiap skor. |
| **Catatan akurasi** | Laporan berupa ringkasan teks, bukan file upload. Penilaian guru memakai tiga kriteria. |

### 4.9 Scene 8 — Pembimbing Industri Menilai Kinerja

| Elemen | Detail |
|---|---|
| **Waktu** | 00:33–00:38 (5 detik) |
| **Visual** | Dashboard Pembimbing Industri: kartu Siswa Praktik, Sudah Dinilai, Belum Dinilai, Jurnal Masuk. Potong ke tab “Aktivitas & Jurnal” yang bersifat pemantauan. Lalu modal penilaian industri: tiga field skor diisi dan nilai akhir muncul. |
| **Screenshot/aset** | `dashboard/industri.php` (dashboard); `#section-jurnal`; modal penilaian industri. |
| **Animasi** | Kartu slide dari kanan; baris jurnal disorot; field skor terisi; ring/bar nilai akhir terisi; transisi vertical wipe. |
| **Teks layar** | **Pembimbing Industri**. Subteks: “Pantau aktivitas • Nilai kinerja”. |
| **Voice-over** | “Pembimbing industri memantau aktivitas kerja dan menilai kedisiplinan, kehadiran, serta kualitas hasil kerja.” |
| **Audio** | Pulse teknologi lembut, klik, tick skor. |
| **Catatan akurasi** | “Kehadiran” di sini adalah **kriteria penilaian**, bukan modul absensi. Jangan menampilkan menu absensi. |

### 4.10 Scene 9 — Penguji Menelaah dan Memberi Nilai

| Elemen | Detail |
|---|---|
| **Waktu** | 00:38–00:44 (6 detik) |
| **Visual** | Dashboard Penguji: kartu Peserta Sidang, Sidang Dinilai, Menunggu Sidang, Jurnal Diuji. Potong ke “Telaah Jurnal & Laporan” yang menampilkan ringkasan laporan dan log jurnal. Potong ke “Penilaian Sidang” dengan tiga kriteria. Potong ke “Penilaian Penguji (Produktif & Jurnal)” dengan lima kriteria per kategori. |
| **Screenshot/aset** | `dashboard/penguji.php` (dashboard); `#section-jurnal`; `#section-penilaian`; `#section-examiner`. |
| **Animasi** | Rangkaian quick cut dengan blue wipe; checkmark muncul pada tiap panel; kartu skor bergeser; split screen untuk kategori Produktif dan Jurnal; transisi zoom out. |
| **Teks layar** | **Penguji**. Subteks: “Telaah • Sidang • Produktif & Jurnal”. |
| **Voice-over** | “Penguji menelaah jurnal dan laporan, lalu memberi nilai sidang serta penilaian produktif dan jurnal.” |
| **Audio** | Page swipe, tick, riser lembut menuju rekap. |
| **Catatan akurasi** | Sidang memakai 3 kriteria + nilai akhir. Produktif dan Jurnal masing-masing 5 kriteria, tanpa nilai gabungan otomatis. Jangan menampilkan tombol approve jurnal di sisi penguji. |

### 4.11 Scene 10 — Rekap Lima Peran

| Elemen | Detail |
|---|---|
| **Waktu** | 00:44–00:48 (4 detik) |
| **Visual** | Lima kartu role tersusun melingkar mengelilingi logo PKL Journey. Setiap kartu berisi miniatur UI dari scene sebelumnya: admin, murid, guru, industri, penguji. Garis biru menghubungkan semua kartu ke logo. |
| **Screenshot/aset** | Potongan UI dari S4–S9 dan `assets/logo.svg`. |
| **Animasi** | Garis orbit digambar; kartu menyala bergantian dengan stagger 120 ms; glow biru lembut; kamera pull-back; transisi zoom ke logo. |
| **Teks layar** | **Satu alur. Lima peran. Terpantau.** |
| **Voice-over** | “Satu alur kerja, lima peran, semua tercatat rapi dan mudah dipantau.” |
| **Audio** | Chord naik, riser lembut, tidak boleh menutupi VO. |
| **Catatan akurasi** | Rekap tidak boleh menambah klaim baru. Gunakan hanya fitur yang sudah ditampilkan. |

### 4.12 Scene 11 — Penutup, Identitas Sekolah, dan Area QR

| Elemen | Detail |
|---|---|
| **Waktu** | 00:48–00:55 (7 detik) |
| **Visual** | End card bersih. Latar putih dengan arc gradasi biru dan pola titik halus. Kiri: logo 96 px + wordmark “PKL Journey” + tagline. Kanan: area QR 320 × 320 px. Bawah: identitas sekolah. |
| **Screenshot/aset** | `assets/logo.svg`. Tidak memakai screenshot dashboard. |
| **Animasi** | Logo settle dengan scale 96% → 100%; teks fade-up; border area QR digambar (dashed); label “Pindai untuk mengakses” muncul; hold 2 detik; fade out lembut. |
| **Teks layar** | **PKL Journey** / **Perjalanan PKL Lebih Terarah** / **SMK Telekomunikasi Telesandi Bekasi** / **Pindai untuk mengakses** |
| **Voice-over** | “PKL Journey. Perjalanan PKL lebih terarah. SMK Telekomunikasi Telesandi Bekasi.” |
| **Audio** | Musik resolve ke chord akhir; chime lembut; tail 1 detik. |
| **Catatan akurasi** | **Jangan membuat QR Code palsu.** Area QR harus kosong atau diisi QR asli yang diberikan pengguna. |

---

## 5. Naskah Voice-Over Utuh

### 5.1 Naskah per scene

| Scene | Waktu | Narasi | Jumlah kata | Estimasi baca | Arahan |
|---|---|---|---:|---:|---|
| S1 | 00:00–00:04 | “Praktik Kerja Lapangan. Kini dalam satu perjalanan yang lebih terarah.” | 11 | ±3,9 s | Hangat, optimistis; beri jeda pendek setelah “Praktik Kerja Lapangan”. |
| S2 | 00:04–00:10 | “Data siswa, jurnal, bimbingan, hingga penilaian. Dulu tersebar, kini menyatu dalam satu platform.” | 15 | ±5,4 s | Kontras: “Dulu tersebar” lebih pelan, “kini menyatu” lebih cerah. |
| S3 | 00:10–00:15 | “PKL Journey. Platform PKL digital yang menghubungkan sekolah, siswa, dan dunia industri.” | 13 | ±4,6 s | Tegas dan bangga; jeda setelah “PKL Journey”. |
| S4 | 00:15–00:19 | “Admin mengelola akun, perusahaan mitra, dan penempatan PKL dari satu panel.” | 11 | ±3,9 s | Informatif, tidak terburu-buru. |
| S5 | 00:19–00:23 | “Data siswa diimpor dari Excel, lengkap dengan pratinjau dan validasi.” | 10 | ±3,6 s | Ringkas; tekan “pratinjau” dan “validasi”. |
| S6 | 00:23–00:28 | “Siswa mencatat jurnal harian dan memantau progres enam tahap PKL dari satu dashboard.” | 13 | ±4,6 s | Ramah, suportif. |
| S7 | 00:28–00:33 | “Guru pembimbing memverifikasi jurnal, meninjau laporan, dan memberi penilaian.” | 10 | ±3,6 s | Jelas; beri penekanan pada tiga kata kerja. |
| S8 | 00:33–00:38 | “Pembimbing industri memantau aktivitas kerja dan menilai kedisiplinan, kehadiran, serta kualitas hasil kerja.” | 14 | ±5,0 s | Profesional; jangan terkesan menghakimi. |
| S9 | 00:38–00:44 | “Penguji menelaah jurnal dan laporan, lalu memberi nilai sidang serta penilaian produktif dan jurnal.” | 15 | ±5,4 s | Tenang dan berwibawa. |
| S10 | 00:44–00:48 | “Satu alur kerja, lima peran, semua tercatat rapi dan mudah dipantau.” | 11 | ±3,9 s | Ringkas, menguatkan. |
| S11 | 00:48–00:55 | “PKL Journey. Perjalanan PKL lebih terarah. SMK Telekomunikasi Telesandi Bekasi.” | 10 | ±3,6 s + jeda | Hangat dan bangga; jeda sebelum nama sekolah. |

### 5.2 Naskah utuh untuk perekaman

> Praktik Kerja Lapangan. Kini dalam satu perjalanan yang lebih terarah.
>
> Data siswa, jurnal, bimbingan, hingga penilaian. Dulu tersebar, kini menyatu dalam satu platform.
>
> PKL Journey. Platform PKL digital yang menghubungkan sekolah, siswa, dan dunia industri.
>
> Admin mengelola akun, perusahaan mitra, dan penempatan PKL dari satu panel.
>
> Data siswa diimpor dari Excel, lengkap dengan pratinjau dan validasi.
>
> Siswa mencatat jurnal harian dan memantau progres enam tahap PKL dari satu dashboard.
>
> Guru pembimbing memverifikasi jurnal, meninjau laporan, dan memberi penilaian.
>
> Pembimbing industri memantau aktivitas kerja dan menilai kedisiplinan, kehadiran, serta kualitas hasil kerja.
>
> Penguji menelaah jurnal dan laporan, lalu memberi nilai sidang serta penilaian produktif dan jurnal.
>
> Satu alur kerja, lima peran, semua tercatat rapi dan mudah dipantau.
>
> PKL Journey. Perjalanan PKL lebih terarah. SMK Telekomunikasi Telesandi Bekasi.

### 5.3 Arahan talenta suara

- **Karakter suara:** dewasa, hangat, profesional, tidak seperti iklan jualan agresif.
- **Tempo:** 150–160 kata per menit; lebih pelan pada S2, S8, dan S11.
- **Artikulasi:** “PKL Journey” dibaca jelas sebagai *pe-ka-el jeur-ni*; “DUDI” sebagai *du-di*; “SMK Telekomunikasi Telesandi Bekasi” diucapkan utuh dan jelas.
- **Jeda:** 0,3–0,5 detik di setiap pergantian scene; 0,6 detik sebelum nama sekolah di S11.
- **Hindari:** nada bertanya, nada terburu-buru, dan penekanan berlebihan pada kata “digital”.

---

## 6. Daftar Screenshot dan Rekaman Layar yang Perlu Disiapkan

### 6.1 Screenshot statis

| ID | Halaman / URL | Kondisi yang diambil | Crop / framing | Scene |
|---|---|---|---|---|
| S-01 | `landing.php` | Hero: “Perjalanan PKL Lebih Terarah”, tombol Masuk Sekarang | Full viewport, browser frame | S3 |
| S-02 | `landing.php#akses` | Lima kartu role | Crop bagian role saja; hindari kartu fitur absensi | S3 |
| S-03 | `index.php?login=1` | Form login kosong, label NIS/Username dan Password | Card login, sudut membulat | S3 |
| S-04 | `dashboard/admin.php` | Dashboard: lima kartu statistik dan ringkasan pembagian murid | Full content area | S4 |
| S-05 | `dashboard/admin.php?tab=section-murid` | Panel import, tombol Download Template Excel, area upload | Crop panel import | S5 |
| S-06 | `dashboard/admin.php?tab=section-murid` | Pratinjau import: total, valid, tidak valid | Crop ringkasan + beberapa baris tabel | S5 |
| S-07 | `dashboard/admin.php?tab=section-murid` | Banner sukses “Import Data Murid Berhasil” | Crop banner + daftar akun; blur password | S5 |
| S-08 | `dashboard/admin.php?tab=section-pembimbing` | Penugasan pembimbing massal | Crop form dan tabel siswa | S4 |
| S-09 | `dashboard/admin.php?tab=section-pembimbing` | Modal penetapan pembimbing, penguji, dan lokasi | Crop modal | S4 |
| S-10 | `dashboard/admin.php?tab=section-perusahaan` | Daftar perusahaan mitra | Crop tabel perusahaan | S4 |
| S-11 | `dashboard/admin.php?tab=section-penguji` | Penugasan penguji Produktif/Jurnal dan monitoring | Crop form + tabel | S9 |
| S-12 | `dashboard/murid.php` | Dashboard: statistik, profil, tim pembimbing | Full content area | S6 |
| S-13 | `dashboard/murid.php` | Stepper “Peta Perjalanan PKL” enam tahap | Crop stepper | S6 |
| S-14 | `dashboard/murid.php` | Modal “Catat Jurnal Kegiatan Baru” | Crop modal | S6 |
| S-15 | `dashboard/murid.php` | Tabel jurnal dengan status Menunggu/Disetujui/Revisi | Crop tabel | S6 |
| S-16 | `dashboard/murid.php` | Panel penilaian siswa (guru, industri, sidang, produktif, jurnal) | Crop dua panel representatif | S9 |
| S-17 | `dashboard/murid.php` | Form laporan PKL: judul + ringkasan | Crop form | S7 |
| S-18 | `dashboard/guru.php` | Dashboard: statistik dan notifikasi bimbingan | Full content area | S7 |
| S-19 | `dashboard/guru.php` | Tabel jurnal dengan tombol Setujui/Revisi | Crop tabel | S7 |
| S-20 | `dashboard/guru.php` | Verifikasi laporan: ringkasan + tombol Setujui/Minta Revisi | Crop kartu laporan | S7 |
| S-21 | `dashboard/guru.php` | Modal penilaian: tiga skor + nilai akhir | Crop modal | S7 |
| S-22 | `dashboard/guru.php` | Peta progress siswa dengan stepper | Crop satu kartu siswa | S6/S10 |
| S-23 | `dashboard/industri.php` | Dashboard: empat kartu statistik | Full content area | S8 |
| S-24 | `dashboard/industri.php` | Tab “Aktivitas & Jurnal” | Crop tabel jurnal | S8 |
| S-25 | `dashboard/industri.php` | Modal penilaian industri: tiga kriteria | Crop modal | S8 |
| S-26 | `dashboard/penguji.php` | Dashboard: empat kartu statistik | Full content area | S9 |
| S-27 | `dashboard/penguji.php` | Telaah jurnal & laporan | Crop ringkasan laporan + tabel jurnal | S9 |
| S-28 | `dashboard/penguji.php` | Modal penilaian sidang: tiga kriteria | Crop modal | S9 |
| S-29 | `dashboard/penguji.php` | Tab penilaian penguji Produktif & Jurnal | Crop tab + modal 5 kriteria | S9 |
| S-30 | `assets/logo.svg` | Logo resmi | Transparan, 1024 px | S1, S10, S11 |

### 6.2 Rekaman layar

| ID | Rekaman | Durasi target | Detail |
|---|---|---:|---|
| R-01 | Login transition | 3–4 s | Isi NIS/username dan password, tampilkan overlay “Checking account…” → “Verifying access…” → “Journey ready ✓”, lalu masuk dashboard. |
| R-02 | Admin import Excel | 5–6 s | Buka tab Data Murid, unduh template (opsional), upload file, tunggu pratinjau, konfirmasi, tampilkan sukses. |
| R-03 | Admin penempatan | 4–5 s | Buka tab Atur Guru Pembimbing, pilih siswa, buka modal, pilih perusahaan/guru/industri/penguji, simpan. |
| R-04 | Murid membuat jurnal | 4–5 s | Buka modal, isi tanggal/judul/deskripsi, simpan, lihat status Menunggu. |
| R-05 | Guru verifikasi jurnal | 3–4 s | Klik Setujui pada satu jurnal, tunjukkan perubahan status. |
| R-06 | Guru menilai | 3–4 s | Buka modal penilaian, isi tiga skor, tunjukkan nilai akhir terhitung. |
| R-07 | Industri menilai | 3–4 s | Buka modal penilaian industri, isi tiga skor, simpan. |
| R-08 | Penguji menilai | 5–6 s | Buka telaah jurnal/laporan, lalu penilaian sidang, lalu tab produktif/jurnal. |
| R-09 | Scroll journey | 3–4 s | Scroll halus dari statistik ke stepper enam tahap. |
| R-10 | Micro-interaction | 2–3 s | Hover kartu, klik tab, buka/tutup modal, tanpa gerakan kursor yang terlalu cepat. |

### 6.3 Persiapan data dan lingkungan rekaman

1. Gunakan akun demo dari seed yang tersedia, atau akun demo lain yang bersih.
2. Pastikan satu siswa demo memiliki:
   - beberapa jurnal dengan status berbeda,
   - satu laporan terkirim,
   - penilaian guru, industri, sidang, produktif, dan jurnal.
3. Jika data demo kurang, gunakan **salinan database terpisah** untuk rekaman. Jangan mengubah database produksi.
4. Samarkan email, NIS, dan nama asli bila memakai data nyata. Gunakan nama demo yang konsisten.
5. Sembunyikan password yang tampil pada hasil import.
6. Matikan notifikasi OS, bookmark bar, dan extension browser.
7. Gunakan mode terang, zoom browser 100%, ukuran jendela konsisten.
8. Rekam pada resolusi 1920 × 1080, 60 fps; screenshot pada 2× DPR agar tajam.

### 6.4 Spesifikasi screenshot

- Kanvas: 1920 × 1080 px, 16:9.
- Capture browser: 1600 × 900 CSS px, device scale factor 2.
- Format: PNG, tanpa kompresi lossy.
- Bingkai: sudut membulat 14–16 px, border 1 px `#E2E8F0`, shadow lembut.
- Jangan menampilkan data pribadi, password, atau alamat email asli.
- Konsisten menggunakan browser frame yang sama di semua scene.

---

## 7. Panduan Visual dan Audio untuk Pembuatan Motion Video

### 7.1 Sistem visual

**Palet warna resmi aplikasi:**

| Warna | Hex | Penggunaan |
|---|---|---|
| Navy Deep | `#0F2744` | Teks utama, background gelap, logo |
| Navy Blue | `#1E3A8A` | Gradasi, elemen sekunder |
| Sky Blue | `#0284C7` | Aksen utama, garis, tombol, highlight |
| Light Sky | `#38BDF8` | Glow, gradient terang |
| Accent Light | `#E0F2FE` | Latar chip, highlight lembut |
| Canvas | `#F8FAFC` | Background utama |
| White | `#FFFFFF` | Kartu, ruang kosong |
| Slate | `#64748B` | Subteks |
| Border | `#E2E8F0` | Garis dan pemisah |
| Success | `#16A34A` | Status disetujui, centang |

**Aturan warna:**
- Dominan putih dan navy; biru digunakan sebagai aksen, bukan memenuhi seluruh layar.
- Hijau hanya untuk status berhasil/disetujui.
- Merah/oranye hanya untuk revisi atau peringatan, dan dipakai sangat sedikit.
- Jangan memakai gradasi ungu, pink, atau neon yang tidak ada di aplikasi.

**Tipografi:**
- Font: **Plus Jakarta Sans** (sama dengan aplikasi).
- Judul: 700–800, ukuran 64–96 px untuk headline scene.
- Subteks: 500–600, ukuran 28–36 px.
- Label role: 700, 24–28 px, huruf kapital dengan tracking 4–6%.
- Subtitle: 600, 32–36 px, maksimal dua baris.

**Layout:**
- Grid 12 kolom, margin aman 5% dari tepi.
- Elemen utama berada di tengah; teks tidak menutupi bagian penting UI.
- Gunakan ruang kosong yang cukup; jangan memenuhi layar dengan kartu.
- Setiap scene memiliki satu fokus utama.

**Perlakuan screenshot:**
- Tempatkan screenshot dalam browser frame atau card dengan sudut 14–16 px.
- Beri border tipis dan shadow `0 24px 60px rgba(15,39,68,0.12)`.
- Zoom 100–110%; jangan melakukan zoom berlebihan hingga teks pecah.
- Sorot area penting dengan rounded rectangle biru transparan, bukan panah besar.
- Gunakan kursor yang halus dan konsisten; jangan menampilkan kursor asli yang bergerak acak.

**Ikon:**
- Gunakan ikon outline dari `includes/icons.php` agar konsisten dengan aplikasi.
- Ketebalan stroke 2 px; ukuran 24–32 px.
- Jangan mencampur ikon filled dan outline.

### 7.2 Prinsip animasi

- **Durasi:** 0.4–0.6 detik untuk sebagian besar gerakan.
- **Easing:** `cubic-bezier(0.4, 0, 0.2, 1)`; hindari bounce/elastic.
- **Stagger:** 80–120 ms antar elemen.
- **Gerakan:** slide 16–32 px, fade, mask reveal, scale 96–100%.
- **Kamera:** push-in maksimal 110%; jangan goyang.
- **Transisi:** blue wipe, radial wipe, match cut, line draw, zoom through checkmark.
- **Jangan:** animasi berlebihan, spin, flip 3D, partikel ramai, atau gerakan yang mengalihkan perhatian dari UI.

**Kosakata transisi:**
1. **Blue wipe** — untuk perpindahan antar role.
2. **Line draw** — untuk menghubungkan lima role dan stepper.
3. **Match cut** — dari ikon/objek ke elemen UI yang sama.
4. **Zoom through** — dari centang validasi ke scene berikutnya.
5. **Card slide** — untuk memunculkan panel dashboard.

### 7.3 Lower third dan teks role

Format lower third:
- Chip kecil dengan ikon role + nama role, di kiri bawah.
- Latar putih 92% opacity, border `#E2E8F0`, sudut 999 px.
- Teks navy 700, 24 px.
- Muncul 0.2 detik setelah cut, bertahan sampai 0.2 detik sebelum cut.
- Jangan menampilkan lebih dari satu lower third sekaligus.

### 7.4 Subtitle

- Bahasa Indonesia, sesuai naskah.
- Maksimal dua baris; 32–36 px; putih dengan backdrop navy 60%.
- Posisi di dalam safe area, tidak menutupi tombol atau angka penting.
- Burn-in subtitle opsional; jika video juga akan diunggah ke media sosial, sediakan versi tanpa subtitle agar bisa memakai caption platform.

### 7.5 End card dan area QR

**Wireframe end card:**

```
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│   [LOGO]  PKL JOURNEY                     ┌──────────────┐   │
│           Perjalanan PKL Lebih Terarah    │              │   │
│                                           │   AREA QR    │   │
│                                           │   320×320    │   │
│                                           │              │   │
│                                           └──────────────┘   │
│                                           Pindai untuk akses  │
│                                                              │
│   SMK Telekomunikasi Telesandi Bekasi                        │
│   Platform PKL Digital                                       │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**Spesifikasi area QR:**
- Ukuran 320 × 320 px pada kanvas 1920 × 1080.
- Kotak putih, sudut membulat 24 px, border 1 px `#E2E8F0`.
- Di versi internal, beri label kecil “Area QR — menunggu file” **di luar** area final atau di layer terpisah.
- Di versi final, tempel QR asli dari pengguna; jangan render pola QR palsu.
- Jangan menambahkan logo di tengah QR tanpa instruksi pembuat QR.
- Sediakan margin putih (quiet zone) minimal 4 modul di sekeliling QR.

### 7.6 Audio

**Musik:**
- Genre: corporate technology, minimal, hangat.
- Tempo: 100–105 BPM.
- Instrumen: piano lembut, synth pad, bass ringan, perkusi halus.
- Tanpa vokal.
- Struktur: intro 0–10 s, groove 10–44 s, resolve 44–55 s.
- Ending: chord yang selesai, bukan fade mendadak.

**Efek suara:**

| Waktu | SFX | Volume |
|---|---|---|
| 00:00 | Logo whoosh + soft click | -18 dB |
| 00:04 | Paper rustle + keyboard | -20 dB |
| 00:08 | Convergence whoosh | -16 dB |
| 00:15 | UI clicks | -22 dB |
| 00:19 | File drop + validation tick | -18 dB |
| 00:22 | Success chime | -16 dB |
| 00:23 | Keyboard typing | -22 dB |
| 00:28 | Approve click + confirm chime | -16 dB |
| 00:33 | Soft tech pulse + score ticks | -20 dB |
| 00:38 | Page swipe + ticks | -18 dB |
| 00:44 | Soft riser | -18 dB |
| 00:48 | Final chime | -16 dB |

**Mix:**
- VO: peak sekitar -6 dBFS; jelas dan di depan.
- Musik: -18 sampai -22 LUFS di bawah VO; ducking -12 dB saat VO berbicara.
- SFX: -12 sampai -22 dB, tidak menutupi konsonan VO.
- Master: -14 LUFS integrated, true peak -1 dBTP.
- Hindari limiter berlebihan hingga suara “pump”.

**Rekaman VO:**
- Mikrofon condenser cardioid, 48 kHz/24-bit.
- Jarak 15–20 cm, sedikit off-axis untuk mengurangi plosif.
- High-pass filter 80 Hz; de-ess ringan; kompresi 3:1; normalize.
- Rekam per scene agar mudah disinkronkan.
- Simpan versi tanpa musik untuk kebutuhan revisi.

### 7.7 Spesifikasi render dan ekspor

- Resolusi: 1920 × 1080, 16:9.
- Frame rate: 30 fps untuk motion graphic; 60 fps untuk rekaman layar yang di-speed-ramp.
- Codec: H.264, MP4, bitrate 12–16 Mbps.
- Audio: AAC 320 kbps, 48 kHz, stereo.
- Warna: Rec.709; jangan gunakan LUT yang mengubah brand color.
- Sediakan:
  - versi final dengan subtitle,
  - versi tanpa subtitle,
  - versi 9:16 (1080 × 1920) untuk media sosial,
  - thumbnail 1280 × 720.

---

## 8. Checklist Akurasi Sebelum Produksi

### 8.1 Wajib ditampilkan

- [ ] Landing page dan identitas PKL Journey.
- [ ] Login dengan NIS/username dan password.
- [ ] Lima role: Admin, Murid, Guru Sekolah, Pembimbing Industri, Penguji.
- [ ] Dashboard Admin dan penempatan PKL.
- [ ] Import Excel dengan template, pratinjau, dan validasi.
- [ ] Jurnal harian siswa.
- [ ] Verifikasi jurnal dan laporan oleh guru.
- [ ] Penilaian guru, industri, sidang, produktif, dan jurnal.
- [ ] Progress enam tahap sebagai visualisasi.
- [ ] Logo, nama sekolah, dan area QR di penutup.

### 8.2 Dilarang ditampilkan atau diklaim

- [ ] Modul absensi mandiri.
- [ ] Export Excel/rekap.
- [ ] Upload file laporan/PDF.
- [ ] QR Code di dalam aplikasi.
- [ ] Registrasi mandiri publik.
- [ ] Chat, notifikasi push, email, atau WhatsApp.
- [ ] Pengajuan tempat PKL sebagai workflow siswa.
- [ ] Sertifikat/PDF otomatis.
- [ ] Klaim “real-time” jika hanya refresh halaman.
- [ ] Data pribadi siswa, email asli, atau password asli.

### 8.3 Daftar kesalahan umum dan koreksinya

| Risiko | Contoh salah | Koreksi |
|---|---|---|
| Absensi | “Kelola absensi siswa” | “Kehadiran menjadi kriteria penilaian industri” |
| Export | “Unduh rekap Excel” | “Import Excel dan unduh template” |
| Laporan | “Unggah laporan PDF” | “Kirim ringkasan laporan” |
| Pengajuan | “Siswa mengajukan tempat PKL” | “Admin menempatkan siswa” |
| QR | “Scan QR di aplikasi” | “QR hanya di end card untuk mengakses informasi” |
| Notifikasi | “Notifikasi otomatis real-time” | “Pantau aktivitas di dashboard” |
| Data | Menampilkan nama/email asli | Gunakan akun demo dan blur data |

---

## 9. Penutup

Konsep ini dirancang untuk menampilkan PKL Journey apa adanya: aplikasi web PHP Native + MySQL dengan lima dashboard role, jurnal digital, penempatan PKL, import Excel, dan penilaian berlapis. Video tidak memakai fitur yang belum ditemukan, tidak mengklaim absensi atau export Excel, dan tidak membuat QR Code palsu.

**Durasi final:** 55 detik.  
**Pesan akhir:** *PKL Journey — Perjalanan PKL Lebih Terarah.*  
**Identitas penutup:** SMK Telekomunikasi Telesandi Bekasi.  
**Area QR:** disediakan khusus, diisi QR asli dari pengguna sebelum render final.
