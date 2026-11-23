<?php
// landing.php — PKL Journey Landing Page (public, no login required)
session_start();

// Logged-in users go straight to their dashboard.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    $map = [
        'murid'         => 'dashboard/murid.php',
        'guru'          => 'dashboard/guru.php',
        'admin'         => 'dashboard/admin.php',
        'guru_industri' => 'dashboard/industri.php',
        'penguji'       => 'dashboard/penguji.php',
    ];
    header('Location: ' . ($map[$_SESSION['role']] ?? 'dashboard/murid.php'));
    exit;
}

require_once __DIR__ . '/includes/icons.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PKL Journey - Platform Praktik Kerja Lapangan</title>
  <meta name="description" content="PKL Journey membantu sekolah, siswa, dan industri mengelola kegiatan Praktik Kerja Lapangan secara terpadu dan terstruktur.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* =====================================================
       Landing Page — scoped styles, all using existing
       CSS custom properties from css/style.css
       ===================================================== */

    /* Reset for landing sections */
    .lp-body {
      background: var(--color-bg-canvas);
      color: var(--color-text);
      font-family: var(--font-family);
    }

    /* ---- Navbar ---- */
    .lp-nav {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid var(--color-border);
    }
    .lp-nav-inner {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 1.5rem;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .lp-logo {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      text-decoration: none;
      color: var(--color-primary-dark);
      font-weight: 800;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .lp-logo span { color: var(--color-accent); }
    .lp-nav-links {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      list-style: none;
    }
    .lp-nav-links a {
      padding: 0.45rem 0.85rem;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--color-slate-500);
      text-decoration: none;
      border-radius: var(--radius-sm);
      transition: color 0.2s, background 0.2s;
    }
    .lp-nav-links a:hover {
      color: var(--color-primary);
      background: var(--color-slate-100);
    }
    .lp-nav-cta {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1.25rem;
      background: var(--color-primary-dark);
      color: #fff !important;
      border-radius: var(--radius-md);
      font-size: 0.88rem;
      font-weight: 700;
      text-decoration: none;
      transition: background 0.2s, transform 0.2s;
      flex-shrink: 0;
    }
    .lp-nav-cta:hover {
      background: var(--color-accent);
      transform: translateY(-1px);
    }
    /* Mobile hamburger */
    .lp-hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      padding: 0.4rem;
      background: none;
      border: none;
    }
    .lp-hamburger span {
      display: block;
      width: 22px;
      height: 2px;
      background: var(--color-primary-dark);
      border-radius: 2px;
      transition: var(--transition);
    }
    .lp-mobile-menu {
      display: none;
      flex-direction: column;
      background: #fff;
      border-top: 1px solid var(--color-border);
      padding: 0.75rem 1.5rem 1rem;
      gap: 0.1rem;
    }
    .lp-mobile-menu.open { display: flex; }
    .lp-mobile-menu a {
      padding: 0.6rem 0.5rem;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--color-text);
      text-decoration: none;
      border-bottom: 1px solid var(--color-border);
    }
    .lp-mobile-menu a:last-child { border-bottom: none; }
    .lp-mobile-menu a:hover { color: var(--color-accent); }

    /* ---- Section base ---- */
    .lp-section {
      padding: 5rem 1.5rem;
    }
    .lp-section-alt {
      background: var(--color-bg-card);
    }
    .lp-container {
      max-width: 1120px;
      margin: 0 auto;
    }
    .lp-section-label {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--color-accent);
      background: var(--color-accent-light);
      border-radius: var(--radius-full);
      padding: 0.3rem 0.9rem;
      margin-bottom: 1rem;
    }
    .lp-section-title {
      font-size: 2rem;
      font-weight: 800;
      color: var(--color-primary-dark);
      line-height: 1.25;
      margin-bottom: 1rem;
    }
    .lp-section-title span { color: var(--color-accent); }
    .lp-section-desc {
      font-size: 1rem;
      color: var(--color-muted);
      line-height: 1.7;
      max-width: 560px;
    }

    /* ---- Hero ---- */
    .lp-hero {
      background: linear-gradient(140deg, var(--color-primary-dark) 0%, #1a2f6a 55%, var(--color-accent) 100%);
      padding: 6rem 1.5rem 5rem;
      text-align: center;
      color: #fff;
    }
    .lp-hero-eyebrow {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.75);
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: var(--radius-full);
      padding: 0.3rem 1rem;
      margin-bottom: 1.5rem;
    }
    .lp-hero-title {
      font-size: clamp(2rem, 5vw, 3.25rem);
      font-weight: 800;
      line-height: 1.18;
      margin-bottom: 1.25rem;
      color: #fff;
    }
    .lp-hero-title span { color: #7dd3fc; }
    .lp-hero-desc {
      font-size: 1.05rem;
      color: rgba(255,255,255,0.82);
      max-width: 560px;
      margin: 0 auto 2.25rem;
      line-height: 1.7;
    }
    .lp-hero-actions {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    .lp-btn-hero {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.85rem 2rem;
      border-radius: var(--radius-md);
      font-size: 1rem;
      font-weight: 700;
      text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .lp-btn-hero.primary {
      background: #fff;
      color: var(--color-primary-dark);
      box-shadow: 0 4px 18px rgba(0,0,0,0.18);
    }
    .lp-btn-hero.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.22);
    }
    .lp-btn-hero.outline {
      background: rgba(255,255,255,0.1);
      color: #fff;
      border: 1.5px solid rgba(255,255,255,0.4);
    }
    .lp-btn-hero.outline:hover {
      background: rgba(255,255,255,0.18);
      transform: translateY(-2px);
    }

    /* ---- Tentang ---- */
    .lp-about-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
      align-items: center;
    }
    .lp-about-visual {
      background: linear-gradient(135deg, var(--color-primary-dark) 0%, #1e3a8a 100%);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .lp-about-stat {
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: var(--radius-md);
      padding: 1rem 1.25rem;
      color: #fff;
    }
    .lp-about-stat-num {
      font-size: 1.75rem;
      font-weight: 800;
      color: #7dd3fc;
    }
    .lp-about-stat-label {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.72);
      margin-top: 0.2rem;
    }

    /* ---- Fitur ---- */
    .lp-features-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.5rem;
      margin-top: 3rem;
    }
    .lp-feature-card {
      background: var(--color-bg-card);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 1.75rem;
      transition: box-shadow 0.2s, transform 0.2s;
    }
    .lp-feature-card:hover {
      box-shadow: 0 8px 28px var(--color-card-shadow);
      transform: translateY(-3px);
    }
    .lp-feature-icon {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      background: var(--color-accent-light);
      color: var(--color-accent);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
    }
    .lp-feature-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--color-primary-dark);
      margin-bottom: 0.5rem;
    }
    .lp-feature-desc {
      font-size: 0.88rem;
      color: var(--color-muted);
      line-height: 1.65;
    }

    /* ---- Cara Kerja ---- */
    .lp-steps {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.5rem;
      margin-top: 3rem;
      position: relative;
    }
    .lp-steps::before {
      content: '';
      position: absolute;
      top: 28px;
      left: calc(12.5% + 0.75rem);
      right: calc(12.5% + 0.75rem);
      height: 2px;
      background: var(--color-border);
      z-index: 0;
    }
    .lp-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
      z-index: 1;
    }
    .lp-step-num {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--color-primary-dark);
      color: #fff;
      font-size: 1.1rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      flex-shrink: 0;
      border: 3px solid var(--color-bg-card);
      box-shadow: 0 0 0 3px var(--color-border);
    }
    .lp-step-title {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--color-primary-dark);
      margin-bottom: 0.4rem;
    }
    .lp-step-desc {
      font-size: 0.82rem;
      color: var(--color-muted);
      line-height: 1.6;
    }

    /* ---- Akses Pengguna ---- */
    .lp-roles-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
      margin-top: 3rem;
    }
    .lp-role-card {
      background: var(--color-bg-card);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 1.5rem;
      border-top: 3px solid var(--color-accent);
    }
    .lp-role-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-sm);
      background: var(--color-primary-dark);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 0.9rem;
    }
    .lp-role-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--color-primary-dark);
      margin-bottom: 0.4rem;
    }
    .lp-role-desc {
      font-size: 0.82rem;
      color: var(--color-muted);
      line-height: 1.6;
    }

    /* ---- CTA Banner ---- */
    .lp-cta {
      background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-accent) 100%);
      padding: 4rem 1.5rem;
      text-align: center;
      color: #fff;
    }
    .lp-cta-title {
      font-size: 1.75rem;
      font-weight: 800;
      margin-bottom: 0.75rem;
    }
    .lp-cta-desc {
      font-size: 0.95rem;
      color: rgba(255,255,255,0.8);
      margin-bottom: 2rem;
    }

    /* ---- Footer ---- */
    .lp-footer {
      background: var(--color-primary-dark);
      color: rgba(255,255,255,0.65);
      padding: 2rem 1.5rem;
      text-align: center;
    }
    .lp-footer-brand {
      font-size: 1rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 0.4rem;
    }
    .lp-footer-sub {
      font-size: 0.82rem;
    }
    .lp-footer-divider {
      width: 32px;
      height: 2px;
      background: var(--color-accent);
      margin: 0.75rem auto;
      border-radius: 2px;
    }

    /* ---- Responsive ---- */
    @media (max-width: 900px) {
      .lp-about-grid { grid-template-columns: 1fr; }
      .lp-features-grid { grid-template-columns: 1fr; }
      .lp-steps {
        grid-template-columns: repeat(2, 1fr);
      }
      .lp-steps::before { display: none; }
      .lp-roles-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
      .lp-nav-links { display: none; }
      .lp-nav-cta.desktop-only { display: none; }
      .lp-hamburger { display: flex; }
      .lp-section { padding: 3.5rem 1.25rem; }
      .lp-section-title { font-size: 1.55rem; }
      .lp-hero { padding: 4.5rem 1.25rem 3.5rem; }
      .lp-steps { grid-template-columns: 1fr; }
      .lp-roles-grid { grid-template-columns: 1fr; }
      .lp-btn-hero { padding: 0.75rem 1.5rem; font-size: 0.9rem; }
      .lp-about-visual { padding: 1.75rem; }
    }
  </style>
