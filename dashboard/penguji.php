<?php
// dashboard/penguji.php
// Panel Dashboard Penguji Sidang PKL Journey

require_once __DIR__ . '/../auth/check_penguji.php';
require_once __DIR__ . '/../includes/icons.php';

$alertMessage = '';
$alertType = '';
$activeTab = 'section-dashboard';

if (!empty($_GET['tab'])) {
    $getTab = trim($_GET['tab']);
    if (!str_starts_with($getTab, 'section-')) {
        $getTab = 'section-' . $getTab;
    }
    if (in_array($getTab, ['section-dashboard', 'section-siswa', 'section-jurnal', 'section-penilaian', 'section-examiner'])) {
        $activeTab = $getTab;
    }
}

// ------------------------------------------
// 1. HANDLER AKSI: SIMPAN NILAI SIDANG PKL
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sidang_assessment') {
    if (!empty($_POST['current_tab'])) {
        $activeTab = trim($_POST['current_tab']);
    }

    $student_id         = (int)($_POST['student_id'] ?? 0);
    $score_understanding = max(0, min(100, (int)($_POST['score_understanding'] ?? 0)));
    $score_presentation  = max(0, min(100, (int)($_POST['score_presentation'] ?? 0)));
    $score_qa            = max(0, min(100, (int)($_POST['score_qa'] ?? 0)));
    $feedback           = trim($_POST['feedback'] ?? '');
    $final_score        = round(($score_understanding + $score_presentation + $score_qa) / 3, 2);

    try {
        // Keamanan Kewenangan Penguji: Pastikan siswa benar-benar ditugaskan untuk diuji oleh Penguji ini
        $stmtCheck = $pdo->prepare("
            SELECT u.id, u.username 
            FROM users u
            JOIN internships i ON u.id = i.student_id
            WHERE u.id = :sid AND i.penguji_id = :pid AND u.role = 'murid'
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':sid' => $student_id,
            ':pid' => $penguji_id
        ]);
        $studentUser = $stmtCheck->fetch();

        if ($studentUser) {
            $stmtSave = $pdo->prepare("
                INSERT INTO sidang_assessments (student_id, penguji_id, score_understanding, score_presentation, score_qa, final_score, feedback, assessed_at)
                VALUES (:sid, :pid, :s_und, :s_pres, :s_qa, :final_score, :feedback, NOW())
                ON DUPLICATE KEY UPDATE
                    penguji_id          = VALUES(penguji_id),
                    score_understanding = VALUES(score_understanding),
                    score_presentation  = VALUES(score_presentation),
                    score_qa            = VALUES(score_qa),
                    final_score         = VALUES(final_score),
                    feedback            = VALUES(feedback),
                    assessed_at         = NOW()
            ");
            $stmtSave->execute([
                ':sid'         => $student_id,
                ':pid'         => $penguji_id,
                ':s_und'       => $score_understanding,
                ':s_pres'      => $score_presentation,
                ':s_qa'        => $score_qa,
                ':final_score' => $final_score,
                ':feedback'    => $feedback
            ]);
            $alertMessage = 'Penilaian sidang untuk peserta <strong>' . htmlspecialchars($studentUser['username']) . '</strong> berhasil disimpan! (Nilai Akhir Sidang: ' . number_format($final_score, 1) . ') ✓';
            $alertType = 'success';
        } else {
            $alertMessage = 'Akses ditolak: Anda tidak memiliki kewenangan menguji siswa ini.';
            $alertType = 'error';
        }
    } catch (Exception $e) {
        $alertMessage = 'Gagal menyimpan penilaian sidang: ' . $e->getMessage();
        $alertType = 'error';
    }
}

// ------------------------------------------
// 1B. HANDLER: SIMPAN NILAI PENGUJI PRODUKTIF (5 kriteria, tanpa nilai gabungan)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_produktif_assessment') {
    if (!empty($_POST['current_tab'])) { $activeTab = trim($_POST['current_tab']); } else { $activeTab = 'section-examiner'; }
    $student_id    = (int)($_POST['student_id'] ?? 0);
    $score_materi  = max(0, min(100, (int)($_POST['score_materi'] ?? 0)));
    $score_teknis  = max(0, min(100, (int)($_POST['score_teknis'] ?? 0)));
    $score_hasil   = max(0, min(100, (int)($_POST['score_hasil'] ?? 0)));
    $score_masalah = max(0, min(100, (int)($_POST['score_masalah'] ?? 0)));
    $score_proses  = max(0, min(100, (int)($_POST['score_proses'] ?? 0)));
    $catatan       = trim($_POST['catatan'] ?? '');
    try {
        // Kewenangan: siswa harus ditugaskan ke penguji ini sebagai kategori 'produktif'
        $stmtCheck = $pdo->prepare("SELECT u.id, u.username FROM users u JOIN examiner_assignments ea ON ea.student_id = u.id WHERE ea.student_id = :sid AND ea.penguji_id = :pid AND ea.category = 'produktif' AND u.role = 'murid' LIMIT 1");
        $stmtCheck->execute([':sid' => $student_id, ':pid' => $penguji_id]);
        $studentUser = $stmtCheck->fetch();
        if ($studentUser) {
            $stmtSave = $pdo->prepare("INSERT INTO produktif_assessments (student_id, penguji_id, score_materi, score_teknis, score_hasil, score_masalah, score_proses, catatan, assessed_at) VALUES (:sid, :pid, :a, :b, :c, :d, :e, :catatan, NOW()) ON DUPLICATE KEY UPDATE score_materi=VALUES(score_materi), score_teknis=VALUES(score_teknis), score_hasil=VALUES(score_hasil), score_masalah=VALUES(score_masalah), score_proses=VALUES(score_proses), catatan=VALUES(catatan), assessed_at=NOW()");
            $stmtSave->execute([':sid'=>$student_id, ':pid'=>$penguji_id, ':a'=>$score_materi, ':b'=>$score_teknis, ':c'=>$score_hasil, ':d'=>$score_masalah, ':e'=>$score_proses, ':catatan'=>$catatan]);
            $alertMessage = 'Penilaian Penguji Produktif untuk <strong>' . htmlspecialchars($studentUser['username']) . '</strong> berhasil disimpan. &#10003;';
            $alertType = 'success';
        } else {
            $alertMessage = 'Akses ditolak: Anda tidak ditugaskan sebagai Penguji Produktif untuk siswa ini.';
            $alertType = 'error';
        }
    } catch (Exception $e) {
        $alertMessage = 'Gagal menyimpan penilaian Produktif: ' . $e->getMessage();
        $alertType = 'error';
    }
}

