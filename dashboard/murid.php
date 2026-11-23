<?php
// dashboard/murid.php
// Panel Dashboard Siswa / Murid PKL Journey

require_once __DIR__ . '/../auth/check_murid.php';
require_once __DIR__ . '/../includes/icons.php';

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
  // Abaikan jika tabel sudah siap
}

$alertMessage = '';
$alertType = '';

// 1. Aksi: Wajib Ganti Password Pertama Kali (Akun Hasil Import Excel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_initial_password') {
  $new_password = $_POST['new_password'] ?? '';
  $confirm_new_password = $_POST['confirm_new_password'] ?? '';

  if ($new_password === '' || $confirm_new_password === '') {
    $alertMessage = 'Password baru dan konfirmasi password wajib diisi.';
    $alertType = 'error';
  } elseif (strlen($new_password) < 6) {
    $alertMessage = 'Password baru minimal harus 6 karakter.';
    $alertType = 'error';
  } elseif ($new_password !== $confirm_new_password) {
    $alertMessage = 'Konfirmasi password baru tidak cocok.';
    $alertType = 'error';
  } elseif ($new_password === $murid_username) {
    $alertMessage = 'Password baru tidak boleh sama dengan NIS Anda.';
    $alertType = 'error';
  } else {
    try {
      $hashed = password_hash($new_password, PASSWORD_DEFAULT);
      $stmtUpdate = $pdo->prepare("UPDATE users SET password = :pwd, must_change_password = 0 WHERE id = :id");
      $stmtUpdate->execute([':pwd' => $hashed, ':id' => $murid_id]);

      $_SESSION['must_change_password'] = 0;
      $murid_must_change_password = 0;

      $alertMessage = 'Password berhasil diperbarui! Selamat datang di PKL Journey.';
      $alertType = 'success';
    } catch (Exception $e) {
      $alertMessage = 'Gagal memperbarui password: ' . $e->getMessage();
      $alertType = 'error';
    }
  }
}

