<?php
// dashboard/industri.php
// Panel Dashboard Pembimbing Industri PKL Journey

require_once __DIR__ . '/../auth/check_industri.php';
require_once __DIR__ . '/../includes/icons.php';

$alertMessage = '';
$alertType = '';
$activeTab = 'section-dashboard';

if (!empty($_GET['tab'])) {
    $getTab = trim($_GET['tab']);
    if (!str_starts_with($getTab, 'section-')) {
        $getTab = 'section-' . $getTab;
    }
    if (in_array($getTab, ['section-dashboard', 'section-siswa', 'section-jurnal', 'section-penilaian'])) {
        $activeTab = $getTab;
    }
}

// ------------------------------------------
// 1. HANDLER AKSI: SIMPAN PENILAIAN INDUSTRI
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_industry_assessment') {
    if (!empty($_POST['current_tab'])) {
        $activeTab = trim($_POST['current_tab']);
    }

    $student_id         = (int)($_POST['student_id'] ?? 0);
    $score_discipline   = max(0, min(100, (int)($_POST['score_discipline'] ?? 0)));
    $score_attendance   = max(0, min(100, (int)($_POST['score_attendance'] ?? 0)));
    $score_work_quality = max(0, min(100, (int)($_POST['score_work_quality'] ?? 0)));
    $feedback           = trim($_POST['feedback'] ?? '');
    $final_score        = round(($score_discipline + $score_attendance + $score_work_quality) / 3, 2);

    try {
        // Keamanan Otoritas: Pastikan siswa benar-benar ditugaskan ke Guru Industri ini di tabel internships
        $stmtCheck = $pdo->prepare("
            SELECT u.id, u.username 
            FROM users u
            JOIN internships i ON u.id = i.student_id
            WHERE u.id = :sid AND i.industri_id = :iid AND u.role = 'murid'
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':sid' => $student_id,
            ':iid' => $industri_id
        ]);
        $studentUser = $stmtCheck->fetch();

        if ($studentUser) {
            $stmtSave = $pdo->prepare("
                INSERT INTO industry_assessments (student_id, industri_id, score_discipline, score_attendance, score_work_quality, final_score, feedback, assessed_at)
                VALUES (:sid, :iid, :s_disc, :s_att, :s_work, :final_score, :feedback, NOW())
                ON DUPLICATE KEY UPDATE
                    industri_id        = VALUES(industri_id),
                    score_discipline   = VALUES(score_discipline),
                    score_attendance   = VALUES(score_attendance),
                    score_work_quality = VALUES(score_work_quality),
                    final_score        = VALUES(final_score),
                    feedback           = VALUES(feedback),
                    assessed_at        = NOW()
            ");
            $stmtSave->execute([
                ':sid'         => $student_id,
                ':iid'         => $industri_id,
                ':s_disc'      => $score_discipline,
                ':s_att'       => $score_attendance,
                ':s_work'      => $score_work_quality,
                ':final_score' => $final_score,
                ':feedback'    => $feedback
            ]);
            $alertMessage = 'Penilaian industri untuk siswa <strong>' . htmlspecialchars($studentUser['username']) . '</strong> berhasil disimpan! (Nilai Akhir Industri: ' . number_format($final_score, 1) . ') ✓';
            $alertType = 'success';
        } else {
            $alertMessage = 'Akses ditolak: Siswa ini tidak terdaftar dalam bimbingan industri Anda.';
            $alertType = 'error';
        }
    } catch (Exception $e) {
        $alertMessage = 'Gagal menyimpan penilaian industri: ' . $e->getMessage();
        $alertType = 'error';
    }
}

// ------------------------------------------
// 2. QUERY SISWA, AKTIVITAS & JURNAL INDUSTRI
// ------------------------------------------

