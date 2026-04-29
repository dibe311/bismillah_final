<?php
/**
 * initial_checks/index.php — MODULE 3
 * Shows queues that need initial check (status=waiting, no initial_check yet).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','perawat']);

$db    = getDB();
$today = date('Y-m-d');
$date  = $_GET['date'] ?? $today;

// Queues that have NOT been checked yet (LEFT JOIN returns NULL)
$pending = $db->prepare("
    SELECT q.id, q.queue_number, q.status, q.created_at,
           p.name AS patient_name, p.birth_date, p.gender, p.allergy
    FROM queues q
    LEFT JOIN patients p        ON q.patient_id = p.id
    LEFT JOIN initial_checks ic ON ic.queue_id  = q.id
    WHERE q.queue_date = ? AND q.status = 'waiting' AND ic.id IS NULL
    ORDER BY q.created_at ASC
");
$pending->execute([$date]);
$pending = $pending->fetchAll();

// Already checked today
$checked = $db->prepare("
    SELECT q.id, q.queue_number, q.status, ic.checked_at,
           p.name AS patient_name,
           ic.blood_pressure, ic.temperature, ic.chief_complaint,
           u.name AS nurse_name
    FROM initial_checks ic
    LEFT JOIN queues q   ON q.id    = ic.queue_id
    LEFT JOIN patients p ON p.id    = ic.patient_id
    LEFT JOIN users u    ON u.id    = ic.nurse_id
    WHERE q.queue_date = ?
    ORDER BY ic.checked_at DESC
");
$checked->execute([$date]);
$checked = $checked->fetchAll();

$pageTitle  = 'Pemeriksaan Awal';
$activeMenu = 'initial_checks';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Pemeriksaan Awal</h1>
    <p class="page-subtitle">Triase & input tanda vital pasien</p>
  </div>
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <input class="form-control" type="date" name="date"
           value="<?= sanitize($date) ?>" style="width:auto">
    <button class="btn btn-outline">Filter</button>
  </form>
</div>

<div class="two-col-grid">

  <!-- Pending -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Menunggu Pemeriksaan</span>
      <span class="badge badge-waiting"><?= count($pending) ?> pasien</span>
    </div>
    <?php if ($pending): ?>
    <div style="padding:8px">
      <?php foreach ($pending as $q): ?>
      <div class="call-card">
        <div class="call-num"><?= sanitize($q['queue_number']) ?></div>
        <div class="call-info">
          <div class="call-patient"><?= sanitize($q['patient_name']) ?></div>
          <div class="call-sub">
            <?= $q['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> ·
            <?= calculateAge($q['birth_date']) ?> tahun
            <?php if ($q['allergy']): ?>
              <span class="allergy-badge ml-2">⚠ Alergi</span>
            <?php endif; ?>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/initial_checks/create?queue_id=<?= $q['id'] ?>"
           class="btn btn-primary btn-sm">Periksa</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px">
      <div class="empty-state-icon">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
      </div>
      <p class="empty-state-title">Semua pasien sudah diperiksa</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Already checked -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Sudah Diperiksa</span>
      <span class="badge badge-done"><?= count($checked) ?> pasien</span>
    </div>
    <?php if ($checked): ?>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr><th>No.</th><th>Pasien</th><th>Vital</th><th>Perawat</th><th>Waktu</th></tr>
        </thead>
        <tbody>
          <?php foreach ($checked as $c): ?>
          <tr>
            <td><strong style="color:var(--blue)"><?= sanitize($c['queue_number']) ?></strong></td>
            <td class="td-name"><?= sanitize($c['patient_name']) ?></td>
            <td class="text-xs">
              <?= $c['blood_pressure'] ? sanitize($c['blood_pressure']).' mmHg' : '' ?>
              <?= $c['temperature'] ? '<br>'.number_format($c['temperature'],1).'°C' : '' ?>
            </td>
            <td class="text-xs text-muted"><?= sanitize($c['nurse_name']) ?></td>
            <td class="text-xs text-muted"><?= date('H:i', strtotime($c['checked_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px">
      <p class="empty-state-title">Belum ada pemeriksaan hari ini</p>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';