</head>
<body class="lp-body">

  <!-- ============================================
       NAVBAR
       ============================================ -->
  <header class="lp-nav">
    <div class="lp-nav-inner">
      <a href="landing.php" class="lp-logo">
        <?= pkl_logo(32) ?>
        PKL <span>Journey</span>
      </a>
      <nav aria-label="Navigasi utama">
        <ul class="lp-nav-links">
          <li><a href="#tentang">Tentang</a></li>
          <li><a href="#fitur">Fitur</a></li>
          <li><a href="#cara-kerja">Cara Kerja</a></li>
          <li><a href="#akses">Akses</a></li>
        </ul>
      </nav>
      <a href="index.php?login=1" class="lp-nav-cta desktop-only" aria-label="Masuk ke aplikasi">
        <?= pkl_icon('logout', '', 15) ?>
        Login
      </a>
      <button class="lp-hamburger" id="lp-hamburger" aria-label="Buka menu" aria-expanded="false" aria-controls="lp-mobile-menu">
        <span></span><span></span><span></span>
      </button>
    </div>
    <div class="lp-mobile-menu" id="lp-mobile-menu" role="navigation" aria-label="Menu mobile">
      <a href="#tentang">Tentang</a>
      <a href="#fitur">Fitur</a>
      <a href="#cara-kerja">Cara Kerja</a>
      <a href="#akses">Akses Pengguna</a>
      <a href="index.php?login=1" style="color:var(--color-accent);font-weight:700;">Login ke Aplikasi</a>
    </div>
  </header>

  <main>

    <!-- ============================================
         HERO
         ============================================ -->
    <section class="lp-hero" id="beranda" aria-labelledby="hero-title">
      <div class="lp-container">
        <div class="lp-hero-eyebrow">Platform PKL Digital</div>
        <h1 class="lp-hero-title" id="hero-title">
          Perjalanan PKL<br><span>Lebih Terarah</span>
        </h1>
        <p class="lp-hero-desc">
          PKL Journey menghubungkan sekolah, siswa, guru pembimbing, dan dunia industri
          dalam satu sistem pengelolaan Praktik Kerja Lapangan yang terstruktur dan mudah dipantau.
        </p>
        <div class="lp-hero-actions">
          <a href="index.php?login=1" class="lp-btn-hero primary">
            <?= pkl_icon('logout', '', 18) ?>
            Masuk Sekarang
          </a>
          <a href="#tentang" class="lp-btn-hero outline">
            Pelajari Lebih Lanjut
          </a>
        </div>
      </div>
    </section>

    <!-- ============================================
         TENTANG
         ============================================ -->
    <section class="lp-section" id="tentang" aria-labelledby="tentang-title">
      <div class="lp-container">
        <div class="lp-about-grid">
          <div>
            <div class="lp-section-label">Tentang PKL Journey</div>
            <h2 class="lp-section-title" id="tentang-title">
              Satu Platform untuk<br><span>Semua Pihak PKL</span>
            </h2>
            <p class="lp-section-desc" style="margin-bottom:1rem;">
              PKL Journey dirancang untuk memudahkan pengelolaan Praktik Kerja Lapangan
              di SMK. Proses yang sebelumnya manual dan tersebar kini dapat dilakukan
              dari satu tempat oleh semua pihak yang terlibat.
            </p>
            <p class="lp-section-desc">
              Dari pendataan siswa, penugasan pembimbing, pengisian jurnal harian,
              hingga penilaian akhir &mdash; semua tercatat secara digital, real-time, dan
              mudah diaudit oleh pihak sekolah.
            </p>
          </div>
          <div class="lp-about-visual" aria-hidden="true">
            <div class="lp-about-stat">
              <div class="lp-about-stat-num">5</div>
              <div class="lp-about-stat-label">Jenis pengguna: Murid, Guru Sekolah, Guru Industri, Penguji, Admin</div>
            </div>
            <div class="lp-about-stat">
              <div class="lp-about-stat-num">1 Platform</div>
              <div class="lp-about-stat-label">Jurnal, bimbingan, penilaian, dan pelaporan PKL terintegrasi</div>
            </div>
            <div class="lp-about-stat">
              <div class="lp-about-stat-num">Terstruktur</div>
              <div class="lp-about-stat-label">Data siswa, penugasan, dan penilaian tersimpan rapi dan aman</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================================
         FITUR
         ============================================ -->
    <section class="lp-section lp-section-alt" id="fitur" aria-labelledby="fitur-title">
      <div class="lp-container">
        <div style="text-align:center;">
          <div class="lp-section-label">Fitur Utama</div>
          <h2 class="lp-section-title" id="fitur-title" style="max-width:480px;margin:0 auto;">
            Semua yang Dibutuhkan<br><span>dalam Satu Sistem</span>
          </h2>
        </div>
        <div class="lp-features-grid">
          <article class="lp-feature-card">
            <div class="lp-feature-icon" aria-hidden="true">
              <?= pkl_icon('jurnal', '', 22) ?>
            </div>
            <div class="lp-feature-title">Jurnal PKL Digital</div>
            <p class="lp-feature-desc">
              Siswa mencatat kegiatan harian secara digital. Guru pembimbing dapat memantau
              dan memberikan status persetujuan atau revisi pada setiap entri jurnal.
            </p>
          </article>
          <article class="lp-feature-card">
            <div class="lp-feature-icon" aria-hidden="true">
              <?= pkl_icon('kehadiran', '', 22) ?>
            </div>
            <div class="lp-feature-title">Pencatatan Absensi</div>
            <p class="lp-feature-desc">
              Rekam kehadiran siswa selama masa PKL. Data absensi tercatat langsung
              di sistem dan dapat dilihat oleh guru pembimbing maupun admin sekolah.
            </p>
          </article>
          <article class="lp-feature-card">
            <div class="lp-feature-icon" aria-hidden="true">
              <?= pkl_icon('pembimbing_sekolah', '', 22) ?>
            </div>
            <div class="lp-feature-title">Manajemen Bimbingan</div>
            <p class="lp-feature-desc">
              Admin menugaskan guru pembimbing sekolah dan industri ke masing-masing siswa.
              Pembimbing dapat memantau perkembangan dan memberikan catatan bimbingan.
            </p>
          </article>
          <article class="lp-feature-card">
            <div class="lp-feature-icon" aria-hidden="true">
              <?= pkl_icon('penilaian', '', 22) ?>
            </div>
            <div class="lp-feature-title">Penilaian Komprehensif</div>
            <p class="lp-feature-desc">
              Penilaian PKL dilakukan oleh guru pembimbing sekolah, pembimbing industri,
              dan dua jenis penguji (Produktif dan Jurnal) dengan kriteria yang terpisah.
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- ============================================
         CARA KERJA
         ============================================ -->
    <section class="lp-section" id="cara-kerja" aria-labelledby="carakerja-title">
      <div class="lp-container">
        <div style="text-align:center;">
          <div class="lp-section-label">Cara Kerja</div>
          <h2 class="lp-section-title" id="carakerja-title" style="max-width:480px;margin:0 auto;">
            Alur PKL Journey<br><span>dari Awal hingga Selesai</span>
          </h2>
        </div>
        <div class="lp-steps" role="list">
          <div class="lp-step" role="listitem">
            <div class="lp-step-num" aria-hidden="true">1</div>
            <div class="lp-step-title">Admin Menyiapkan Data</div>
            <p class="lp-step-desc">
              Admin mengimpor data siswa, mendaftarkan perusahaan mitra, dan menugaskan
              pembimbing serta penguji ke masing-masing siswa.
            </p>
          </div>
          <div class="lp-step" role="listitem">
            <div class="lp-step-num" aria-hidden="true">2</div>
            <div class="lp-step-title">Siswa Mengisi Jurnal</div>
            <p class="lp-step-desc">
              Setiap hari, siswa mencatat kegiatan PKL melalui dashboard pribadi.
              Laporan akhir PKL juga dikirimkan melalui sistem.
            </p>
          </div>
          <div class="lp-step" role="listitem">
            <div class="lp-step-num" aria-hidden="true">3</div>
            <div class="lp-step-title">Guru Membimbing</div>
            <p class="lp-step-desc">
              Guru pembimbing sekolah dan industri memantau jurnal, memberikan
              catatan, dan mengisi penilaian bimbingan siswa.
            </p>
          </div>
          <div class="lp-step" role="listitem">
            <div class="lp-step-num" aria-hidden="true">4</div>
            <div class="lp-step-title">Penguji Memberikan Nilai</div>
            <p class="lp-step-desc">
              Penguji Produktif menilai kompetensi teknis. Penguji Jurnal menilai
              kualitas dan pemahaman dokumentasi PKL siswa.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================================
         AKSES PENGGUNA
         ============================================ -->
    <section class="lp-section lp-section-alt" id="akses" aria-labelledby="akses-title">
      <div class="lp-container">
        <div style="text-align:center;">
          <div class="lp-section-label">Akses Pengguna</div>
          <h2 class="lp-section-title" id="akses-title" style="max-width:480px;margin:0 auto;">
            Hak Akses untuk<br><span>Setiap Peran</span>
          </h2>
        </div>
        <div class="lp-roles-grid">
          <article class="lp-role-card">
            <div class="lp-role-icon" aria-hidden="true">
              <?= pkl_icon('siswa', '', 20) ?>
            </div>
            <div class="lp-role-title">Murid / Siswa</div>
            <p class="lp-role-desc">
              Mengisi jurnal harian, melihat status bimbingan dan penugasan,
              serta memantau hasil penilaian dari seluruh pembimbing dan penguji.
            </p>
          </article>
          <article class="lp-role-card">
            <div class="lp-role-icon" aria-hidden="true">
              <?= pkl_icon('pembimbing_sekolah', '', 20) ?>
            </div>
            <div class="lp-role-title">Guru Pembimbing Sekolah</div>
            <p class="lp-role-desc">
              Memantau jurnal dan laporan siswa bimbingan, memberikan persetujuan atau
              revisi, serta mengisi penilaian dari sisi sekolah.
            </p>
          </article>
          <article class="lp-role-card">
            <div class="lp-role-icon" aria-hidden="true">
              <?= pkl_icon('industri', '', 20) ?>
            </div>
            <div class="lp-role-title">Guru Pembimbing Industri</div>
            <p class="lp-role-desc">
              Memantau perkembangan siswa PKL di tempat kerja dan mengisi penilaian
              berdasarkan kedisiplinan, kehadiran, dan kualitas hasil kerja.
            </p>
          </article>
          <article class="lp-role-card">
            <div class="lp-role-icon" aria-hidden="true">
              <?= pkl_icon('penguji', '', 20) ?>
            </div>
            <div class="lp-role-title">Penguji</div>
            <p class="lp-role-desc">
              Melakukan penilaian akhir PKL. Penguji Produktif menilai kompetensi teknis,
              Penguji Jurnal menilai kelengkapan dan pemahaman dokumentasi siswa.
            </p>
          </article>
          <article class="lp-role-card" style="grid-column:span 1;">
            <div class="lp-role-icon" aria-hidden="true" style="background:var(--color-accent);">
              <?= pkl_icon('pengaturan', '', 20) ?>
            </div>
            <div class="lp-role-title">Administrator</div>
            <p class="lp-role-desc">
              Mengelola seluruh akun pengguna, mengimpor data siswa, mendaftarkan
              perusahaan mitra, menugaskan pembimbing, dan memantau status PKL semua siswa.
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- ============================================
         CTA BANNER
         ============================================ -->
    <section class="lp-cta" aria-labelledby="cta-title">
      <div class="lp-container">
        <h2 class="lp-cta-title" id="cta-title">Mulai Gunakan PKL Journey</h2>
        <p class="lp-cta-desc">
          Masuk menggunakan akun yang telah disiapkan oleh Administrator sekolah Anda.
        </p>
        <a href="index.php?login=1" class="lp-btn-hero primary">
          <?= pkl_icon('logout', '', 18) ?>
          Masuk ke Aplikasi
        </a>
      </div>
    </section>

  </main>

  <!-- ============================================
       FOOTER
       ============================================ -->
  <footer class="lp-footer" role="contentinfo">
    <div class="lp-container">
      <div class="lp-footer-brand">PKL Journey</div>
      <div class="lp-footer-divider" aria-hidden="true"></div>
      <div class="lp-footer-sub">
        Platform Praktik Kerja Lapangan Digital
      </div>
      <div class="lp-footer-sub" style="margin-top:0.3rem;">
        SMK Telekomunikasi Telesandi Bekasi
      </div>
    </div>
  </footer>

  <script>
    // Hamburger menu toggle
    (function () {
      var btn = document.getElementById('lp-hamburger');
      var menu = document.getElementById('lp-mobile-menu');
      if (!btn || !menu) return;
      btn.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
      // Close when any link inside is clicked
      menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          menu.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
        });
      });
    }());

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href').slice(1);
        var el = document.getElementById(id);
        if (!el) return;
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  </script>

</body>
</html>