// ------------------------------------------
// 1C. HANDLER: SIMPAN NILAI PENGUJI JURNAL (5 kriteria, tanpa nilai gabungan)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_jurnal_assessment') {
    if (!empty($_POST['current_tab'])) { $activeTab = trim($_POST['current_tab']); } else { $activeTab = 'section-examiner'; }
    $student_id        = (int)($_POST['student_id'] ?? 0);
    $score_kelengkapan = max(0, min(100, (int)($_POST['score_kelengkapan'] ?? 0)));
    $score_sesuai       = max(0, min(100, (int)($_POST['score_sesuai'] ?? 0)));
    $score_pemahaman    = max(0, min(100, (int)($_POST['score_pemahaman'] ?? 0)));
    $score_jelaskan     = max(0, min(100, (int)($_POST['score_jelaskan'] ?? 0)));
    $score_komunikasi   = max(0, min(100, (int)($_POST['score_komunikasi'] ?? 0)));
    $catatan            = trim($_POST['catatan'] ?? '');
    try {
        $stmtCheck = $pdo->prepare("SELECT u.id, u.username FROM users u JOIN examiner_assignments ea ON ea.student_id = u.id WHERE ea.student_id = :sid AND ea.penguji_id = :pid AND ea.category = 'jurnal' AND u.role = 'murid' LIMIT 1");
        $stmtCheck->execute([':sid' => $student_id, ':pid' => $penguji_id]);
        $studentUser = $stmtCheck->fetch();
        if ($studentUser) {
            $stmtSave = $pdo->prepare("INSERT INTO jurnal_assessments (student_id, penguji_id, score_kelengkapan, score_sesuai, score_pemahaman, score_jelaskan, score_komunikasi, catatan, assessed_at) VALUES (:sid, :pid, :a, :b, :c, :d, :e, :catatan, NOW()) ON DUPLICATE KEY UPDATE score_kelengkapan=VALUES(score_kelengkapan), score_sesuai=VALUES(score_sesuai), score_pemahaman=VALUES(score_pemahaman), score_jelaskan=VALUES(score_jelaskan), score_komunikasi=VALUES(score_komunikasi), catatan=VALUES(catatan), assessed_at=NOW()");
            $stmtSave->execute([':sid'=>$student_id, ':pid'=>$penguji_id, ':a'=>$score_kelengkapan, ':b'=>$score_sesuai, ':c'=>$score_pemahaman, ':d'=>$score_jelaskan, ':e'=>$score_komunikasi, ':catatan'=>$catatan]);
            $alertMessage = 'Penilaian Penguji Jurnal untuk <strong>' . htmlspecialchars($studentUser['username']) . '</strong> berhasil disimpan. &#10003;';
            $alertType = 'success';
        } else {
            $alertMessage = 'Akses ditolak: Anda tidak ditugaskan sebagai Penguji Jurnal untuk siswa ini.';
            $alertType = 'error';
        }
    } catch (Exception $e) {
        $alertMessage = 'Gagal menyimpan penilaian Jurnal: ' . $e->getMessage();
        $alertType = 'error';
    }
}

// ------------------------------------------
// 2. QUERY PESERTA SIDANG, JURNAL & NILAI
// ------------------------------------------

// Query Siswa yang ditugaskan untuk diuji oleh Penguji ini
$stmtStudents = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at,
           c.name AS company_name,
           g.username AS guru_sekolah_name,
           r.id AS report_id, r.title AS report_title, r.summary AS report_summary, r.status AS report_status,
           COUNT(DISTINCT j.id) AS total_journals,
           sa.id AS assessment_id, sa.score_understanding, sa.score_presentation, sa.score_qa, sa.final_score, sa.feedback, sa.assessed_at
    FROM users u
    JOIN internships i ON u.id = i.student_id AND i.penguji_id = :pid
    LEFT JOIN companies c ON i.company_id = c.id
    LEFT JOIN users g ON i.guru_id = g.id
    LEFT JOIN pkl_reports r ON u.id = r.student_id
    LEFT JOIN pkl_journals j ON u.id = j.student_id
    LEFT JOIN sidang_assessments sa ON u.id = sa.student_id AND sa.penguji_id = i.penguji_id
    WHERE u.role = 'murid'
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmtStudents->execute([':pid' => $penguji_id]);
$allStudents = $stmtStudents->fetchAll();

