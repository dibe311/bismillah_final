<?php
/**
 * dashboard.php
 * TASK 5: Role-Based Dashboard
 * - Admin/Perawat: stats + quick actions + Chart.js 7-day trend
 * - Dokter: personal queue + c
 * - Pasien: active queue status + visit history
 */
require_once 'config/app.php';
require_once 'config/database.php';
requireAuth();

$db    = getDB();
$user  = currentUser();
$role  = $user['role'];
$today = date('Y-m-d');
$flash = getFlash();

/* ================================================================
   DATA QUERIES — per role
   ================================================================ */

if (in_array($role, ['admin', 'perawat'])) {

    // Top metrics
    $totalPatients = (int)$db->query("SELECT COUNT(*) FROM patients WHERE is_active=1")->fetchColumn();

    $stmtQ = $db->prepare("SELECT COUNT(*) FROM queues WHERE queue_date=?");
    $stmtQ->execute([$today]);
    $todayQueues = (int)$stmtQ->fetchColumn();

    $stmtW = $db->prepare("SELECT COUNT(*) FROM queues WHERE queue_date=? AND status='waiting'");
    $stmtW->execute([$today]);
    $waiting = (int)$stmtW->fetchColumn();

    $stmtD = $db->prepare("SELECT COUNT(*) FROM queues WHERE queue_date=? AND status='done'");
    $stmtD->execute([$today]);
    $done = (int)$stmtD->fetchColumn();

    // 7-day visit trend (GROUP BY date)
    $trendStmt = $db->prepare("
        SELECT DATE(queue_date) AS day, COUNT(*) AS total
        FROM queues
        WHERE queue_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(queue_date)
        ORDER BY day ASC
    ");
    $trendStmt->execute();
    $trendRows = $trendStmt->fetchAll();

    // Fill all 7 days (even if no data)
    $trendLabels = [];
    $trendData   = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('d/m', strtotime($day));
        $trendData[$day] = 0;
    }
    foreach ($trendRows as $row) {
        if (isset($trendData[$row['day']])) $trendData[$row['day']] = (int)$row['total'];
    }
    $trendData = array_values($trendData);

    // Today's queue list
    $queueList = $db->prepare("
        SELECT q.id, q.queue_number, q.status, q.created_at, q.called_at,
               p.name AS patient_name, p.gender, p.birth_date,
               u.name AS doctor_name
        FROM queues q
        LEFT JOIN patients p ON q.patient_id = p.id
        LEFT JOIN users u    ON q.doctor_id  = u.id
        WHERE q.queue_date = ?
        ORDER BY q.created_at ASC
        LIMIT 20
    ");
    $queueList->execute([$today]);
    $queueList = $queueList->fetchAll();

} elseif ($role === 'dokter') {

    // Doctor's assigned queue today
    $myQueue = $db->prepare("
        SELECT q.id, q.queue_number, q.status, q.created_at,
               p.id AS patient_id, p.name AS patient_name, p.birth_date, p.gender,
               ic.blood_pressure, ic.temperature, ic.chief_complaint
        FROM queues q
        LEFT JOIN patients p        ON q.patient_id = p.id
        LEFT JOIN initial_checks ic ON ic.queue_id  = q.id
        WHERE q.doctor_id = ? AND q.queue_date = ?
          AND q.status IN ('waiting','called','in_progress')
        ORDER BY q.created_at ASC
        LIMIT 15
    ");
    $myQueue->execute([$user['id'], $today]);
    $myQueue = $myQueue->fetchAll();

    $myDone = $db->prepare("SELECT COUNT(*) FROM queues WHERE doctor_id=? AND queue_date=? AND status='done'");
    $myDone->execute([$user['id'], $today]);
    $myDone = (int)$myDone->fetchColumn();

    $myTotal = $db->prepare("SELECT COUNT(*) FROM queues WHERE doctor_id=? AND queue_date=?");
    $myTotal->execute([$user['id'], $today]);
    $myTotal = (int)$myTotal->fetchColumn();

} elseif ($role === 'pasien') {

    // Find patient record linked to this user
    $patientRec = $db->prepare("SELECT id, name FROM patients WHERE user_id=? LIMIT 1");
    $patientRec->execute([$user['id']]);
    $patientRec = $patientRec->fetch();

    $activeQueue  = null;
    $visitHistory = [];

    if ($patientRec) {
        // Active queue today
        $aqStmt = $db->prepare("
            SELECT q.*, u.name AS doctor_name
            FROM queues q
            LEFT JOIN users u ON q.doctor_id = u.id
            WHERE q.patient_id = ? AND q.queue_date = ? AND q.status NOT IN ('done','cancelled')
            ORDER BY q.created_at DESC LIMIT 1
        ");
        $aqStmt->execute([$patientRec['id'], $today]);
        $activeQueue = $aqStmt->fetch();

        // Visit history
        $vhStmt = $db->prepare("
            SELECT mr.visit_date, mr.diagnosis, mr.prescription, u.name AS doctor_name
            FROM medical_records mr
            LEFT JOIN users u ON mr.doctor_id = u.id
            WHERE mr.patient_id = ?
            ORDER BY mr.visit_date DESC
            LIMIT 8
        ");
        $vhStmt->execute([$patientRec['id']]);
        $visitHistory = $vhStmt->fetchAll();
    }
}

// Doctors on duty (all roles see this)
$doctors = $db->prepare("
    SELECT u.name, dp.specialization
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    WHERE u.role = 'dokter' AND u.is_active = 1
    LIMIT 6
");
$doctors->execute();
$doctors = $doctors->fetchAll();

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$extraHead  = ''; // inner.css already loaded via header.php cssFile default
?>
<?php require_once 'includes/header.php'; ?>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="flex items-center gap-2">
        <button id="sidebarToggle" class="btn btn-ghost btn-icon">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
        </button>
        <span class="topbar-title">Dashboard</span>
      </div>
      <div class="topbar-actions">
        <span class="topbar-date"><?= date('l, d F Y') ?></span>
        <span id="liveClock" class="text-sm font-semibold" style="color:var(--gray-600)"></span>
      </div>
    </div>

    <div class="page-content">

      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <?= sanitize($flash['message']) ?>
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
      <div class="alert alert-error">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        Anda tidak memiliki akses ke halaman tersebut.
      </div>
      <?php endif; ?>

      <!-- ======================================================
           ADMIN / PERAWAT DASHBOARD
           ====================================================== -->
      <?php if (in_array($role, ['admin','perawat'])): ?>

      <!-- Greeting Banner -->
      <div class="greeting-banner">
        <div class="greeting-text">
          <h2>Selamat Datang, <?= sanitize(explode(' ', $user['name'])[0]) ?></h2>
          <p>Ringkasan operasional hari ini — <?= date('d F Y') ?></p>
        </div>
        <div class="greeting-badge">
          <span class="live-dot"></span>
          Sistem Aktif
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon navy">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1z"/></svg>
          </div>
          <div class="stat-body">
            <div class="stat-value"><?= number_format($totalPatients) ?></div>
            <div class="stat-label">Total Pasien</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="stat-body">
            <div class="stat-value"><?= $todayQueues ?></div>
            <div class="stat-label">Antrian Hari Ini</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
          </div>
          <div class="stat-body">
            <div class="stat-value"><?= $waiting ?></div>
            <div class="stat-label">Menunggu</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
          </div>
          <div class="stat-body">
            <div class="stat-value"><?= $done ?></div>
            <div class="stat-label">Selesai Hari Ini</div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="section-header-row mb-2">
        <span class="section-label">Aksi Cepat</span>
      </div>
      <div class="quick-actions mb-4">
        <a href="<?= BASE_URL ?>/patients/add" class="quick-action-btn">
          <div class="quick-action-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          </div>
          <span class="quick-action-label">Tambah Pasien</span>
        </a>
        <a href="<?= BASE_URL ?>/queues/create" class="quick-action-btn">
          <div class="quick-action-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
          </div>
          <span class="quick-action-label">Buat Antrian</span>
        </a>
        <a href="<?= BASE_URL ?>/initial_checks" class="quick-action-btn">
          <div class="quick-action-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V7h2v2z" clip-rule="evenodd"/></svg>
          </div>
          <span class="quick-action-label">Pemeriksaan Awal</span>
        </a>
        <a href="<?= BASE_URL ?>/patients" class="quick-action-btn">
          <div class="quick-action-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          </div>
          <span class="quick-action-label">Cari Pasien</span>
        </a>        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/admin/users" class="quick-action-btn">
          <div class="quick-action-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </div>
          <span class="quick-action-label">Kelola User</span>
        </a>
        <?php endif; ?>
      </div>

      <!-- Chart + Queue -->
      <div class="two-col-grid">

        <!-- Chart.js — 7-day trend -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Tren Kunjungan 7 Hari Terakhir</span>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="visitTrendChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Today's Queue -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Antrian Hari Ini</span>
            <a href="<?= BASE_URL ?>/queues" class="btn btn-ghost btn-sm">Lihat Semua →</a>
          </div>
          <?php if ($queueList): ?>
          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Pasien</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($queueList, 0, 8) as $q): ?>
                <tr>
                  <td><strong style="color:var(--blue)"><?= sanitize($q['queue_number']) ?></strong></td>
                  <td>
                    <div class="td-name"><?= sanitize($q['patient_name']) ?></div>
                    <div class="text-xs text-muted"><?= $q['gender'] === 'L' ? 'L' : 'P' ?>, <?= calculateAge($q['birth_date']) ?> th</div>
                  </td>
                  <td>
                    <span class="badge badge-<?= $q['status'] ?>">
                      <?= queueStatusLabel($q['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($q['status'] === 'waiting'): ?>
                      <button class="btn btn-primary btn-sm" data-queue-action="called" data-queue-id="<?= $q['id'] ?>">Panggil</button>
                    <?php elseif ($q['status'] === 'called'): ?>
                      <button class="btn btn-success btn-sm" data-queue-action="in_progress" data-queue-id="<?= $q['id'] ?>">Masuk</button>
                    <?php else: ?>
                      <span class="text-xs text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="empty-state" style="padding:28px">
            <div class="empty-state-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg></div>
            <p class="empty-state-title">Belum ada antrian</p>
            <a href="<?= BASE_URL ?>/queues/create" class="btn btn-primary btn-sm">+ Buat Antrian</a>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- end two-col-grid -->

      <!-- Chart.js Script -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
      <script>
      (function() {
        const labels = <?= json_encode($trendLabels) ?>;
        const data   = <?= json_encode($trendData) ?>;
        const ctx    = document.getElementById('visitTrendChart');
        if (!ctx) return;
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels,
            datasets: [{
              label: 'Kunjungan',
              data,
              backgroundColor: 'rgba(25,118,210,.15)',
              borderColor: '#1976D2',
              borderWidth: 1.5,
              borderRadius: 4,
              borderSkipped: false,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: ctx => ` ${ctx.parsed.y} kunjungan`
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 11 } },
                grid: { color: '#EEEEEE' }
              },
              x: {
                ticks: { font: { size: 11 } },
                grid: { display: false }
              }
            }
          }
        });
      })();
      </script>

      <!-- ======================================================
           DOKTER DASHBOARD
           ====================================================== -->
      <?php elseif ($role === 'dokter'): ?>

      <!-- Greeting -->
      <div class="greeting-banner">
        <div class="greeting-text">
          <h2>Dr. <?= sanitize(explode(' ', $user['name'])[0]) ?></h2>
          <p>Jadwal pemeriksaan Anda — <?= date('d F Y') ?></p>
        </div>
        <div style="display:flex;gap:12px">
          <div class="greeting-badge">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            <?= $myDone ?> selesai
          </div>
          <div class="greeting-badge" style="background:rgba(255,193,7,.15);color:#FFD54F">
            <?= count($myQueue) ?> menunggu
          </div>
        </div>
      </div>

      <div class="two-col-grid">

        <!-- Queue list for this doctor -->
        <div class="card" style="grid-column: span 1">
          <div class="card-header">
            <span class="card-title">Antrian Pasien Saya</span>
            <span class="badge badge-waiting"><?= count($myQueue) ?> pasien</span>
          </div>
          <div class="card-body" style="padding:12px">
            <?php if ($myQueue): ?>
              <?php foreach ($myQueue as $q): ?>
              <div class="call-card">
                <div class="call-num"><?= sanitize($q['queue_number']) ?></div>
                <div class="call-info">
                  <div class="call-patient"><?= sanitize($q['patient_name']) ?></div>
                  <div class="call-sub">
                    <?= calculateAge($q['birth_date']) ?> th
                    <?php if ($q['chief_complaint']): ?>
                      · <?= sanitize(mb_substr($q['chief_complaint'], 0, 40)) ?>
                    <?php endif; ?>
                  </div>
                  <?php if ($q['blood_pressure'] || $q['temperature']): ?>
                  <div class="text-xs text-muted mt-1">
                    <?= $q['blood_pressure'] ? 'TD: ' . sanitize($q['blood_pressure']) : '' ?>
                    <?= $q['temperature']    ? ' · Suhu: ' . $q['temperature'] . '°C' : '' ?>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="flex gap-2">
                  <?php if ($q['status'] === 'in_progress'): ?>
                  <a href="<?= BASE_URL ?>/medical_records/create?queue_id=<?= $q['id'] ?>&patient_id=<?= $q['patient_id'] ?>"
                     class="btn btn-primary btn-sm">Periksa</a>
                  <?php elseif ($q['status'] === 'called'): ?>
                  <button class="btn btn-success btn-sm" data-queue-action="in_progress" data-queue-id="<?= $q['id'] ?>">Mulai</button>
                  <?php else: ?>
                  <span class="badge badge-waiting">Menunggu</span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
              <p class="empty-state-title">Tidak ada antrian aktif</p>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Dokter on duty + quick links -->
        <div class="flex flex-col gap-3">
          <div class="card">
            <div class="card-header"><span class="card-title">Aksi Cepat</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
              <a href="<?= BASE_URL ?>/medical_records" class="btn btn-outline w-full" style="justify-content:start">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/></svg>
                Riwayat Rekam Medis
              </a>
              <a href="<?= BASE_URL ?>/medical_records/new" class="btn btn-primary w-full" style="justify-content:start">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                Catat Rekam Medis Baru
              </a>
              <a href="<?= BASE_URL ?>/patients" class="btn btn-outline w-full" style="justify-content:start">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Data Pasien
              </a>
            </div>
          </div>

          <!-- Today's stats -->
          <div class="card">
            <div class="card-header"><span class="card-title">Statistik Hari Ini</span></div>
            <div class="card-body">
              <div class="vitals-grid" style="grid-template-columns:1fr 1fr">
                <div class="vital-item">
                  <div class="vital-value"><?= $myTotal ?></div>
                  <div class="vital-label">Total Antrian</div>
                </div>
                <div class="vital-item" style="background:var(--green-bg);border-color:var(--green-border)">
                  <div class="vital-value" style="color:var(--green)"><?= $myDone ?></div>
                  <div class="vital-label">Selesai</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- end two-col-grid dokter -->

      <!-- ======================================================
           PASIEN DASHBOARD
           ====================================================== -->
      <?php elseif ($role === 'pasien'): ?>

      <div class="page-header">
        <div>
          <h1 class="page-title">Halo, <?= sanitize(explode(' ', $user['name'])[0]) ?></h1>
          <p class="page-subtitle">Informasi kesehatan Anda — <?= date('d F Y') ?></p>
        </div>
      </div>

      <?php if (!$patientRec): ?>
      <div class="alert alert-info">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        Data pasien Anda belum terdaftar. Silakan hubungi petugas pendaftaran.
      </div>
      <?php else: ?>

        <!-- Active queue -->
        <?php if ($activeQueue): ?>
        <div class="queue-banner">
          <div class="queue-big-number"><?= sanitize($activeQueue['queue_number']) ?></div>
          <div class="queue-banner-info">
            <h3>Nomor Antrian Aktif</h3>
            <p>
              Status: <strong><?= queueStatusLabel($activeQueue['status']) ?></strong>
              <?php if ($activeQueue['doctor_name']): ?>
                · Dokter: Dr. <?= sanitize($activeQueue['doctor_name']) ?>
              <?php endif; ?>
            </p>
          </div>
          <span class="badge badge-<?= $activeQueue['status'] ?>" style="margin-left:auto">
            <?= queueStatusLabel($activeQueue['status']) ?>
          </span>
        </div>
        <?php else: ?>
        <div class="alert alert-info" style="margin-bottom:20px">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
          Tidak ada antrian aktif hari ini.
        </div>
        <?php endif; ?>

        <!-- Visit history -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Riwayat Kunjungan</span>
            <a href="<?= BASE_URL ?>/patients/my_records" class="btn btn-ghost btn-sm">Lihat Semua →</a>
          </div>
          <?php if ($visitHistory): ?>
          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Diagnosa</th>
                  <th>Dokter</th>
                  <th>Resep</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visitHistory as $v): ?>
                <tr>
                  <td class="text-sm"><?= date('d/m/Y', strtotime($v['visit_date'])) ?></td>
                  <td class="td-name"><?= sanitize($v['diagnosis']) ?></td>
                  <td class="text-sm text-muted">Dr. <?= sanitize($v['doctor_name']) ?></td>
                  <td class="text-sm"><?= $v['prescription'] ? sanitize(mb_substr($v['prescription'], 0, 50)) . '...' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5z"/></svg></div>
            <p class="empty-state-title">Belum ada riwayat kunjungan</p>
          </div>
          <?php endif; ?>
        </div>

      <?php endif; ?>
      <?php endif; /* end role pasien */ ?>

      <!-- ======================================================
           DOKTER ON DUTY (semua role)
           ====================================================== -->
      <?php if ($doctors): ?>
      <div class="mt-6">
        <div class="section-header-row mb-2">
          <span class="section-label">Dokter Bertugas Hari Ini</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
          <?php foreach ($doctors as $doc): ?>
          <div class="doctor-card">
            <div class="doctor-avatar"><?= strtoupper(substr($doc['name'], 0, 2)) ?></div>
            <div>
              <div class="doctor-name">dr. <?= sanitize($doc['name']) ?></div>
              <div class="doctor-spec"><?= sanitize($doc['specialization'] ?? 'Dokter Umum') ?></div>
            </div>
            <div class="doctor-status">
              <span class="badge badge-done">
                <span class="live-dot" style="width:5px;height:5px"></span> Jaga
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<script>
// MediRek — Main JS

