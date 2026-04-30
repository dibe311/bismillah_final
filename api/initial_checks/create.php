<?php
/**
 * initial_checks/create.php — disesuaikan dengan struktur tabel DB
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin', 'perawat']);

$db = getDB();

$queueId = (int)($_GET['queue_id'] ?? 0);
if (!$queueId) {
    flashMessage('error', 'ID antrian tidak valid.');
    redirect('queues');
}

$qStmt = $db->prepare("
    SELECT q.id, q.queue_number, q.status, q.queue_date,
           p.id AS patient_id, p.name AS patient_name,
           p.birth_date, p.gender, p.blood_type, p.allergy,
           p.insurance_type, p.nik, p.phone
    FROM queues q
    LEFT JOIN patients p ON q.patient_id = p.id
    WHERE q.id = ?
");
$qStmt->execute([$queueId]);
$queue = $qStmt->fetch();

if (!$queue) {
    flashMessage('error', 'Antrian tidak ditemukan.');
    redirect('queues');
}

$existing = $db->prepare("SELECT id FROM initial_checks WHERE queue_id = ?");
$existing->execute([$queueId]);
if ($existing->fetch()) {
    flashMessage('warning', "Pemeriksaan awal antrian {$queue['queue_number']} sudah diinput sebelumnya.");
    redirect('queues');
}

// Vital terakhir pasien sebagai referensi
$lastVital = $db->prepare("
    SELECT * FROM initial_checks
    WHERE patient_id = ?
    ORDER BY checked_at DESC LIMIT 1
");
$lastVital->execute([$queue['patient_id']]);
$lastVital = $lastVital->fetch();

$patientAge = calculateAge($queue['birth_date']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $blood_pressure    = trim($_POST['blood_pressure']    ?? '');
    $temperature       = trim($_POST['temperature']       ?? '') ?: null;
    $pulse             = trim($_POST['pulse']             ?? '') ?: null;
    $oxygen_saturation = trim($_POST['oxygen_saturation'] ?? '') ?: null;
    $weight            = trim($_POST['weight']            ?? '') ?: null;
    $height            = trim($_POST['height']            ?? '') ?: null;
    $chief_complaint   = trim($_POST['chief_complaint']   ?? '');
    $allergy_check     = trim($_POST['allergy_check']     ?? '');
    $disease_history   = trim($_POST['disease_history']   ?? '');
    $current_meds      = trim($_POST['current_meds']      ?? '');
    $pain_scale        = trim($_POST['pain_scale']        ?? '') ?: null;
    $notes_input       = trim($_POST['notes']             ?? '');
    $update_allergy    = isset($_POST['update_allergy']) ? 1 : 0;

    if (empty($blood_pressure))   $errors[] = 'Tekanan darah wajib diisi (format: 120/80).';
    if (empty($chief_complaint))  $errors[] = 'Keluhan utama wajib diisi.';

    if (!$errors) {
        // Gabungkan anamnesis ke dalam notes
        $anamnesisLines = [];
        if ($allergy_check)  $anamnesisLines[] = "Alergi: $allergy_check";
        if ($disease_history)$anamnesisLines[] = "Riwayat penyakit: $disease_history";
        if ($current_meds)   $anamnesisLines[] = "Obat rutin: $current_meds";
        if ($pain_scale !== null) $anamnesisLines[] = "Skala nyeri: {$pain_scale}/10";

        $finalNotes = implode("\n", $anamnesisLines);
        if ($notes_input) {
            $finalNotes .= ($finalNotes ? "\n\n[Catatan Perawat]\n" : '') . $notes_input;
        }

        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO initial_checks
                    (queue_id, patient_id, nurse_id,
                     blood_pressure, temperature, pulse, oxygen_saturation,
                     weight, height, chief_complaint, notes, checked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $queueId,
                $queue['patient_id'],
                currentUser()['id'],
                $blood_pressure,
                $temperature,
                $pulse,
                $oxygen_saturation,
                $weight,
                $height,
                $chief_complaint,
                $finalNotes ?: null,
            ]);

            if ($update_allergy && $allergy_check) {
                $db->prepare("UPDATE patients SET allergy = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$allergy_check, $queue['patient_id']]);
            }

            // Update status antrian: waiting → called
            $db->prepare("UPDATE queues SET status = 'called', called_at = NOW(), updated_at = NOW() WHERE id = ?")
               ->execute([$queueId]);

            $db->commit();
            flashMessage('success', "Pemeriksaan awal antrian <strong>{$queue['queue_number']}</strong> tersimpan. Pasien siap dipanggil dokter.");
            redirect('queues');

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

$pageTitle  = 'Pemeriksaan Awal';
$activeMenu = 'initial_checks';
ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Input Pemeriksaan Awal</h1>
        <p class="page-subtitle">Antrian <?= sanitize($queue['queue_number']) ?> · <?= date('d F Y', strtotime($queue['queue_date'])) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">← Kembali ke Antrian</a>
</div>

<!-- Banner info pasien -->
<div style="background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);
            padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:52px;height:52px;border-radius:var(--r-md);background:var(--navy);color:white;
                display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($queue['patient_name'],0,2)) ?>
    </div>
    <div style="flex:1;min-width:180px">
        <div style="font-size:17px;font-weight:700;color:var(--gray-900)"><?= sanitize($queue['patient_name']) ?></div>
        <div style="font-size:13px;color:var(--gray-500);margin-top:2px">
            <?= $queue['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> · <?= $patientAge ?> tahun ·
            <?= sanitize($queue['insurance_type']) ?>
        </div>
        <?php if ($queue['allergy']): ?>
        <div style="display:inline-flex;align-items:center;gap:4px;background:var(--red-bg);
                    color:var(--red);border:1px solid var(--red-border);border-radius:var(--r-sm);
                    padding:2px 8px;font-size:11px;font-weight:700;margin-top:4px">
            ⚠ ALERGI: <?= sanitize($queue['allergy']) ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="text-align:center;flex-shrink:0">
        <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.07em">Antrian</div>
        <div style="font-size:28px;font-weight:700;color:var(--blue);letter-spacing:-1px;line-height:1.1">
            <?= sanitize($queue['queue_number']) ?>
        </div>
    </div>
    <?php if ($lastVital): ?>
    <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--r);
                padding:10px 14px;flex-shrink:0">
        <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;margin-bottom:6px">Vital Sebelumnya</div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px">
            <?php if ($lastVital['blood_pressure']): ?>
            <span>TD: <strong><?= sanitize($lastVital['blood_pressure']) ?></strong></span>
            <?php endif; ?>
            <?php if ($lastVital['temperature']): ?>
            <span>Suhu: <strong><?= number_format($lastVital['temperature'],1) ?>°C</strong></span>
            <?php endif; ?>
        </div>
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
    <div class="two-col-grid">

        <!-- KIRI: Tanda Vital + Antropometri -->
        <div class="flex flex-col gap-3">
            <div class="card">
                <div class="card-header" style="background:#E8F5E9;border-bottom-color:#A5D6A7">
                    <span class="card-title" style="color:var(--green)">Tanda Vital</span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Tekanan Darah <span class="req">*</span></label>
                        <input class="form-control" type="text" name="blood_pressure"
                               placeholder="120/80" value="<?= sanitize($_POST['blood_pressure'] ?? '') ?>" required>
                        <div class="form-hint">Format: Sistolik/Diastolik (contoh: 120/80)</div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Suhu Tubuh (°C)</label>
                            <input class="form-control" type="number" name="temperature"
                                   placeholder="36.5" step="0.1" min="30" max="45"
                                   value="<?= sanitize($_POST['temperature'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nadi (bpm)</label>
                            <input class="form-control" type="number" name="pulse"
                                   placeholder="80" min="30" max="250"
                                   value="<?= sanitize($_POST['pulse'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SpO₂ (%)</label>
                            <input class="form-control" type="number" name="oxygen_saturation"
                                   placeholder="98" min="50" max="100"
                                   value="<?= sanitize($_POST['oxygen_saturation'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Skala Nyeri (0–10)</label>
                            <input class="form-control" type="number" name="pain_scale"
                                   placeholder="0" min="0" max="10"
                                   value="<?= sanitize($_POST['pain_scale'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background:var(--blue-pale);border-bottom-color:var(--blue-muted)">
                    <span class="card-title" style="color:var(--blue)">Antropometri</span>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Berat Badan (kg)</label>
                            <input class="form-control" type="number" name="weight" id="weightInput"
                                   placeholder="65" step="0.1" min="1" max="300"
                                   value="<?= sanitize($_POST['weight'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tinggi Badan (cm)</label>
                            <input class="form-control" type="number" name="height" id="heightInput"
                                   placeholder="165" step="0.1" min="50" max="250"
                                   value="<?= sanitize($_POST['height'] ?? '') ?>">
                        </div>
                    </div>
                    <div id="bmiPanel" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);
                                              border-radius:var(--r);padding:12px;margin-top:4px">
                        <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;margin-bottom:8px">IMT / BMI</div>
                        <div style="display:flex;align-items:center;gap:16px">
                            <div>
                                <div style="font-size:28px;font-weight:700;line-height:1" id="bmiValue">—</div>
                                <div style="font-size:11px;color:var(--gray-500)">kg/m²</div>
                            </div>
                            <span id="bmiCategory" class="badge">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KANAN: Anamnesis -->
        <div class="flex flex-col gap-3">
            <div class="card">
                <div class="card-header" style="background:var(--amber-bg);border-bottom-color:var(--amber-border)">
                    <span class="card-title" style="color:var(--amber)">Anamnesis</span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Keluhan Utama <span class="req">*</span></label>
                        <textarea class="form-control" name="chief_complaint" rows="4" required
                                  placeholder="Deskripsikan keluhan utama pasien..."><?= sanitize($_POST['chief_complaint'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Alergi</label>
                        <textarea class="form-control" name="allergy_check" rows="2"
                                  placeholder="Alergi obat, makanan, atau bahan tertentu?"><?= sanitize($_POST['allergy_check'] ?? $queue['allergy'] ?? '') ?></textarea>
                        <label style="display:flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;cursor:pointer">
                            <input type="checkbox" name="update_allergy" style="width:14px;height:14px">
                            Perbarui data alergi pasien di sistem
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Riwayat Penyakit</label>
                        <textarea class="form-control" name="disease_history" rows="2"
                                  placeholder="Diabetes, hipertensi, dll..."><?= sanitize($_POST['disease_history'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Obat yang Sedang Dikonsumsi</label>
                        <textarea class="form-control" name="current_meds" rows="2"
                                  placeholder="Obat rutin, suplemen..."><?= sanitize($_POST['current_meds'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">Catatan Tambahan untuk Dokter</span></div>
                <div class="card-body">
                    <textarea class="form-control" name="notes" rows="3"
                              placeholder="Kondisi umum pasien, hal yang perlu diperhatikan dokter..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Simpan & Panggil ke Dokter
                </button>
            </div>
        </div>

    </div>
</form>

<script>
(function() {
    const weightInput = document.getElementById('weightInput');
    const heightInput = document.getElementById('heightInput');
    const bmiPanel    = document.getElementById('bmiPanel');
    const bmiValue    = document.getElementById('bmiValue');
    const bmiCat      = document.getElementById('bmiCategory');

    function calcBMI() {
        const w = parseFloat(weightInput.value);
        const h = parseFloat(heightInput.value);
        if (w > 0 && h > 50) {
            const bmi = w / Math.pow(h / 100, 2);
            bmiValue.textContent = bmi.toFixed(1);
            bmiPanel.style.display = 'block';
            let cat='', cls='';
            if      (bmi < 18.5) { cat='Kurus';    cls='badge-waiting'; }
            else if (bmi < 23)   { cat='Normal ✓'; cls='badge-done';    }
            else if (bmi < 25)   { cat='Gemuk';    cls='badge-called';  }
            else                 { cat='Obesitas'; cls='badge-cancelled';}
            bmiCat.textContent = cat;
            bmiCat.className   = 'badge ' + cls;
        } else {
            bmiPanel.style.display = 'none';
        }
    }
    weightInput && weightInput.addEventListener('input', calcBMI);
    heightInput && heightInput.addEventListener('input', calcBMI);
})();
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';