// Query Seluruh Jurnal PKL Siswa yang Diuji
$stmtJournals = $pdo->prepare("
    SELECT j.*, u.username AS student_name, u.email AS student_email, c.name AS company_name
    FROM pkl_journals j
    JOIN users u ON j.student_id = u.id
    JOIN internships i ON u.id = i.student_id AND i.penguji_id = :pid
    LEFT JOIN companies c ON i.company_id = c.id
    ORDER BY j.activity_date DESC, j.id DESC
");
$stmtJournals->execute([':pid' => $penguji_id]);
$allJournals = $stmtJournals->fetchAll();

// Query Siswa yang ditugaskan ke Penguji ini (kategori Produktif & Jurnal).
// Pakai placeholder posisional (?) karena :pid dipakai 3x (Hindari HY093).
$stmtExaminees = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.nama, u.kelas, u.jurusan,
           GROUP_CONCAT(DISTINCT ea.category) AS categories,
           (SELECT COUNT(*) FROM produktif_assessments pa WHERE pa.student_id = u.id AND pa.penguji_id = ?) AS produktif_done,
           (SELECT COUNT(*) FROM jurnal_assessments ja WHERE ja.student_id = u.id AND ja.penguji_id = ?) AS jurnal_done
    FROM examiner_assignments ea
    JOIN users u ON u.id = ea.student_id
    WHERE ea.penguji_id = ? AND u.role = 'murid'
    GROUP BY u.id
    ORDER BY u.username ASC
");
$stmtExaminees->execute([$penguji_id, $penguji_id, $penguji_id]);
$examinees = $stmtExaminees->fetchAll();

// Prefill map untuk modal (penilaian yang sudah ada oleh penguji ini)
$stmtProduktifMine = $pdo->prepare("SELECT * FROM produktif_assessments WHERE penguji_id = :pid");
$stmtProduktifMine->execute([':pid' => $penguji_id]);
$produktifMap = [];
foreach ($stmtProduktifMine->fetchAll() as $row) { $produktifMap[$row['student_id']] = $row; }

$stmtJurnalMine = $pdo->prepare("SELECT * FROM jurnal_assessments WHERE penguji_id = :pid");
$stmtJurnalMine->execute([':pid' => $penguji_id]);
$jurnalAssessMap = [];
foreach ($stmtJurnalMine->fetchAll() as $row) { $jurnalAssessMap[$row['student_id']] = $row; }

// Statistik Penguji
$totalStudents = count($allStudents);
$totalJournals = count($allJournals);
$testedStudents = 0;
$pendingStudents = 0;

foreach ($allStudents as $st) {
    if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0) {
        $testedStudents++;
    } else {
        $pendingStudents++;
    }
}

function penguji_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function penguji_excerpt($text, $max = 90) {
    $text = (string)$text;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '...');
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max) . '...';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Penguji Sidang PKL - PKL Journey</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .btn-action-group {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      flex-wrap: wrap;
    }
    .btn-action-sm {
      padding: 0.38rem 0.75rem;
      font-size: 0.78rem;
      font-weight: 700;
      border-radius: var(--radius-sm);
      border: 1px solid var(--color-border);
      background: var(--color-white);
      color: var(--color-text);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      transition: var(--transition);
      text-decoration: none;
      font-family: inherit;
    }
    .btn-action-sm:hover {
      background: var(--color-slate-100);
      color: var(--color-primary);
      border-color: var(--color-border);
    }
    .btn-action-sm.primary {
      background: var(--color-orange);
      color: var(--color-white);
      border-color: var(--color-dark-orange);
    }
    .btn-action-sm.primary:hover {
      background: var(--color-dark-orange);
    }
    .table-container-responsive {
      width: 100%;
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      background: var(--color-white);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
    }
    .table-container-responsive table {
      min-width: 640px;
    }
    .student-score-badge {
      padding: 0.35rem 0.85rem;
      background: #E8F5E9;
      color: var(--color-success);
      font-weight: 800;
      font-size: 0.88rem;
      border-radius: var(--radius-full);
      border: 1px solid #A5D6A7;
    }
  </style>
