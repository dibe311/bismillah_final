<?php
/**
 * patients/my_records.php
 * Pasien melihat rekam medis sendiri.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole('pasien');

$db   = getDB();
$user = currentUser();

$patientRec = $db->prepare("SELECT id, name FROM patients WHERE user_id = ? LIMIT 1");
$patientRec->execute([$user['id']]);
$patientRec = $patientRec->fetch();

$records = [];
if ($patientRec) {
    $stmt = $db->prepare("
        SELECT mr.id, mr.visit_date, mr.diagnosis, mr.icd_code, mr.prescription,
               mr.treatment, mr.follow_up_date, mr.is_referred,
               u.name AS doctor_name
        FROM medical_records mr
        LEFT JOIN users u ON u.id = mr.doctor_id
        WHERE mr.patient_id = ?
        ORDER BY mr.visit_date DESC
    ");
    $stmt->execute([$patientRec['id']]);
    $records = $stmt->fetchAll();
}

$pageTitle  = 'Rekam Medis Saya';
$activeMenu = 'my_records';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Rekam Medis Saya</h1>
    <p class="page-subtitle"><?= $patientRec ? sanitize($patientRec['name']) : sanitize($user['name']) ?></p>
  </div>
</div>

<?php if (!$patientRec): ?>
<div class="alert alert-info">
  <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
  Data pasien Anda belum terdaftar. Silakan hubungi petugas pendaftaran.
</div>
<?php elseif (!$records): ?>
<div class="card">
  <div class="empty-state" style="padding:48px">
    <div class="empty-state-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/></svg></div>
    <p class="empty-state-title">Belum ada rekam medis</p>
    <p class="text-sm text-muted">Rekam medis akan muncul setelah Anda menjalani pemeriksaan.</p>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Riwayat Kunjungan</span>
    <span class="badge badge-done"><?= count($records) ?> kunjungan</span>
  </div>
  <div class="card-body" style="padding:12px">
    <?php foreach ($records as $r): ?>
    <div style="border:1px solid var(--gray-200);border-radius:var(--r);padding:16px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
        <div>
          <div class="text-xs text-muted"><?= date('d F Y', strtotime($r['visit_date'])) ?></div>
          <div style="font-weight:700;margin-top:2px">dr. <?= sanitize($r['doctor_name']) ?></div>
        </div>
        <?php if ($r['icd_code']): ?>
        <span class="badge badge-called"><?= sanitize($r['icd_code']) ?></span>
        <?php endif; ?>
      </div>
      <div style="background:var(--gray-50);border-radius:var(--r);padding:10px;margin-bottom:8px">
        <div class="text-xs font-semibold text-muted mb-1">DIAGNOSA</div>
        <div class="text-sm"><?= sanitize($r['diagnosis']) ?></div>
      </div>
      <?php if ($r['prescription']): ?>
      <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--r);padding:10px;margin-bottom:8px">
        <div class="text-xs font-semibold mb-1" style="color:#166534">💊 RESEP OBAT</div>
        <pre class="text-sm" style="margin:0;font-family:inherit;white-space:pre-wrap;color:#166534"><?= sanitize($r['prescription']) ?></pre>
      </div>
      <?php endif; ?>
      <?php if ($r['follow_up_date']): ?>
      <div class="text-xs text-muted">Kontrol ulang: <?= date('d F Y', strtotime($r['follow_up_date'])) ?></div>
      <?php endif; ?>
      <?php if ($r['is_referred']): ?>
      <span class="badge badge-waiting" style="margin-top:4px">Dirujuk</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
