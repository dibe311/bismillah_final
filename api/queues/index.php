<?php
/**
 * queues/index.php — MODULE 3
 * Queue management list. Admin, Perawat, Dokter.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','perawat','dokter']);

$db   = getDB();
$user = currentUser();

$date   = $_GET['date']   ?? date('Y-m-d');
$status = $_GET['status'] ?? '';

// Gunakan positional params (?) agar tidak ada konflik named params di PDO + TiDB
$where  = 'WHERE q.queue_date = ?';
$params = [$date];

if ($status) {
    $allowed = ['waiting','called','in_progress','done','cancelled'];
    if (in_array($status, $allowed, true)) {
        $where .= ' AND q.status = ?';
        $params[] = $status;
    }
}

// Dokter sees only their own assignments
if ($user['role'] === 'dokter') {
    $where .= ' AND q.doctor_id = ?';
    $params[] = (int)$user['id'];
}

$stmt = $db->prepare("
    SELECT q.id, q.queue_number, q.status, q.queue_date, q.created_at, q.notes,
           p.id   AS patient_id,  p.name  AS patient_name, p.gender, p.birth_date,
           u.name AS doctor_name,
           ic.blood_pressure, ic.temperature, ic.chief_complaint,
           mr.id  AS record_id
    FROM   queues q
    LEFT JOIN patients p         ON q.patient_id = p.id
    LEFT JOIN users u            ON q.doctor_id  = u.id
    LEFT JOIN initial_checks ic  ON ic.queue_id  = q.id
    LEFT JOIN medical_records mr ON mr.id_queue = q.id    $where
    ORDER BY q.created_at ASC
");
$stmt->execute($params);
$queues = $stmt->fetchAll();

// Status tab counts
$cntStmt = $db->prepare("SELECT status, COUNT(*) AS cnt FROM queues WHERE queue_date = ? GROUP BY status");
$cntStmt->execute([$date]);
$statusCounts = [];
foreach ($cntStmt->fetchAll() as $r) $statusCounts[$r['status']] = $r['cnt'];

$STATUS_LABELS = ['waiting'=>'Menunggu','called'=>'Dipanggil','in_progress'=>'Diperiksa','done'=>'Selesai','cancelled'=>'Batal'];

$pageTitle  = 'Daftar Antrian';
$activeMenu = 'queues';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Daftar Antrian</h1>
    <p class="page-subtitle"><?= date('l, d F Y', strtotime($date)) ?></p>
  </div>
  <?php if (hasRole(['admin','perawat'])): ?>
  <a href="<?= BASE_URL ?>/queues/create" class="btn btn-primary">
    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
    Buat Antrian
  </a>
  <?php endif; ?>
</div>

<!-- Date + status filter -->
<form method="GET" action=""
      style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <input class="form-control" type="date" name="date"
         value="<?= sanitize($date) ?>" style="width:auto">
  <select class="form-control" name="status" style="width:auto">
    <option value="">Semua Status</option>
    <?php foreach ($STATUS_LABELS as $k => $v): ?>
    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline">Filter</button>
  <a href="<?= BASE_URL ?>/queues" class="btn btn-ghost">Reset</a>
</form>

<!-- Status pill counters -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach ($STATUS_LABELS as $k => $v): $cnt = $statusCounts[$k] ?? 0; ?>
  <a href="?date=<?= $date ?>&status=<?= $k ?>"
     class="badge badge-<?= $k ?>"
     style="font-size:12px;padding:4px 10px;text-decoration:none;cursor:pointer">
    <?= $v ?>: <strong><?= $cnt ?></strong>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Antrian (<?= count($queues) ?> pasien)</span>
  </div>

  <?php if ($queues): ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>No.</th><th>Pasien</th><th>Dokter</th>
          <th>Vital / Keluhan</th><th>Status</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($queues as $q): ?>
        <tr>
          <td><strong style="color:var(--blue);font-size:15px"><?= sanitize($q['queue_number']) ?></strong>
              <div class="text-xs text-muted"><?= date('H:i', strtotime($q['created_at'])) ?></div>
          </td>
          <td>
            <div class="td-name"><?= sanitize($q['patient_name'] ?? '—') ?></div>
            <div class="text-xs text-muted">
              <?= $q['gender']==='L' ? 'L' : 'P' ?>, <?= calculateAge($q['birth_date']) ?> th
            </div>
          </td>
          <td class="text-sm text-muted">
            <?= $q['doctor_name'] ? 'dr. '.sanitize($q['doctor_name']) : '<span style="color:var(--gray-400)">Belum ditugaskan</span>' ?>
          </td>
          <td>
            <?php if ($q['blood_pressure'] || $q['temperature']): ?>
            <div class="text-xs font-semibold">
              <?= $q['blood_pressure'] ? sanitize($q['blood_pressure']).' mmHg' : '' ?>
              <?= $q['temperature']    ? ' · '.number_format($q['temperature'],1).'°C' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($q['chief_complaint']): ?>
            <div class="text-xs text-muted"
                 style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                 title="<?= sanitize($q['chief_complaint']) ?>">
              <?= sanitize($q['chief_complaint']) ?>
            </div>
            <?php else: ?>
              <?php if (!$q['blood_pressure']): ?>
              <span class="text-xs text-muted">Belum diperiksa</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $q['status'] ?>">
              <?= $STATUS_LABELS[$q['status']] ?? $q['status'] ?>
            </span>
          </td>
          <td>
            <div class="flex gap-1 flex-wrap">

              <!-- Perawat/Admin: input vital -->
              <?php if (hasRole(['admin','perawat'])
                     && !$q['blood_pressure']
                     && $q['status'] === 'waiting'): ?>
              <a href="<?= BASE_URL ?>/initial_checks/create?queue_id=<?= $q['id'] ?>"
                 class="btn btn-outline btn-sm">Vital</a>
              <?php endif; ?>

              <!-- Panggil pasien -->
              <?php if (hasRole(['admin','perawat']) && $q['status'] === 'waiting'): ?>
              <button class="btn btn-primary btn-sm"
                      data-queue-action="called"
                      data-queue-id="<?= $q['id'] ?>">Panggil</button>
              <?php endif; ?>

              <!-- Dokter: mulai pemeriksaan / buat rekam medis -->
              <?php if (hasRole('dokter')): ?>
                <?php if ($q['status'] === 'called'): ?>
                <button class="btn btn-success btn-sm"
                        data-queue-action="in_progress"
                        data-queue-id="<?= $q['id'] ?>">Mulai</button>
                <?php elseif ($q['status'] === 'in_progress' && !$q['record_id']): ?>
                <a href="<?= BASE_URL ?>/medical_records/create?queue_id=<?= $q['id'] ?>"
                   class="btn btn-primary btn-sm">Rekam Medis</a>
                <?php elseif ($q['record_id']): ?>
                <a href="<?= BASE_URL ?>/medical_records/view?id=<?= $q['record_id'] ?>"
                   class="btn btn-ghost btn-sm">Lihat RM</a>
                <?php endif; ?>
              <?php endif; ?>

              <!-- Admin: batalkan -->
              <?php if (hasRole('admin')
                     && in_array($q['status'], ['waiting','called'], true)): ?>
              <button class="btn btn-ghost btn-sm"
                      data-queue-action="cancelled"
                      data-queue-id="<?= $q['id'] ?>"
                      data-confirm="Batalkan antrian <?= sanitize($q['queue_number']) ?>?">
                Batal
              </button>
              <?php endif; ?>

            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
    </div>
    <p class="empty-state-title">Tidak ada antrian untuk tanggal ini</p>
    <?php if (hasRole(['admin','perawat'])): ?>
    <a href="<?= BASE_URL ?>/queues/create" class="btn btn-primary btn-sm">+ Buat Antrian</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';