</head>
<body class="dashboard-body">

  <div class="dashboard-layout">
    <!-- Sidebar Penguji Sidang -->
    <aside class="app-sidebar" id="app-sidebar">
      <div class="sidebar-header">
        <a href="penguji.php" class="sidebar-brand">
          <span class="brand-logo-icon"><?= pkl_logo(34) ?></span> PKL <span class="brand-text-accent">Journey</span>
        </a>
        <div class="sidebar-user-card">
          <div class="user-avatar-circle" style="background: linear-gradient(135deg, var(--color-primary), var(--color-navy));">
            <?= strtoupper(substr($penguji_username, 0, 1)) ?>
          </div>
          <div class="user-meta">
            <div class="user-meta-name" title="<?= penguji_h($penguji_username) ?>"><?= penguji_h($penguji_username) ?></div>
            <div class="user-meta-school">Penguji Sidang PKL</div>
          </div>
        </div>
      </div>

      <!-- Menu Sidebar Khusus Penguji -->
      <nav class="sidebar-menu">
        <button type="button" class="nav-link-item <?= $activeTab === 'section-dashboard' ? 'active' : '' ?>" data-target="section-dashboard" id="nav-dashboard">
          <span class="nav-icon"><?= pkl_icon('dashboard') ?></span>
          <span>Dashboard</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-siswa' ? 'active' : '' ?>" data-target="section-siswa" id="nav-siswa">
          <span class="nav-icon"><?= pkl_icon('sidang') ?></span>
          <span>Peserta Sidang</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-jurnal' ? 'active' : '' ?>" data-target="section-jurnal" id="nav-jurnal">
          <span class="nav-icon"><?= pkl_icon('jurnal') ?></span>
          <span>Telaah Jurnal &amp; Laporan</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-penilaian' ? 'active' : '' ?>" data-target="section-penilaian" id="nav-penilaian">
          <span class="nav-icon"><?= pkl_icon('penilaian') ?></span>
          <span>Penilaian Sidang</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-examiner' ? 'active' : '' ?>" data-target="section-examiner" id="nav-examiner">
          <span class="nav-icon"><?= pkl_icon('penguji') ?></span>
          <span>Penilaian Penguji (Produktif &amp; Jurnal)</span>
        </button>
      </nav>

      <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link-logout">
          <span class="nav-icon"><?= pkl_icon('logout') ?></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Konten Utama Dashboard Penguji -->
    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="topbar-title-wrap">
          <h1 class="topbar-greeting">Halo, <span><?= penguji_h($penguji_username) ?></span>!</h1>
          <p class="topbar-sub">Dewan Penguji Sidang PKL • <?= penguji_h($penguji_school_name) ?></p>
        </div>
        <div class="topbar-actions">
          <button type="button" class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <button type="button" class="btn-primary" onclick="openAssessmentModal()" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;display:inline-flex;align-items:center;gap:6px;">
            <span><?= pkl_icon('penilaian', '', 18) ?></span> Input Nilai Sidang
          </button>
        </div>
      </header>

      <?php if ($alertMessage !== ''): ?>
        <div class="status-box <?= $alertType === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 1.5rem;">
          <?= $alertMessage ?>
        </div>
      <?php endif; ?>

      <!-- 1. TAB: DASHBOARD -->
      <div class="content-tab-view <?= $activeTab === 'section-dashboard' ? 'active' : '' ?>" id="section-dashboard" <?= $activeTab === 'section-dashboard' ? '' : 'hidden' ?>>
        <!-- 4 Stat Cards -->
        <div class="stats-row">
          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Peserta Sidang</div>
              <div class="stat-value"><?= (int)$totalStudents ?> Siswa</div>
              <div class="stat-sub">Ditugaskan untuk Anda uji</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('sidang', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Sidang Dinilai</div>
              <div class="stat-value" style="color:var(--color-success);"><?= (int)$testedStudents ?> Siswa</div>
              <div class="stat-sub"><?= $totalStudents > 0 ? round(($testedStudents / $totalStudents) * 100) : 0 ?>% selesai diuji</div>
            </div>
            <div class="stat-icon-badge" style="background:#E8F5E9;color:var(--color-success);"><?= pkl_icon('check', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Menunggu Sidang</div>
              <div class="stat-value" style="color:var(--color-accent);"><?= (int)$pendingStudents ?> Siswa</div>
              <div class="stat-sub">Belum diberi nilai sidang</div>
            </div>
            <div class="stat-icon-badge" style="background:var(--color-slate-100);color:var(--color-accent);"><?= pkl_icon('clock', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Jurnal Diuji</div>
              <div class="stat-value"><?= (int)$totalJournals ?> Entri</div>
              <div class="stat-sub">Bahan uji &amp; verifikasi karya</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('jurnal', '', 24) ?></div>
          </div>
        </div>

        <!-- Daftar Peserta Sidang Preview -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('sidang', '', 20) ?></span> Peserta Sidang PKL Ditugaskan
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
            <button type="button" class="btn-action-sm primary" onclick="switchTab('section-siswa')">
              Lihat Seluruh Peserta →
            </button>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('penguji', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa yang Ditugaskan untuk Diuji</div>
              <p class="empty-state-desc">Administrator belum menetapkan peserta sidang PKL ke akun penguji Anda.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Peserta Sidang</th>
                    <th>Lokasi PKL</th>
                    <th>Laporan PKL</th>
                    <th>Jurnal</th>
                    <th>Nilai Sidang</th>
                    <th>Aksi Uji</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allStudents as $st): ?>
                    <tr>
                      <td>
                        <div style="font-weight:700;color:var(--color-text);"><?= penguji_h($st['username']) ?></div>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= penguji_h($st['email']) ?></div>
                      </td>
                      <td>
                        <span style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= penguji_h($st['company_name'] ?? 'Mitra Industri') ?></span>
                      </td>
                      <td>
                        <?php if (!empty($st['report_title'])): ?>
                          <div style="font-weight:600;font-size:0.85rem;color:var(--color-text);"><?= penguji_h(penguji_excerpt($st['report_title'], 45)) ?></div>
                          <span class="status-pill <?= penguji_h($st['report_status']) ?>" style="display:inline-flex;align-items:center;gap:3px;"><?= $st['report_status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Dikirim' ?></span>
                        <?php else: ?>
                          <span style="font-size:0.8rem;color:var(--color-muted);font-style:italic;">Belum submit</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <strong><?= (int)$st['total_journals'] ?></strong> entri
                      </td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Belum Sidang</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= penguji_h(addslashes($st['username'])) ?>', <?= (int)($st['score_understanding'] ?? 0) ?>, <?= (int)($st['score_presentation'] ?? 0) ?>, <?= (int)($st['score_qa'] ?? 0) ?>, '<?= penguji_h(addslashes($st['feedback'] ?? '')) ?>')">
                          <span><?= pkl_icon('penilaian', '', 14) ?></span> Nilai Sidang
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. TAB: PESERTA SIDANG -->
      <div class="content-tab-view <?= $activeTab === 'section-siswa' ? 'active' : '' ?>" id="section-siswa" <?= $activeTab === 'section-siswa' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('sidang', '', 20) ?></span> Daftar Seluruh Peserta Sidang PKL
              <span class="sec-badge"><?= (int)$totalStudents ?> Peserta</span>
            </div>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Peserta Sidang</div>
              <p class="empty-state-desc">Belum ada siswa yang dialokasikan ke jadwal ujian Anda.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Siswa</th>
                    <th>Email</th>
                    <th>Tempat PKL</th>
                    <th>Pembimbing Sekolah</th>
                    <th>Status Nilai Sidang</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $st): ?>
                    <tr>
                      <td style="font-weight:700;width:40px;"><?= $no++ ?></td>
                      <td><strong><?= penguji_h($st['username']) ?></strong></td>
                      <td><?= penguji_h($st['email']) ?></td>
                      <td><span style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= penguji_h($st['company_name'] ?? 'Mitra Industri') ?></span></td>
                      <td><span style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('pembimbing_sekolah', '', 14) ?> <?= penguji_h($st['guru_sekolah_name'] ?? '-') ?></span></td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Belum Diuji</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= penguji_h(addslashes($st['username'])) ?>', <?= (int)($st['score_understanding'] ?? 0) ?>, <?= (int)($st['score_presentation'] ?? 0) ?>, <?= (int)($st['score_qa'] ?? 0) ?>, '<?= penguji_h(addslashes($st['feedback'] ?? '')) ?>')">
                          <span><?= pkl_icon('penilaian', '', 14) ?></span> Beri Nilai
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 3. TAB: TELAAH JURNAL & LAPORAN -->
      <div class="content-tab-view <?= $activeTab === 'section-jurnal' ? 'active' : '' ?>" id="section-jurnal" <?= $activeTab === 'section-jurnal' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('laporan', '', 20) ?></span> Telaah Jurnal Kegiatan &amp; Laporan PKL Peserta Sidang
              <span class="sec-badge"><?= (int)$totalJournals ?> Total Entri</span>
            </div>
          </div>

          <p class="card-desc-muted">Gunakan catatan jurnal dan dokumen laporan berikut untuk menguji penguasaan materi dan keaslian pekerjaan siswa saat sidang:</p>

          <!-- Rincian Laporan Akhir Siswa -->
          <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--color-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
              <span><?= pkl_icon('laporan', '', 18) ?></span> Dokumen Laporan PKL Siswa yang Diuji
            </h3>
            <?php 
              $hasReport = false;
              foreach ($allStudents as $st): 
                if (!empty($st['report_title'])):
                  $hasReport = true;
            ?>
              <div class="info-box" style="margin-bottom: 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap;">
                  <div>
                    <div class="info-box-label">Peserta: <?= penguji_h($st['username']) ?> (<?= penguji_h($st['email']) ?>)</div>
                    <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--color-text); margin-top:0.2rem;"><?= penguji_h($st['report_title']) ?></h4>
                    <p style="font-size:0.92rem; color:var(--color-text); line-height:1.6; margin-top:0.5rem; background:var(--color-cream); padding:0.85rem; border-radius:var(--radius-sm); border:1px solid var(--color-border);">
                      <?= nl2br(penguji_h($st['report_summary'])) ?>
                    </p>
                  </div>
                  <span class="status-pill <?= penguji_h($st['report_status']) ?>" style="display:inline-flex;align-items:center;gap:3px;"><?= $st['report_status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui Guru' : 'Dikirim' ?></span>
                </div>
              </div>
            <?php 
                endif;
              endforeach; 
              if (!$hasReport):
            ?>
              <div class="empty-state" style="padding:1.5rem; margin-bottom:1.5rem;">
                <p class="empty-state-desc">Belum ada dokumen laporan PKL yang diunggah oleh peserta sidang.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Tabel Jurnal Kegiatan Siswa -->
          <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--color-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
            <span><?= pkl_icon('jurnal', '', 18) ?></span> Log Harian Jurnal Kegiatan Peserta Sidang
          </h3>
          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Aktivitas Jurnal</div>
              <p class="empty-state-desc">Siswa yang diuji belum memiliki catatan jurnal harian.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Peserta Sidang</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Pembelajaran</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allJournals as $j): ?>
                    <tr>
                      <td style="white-space:nowrap;"><?= date('d M Y', strtotime($j['activity_date'])) ?></td>
                      <td>
                        <strong><?= penguji_h($j['student_name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= penguji_h($j['student_email']) ?></div>
                      </td>
                      <td><strong><?= penguji_h($j['title']) ?></strong></td>
                      <td style="max-width:320px;"><?= nl2br(penguji_h($j['description'])) ?></td>
                      <td>
                        <span class="status-pill <?= penguji_h($j['status']) ?>" style="display:inline-flex;align-items:center;gap:4px;">
                          <?= $j['status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : ($j['status'] === 'revisi' ? pkl_icon('alert', '', 12) . ' Revisi' : pkl_icon('clock', '', 12) . ' Menunggu') ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. TAB: PENILAIAN SIDANG -->
      <div class="content-tab-view <?= $activeTab === 'section-penilaian' ? 'active' : '' ?>" id="section-penilaian" <?= $activeTab === 'section-penilaian' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('penilaian', '', 20) ?></span> Lembar Penilaian Sidang / Ujian Akhir PKL
              <span class="sec-badge"><?= (int)$totalStudents ?> Peserta</span>
            </div>
            <button type="button" class="btn-primary" onclick="openAssessmentModal()" style="width:auto;margin-top:0;padding:0.6rem 1.25rem;font-size:0.88rem;display:inline-flex;align-items:center;gap:6px;">
              <span><?= pkl_icon('plus', '', 16) ?></span> Input Nilai Sidang
            </button>
          </div>

          <p class="card-desc-muted">Penilaian sidang mencakup 3 parameter: Pemahaman terhadap Jurnal PKL, Kemampuan Menjelaskan/Presentasi, dan Penguasaan Tanya Jawab:</p>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('penilaian', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Peserta untuk Dinilai</div>
              <p class="empty-state-desc">Peserta yang ditugaskan kepada Anda akan tampil di daftar ini.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Siswa</th>
                    <th>Pemahaman Jurnal (33%)</th>
                    <th>Presentasi (33%)</th>
                    <th>Tanya Jawab (33%)</th>
                    <th>Nilai Akhir Sidang</th>
                    <th>Catatan &amp; Evaluasi Penguji</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $st): ?>
                    <tr>
                      <td style="font-weight:700;width:40px;"><?= $no++ ?></td>
                      <td>
                        <strong><?= penguji_h($st['username']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= penguji_h($st['email']) ?></div>
                      </td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_understanding'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_presentation'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_qa'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span style="font-size:0.82rem;color:var(--color-muted);">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td style="max-width:240px;font-size:0.85rem;color:var(--color-muted);">
                        <?= !empty($st['feedback']) ? penguji_h(penguji_excerpt($st['feedback'], 70)) : '<em>Tidak ada catatan</em>' ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= penguji_h(addslashes($st['username'])) ?>', <?= (int)($st['score_understanding'] ?? 0) ?>, <?= (int)($st['score_presentation'] ?? 0) ?>, <?= (int)($st['score_qa'] ?? 0) ?>, '<?= penguji_h(addslashes($st['feedback'] ?? '')) ?>')">
                          <span><?= pkl_icon('edit', '', 14) ?></span> <?= !empty($st['assessment_id']) ? 'Edit Nilai' : 'Beri Nilai' ?>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ==========================================
           TAB: PENILAIAN PENGUJI PRODUKTIF & JURNAL
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-examiner' ? 'active' : '' ?>" id="section-examiner" <?= $activeTab === 'section-examiner' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div>
              <div class="section-panel-title">
                <span><?= pkl_icon('penguji', '', 20) ?></span> Penilaian Penguji: Produktif &amp; Jurnal
                <span class="sec-badge">Dua Kategori</span>
              </div>
              <div style="font-size:0.85rem;color:var(--color-muted);margin-top:0.25rem;">
                Siswa yang ditugaskan kepada Anda. Nilai Produktif &amp; Jurnal disimpan terpisah. Tidak ada nilai gabungan otomatis (bobot belum ditentukan).
              </div>
            </div>
          </div>

          <?php if (empty($examinees)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('penguji', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa Ditugaskan</div>
              <div class="empty-state-desc">Administrator belum menugaskan siswa kepada Anda sebagai Penguji Produktif/Jurnal.</div>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Siswa</th>
                    <th>Kelas</th>
                    <th>Kategori Ditugaskan</th>
                    <th>Penilaian Produktif</th>
                    <th>Penilaian Jurnal</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                <?php $no = 1; foreach ($examinees as $ex):
                    $cats = array_flip(array_filter(array_map('trim', explode(',', $ex['categories'] ?? ''))));
                    $isProduktif = isset($cats['produktif']);
                    $isJurnal    = isset($cats['jurnal']);
                    $pDone = !empty($ex['produktif_done']);
                    $jDone = !empty($ex['jurnal_done']);
                    $pRow = $produktifMap[$ex['id']] ?? null;
                    $jRow = $jurnalAssessMap[$ex['id']] ?? null;
                ?>
                  <tr>
                    <td style="font-weight:700;"><?= $no++ ?></td>
                    <td>
                      <div style="font-weight:700;color:var(--color-text);"><?= penguji_h($ex['username']) ?></div>
                      <span style="font-size:0.72rem;color:var(--color-muted);"><?= penguji_h($ex['nama'] ?? '') ?></span>
                    </td>
                    <td><?= penguji_h($ex['kelas'] ?? '-') ?></td>
                    <td>
                      <?php if ($isProduktif): ?><span class="status-pill disetujui">Produktif</span><?php endif; ?>
                      <?php if ($isProduktif && $isJurnal): ?> <?php endif; ?>
                      <?php if ($isJurnal): ?><span class="status-pill disetujui">Jurnal</span><?php endif; ?>
                    </td>
                    <td>
                      <?php if (!$isProduktif): ?><span class="status-pill menunggu">Bukan tugas Anda</span>
                      <?php elseif ($pDone): ?><span class="status-pill disetujui">Sudah dinilai</span>
                      <?php else: ?><span class="status-pill menunggu">Belum dinilai</span><?php endif; ?>
                    </td>
                    <td>
                      <?php if (!$isJurnal): ?><span class="status-pill menunggu">Bukan tugas Anda</span>
                      <?php elseif ($jDone): ?><span class="status-pill disetujui">Sudah dinilai</span>
                      <?php else: ?><span class="status-pill menunggu">Belum dinilai</span><?php endif; ?>
                    </td>
                    <td>
                      <div class="btn-action-group">
                        <?php if ($isProduktif): ?>
                          <button type="button" class="btn-action-sm primary" onclick="openProduktifModal(<?= (int)$ex['id'] ?>, '<?= penguji_h(addslashes($ex['username'])) ?>', <?= (int)($pRow['score_materi'] ?? 0) ?>, <?= (int)($pRow['score_teknis'] ?? 0) ?>, <?= (int)($pRow['score_hasil'] ?? 0) ?>, <?= (int)($pRow['score_masalah'] ?? 0) ?>, <?= (int)($pRow['score_proses'] ?? 0) ?>, '<?= penguji_h(addslashes($pRow['catatan'] ?? '')) ?>')">
                            <span><?= pkl_icon('penilaian', '', 14) ?></span> Nilai Produktif
                          </button>
                        <?php endif; ?>
                        <?php if ($isJurnal): ?>
                          <button type="button" class="btn-action-sm primary" onclick="openJurnalModal(<?= (int)$ex['id'] ?>, '<?= penguji_h(addslashes($ex['username'])) ?>', <?= (int)($jRow['score_kelengkapan'] ?? 0) ?>, <?= (int)($jRow['score_sesuai'] ?? 0) ?>, <?= (int)($jRow['score_pemahaman'] ?? 0) ?>, <?= (int)($jRow['score_jelaskan'] ?? 0) ?>, <?= (int)($jRow['score_komunikasi'] ?? 0) ?>, '<?= penguji_h(addslashes($jRow['catatan'] ?? '')) ?>')">
                            <span><?= pkl_icon('jurnal', '', 14) ?></span> Nilai Jurnal
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Input Penilaian Sidang -->
  <div class="modal-backdrop" id="assessment-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" style="display:flex;align-items:center;gap:8px;"><span><?= pkl_icon('penilaian', '', 20) ?></span> Form Penilaian Sidang PKL</div>
        <button type="button" class="modal-close-btn" id="btn-close-assessment-modal">&times;</button>
      </div>

      <form method="POST" action="penguji.php" id="form-assessment">
        <input type="hidden" name="action" value="save_sidang_assessment">
        <input type="hidden" name="current_tab" value="section-penilaian">

        <div class="form-group">
          <label class="form-label" for="select_student_id">Pilih Peserta Sidang *</label>
          <select name="student_id" id="select_student_id" class="form-control" required>
            <option value="">-- Pilih Peserta Sidang --</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>">
                <?= penguji_h($st['username']) ?> (<?= penguji_h($st['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="score_understanding">1. Nilai Pemahaman terhadap Jurnal &amp; Kegiatan PKL (0 - 100) *</label>
          <input type="number" name="score_understanding" id="score_understanding" class="form-control" min="0" max="100" placeholder="Contoh: 85" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_presentation">2. Nilai Kemampuan Menjelaskan &amp; Presentasi Pekerjaan (0 - 100) *</label>
          <input type="number" name="score_presentation" id="score_presentation" class="form-control" min="0" max="100" placeholder="Contoh: 88" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_qa">3. Nilai Penguasaan Tanya Jawab &amp; Teori Teknis (0 - 100) *</label>
          <input type="number" name="score_qa" id="score_qa" class="form-control" min="0" max="100" placeholder="Contoh: 90" required oninput="calcFinalScore()">
        </div>

        <div class="info-box" style="background:var(--color-cream);margin-bottom:1.15rem;padding:0.85rem;">
          <div class="info-box-label">Estimasi Nilai Akhir Sidang (Rata-rata)</div>
          <div class="info-box-val" id="preview_final_score" style="color:var(--color-orange);">0.0</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="feedback">Catatan Evaluasi, Masukan &amp; Keputusan Sidang</label>
          <textarea name="feedback" id="feedback" class="form-control" rows="3" placeholder="Tuliskan catatan hasil sidang, penguasaan materi, saran perbaikan, atau catatan kelulusan sidang..."></textarea>
        </div>

        <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
          <button type="button" class="btn-primary" id="btn-cancel-assessment-modal" style="width:auto;background:transparent;color:var(--color-muted);border:1.5px solid var(--color-border);box-shadow:none;">Batal</button>
          <button type="submit" class="btn-primary" style="margin-top:0;flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
            <span><?= pkl_icon('save', '', 18) ?></span> Simpan Nilai Sidang
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Penilaian Penguji Produktif (5 kriteria, tanpa nilai gabungan) -->
  <div class="modal-backdrop" id="produktif-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" style="display:flex;align-items:center;gap:8px;"><span><?= pkl_icon('penguji', '', 20) ?></span> Form Penilaian Penguji Produktif</div>
        <button type="button" class="modal-close-btn" id="btn-close-produktif-modal">&times;</button>
      </div>
      <form method="POST" action="penguji.php" id="form-produktif">
        <input type="hidden" name="action" value="save_produktif_assessment">
        <input type="hidden" name="current_tab" value="section-examiner">
        <div class="form-group">
          <label class="form-label">Peserta *</label>
          <input type="text" id="produktif_student_label" class="form-control" readonly>
          <input type="hidden" name="student_id" id="produktif_student_id">
        </div>
        <div class="form-group"><label class="form-label">1. Penguasaan Materi (0 - 100) *</label><input type="number" name="score_materi" id="p_materi" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">2. Keterampilan Teknis (0 - 100) *</label><input type="number" name="score_teknis" id="p_teknis" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">3. Kualitas Hasil Pekerjaan (0 - 100) *</label><input type="number" name="score_hasil" id="p_hasil" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">4. Pemecahan Masalah (0 - 100) *</label><input type="number" name="score_masalah" id="p_masalah" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">5. Penguasaan Proses Kerja (0 - 100) *</label><input type="number" name="score_proses" id="p_proses" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">Catatan / Tanggapan Penguji</label><textarea name="catatan" id="p_catatan" class="form-control" rows="3" placeholder="Tuliskan catatan penilaian produktif siswa..."></textarea></div>
        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
          <button type="button" class="btn-action-sm" id="btn-cancel-produktif-modal">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto;margin-top:0;"><span><?= pkl_icon('save', '', 16) ?></span> Simpan Penilaian</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Penilaian Penguji Jurnal (5 kriteria, tanpa nilai gabungan) -->
  <div class="modal-backdrop" id="jurnal-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" style="display:flex;align-items:center;gap:8px;"><span><?= pkl_icon('jurnal', '', 20) ?></span> Form Penilaian Penguji Jurnal</div>
        <button type="button" class="modal-close-btn" id="btn-close-jurnal-modal">&times;</button>
      </div>
      <form method="POST" action="penguji.php" id="form-jurnal">
        <input type="hidden" name="action" value="save_jurnal_assessment">
        <input type="hidden" name="current_tab" value="section-examiner">
        <div class="form-group">
          <label class="form-label">Peserta *</label>
          <input type="text" id="jurnal_student_label" class="form-control" readonly>
          <input type="hidden" name="student_id" id="jurnal_student_id">
        </div>
        <div class="form-group"><label class="form-label">1. Kelengkapan Jurnal (0 - 100) *</label><input type="number" name="score_kelengkapan" id="j_kelengkapan" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">2. Kesesuaian Isi dengan Kegiatan PKL (0 - 100) *</label><input type="number" name="score_sesuai" id="j_sesuai" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">3. Pemahaman Kegiatan (0 - 100) *</label><input type="number" name="score_pemahaman" id="j_pemahaman" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">4. Kemampuan Menjelaskan (0 - 100) *</label><input type="number" name="score_jelaskan" id="j_jelaskan" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">5. Komunikasi (0 - 100) *</label><input type="number" name="score_komunikasi" id="j_komunikasi" class="form-control" min="0" max="100" required></div>
        <div class="form-group"><label class="form-label">Catatan / Tanggapan Penguji</label><textarea name="catatan" id="j_catatan" class="form-control" rows="3" placeholder="Tuliskan catatan penilaian jurnal siswa..."></textarea></div>
        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
          <button type="button" class="btn-action-sm" id="btn-cancel-jurnal-modal">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto;margin-top:0;"><span><?= pkl_icon('save', '', 16) ?></span> Simpan Penilaian</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function switchTab(targetId) {
      document.querySelectorAll('.content-tab-view').forEach(function (view) {
        view.hidden = true;
        view.classList.remove('active');
      });

      var activeView = document.getElementById(targetId);
      if (activeView) {
        activeView.hidden = false;
        activeView.classList.add('active');
      }

      document.querySelectorAll('.nav-link-item').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
      });

      var sidebarEl = document.getElementById('app-sidebar');
      if (sidebarEl) {
        sidebarEl.classList.remove('open');
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('.nav-link-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-target');
        if (target) {
          switchTab(target);
        }
      });
    });

    var mobileToggle = document.getElementById('mobile-menu-toggle');
    var sidebar = document.getElementById('app-sidebar');
    if (mobileToggle && sidebar) {
      mobileToggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
      });
    }

    var assessModal = document.getElementById('assessment-modal');

    function openAssessmentModal(studentId, studentName, sUnd, sPres, sQA, feedbackText) {
      if (!assessModal) return;
      var selectEl = document.getElementById('select_student_id');
      var undEl    = document.getElementById('score_understanding');
      var presEl   = document.getElementById('score_presentation');
      var qaEl     = document.getElementById('score_qa');
      var feedEl   = document.getElementById('feedback');

      if (selectEl) selectEl.value = studentId || '';
      if (undEl) undEl.value = sUnd || '';
      if (presEl) presEl.value = sPres || '';
      if (qaEl) qaEl.value = sQA || '';
      if (feedEl) feedEl.value = feedbackText || '';

      calcFinalScore();
      assessModal.classList.add('active');
    }

    function closeAssessmentModal() {
      if (assessModal) assessModal.classList.remove('active');
    }

    ['btn-close-assessment-modal', 'btn-cancel-assessment-modal'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('click', closeAssessmentModal);
    });

    if (assessModal) {
      assessModal.addEventListener('click', function (e) {
        if (e.target === assessModal) closeAssessmentModal();
      });
    }

    function calcFinalScore() {
      var u = parseFloat(document.getElementById('score_understanding').value) || 0;
      var p = parseFloat(document.getElementById('score_presentation').value) || 0;
      var q = parseFloat(document.getElementById('score_qa').value) || 0;
      var avg = (u + p + q) / 3;
      var preview = document.getElementById('preview_final_score');
      if (preview) {
        preview.textContent = avg.toFixed(1);
      }
    }

    // ===== Penguji Produktif & Jurnal =====
    function openProduktifModal(studentId, studentName, sMateri, sTeknis, sHasil, sMasalah, sProses, catatanText) {
      var m = document.getElementById('produktif-modal'); if (!m) return;
      document.getElementById('produktif_student_id').value = studentId || '';
      document.getElementById('produktif_student_label').value = studentName || '';
      document.getElementById('p_materi').value = sMateri || '';
      document.getElementById('p_teknis').value = sTeknis || '';
      document.getElementById('p_hasil').value = sHasil || '';
      document.getElementById('p_masalah').value = sMasalah || '';
      document.getElementById('p_proses').value = sProses || '';
      document.getElementById('p_catatan').value = catatanText || '';
      m.classList.add('active');
    }
    function openJurnalModal(studentId, studentName, sKelengkapan, sSesuai, sPemahaman, sJelaskan, sKomunikasi, catatanText) {
      var m = document.getElementById('jurnal-modal'); if (!m) return;
      document.getElementById('jurnal_student_id').value = studentId || '';
      document.getElementById('jurnal_student_label').value = studentName || '';
      document.getElementById('j_kelengkapan').value = sKelengkapan || '';
      document.getElementById('j_sesuai').value = sSesuai || '';
      document.getElementById('j_pemahaman').value = sPemahaman || '';
      document.getElementById('j_jelaskan').value = sJelaskan || '';
      document.getElementById('j_komunikasi').value = sKomunikasi || '';
      document.getElementById('j_catatan').value = catatanText || '';
      m.classList.add('active');
    }
    function closeProduktifModal(){ var m=document.getElementById('produktif-modal'); if(m) m.classList.remove('active'); }
    function closeJurnalModal(){ var m=document.getElementById('jurnal-modal'); if(m) m.classList.remove('active'); }
    ['btn-close-produktif-modal','btn-cancel-produktif-modal'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('click', closeProduktifModal); });
    ['btn-close-jurnal-modal','btn-cancel-jurnal-modal'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('click', closeJurnalModal); });
    var pm=document.getElementById('produktif-modal'); if(pm) pm.addEventListener('click', function(e){ if(e.target===pm) closeProduktifModal(); });
    var jm=document.getElementById('jurnal-modal'); if(jm) jm.addEventListener('click', function(e){ if(e.target===jm) closeJurnalModal(); });
  </script>
</body>
</html>