// Query Siswa yang ditugaskan ke Pembimbing Industri ini
$stmtStudents = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at,
           c.name AS company_name, c.address AS company_address,
           g.username AS guru_sekolah_name,
           COUNT(DISTINCT j.id) AS total_journals,
           COUNT(DISTINCT CASE WHEN j.status = 'disetujui' THEN j.id END) AS approved_journals,
           r.status AS report_status,
           ia.id AS assessment_id, ia.score_discipline, ia.score_attendance, ia.score_work_quality, ia.final_score, ia.feedback, ia.assessed_at
    FROM users u
    JOIN internships i ON u.id = i.student_id AND i.industri_id = :iid
    LEFT JOIN companies c ON i.company_id = c.id
    LEFT JOIN users g ON i.guru_id = g.id
    LEFT JOIN pkl_journals j ON u.id = j.student_id
    LEFT JOIN pkl_reports r ON u.id = r.student_id
    LEFT JOIN industry_assessments ia ON u.id = ia.student_id AND ia.industri_id = i.industri_id
    WHERE u.role = 'murid'
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmtStudents->execute([':iid' => $industri_id]);
$allStudents = $stmtStudents->fetchAll();

// Query Seluruh Jurnal Siswa Bimbingan Industri Ini
$stmtJournals = $pdo->prepare("
    SELECT j.*, u.username AS student_name, u.email AS student_email, c.name AS company_name
    FROM pkl_journals j
    JOIN users u ON j.student_id = u.id
    JOIN internships i ON u.id = i.student_id AND i.industri_id = :iid
    LEFT JOIN companies c ON i.company_id = c.id
    ORDER BY j.activity_date DESC, j.id DESC
");
$stmtJournals->execute([':iid' => $industri_id]);
$allJournals = $stmtJournals->fetchAll();

// Hitung Statistik
$totalStudents = count($allStudents);
$totalJournals = count($allJournals);
$assessedStudents = 0;
$pendingAssessments = 0;

foreach ($allStudents as $st) {
    if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0) {
        $assessedStudents++;
    } else {
        $pendingAssessments++;
    }
}

