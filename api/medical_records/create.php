<?php
/**
 * medical_records/create.php — MODULE 4 (CORE CLINICAL ENGINE)
 * Doctor-only. SOAP medical record form.
 * Pre-fills data from initial_checks (S from chief_complaint, O from vitals).
 * On submit: INSERT medical_records + UPDATE queue status → 'done'.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole('dokter');

$db   = getDB();
$user = currentUser();

$queueId    = (int)($_GET['queue_id']    ?? $_POST['queue_id']    ?? 0);
$patientIdQ = (int)($_GET['patient_id']  ?? 0);

if (!$queueId) { flashMessage('error', 'ID antrian tidak valid.'); redirect('queues'); }

// Load queue — must belong to this doctor
$q = $db->prepare("
    SELECT q.id, q.queue_number, q.status, q.queue_date, q.doctor_id,
           p.id   AS patient_id,   p.name  AS patient_name,
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

// Prevent duplicate record
$dupRec = $db->prepare("SELECT id FROM medical_records WHERE queue_id = ?");
$dupRec->execute([$queueId]);
if ($dupRec->fetch()) {
    flashMessage('error', 'Rekam medis untuk antrian ini sudah dibuat.');
    redirect('queues');
}

// Load initial check (O data + nurse's S data)
$ic = $db->prepare("
    SELECT ic.*, u.name AS nurse_name
    FROM initial_checks ic
    LEFT JOIN users u ON u.id = ic.nurse_id
    WHERE ic.queue_id = ?
");
$ic->execute([$queueId]);
$initCheck = $ic->fetch();

// Patient's last records for context
$prevRecords = $db->prepare("
    SELECT mr.visit_date, mr.diagnosis, mr.prescription, mr.treatment
    FROM medical_records mr
    WHERE mr.patient_id = ? AND mr.queue_id != ?
    ORDER BY mr.visit_date DESC LIMIT 5
");
$prevRecords->execute([$queue['patient_id'], $queueId]);
$prevRecords = $prevRecords->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'chief_complaint'  => trim($_POST['chief_complaint']  ?? ''),
        'objective_notes'  => trim($_POST['objective_notes']  ?? ''),
        'diagnosis'        => trim($_POST['diagnosis']        ?? ''),
        'icd_code'         => strtoupper(trim($_POST['icd_code'] ?? '')),
        'treatment'        => trim($_POST['treatment']        ?? ''),
        'prescription'     => trim($_POST['prescription']     ?? ''),
        'lab_notes'        => trim($_POST['lab_notes']        ?? ''),
        'follow_up_date'   => $_POST['follow_up_date']  ?? null ?: null,
        'follow_up_notes'  => trim($_POST['follow_up_notes']  ?? ''),
        'is_referred'      => isset($_POST['is_referred']) ? 1 : 0,
        'referral_notes'   => trim($_POST['referral_notes']   ?? ''),
    ];

    if (empty($data['chief_complaint'])) $errors[] = 'Keluhan (Subjektif) wajib diisi.';
    if (empty($data['diagnosis']))       $errors[] = 'Diagnosa (Assessment) wajib diisi.';

    if (!$errors) {
        $db->beginTransaction();
        try {
            $ins = $db->prepare("
                INSERT INTO medical_records
                  (queue_id, patient_id, doctor_id, visit_date,
                   chief_complaint, objective_notes, diagnosis, icd_code, treatment,
                   prescription, lab_notes, follow_up_date, follow_up_notes,
                   is_referred, referral_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $queueId,
                $queue['patient_id'],
                $user['id'],
                $queue['queue_date'],
                $data['chief_complaint'],
                $data['objective_notes']  ?: null,
                $data['diagnosis'],
                $data['icd_code']         ?: null,
                $data['treatment']        ?: null,
                $data['prescription']     ?: null,
                $data['lab_notes']        ?: null,
                $data['follow_up_date'],
                $data['follow_up_notes']  ?: null,
                $data['is_referred'],
                $data['referral_notes']   ?: null,
            ]);
            $newRecordId = $db->lastInsertId();

            // Mark queue done
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

<!-- Patient info banner -->
<div class="patient-header" style="margin-bottom:20px">
  <div class="patient-avatar"><?= strtoupper(substr($queue['patient_name'],0,2)) ?></div>
  <div>
    <div style="font-size:16px;font-weight:700"><?= sanitize($queue['patient_name']) ?></div>
    <div class="text-sm text-muted">
      <?= $queue['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> ·
      <?= calculateAge($queue['birth_date']) ?> tahun ·
      Gol. <?= $queue['blood_type']==='unknown' ? '—' : sanitize($queue['blood_type']) ?> ·
      <?= sanitize($queue['insurance_type']) ?>
    </div>
    <?php if ($queue['allergy']): ?>
    <div class="allergy-badge mt-1">⚠ Alergi: <?= sanitize($queue['allergy']) ?></div>
    <?php endif; ?>
  </div>

  <!-- Vitals from nurse -->
  <?php if ($initCheck): ?>
  <div class="vitals-grid" style="grid-template-columns:repeat(4,auto);gap:8px;margin-left:auto;margin-bottom:0">
    <?php if ($initCheck['blood_pressure']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= sanitize($initCheck['blood_pressure']) ?></div>
      <div class="vital-unit">mmHg</div>
      <div class="vital-label">Tekanan Darah</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['temperature']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= number_format($initCheck['temperature'],1) ?></div>
      <div class="vital-unit">°C</div>
      <div class="vital-label">Suhu</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['pulse']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= (int)$initCheck['pulse'] ?></div>
      <div class="vital-unit">bpm</div>
      <div class="vital-label">Nadi</div>
    </div>
    <?php endif; ?>
    <?php if ($initCheck['oxygen_saturation']): ?>
    <div class="vital-item">
      <div class="vital-value" style="font-size:16px"><?= (int)$initCheck['oxygen_saturation'] ?></div>
      <div class="vital-unit">%</div>
      <div class="vital-label">SpO₂</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
  <ul style="margin:0;padding-left:16px">
    <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="">
  <input type="hidden" name="queue_id" value="<?= $queueId ?>">

  <div class="two-col-grid">

    <!-- Left: SOAP form -->
    <div class="flex flex-col gap-3">

      <!-- S — Subjective -->
      <div class="card">
        <div class="card-header" style="background:#E8F5E9;border-bottom-color:#A5D6A7">
          <span class="card-title" style="color:var(--green)">S — Subjective (Keluhan Pasien)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Keluhan Utama <span class="req">*</span></label>
            <textarea class="form-control" name="chief_complaint" rows="4" required
                      placeholder="Deskripsikan keluhan utama dan anamnesis pasien..."><?= sanitize(
              $_POST['chief_complaint'] ?? $initCheck['chief_complaint'] ?? ''
            ) ?></textarea>
            <?php if ($initCheck && $initCheck['chief_complaint']): ?>
            <div class="form-hint">
              📋 Catatan perawat (<?= sanitize($initCheck['nurse_name']) ?>):
              "<?= sanitize(mb_substr($initCheck['chief_complaint'],0,120)) ?>..."
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- O — Objective -->
      <div class="card">
        <div class="card-header" style="background:var(--blue-pale);border-bottom-color:var(--blue-muted)">
          <span class="card-title" style="color:var(--blue)">O — Objective (Pemeriksaan Fisik)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Hasil Pemeriksaan Fisik</label>
            <textarea class="form-control" name="objective_notes" rows="4"
                      placeholder="Kondisi umum, pemeriksaan sistem organ, temuan fisik..."><?= sanitize($_POST['objective_notes'] ?? '') ?></textarea>
            <div class="form-hint">Tanda vital otomatis tercatat dari pemeriksaan perawat.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Hasil Lab / Penunjang</label>
            <textarea class="form-control" name="lab_notes" rows="2"
                      placeholder="Hasil lab, rontgen, USG, dll (jika ada)..."><?= sanitize($_POST['lab_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- A — Assessment -->
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

      <!-- P — Plan -->
      <div class="card">
        <div class="card-header" style="background:var(--red-bg);border-bottom-color:var(--red-border)">
          <span class="card-title" style="color:var(--red)">P — Plan (Tindakan & Resep)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tindakan Medis</label>
            <textarea class="form-control" name="treatment" rows="3"
                      placeholder="Prosedur, tindakan, edukasi, saran gaya hidup..."><?= sanitize($_POST['treatment'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Resep Obat</label>
            <textarea class="form-control" name="prescription" rows="4"
                      placeholder="Nama obat, dosis, frekuensi, durasi&#10;Contoh: Amoxicillin 500mg 3x1 tab, 5 hari"><?= sanitize($_POST['prescription'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

    </div><!-- /left -->

    <!-- Right: follow-up + history -->
    <div class="flex flex-col gap-3">

      <!-- Follow-up & referral -->
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
                      placeholder="Instruksi khusus untuk kontrol berikutnya..."><?= sanitize($_POST['follow_up_notes'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
              <input type="checkbox" name="is_referred"
                     <?= ($_POST['is_referred'] ?? 0) ? 'checked' : '' ?>
                     style="width:16px;height:16px">
              Pasien Dirujuk
            </label>
          </div>
          <div class="form-group" id="referralNotesGroup"
               style="display:<?= ($_POST['is_referred'] ?? 0) ? 'block' : 'none' ?>">
            <label class="form-label">Catatan Rujukan</label>
            <textarea class="form-control" name="referral_notes" rows="2"
                      placeholder="Dirujuk ke: RS / Spesialis / Fasilitas..."><?= sanitize($_POST['referral_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Riwayat pasien sebelumnya -->
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
            <div class="text-xs text-muted">
              💊 <?= sanitize(mb_substr($pr['prescription'],0,80)) ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Nurse notes from initial check -->
      <?php if ($initCheck && $initCheck['notes']): ?>
      <div class="card" style="border-color:var(--amber-border)">
        <div class="card-header" style="background:var(--amber-bg)">
          <span class="card-title" style="color:var(--amber)">Catatan Perawat</span>
        </div>
        <div class="card-body">
          <p class="text-sm"><?= sanitize($initCheck['notes']) ?></p>
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

    </div><!-- /right -->

  </div>
</form>

<script>
// Toggle referral notes
document.querySelector('input[name="is_referred"]').addEventListener('change', function() {
  document.getElementById('referralNotesGroup').style.display = this.checked ? 'block' : 'none';
});
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
