<?php
/**
 * medical_records/create.php — disesuaikan dengan struktur tabel DB
 * Kolom DB: complaints, diagnosis, treatment, prescription, notes,
 *           icd_code, is_referred, follow_up_date,
 *           + kolom baru: chief_complaint, objective_notes, lab_notes,
 *                         follow_up_notes, referral_notes
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole('dokter');

$db   = getDB();
$user = currentUser();

$queueId = (int)($_GET['queue_id'] ?? $_POST['queue_id'] ?? 0);
if (!$queueId) { flashMessage('error', 'ID antrian tidak valid.'); redirect('queues'); }

// Load antrian — harus milik dokter ini
$q = $db->prepare("
    SELECT q.id, q.queue_number, q.status, q.queue_date, q.doctor_id,
           p.id AS patient_id, p.name AS patient_name,
           p.birth_date, p.gender, p.blood_type, p.allergy, p.insurance_type
    FROM queues q
    LEFT JOIN patients p ON q.patient_id = p.id
    WHERE q.id = ? AND q.doctor_id = ?
");
$q->execute([$queueId, $user['id']]);
$queue = $q->fetch();

if (!$queue) {
    flashMessage('error', 'Antrian tidak ditemukan atau tidak ditugaskan ke Anda.');
    redirect('queues');
}

// Cegah rekam medis duplikat
$dupRec = $db->prepare("SELECT id FROM medical_records WHERE queue_id = ?");
$dupRec->execute([$queueId]);
if ($dupRec->fetch()) {
    flashMessage('error', 'Rekam medis untuk antrian ini sudah dibuat.');
    redirect('queues');
}

// Load initial check dari perawat
$ic = $db->prepare("
    SELECT ic.*, u.name AS nurse_name
    FROM initial_checks ic
    LEFT JOIN users u ON u.id = ic.nurse_id
    WHERE ic.queue_id = ?
");
$ic->execute([$queueId]);
$initCheck = $ic->fetch();

// Riwayat kunjungan sebelumnya
$prevRecords = $db->prepare("
    SELECT mr.visit_date, mr.diagnosis, mr.prescription
    FROM medical_records mr
    WHERE mr.patient_id = ? AND mr.queue_id != ?
    ORDER BY mr.visit_date DESC LIMIT 5
");
$prevRecords->execute([$queue['patient_id'], $queueId]);
$prevRecords = $prevRecords->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $objective_notes = trim($_POST['objective_notes'] ?? '');
    $diagnosis       = trim($_POST['diagnosis']       ?? '');
    $icd_code        = strtoupper(trim($_POST['icd_code'] ?? ''));
    $treatment       = trim($_POST['treatment']       ?? '');
    $prescription    = trim($_POST['prescription']    ?? '');
    $lab_notes       = trim($_POST['lab_notes']       ?? '');
    $follow_up_date  = $_POST['follow_up_date']  ?? null ?: null;
    $follow_up_notes = trim($_POST['follow_up_notes'] ?? '');
    $is_referred     = isset($_POST['is_referred']) ? 1 : 0;
    $referral_notes  = trim($_POST['referral_notes']  ?? '');

    if (empty($chief_complaint)) $errors[] = 'Keluhan (Subjektif) wajib diisi.';
    if (empty($diagnosis))       $errors[] = 'Diagnosa (Assessment) wajib diisi.';

    if (!$errors) {
        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO medical_records
                    (queue_id, patient_id, doctor_id, visit_date,
                     chief_complaint, complaints,
                     objective_notes, diagnosis, icd_code,
                     treatment, prescription,
                     lab_notes, notes,
                     follow_up_date, follow_up_notes,
                     is_referred, referral_notes)
                VALUES (?, ?, ?, ?,  ?, ?,  ?, ?, ?,  ?, ?,  ?, ?,  ?, ?,  ?, ?)
            ")->execute([
                $queueId,
                $queue['patient_id'],
                $user['id'],
                $queue['queue_date'],
                $chief_complaint,           // kolom baru
                $chief_complaint,           // kolom lama 'complaints' — isi sama
                $objective_notes ?: null,
                $diagnosis,
                $icd_code        ?: null,
                $treatment       ?: null,
                $prescription    ?: null,
                $lab_notes       ?: null,
                $lab_notes       ?: null,   // kolom lama 'notes' — isi sama
                $follow_up_date,
                $follow_up_notes ?: null,
                $is_referred,
                $referral_notes  ?: null,
            ]);
            $newRecordId = $db->lastInsertId();

            // Tandai antrian selesai
            $db->prepare("UPDATE queues SET status='done', done_at=NOW(), updated_at=NOW() WHERE id=?")
               ->execute([$queueId]);

            $db->commit();
            flashMessage('success', "Rekam medis untuk {$queue['patient_name']} berhasil disimpan.");
            redirect("medical_records/view?id=$newRecordId");

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

$pageTitle  = 'Input Rekam Medis';
$activeMenu = 'medical_records';
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Rekam Medis — SOAP</h1>
    <p class="page-subtitle">Antrian <?= sanitize($queue['queue_number']) ?> · <?= date('d F Y', strtotime($queue['queue_date'])) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">← Antrian</a>
</div>

<!-- Banner pasien -->
<div class="patient-header" style="margin-bottom:20px">
  <div class="patient-avatar"><?= strtoupper(substr($queue['patient_name'],0,2)) ?></div>
  <div>
    <div style="font-size:16px;font-weight:700"><?= sanitize($queue['patient_name']) ?></div>
    <div class="text-sm text-muted">
      <?= $queue['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> ·
      <?= calculateAge($queue['birth_date']) ?> tahun ·
      <?= sanitize($queue['insurance_type']) ?>
    </div>
    <?php if ($queue['allergy']): ?>
    <div class="allergy-badge mt-1">⚠ Alergi: <?= sanitize($queue['allergy']) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($initCheck): ?>
  <div class="vitals-grid" style="grid-template-columns:repeat(4,auto);gap:8px;margin-left:auto;margin-bottom:0">
    <?php if ($initCheck['blood_pressure']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= sanitize($initCheck['blood_pressure']) ?></div>
      <div class="vital-unit">mmHg</div><div class="vital-label">TD</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['temperature']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= number_format($initCheck['temperature'],1) ?></div>
      <div class="vital-unit">°C</div><div class="vital-label">Suhu</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['pulse']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= (int)$initCheck['pulse'] ?></div>
      <div class="vital-unit">bpm</div><div class="vital-label">Nadi</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['oxygen_saturation']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= (int)$initCheck['oxygen_saturation'] ?></div>
      <div class="vital-unit">%</div><div class="vital-label">SpO₂</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <ul style="margin:0;padding-left:16px">
    <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="">
  <input type="hidden" name="queue_id" value="<?= $queueId ?>">

  <div class="two-col-grid">

    <!-- KIRI: SOAP -->
    <div class="flex flex-col gap-3">

      <!-- S -->
      <div class="card">
        <div class="card-header" style="background:#E8F5E9;border-bottom-color:#A5D6A7">
          <span class="card-title" style="color:var(--green)">S — Subjective (Keluhan Pasien)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Keluhan Utama <span class="req">*</span></label>
            <textarea class="form-control" name="chief_complaint" rows="4" required
                      placeholder="Keluhan utama dan anamnesis pasien..."><?= sanitize(
              $_POST['chief_complaint'] ?? $initCheck['chief_complaint'] ?? ''
            ) ?></textarea>
            <?php if ($initCheck && $initCheck['chief_complaint']): ?>
            <div class="form-hint">📋 Catatan perawat (<?= sanitize($initCheck['nurse_name'] ?? '') ?>):
              "<?= sanitize(mb_substr($initCheck['chief_complaint'],0,120)) ?>"
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- O -->
      <div class="card">
        <div class="card-header" style="background:var(--blue-pale);border-bottom-color:var(--blue-muted)">
          <span class="card-title" style="color:var(--blue)">O — Objective (Pemeriksaan Fisik)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Hasil Pemeriksaan Fisik</label>
            <textarea class="form-control" name="objective_notes" rows="4"
                      placeholder="Kondisi umum, pemeriksaan sistem organ, temuan fisik..."><?= sanitize($_POST['objective_notes'] ?? '') ?></textarea>
            <div class="form-hint">Tanda vital dari perawat sudah tercatat otomatis.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Hasil Lab / Penunjang</label>
            <textarea class="form-control" name="lab_notes" rows="2"
                      placeholder="Hasil lab, rontgen, USG, dll..."><?= sanitize($_POST['lab_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- A -->
      <div class="card">
        <div class="card-header" style="background:var(--amber-bg);border-bottom-color:var(--amber-border)">
          <span class="card-title" style="color:var(--amber)">A — Assessment (Diagnosa)</span>
        </div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group flex-1">
              <label class="form-label">Diagnosa <span class="req">*</span></label>
              <textarea class="form-control" name="diagnosis" rows="3" required
                        placeholder="Diagnosa kerja / diagnosa banding..."><?= sanitize($_POST['diagnosis'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="flex:0 0 120px">
              <label class="form-label">Kode ICD-10</label>
              <input class="form-control" type="text" name="icd_code"
                     placeholder="A00, J06.9" maxlength="10"
                     value="<?= sanitize($_POST['icd_code'] ?? '') ?>"
                     style="text-transform:uppercase">
            </div>
          </div>
        </div>
      </div>

      <!-- P -->
      <div class="card">
        <div class="card-header" style="background:var(--red-bg);border-bottom-color:var(--red-border)">
          <span class="card-title" style="color:var(--red)">P — Plan (Tindakan & Resep)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tindakan Medis</label>
            <textarea class="form-control" name="treatment" rows="3"
                      placeholder="Prosedur, tindakan, edukasi..."><?= sanitize($_POST['treatment'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Resep Obat</label>
            <textarea class="form-control" name="prescription" rows="4"
                      placeholder="Nama obat, dosis, frekuensi, durasi..."><?= sanitize($_POST['prescription'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

    </div>

    <!-- KANAN: Tindak lanjut + riwayat -->
    <div class="flex flex-col gap-3">

      <div class="card">
        <div class="card-header"><span class="card-title">Tindak Lanjut</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tanggal Kontrol Ulang</label>
            <input class="form-control" type="date" name="follow_up_date"
                   value="<?= sanitize($_POST['follow_up_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Catatan Kontrol</label>
            <textarea class="form-control" name="follow_up_notes" rows="2"
                      placeholder="Instruksi untuk kontrol berikutnya..."><?= sanitize($_POST['follow_up_notes'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
              <input type="checkbox" name="is_referred" id="isReferred"
                     <?= ($_POST['is_referred'] ?? 0) ? 'checked' : '' ?>
                     style="width:16px;height:16px">
              Pasien Dirujuk
            </label>
          </div>
          <div class="form-group" id="referralNotesGroup"
               style="display:<?= ($_POST['is_referred'] ?? 0) ? 'block' : 'none' ?>">
            <label class="form-label">Catatan Rujukan</label>
            <textarea class="form-control" name="referral_notes" rows="2"
                      placeholder="Dirujuk ke: RS / Spesialis..."><?= sanitize($_POST['referral_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <?php if ($prevRecords): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title">Riwayat Kunjungan Sebelumnya</span>
          <span class="badge badge-draft"><?= count($prevRecords) ?> kunjungan</span>
        </div>
        <div class="card-body" style="padding:12px">
          <?php foreach ($prevRecords as $pr): ?>
          <div style="border-bottom:1px solid var(--gray-200);padding:8px 0">
            <div class="text-xs text-muted"><?= date('d/m/Y', strtotime($pr['visit_date'])) ?></div>
            <div class="text-sm font-semibold"><?= sanitize($pr['diagnosis']) ?></div>
            <?php if ($pr['prescription']): ?>
            <div class="text-xs text-muted">💊 <?= sanitize(mb_substr($pr['prescription'],0,80)) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($initCheck && $initCheck['notes']): ?>
      <div class="card" style="border-color:var(--amber-border)">
        <div class="card-header" style="background:var(--amber-bg)">
          <span class="card-title" style="color:var(--amber)">Catatan Perawat</span>
        </div>
        <div class="card-body">
          <p class="text-sm"><?= nl2br(sanitize($initCheck['notes'])) ?></p>
          <div class="text-xs text-muted mt-2">— <?= sanitize($initCheck['nurse_name'] ?? 'Perawat') ?>, <?= date('H:i', strtotime($initCheck['checked_at'])) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex justify-end gap-2">
        <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-navy btn-lg">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
          Simpan & Selesaikan
        </button>
      </div>

    </div>
  </div>
</form>

<script>
document.getElementById('isReferred').addEventListener('change', function() {
    document.getElementById('referralNotesGroup').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';