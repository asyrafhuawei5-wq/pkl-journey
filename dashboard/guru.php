<?php
// dashboard/guru.php
// Panel Dashboard Guru Pembimbing PKL Journey

require_once __DIR__ . '/../auth/check_guru.php';
require_once __DIR__ . '/../includes/icons.php';

// Pastikan tabel database tersedia
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `companies` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `school_id` INT NOT NULL DEFAULT 1,
          `name` VARCHAR(255) NOT NULL,
          `address` TEXT NULL,
          `phone` VARCHAR(50) NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_companies_school` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `internships` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT NOT NULL UNIQUE,
          `guru_id` INT NULL,
          `company_id` INT NULL,
          `school_id` INT NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_internships_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_internships_guru` FOREIGN KEY (`guru_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_internships_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `pkl_journals` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `activity_date` DATE NOT NULL,
          `description` TEXT NOT NULL,
          `status` ENUM('menunggu', 'disetujui', 'revisi') NOT NULL DEFAULT 'menunggu',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_journals_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `pkl_reports` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `summary` TEXT NOT NULL,
          `submission_date` DATE NOT NULL,
          `status` ENUM('draft', 'dikirim', 'disetujui') NOT NULL DEFAULT 'dikirim',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_reports_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `pkl_assessments` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT NOT NULL UNIQUE,
          `guru_id` INT NOT NULL,
          `score_discipline` INT NOT NULL DEFAULT 0,
          `score_technical` INT NOT NULL DEFAULT 0,
          `score_communication` INT NOT NULL DEFAULT 0,
          `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `feedback` TEXT NULL,
          `assessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          CONSTRAINT `fk_assessments_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_assessments_guru` FOREIGN KEY (`guru_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // Abaikan jika tabel sudah terbuat
}

$alertMessage = '';
$alertType = '';