document.addEventListener('DOMContentLoaded', () => {

    // ---- Auto-dismiss alerts ----
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });

    // ---- Confirm delete/action buttons ----
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });

    // ---- Modal system ----
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ---- Mobile sidebar toggle ----
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ---- Live clock in topbar ----
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        const update = () => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        };
        update();
        setInterval(update, 10000);
    }

    // ---- Demo account fill (login page) ----
    document.querySelectorAll('.demo-row').forEach(row => {
        row.addEventListener('click', () => {
            const email = row.dataset.email;
            const pwd = row.dataset.password;
            const emailField = document.getElementById('email');
            const pwdField = document.getElementById('password');
            if (emailField) emailField.value = email;
            if (pwdField) { pwdField.value = pwd; pwdField.type = 'text'; setTimeout(() => pwdField.type='password', 600); }
        });
    });

    // ---- Inline patient search autocomplete (new.php) ----
    const patientSearch = document.getElementById('patientSearch');
    const patientDropdown = document.getElementById('patientDropdown');
    const patientIdInput = document.getElementById('patientId');

    if (patientSearch && patientDropdown) {
        let debounceTimer;
        patientSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const q = patientSearch.value.trim();
                if (q.length < 2) { patientDropdown.classList.remove('open'); return; }
                try {
                    // FIX: gunakan path relatif dari BASE_URL, tidak hardcode /medirek/
                    const res = await fetch(`/apb/search_patients?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    patientDropdown.innerHTML = '';
                    if (!data.length) {
                        patientDropdown.innerHTML = '<div class="patient-option text-muted">Tidak ditemukan</div>';
                    } else {
                        data.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'patient-option';
                            div.innerHTML = `<div class="patient-option-name">${escHtml(p.name)}</div><div class="patient-option-sub">NIK: ${escHtml(p.nik)} &bull; ${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} &bull; ${p.age} th</div>`;
                            div.addEventListener('click', () => {
                                patientSearch.value = p.name;
                                if (patientIdInput) patientIdInput.value = p.id;
                                patientDropdown.classList.remove('open');
                                // Enable submit button
                                const submitBtn = document.getElementById('submitBtn');
                                if (submitBtn) submitBtn.disabled = false;
                                // Trigger patient info update
                                if (typeof onPatientSelected === 'function') onPatientSelected(p);
                                // Update info card for new.php
                                const infoCard = document.getElementById('patientInfoCard');
                                const headerRow = document.getElementById('patientHeaderRow');
                                const infoAvatar = document.getElementById('infoAvatar');
                                const infoName = document.getElementById('infoName');
                                const infoSub = document.getElementById('infoSub');
                                const infoAllergy = document.getElementById('infoAllergy');
                                if (infoCard) infoCard.style.display = 'block';
                                if (headerRow) headerRow.style.display = 'flex';
                                if (infoAvatar) infoAvatar.textContent = p.name.substring(0, 2).toUpperCase();
                                if (infoName) infoName.textContent = p.name;
                                if (infoSub) infoSub.textContent = `${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'} \u00b7 ${p.age} tahun`;
                                if (infoAllergy) {
                                    if (p.allergy) {
                                        infoAllergy.textContent = '\u26a0 Alergi: ' + p.allergy;
                                        infoAllergy.style.display = 'inline-flex';
                                    } else {
                                        infoAllergy.style.display = 'none';
                                    }
                                }
                            });
                            patientDropdown.appendChild(div);
                        });
                    }
                    patientDropdown.classList.add('open');
                } catch (_) {}
            }, 280);
        });

        document.addEventListener('click', e => {
            if (!patientSearch.contains(e.target)) patientDropdown.classList.remove('open');
        });
    }

    // ---- Character counter for textareas ----
    document.querySelectorAll('textarea[maxlength]').forEach(ta => {
        const counter = document.createElement('div');
        counter.className = 'text-xs text-muted mt-2';
        const update = () => { counter.textContent = `${ta.value.length} / ${ta.maxLength}`; };
        ta.after(counter);
        ta.addEventListener('input', update);
        update();
    });

    // ---- Queue status quick-update via AJAX ----
    // FIX: gunakan BASE_URL yang benar, tidak hardcode /medirek/
    document.querySelectorAll('[data-queue-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const queueId = btn.dataset.queueId;
            const action = btn.dataset.queueAction;
            const confirmMsg = btn.dataset.confirm;

            if (confirmMsg && !confirm(confirmMsg)) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>';

            try {
                const res = await fetch('/apb/queue_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ queue_id: parseInt(queueId), action })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    showToast('error', data.message || 'Gagal memperbarui antrian');
                    btn.disabled = false;
                    btn.innerHTML = action === 'called' ? 'Panggil' : action === 'in_progress' ? 'Mulai' : 'Batal';
                }
            } catch (_) {
                btn.disabled = false;
                showToast('error', 'Terjadi kesalahan jaringan');
            }
        });
    });
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;box-shadow:0 4px 20px rgba(0,0,0,.15);';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity .4s'; setTimeout(() => toast.remove(), 400); }, 3500);
}

</script>
</body>
</html>
