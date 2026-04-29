<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','perawat']);

$db     = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nik'              => trim($_POST['nik'] ?? ''),
        'name'             => trim($_POST['name'] ?? ''),
        'gender'           => $_POST['gender'] ?? '',
        'birth_date'       => $_POST['birth_date'] ?? '',
        'blood_type'       => $_POST['blood_type'] ?? 'unknown',
        'address'          => trim($_POST['address'] ?? ''),
        'phone'            => trim($_POST['phone'] ?? ''),
        'email'            => trim($_POST['email'] ?? ''),
        'insurance_number' => trim($_POST['insurance_number'] ?? ''),
        'insurance_type'   => $_POST['insurance_type'] ?? 'Umum',
        'allergy'          => trim($_POST['allergy'] ?? ''),
    ];

    if (empty($data['nik']))             $errors[] = 'NIK wajib diisi.';
    elseif (strlen($data['nik']) !== 16) $errors[] = 'NIK harus 16 digit angka.';
    if (empty($data['name']))            $errors[] = 'Nama wajib diisi.';
    if (!in_array($data['gender'], ['L','P'])) $errors[] = 'Jenis kelamin wajib dipilih.';
    if (empty($data['birth_date']))      $errors[] = 'Tanggal lahir wajib diisi.';

    if (!$errors) {
        $nikCheck = $db->prepare("SELECT id FROM patients WHERE nik = ?");
        $nikCheck->execute([$data['nik']]);
        if ($nikCheck->fetch()) $errors[] = 'NIK sudah terdaftar.';
    }

    if (!$errors) {
        $stmt = $db->prepare("
            INSERT INTO patients (nik, name, gender, birth_date, blood_type, address, phone, email, insurance_number, insurance_type, allergy, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $data['nik'], $data['name'], $data['gender'], $data['birth_date'],
            $data['blood_type'], $data['address'], $data['phone'], $data['email'],
            $data['insurance_number'], $data['insurance_type'], $data['allergy'],
            currentUser()['id']
        ]);
        $newId = $db->lastInsertId();
        flashMessage('success', "Pasien <strong>" . htmlspecialchars($data['name']) . "</strong> berhasil ditambahkan.");
        redirect("patients/view?id=$newId");
    }
}

$pageTitle  = 'Tambah Pasien';
$activeMenu = 'patients';
ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tambah Pasien Baru</h1>
        <p class="page-subtitle">Isi data identitas pasien dengan lengkap dan benar</p>
    </div>
    <a href="<?= BASE_URL ?>/patients" class="btn btn-outline">← Kembali</a>
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
    <div class="two-col-grid">
        <!-- Identitas -->
        <div class="card">
            <div class="card-header"><span class="card-title">Identitas Pasien</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">NIK <span class="req">*</span></label>
                    <input class="form-control" type="text" name="nik" placeholder="16 digit NIK" maxlength="16" value="<?= sanitize($_POST['nik'] ?? '') ?>" required>
                    <div class="form-hint">Nomor Induk Kependudukan (16 digit)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                    <input class="form-control" type="text" name="name" placeholder="Nama sesuai KTP" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenis Kelamin <span class="req">*</span></label>
                        <select class="form-control" name="gender" required>
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= ($_POST['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= ($_POST['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Golongan Darah</label>
                        <select class="form-control" name="blood_type">
                            <?php foreach (['unknown','A','B','AB','O'] as $bt): ?>
                            <option value="<?= $bt ?>" <?= ($_POST['blood_type'] ?? 'unknown') === $bt ? 'selected' : '' ?>><?= $bt === 'unknown' ? 'Tidak diketahui' : $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Lahir <span class="req">*</span></label>
                    <input class="form-control" type="date" name="birth_date" value="<?= sanitize($_POST['birth_date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea class="form-control" name="address" rows="3" placeholder="Alamat lengkap..."><?= sanitize($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Riwayat Alergi</label>
                    <textarea class="form-control" name="allergy" rows="2" placeholder="Contoh: alergi penisilin, seafood..."><?= sanitize($_POST['allergy'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Kontak & Asuransi -->
        <div class="flex flex-col gap-3">
            <div class="card">
                <div class="card-header"><span class="card-title">Kontak</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">No. Telepon</label>
                        <input class="form-control" type="tel" name="phone" placeholder="08xxxxxxxxxx" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" placeholder="email@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">Asuransi</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Jenis Asuransi</label>
                        <select class="form-control" name="insurance_type">
                            <option value="Umum" <?= ($_POST['insurance_type'] ?? 'Umum') === 'Umum' ? 'selected' : '' ?>>Umum (Bayar Sendiri)</option>
                            <option value="BPJS" <?= ($_POST['insurance_type'] ?? '') === 'BPJS' ? 'selected' : '' ?>>BPJS Kesehatan</option>
                            <option value="Asuransi Lain" <?= ($_POST['insurance_type'] ?? '') === 'Asuransi Lain' ? 'selected' : '' ?>>Asuransi Lain</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor Asuransi/BPJS</label>
                        <input class="form-control" type="text" name="insurance_number" value="<?= sanitize($_POST['insurance_number'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
                <a href="<?= BASE_URL ?>/patients" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Simpan Pasien
                </button>
            </div>
        </div>
    </div>
</form>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