// 1. Aksi: Verifikasi Jurnal (Approve / Revisi / Menunggu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_journal_status') {
    $journal_id = (int)($_POST['journal_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    if (!in_array($new_status, ['menunggu', 'disetujui', 'revisi'])) {
        $alertMessage = 'Status jurnal yang dipilih tidak valid.';
        $alertType = 'error';
    } else {
        try {
            $stmtCheck = $pdo->prepare("
                SELECT j.id, u.username 
                FROM pkl_journals j
                JOIN users u ON j.student_id = u.id
                JOIN internships i ON u.id = i.student_id
                WHERE j.id = :jid AND u.school_id = :sid AND i.guru_id = :gid
                LIMIT 1
            ");
            $stmtCheck->execute([
                ':jid' => $journal_id,
                ':sid' => $guru_school_id,
                ':gid' => $guru_id
            ]);
            $journalData = $stmtCheck->fetch();

            if ($journalData) {
                $stmtUpdate = $pdo->prepare("UPDATE pkl_journals SET status = :status WHERE id = :jid");
                $stmtUpdate->execute([
                    ':status' => $new_status,
                    ':jid'    => $journal_id
                ]);
                $statusText = ($new_status === 'disetujui') ? 'Disetujui ✓' : (($new_status === 'revisi') ? 'Revisi ⚠️' : 'Menunggu ⏳');
                $alertMessage = 'Status jurnal siswa (' . $journalData['username'] . ') berhasil diubah menjadi ' . $statusText;
                $alertType = 'success';
            } else {
                $alertMessage = 'Akses ditolak: Jurnal tidak ditemukan atau siswa bukan merupakan murid bimbingan Anda.';
                $alertType = 'error';
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal memperbarui status jurnal: ' . $e->getMessage();
            $alertType = 'error';
        }
    }
}

// 2. Aksi: Verifikasi Laporan PKL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_report_status') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    if (!in_array($new_status, ['dikirim', 'disetujui', 'draft'])) {
        $alertMessage = 'Status laporan tidak valid.';
        $alertType = 'error';
    } else {
        try {
            $stmtCheck = $pdo->prepare("
                SELECT r.id, u.username 
                FROM pkl_reports r
                JOIN users u ON r.student_id = u.id
                JOIN internships i ON u.id = i.student_id
                WHERE r.id = :rid AND u.school_id = :sid AND i.guru_id = :gid
                LIMIT 1
            ");
            $stmtCheck->execute([
                ':rid' => $report_id,
                ':sid' => $guru_school_id,
                ':gid' => $guru_id
            ]);
            $reportData = $stmtCheck->fetch();

            if ($reportData) {
                $stmtUpdate = $pdo->prepare("UPDATE pkl_reports SET status = :status WHERE id = :rid");
                $stmtUpdate->execute([
                    ':status' => $new_status,
                    ':rid'    => $report_id
                ]);
                $alertMessage = 'Status laporan PKL siswa (' . $reportData['username'] . ') berhasil diubah menjadi ' . ($new_status === 'disetujui' ? 'Disetujui ✓' : 'Perlu Revisi');
                $alertType = 'success';
            } else {
                $alertMessage = 'Akses ditolak: Laporan tidak ditemukan atau siswa bukan merupakan murid bimbingan Anda.';
                $alertType = 'error';
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal memperbarui laporan: ' . $e->getMessage();
            $alertType = 'error';
        }
    }
}

// 3. Aksi: Simpan Penilaian Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_assessment') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $score_discipline = max(0, min(100, (int)($_POST['score_discipline'] ?? 0)));
    $score_technical = max(0, min(100, (int)($_POST['score_technical'] ?? 0)));
    $score_communication = max(0, min(100, (int)($_POST['score_communication'] ?? 0)));
    $feedback = trim($_POST['feedback'] ?? '');
    $final_score = round(($score_discipline + $score_technical + $score_communication) / 3, 2);

    try {
        $stmtCheckStudent = $pdo->prepare("
            SELECT u.id, u.username FROM users u
            JOIN internships i ON u.id = i.student_id
            WHERE u.id = :sid AND u.school_id = :school_id AND u.role = 'murid' AND i.guru_id = :gid
            LIMIT 1
        ");
        $stmtCheckStudent->execute([
            ':sid'       => $student_id,
            ':school_id' => $guru_school_id,
            ':gid'       => $guru_id
        ]);
        $studentUser = $stmtCheckStudent->fetch();

        if ($studentUser) {
            $stmtSave = $pdo->prepare("
                INSERT INTO pkl_assessments (student_id, guru_id, score_discipline, score_technical, score_communication, final_score, feedback, assessed_at)
                VALUES (:sid, :gid, :s_disc, :s_tech, :s_comm, :final_score, :feedback, NOW())
                ON DUPLICATE KEY UPDATE
                    guru_id = VALUES(guru_id),
                    score_discipline = VALUES(score_discipline),
                    score_technical = VALUES(score_technical),
                    score_communication = VALUES(score_communication),
                    final_score = VALUES(final_score),
                    feedback = VALUES(feedback),
                    assessed_at = NOW()
            ");
            $stmtSave->execute([
                ':sid'         => $student_id,
                ':gid'         => $guru_id,
                ':s_disc'      => $score_discipline,
                ':s_tech'      => $score_technical,
                ':s_comm'      => $score_communication,
                ':final_score' => $final_score,
                ':feedback'    => $feedback
            ]);
            $alertMessage = 'Penilaian untuk siswa ' . $studentUser['username'] . ' berhasil disimpan! (Nilai Akhir: ' . number_format($final_score, 1) . ') ✓';
            $alertType = 'success';
        } else {
            $alertMessage = 'Siswa tidak valid atau bukan merupakan murid bimbingan Anda.';
            $alertType = 'error';
        }
    } catch (Exception $e) {
        $alertMessage = 'Gagal menyimpan penilaian: ' . $e->getMessage();
        $alertType = 'error';
    }
}

// Query Khusus Siswa Bimbingan Guru Ini
$stmtStudents = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at,
           COUNT(DISTINCT j.id) AS total_journals,
           COUNT(DISTINCT CASE WHEN j.status = 'disetujui' THEN j.id END) AS approved_journals,
           COUNT(DISTINCT CASE WHEN j.status = 'menunggu' THEN j.id END) AS pending_journals,
           COUNT(DISTINCT CASE WHEN j.status = 'revisi' THEN j.id END) AS revision_journals,
           r.id AS report_id, r.title AS report_title, r.summary AS report_summary, r.status AS report_status, r.submission_date AS report_date,
           a.id AS assessment_id, a.score_discipline, a.score_technical, a.score_communication, a.final_score, a.feedback, a.assessed_at
    FROM users u
    JOIN internships i ON u.id = i.student_id AND i.guru_id = :guru_id
    LEFT JOIN pkl_journals j ON u.id = j.student_id
    LEFT JOIN pkl_reports r ON u.id = r.student_id
    LEFT JOIN pkl_assessments a ON u.id = a.student_id
    WHERE u.role = 'murid' AND u.school_id = :school_id
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmtStudents->execute([':school_id' => $guru_school_id, ':guru_id' => $guru_id]);
$allStudents = $stmtStudents->fetchAll();

// Query Seluruh Jurnal Siswa Bimbingan Guru Ini
$stmtJournals = $pdo->prepare("
    SELECT j.*, u.username AS student_name, u.email AS student_email
    FROM pkl_journals j
    JOIN users u ON j.student_id = u.id
    JOIN internships i ON u.id = i.student_id AND i.guru_id = :guru_id
    WHERE u.school_id = :school_id
    ORDER BY j.activity_date DESC, j.id DESC
");
$stmtJournals->execute([':school_id' => $guru_school_id, ':guru_id' => $guru_id]);
$allJournals = $stmtJournals->fetchAll();

// Query Seluruh Laporan Siswa Bimbingan Guru Ini
$stmtReports = $pdo->prepare("
    SELECT r.*, u.username AS student_name, u.email AS student_email
    FROM pkl_reports r
    JOIN users u ON r.student_id = u.id
    JOIN internships i ON u.id = i.student_id AND i.guru_id = :guru_id
    WHERE u.school_id = :school_id
    ORDER BY r.submission_date DESC, r.id DESC
");
$stmtReports->execute([':school_id' => $guru_school_id, ':guru_id' => $guru_id]);
$allReports = $stmtReports->fetchAll();

// Hitung Statistik Dashboard
$totalStudents = count($allStudents);
$totalJournals = count($allJournals);
$pendingJournals = 0;
$approvedJournals = 0;
$revisionJournals = 0;

foreach ($allJournals as $j) {
    if ($j['status'] === 'menunggu') {
        $pendingJournals++;
    } elseif ($j['status'] === 'disetujui') {
        $approvedJournals++;
    } elseif ($j['status'] === 'revisi') {
        $revisionJournals++;
    }
}

$totalReports = count($allReports);
$pendingReports = 0;
$approvedReports = 0;

foreach ($allReports as $r) {
    if ($r['status'] === 'dikirim') {
        $pendingReports++;
    } elseif ($r['status'] === 'disetujui') {
        $approvedReports++;
    }
}

// Analisis Progress Siswa
$activeStudents = 0;
$completedStudents = 0;

// Helper untuk menghitung step & percent per siswa
function get_student_journey_progress($student) {
    $hasAssessment = !empty($student['assessment_id']) && (float)$student['final_score'] > 0;
    $hasReportApproved = ($student['report_status'] === 'disetujui');
    $hasReportSubmitted = ($student['report_status'] === 'dikirim');
    $hasApprovedJournals = ((int)$student['approved_journals'] >= 1);
    $hasJournals = ((int)$student['total_journals'] >= 1);

    if ($hasAssessment) {
        return [
            'step' => 6,
            'percent' => 100,
            'stage' => 'Selesai PKL',
            'is_complete' => true
        ];
    }
    if ($hasReportApproved) {
        return [
            'step' => 6,
            'percent' => 90,
            'stage' => 'Laporan Disetujui',
            'is_complete' => false
        ];
    }
    if ($hasReportSubmitted) {
        return [
            'step' => 5,
            'percent' => 75,
            'stage' => 'Laporan Dikirim',
            'is_complete' => false
        ];
    }
    if ($hasApprovedJournals) {
        return [
            'step' => 4,
            'percent' => 60,
            'stage' => 'Monitoring & Bimbingan',
            'is_complete' => false
        ];
    }
    if ($hasJournals) {
        return [
            'step' => 3,
            'percent' => 45,
            'stage' => 'Mulai Praktik PKL',
            'is_complete' => false
        ];
    }
    return [
        'step' => 2,
        'percent' => 30,
        'stage' => 'Diterima di Industri',
        'is_complete' => false
    ];
}

foreach ($allStudents as $student) {
    $prog = get_student_journey_progress($student);
    if ($prog['is_complete']) {
        $completedStudents++;
    } else {
        $activeStudents++;
    }
}

// Helper Sanitasi & Tampilan
function guru_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function guru_excerpt($text, $max = 90) {
    $text = (string)$text;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '...');
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max) . '...';
}

function guru_stepper_class($nodeStep, $currentStep) {
    if ($currentStep >= $nodeStep) {
        return ($currentStep > $nodeStep) ? 'completed' : 'active';
    }
    return '';
}

// Pending journals untuk review cepat
$pendingJournalsList = array_filter($allJournals, function($j) {
    return $j['status'] === 'menunggu';
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Guru Pembimbing - PKL Journey</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* Styling pelengkap untuk kontrol Guru */
    .btn-action-group {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      flex-wrap: wrap;
    }
    .btn-action-sm {
      padding: 0.35rem 0.65rem;
      font-size: 0.76rem;
      font-weight: 700;
      border-radius: var(--radius-sm);
      border: 1px solid var(--color-border);
      background: var(--color-white);
      color: var(--color-text);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      transition: var(--transition);
      text-decoration: none;
    }
    .btn-action-sm:hover {
      background: var(--color-slate-100);
      color: var(--color-primary);
      border-color: var(--color-border);
    }
    .btn-action-sm.approve {
      background: #E8F5E9;
      color: var(--color-success);
      border-color: #A5D6A7;
    }
    .btn-action-sm.approve:hover {
      background: #C8E6C9;
    }
    .btn-action-sm.revise {
      background: #FFFBEB;
      color: #B45309;
      border-color: #FDE68A;
    }
    .btn-action-sm.revise:hover {
      background: #FEF3C7;
    }
    .notification-item {
      background: var(--color-white);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 1rem 1.25rem;
      margin-bottom: 0.85rem;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      transition: var(--transition);
    }
    .notification-item:hover {
      border-color: var(--color-accent);
      transform: translateY(-1px);
    }
    .notification-icon-wrap {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
      background: var(--color-accent-light);
      flex-shrink: 0;
    }
    .notification-content {
      flex: 1;
    }
    .notification-title {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--color-text);
      margin-bottom: 0.15rem;
    }
    .notification-sub {
      font-size: 0.8rem;
      color: var(--color-muted);
    }
    .filter-pills-wrap {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 1.25rem;
    }
    .filter-pill {
      padding: 0.4rem 0.9rem;
      border-radius: var(--radius-full);
      font-size: 0.82rem;
      font-weight: 700;
      background: var(--color-white);
      border: 1.5px solid var(--color-border);
      color: var(--color-muted);
      cursor: pointer;
      transition: var(--transition);
    }
    .filter-pill.active, .filter-pill:hover {
      background: var(--color-orange);
      color: var(--color-white);
      border-color: var(--color-dark-orange);
    }
    .student-journey-card {
      background: var(--color-white);
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 12px rgba(15, 39, 68, 0.04);
      transition: var(--transition);
    }
    .student-journey-card:hover {
      border-color: rgba(2, 132, 199, 0.5);
      box-shadow: 0 8px 20px -4px var(--color-card-shadow);
    }
    .student-header-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--color-border);
      padding-bottom: 1rem;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .student-avatar-wrap {
      display: flex;
      align-items: center;
      gap: 0.85rem;
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
    <!-- Sidebar Guru PKL -->
    <aside class="app-sidebar" id="app-sidebar">
      <div class="sidebar-header">
        <a href="guru.php" class="sidebar-brand">
          <span class="brand-logo-icon"><?= pkl_logo(34) ?></span> PKL <span class="brand-text-accent">Journey</span>
        </a>
        <div class="sidebar-user-card">
          <div class="user-avatar-circle" style="background: linear-gradient(135deg, var(--color-primary), var(--color-navy));">
            <?= strtoupper(substr($guru_username, 0, 1)) ?>
          </div>
          <div class="user-meta">
            <div class="user-meta-name" title="<?= guru_h($guru_username) ?>"><?= guru_h($guru_username) ?></div>
            <div class="user-meta-school" title="<?= guru_h($guru_school_name) ?>"><?= guru_h($guru_school_name) ?></div>
          </div>
        </div>
      </div>

      <!-- Menu Sidebar Sesuai Request -->
      <nav class="sidebar-menu">
        <button type="button" class="nav-link-item active" data-target="section-dashboard" id="nav-dashboard">
          <span class="nav-icon"><?= pkl_icon('dashboard') ?></span>
          <span>Dashboard</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-siswa" id="nav-siswa">
          <span class="nav-icon"><?= pkl_icon('siswa') ?></span>
          <span>Data Siswa PKL</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-jurnal" id="nav-jurnal">
          <span class="nav-icon"><?= pkl_icon('jurnal') ?></span>
          <span>Jurnal Siswa</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-progress" id="nav-progress">
          <span class="nav-icon"><?= pkl_icon('journey') ?></span>
          <span>Progress PKL</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-penilaian" id="nav-penilaian">
          <span class="nav-icon"><?= pkl_icon('penilaian') ?></span>
          <span>Penilaian</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-laporan" id="nav-laporan">
          <span class="nav-icon"><?= pkl_icon('laporan') ?></span>
          <span>Laporan PKL</span>
        </button>
      </nav>

      <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link-logout">
          <span class="nav-icon"><?= pkl_icon('logout') ?></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Konten Utama Dashboard -->
    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="topbar-title-wrap">
          <h1 class="topbar-greeting">Halo, <span><?= guru_h($guru_username) ?></span>!</h1>
          <p class="topbar-sub">Portal Guru Pembimbing PKL • <?= guru_h($guru_school_name) ?></p>
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
            <span><?= pkl_icon('penilaian', '', 18) ?></span> Beri Penilaian Siswa
          </button>
        </div>
      </header>

      <?php if ($alertMessage !== ''): ?>
        <div class="status-box <?= $alertType === 'success' ? 'success' : 'error' ?>">
          <?= guru_h($alertMessage) ?>
        </div>
      <?php endif; ?>

      <!-- 1. TAB: DASHBOARD -->
      <div class="content-tab-view" id="section-dashboard">
        <!-- 4 Stat Cards Sesuai Request -->
        <div class="stats-row">
          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Total Siswa</div>
              <div class="stat-value"><?= (int)$totalStudents ?> Siswa</div>
              <div class="stat-sub">Bimbingan di <?= guru_h($guru_school_name) ?></div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('siswa', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Siswa Aktif</div>
              <div class="stat-value"><?= (int)$activeStudents ?> Siswa</div>
              <div class="stat-progress-bar-bg">
                <div class="stat-progress-bar-fill" style="width: <?= $totalStudents > 0 ? round(($activeStudents / $totalStudents) * 100) : 0 ?>%;"></div>
              </div>
              <div class="stat-sub"><?= (int)$completedStudents ?> Siswa telah menyelesaikan PKL</div>
            </div>
            <div class="stat-icon-badge" style="background:#E8F5E9;color:var(--color-success);"><?= pkl_icon('journey', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Jurnal Masuk</div>
              <div class="stat-value"><?= (int)$totalJournals ?> Entri</div>
              <div class="stat-sub">
                <?= (int)$pendingJournals ?> Menunggu • <?= (int)$approvedJournals ?> Disetujui
              </div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('jurnal', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Laporan PKL</div>
              <div class="stat-value"><?= (int)$totalReports ?> Dokumen</div>
              <div class="stat-sub">
                <?= (int)$pendingReports ?> Perlu Review • <?= (int)$approvedReports ?> Selesai
              </div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('laporan', '', 24) ?></div>
          </div>
        </div>

        <!-- Notifikasi & Aktivitas Siswa -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('alert', '', 20) ?></span> Notifikasi &amp; Aktivitas Bimbingan
              <span class="sec-badge"><?= (int)($pendingJournals + $pendingReports) ?> Perlu Tindakan</span>
            </div>
          </div>

          <?php if ($pendingJournals === 0 && $pendingReports === 0): ?>
            <div class="notification-item">
              <div class="notification-icon-wrap" style="background:#E8F5E9;color:var(--color-success);"><?= pkl_icon('check', '', 20) ?></div>
              <div class="notification-content">
                <div class="notification-title">Semua Laporan &amp; Jurnal Telah Ditinjau</div>
                <div class="notification-sub">Tidak ada entri jurnal atau laporan yang menunggu verifikasi saat ini. Seluruh progres bimbingan berjalan lancar.</div>
              </div>
            </div>
          <?php else: ?>
            <?php if ($pendingJournals > 0): ?>
              <div class="notification-item">
                <div class="notification-icon-wrap" style="background:var(--color-slate-100);color:var(--color-accent);"><?= pkl_icon('clock', '', 20) ?></div>
                <div class="notification-content">
                  <div class="notification-title">Terdapat <?= (int)$pendingJournals ?> Jurnal Kegiatan Menunggu Verifikasi</div>
                  <div class="notification-sub">Siswa bimbingan telah mencatat kegiatan harian baru dan membutuhkan persetujuan guru pembimbing.</div>
                </div>
                <button type="button" class="btn-action-sm" onclick="switchTab('section-jurnal')">Tinjau Jurnal →</button>
              </div>
            <?php endif; ?>

            <?php if ($pendingReports > 0): ?>
              <div class="notification-item">
                <div class="notification-icon-wrap" style="background:var(--color-slate-100);color:var(--color-navy);"><?= pkl_icon('laporan', '', 20) ?></div>
                <div class="notification-content">
                  <div class="notification-title">Terdapat <?= (int)$pendingReports ?> Laporan Akhir PKL Siap Ditinjau</div>
                  <div class="notification-sub">Siswa telah mengunggah ringkasan laporan PKL untuk evaluasi akhir.</div>
                </div>
                <button type="button" class="btn-action-sm" onclick="switchTab('section-laporan')">Tinjau Laporan →</button>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Jurnal Masuk Terbaru -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Jurnal Siswa Terbaru
              <span class="sec-badge"><?= (int)$totalJournals ?> Total</span>
            </div>
            <button type="button" class="btn-primary" onclick="switchTab('section-jurnal')" style="width:auto;margin-top:0;padding:0.45rem 1rem;font-size:0.82rem;background:transparent;color:var(--color-orange);border:1.5px solid var(--color-orange);box-shadow:none;">
              Buka Semua Jurnal →
            </button>
          </div>

          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Jurnal Kegiatan yang Masuk</div>
              <p class="empty-state-desc">Siswa bimbingan Anda di <?= guru_h($guru_school_name) ?> belum mengunggah catatan harian PKL.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Nama Siswa</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Ringkas</th>
                    <th>Status</th>
                    <th>Aksi Verifikasi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($allJournals, 0, 5) as $journal): ?>
                    <?php $statusClass = strtolower($journal['status']); ?>
                    <tr>
                      <td><?= date('d M Y', strtotime($journal['activity_date'])) ?></td>
                      <td>
                        <strong><?= guru_h($journal['student_name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= guru_h($journal['student_email']) ?></div>
                      </td>
                      <td><?= guru_h($journal['title']) ?></td>
                      <td><?= guru_h(guru_excerpt($journal['description'], 85)) ?></td>
                      <td>
                        <span class="status-pill <?= guru_h($statusClass) ?>" style="display:inline-flex;align-items:center;gap:3px;">
                          <?= $statusClass === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : ($statusClass === 'revisi' ? pkl_icon('alert', '', 12) . ' Revisi' : pkl_icon('clock', '', 12) . ' Menunggu') ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-action-group">
                          <form method="POST" action="guru.php" style="display:inline;">
                            <input type="hidden" name="action" value="update_journal_status">
                            <input type="hidden" name="journal_id" value="<?= (int)$journal['id'] ?>">
                            <input type="hidden" name="status" value="disetujui">
                            <button type="submit" class="btn-action-sm approve" title="Setujui Jurnal" style="display:inline-flex;align-items:center;gap:3px;"><span><?= pkl_icon('check', '', 12) ?></span> Setujui</button>
                          </form>
                          <form method="POST" action="guru.php" style="display:inline;">
                            <input type="hidden" name="action" value="update_journal_status">
                            <input type="hidden" name="journal_id" value="<?= (int)$journal['id'] ?>">
                            <input type="hidden" name="status" value="revisi">
                            <button type="submit" class="btn-action-sm revise" title="Minta Revisi Jurnal" style="display:inline-flex;align-items:center;gap:3px;"><span><?= pkl_icon('alert', '', 12) ?></span> Revisi</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Daftar Siswa Bimbingan Preview -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('siswa', '', 20) ?></span> Daftar Siswa Bimbingan PKL
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa Terdaftar</span>
            </div>
            <button type="button" class="btn-primary" onclick="switchTab('section-siswa')" style="width:auto;margin-top:0;padding:0.45rem 1rem;font-size:0.82rem;background:transparent;color:var(--color-orange);border:1.5px solid var(--color-orange);box-shadow:none;">
              Lihat Seluruh Siswa →
            </button>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa Terdaftar</div>
              <p class="empty-state-desc">Belum ada akun siswa yang terdaftar di sekolah <?= guru_h($guru_school_name) ?>.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Siswa</th>
                    <th>Total Jurnal</th>
                    <th>Laporan PKL</th>
                    <th>Progress Milestone</th>
                    <th>Nilai Akhir</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($allStudents, 0, 5) as $student): ?>
                    <?php $prog = get_student_journey_progress($student); ?>
                    <tr>
                      <td>
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                          <div class="user-avatar-circle" style="width:34px;height:34px;font-size:0.9rem;">
                            <?= strtoupper(substr($student['username'], 0, 1)) ?>
                          </div>
                          <div>
                            <strong><?= guru_h($student['username']) ?></strong>
                            <div style="font-size:0.75rem;color:var(--color-muted);"><?= guru_h($student['email']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <strong><?= (int)$student['total_journals'] ?></strong>
                        <span style="font-size:0.75rem;color:var(--color-muted);"> (<?= (int)$student['approved_journals'] ?> ACC)</span>
                      </td>
                      <td>
                        <?php if ($student['report_id']): ?>
                          <span class="status-pill <?= guru_h(strtolower($student['report_status'])) ?>" style="display:inline-flex;align-items:center;gap:3px;">
                            <?= $student['report_status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Dikirim' ?>
                          </span>
                        <?php else: ?>
                          <span style="font-size:0.8rem;color:var(--color-muted);">Belum Kirim</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-size:0.82rem;font-weight:700;margin-bottom:0.25rem;">
                          Tahap <?= $prog['step'] ?>/6 • <?= $prog['percent'] ?>%
                        </div>
                        <div class="stat-progress-bar-bg" style="margin-top:0;height:6px;width:120px;">
                          <div class="stat-progress-bar-fill" style="width: <?= $prog['percent'] ?>%;"></div>
                        </div>
                      </td>
                      <td>
                        <?php if (!empty($student['assessment_id']) && (float)$student['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$student['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span style="font-size:0.8rem;color:var(--color-muted);">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$student['id'] ?>, '<?= guru_h(addslashes($student['username'])) ?>', <?= (int)($student['score_discipline'] ?? 0) ?>, <?= (int)($student['score_technical'] ?? 0) ?>, <?= (int)($student['score_communication'] ?? 0) ?>, '<?= guru_h(addslashes($student['feedback'] ?? '')) ?>')" style="display:inline-flex;align-items:center;gap:3px;">
                          <span><?= pkl_icon('penilaian', '', 13) ?></span> Beri Nilai
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

      <!-- 2. TAB: DATA SISWA PKL -->
      <div class="content-tab-view" id="section-siswa" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('siswa', '', 20) ?></span> Data Lengkap Siswa PKL Bimbingan
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
          </div>

          <p class="card-desc-muted">Daftar seluruh siswa aktif maupun alumni bimbingan PKL dari <?= guru_h($guru_school_name) ?>:</p>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div>
              <div class="empty-state-title">Tidak Ada Siswa Ditemukan</div>
              <p class="empty-state-desc">Belum ada siswa yang terdaftar di sekolah ini.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Siswa</th>
                    <th>Email</th>
                    <th>Total Jurnal</th>
                    <th>Laporan PKL</th>
                    <th>Status Journey</th>
                    <th>Nilai Akhir</th>
                    <th>Aksi Guru</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $student): ?>
                    <?php $prog = get_student_journey_progress($student); ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                          <div class="user-avatar-circle" style="width:32px;height:32px;font-size:0.85rem;">
                            <?= strtoupper(substr($student['username'], 0, 1)) ?>
                          </div>
                          <strong><?= guru_h($student['username']) ?></strong>
                        </div>
                      </td>
                      <td><?= guru_h($student['email']) ?></td>
                      <td>
                        <div><strong><?= (int)$student['total_journals'] ?></strong> total</div>
                        <div style="font-size:0.75rem;color:var(--color-muted);">
                          <?= (int)$student['approved_journals'] ?> Disetujui • <?= (int)$student['pending_journals'] ?> Menunggu
                        </div>
                      </td>
                      <td>
                        <?php if ($student['report_id']): ?>
                          <span class="status-pill <?= guru_h(strtolower($student['report_status'])) ?>" style="display:inline-flex;align-items:center;gap:3px;">
                            <?= $student['report_status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Dikirim' ?>
                          </span>
                        <?php else: ?>
                          <span style="font-size:0.82rem;color:var(--color-muted);">Belum Submit</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="status-pill <?= $prog['is_complete'] ? 'disetujui' : 'menunggu' ?>">
                          <?= $prog['is_complete'] ? pkl_icon('check', '', 12) . ' ' : '' ?><?= guru_h($prog['stage']) ?>
                        </span>
                      </td>
                      <td>
                        <?php if (!empty($student['assessment_id']) && (float)$student['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$student['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span style="font-size:0.82rem;color:var(--color-muted);">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssessmentModal(<?= (int)$student['id'] ?>, '<?= guru_h(addslashes($student['username'])) ?>', <?= (int)($student['score_discipline'] ?? 0) ?>, <?= (int)($student['score_technical'] ?? 0) ?>, <?= (int)($student['score_communication'] ?? 0) ?>, '<?= guru_h(addslashes($student['feedback'] ?? '')) ?>')" style="display:inline-flex;align-items:center;gap:3px;">
                          <span><?= pkl_icon('penilaian', '', 13) ?></span> Nilai
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

      <!-- 3. TAB: JURNAL SISWA -->
      <div class="content-tab-view" id="section-jurnal" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Manajemen &amp; Verifikasi Jurnal Kegiatan Siswa
              <span class="sec-badge"><?= (int)$totalJournals ?> Total</span>
            </div>
          </div>

          <!-- Filter Pills -->
          <div class="filter-pills-wrap">
            <button type="button" class="filter-pill active" onclick="filterJournals('all', this)">Semua (<?= (int)$totalJournals ?>)</button>
            <button type="button" class="filter-pill" onclick="filterJournals('menunggu', this)"><?= pkl_icon('clock', '', 13) ?> Menunggu (<?= (int)$pendingJournals ?>)</button>
            <button type="button" class="filter-pill" onclick="filterJournals('disetujui', this)"><?= pkl_icon('check', '', 13) ?> Disetujui (<?= (int)$approvedJournals ?>)</button>
            <button type="button" class="filter-pill" onclick="filterJournals('revisi', this)"><?= pkl_icon('alert', '', 13) ?> Revisi (<?= (int)$revisionJournals ?>)</button>
          </div>

          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Jurnal Kegiatan yang Dikirim</div>
              <p class="empty-state-desc">Siswa akan mencatat kegiatan harian PKL yang akan muncul di halaman ini.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table" id="table-jurnal-full">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Siswa</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Pembelajaran</th>
                    <th>Status</th>
                    <th>Aksi Pembimbing</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allJournals as $journal): ?>
                    <?php $statusClass = strtolower($journal['status']); ?>
                    <tr class="journal-row" data-status="<?= guru_h($statusClass) ?>">
                      <td><?= $no++ ?></td>
                      <td><?= date('d M Y', strtotime($journal['activity_date'])) ?></td>
                      <td>
                        <strong><?= guru_h($journal['student_name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= guru_h($journal['student_email']) ?></div>
                      </td>
                      <td><strong><?= guru_h($journal['title']) ?></strong></td>
                      <td style="max-width:320px;"><?= nl2br(guru_h($journal['description'])) ?></td>
                      <td>
                        <span class="status-pill <?= guru_h($statusClass) ?>" style="display:inline-flex;align-items:center;gap:3px;">
                          <?= $statusClass === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : ($statusClass === 'revisi' ? pkl_icon('alert', '', 12) . ' Revisi' : pkl_icon('clock', '', 12) . ' Menunggu') ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-action-group">
                          <form method="POST" action="guru.php" style="display:inline;">
                            <input type="hidden" name="action" value="update_journal_status">
                            <input type="hidden" name="journal_id" value="<?= (int)$journal['id'] ?>">
                            <input type="hidden" name="status" value="disetujui">
                            <button type="submit" class="btn-action-sm approve" title="Setujui Jurnal" style="display:inline-flex;align-items:center;gap:3px;"><span><?= pkl_icon('check', '', 12) ?></span> Setujui</button>
                          </form>
                          <form method="POST" action="guru.php" style="display:inline;">
                            <input type="hidden" name="action" value="update_journal_status">
                            <input type="hidden" name="journal_id" value="<?= (int)$journal['id'] ?>">
                            <input type="hidden" name="status" value="revisi">
                            <button type="submit" class="btn-action-sm revise" title="Minta Revisi Jurnal" style="display:inline-flex;align-items:center;gap:3px;"><span><?= pkl_icon('alert', '', 12) ?></span> Revisi</button>
                          </form>
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

      <!-- 4. TAB: PROGRESS PKL -->
      <div class="content-tab-view" id="section-progress" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('journey', '', 20) ?></span> Peta &amp; Monitoring Progress PKL Siswa
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa Bimbingan</span>
            </div>
          </div>

          <p class="card-desc-muted">Berikut adalah perkembangan perjalanan tahapan praktik kerja lapangan untuk masing-masing siswa:</p>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('journey', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Data Siswa</div>
              <p class="empty-state-desc">Data progress akan tampil setelah ada siswa yang mendaftar.</p>
            </div>
          <?php else: ?>
            <?php foreach ($allStudents as $student): ?>
              <?php 
                $prog = get_student_journey_progress($student); 
                $cStep = $prog['step'];
                $stepperFill = max(0, min(100, (($cStep - 1) / 5) * 84));
              ?>
              <div class="student-journey-card">
                <div class="student-header-row">
                  <div class="student-avatar-wrap">
                    <div class="user-avatar-circle" style="width:44px;height:44px;">
                      <?= strtoupper(substr($student['username'], 0, 1)) ?>
                    </div>
                    <div>
                      <h3 style="font-size:1.15rem;font-weight:800;color:var(--color-text);"><?= guru_h($student['username']) ?></h3>
                      <div style="font-size:0.8rem;color:var(--color-muted);"><?= guru_h($student['email']) ?> • <?= (int)$student['total_journals'] ?> Jurnal • Laporan: <?= $student['report_id'] ? ucfirst($student['report_status']) : 'Belum Submit' ?></div>
                    </div>
                  </div>
                  <div style="display:flex;align-items:center;gap:0.75rem;">
                    <span class="status-pill <?= $prog['is_complete'] ? 'disetujui' : 'menunggu' ?>">
                      <?= $prog['is_complete'] ? pkl_icon('check', '', 12) . ' ' : '' ?><?= guru_h($prog['stage']) ?>
                    </span>
                    <button type="button" class="btn-action-sm" onclick="openAssessmentModal(<?= (int)$student['id'] ?>, '<?= guru_h(addslashes($student['username'])) ?>', <?= (int)($student['score_discipline'] ?? 0) ?>, <?= (int)($student['score_technical'] ?? 0) ?>, <?= (int)($student['score_communication'] ?? 0) ?>, '<?= guru_h(addslashes($student['feedback'] ?? '')) ?>')">
                      <span><?= pkl_icon('penilaian', '', 14) ?></span> Evaluasi Siswa
                    </button>
                  </div>
                </div>

                <!-- Stepper Roadmap Identik Desain Siswa -->
                <div class="journey-stepper-wrap">
                  <div class="stepper-line-bg"></div>
                  <div class="stepper-line-fill" style="width: <?= (int)$stepperFill ?>%;"></div>
                  <div class="journey-stepper-track">
                    <div class="stepper-node <?= guru_stepper_class(1, $cStep) ?>">
                      <div class="stepper-circle"><?= $cStep > 1 ? pkl_icon('check', '', 16) : '1' ?></div>
                      <div class="stepper-title">Pengajuan</div>
                      <div class="stepper-sub"><?= $cStep > 1 ? 'Selesai' : 'Aktif' ?></div>
                    </div>
                    <div class="stepper-node <?= guru_stepper_class(2, $cStep) ?>">
                      <div class="stepper-circle"><?= $cStep > 2 ? pkl_icon('check', '', 16) : '2' ?></div>
                      <div class="stepper-title">Diterima</div>
                      <div class="stepper-sub"><?= $cStep > 2 ? 'Disetujui' : ($cStep === 2 ? 'Aktif' : 'Menunggu') ?></div>
                    </div>
                    <div class="stepper-node <?= guru_stepper_class(3, $cStep) ?>">
                      <div class="stepper-circle"><?= $cStep > 3 ? pkl_icon('check', '', 16) : '3' ?></div>
                      <div class="stepper-title">Mulai PKL</div>
                      <div class="stepper-sub"><?= $cStep > 3 ? 'Selesai' : ($cStep === 3 ? 'Berjalan' : 'Menunggu') ?></div>
                    </div>
                    <div class="stepper-node <?= guru_stepper_class(4, $cStep) ?>">
                      <div class="stepper-circle"><?= $cStep > 4 ? pkl_icon('check', '', 16) : '4' ?></div>
                      <div class="stepper-title">Monitoring</div>
                      <div class="stepper-sub"><?= $cStep > 4 ? 'Selesai' : ($cStep === 4 ? 'Bimbingan' : 'Menunggu') ?></div>
                    </div>
                    <div class="stepper-node <?= guru_stepper_class(5, $cStep) ?>">
                      <div class="stepper-circle"><?= $cStep > 5 ? pkl_icon('check', '', 16) : '5' ?></div>
                      <div class="stepper-title">Laporan</div>
                      <div class="stepper-sub"><?= $cStep > 5 ? 'Disetujui' : ($cStep === 5 ? 'Review' : 'Menunggu') ?></div>
                    </div>
                    <div class="stepper-node <?= $cStep >= 6 ? 'completed active' : '' ?>">
                      <div class="stepper-circle"><?= $cStep >= 6 ? pkl_icon('check', '', 18) : '6' ?></div>
                      <div class="stepper-title">Selesai</div>
                      <div class="stepper-sub"><?= $cStep >= 6 ? 'Lulus PKL' : 'Tahap Akhir' ?></div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5. TAB: PENILAIAN -->
      <div class="content-tab-view" id="section-penilaian" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('penilaian', '', 20) ?></span> Lembar Penilaian &amp; Evaluasi Akhir PKL Siswa
              <span class="sec-badge"><?= (int)$totalStudents ?> Siswa</span>
            </div>
            <button type="button" class="btn-primary" onclick="openAssessmentModal()" style="width:auto;margin-top:0;padding:0.6rem 1.25rem;font-size:0.88rem;">
              <span>+</span> Input Penilaian Baru
            </button>
          </div>

          <p class="card-desc-muted">Penilaian mencakup 3 aspek utama: Kedisiplinan &amp; Sikap, Keterampilan Teknis, serta Komunikasi &amp; Inisiatif:</p>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('penilaian', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Siswa untuk Dinilai</div>
              <p class="empty-state-desc">Daftar siswa bimbingan akan muncul di sini.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Siswa</th>
                    <th>Disiplin (33%)</th>
                    <th>Teknis (33%)</th>
                    <th>Komunikasi (33%)</th>
                    <th>Nilai Akhir</th>
                    <th>Catatan &amp; Umpan Balik</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $student): ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td>
                        <strong><?= guru_h($student['username']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--color-muted);"><?= guru_h($student['email']) ?></div>
                      </td>
                      <td><?= !empty($student['assessment_id']) ? (int)$student['score_discipline'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($student['assessment_id']) ? (int)$student['score_technical'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td><?= !empty($student['assessment_id']) ? (int)$student['score_communication'] : '<span style="color:var(--color-muted);">-</span>' ?></td>
                      <td>
                        <?php if (!empty($student['assessment_id']) && (float)$student['final_score'] > 0): ?>
                          <span class="student-score-badge"><?= number_format((float)$student['final_score'], 1) ?></span>
                        <?php else: ?>
                          <span style="font-size:0.82rem;color:var(--color-muted);">Belum Dinilai</span>
                        <?php endif; ?>
                      </td>
                      <td style="max-width:240px;font-size:0.85rem;color:var(--color-muted);">
                        <?= !empty($student['feedback']) ? guru_h(guru_excerpt($student['feedback'], 70)) : '<em>Tidak ada catatan</em>' ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm" onclick="openAssessmentModal(<?= (int)$student['id'] ?>, '<?= guru_h(addslashes($student['username'])) ?>', <?= (int)($student['score_discipline'] ?? 0) ?>, <?= (int)($student['score_technical'] ?? 0) ?>, <?= (int)($student['score_communication'] ?? 0) ?>, '<?= guru_h(addslashes($student['feedback'] ?? '')) ?>')">
                          <span><?= pkl_icon('edit', '', 14) ?></span> <?= !empty($student['assessment_id']) ? 'Edit Nilai' : 'Beri Nilai' ?>
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

      <!-- 6. TAB: LAPORAN PKL -->
      <div class="content-tab-view" id="section-laporan" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('laporan', '', 20) ?></span> Verifikasi Laporan Akhir Praktik Kerja Lapangan
              <span class="sec-badge"><?= (int)$totalReports ?> Laporan</span>
            </div>
          </div>

          <?php if (empty($allReports)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('laporan', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Laporan PKL yang Dikirim</div>
              <p class="empty-state-desc">Laporan akhir yang dikirimkan oleh siswa bimbingan akan ditampilkan di sini untuk ditinjau dan disetujui.</p>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:1.25rem;">
              <?php foreach ($allReports as $report): ?>
                <div class="info-box" style="padding:1.5rem;">
                  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                    <div>
                      <div class="info-box-label">Siswa: <?= guru_h($report['student_name']) ?> (<?= guru_h($report['student_email']) ?>)</div>
                      <h3 style="font-size:1.2rem;font-weight:800;color:var(--color-text);margin-top:0.2rem;"><?= guru_h($report['title']) ?></h3>
                      <div style="font-size:0.8rem;color:var(--color-muted);margin-top:0.15rem;">
                        Tanggal Pengiriman: <?= date('d F Y', strtotime($report['submission_date'])) ?>
                      </div>
                    </div>
                    <span class="status-pill <?= guru_h(strtolower($report['status'])) ?>" style="display:inline-flex;align-items:center;gap:3px;">
                      <?= $report['status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Dikirim' ?>
                    </span>
                  </div>

                  <div style="background:var(--color-cream);border:1px solid var(--color-border);padding:1rem;border-radius:var(--radius-md);margin-bottom:1rem;font-size:0.92rem;line-height:1.6;">
                    <div style="font-weight:700;font-size:0.82rem;color:var(--color-muted);margin-bottom:0.35rem;text-transform:uppercase;">Ringkasan / Abstrak Laporan:</div>
                    <?= nl2br(guru_h($report['summary'])) ?>
                  </div>

                  <div style="display:flex;align-items:center;gap:0.5rem;justify-content:flex-end;">
                    <form method="POST" action="guru.php" style="display:inline;">
                      <input type="hidden" name="action" value="update_report_status">
                      <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                      <input type="hidden" name="status" value="disetujui">
                      <button type="submit" class="btn-action-sm approve" style="padding:0.5rem 1rem;font-size:0.85rem;display:inline-flex;align-items:center;gap:4px;">
                        <span><?= pkl_icon('check', '', 13) ?></span> Setujui Laporan PKL
                      </button>
                    </form>
                    <form method="POST" action="guru.php" style="display:inline;">
                      <input type="hidden" name="action" value="update_report_status">
                      <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                      <input type="hidden" name="status" value="draft">
                      <button type="submit" class="btn-action-sm revise" style="padding:0.5rem 1rem;font-size:0.85rem;display:inline-flex;align-items:center;gap:4px;">
                        <span><?= pkl_icon('alert', '', 13) ?></span> Minta Revisi Laporan
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Input / Edit Penilaian -->
  <div class="modal-backdrop" id="assessment-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('penilaian', '', 20) ?> Form Penilaian Siswa PKL</div>
        <button type="button" class="modal-close-btn" id="btn-close-assessment-modal">&times;</button>
      </div>

      <form method="POST" action="guru.php" id="form-assessment">
        <input type="hidden" name="action" value="save_assessment">

        <div class="form-group">
          <label class="form-label" for="select_student_id">Pilih Siswa Bimbingan</label>
          <select name="student_id" id="select_student_id" class="form-control" required>
            <option value="">-- Pilih Siswa --</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>">
                <?= guru_h($st['username']) ?> (<?= guru_h($st['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="score_discipline">1. Nilai Kedisiplinan &amp; Sikap (0 - 100)</label>
          <input type="number" name="score_discipline" id="score_discipline" class="form-control" min="0" max="100" placeholder="Contoh: 88" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_technical">2. Nilai Keterampilan Teknis (0 - 100)</label>
          <input type="number" name="score_technical" id="score_technical" class="form-control" min="0" max="100" placeholder="Contoh: 90" required oninput="calcFinalScore()">
        </div>

        <div class="form-group">
          <label class="form-label" for="score_communication">3. Nilai Komunikasi &amp; Inisiatif (0 - 100)</label>
          <input type="number" name="score_communication" id="score_communication" class="form-control" min="0" max="100" placeholder="Contoh: 85" required oninput="calcFinalScore()">
        </div>

        <div class="info-box" style="background:var(--color-cream);margin-bottom:1.15rem;padding:0.85rem;">
          <div class="info-box-label">Estimasi Nilai Akhir (Rata-rata)</div>
          <div class="info-box-val" id="preview_final_score" style="color:var(--color-orange);">0.0</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="feedback">Catatan Evaluasi &amp; Umpan Balik Guru Pembimbing</label>
          <textarea name="feedback" id="feedback" class="form-control" rows="3" placeholder="Tuliskan apresiasi, saran pengembangan kompetensi, atau evaluasi selama PKL..."></textarea>
        </div>

        <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
          <button type="button" class="btn-primary" id="btn-cancel-assessment-modal" style="width:auto;background:transparent;color:var(--color-muted);border:1.5px solid var(--color-border);box-shadow:none;">Batal</button>
          <button type="submit" class="btn-primary" style="margin-top:0;flex:1;">
            <span><?= pkl_icon('save', '', 18) ?></span> Simpan Penilaian
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Tab switching logic
    function switchTab(targetId) {
      document.querySelectorAll('.content-tab-view').forEach(function (view) {
        view.hidden = true;
      });

      var activeView = document.getElementById(targetId);
      if (activeView) {
        activeView.hidden = false;
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

    // Mobile menu toggle
    var mobileToggle = document.getElementById('mobile-menu-toggle');
    var sidebar = document.getElementById('app-sidebar');
    if (mobileToggle && sidebar) {
      mobileToggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
      });
    }

    // Modal Penilaian
    var assessModal = document.getElementById('assessment-modal');

    function openAssessmentModal(studentId, studentName, sDisc, sTech, sComm, feedbackText) {
      if (!assessModal) return;
      
      var selectEl = document.getElementById('select_student_id');
      var discEl = document.getElementById('score_discipline');
      var techEl = document.getElementById('score_technical');
      var commEl = document.getElementById('score_communication');
      var feedEl = document.getElementById('feedback');

      if (studentId && selectEl) {
        selectEl.value = studentId;
      }
      if (discEl) discEl.value = sDisc || '';
      if (techEl) techEl.value = sTech || '';
      if (commEl) commEl.value = sComm || '';
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

    // Hitung Live Nilai Akhir
    function calcFinalScore() {
      var d = parseFloat(document.getElementById('score_discipline').value) || 0;
      var t = parseFloat(document.getElementById('score_technical').value) || 0;
      var c = parseFloat(document.getElementById('score_communication').value) || 0;

      var finalAvg = (d + t + c) / 3;
      var preview = document.getElementById('preview_final_score');
      if (preview) {
        preview.textContent = finalAvg.toFixed(1);
      }
    }

    // Filter Jurnal
    function filterJournals(status, btnEl) {
      document.querySelectorAll('.filter-pill').forEach(function(pill) {
        pill.classList.remove('active');
      });
      if (btnEl) btnEl.classList.add('active');

      var rows = document.querySelectorAll('.journal-row');
      rows.forEach(function(row) {
        if (status === 'all') {
          row.style.display = '';
        } else {
          var rowStatus = row.getAttribute('data-status');
          row.style.display = (rowStatus === status) ? '' : 'none';
        }
      });
    }
  </script>
</body>
</html>
