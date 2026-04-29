<?php
/**
 * initial_checks/create.php
 * Pemeriksaan awal oleh Perawat / Admin.
 *
 * Field yang tersedia:
 * - Tanda Vital: Tekanan Darah, Suhu, Nadi, SpO2, Skala Nyeri
 * - Antropometri: Berat Badan, Tinggi Badan (+ kalkulasi BMI otomatis)
 * - Anamnesis: Keluhan utama, Riwayat penyakit, Konfirmasi alergi, Obat rutin
 * - Catatan untuk dokter (bebas)
 *
 * Data ini otomatis muncul di form rekam medis dokter.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin', 'perawat']);

$db = getDB();

// Validasi queue_id
$queueId = (int)($_GET['queue_id'] ?? 0);
if (!$queueId) {
    flashMessage('error', 'ID antrian tidak valid.');
    redirect('queues');
}

// Ambil data antrian + pasien
$qStmt = $db->prepare("
    SELECT
        q.id, q.queue_number, q.status, q.queue_date,
        p.id          AS patient_id,
        p.name        AS patient_name,
        p.birth_date,
        p.gender,
        p.blood_type,
        p.allergy,
        p.insurance_type,
        p.nik,
        p.phone
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

// Cek apakah sudah ada pemeriksaan awal
$existing = $db->prepare("SELECT id FROM initial_checks WHERE queue_id = ?");
$existing->execute([$queueId]);
if ($existing->fetch()) {
    flashMessage('warning', "Pemeriksaan awal antrian {$queue['queue_number']} sudah diinput sebelumnya.");
    redirect('queues');
}

// Ambil data vital terakhir pasien ini (sebagai referensi)
$lastVital = $db->prepare("
    SELECT ic.*
    FROM initial_checks ic
    WHERE ic.patient_id = ?
    ORDER BY ic.checked_at DESC
    LIMIT 1
");
$lastVital->execute([$queue['patient_id']]);
$lastVital = $lastVital->fetch();

// Hitung usia
$patientAge = calculateAge($queue['birth_date']);

$errors = [];

// ── POST Handler ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'blood_pressure'    => trim($_POST['blood_pressure']    ?? ''),
        'temperature'       => trim($_POST['temperature']       ?? '') ?: null,
        'pulse'             => trim($_POST['pulse']             ?? '') ?: null,
        'oxygen_saturation' => trim($_POST['oxygen_saturation'] ?? '') ?: null,
        'weight'            => trim($_POST['weight']            ?? '') ?: null,
        'height'            => trim($_POST['height']            ?? '') ?: null,
        'chief_complaint'   => trim($_POST['chief_complaint']   ?? ''),
        'allergy_check'     => trim($_POST['allergy_check']     ?? ''),
        'disease_history'   => trim($_POST['disease_history']   ?? ''),
        'current_meds'      => trim($_POST['current_meds']      ?? ''),
        'pain_scale'        => trim($_POST['pain_scale']        ?? '') ?: null,
        'notes'             => trim($_POST['notes']             ?? ''),
        'update_allergy'    => isset($_POST['update_allergy']) ? 1 : 0,
    ];

    // Validasi
    if (empty($data['blood_pressure']))
        $errors[] = 'Tekanan darah wajib diisi (format: 120/80).';
    if (empty($data['chief_complaint']))
        $errors[] = 'Keluhan utama wajib diisi.';
    if ($data['temperature'] !== null && $data['temperature'] !== '' && !is_numeric($data['temperature']))
        $errors[] = 'Suhu tubuh harus berupa angka desimal (contoh: 36.5).';
    if ($data['pulse'] !== null && $data['pulse'] !== '' && !ctype_digit($data['pulse']))
        $errors[] = 'Nadi harus berupa angka bulat (contoh: 80).';
    if ($data['weight'] !== null && $data['weight'] !== '' && !is_numeric($data['weight']))
        $errors[] = 'Berat badan harus berupa angka.';
    if ($data['height'] !== null && $data['height'] !== '' && !is_numeric($data['height']))
        $errors[] = 'Tinggi badan harus berupa angka.';

    if (!$errors) {
        // Bangun notes gabungan (anamnesis + catatan bebas)
        $anamnesisLines = [];
        if ($data['allergy_check'])  $anamnesisLines[] = "Alergi: " . $data['allergy_check'];
        if ($data['disease_history'])$anamnesisLines[] = "Riwayat penyakit: " . $data['disease_history'];
        if ($data['current_meds'])   $anamnesisLines[] = "Obat rutin: " . $data['current_meds'];
        if ($data['pain_scale'] !== null) $anamnesisLines[] = "Skala nyeri: " . $data['pain_scale'] . "/10";

        $finalNotes = '';
        if ($anamnesisLines) $finalNotes .= implode("\n", $anamnesisLines);
        if ($data['notes']) {
            $finalNotes .= ($finalNotes ? "\n\n[Catatan Perawat]\n" : '') . $data['notes'];
        }

        $db->beginTransaction();
        try {
            // INSERT initial_check
            $db->prepare("
                INSERT INTO initial_checks
                    (queue_id, patient_id, nurse_id,
                     blood_pressure, temperature, pulse, oxygen_saturation,
                     weight, height,
                     chief_complaint, notes, checked_at)
                VALUES
                    (?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?,
                     ?, ?, NOW())
            ")->execute([
                $queueId,
                $queue['patient_id'],
                currentUser()['id'],
                $data['blood_pressure'],
                $data['temperature']       ?: null,
                $data['pulse']             ?: null,
                $data['oxygen_saturation'] ?: null,
                $data['weight']            ?: null,
                $data['height']            ?: null,
                $data['chief_complaint'],
                $finalNotes ?: null,
            ]);

            // Update alergi pasien jika diminta
            if ($data['update_allergy'] && $data['allergy_check']) {
                $db->prepare("UPDATE patients SET allergy = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$data['allergy_check'], $queue['patient_id']]);
            }

            // Majukan status antrian: waiting → called
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

<!-- ── Banner info pasien ── -->
<div style="background:var(--white);border:1px solid var(--gray-300);border-radius:var(--r-md);
            padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">

    <!-- Avatar -->
    <div style="width:52px;height:52px;border-radius:var(--r-md);background:var(--navy);color:white;
                display:flex;align-items:center;justify-content:center;font-size:18px;
                font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($queue['patient_name'],0,2)) ?>
    </div>

    <!-- Info dasar -->
    <div style="flex:1;min-width:180px">
        <div style="font-size:17px;font-weight:700;color:var(--gray-900)">
            <?= sanitize($queue['patient_name']) ?>
        </div>
        <div style="font-size:13px;color:var(--gray-500);margin-top:2px">
            <?= $queue['gender']==='L' ? 'Laki-laki' : 'Perempuan' ?> ·
            <?= $patientAge ?> tahun ·
            Gol. <?= $queue['blood_type']==='unknown' ? '—' : sanitize($queue['blood_type']) ?> ·
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

    <!-- Nomor antrian -->
    <div style="text-align:center;flex-shrink:0">
        <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.07em">Antrian</div>
        <div style="font-size:28px;font-weight:700;color:var(--blue);letter-spacing:-1px;line-height:1.1">
            <?= sanitize($queue['queue_number']) ?>
        </div>
    </div>

    <!-- Vital terakhir (referensi) -->
    <?php if ($lastVital): ?>
    <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--r);
                padding:10px 14px;flex-shrink:0;min-width:200px">
        <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;
                    letter-spacing:.06em;margin-bottom:6px">
            Vital Kunjungan Sebelumnya
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px">
            <?php if ($lastVital['blood_pressure']): ?>
            <span>TD: <strong><?= sanitize($lastVital['blood_pressure']) ?></strong></span>
            <?php endif; ?>
            <?php if ($lastVital['temperature']): ?>
            <span>Suhu: <strong><?= number_format($lastVital['temperature'],1) ?>°C</strong></span>
            <?php endif; ?>
            <?php if ($lastVital['weight']): ?>
            <span>BB: <strong><?= $lastVital['weight'] ?> kg</strong></span>
            <?php endif; ?>
            <?php if ($lastVital['height']): ?>
            <span>TB: <strong><?= $lastVital['height'] ?> cm</strong></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if ($errors): ?>
<div class="alert alert-error">
    <svg viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <ul style="margin:0;padding-left:16px">
        <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="">

    <div class="two-col-grid">

        <!-- ══ KIRI ══════════════════════════════════════════════════ -->
        <div class="flex flex-col gap-3">

            <!-- Tanda Vital -->
            <div class="card">
                <div class="card-header" style="background:#E8F5E9;border-bottom-color:#A5D6A7">
                    <span class="card-title" style="color:var(--green)">Tanda Vital</span>
                </div>
                <div class="card-body">

                    <!-- Tekanan Darah -->
                    <div class="form-group">
                        <label class="form-label">
                            Tekanan Darah <span class="req">*</span>
                        </label>
                        <input class="form-control" type="text" name="blood_pressure"
                               id="bpInput"
                               placeholder="120/80"
                               value="<?= sanitize($_POST['blood_pressure'] ?? '') ?>"
                               pattern="^\d{2,3}\/\d{2,3}$"
                               required>
                        <div class="form-hint">Format: Sistolik/Diastolik (contoh: 120/80 mmHg)</div>
                        <div id="bpWarning" style="display:none;color:var(--red);font-size:11px;font-weight:600;margin-top:4px">
                            ⚠ Tekanan darah SANGAT TINGGI — segera laporkan ke dokter!
                        </div>
                    </div>

                    <!-- Suhu + Nadi -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Suhu Tubuh (°C)</label>
                            <input class="form-control" type="number" name="temperature"
                                   id="tempInput"
                                   placeholder="36.5" step="0.1" min="30" max="45"
                                   value="<?= sanitize($_POST['temperature'] ?? '') ?>">
                            <div id="tempWarning" style="display:none;color:var(--amber);font-size:11px;font-weight:600;margin-top:4px">
                                ⚠ Di luar rentang normal (36.1–37.5°C)
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nadi (bpm)</label>
                            <input class="form-control" type="number" name="pulse"
                                   placeholder="80" min="30" max="250"
                                   value="<?= sanitize($_POST['pulse'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- SpO2 + Skala Nyeri -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SpO₂ (%)</label>
                            <input class="form-control" type="number" name="oxygen_saturation"
                                   id="spo2Input"
                                   placeholder="98" min="50" max="100"
                                   value="<?= sanitize($_POST['oxygen_saturation'] ?? '') ?>">
                            <div id="spo2Warning" style="display:none;color:var(--red);font-size:11px;font-weight:600;margin-top:4px">
                                ⚠ SpO₂ di bawah normal (&lt;95%)!
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Skala Nyeri (0–10)</label>
                            <input class="form-control" type="number" name="pain_scale"
                                   placeholder="0" min="0" max="10"
                                   value="<?= sanitize($_POST['pain_scale'] ?? '') ?>">
                            <div class="form-hint">0 = tidak nyeri, 10 = nyeri ekstrem</div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Berat & Tinggi Badan -->
            <div class="card">
                <div class="card-header" style="background:var(--blue-pale);border-bottom-color:var(--blue-muted)">
                    <span class="card-title" style="color:var(--blue)">Antropometri</span>
                </div>
                <div class="card-body">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Berat Badan (kg)</label>
                            <input class="form-control" type="number" name="weight"
                                   id="weightInput"
                                   placeholder="65" step="0.1" min="1" max="300"
                                   value="<?= sanitize($_POST['weight'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tinggi Badan (cm)</label>
                            <input class="form-control" type="number" name="height"
                                   id="heightInput"
                                   placeholder="165" step="0.1" min="50" max="250"
                                   value="<?= sanitize($_POST['height'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- BMI Result -->
                    <div id="bmiPanel" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);
                                              border-radius:var(--r);padding:12px;margin-top:4px">
                        <div style="font-size:11px;font-weight:700;color:var(--gray-500);
                                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">
                            IMT / BMI Otomatis
                        </div>
                        <div style="display:flex;align-items:center;gap:16px">
                            <div>
                                <div style="font-size:28px;font-weight:700;color:var(--gray-900);line-height:1" id="bmiValue">—</div>
                                <div style="font-size:11px;color:var(--gray-500)">kg/m²</div>
                            </div>
                            <div>
                                <span id="bmiCategory" class="badge">—</span>
                                <div style="font-size:11px;color:var(--gray-500);margin-top:4px" id="bmiDesc"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Referensi BMI -->
                    <div style="font-size:11px;color:var(--gray-500);margin-top:8px;line-height:1.7">
                        Referensi IMT Asia: Kurus &lt;18.5 · Normal 18.5–22.9 · Gemuk 23–24.9 · Obesitas ≥25
                        <?php if ($patientAge < 18): ?>
                        <br><strong style="color:var(--amber)">⚠ Pasien anak (<?= $patientAge ?> th) — gunakan tabel IMT anak.</strong>
                        <?php elseif ($patientAge >= 60): ?>
                        <br><strong style="color:var(--blue)">ℹ Pasien lansia (<?= $patientAge ?> th) — interpretasi IMT berbeda.</strong>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- Referensi nilai normal -->
            <div class="card" style="border-color:var(--gray-200)">
                <div class="card-header"><span class="card-title">Referensi Nilai Normal Dewasa</span></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
                        <div style="padding:8px;background:var(--gray-50);border-radius:var(--r)">
                            <div style="font-weight:700;color:var(--gray-700)">Tekanan Darah</div>
                            <div>Normal: <strong>90/60 – 120/80</strong></div>
                            <div style="color:var(--amber)">Pre-hipertensi: 120–139/80–89</div>
                            <div style="color:var(--red)">Hipertensi: ≥140/90</div>
                        </div>
                        <div style="padding:8px;background:var(--gray-50);border-radius:var(--r)">
                            <div style="font-weight:700;color:var(--gray-700)">Suhu Tubuh</div>
                            <div>Normal: <strong>36.1 – 37.5°C</strong></div>
                            <div style="color:var(--amber)">Subfebrile: 37.6–38°C</div>
                            <div style="color:var(--red)">Demam: ≥38.1°C</div>
                        </div>
                        <div style="padding:8px;background:var(--gray-50);border-radius:var(--r)">
                            <div style="font-weight:700;color:var(--gray-700)">Nadi</div>
                            <div>Normal: <strong>60 – 100 bpm</strong></div>
                            <div style="color:var(--amber)">Bradikardi: &lt;60 bpm</div>
                            <div style="color:var(--red)">Takikardi: &gt;100 bpm</div>
                        </div>
                        <div style="padding:8px;background:var(--gray-50);border-radius:var(--r)">
                            <div style="font-weight:700;color:var(--gray-700)">SpO₂</div>
                            <div>Normal: <strong>≥95%</strong></div>
                            <div style="color:var(--amber)">Perhatian: 90–94%</div>
                            <div style="color:var(--red)">Kritis: &lt;90%</div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /kiri -->

        <!-- ══ KANAN ═════════════════════════════════════════════════ -->
        <div class="flex flex-col gap-3">

            <!-- Anamnesis / Keluhan -->
            <div class="card">
                <div class="card-header" style="background:var(--amber-bg);border-bottom-color:var(--amber-border)">
                    <span class="card-title" style="color:var(--amber)">Anamnesis — Informasi untuk Dokter</span>
                </div>
                <div class="card-body">

                    <!-- Keluhan Utama -->
                    <div class="form-group">
                        <label class="form-label">
                            Keluhan Utama <span class="req">*</span>
                        </label>
                        <textarea class="form-control" name="chief_complaint" rows="4" required
                                  placeholder="Deskripsikan keluhan utama pasien sejelas mungkin.&#10;Contoh: Demam 3 hari, batuk berdahak, nyeri tenggorokan..."><?= sanitize($_POST['chief_complaint'] ?? '') ?></textarea>
                        <div class="form-hint">✅ Data ini langsung muncul sebagai pre-fill di form rekam medis dokter, mempersingkat waktu pemeriksaan.</div>
                    </div>

                    <!-- Konfirmasi Alergi -->
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Alergi</label>
                        <textarea class="form-control" name="allergy_check" rows="2"
                                  placeholder="Tanyakan: alergi obat, makanan, atau bahan tertentu?&#10;Tulis 'Tidak ada' jika disangkal pasien."><?= sanitize($_POST['allergy_check'] ?? $queue['allergy'] ?? '') ?></textarea>
                        <label style="display:flex;align-items:center;gap:6px;margin-top:6px;
                                      font-size:12px;color:var(--gray-600);cursor:pointer">
                            <input type="checkbox" name="update_allergy"
                                   <?= ($_POST['update_allergy'] ?? 0) ? 'checked' : '' ?>
                                   style="width:14px;height:14px">
                            Perbarui data alergi pasien di sistem
                        </label>
                    </div>

                    <!-- Riwayat Penyakit -->
                    <div class="form-group">
                        <label class="form-label">Riwayat Penyakit</label>
                        <textarea class="form-control" name="disease_history" rows="3"
                                  placeholder="Diabetes, hipertensi, jantung, asma, operasi sebelumnya, riwayat keluarga..."><?= sanitize($_POST['disease_history'] ?? '') ?></textarea>
                    </div>

                    <!-- Obat Rutin -->
                    <div class="form-group">
                        <label class="form-label">Obat yang Sedang Dikonsumsi</label>
                        <textarea class="form-control" name="current_meds" rows="2"
                                  placeholder="Nama obat rutin, suplemen, vitamin yang sedang dipakai pasien..."><?= sanitize($_POST['current_meds'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>

            <!-- Catatan Tambahan untuk Dokter -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Catatan Tambahan untuk Dokter</span>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:0">
                        <textarea class="form-control" name="notes" rows="4"
                                  placeholder="Kondisi umum pasien saat datang, kondisi khusus, observasi perawat, hal yang perlu diperhatikan dokter..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                        <div class="form-hint">Catatan ini akan tersimpan bersama data pemeriksaan dan bisa dibaca dokter.</div>
                    </div>
                </div>
            </div>

            <!-- Summary (readonly - muncul setelah isi form) -->
            <div class="card" style="border-color:var(--navy);background:#F0F4FA" id="summaryCard">
                <div class="card-header" style="background:var(--navy)">
                    <span class="card-title" style="color:white">Ringkasan Pengisian</span>
                </div>
                <div class="card-body" style="padding:12px">
                    <div style="font-size:12px;color:var(--gray-600);line-height:2">
                        Setelah disimpan, data ini akan:
                    </div>
                    <ul style="font-size:12px;color:var(--gray-700);padding-left:18px;line-height:2;margin-top:4px">
                        <li>Disimpan sebagai catatan pemeriksaan awal perawat</li>
                        <li>Status antrian otomatis: <strong style="color:var(--green)">Menunggu → Dipanggil</strong></li>
                        <li>Data vital &amp; anamnesis langsung pre-fill form SOAP dokter</li>
                        <li>Dokter bisa baca TD, suhu, keluhan tanpa tanya ulang ke pasien</li>
                        <?php if ($queue['allergy']): ?>
                        <li style="color:var(--red)">Alergi pasien akan ditampilkan di header rekam medis</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Tombol -->
            <div class="flex justify-end gap-2">
                <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Simpan & Panggil ke Dokter
                </button>
            </div>

        </div><!-- /kanan -->

    </div><!-- /two-col-grid -->

</form>

<script>
(function() {

    // ── BMI Calculator ────────────────────────────────────────────────
    const weightInput = document.getElementById('weightInput');
    const heightInput = document.getElementById('heightInput');
    const bmiPanel    = document.getElementById('bmiPanel');
    const bmiValue    = document.getElementById('bmiValue');
    const bmiCat      = document.getElementById('bmiCategory');
    const bmiDesc     = document.getElementById('bmiDesc');

    function calcBMI() {
        const w = parseFloat(weightInput.value);
        const h = parseFloat(heightInput.value);
        if (w > 0 && h > 50) {
            const bmi = w / Math.pow(h / 100, 2);
            bmiValue.textContent = bmi.toFixed(1);
            bmiPanel.style.display = 'block';

            // Kategori (standar Asia WHO)
            let cat='', cls='', desc='';
            if      (bmi < 18.5) { cat='Kurus';   cls='badge-waiting'; desc='BB kurang dari ideal'; }
            else if (bmi < 23)   { cat='Normal ✓'; cls='badge-done';   desc='Berat badan ideal'; }
            else if (bmi < 25)   { cat='Gemuk';    cls='badge-called';  desc='Kelebihan berat badan'; }
            else                 { cat='Obesitas'; cls='badge-cancelled'; desc='Risiko penyakit meningkat'; }

            bmiCat.textContent = cat;
            bmiCat.className   = 'badge ' + cls;
            bmiDesc.textContent = desc;
        } else {
            bmiPanel.style.display = 'none';
        }
    }

    weightInput && weightInput.addEventListener('input', calcBMI);
    heightInput && heightInput.addEventListener('input', calcBMI);
    calcBMI(); // hitung jika ada nilai POST

    // ── Peringatan Suhu ───────────────────────────────────────────────
    const tempInput   = document.getElementById('tempInput');
    const tempWarning = document.getElementById('tempWarning');
    if (tempInput) {
        tempInput.addEventListener('input', function() {
            const v = parseFloat(this.value);
            tempWarning.style.display = (v && (v < 36.1 || v > 37.5)) ? 'block' : 'none';
        });
        // Trigger saat load (ada nilai POST)
        if (tempInput.value) tempInput.dispatchEvent(new Event('input'));
    }

    // ── Peringatan SpO2 ───────────────────────────────────────────────
    const spo2Input   = document.getElementById('spo2Input');
    const spo2Warning = document.getElementById('spo2Warning');
    if (spo2Input) {
        spo2Input.addEventListener('input', function() {
            spo2Warning.style.display = (parseInt(this.value) < 95 && this.value !== '') ? 'block' : 'none';
        });
        if (spo2Input.value) spo2Input.dispatchEvent(new Event('input'));
    }

    // ── Peringatan Tekanan Darah Sangat Tinggi ────────────────────────
    const bpInput   = document.getElementById('bpInput');
    const bpWarning = document.getElementById('bpWarning');
    if (bpInput) {
        bpInput.addEventListener('blur', function() {
            const parts = this.value.split('/');
            if (parts.length === 2) {
                const s = parseInt(parts[0]);
                const d = parseInt(parts[1]);
                if (s >= 180 || d >= 120) {
                    bpWarning.style.display = 'block';
                    // Alert serius
                    if (confirm('⚠ PERHATIAN: Tekanan darah sangat tinggi (' + this.value + ')!\n\nSegera laporkan ke dokter sekarang?\n\nKlik OK untuk tetap simpan, Cancel untuk periksa ulang.')) {
                        // Lanjut simpan
                    }
                } else {
                    bpWarning.style.display = 'none';
                }
            }
        });
    }

})();
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';