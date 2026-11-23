<?php
// index.php
session_start();

require_once __DIR__ . '/includes/icons.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $redirects = [
        'murid'         => 'dashboard/murid.php',
        'guru'          => 'dashboard/guru.php',
        'admin'         => 'dashboard/admin.php',
        'guru_industri' => 'dashboard/industri.php',
        'penguji'       => 'dashboard/penguji.php'
    ];
    $target = $redirects[$_SESSION['role']] ?? 'dashboard/murid.php';
    header("Location: $target");
    exit;
}

// Unauthenticated visitors without ?login=1 see the landing page.
if (empty($_GET['login'])) {
    header('Location: landing.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PKL Journey - Platform Praktik Kerja Lapangan</title>
  

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- CSS -->
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <div class="app-container">
    
    
    <header class="journey-header">
      <div class="brand-badge"><?= pkl_icon('journey', '', 16) ?> Internship Ecosystem</div>
      <h1 class="brand-title">
        <span class="brand-logo-icon"><?= pkl_logo(42) ?></span>
        <span>PKL <span>Journey</span></span>
      </h1>
      <p class="brand-subtitle">Menghubungkan Sekolah, Siswa, dan Industri dalam satu peta perjalanan</p>
    </header>


    <div class="journey-map-container">
      
      
      <svg class="journey-svg-line" viewBox="0 0 500 40" preserveAspectRatio="none">
        <path class="curve-path-bg" d="M 10 20 Q 250 35 490 20" />
        <path class="curve-path-active" d="M 10 20 Q 250 35 490 20" />
      </svg>

      <div class="journey-map-track">
        
        <!-- Node 1: Sekolah -->
        <div class="map-node">
          <div class="node-icon-wrapper" title="Sekolah asal siswa">
            <?= pkl_icon('pembimbing_sekolah', '', 26) ?>
          </div>
          <span class="node-label">Sekolah</span>
          <span class="node-sub">Titik Awal</span>
        </div>

        <!-- Node 2: Siswa (Active Trajectory Point) -->
        <div class="map-node">
          <div class="node-icon-wrapper student" title="Siswa sedang berproses">
            <?= pkl_icon('siswa', '', 26) ?>
          </div>
          <span class="node-label">Siswa</span>
          <span class="node-sub">Sedang Berjalan</span>
        </div>

        <!-- Node 3: Industri -->
        <div class="map-node">
          <div class="node-icon-wrapper" title="Industri tempat PKL">
            <?= pkl_icon('pembimbing_industri', '', 26) ?>
          </div>
          <span class="node-label">Industri</span>
          <span class="node-sub">Tujuan PKL</span>
        </div>

      </div>
    </div>


    <div class="auth-wrapper">
      <div class="auth-card">
        
        <!-- Status Overlay for animated transitions -->
        <div id="journey-loading-overlay" class="journey-loading-overlay">
          <div class="spinner-journey"></div>
          <div id="status-step-text" class="status-step-text">Checking account...</div>
          <div id="status-step-sub" class="status-step-sub">Memproses...</div>
        </div>

        <!-- Container for Form Views -->
        <div class="form-views-container">
          
          <!-- ========================================== -->
          <!-- LOGIN VIEW                                 -->
          <!-- ========================================== -->
          <div class="form-view active" id="view-login" style="display:block;">
            <div class="form-header">
              <h2 class="form-title">Masuk ke PKL Journey</h2>
              <p class="form-desc">Silakan masukkan NIS (Murid) atau Username untuk melanjutkan</p>
            </div>

            <!-- Login Status Box (Error / Success alert) -->
            <div id="login-status" class="status-box"></div>

            <form id="form-login" autocomplete="off">
              
              <!-- Username / NIS -->
              <div class="form-group">
                <label for="login-username" class="form-label">NIS / Username</label>
                <div class="input-wrapper">
                  <input type="text" id="login-username" name="username" class="form-control" placeholder="Masukkan NIS atau Username" required autofocus>
                </div>
              </div>

              <!-- Password + Show/Hide -->
              <div class="form-group">
                <label for="login-password" class="form-label">Password</label>
                <div class="input-wrapper">
                  <input type="password" id="login-password" name="password" class="form-control" placeholder="Masukkan password" required>
                  <button type="button" class="password-toggle-btn" data-target="login-password" title="Lihat/Sembunyikan Password">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Submit Button -->
              <button type="submit" class="btn-primary">
                <span>LOGIN</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </button>

              <div style="text-align:center;font-size:0.82rem;color:var(--color-muted);margin-top:1.25rem;">
                Seluruh akun dikelola oleh Administrator. Hubungi pihak sekolah jika belum memiliki akun.
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

  </div>

  <!-- JavaScript -->
  <script src="js/script.js"></script>
</body>
</html>