function industri_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function industri_excerpt($text, $max = 90) {
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
  <title>Dashboard Pembimbing Industri - PKL Journey</title>
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
    <!-- Sidebar Pembimbing Industri -->
    <aside class="app-sidebar" id="app-sidebar">
      <div class="sidebar-header">
        <a href="industri.php" class="sidebar-brand">
          <span class="brand-logo-icon"><?= pkl_logo(34) ?></span>
          <span>PKL <span>Journey</span></span>
        </a>
        <div class="sidebar-user-card">
          <div class="user-avatar-circle" style="background: linear-gradient(135deg, var(--color-primary-dark), var(--color-primary));">
            <?= strtoupper(substr($industri_username, 0, 1)) ?>
          </div>
          <div class="user-meta">
            <div class="user-meta-name" title="<?= industri_h($industri_username) ?>"><?= industri_h($industri_username) ?></div>
            <div class="user-meta-school">Pembimbing Industri</div>
          </div>
        </div>
      </div>

      <!-- Menu Sidebar Khusus Industri -->
      <nav class="sidebar-menu">
        <button type="button" class="nav-link-item <?= $activeTab === 'section-dashboard' ? 'active' : '' ?>" data-target="section-dashboard" id="nav-dashboard">
          <span class="nav-icon"><?= pkl_icon('dashboard') ?></span>
          <span>Dashboard</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-siswa' ? 'active' : '' ?>" data-target="section-siswa" id="nav-siswa">
          <span class="nav-icon"><?= pkl_icon('siswa') ?></span>
          <span>Siswa Praktik (DUDI)</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-jurnal' ? 'active' : '' ?>" data-target="section-jurnal" id="nav-jurnal">
          <span class="nav-icon"><?= pkl_icon('jurnal') ?></span>
          <span>Aktivitas &amp; Jurnal</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-penilaian' ? 'active' : '' ?>" data-target="section-penilaian" id="nav-penilaian">
          <span class="nav-icon"><?= pkl_icon('penilaian') ?></span>
          <span>Penilaian Industri</span>
        </button>
      </nav>

      <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link-logout">
          <span class="nav-icon"><?= pkl_icon('logout') ?></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Konten Utama Dashboard Industri -->
    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="topbar-title-wrap">
          <h1 class="topbar-greeting">Halo, <span><?= industri_h($industri_username) ?></span>!</h1>
          <p class="topbar-sub">Portal Pembimbing Industri / DUDI • Mitra PKL Journey</p>
        </div>
        <div class="topbar-actions">
          <button type="button" class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <button type="button" class="btn-primary" onclick="openAssessmentModal()" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
            <span><?= pkl_icon('penilaian', '', 18) ?></span> Input Nilai Industri
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
              <div class="stat-label">Siswa Praktik</div>
              <div class="stat-value"><?= (int)$totalStudents ?> Siswa</div>
              <div class="stat-sub">Ditugaskan di industri Anda</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('siswa', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Sudah Dinilai</div>
              <div class="stat-value" style="color:var(--color-success);"><?= (int)$assessedStudents ?> Siswa</div>
              <div class="stat-sub"><?= $totalStudents > 0 ? round(($assessedStudents / $totalStudents) * 100) : 0 ?>% evaluasi selesai</div>
            </div>
            <div class="stat-icon-badge" style="background:#F0FDF4;color:var(--color-success);"><?= pkl_icon('check', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Belum Dinilai</div>
              <div class="stat-value" style="color:var(--color-accent);"><?= (int)$pendingAssessments ?> Siswa</div>
              <div class="stat-sub">Menunggu penilaian akhir</div>
            </div>
            <div class="stat-icon-badge" style="background:var(--color-accent-light);color:var(--color-accent);"><?= pkl_icon('clock', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Jurnal Masuk</div>
              <div class="stat-value"><?= (int)$totalJournals ?> Entri</div>
              <div class="stat-sub">Laporan aktivitas kerja harian</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('jurnal', '', 24) ?></div>
          </div>
        </div>

        <!-- Daftar Siswa Bimbingan Preview -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('siswa', '', 20) ?></span> Siswa PKL dalam Bimbingan Industri Anda
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
            <button type="button" class="btn-action-sm primary" onclick="switchTab('section-siswa')">
              Lihat Semua Siswa →
            </button>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('perusahaan', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa yang Ditugaskan</div>
              <p class="empty-state-desc">Administrator sekolah belum menetapkan siswa ke akun pembimbing industri Anda.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Nama Siswa</th>
                    <th>Perusahaan / Divisi</th>
                    <th>Guru Sekolah</th>
                    <th>Aktivitas Jurnal</th>
                    <th>Nilai Industri</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allStudents as $st): ?>
                    <tr>
                      <td>
                        <div style="font-weight:700;color:var(--color-text);"><?= industri_h($st['username']) ?></div>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= industri_h($st['email']) ?></div>
                      </td>
                      <td>
                        <div style="font-weight:600;display:inline-flex;align-items:center;gap:5px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= industri_h($st['company_name'] ?? 'Mitra Industri') ?></div>
                      </td>
                      <td>
                        <span style="font-size:0.85rem;color:var(--color-text);display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('pembimbing_sekolah', '', 14) ?> <?= industri_h($st['guru_sekolah_name'] ?? '-') ?></span>
                      </td>
                      <td>
                        <strong><?= (int)$st['total_journals'] ?></strong> entri tercatat
                      </td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= industri_h(addslashes($st['username'])) ?>', <?= (int)($st['score_discipline'] ?? 0) ?>, <?= (int)($st['score_attendance'] ?? 0) ?>, <?= (int)($st['score_work_quality'] ?? 0) ?>, '<?= industri_h(addslashes($st['feedback'] ?? '')) ?>')">
                          <span><?= pkl_icon('penilaian', '', 14) ?></span> Nilai Kinerja
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

      <!-- 2. TAB: SISWA PKL -->
      <div class="content-tab-view <?= $activeTab === 'section-siswa' ? 'active' : '' ?>" id="section-siswa" <?= $activeTab === 'section-siswa' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('siswa', '', 20) ?></span> Data Lengkap Siswa Praktik Industri
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa Terdaftar</div>
              <p class="empty-state-desc">Belum ada siswa yang ditugaskan ke pembimbing industri Anda.</p>
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
                    <th>Total Jurnal</th>
                    <th>Status Nilai</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $st): ?>
                    <tr>
                      <td style="font-weight:700;width:40px;"><?= $no++ ?></td>
                      <td><strong><?= industri_h($st['username']) ?></strong></td>
                      <td><?= industri_h($st['email']) ?></td>
                      <td><span style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= industri_h($st['company_name'] ?? 'Mitra Industri') ?></span></td>
                      <td><?= (int)$st['total_journals'] ?> jurnal</td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= industri_h(addslashes($st['username'])) ?>', <?= (int)($st['score_discipline'] ?? 0) ?>, <?= (int)($st['score_attendance'] ?? 0) ?>, <?= (int)($st['score_work_quality'] ?? 0) ?>, '<?= industri_h(addslashes($st['feedback'] ?? '')) ?>')">
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

      <!-- 3. TAB: JURNAL & AKTIVITAS -->
      <div class="content-tab-view <?= $activeTab === 'section-jurnal' ? 'active' : '' ?>" id="section-jurnal" <?= $activeTab === 'section-jurnal' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Catatan Aktivitas &amp; Jurnal Harian Siswa
              <span class="sec-badge"><?= (int)$totalJournals ?> Total Entri</span>
            </div>
          </div>

          <p class="card-desc-muted">Pantau aktivitas teknis harian yang dikerjakan siswa di tempat PKL:</p>

          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Aktivitas Jurnal</div>
              <p class="empty-state-desc">Siswa yang Anda bimbing belum mencatat kegiatan kerja harian.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Siswa</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Pekerjaan / Aktivitas</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allJournals as $j): ?>
                    <tr>
                      <td style="white-space:nowrap;"><?= date('d M Y', strtotime($j['activity_date'])) ?></td>
                      <td>
                        <strong><?= industri_h($j['student_name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= industri_h($j['student_email']) ?></div>
                      </td>
                      <td><strong><?= industri_h($j['title']) ?></strong></td>
                      <td style="max-width:320px;"><?= nl2br(industri_h($j['description'])) ?></td>
                      <td>
                        <span class="status-pill <?= industri_h($j['status']) ?>" style="display:inline-flex;align-items:center;gap:4px;">
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

      <!-- 4. TAB: PENILAIAN INDUSTRI -->
      <div class="content-tab-view <?= $activeTab === 'section-penilaian' ? 'active' : '' ?>" id="section-penilaian" <?= $activeTab === 'section-penilaian' ? '' : 'hidden' ?>>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('penilaian', '', 20) ?></span> Lembar Penilaian Kinerja &amp; Kedisiplinan Siswa (DUDI)
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
            <button type="button" class="btn-primary" onclick="openAssessmentModal()" style="width:auto;margin-top:0;padding:0.6rem 1.25rem;font-size:0.88rem;display:inline-flex;align-items:center;gap:6px;">
              <span><?= pkl_icon('plus', '', 16) ?></span> Input Penilaian Baru
            </button>
          </div>

          <p class="card-desc-muted">Penilaian pembimbing industri mencakup: Kedisiplinan &amp; Sikap Kerja, Kehadiran &amp; Kerajinan, serta Kualitas Hasil Pekerjaan Praktik:</p>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('penilaian', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa untuk Dinilai</div>
              <p class="empty-state-desc">Siswa yang ditugaskan kepada Anda akan muncul di sini.</p>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Siswa</th>
                    <th>Disiplin (33%)</th>
                    <th>Kehadiran/Kerajinan (33%)</th>
                    <th>Hasil Kerja (33%)</th>
                    <th>Nilai Akhir Industri</th>
                    <th>Catatan Evaluasi</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $st): ?>
                    <tr>
                      <td style="font-weight:700;width:40px;"><?= $no++ ?></td>
                      <td>
                        <strong><?= industri_h($st['username']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= industri_h($st['email']) ?></div>
                      </td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_discipline'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_attendance'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($st['assessment_id']) ? (int)$st['score_work_quality'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td>
                        <?php if (!empty($st['assessment_id']) && (float)$st['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$st['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span style="font-size:0.82rem;color:var(--color-muted);">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td style="max-width:240px;font-size:0.85rem;color:var(--color-muted);">
                        <?= !empty($st['feedback']) ? industri_h(industri_excerpt($st['feedback'], 70)) : '<em>Tidak ada catatan</em>' ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$st['id'] ?>, '<?= industri_h(addslashes($st['username'])) ?>', <?= (int)($st['score_discipline'] ?? 0) ?>, <?= (int)($st['score_attendance'] ?? 0) ?>, <?= (int)($st['score_work_quality'] ?? 0) ?>, '<?= industri_h(addslashes($st['feedback'] ?? '')) ?>')">
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
    </main>
  </div>

  <!-- Modal Input Penilaian Industri -->
  <div class="modal-backdrop" id="assessment-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" style="display:flex;align-items:center;gap:8px;"><span><?= pkl_icon('penilaian', '', 20) ?></span> Form Penilaian Pembimbing Industri</div>
        <button type="button" class="modal-close-btn" id="btn-close-assessment-modal">&times;</button>
      </div>

      <form method="POST" action="industri.php" id="form-assessment">
        <input type="hidden" name="action" value="save_industry_assessment">
        <input type="hidden" name="current_tab" value="section-penilaian">

        <div class="form-group">
          <label class="form-label" for="select_student_id">Pilih Siswa Praktik *</label>
          <select name="student_id" id="select_student_id" class="form-control" required>
            <option value="">-- Pilih Siswa --</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>">
                <?= industri_h($st['username']) ?> (<?= industri_h($st['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="score_discipline">1. Nilai Kedisiplinan &amp; Kepatuhan Aturan Kerja (0 - 100) *</label>
          <input type="number" name="score_discipline" id="score_discipline" class="form-control" min="0" max="100" placeholder="Contoh: 88" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_attendance">2. Nilai Kehadiran, Ketepatan Waktu &amp; Kerajinan (0 - 100) *</label>
          <input type="number" name="score_attendance" id="score_attendance" class="form-control" min="0" max="100" placeholder="Contoh: 90" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_work_quality">3. Nilai Kinerja &amp; Hasil Pekerjaan Praktik Lapangan (0 - 100) *</label>
          <input type="number" name="score_work_quality" id="score_work_quality" class="form-control" min="0" max="100" placeholder="Contoh: 85" required oninput="calcFinalScore()">
        </div>

        <div class="info-box" style="background:var(--color-cream);margin-bottom:1.15rem;padding:0.85rem;">
          <div class="info-box-label">Estimasi Nilai Akhir Industri (Rata-rata)</div>
          <div class="info-box-val" id="preview_final_score" style="color:var(--color-orange);">0.0</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="feedback">Catatan Evaluasi, Performa &amp; Rekomendasi Industri</label>
          <textarea name="feedback" id="feedback" class="form-control" rows="3" placeholder="Tuliskan catatan etos kerja siswa, pencapaian teknis di industri, atau saran pengembangan..."></textarea>
        </div>

        <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
          <button type="button" class="btn-primary" id="btn-cancel-assessment-modal" style="width:auto;background:transparent;color:var(--color-muted);border:1.5px solid var(--color-border);box-shadow:none;">Batal</button>
          <button type="submit" class="btn-primary" style="margin-top:0;flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
            <span><?= pkl_icon('save', '', 18) ?></span> Simpan Penilaian Industri
          </button>
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

    function openAssessmentModal(studentId, studentName, sDisc, sAtt, sWork, feedbackText) {
      if (!assessModal) return;
      var selectEl = document.getElementById('select_student_id');
      var discEl   = document.getElementById('score_discipline');
      var attEl    = document.getElementById('score_attendance');
      var workEl   = document.getElementById('score_work_quality');
      var feedEl   = document.getElementById('feedback');

      if (selectEl) selectEl.value = studentId || '';
      if (discEl) discEl.value = sDisc || '';
      if (attEl) attEl.value = sAtt || '';
      if (workEl) workEl.value = sWork || '';
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
      var d = parseFloat(document.getElementById('score_discipline').value) || 0;
      var a = parseFloat(document.getElementById('score_attendance').value) || 0;
      var w = parseFloat(document.getElementById('score_work_quality').value) || 0;
      var avg = (d + a + w) / 3;
      var preview = document.getElementById('preview_final_score');
      if (preview) {
        preview.textContent = avg.toFixed(1);
      }
    }
  </script>
</body>
</html>
