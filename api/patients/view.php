<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','dokter','perawat']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$patient = $db->prepare("SELECT * FROM patients WHERE id = ? AND is_active = 1");
$patient->execute([$id]);
$patient = $patient->fetch();
if (!$patient) { flashMessage('error', 'Pasien tidak ditemukan.'); redirect('patients'); }

// Medical records
$records = $db->prepare("
    SELECT mr.*, u.name AS doctor_name
    FROM medical_records mr
    LEFT JOIN users u ON mr.doctor_id = u.id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC
    LIMIT 10
");
$records->execute([$id]);
$records = $records->fetchAll();

// Last initial check
$lastCheck = $db->prepare("SELECT ic.* FROM initial_checks ic WHERE ic.patient_id = ? ORDER BY checked_at DESC LIMIT 1");
$lastCheck->execute([$id]);
$lastCheck = $lastCheck->fetch();

$pageTitle  = sanitize($patient['name']);
$activeMenu = 'patients';
ob_start();
?>

<!-- Patient header card -->
<div class="patient-header">
    <div class="patient-avatar"><?= strtoupper(substr($patient['name'], 0, 2)) ?></div>
    <div>
        <h2 style="font-size:1.15rem;font-weight:700;color:var(--gray-900)"><?= sanitize($patient['name']) ?></h2>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
            <span class="badge <?= $patient['insurance_type'] === 'BPJS' ? 'badge-done' : 'badge-draft' ?>"><?= sanitize($patient['insurance_type']) ?></span>
            <?php if ($patient['allergy']): ?>
            <span class="allergy-badge">⚠ Alergi</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="patient-meta" style="margin-left:auto">
        <div class="patient-meta-item">
            <span class="meta-label">NIK</span>
            <span class="meta-value"><?= sanitize($patient['nik']) ?></span>
        </div>
        <div class="patient-meta-item">
            <span class="meta-label">Usia</span>
            <span class="meta-value"><?= calculateAge($patient['birth_date']) ?> tahun</span>
        </div>
        <div class="patient-meta-item">
            <span class="meta-label">Gender</span>
            <span class="meta-value"><?= $patient['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span>
        </div>
        <div class="patient-meta-item">
            <span class="meta-label">Gol. Darah</span>
            <span class="meta-value"><?= $patient['blood_type'] === 'unknown' ? '—' : sanitize($patient['blood_type']) ?></span>
        </div>
        <div class="patient-meta-item">
            <span class="meta-label">Telepon</span>
            <span class="meta-value"><?= sanitize($patient['phone'] ?? '—') ?></span>
        </div>
    </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php if (hasRole(['admin','perawat'])): ?>
    <a href="<?= BASE_URL ?>/patients/edit?id=<?= $id ?>" class="btn btn-outline btn-sm">Edit Data</a>
    <a href="<?= BASE_URL ?>/queues/create?patient_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Buat Antrian</a>
    <?php endif; ?>
    <?php if (hasRole(['dokter','admin'])): ?>
    <a href="<?= BASE_URL ?>/medical_records/new?patient_id=<?= $id ?>" class="btn btn-outline btn-sm">+ Rekam Medis</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/patients" class="btn btn-ghost btn-sm">← Kembali</a>
</div>

<div class="two-col-grid">
    <!-- Detail Pasien -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><span class="card-title">Informasi Lengkap</span></div>
            <div class="card-body">
                <dl class="info-dl">
                    <dt>Alamat</dt><dd><?= sanitize($patient['address'] ?? '—') ?></dd>
                    <dt>Email</dt><dd><?= sanitize($patient['email'] ?? '—') ?></dd>
                    <dt>No. Asuransi</dt><dd><?= sanitize($patient['insurance_number'] ?? '—') ?></dd>
                    <dt>Tanggal Lahir</dt><dd><?= date('d F Y', strtotime($patient['birth_date'])) ?></dd>
                    <dt>Terdaftar</dt><dd><?= date('d F Y', strtotime($patient['created_at'])) ?></dd>
                    <?php if ($patient['allergy']): ?>
                    <dt style="color:var(--red)">⚠ Alergi</dt>
                    <dd style="color:var(--red);font-weight:600"><?= sanitize($patient['allergy']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($lastCheck): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Tanda Vital Terakhir</span>
                <span class="text-xs text-muted"><?= date('d/m/Y H:i', strtotime($lastCheck['checked_at'])) ?></span>
            </div>
            <div class="card-body">
                <div class="vitals-grid">
                    <?php if ($lastCheck['blood_pressure']): ?>
                    <div class="vital-item">
                        <div class="vital-value"><?= sanitize($lastCheck['blood_pressure']) ?> <span class="vital-unit">mmHg</span></div>
                        <div class="vital-label">Tekanan Darah</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($lastCheck['temperature']): ?>
                    <div class="vital-item">
                        <div class="vital-value"><?= $lastCheck['temperature'] ?><span class="vital-unit">°C</span></div>
                        <div class="vital-label">Suhu</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($lastCheck['pulse']): ?>
                    <div class="vital-item">
                        <div class="vital-value"><?= $lastCheck['pulse'] ?> <span class="vital-unit">bpm</span></div>
                        <div class="vital-label">Nadi</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($lastCheck['oxygen_saturation']): ?>
                    <div class="vital-item">
                        <div class="vital-value"><?= $lastCheck['oxygen_saturation'] ?><span class="vital-unit">%</span></div>
                        <div class="vital-label">SpO₂</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($lastCheck['chief_complaint']): ?>
                <div style="background:var(--amber-bg);border:1px solid var(--amber-border);border-radius:var(--r);padding:12px;margin-top:8px">
                    <div class="text-xs" style="font-weight:700;color:var(--amber);margin-bottom:4px">KELUHAN UTAMA</div>
                    <div class="text-sm"><?= sanitize($lastCheck['chief_complaint']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Riwayat Rekam Medis -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Riwayat Kunjungan</span>
            <span class="badge badge-called"><?= count($records) ?> kunjungan</span>
        </div>
        <div class="card-body">
            <?php if ($records): ?>
            <div class="record-timeline">
                <?php foreach ($records as $r): ?>
                <div class="timeline-item">
                    <div class="timeline-date"><?= date('d F Y', strtotime($r['visit_date'])) ?></div>
                    <div class="timeline-card">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                            <span class="text-sm font-semibold">dr. <?= sanitize($r['doctor_name']) ?></span>
                            <?php if ($r['icd_code']): ?>
                            <span class="badge badge-called" style="font-size:.65rem"><?= sanitize($r['icd_code']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted" style="margin-bottom:4px">DIAGNOSA</div>
                        <div class="text-sm font-semibold" style="margin-bottom:8px"><?= sanitize($r['diagnosis']) ?></div>
                        <?php if ($r['prescription']): ?>
                        <div class="text-xs text-muted" style="margin-bottom:4px">RESEP</div>
                        <div class="text-sm"><?= sanitize($r['prescription']) ?></div>
                        <?php endif; ?>
                        <div style="margin-top:8px">
                            <a href="<?= BASE_URL ?>/medical_records/view?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:32px 0">
                <p class="empty-state-title">Belum ada riwayat kunjungan</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
