<?php
/**
 * medical_records/view.php
 * Tampilan detail rekam medis (Dokter & Admin).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','dokter','perawat']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flashMessage('error', 'ID rekam medis tidak valid.'); redirect('medical_records'); }

$stmt = $db->prepare("
    SELECT mr.*,
           p.name AS patient_name, p.birth_date, p.gender, p.blood_type,
           p.allergy, p.insurance_type, p.nik, p.phone, p.address,
           u.name AS doctor_name,
           ic.blood_pressure, ic.temperature, ic.pulse, ic.oxygen_saturation,
           ic.weight, ic.height, ic.nurse_id,
           un.name AS nurse_name,
           q.queue_number
    FROM medical_records mr
    LEFT JOIN patients p    ON p.id = mr.patient_id
    LEFT JOIN users u       ON u.id = mr.doctor_id
    LEFT JOIN queues q      ON q.id = mr.queue_id
    LEFT JOIN initial_checks ic ON ic.queue_id = mr.queue_id
    LEFT JOIN users un      ON un.id = ic.nurse_id
    WHERE mr.id = ?
");
$stmt->execute([$id]);
$rec = $stmt->fetch();

if (!$rec) { flashMessage('error', 'Rekam medis tidak ditemukan.'); redirect('medical_records'); }

// Akses dokter: hanya milik sendiri
if (currentUser()['role'] === 'dokter' && (int)$rec['doctor_id'] !== (int)currentUser()['id']) {
    flashMessage('error', 'Anda tidak memiliki akses ke rekam medis ini.');
    redirect('medical_records');
}

$pageTitle  = 'Detail Rekam Medis';
$activeMenu = 'medical_records';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Detail Rekam Medis</h1>
    <p class="page-subtitle">
      Antrian <?= sanitize($rec['queue_number'] ?? '—') ?> · <?= date('d F Y', strtotime($rec['visit_date'])) ?>
    </p>
  </div>
  <div class="flex gap-2">
    <a href="<?= BASE_URL ?>/patients/view?id=<?= $rec['patient_id'] ?>" class="btn btn-outline">Profil Pasien</a>
    <a href="<?= BASE_URL ?>/medical_records" class="btn btn-ghost">← Kembali</a>
  </div>
</div>

<!-- Patient info banner -->
<div class="patient-header" style="margin-bottom:20px">
  <div class="patient-avatar"><?= strtoupper(substr($rec['patient_name'], 0, 2)) ?></div>
  <div>
    <div style="font-size:16px;font-weight:700"><?= sanitize($rec['patient_name']) ?></div>
    <div class="text-sm text-muted">
      <?= $rec['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> ·
      <?= calculateAge($rec['birth_date']) ?> tahun ·
      Gol. <?= $rec['blood_type'] === 'unknown' ? '—' : sanitize($rec['blood_type']) ?> ·
      <?= sanitize($rec['insurance_type']) ?>
    </div>
    <div class="text-xs text-muted">NIK: <?= sanitize($rec['nik'] ?? '—') ?> · <?= sanitize($rec['phone'] ?? '—') ?></div>
    <?php if ($rec['allergy']): ?>
    <div class="allergy-badge mt-1">⚠ Alergi: <?= sanitize($rec['allergy']) ?></div>
    <?php endif; ?>
  </div>
  <!-- Vitals -->
  <?php if ($rec['blood_pressure'] || $rec['temperature'] || $rec['pulse']): ?>
  <div class="vitals-grid" style="grid-template-columns:repeat(4,auto);gap:8px;margin-left:auto;margin-bottom:0">
    <?php if ($rec['blood_pressure']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:15px"><?= sanitize($rec['blood_pressure']) ?></div>
      <div class="vital-unit">mmHg</div>
      <div class="vital-label">Tekanan Darah</div>
    </div>
    <?php endif; ?>
    <?php if ($rec['temperature']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:15px"><?= number_format($rec['temperature'],1) ?></div>
      <div class="vital-unit">°C</div>
      <div class="vital-label">Suhu</div>
    </div>
    <?php endif; ?>
    <?php if ($rec['pulse']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:15px"><?= (int)$rec['pulse'] ?></div>
      <div class="vital-unit">bpm</div>
      <div class="vital-label">Nadi</div>
    </div>
    <?php endif; ?>
    <?php if ($rec['oxygen_saturation']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:15px"><?= (int)$rec['oxygen_saturation'] ?></div>
      <div class="vital-unit">%</div>
      <div class="vital-label">SpO₂</div>
    </div>
    <?php endif; ?>
    <?php if ($rec['weight'] || $rec['height']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:15px"><?= $rec['weight'] ? $rec['weight'].'kg' : '—' ?></div>
      <div class="vital-unit"><?= ($rec['weight'] && $rec['height']) ? round($rec['weight']/(($rec['height']/100)**2),1).' BMI' : '' ?></div>
      <div class="vital-label">BB / <?= $rec['height'] ? $rec['height'].'cm' : '—' ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="two-col-grid">

  <!-- SOAP Content -->
  <div class="flex flex-col gap-3">

    <!-- S -->
    <div class="card">
      <div class="card-header" style="background:#E8F5E9;border-bottom-color:#A5D6A7">
        <span class="card-title" style="color:var(--green)">S — Subjective (Keluhan)</span>
        <?php if ($rec['nurse_name']): ?>
        <span class="text-xs text-muted">Anamnesis oleh <?= sanitize($rec['nurse_name']) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <p class="text-sm" style="white-space:pre-wrap"><?= sanitize($rec['chief_complaint']) ?></p>
      </div>
    </div>

    <!-- O -->
    <?php if ($rec['objective_notes'] || $rec['lab_notes']): ?>
    <div class="card">
      <div class="card-header" style="background:var(--blue-pale);border-bottom-color:var(--blue-muted)">
        <span class="card-title" style="color:var(--blue)">O — Objective (Pemeriksaan Fisik)</span>
      </div>
      <div class="card-body">
        <?php if ($rec['objective_notes']): ?>
        <div class="form-group">
          <div class="text-xs font-semibold text-muted mb-1">PEMERIKSAAN FISIK</div>
          <p class="text-sm" style="white-space:pre-wrap"><?= sanitize($rec['objective_notes']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($rec['lab_notes']): ?>
        <div class="form-group" style="margin-top:8px">
          <div class="text-xs font-semibold text-muted mb-1">LAB / PENUNJANG</div>
          <p class="text-sm" style="white-space:pre-wrap"><?= sanitize($rec['lab_notes']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- A -->
    <div class="card">
      <div class="card-header" style="background:var(--amber-bg);border-bottom-color:var(--amber-border)">
        <span class="card-title" style="color:var(--amber)">A — Assessment (Diagnosa)</span>
        <?php if ($rec['icd_code']): ?>
        <span class="badge badge-called"><?= sanitize($rec['icd_code']) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <p class="text-sm font-semibold" style="white-space:pre-wrap"><?= sanitize($rec['diagnosis']) ?></p>
      </div>
    </div>

    <!-- P -->
    <div class="card">
      <div class="card-header" style="background:var(--red-bg);border-bottom-color:var(--red-border)">
        <span class="card-title" style="color:var(--red)">P — Plan (Tindakan & Resep)</span>
      </div>
      <div class="card-body">
        <?php if ($rec['treatment']): ?>
        <div class="form-group">
          <div class="text-xs font-semibold text-muted mb-1">TINDAKAN MEDIS</div>
          <p class="text-sm" style="white-space:pre-wrap"><?= sanitize($rec['treatment']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($rec['prescription']): ?>
        <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:var(--r);padding:12px;margin-top:8px">
          <div class="text-xs font-semibold mb-1" style="color:#166534">💊 RESEP OBAT</div>
          <pre class="text-sm" style="margin:0;font-family:inherit;white-space:pre-wrap;color:#166534"><?= sanitize($rec['prescription']) ?></pre>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /left -->

  <!-- Right panel -->
  <div class="flex flex-col gap-3">

    <!-- Info kunjungan -->
    <div class="card">
      <div class="card-header"><span class="card-title">Info Kunjungan</span></div>
      <div class="card-body">
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:13px">
          <dt class="text-muted">Tanggal</dt>
          <dd><?= date('d F Y', strtotime($rec['visit_date'])) ?></dd>
          <dt class="text-muted">Dokter</dt>
          <dd>dr. <?= sanitize($rec['doctor_name']) ?></dd>
          <?php if ($rec['nurse_name']): ?>
          <dt class="text-muted">Perawat</dt>
          <dd><?= sanitize($rec['nurse_name']) ?></dd>
          <?php endif; ?>
          <dt class="text-muted">No. Antrian</dt>
          <dd><strong style="color:var(--blue)"><?= sanitize($rec['queue_number'] ?? '—') ?></strong></dd>
        </dl>
      </div>
    </div>

    <!-- Tindak Lanjut -->
    <?php if ($rec['follow_up_date'] || $rec['follow_up_notes'] || $rec['is_referred']): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Tindak Lanjut</span>
        <?php if ($rec['is_referred']): ?>
        <span class="badge badge-waiting">Dirujuk</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($rec['is_referred'] && $rec['referral_notes']): ?>
        <div style="background:var(--amber-bg);border:1px solid var(--amber-border);border-radius:var(--r);padding:10px;margin-bottom:10px">
          <div class="text-xs font-semibold text-muted mb-1">CATATAN RUJUKAN</div>
          <p class="text-sm"><?= sanitize($rec['referral_notes']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($rec['follow_up_date']): ?>
        <div class="text-sm"><strong>Kontrol Ulang:</strong> <?= date('d F Y', strtotime($rec['follow_up_date'])) ?></div>
        <?php endif; ?>
        <?php if ($rec['follow_up_notes']): ?>
        <p class="text-sm text-muted mt-1"><?= sanitize($rec['follow_up_notes']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Print action -->
    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <button onclick="window.print()" class="btn btn-outline w-full" style="justify-content:center">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/></svg>
          Cetak Rekam Medis
        </button>
        <a href="<?= BASE_URL ?>/medical_records" class="btn btn-ghost w-full" style="justify-content:center">
          ← Kembali ke Daftar
        </a>
      </div>
    </div>

  </div><!-- /right -->

</div>
<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../includes/layout.php';
