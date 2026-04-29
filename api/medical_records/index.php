<?php
/**
 * medical_records/index.php — MODULE 4
 * Doctor & Admin: list all medical records with search.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','dokter','perawat']);

$db   = getDB();
$user = currentUser();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = 'WHERE 1=1';
$params = [];

// Dokter: only own records
if ($user['role'] === 'dokter') {
    $where .= ' AND mr.doctor_id = :doc';
    $params[':doc'] = $user['id'];
}

if ($search) {
    $where .= ' AND (p.name LIKE :q OR mr.diagnosis LIKE :q2 OR mr.icd_code LIKE :q3)';
    $s = "%$search%";
    $params[':q'] = $s; $params[':q2'] = $s; $params[':q3'] = $s;
}

$total = $db->prepare("
    SELECT COUNT(*)
    FROM medical_records mr
    LEFT JOIN patients p ON p.id = mr.patient_id
    $where
");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

$stmt = $db->prepare("
    SELECT mr.id, mr.visit_date, mr.diagnosis, mr.icd_code, mr.prescription,
           mr.is_referred, mr.follow_up_date,
           p.id   AS patient_id, p.name AS patient_name, p.gender, p.birth_date,
           u.name AS doctor_name
    FROM   medical_records mr
    LEFT JOIN patients p ON p.id = mr.patient_id
    LEFT JOIN users u    ON u.id = mr.doctor_id
    $where
    ORDER BY mr.visit_date DESC, mr.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$pageTitle  = 'Rekam Medis';
$activeMenu = 'medical_records';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Rekam Medis</h1>
    <p class="page-subtitle"><?= number_format($total) ?> catatan ditemukan</p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Daftar Rekam Medis</span>
    <form method="GET" action="" style="display:flex;gap:8px">
      <div class="search-bar">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
        <input type="text" name="q" placeholder="Cari pasien, diagnosa, ICD..." value="<?= sanitize($search) ?>">
      </div>
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <?php if ($search): ?><a href="?" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
    </form>
  </div>

  <?php if ($records): ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Pasien</th>
          <th>Diagnosa</th>
          <th>Dokter</th>
          <th>ICD</th>
          <th>Tindak Lanjut</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
          <td class="text-sm"><?= date('d/m/Y', strtotime($r['visit_date'])) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/patients/view?id=<?= $r['patient_id'] ?>"
               class="td-name" style="color:var(--blue)">
              <?= sanitize($r['patient_name']) ?>
            </a>
            <div class="text-xs text-muted">
              <?= $r['gender']==='L'?'L':'P' ?>, <?= calculateAge($r['birth_date']) ?> th
            </div>
          </td>
          <td class="text-sm" style="max-width:200px">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600"
                 title="<?= sanitize($r['diagnosis']) ?>">
              <?= sanitize($r['diagnosis']) ?>
            </div>
          </td>
          <td class="text-sm text-muted">dr. <?= sanitize($r['doctor_name']) ?></td>
          <td>
            <?php if ($r['icd_code']): ?>
            <span class="badge badge-called"><?= sanitize($r['icd_code']) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-xs">
            <?php if ($r['is_referred']): ?>
            <span class="badge badge-waiting">Dirujuk</span>
            <?php elseif ($r['follow_up_date']): ?>
            <span class="text-muted"><?= date('d/m/Y', strtotime($r['follow_up_date'])) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/medical_records/view?id=<?= $r['id'] ?>"
               class="btn btn-ghost btn-sm">Lihat</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="card-footer flex justify-between items-center">
    <span class="text-sm text-muted">Halaman <?= $page ?> dari <?= $totalPages ?></span>
    <div class="flex gap-1">
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $i===$page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/></svg></div>
    <p class="empty-state-title">Tidak ada rekam medis<?= $search ? ' yang sesuai pencarian' : '' ?></p>
  </div>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
