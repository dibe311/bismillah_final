<?php
/**
 * queues/create.php
 * PERBAIKAN:
 * 1. Tombol submit TIDAK disabled - user bisa submit meski AJAX gagal
 * 2. Pencarian ganda: AJAX dropdown + tabel klik langsung
 * 3. Validasi server-side tetap berjalan meski JS mati
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin', 'perawat']);

$db     = getDB();
$errors = [];
$today  = date('Y-m-d');

// Ambil dokter aktif
$doctors = $db->query("
    SELECT u.id, u.name, COALESCE(dp.specialization, 'Dokter Umum') AS specialization
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    WHERE u.role = 'dokter' AND u.is_active = 1
    ORDER BY u.name ASC
")->fetchAll();

// Ambil SEMUA pasien aktif untuk tabel lokal (tidak butuh AJAX)
$allPatients = $db->query("
    SELECT id, nik, name, gender, birth_date, phone, insurance_type
    FROM patients WHERE is_active = 1
    ORDER BY name ASC LIMIT 1000
")->fetchAll();

// Info antrian hari ini
$infoStmt = $db->prepare("SELECT status, COUNT(*) AS cnt FROM queues WHERE queue_date = ? GROUP BY status");
$infoStmt->execute([$today]);
$infoCnts = [];
foreach ($infoStmt->fetchAll() as $r) $infoCnts[$r['status']] = (int)$r['cnt'];
$totalToday = array_sum($infoCnts);
$nextNum    = generateQueueNumber($db, $today);

// Pre-fill dari URL
$prePatientId   = (int)($_GET['patient_id'] ?? 0);
$prePatientName = '';
$prePatientInfo = '';
if ($prePatientId) {
    foreach ($allPatients as $p) {
        if ((int)$p['id'] === $prePatientId) {
            $prePatientName = $p['name'];
            $age = calculateAge($p['birth_date']);
            $prePatientInfo = "NIK: {$p['nik']} · " . ($p['gender']==='L'?'L':'P') . " · $age tahun";
            break;
        }
    }
    if (!$prePatientName) $prePatientId = 0;
}

// ── POST Handler ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId  = ($_POST['doctor_id'] ?? '') !== '' ? (int)$_POST['doctor_id'] : null;
    $notes     = trim($_POST['notes'] ?? '');

    if (!$patientId)
        $errors[] = 'Pasien wajib dipilih. Ketik nama di kolom pencarian atau klik "Pilih" dari tabel.';

    if (!$errors) {
        $chk = $db->prepare("SELECT id FROM patients WHERE id = ? AND is_active = 1");
        $chk->execute([$patientId]);
        if (!$chk->fetch()) $errors[] = 'Pasien tidak ditemukan di database.';
    }

    if (!$errors) {
        $dup = $db->prepare("
            SELECT queue_number FROM queues
            WHERE patient_id = ? AND queue_date = ?
              AND status NOT IN ('done','cancelled')
        ");
        $dup->execute([$patientId, $today]);
        $dupRow = $dup->fetch();
        if ($dupRow) $errors[] = "Pasien sudah punya antrian aktif hari ini (No. {$dupRow['queue_number']}).";
    }

    if (!$errors) {
        $queueNum = generateQueueNumber($db, $today);
        $db->prepare("
            INSERT INTO queues (queue_number, patient_id, doctor_id, queue_date, status, notes, created_by)
            VALUES (?, ?, ?, ?, 'waiting', ?, ?)
        ")->execute([$queueNum, $patientId, $doctorId, $today, $notes ?: null, currentUser()['id']]);

        flashMessage('success', "Antrian <strong>$queueNum</strong> berhasil dibuat.");
        redirect("queues?date=$today");
    }
}

// Restore pilihan pasien setelah error POST
$postPatientId   = (int)($_POST['patient_id'] ?? $prePatientId);
$postPatientName = '';
$postPatientInfo = '';
if ($postPatientId) {
    foreach ($allPatients as $p) {
        if ((int)$p['id'] === $postPatientId) {
            $postPatientName = $p['name'];
            $age = calculateAge($p['birth_date']);
            $postPatientInfo = "NIK: {$p['nik']} · ".($p['gender']==='L'?'L':'P')." · $age tahun";
            break;
        }
    }
}

$pageTitle  = 'Buat Antrian';
$activeMenu = 'queues_create';
ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Buat Antrian Baru</h1>
        <p class="page-subtitle">
            <?= date('d F Y') ?> &nbsp;·&nbsp;
            Nomor berikutnya: <strong style="color:var(--blue);font-size:16px"><?= $nextNum ?></strong>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">← Daftar Antrian</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
    <svg viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <ul style="margin:0;padding-left:16px">
        <?php foreach ($errors as $e): ?>
        <li><?= sanitize($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="two-col-grid">

    <!-- ══ KIRI: FORM ════════════════════════════════════════════════ -->
    <div class="flex flex-col gap-3">

        <div class="card">
            <div class="card-header"><span class="card-title">Data Antrian</span></div>
            <div class="card-body">
                <form method="POST" action="" id="queueForm" novalidate>

                    <!-- Pencarian pasien -->
                    <div class="form-group">
                        <label class="form-label">Cari & Pilih Pasien <span class="req">*</span></label>
                        <div style="position:relative">
                            <input type="text" id="patientSearchBox" class="form-control"
                                   placeholder="Ketik nama, NIK, atau no. HP..."
                                   autocomplete="off"
                                   value="<?= sanitize($postPatientName) ?>">
                            <div id="ajaxDropdown"
                                 style="display:none;position:absolute;top:100%;left:0;right:0;
                                        background:#fff;border:1px solid var(--gray-400);
                                        border-top:none;border-radius:0 0 var(--r) var(--r);
                                        box-shadow:var(--shadow-md);z-index:200;max-height:200px;overflow-y:auto">
                            </div>
                        </div>
                        <!-- PERBAIKAN KRITIS: hidden field yang dikirim ke server -->
                        <input type="hidden" name="patient_id" id="patientIdHidden" value="<?= $postPatientId ?>">
                        <div class="form-hint">Atau klik <strong>Pilih</strong> dari tabel pasien di bawah</div>
                    </div>

                    <!-- Info pasien terpilih -->
                    <div id="selectedBox"
                         style="display:<?= $postPatientId ? 'flex' : 'none' ?>;
                                align-items:center;gap:12px;background:var(--blue-pale);
                                border:1px solid var(--blue-muted);border-radius:var(--r);
                                padding:12px 14px;margin-bottom:16px">
                        <div id="selAvatar"
                             style="width:36px;height:36px;border-radius:var(--r);background:var(--navy);
                                    color:white;display:flex;align-items:center;justify-content:center;
                                    font-size:12px;font-weight:700;flex-shrink:0">
                            <?= $postPatientName ? strtoupper(substr($postPatientName,0,2)) : '??' ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:14px;font-weight:700" id="selName"><?= sanitize($postPatientName) ?></div>
                            <div style="font-size:12px;color:var(--gray-500)" id="selInfo"><?= sanitize($postPatientInfo) ?></div>
                        </div>
                        <button type="button" onclick="clearPatient()"
                                style="border:1px solid var(--gray-300);background:none;border-radius:var(--r-sm);
                                       padding:3px 8px;font-size:11px;color:var(--gray-500);cursor:pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Dokter -->
                    <div class="form-group">
                        <label class="form-label">Tugaskan ke Dokter</label>
                        <select class="form-control" name="doctor_id">
                            <option value="">— Belum ditugaskan —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= ((int)($_POST['doctor_id'] ?? 0) === $d['id']) ? 'selected' : '' ?>>
                                dr. <?= sanitize($d['name']) ?> — <?= sanitize($d['specialization']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Catatan -->
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="Kontrol, urgent, rujukan, dll..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="flex justify-end gap-2" style="margin-top:8px">
                        <a href="<?= BASE_URL ?>/queues" class="btn btn-outline">Batal</a>
                        <!-- PERBAIKAN: TIDAK disabled, validasi di server -->
                        <button type="submit" class="btn btn-primary">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Daftarkan ke Antrian
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- Tabel pasien — klik langsung pilih -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Daftar Pasien (<?= count($allPatients) ?>)</span>
            </div>
            <div style="padding:10px 14px 4px">
                <input type="text" id="tblFilter" class="form-control"
                       placeholder="Filter: nama, NIK, atau HP..." style="margin-bottom:6px">
            </div>
            <div style="max-height:340px;overflow-y:auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th><th>NIK</th><th>L/P</th><th>Usia</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tblBody">
                        <?php if (!$allPatients): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gray-400)">
                            Belum ada pasien. <a href="<?= BASE_URL ?>/patients/add">Tambah pasien</a>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($allPatients as $p):
                            $age = calculateAge($p['birth_date']);
                            $isSel = ((int)$postPatientId === (int)$p['id']);
                        ?>
                        <tr class="ptrow<?= $isSel ? ' tr-sel' : '' ?>"
                            id="prow<?= $p['id'] ?>"
                            data-name="<?= htmlspecialchars(mb_strtolower($p['name']),ENT_QUOTES) ?>"
                            data-nik="<?= htmlspecialchars($p['nik'],ENT_QUOTES) ?>"
                            data-phone="<?= htmlspecialchars($p['phone']??'',ENT_QUOTES) ?>">
                            <td class="td-name"><?= sanitize($p['name']) ?></td>
                            <td class="text-xs text-muted"><?= sanitize($p['nik']) ?></td>
                            <td class="text-xs"><?= $p['gender']==='L'?'L':'P' ?></td>
                            <td class="text-xs"><?= $age ?>th</td>
                            <td>
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="pickPatient(
                                            <?= (int)$p['id'] ?>,
                                            <?= json_encode($p['name']) ?>,
                                            <?= json_encode($p['nik']) ?>,
                                            <?= json_encode($p['gender']) ?>,
                                            <?= $age ?>,
                                            <?= json_encode($p['insurance_type']) ?>
                                        )">Pilih</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /kiri -->

    <!-- ══ KANAN: INFO ════════════════════════════════════════════════ -->
    <div class="flex flex-col gap-3">

        <div class="card">
            <div class="card-header"><span class="card-title">Status Antrian Hari Ini</span></div>
            <div class="card-body">
                <div class="vitals-grid" style="grid-template-columns:1fr 1fr 1fr">
                    <div class="vital-item">
                        <div class="vital-value"><?= $totalToday ?></div>
                        <div class="vital-label">Total</div>
                    </div>
                    <div class="vital-item" style="background:var(--amber-bg);border-color:var(--amber-border)">
                        <div class="vital-value" style="color:var(--amber)"><?= $infoCnts['waiting'] ?? 0 ?></div>
                        <div class="vital-label">Menunggu</div>
                    </div>
                    <div class="vital-item" style="background:var(--green-bg);border-color:var(--green-border)">
                        <div class="vital-value" style="color:var(--green)"><?= $infoCnts['done'] ?? 0 ?></div>
                        <div class="vital-label">Selesai</div>
                    </div>
                </div>
                <div style="background:var(--navy);border-radius:var(--r);padding:14px;margin-top:12px;text-align:center">
                    <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.07em;margin-bottom:2px">
                        Nomor Berikutnya
                    </div>
                    <div style="font-size:38px;font-weight:700;color:white;letter-spacing:-1px;line-height:1.1">
                        <?= $nextNum ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Dokter Bertugas</span></div>
            <div class="card-body" style="padding:10px">
                <?php if ($doctors): ?>
                <?php foreach ($doctors as $d): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px;border-bottom:1px solid var(--gray-200)">
                    <div style="width:34px;height:34px;border-radius:50%;background:var(--navy);color:white;
                                display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                        <?= strtoupper(substr($d['name'],0,2)) ?>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:600">dr. <?= sanitize($d['name']) ?></div>
                        <div style="font-size:11px;color:var(--gray-500)"><?= sanitize($d['specialization']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-sm text-muted" style="padding:8px">Belum ada dokter terdaftar.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="border-color:var(--blue-muted);background:var(--blue-pale)">
            <div class="card-body" style="padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">
                    Cara Membuat Antrian
                </div>
                <ol style="padding-left:16px;font-size:12px;color:var(--gray-700);line-height:2;margin:0">
                    <li>Ketik nama/NIK di kolom pencarian, <em>atau</em><br>klik <strong>Pilih</strong> dari tabel di bawah</li>
                    <li>Pilih dokter (opsional)</li>
                    <li>Klik <strong>Daftarkan ke Antrian</strong></li>
                </ol>
            </div>
        </div>

    </div><!-- /kanan -->

</div>

<style>
.tr-sel { background: var(--blue-pale) !important; }
.tr-sel td { color: var(--blue); font-weight: 600; }
</style>

<script>
(function() {
    const searchBox    = document.getElementById('patientSearchBox');
    const ajaxDrop     = document.getElementById('ajaxDropdown');
    const hiddenId     = document.getElementById('patientIdHidden');
    const selBox       = document.getElementById('selectedBox');
    const selAvatar    = document.getElementById('selAvatar');
    const selName      = document.getElementById('selName');
    const selInfo      = document.getElementById('selInfo');
    const tblFilter    = document.getElementById('tblFilter');

    // ── Pilih pasien ─────────────────────────────────────────────────
    window.pickPatient = function(id, name, nik, gender, age, insurance) {
        hiddenId.value         = id;
        searchBox.value        = name;
        selAvatar.textContent  = name.substring(0,2).toUpperCase();
        selName.textContent    = name;
        selInfo.textContent    = 'NIK: ' + nik + ' · ' + (gender==='L'?'L':'P') + ' · ' + age + ' tahun · ' + insurance;
        selBox.style.display   = 'flex';
        ajaxDrop.style.display = 'none';

        // Highlight baris
        document.querySelectorAll('.ptrow').forEach(r => r.classList.remove('tr-sel'));
        const row = document.getElementById('prow' + id);
        if (row) { row.classList.add('tr-sel'); row.scrollIntoView({block:'nearest'}); }

        // Scroll ke form
        document.getElementById('queueForm').scrollIntoView({behavior:'smooth', block:'start'});
    };

    // ── Hapus pilihan ─────────────────────────────────────────────────
    window.clearPatient = function() {
        hiddenId.value       = '';
        searchBox.value      = '';
        selBox.style.display = 'none';
        document.querySelectorAll('.ptrow').forEach(r => r.classList.remove('tr-sel'));
    };

    // ── AJAX autocomplete ─────────────────────────────────────────────
    let timer;
    searchBox.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();

        // Filter tabel lokal sekaligus
        filterTable(q);

        if (q.length < 2) { ajaxDrop.style.display = 'none'; return; }

        timer = setTimeout(async () => {
            try {
                const r = await fetch('/apb/search_patients?q=' + encodeURIComponent(q), {credentials:'same-origin'});
                if (!r.ok) throw 0;
                const data = await r.json();
                if (!Array.isArray(data)) throw 0;

                ajaxDrop.innerHTML = '';
                if (!data.length) {
                    ajaxDrop.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:var(--gray-500)">Tidak ditemukan — coba klik Pilih dari tabel</div>';
                } else {
                    data.forEach(p => {
                        const d = document.createElement('div');
                        d.style.cssText = 'padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:13px';
                        d.innerHTML = '<strong>' + e(p.name) + '</strong><span style="color:var(--gray-400);margin-left:8px;font-size:11px">NIK: ' + e(p.nik) + ' · ' + (p.gender==='L'?'L':'P') + ' · ' + p.age + 'th</span>';
                        d.onmouseover = () => d.style.background = 'var(--blue-pale)';
                        d.onmouseout  = () => d.style.background = '';
                        d.onclick     = () => pickPatient(p.id, p.name, p.nik, p.gender, p.age, p.insurance_type||'Umum');
                        ajaxDrop.appendChild(d);
                    });
                }
                ajaxDrop.style.display = 'block';
            } catch (_) { ajaxDrop.style.display = 'none'; }
        }, 280);
    });

    document.addEventListener('click', ev => {
        if (!searchBox.contains(ev.target) && !ajaxDrop.contains(ev.target))
            ajaxDrop.style.display = 'none';
    });

    // ── Filter tabel ─────────────────────────────────────────────────
    function filterTable(q) {
        const rows = document.querySelectorAll('.ptrow');
        const lq   = q.toLowerCase();
        rows.forEach(r => {
            const match = !q
                || r.dataset.name.includes(lq)
                || r.dataset.nik.includes(q)
                || r.dataset.phone.includes(q);
            r.style.display = match ? '' : 'none';
        });
    }

    if (tblFilter) tblFilter.addEventListener('input', function() { filterTable(this.value.trim()); });

    function e(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
})();
</script>

<?php
// BUG FIX: gunakan __DIR__ agar path selalu absolut, tidak bergantung working directory server
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';