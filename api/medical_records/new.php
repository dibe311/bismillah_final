<?php
/**
 * medical_records/new.php
 * Dokter dapat mencatat rekam medis langsung (tanpa perlu antrian aktif).
 * Termasuk: resep obat, diagnosis, riwayat penyakit, dll.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['dokter','admin']);

$db   = getDB();
$user = currentUser();

// Pre-fill patient if coming from patient view
$prePatientId = (int)($_GET['patient_id'] ?? 0);
$prePatient   = null;
if ($prePatientId) {
    $pp = $db->prepare("SELECT id, name, birth_date, gender, blood_type, allergy, insurance_type FROM patients WHERE id = ? AND is_active = 1");
    $pp->execute([$prePatientId]);
    $prePatient = $pp->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $data = [
        'visit_date'      => $_POST['visit_date']      ?? date('Y-m-d'),
        'chief_complaint' => trim($_POST['chief_complaint'] ?? ''),
        'objective_notes' => trim($_POST['objective_notes'] ?? ''),
        'diagnosis'       => trim($_POST['diagnosis']       ?? ''),
        'icd_code'        => strtoupper(trim($_POST['icd_code'] ?? '')),
        'treatment'       => trim($_POST['treatment']       ?? ''),
        'prescription'    => trim($_POST['prescription']    ?? ''),
        'lab_notes'       => trim($_POST['lab_notes']       ?? ''),
        'follow_up_date'  => $_POST['follow_up_date']  ?? null ?: null,
        'follow_up_notes' => trim($_POST['follow_up_notes'] ?? ''),
        'is_referred'     => isset($_POST['is_referred']) ? 1 : 0,
        'referral_notes'  => trim($_POST['referral_notes']  ?? ''),
    ];

    if (!$patientId)                 $errors[] = 'Pilih pasien terlebih dahulu.';
    if (empty($data['chief_complaint'])) $errors[] = 'Keluhan utama wajib diisi.';
    if (empty($data['diagnosis']))       $errors[] = 'Diagnosa wajib diisi.';

    if (!$errors) {
        $ins = $db->prepare("
            INSERT INTO medical_records
              (patient_id, doctor_id, visit_date,
               chief_complaint, objective_notes, diagnosis, icd_code, treatment,
               prescription, lab_notes, follow_up_date, follow_up_notes,
               is_referred, referral_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $patientId,
            $user['id'],
            $data['visit_date'],
            $data['chief_complaint'],
            $data['objective_notes'] ?: null,
            $data['diagnosis'],
            $data['icd_code']        ?: null,
            $data['treatment']       ?: null,
            $data['prescription']    ?: null,
            $data['lab_notes']       ?: null,
            $data['follow_up_date'],
            $data['follow_up_notes'] ?: null,
            $data['is_referred'],
            $data['referral_notes']  ?: null,
        ]);
        $newId = $db->lastInsertId();
        flashMessage('success', 'Rekam medis berhasil dicatat.');
        redirect("medical_records/view?id=$newId");
    }
}

$pageTitle  = 'Catat Rekam Medis';
$activeMenu = 'medical_records_new';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Catat Rekam Medis</h1>
    <p class="page-subtitle">Input mandiri rekam medis pasien oleh dokter</p>
  </div>
  <a href="<?= BASE_URL ?>/medical_records" class="btn btn-outline">← Kembali</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
  <ul style="margin:0;padding-left:16px">
    <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="" id="newRecordForm">

  <!-- Pilih Pasien -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Data Pasien</span></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Pasien <span class="req">*</span></label>
        <div class="inline-patient-search">
          <input class="form-control" type="text" id="patientSearch"
                 placeholder="Ketik nama atau NIK pasien..."
                 value="<?= $prePatient ? sanitize($prePatient['name']) : '' ?>"
                 autocomplete="off">
          <div class="patient-dropdown" id="patientDropdown"></div>
        </div>
        <input type="hidden" name="patient_id" id="patientId" value="<?= $prePatientId ?>">
      </div>

      <!-- Patient info display -->
      <div id="patientInfoCard" style="display:<?= $prePatient ? 'block' : 'none' ?>">
        <?php if ($prePatient): ?>
        <div class="patient-header" style="margin-top:12px">
          <div class="patient-avatar"><?= strtoupper(substr($prePatient['name'],0,2)) ?></div>
          <div>
            <div style="font-weight:700" id="infoName"><?= sanitize($prePatient['name']) ?></div>
            <div class="text-sm text-muted" id="infoSub">
              <?= $prePatient['gender']==='L'?'Laki-laki':'Perempuan' ?> ·
              <?= calculateAge($prePatient['birth_date']) ?> tahun ·
              Gol. <?= $prePatient['blood_type']==='unknown'?'—':sanitize($prePatient['blood_type']) ?>
            </div>
            <?php if ($prePatient['allergy']): ?>
            <div class="allergy-badge mt-1" id="infoAllergy">⚠ Alergi: <?= sanitize($prePatient['allergy']) ?></div>
            <?php else: ?>
            <div id="infoAllergy" style="display:none"></div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="patient-header" style="margin-top:12px;display:none" id="patientHeaderRow">
          <div class="patient-avatar" id="infoAvatar"></div>
          <div>
            <div style="font-weight:700" id="infoName"></div>
            <div class="text-sm text-muted" id="infoSub"></div>
            <div id="infoAllergy" class="allergy-badge mt-1" style="display:none"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="form-group" style="margin-top:12px">
        <label class="form-label">Tanggal Kunjungan</label>
        <input class="form-control" type="date" name="visit_date"
               value="<?= sanitize($_POST['visit_date'] ?? date('Y-m-d')) ?>"
               style="width:auto">
      </div>
    </div>
  </div>

  <div class="two-col-grid">
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
                      placeholder="Anamnesis: keluhan utama, onset, durasi, riwayat penyakit..."><?= sanitize($_POST['chief_complaint'] ?? '') ?></textarea>
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
                      placeholder="Kondisi umum, tanda vital, pemeriksaan sistem organ..."><?= sanitize($_POST['objective_notes'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Hasil Lab / Penunjang</label>
            <textarea class="form-control" name="lab_notes" rows="2"
                      placeholder="Hasil lab, rontgen, USG, EKG, dll..."><?= sanitize($_POST['lab_notes'] ?? '') ?></textarea>
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
            <div class="form-group" style="flex:0 0 130px">
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
          <span class="card-title" style="color:var(--red)">P — Plan (Tindakan & Resep Obat)</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tindakan Medis</label>
            <textarea class="form-control" name="treatment" rows="3"
                      placeholder="Prosedur, injeksi, infus, edukasi, saran gaya hidup..."><?= sanitize($_POST['treatment'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label" style="display:flex;align-items:center;gap:6px">
              <svg viewBox="0 0 20 20" fill="currentColor" width="14" style="color:var(--green)"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              Resep Obat
            </label>
            <textarea class="form-control" name="prescription" rows="5"
                      placeholder="Format resep:&#10;1. Nama Obat - Dosis - Frekuensi - Durasi&#10;   Contoh: Amoxicillin 500mg 3x1 tab, 5 hari&#10;2. Paracetamol 500mg 3x1 tab, jika demam&#10;3. ..."
                      style="font-family:monospace;font-size:13px"><?= sanitize($_POST['prescription'] ?? '') ?></textarea>
            <div class="form-hint">Tulis setiap obat di baris baru untuk keterbacaan yang lebih baik.</div>
          </div>
        </div>
      </div>

    </div><!-- /left -->

    <div class="flex flex-col gap-3">

      <!-- Tindak lanjut -->
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
                      placeholder="Instruksi kontrol berikutnya..."><?= sanitize($_POST['follow_up_notes'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
              <input type="checkbox" name="is_referred"
                     <?= ($_POST['is_referred'] ?? 0) ? 'checked' : '' ?>
                     id="referredCheck" style="width:16px;height:16px">
              Pasien Dirujuk ke Fasilitas Lain
            </label>
          </div>
          <div id="referralBox" style="display:<?= ($_POST['is_referred'] ?? 0) ? 'block' : 'none' ?>">
            <div class="form-group">
              <label class="form-label">Catatan Rujukan</label>
              <textarea class="form-control" name="referral_notes" rows="2"
                        placeholder="Dirujuk ke: RS / Spesialis / Fasilitas..."><?= sanitize($_POST['referral_notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Template resep cepat -->
      <div class="card" style="border-color:var(--blue-muted);background:var(--blue-pale)">
        <div class="card-header" style="background:transparent">
          <span class="card-title" style="color:var(--blue)">Template Resep Cepat</span>
        </div>
        <div class="card-body" style="padding:12px;display:flex;flex-direction:column;gap:6px">
          <button type="button" class="btn btn-outline btn-sm" onclick="addTemplate('ISPA')">+ ISPA / Flu</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="addTemplate('Demam')">+ Demam / Infeksi</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="addTemplate('Hipertensi')">+ Hipertensi</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="addTemplate('DM')">+ Diabetes (DM)</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="addTemplate('Gastritis')">+ Gastritis / Maag</button>
          <div class="form-hint" style="margin-top:4px">Klik untuk mengisi template resep umum.</div>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex justify-end gap-2">
        <a href="<?= BASE_URL ?>/medical_records" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-navy btn-lg" id="submitBtn" disabled>
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
          Simpan Rekam Medis
        </button>
      </div>

    </div><!-- /right -->
  </div>
</form>

<script>
// Patient search autocomplete
(function() {
  const searchInput = document.getElementById('patientSearch');
  const dropdown    = document.getElementById('patientDropdown');
  const hiddenId    = document.getElementById('patientId');
  const submitBtn   = document.getElementById('submitBtn');
  const headerRow   = document.getElementById('patientHeaderRow');
  const infoAvatar  = document.getElementById('infoAvatar');
  const infoName    = document.getElementById('infoName');
  const infoSub     = document.getElementById('infoSub');
  const infoAllergy = document.getElementById('infoAllergy');
  const infoCard    = document.getElementById('patientInfoCard');

  if (hiddenId.value) submitBtn.disabled = false;

  let timer;
  searchInput.addEventListener('input', () => {
    hiddenId.value = '';
    submitBtn.disabled = true;
    clearTimeout(timer);
    const q = searchInput.value.trim();
    if (q.length < 2) { dropdown.classList.remove('open'); return; }
    timer = setTimeout(async () => {
      try {
        const res  = await fetch(`/apb/search_patients?q=${encodeURIComponent(q)}`, {credentials:'same-origin'});
        const data = await res.json();
        dropdown.innerHTML = '';
        if (!data.length) {
          dropdown.innerHTML = '<div class="patient-option text-sm text-muted">Pasien tidak ditemukan</div>';
        } else {
          data.forEach(p => {
            const div = document.createElement('div');
            div.className = 'patient-option';
            div.innerHTML = `<div class="patient-option-name">${esc(p.name)}</div>
              <div class="patient-option-sub">NIK: ${esc(p.nik)} &bull; ${p.gender==='L'?'Laki-laki':'Perempuan'} &bull; ${p.age} th</div>`;
            div.addEventListener('click', () => {
              searchInput.value = p.name;
              hiddenId.value    = p.id;
              submitBtn.disabled = false;
              dropdown.classList.remove('open');
              // Update info card
              infoCard.style.display = 'block';
              if (headerRow) headerRow.style.display = 'flex';
              if (infoAvatar) infoAvatar.textContent = p.name.substring(0,2).toUpperCase();
              if (infoName) infoName.textContent = p.name;
              if (infoSub) infoSub.textContent = `${p.gender==='L'?'Laki-laki':'Perempuan'} · ${p.age} tahun`;
              if (infoAllergy) {
                if (p.allergy) {
                  infoAllergy.textContent = '⚠ Alergi: ' + p.allergy;
                  infoAllergy.style.display = 'inline-flex';
                } else {
                  infoAllergy.style.display = 'none';
                }
              }
            });
            dropdown.appendChild(div);
          });
        }
        dropdown.classList.add('open');
      } catch(e) {}
    }, 280);
  });
  document.addEventListener('click', e => {
    if (!searchInput.contains(e.target)) dropdown.classList.remove('open');
  });
  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
})();

// Referral toggle
document.getElementById('referredCheck').addEventListener('change', function() {
  document.getElementById('referralBox').style.display = this.checked ? 'block' : 'none';
});

// Quick prescription templates
const TEMPLATES = {
  'ISPA': `1. Amoxicillin 500mg - 3x1 tablet - 5 hari
2. Paracetamol 500mg - 3x1 tablet - jika demam/nyeri
3. OBH Combi (sirup batuk) - 3x1 sendok makan - 3 hari
4. Vitamin C 500mg - 1x1 tablet - 7 hari`,
  'Demam': `1. Paracetamol 500mg - 3x1 tablet - jika demam (T > 38°C)
2. Ibuprofen 400mg - 3x1 tablet - setelah makan
3. Oralit - minum cukup cairan
4. Istirahat yang cukup`,
  'Hipertensi': `1. Amlodipine 5mg - 1x1 tablet pagi hari - 30 hari
2. Captopril 12.5mg - 2x1 tablet - (sublingual jika hipertensi krisis)
3. Konsul gizi: diet rendah garam (<2g/hari)
4. Kontrol tekanan darah rutin`,
  'DM': `1. Metformin 500mg - 3x1 tablet - sesudah makan - 30 hari
2. Glibenclamide 5mg - 1x1 tablet pagi - 30 hari
3. Vitamin B kompleks - 1x1 tablet - 30 hari
4. Diet diabetes: hindari gula & karbohidrat berlebih`,
  'Gastritis': `1. Omeprazole 20mg - 1x1 kapsul sebelum makan pagi - 7 hari
2. Antasida suspensi - 3x1 sendok makan - 1 jam setelah makan
3. Sucralfate 500mg - 3x1 tablet - sebelum makan
4. Hindari makanan pedas, asam, dan kafein`
};

function addTemplate(name) {
  const ta = document.querySelector('textarea[name="prescription"]');
  const current = ta.value.trim();
  ta.value = current ? current + '\n\n' + TEMPLATES[name] : TEMPLATES[name];
  ta.focus();
}
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