// Blokir aktivitas lain jika belum mengganti password awal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'change_initial_password' && $murid_must_change_password === 1) {
  $alertMessage = 'Anda wajib mengganti password awal (NIS) terlebih dahulu sebelum dapat melakukan aktivitas PKL.';
  $alertType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_journal') {
  $title = trim($_POST['title'] ?? '');
  $activity_date = trim($_POST['activity_date'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($title === '' || $activity_date === '' || $description === '') {
    $alertMessage = 'Mohon lengkapi seluruh form jurnal kegiatan harian.';
    $alertType = 'error';
  } else {
    try {
      $stmtInsert = $pdo->prepare("
                INSERT INTO pkl_journals (student_id, title, activity_date, description, status)
                VALUES (:student_id, :title, :activity_date, :description, 'menunggu')
            ");
      $stmtInsert->execute([
        ':student_id' => $murid_id,
        ':title' => $title,
        ':activity_date' => $activity_date,
        ':description' => $description,
      ]);
      $alertMessage = 'Jurnal harian berhasil dicatat dan dikirim untuk verifikasi guru pembimbing! ✓';
      $alertType = 'success';
    } catch (Exception $e) {
      $alertMessage = 'Gagal menyimpan jurnal: ' . $e->getMessage();
      $alertType = 'error';
    }
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
  $report_title = trim($_POST['report_title'] ?? '');
  $summary = trim($_POST['summary'] ?? '');
  $submission_date = date('Y-m-d');

  if ($report_title === '' || $summary === '') {
    $alertMessage = 'Judul laporan dan ringkasan wajib diisi.';
    $alertType = 'error';
  } else {
    try {
      $stmtCheckRep = $pdo->prepare('SELECT id FROM pkl_reports WHERE student_id = :sid LIMIT 1');
      $stmtCheckRep->execute([':sid' => $murid_id]);
      $existingReport = $stmtCheckRep->fetch();

      if ($existingReport) {
        $stmtUpRep = $pdo->prepare("
                    UPDATE pkl_reports
                    SET title = :title, summary = :summary, submission_date = :sdate, status = 'dikirim'
                    WHERE id = :id AND student_id = :sid
                ");
        $stmtUpRep->execute([
          ':title' => $report_title,
          ':summary' => $summary,
          ':sdate' => $submission_date,
          ':id' => $existingReport['id'],
          ':sid' => $murid_id,
        ]);
      } else {
        $stmtInsRep = $pdo->prepare("
                    INSERT INTO pkl_reports (student_id, title, summary, submission_date, status)
                    VALUES (:sid, :title, :summary, :sdate, 'dikirim')
                ");
        $stmtInsRep->execute([
          ':sid' => $murid_id,
          ':title' => $report_title,
          ':summary' => $summary,
          ':sdate' => $submission_date,
        ]);
      }
      $alertMessage = 'Laporan PKL berhasil dikirim ke guru pembimbing! ✓';
      $alertType = 'success';
    } catch (Exception $e) {
      $alertMessage = 'Gagal menyimpan laporan: ' . $e->getMessage();
      $alertType = 'error';
    }
  }
}

$stmtJournals = $pdo->prepare("
    SELECT * FROM pkl_journals
    WHERE student_id = :sid
    ORDER BY activity_date DESC, id DESC
");
$stmtJournals->execute([':sid' => $murid_id]);
$allJournals = $stmtJournals->fetchAll();

$totalJournals = count($allJournals);
$approvedCount = 0;
$pendingCount = 0;
$revisionCount = 0;

foreach ($allJournals as $j) {
  if ($j['status'] === 'disetujui') {
    $approvedCount++;
  } elseif ($j['status'] === 'menunggu') {
    $pendingCount++;
  } elseif ($j['status'] === 'revisi') {
    $revisionCount++;
  }
}

$stmtReport = $pdo->prepare("
    SELECT * FROM pkl_reports
    WHERE student_id = :sid
    ORDER BY id DESC LIMIT 1
");
$stmtReport->execute([':sid' => $murid_id]);
$reportData = $stmtReport->fetch();

$stmtAssessment = $pdo->prepare("
    SELECT a.*, u.username AS guru_name, u.email AS guru_email
    FROM pkl_assessments a
    LEFT JOIN users u ON a.guru_id = u.id
    WHERE a.student_id = :sid
    LIMIT 1
");
$stmtAssessment->execute([':sid' => $murid_id]);
$assessmentData = $stmtAssessment->fetch();

$stmtIndustryAssessment = $pdo->prepare("
    SELECT ia.*, u.username AS industri_name, u.email AS industri_email
    FROM industry_assessments ia
    JOIN internships i ON i.student_id = ia.student_id AND i.industri_id = ia.industri_id
    LEFT JOIN users u ON ia.industri_id = u.id
    WHERE ia.student_id = :sid
    LIMIT 1
");
$stmtIndustryAssessment->execute([':sid' => $murid_id]);
$industryAssessmentData = $stmtIndustryAssessment->fetch();

$stmtSidangAssessment = $pdo->prepare("
    SELECT sa.*, u.username AS penguji_name, u.email AS penguji_email
    FROM sidang_assessments sa
    JOIN internships i ON i.student_id = sa.student_id AND i.penguji_id = sa.penguji_id
    LEFT JOIN users u ON sa.penguji_id = u.id
    WHERE sa.student_id = :sid
    LIMIT 1
");
$stmtSidangAssessment->execute([':sid' => $murid_id]);
$sidangAssessmentData = $stmtSidangAssessment->fetch();

// Penilaian Penguji Produktif (5 kriteria)
$stmtProduktifAssessment = $pdo->prepare("
    SELECT pa.*, u.username AS penguji_name
    FROM produktif_assessments pa
    JOIN examiner_assignments ea ON ea.student_id = pa.student_id
        AND ea.penguji_id = pa.penguji_id AND ea.category = 'produktif'
    LEFT JOIN users u ON pa.penguji_id = u.id
    WHERE pa.student_id = :sid
    LIMIT 1
");
$stmtProduktifAssessment->execute([':sid' => $murid_id]);
$produktifAssessmentData = $stmtProduktifAssessment->fetch();

// Penilaian Penguji Jurnal (5 kriteria)
$stmtJurnalAssessment = $pdo->prepare("
    SELECT ja.*, u.username AS penguji_name
    FROM jurnal_assessments ja
    JOIN examiner_assignments ea ON ea.student_id = ja.student_id
        AND ea.penguji_id = ja.penguji_id AND ea.category = 'jurnal'
    LEFT JOIN users u ON ja.penguji_id = u.id
    WHERE ja.student_id = :sid
    LIMIT 1
");
$stmtJurnalAssessment->execute([':sid' => $murid_id]);
$jurnalAssessmentData = $stmtJurnalAssessment->fetch();

// Nama penguji yang ditugaskan (untuk pesan "menunggu")
$stmtExaminerAssigned = $pdo->prepare("
    SELECT ea.category, u.username AS penguji_name
    FROM examiner_assignments ea
    JOIN users u ON ea.penguji_id = u.id
    WHERE ea.student_id = :sid
");
$stmtExaminerAssigned->execute([':sid' => $murid_id]);
$examinerAssignedMap = [];
foreach ($stmtExaminerAssigned->fetchAll() as $row) {
    $examinerAssignedMap[$row['category']] = $row['penguji_name'];
}

$stmtInternship = $pdo->prepare("
    SELECT i.*, 
           g.username AS guru_name, g.email AS guru_email,
           ind.username AS industri_name, ind.email AS industri_email,
           pj.username AS penguji_name, pj.email AS penguji_email,
           c.name AS company_name, c.address AS company_address
    FROM internships i
    LEFT JOIN users g ON i.guru_id = g.id
    LEFT JOIN users ind ON i.industri_id = ind.id
    LEFT JOIN users pj ON i.penguji_id = pj.id
    LEFT JOIN companies c ON i.company_id = c.id
    WHERE i.student_id = :sid
    LIMIT 1
");
$stmtInternship->execute([':sid' => $murid_id]);
$internshipData = $stmtInternship->fetch();

$tempatPKL = 'PT Telkom Indonesia (Witel Bekasi)';
if ($internshipData && !empty($internshipData['company_name'])) {
  $tempatPKL = $internshipData['company_name'];
} elseif (!empty($allJournals)) {
  if (stripos($allJournals[0]['description'], 'telkom') !== false) {
    $tempatPKL = 'PT Telkom Indonesia (Divisi FTTH)';
  } elseif (stripos($allJournals[0]['description'], 'jaringan') !== false) {
    $tempatPKL = 'PT Mitra Jaringan Nusantara';
  }
}

$currentStep = 3;
$progressPercent = 50;

if ($assessmentData && (float) $assessmentData['final_score'] > 0) {
  $currentStep = 6;
  $progressPercent = 100;
} elseif ($reportData && $reportData['status'] === 'disetujui') {
  $currentStep = 6;
  $progressPercent = 90;
} elseif ($reportData && $reportData['status'] === 'dikirim') {
  $currentStep = 5;
  $progressPercent = 75;
} elseif ($approvedCount >= 1) {
  $currentStep = 4;
  $progressPercent = 60;
} elseif ($totalJournals >= 1) {
  $currentStep = 3;
  $progressPercent = 45;
} else {
  $currentStep = 3;
  $progressPercent = 35;
}

$statusPKL = ($currentStep >= 6) ? 'Selesai PKL' : 'Aktif Berjalan';
$stepperFill = max(0, min(100, (($currentStep - 1) / 5) * 84));

function murid_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function murid_excerpt($text, $max = 100)
{
  $text = (string) $text;
  if (function_exists('mb_strimwidth')) {
    return mb_strimwidth($text, 0, $max, '...');
  }
  if (strlen($text) <= $max) {
    return $text;
  }
  return substr($text, 0, $max) . '...';
}

function murid_journal_status_label($status)
{
  $status = strtolower((string) $status);
  if ($status === 'disetujui') {
    return pkl_icon('check', '', 12) . ' Disetujui';
  }
  if ($status === 'menunggu') {
    return pkl_icon('clock', '', 12) . ' Menunggu';
  }
  return pkl_icon('alert', '', 12) . ' Revisi';
}

function murid_stepper_class($step, $currentStep)
{
  if ($currentStep >= $step) {
    return $currentStep > $step ? 'completed' : 'active';
  }
  return '';
}

function murid_step_sub($step, $currentStep, $doneText, $activeText, $waitText)
{
  if ($currentStep > $step) {
    return $doneText;
  }
  if ($currentStep == $step) {
    return $activeText;
  }
  return $waitText;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Siswa - PKL Journey</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body">

  <div class="dashboard-layout">
    <aside class="app-sidebar" id="app-sidebar">
      <div class="sidebar-header">
        <a href="murid.php" class="sidebar-brand">
          <span class="brand-logo-icon"><?= pkl_logo(34) ?></span>
          <span>PKL <span>Journey</span></span>
        </a>
        <div class="sidebar-user-card">
          <div class="user-avatar-circle"><?= strtoupper(substr($murid_nama !== '' ? $murid_nama : $murid_username, 0, 1)) ?></div>
          <div class="user-meta">
            <div class="user-meta-name" title="<?= murid_h($murid_nama !== '' ? $murid_nama : $murid_username) ?>"><?= murid_h($murid_nama !== '' ? $murid_nama : $murid_username) ?></div>
            <div class="user-meta-sub" style="font-size:0.75rem;color:var(--color-muted);">
              NIS: <?= murid_h($murid_username) ?><?php if (!empty($murid_kelas)): ?> • <?= murid_h($murid_kelas) ?><?php endif; ?>
            </div>
            <div class="user-meta-school" title="<?= murid_h($murid_school_name) ?>"><?= murid_h($murid_school_name) ?></div>
          </div>
        </div>
      </div>

      <nav class="sidebar-menu">
        <button type="button" class="nav-link-item active" data-target="section-dashboard" id="nav-dashboard">
          <span class="nav-icon"><?= pkl_icon('dashboard') ?></span>
          <span>Dashboard</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-jurnal" id="nav-jurnal">
          <span class="nav-icon"><?= pkl_icon('jurnal') ?></span>
          <span>Jurnal PKL</span>
        </button>
        <button type="button" class="nav-link-item" data-target="section-journey" id="nav-journey">
          <span class="nav-icon"><?= pkl_icon('journey') ?></span>
          <span>Progress / Journey</span>
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

    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="topbar-title-wrap">
          <h1 class="topbar-greeting">Halo, <span><?= murid_h($murid_nama !== '' ? $murid_nama : $murid_username) ?></span>!</h1>
          <p class="topbar-sub">
            NIS: <strong><?= murid_h($murid_username) ?></strong>
            <?php if (!empty($murid_kelas)): ?> • Kelas: <strong><?= murid_h($murid_kelas) ?></strong><?php endif; ?>
            <?php if (!empty($murid_jurusan)): ?> • Jurusan: <strong><?= murid_h($murid_jurusan) ?></strong><?php endif; ?>
            • <?= murid_h($murid_school_name) ?>
          </p>
        </div>
        <div class="topbar-actions">
          <button type="button" class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <button type="button" class="btn-primary" id="btn-open-journal-modal"
            style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
            <span><?= pkl_icon('plus', '', 18) ?></span> Catat Jurnal Baru
          </button>
        </div>
      </header>

      <?php if ($alertMessage !== ''): ?>
        <div class="status-box <?= $alertType === 'success' ? 'success' : 'error' ?>">
          <?= murid_h($alertMessage) ?>
        </div>
      <?php endif; ?>

      <div class="content-tab-view" id="section-dashboard">
        <div class="stats-row">
          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Status PKL</div>
              <div class="stat-value"><?= murid_h($statusPKL) ?></div>
              <div class="stat-sub">Tahap ke-<?= (int) $currentStep ?> dari 6 Milestone</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('journey', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Progress PKL</div>
              <div class="stat-value"><?= (int) $progressPercent ?>%</div>
              <div class="stat-progress-bar-bg">
                <div class="stat-progress-bar-fill" style="width: <?= (int) $progressPercent ?>%;"></div>
              </div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('check', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Tempat PKL</div>
              <div class="stat-value" title="<?= murid_h($tempatPKL) ?>"><?= murid_h($tempatPKL) ?></div>
              <div class="stat-sub">Mitra Industri (DUDI)</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('perusahaan', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Jumlah Jurnal</div>
              <div class="stat-value"><?= (int) $totalJournals ?> Entri</div>
              <div class="stat-sub">
                <?= (int) $approvedCount ?> Disetujui • <?= (int) $pendingCount ?> Menunggu
              </div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('jurnal', '', 24) ?></div>
          </div>
        </div>

        <!-- Panel Profil & Identitas Siswa PKL (Otomatis dari Database) -->
        <div class="section-panel" style="margin-bottom: 1.5rem;">
          <div class="section-panel-header" style="margin-bottom:0.75rem;">
            <div class="section-panel-title">
              <span><?= pkl_icon('siswa', '', 20) ?></span> Profil &amp; Identitas Akademik Siswa
              <span class="sec-badge">Data Resmi Sekolah</span>
            </div>
          </div>
          <div class="info-grid">
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('profil', '', 16) ?> Nama Lengkap Siswa</div>
              <div class="info-box-val" style="font-size:1.05rem;font-weight:700;"><?= murid_h($murid_nama !== '' ? $murid_nama : '-') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);">NIS: <?= murid_h($murid_username) ?></div>
            </div>
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('siswa', '', 16) ?> Kelas &amp; Rombel</div>
              <div class="info-box-val" style="font-size:1.05rem;font-weight:700;"><?= murid_h($murid_kelas !== '' ? $murid_kelas : '-') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Tingkat Kelas</div>
            </div>
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('journey', '', 16) ?> Jurusan / Program Keahlian</div>
              <div class="info-box-val" style="font-size:1.05rem;font-weight:700;"><?= murid_h($murid_jurusan !== '' ? $murid_jurusan : '-') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);"><?= murid_h($murid_school_name) ?></div>
            </div>
          </div>
        </div>

        <!-- Panel Tim Pembimbing & Penguji PKL -->
        <div class="section-panel" style="margin-bottom: 1.5rem;">
          <div class="section-panel-header" style="margin-bottom:0.75rem;">
            <div class="section-panel-title">
              <span><?= pkl_icon('pengguna', '', 20) ?></span> Tim Pembimbing &amp; Penguji PKL Anda
              <span class="sec-badge">Penugasan Resmi</span>
            </div>
            <button type="button" class="btn-action-sm primary" onclick="switchTab('section-penilaian')">
              <span><?= pkl_icon('penilaian', '', 16) ?></span> Lihat Nilai Evaluasi
            </button>
          </div>
          <div class="info-grid">
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('pembimbing_sekolah', '', 16) ?> Guru Pembimbing Sekolah</div>
              <div class="info-box-val" style="font-size:0.98rem;"><?= murid_h($internshipData['guru_name'] ?? 'Belum Ditugaskan') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);"><?= murid_h($internshipData['guru_email'] ?? '-') ?></div>
            </div>
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('pembimbing_industri', '', 16) ?> Guru Pembimbing Industri (DUDI)</div>
              <div class="info-box-val" style="font-size:0.98rem;"><?= murid_h($internshipData['industri_name'] ?? 'Belum Ditugaskan') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);"><?= murid_h($internshipData['industri_email'] ?? '-') ?></div>
            </div>
            <div class="info-box">
              <div class="info-box-label"><?= pkl_icon('sidang', '', 16) ?> Penguji Sidang PKL</div>
              <div class="info-box-val" style="font-size:0.98rem;"><?= murid_h($internshipData['penguji_name'] ?? 'Belum Ditugaskan') ?></div>
              <div style="font-size:0.75rem;color:var(--color-muted);"><?= murid_h($internshipData['penguji_email'] ?? '-') ?></div>
            </div>
          </div>
        </div>

        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('journey', '', 20) ?></span> Peta Perjalanan PKL
              <span class="sec-badge">Journey Roadmap</span>
            </div>
            <span>Tahap Aktif: <?= (int) $currentStep ?> / 6</span>
          </div>

          <div class="journey-stepper-wrap">
            <div class="stepper-line-bg"></div>
            <div class="stepper-line-fill" style="width: <?= (int) $stepperFill ?>%;"></div>
            <div class="journey-stepper-track">
              <div class="stepper-node <?= murid_stepper_class(1, $currentStep) ?>">
                <div class="stepper-circle"><?= $currentStep > 1 ? pkl_icon('check', '', 16) : '1' ?></div>
                <div class="stepper-title">Pengajuan</div>
                <div class="stepper-sub"><?= $currentStep > 1 ? 'Selesai' : 'Sedang Diproses' ?></div>
              </div>
              <div class="stepper-node <?= murid_stepper_class(2, $currentStep) ?>">
                <div class="stepper-circle"><?= $currentStep > 2 ? pkl_icon('check', '', 16) : '2' ?></div>
                <div class="stepper-title">Diterima</div>
                <div class="stepper-sub">
                  <?= murid_h(murid_step_sub(2, $currentStep, 'Disetujui', 'Aktif', 'Menunggu')) ?></div>
              </div>
              <div class="stepper-node <?= murid_stepper_class(3, $currentStep) ?>">
                <div class="stepper-circle"><?= $currentStep > 3 ? pkl_icon('check', '', 16) : '3' ?></div>
                <div class="stepper-title">Mulai PKL</div>
                <div class="stepper-sub">
                  <?= murid_h(murid_step_sub(3, $currentStep, 'Selesai', 'Sedang Berjalan', 'Menunggu')) ?></div>
              </div>
              <div class="stepper-node <?= murid_stepper_class(4, $currentStep) ?>">
                <div class="stepper-circle"><?= $currentStep > 4 ? pkl_icon('check', '', 16) : '4' ?></div>
                <div class="stepper-title">Monitoring</div>
                <div class="stepper-sub">
                  <?= murid_h(murid_step_sub(4, $currentStep, 'Selesai', 'Bimbingan Guru', 'Menunggu')) ?></div>
              </div>
              <div class="stepper-node <?= murid_stepper_class(5, $currentStep) ?>">
                <div class="stepper-circle"><?= $currentStep > 5 ? pkl_icon('check', '', 16) : '5' ?></div>
                <div class="stepper-title">Laporan</div>
                <div class="stepper-sub">
                  <?= murid_h(murid_step_sub(5, $currentStep, 'Disetujui', 'Penyusunan', 'Menunggu')) ?></div>
              </div>
              <div class="stepper-node <?= $currentStep >= 6 ? 'completed active' : '' ?>">
                <div class="stepper-circle"><?= $currentStep >= 6 ? pkl_icon('check', '', 18) : '6' ?></div>
                <div class="stepper-title">Selesai</div>
                <div class="stepper-sub"><?= $currentStep >= 6 ? 'Lulus PKL' : 'Tahap Akhir' ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Jurnal Kegiatan Terbaru
              <span class="sec-badge"><?= (int) $totalJournals ?> Catatan</span>
            </div>
            <button type="button" class="btn-primary" onclick="switchTab('section-jurnal')"
              style="width:auto;margin-top:0;padding:0.45rem 1rem;font-size:0.82rem;background:transparent;color:var(--color-accent);border:1.5px solid var(--color-accent);box-shadow:none;">
              Lihat Semua Jurnal →
            </button>
          </div>

          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 48) ?></div>
              <div class="empty-state-title">Belum Ada Jurnal Kegiatan</div>
              <p class="empty-state-desc">Mulailah mencatat kegiatan harian PKL Anda secara berkala untuk dinilai guru
                pembimbing.</p>
              <button type="button" class="btn-primary" id="btn-empty-add-journal"
                style="display:inline-flex;width:auto;margin:auto;">+ Tambah Jurnal Pertama</button>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Aktivitas</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($allJournals, 0, 5) as $journal): ?>
                    <?php $statusClass = strtolower($journal['status']); ?>
                    <tr>
                      <td><?= date('d M Y', strtotime($journal['activity_date'])) ?></td>
                      <td><?= murid_h($journal['title']) ?></td>
                      <td><?= murid_h(murid_excerpt($journal['description'], 100)) ?></td>
                      <td>
                        <span class="status-pill <?= murid_h($statusClass) ?>">
                          <?= murid_journal_status_label($statusClass) ?>
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

      <div class="content-tab-view" id="section-jurnal" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Seluruh Jurnal Kegiatan PKL
              <span class="sec-badge"><?= (int) $totalJournals ?> Total</span>
            </div>
            <button type="button" class="btn-primary" id="btn-open-journal-modal-2"
              style="width:auto;margin-top:0;padding:0.6rem 1.25rem;font-size:0.88rem;">
              <span><?= pkl_icon('plus', '', 18) ?></span> Tambah Jurnal Harian
            </button>
          </div>

          <?php if (empty($allJournals)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('jurnal', '', 48) ?></div>
              <div class="empty-state-title">Belum ada jurnal yang tercatat</div>
              <p class="empty-state-desc">Klik tombol di atas untuk membuat catatan kegiatan harian Anda.</p>
            </div>
          <?php else: ?>
            <div>
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Judul Kegiatan</th>
                    <th>Deskripsi Lengkap</th>
                    <th>Status Verifikasi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1;
                  foreach ($allJournals as $journal): ?>
                    <?php $statusClass = strtolower($journal['status']); ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td><?= date('d M Y', strtotime($journal['activity_date'])) ?></td>
                      <td><?= murid_h($journal['title']) ?></td>
                      <td><?= nl2br(murid_h($journal['description'])) ?></td>
                      <td>
                        <span class="status-pill <?= murid_h($statusClass) ?>">
                          <?= murid_journal_status_label($statusClass) ?>
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

      <div class="content-tab-view" id="section-journey" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('journey', '', 20) ?></span> Detail Tahapan Journey PKL
              <span class="sec-badge"><?= (int) $progressPercent ?>% Selesai</span>
            </div>
          </div>

          <p class="card-desc-muted">Berikut adalah alur milestone perjalanan praktik kerja lapangan siswa mulai dari
            pengajuan hingga evaluasi kelulusan akhir:</p>

          <div class="info-box">
            <div class="info-box-label">1. Pengajuan Tempat PKL</div>
            <div class="info-box-val">Pemilihan sekolah mitra, penyerahan surat permohonan PKL ke instansi.</div>
            <span class="status-pill disetujui" style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('check', '', 12) ?> Selesai</span>
          </div>
          <div class="info-box">
            <div class="info-box-label">2. Diterima di Industri (DUDI)</div>
            <div class="info-box-val">Konfirmasi penerimaan dari mitra industri: <?= murid_h($tempatPKL) ?>.</div>
            <span class="status-pill disetujui" style="display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('check', '', 12) ?> Selesai</span>
          </div>
          <div class="info-box">
            <div class="info-box-label">3. Mulai Praktik Kerja Lapangan</div>
            <div class="info-box-val">Penugasan divisi teknis, orientasi lingkungan kerja, dan pengisian jurnal harian.
            </div>
            <span class="status-pill <?= $currentStep >= 3 ? 'disetujui' : 'menunggu' ?>" style="display:inline-flex;align-items:center;gap:4px;">
              <?= $currentStep > 3 ? pkl_icon('check', '', 12) . ' Selesai' : 'Aktif Berjalan' ?>
            </span>
          </div>
          <div class="info-box">
            <div class="info-box-label">4. Monitoring &amp; Bimbingan Guru</div>
            <div class="info-box-val">Kunjungan guru pembimbing, review jurnal kegiatan harian, dan verifikasi progres
              kompetensi.</div>
            <span class="status-pill <?= $currentStep >= 4 ? 'disetujui' : 'menunggu' ?>">
              <?= $currentStep >= 4 ? 'Sedang Berlangsung' : 'Tahap Berikutnya' ?>
            </span>
          </div>
          <div class="info-box">
            <div class="info-box-label">5. Laporan Akhir PKL</div>
            <div class="info-box-val">Penyusunan ringkasan hasil kegiatan dan penyerahan dokumentasi laporan PKL.</div>
            <span class="status-pill <?= $reportData ? 'dikirim' : 'menunggu' ?>" style="display:inline-flex;align-items:center;gap:4px;">
              <?= $reportData ? ($reportData['status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Laporan Dikirim') : 'Belum Submit' ?>
            </span>
          </div>
          <div class="info-box">
            <div class="info-box-label">6. Penilaian Akhir &amp; Sertifikasi PKL</div>
            <div class="info-box-val">Pemberian nilai disiplin, teknis, dan komunikasi oleh guru pembimbing.</div>
            <span class="status-pill <?= $assessmentData ? 'disetujui' : 'menunggu' ?>" style="display:inline-flex;align-items:center;gap:4px;">
              <?= $assessmentData ? pkl_icon('check', '', 12) . ' Dinilai' : 'Menunggu Penilaian' ?>
            </span>
          </div>
        </div>
      </div>

      <div class="content-tab-view" id="section-penilaian" hidden>
        <!-- 1. Penilaian Pembimbing Sekolah -->
        <div class="section-panel" style="margin-bottom: 1.5rem;">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('pembimbing_sekolah', '', 20) ?></span> Penilaian Guru Pembimbing Sekolah
              <span class="sec-badge"><?= $assessmentData ? 'Sudah Dinilai' : 'Menunggu' ?></span>
            </div>
          </div>

          <?php if (!$assessmentData): ?>
            <div class="empty-state" style="padding: 1.5rem 1rem;">
              <div class="empty-state-icon"><?= pkl_icon('clock', '', 42) ?></div>
              <div class="empty-state-title">Belum Ada Penilaian Guru Sekolah</div>
              <p class="empty-state-desc">Guru pembimbing sekolah (<?= murid_h($internshipData['guru_name'] ?? 'Belum Ditugaskan') ?>) akan memberikan nilai setelah pemantauan jurnal kegiatan PKL Anda.</p>
            </div>
          <?php else: ?>
            <div class="info-grid">
              <div class="info-box">
                <div class="info-box-label">Disiplin &amp; Sikap</div>
                <div class="info-box-val"><?= (int) $assessmentData['score_discipline'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Keterampilan Teknis</div>
                <div class="info-box-val"><?= (int) $assessmentData['score_technical'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Komunikasi &amp; Inisiatif</div>
                <div class="info-box-val"><?= (int) $assessmentData['score_communication'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Nilai Akhir Sekolah</div>
                <div class="info-box-val" style="color:var(--color-accent);"><?= number_format((float) $assessmentData['final_score'], 1) ?></div>
              </div>
            </div>

            <div class="info-box" style="margin-top: 1rem;">
              <div class="info-box-label">Catatan &amp; Umpan Balik Guru (<?= murid_h($assessmentData['guru_name'] ?? 'Guru Pembimbing') ?>)</div>
              <div style="font-style:italic;margin:0.35rem 0;">"<?= nl2br(murid_h($assessmentData['feedback'] ?? 'Tidak ada catatan khusus.')) ?>"</div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Dinilai pada: <?= date('d F Y - H:i', strtotime($assessmentData['assessed_at'])) ?> WIB</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- 2. Penilaian Pembimbing Industri -->
        <div class="section-panel" style="margin-bottom: 1.5rem;">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('pembimbing_industri', '', 20) ?></span> Penilaian Guru Pembimbing Industri (DUDI)
              <span class="sec-badge"><?= $industryAssessmentData ? 'Sudah Dinilai' : 'Menunggu' ?></span>
            </div>
          </div>

          <?php if (!$industryAssessmentData): ?>
            <div class="empty-state" style="padding: 1.5rem 1rem;">
              <div class="empty-state-icon"><?= pkl_icon('clock', '', 42) ?></div>
              <div class="empty-state-title">Belum Ada Penilaian Pembimbing Industri</div>
              <p class="empty-state-desc">Pembimbing industri (<?= murid_h($internshipData['industri_name'] ?? 'Belum Ditugaskan') ?>) di tempat PKL akan menilai kedisiplinan, kehadiran, dan hasil kerja Anda.</p>
            </div>
          <?php else: ?>
            <div class="info-grid">
              <div class="info-box">
                <div class="info-box-label">Kedisiplinan &amp; Kepatuhan SOP</div>
                <div class="info-box-val"><?= (int) $industryAssessmentData['score_discipline'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kehadiran &amp; Kerajinan</div>
                <div class="info-box-val"><?= (int) $industryAssessmentData['score_attendance'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kualitas Hasil Pekerjaan</div>
                <div class="info-box-val"><?= (int) $industryAssessmentData['score_work_quality'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Nilai Akhir Industri</div>
                <div class="info-box-val" style="color:var(--color-accent);"><?= number_format((float) $industryAssessmentData['final_score'], 1) ?></div>
              </div>
            </div>

            <div class="info-box" style="margin-top: 1rem;">
              <div class="info-box-label">Catatan Evaluasi Industri (<?= murid_h($industryAssessmentData['industri_name'] ?? 'Pembimbing Industri') ?>)</div>
              <div style="font-style:italic;margin:0.35rem 0;">"<?= nl2br(murid_h($industryAssessmentData['feedback'] ?? 'Tidak ada catatan khusus.')) ?>"</div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Dinilai pada: <?= date('d F Y - H:i', strtotime($industryAssessmentData['assessed_at'])) ?> WIB</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- 3. Penilaian Sidang PKL -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('sidang', '', 20) ?></span> Penilaian Sidang PKL (Penguji)
              <span class="sec-badge"><?= $sidangAssessmentData ? 'Sudah Dinilai' : 'Menunggu' ?></span>
            </div>
          </div>

          <?php if (!$sidangAssessmentData): ?>
            <div class="empty-state" style="padding: 1.5rem 1rem;">
              <div class="empty-state-icon"><?= pkl_icon('clock', '', 42) ?></div>
              <div class="empty-state-title">Belum Ada Penilaian Sidang PKL</div>
              <p class="empty-state-desc">Penguji sidang (<?= murid_h($internshipData['penguji_name'] ?? 'Belum Ditugaskan') ?>) akan menguji pemahaman jurnal PKL, presentasi, dan tanya jawab Anda saat sidang PKL berlangsung.</p>
            </div>
          <?php else: ?>
            <div class="info-grid">
              <div class="info-box">
                <div class="info-box-label">Pemahaman Jurnal &amp; Kerja</div>
                <div class="info-box-val"><?= (int) $sidangAssessmentData['score_understanding'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kemampuan Presentasi</div>
                <div class="info-box-val"><?= (int) $sidangAssessmentData['score_presentation'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Tanya Jawab &amp; Teori Teknis</div>
                <div class="info-box-val"><?= (int) $sidangAssessmentData['score_qa'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Nilai Akhir Sidang</div>
                <div class="info-box-val" style="color:var(--color-accent);"><?= number_format((float) $sidangAssessmentData['final_score'], 1) ?></div>
              </div>
            </div>

            <div class="info-box" style="margin-top: 1rem;">
              <div class="info-box-label">Catatan &amp; Evaluasi Penguji (<?= murid_h($sidangAssessmentData['penguji_name'] ?? 'Penguji Sidang') ?>)</div>
              <div style="font-style:italic;margin:0.35rem 0;">"<?= nl2br(murid_h($sidangAssessmentData['feedback'] ?? 'Tidak ada catatan khusus.')) ?>"</div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Dinilai pada: <?= date('d F Y - H:i', strtotime($sidangAssessmentData['assessed_at'])) ?> WIB</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- 4. Penilaian Penguji Produktif -->
        <div class="section-panel" style="margin-bottom: 1.5rem;">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('penguji', '', 20) ?></span> Penilaian Penguji Produktif
              <span class="sec-badge"><?= $produktifAssessmentData ? 'Sudah Dinilai' : 'Menunggu' ?></span>
            </div>
          </div>
          <?php if (!$produktifAssessmentData): ?>
            <div class="empty-state" style="padding: 1.5rem 1rem;">
              <div class="empty-state-icon"><?= pkl_icon('clock', '', 42) ?></div>
              <div class="empty-state-title">Belum Ada Penilaian Penguji Produktif</div>
              <p class="empty-state-desc">
                <?php if (!empty($examinerAssignedMap['produktif'])): ?>
                  Penguji Produktif (<?= murid_h($examinerAssignedMap['produktif']) ?>) belum memberikan penilaian.
                <?php else: ?>
                  Belum ada Penguji Produktif yang ditugaskan. Hubungi Administrator.
                <?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <div class="info-grid">
              <div class="info-box">
                <div class="info-box-label">Penguasaan Materi</div>
                <div class="info-box-val"><?= (int)$produktifAssessmentData['score_materi'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Keterampilan Teknis</div>
                <div class="info-box-val"><?= (int)$produktifAssessmentData['score_teknis'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kualitas Hasil Pekerjaan</div>
                <div class="info-box-val"><?= (int)$produktifAssessmentData['score_hasil'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Pemecahan Masalah</div>
                <div class="info-box-val"><?= (int)$produktifAssessmentData['score_masalah'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Penguasaan Proses Kerja</div>
                <div class="info-box-val"><?= (int)$produktifAssessmentData['score_proses'] ?> / 100</div>
              </div>
            </div>
            <?php if (!empty($produktifAssessmentData['catatan'])): ?>
            <div class="info-box" style="margin-top: 1rem;">
              <div class="info-box-label">Catatan Penguji Produktif (<?= murid_h($produktifAssessmentData['penguji_name'] ?? 'Penguji Produktif') ?>)</div>
              <div style="font-style:italic;margin:0.35rem 0;">"<?= nl2br(murid_h($produktifAssessmentData['catatan'])) ?>"</div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Dinilai pada: <?= date('d F Y - H:i', strtotime($produktifAssessmentData['assessed_at'])) ?> WIB</div>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- 5. Penilaian Penguji Jurnal -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('jurnal', '', 20) ?></span> Penilaian Penguji Jurnal
              <span class="sec-badge"><?= $jurnalAssessmentData ? 'Sudah Dinilai' : 'Menunggu' ?></span>
            </div>
          </div>
          <?php if (!$jurnalAssessmentData): ?>
            <div class="empty-state" style="padding: 1.5rem 1rem;">
              <div class="empty-state-icon"><?= pkl_icon('clock', '', 42) ?></div>
              <div class="empty-state-title">Belum Ada Penilaian Penguji Jurnal</div>
              <p class="empty-state-desc">
                <?php if (!empty($examinerAssignedMap['jurnal'])): ?>
                  Penguji Jurnal (<?= murid_h($examinerAssignedMap['jurnal']) ?>) belum memberikan penilaian.
                <?php else: ?>
                  Belum ada Penguji Jurnal yang ditugaskan. Hubungi Administrator.
                <?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <div class="info-grid">
              <div class="info-box">
                <div class="info-box-label">Kelengkapan Jurnal</div>
                <div class="info-box-val"><?= (int)$jurnalAssessmentData['score_kelengkapan'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kesesuaian Isi dengan PKL</div>
                <div class="info-box-val"><?= (int)$jurnalAssessmentData['score_sesuai'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Pemahaman Kegiatan</div>
                <div class="info-box-val"><?= (int)$jurnalAssessmentData['score_pemahaman'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Kemampuan Menjelaskan</div>
                <div class="info-box-val"><?= (int)$jurnalAssessmentData['score_jelaskan'] ?> / 100</div>
              </div>
              <div class="info-box">
                <div class="info-box-label">Komunikasi</div>
                <div class="info-box-val"><?= (int)$jurnalAssessmentData['score_komunikasi'] ?> / 100</div>
              </div>
            </div>
            <?php if (!empty($jurnalAssessmentData['catatan'])): ?>
            <div class="info-box" style="margin-top: 1rem;">
              <div class="info-box-label">Catatan Penguji Jurnal (<?= murid_h($jurnalAssessmentData['penguji_name'] ?? 'Penguji Jurnal') ?>)</div>
              <div style="font-style:italic;margin:0.35rem 0;">"<?= nl2br(murid_h($jurnalAssessmentData['catatan'])) ?>"</div>
              <div style="font-size:0.75rem;color:var(--color-muted);">Dinilai pada: <?= date('d F Y - H:i', strtotime($jurnalAssessmentData['assessed_at'])) ?> WIB</div>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="content-tab-view" id="section-laporan" hidden>
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('laporan', '', 20) ?></span> Laporan Praktik Kerja Lapangan
              <span
                class="sec-badge"><?= $reportData ? murid_h(ucfirst($reportData['status'])) : 'Belum Submit' ?></span>
            </div>
          </div>

          <?php if ($reportData): ?>
            <div class="info-box">
              <div class="info-box-label">Laporan Terkirim</div>
              <span class="status-pill <?= murid_h(strtolower($reportData['status'])) ?>" style="display:inline-flex;align-items:center;gap:4px;">
                <?= $reportData['status'] === 'disetujui' ? pkl_icon('check', '', 12) . ' Disetujui' : 'Dikirim' ?>
              </span>
              <div class="info-box-val"><?= murid_h($reportData['title']) ?></div>
              <p class="card-desc-muted"><?= nl2br(murid_h($reportData['summary'])) ?></p>
              <div>Tanggal Pengiriman: <?= date('d M Y', strtotime($reportData['submission_date'])) ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" action="murid.php">
            <input type="hidden" name="action" value="submit_report">
            <h3><?= $reportData ? 'Perbarui Dokumen Laporan PKL' : 'Kirim Laporan Akhir PKL' ?></h3>

            <div class="form-group">
              <label class="form-label" for="report_title">Judul Laporan PKL</label>
              <input type="text" name="report_title" id="report_title" class="form-control"
                placeholder="Contoh: Laporan Akhir Implementasi FTTH di Industri..." required
                value="<?= murid_h($reportData['title'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label class="form-label" for="summary">Ringkasan / Abstrak Kegiatan PKL</label>
              <textarea name="summary" id="summary" class="form-control" rows="5"
                placeholder="Tuliskan intisari pembelajaran, proyek yang dikerjakan, dan pencapaian kompetensi selama PKL..."
                required><?= murid_h($reportData['summary'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-primary" style="width:auto;margin-top:1rem;padding:0.75rem 1.75rem;">
              <span><?= pkl_icon('save', '', 18) ?></span> <?= $reportData ? 'Simpan Perubahan Laporan' : 'Submit Laporan PKL' ?>
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="journal-modal">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('jurnal', '', 20) ?> Catat Jurnal Kegiatan Baru</div>
        <button type="button" class="modal-close-btn" id="btn-close-journal-modal">&times;</button>
      </div>

      <form method="POST" action="murid.php">
        <input type="hidden" name="action" value="add_journal">

        <div class="form-group">
          <label class="form-label" for="jurnal_date">Tanggal Kegiatan</label>
          <input type="date" name="activity_date" id="jurnal_date" class="form-control" required
            value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="jurnal_title">Judul / Pokok Kegiatan</label>
          <input type="text" name="title" id="jurnal_title" class="form-control"
            placeholder="Contoh: Konfigurasi VLAN &amp; Splicing Kabel..." required>
        </div>

        <div class="form-group">
          <label class="form-label" for="jurnal_desc">Deskripsi Aktivitas &amp; Pembelajaran</label>
          <textarea name="description" id="jurnal_desc" class="form-control" rows="4"
            placeholder="Jelaskan secara ringkas kegiatan teknis, kendala yang dihadapi, dan solusi yang Anda terapkan hari ini..."
            required></textarea>
        </div>

        <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
          <button type="button" class="btn-primary" id="btn-cancel-journal-modal"
            style="width:auto;background:transparent;color:var(--color-muted);border:1.5px solid var(--color-border);box-shadow:none;">Batal</button>
          <button type="submit" class="btn-primary" style="margin-top:0;flex:1;">
            <span><?= pkl_icon('save', '', 18) ?></span> Simpan Jurnal
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($murid_must_change_password)): ?>
  <div class="modal-backdrop active" id="modal-force-change-password" style="z-index: 99999; backdrop-filter: blur(4px); background: rgba(15, 23, 42, 0.75);">
    <div class="modal-box" style="max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
      <div class="modal-header" style="border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
        <div class="modal-title" style="display: flex; align-items: center; gap: 8px; color: var(--color-primary); font-size: 1.15rem;">
          <span><?= pkl_icon('key', '', 20) ?></span> Wajib Ganti Password Pertama Kali
        </div>
      </div>
      <div style="padding-top: 0.75rem;">
        <p style="font-size: 0.9rem; color: var(--color-text); line-height: 1.5; margin-bottom: 1.25rem;">
          Akun Anda menggunakan password awal (NIS). Demi keamanan akun Anda, silakan buat password baru sebelum melanjutkan ke dashboard PKL Journey.
        </p>
        <form method="POST" action="murid.php">
          <input type="hidden" name="action" value="change_initial_password">
          <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" for="new_password">Password Baru *</label>
            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
          </div>
          <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" for="confirm_new_password">Konfirmasi Password Baru *</label>
            <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" placeholder="Ulangi password baru" required minlength="6">
          </div>
          <button type="submit" class="btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
            <span><?= pkl_icon('save', '', 18) ?></span> Simpan Password &amp; Lanjutkan
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
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

    var modal = document.getElementById('journal-modal');
    function openJournalModal() {
      if (modal) modal.classList.add('active');
    }
    function closeJournalModal() {
      if (modal) modal.classList.remove('active');
    }

    ['btn-open-journal-modal', 'btn-open-journal-modal-2', 'btn-empty-add-journal'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('click', openJournalModal);
    });
    ['btn-close-journal-modal', 'btn-cancel-journal-modal'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('click', closeJournalModal);
    });
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeJournalModal();
      });
    }
  </script>
</body>

</html>