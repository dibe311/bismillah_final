<?php
/**
 * patients/edit.php  — MODULE 2
 * Edit existing patient. Admin & Perawat only.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin', 'perawat']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flashMessage('error', 'ID pasien tidak valid.'); redirect('patients'); }

$patient = $db->prepare("SELECT * FROM patients WHERE id = ? AND is_active = 1");
$patient->execute([$id]);
$patient = $patient->fetch();
if (!$patient) { flashMessage('error', 'Pasien tidak ditemukan.'); redirect('patients'); }

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

    // Validate
    if (empty($data['nik']))              $errors[] = 'NIK wajib diisi.';
    elseif (strlen($data['nik']) !== 16)  $errors[] = 'NIK harus 16 digit angka.';
    if (empty($data['name']))             $errors[] = 'Nama pasien wajib diisi.';
    if (!in_array($data['gender'], ['L','P'])) $errors[] = 'Jenis kelamin tidak valid.';
    if (empty($data['birth_date']))       $errors[] = 'Tanggal lahir wajib diisi.';

    // NIK uniqueness (exclude self)
    if (!$errors) {
        $chk = $db->prepare("SELECT id FROM patients WHERE nik = ? AND id != ?");
        $chk->execute([$data['nik'], $id]);
        if ($chk->fetch()) $errors[] = 'NIK sudah digunakan pasien lain.';
    }

    if (!$errors) {
        $stmt = $db->prepare("
            UPDATE patients SET
              nik=?, name=?, gender=?, birth_date=?, blood_type=?,
              address=?, phone=?, email=?, insurance_number=?,
              insurance_type=?, allergy=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $data['nik'], $data['name'], $data['gender'], $data['birth_date'],
            $data['blood_type'], $data['address'], $data['phone'], $data['email'],
            $data['insurance_number'], $data['insurance_type'], $data['allergy'],
            $id
        ]);
        flashMessage('success', "Data pasien {$data['name']} berhasil diperbarui.");
        redirect("patients/view?id=$id");
    }

    // Re-populate with POST data on error
    $patient = array_merge($patient, $data);
}

$pageTitle  = 'Edit Pasien';
$activeMenu = 'patients';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Edit Data Pasien</h1>
    <p class="page-subtitle">ID: <?= $id ?> — <?= sanitize($patient['name']) ?></p>
  </div>
  <div class="flex gap-2">
    <a href="<?= BASE_URL ?>/patients/view?id=<?= $id ?>" class="btn btn-ghost">← Kembali</a>
    <a href="<?= BASE_URL ?>/patients" class="btn btn-outline">Daftar Pasien</a>
  </div>
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
          <input class="form-control" type="text" name="nik" maxlength="16" pattern="[0-9]{16}"
                 value="<?= sanitize($patient['nik']) ?>" required>
          <div class="form-hint">16 digit — sesuai KTP</div>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="req">*</span></label>
          <input class="form-control" type="text" name="name"
                 value="<?= sanitize($patient['name']) ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin <span class="req">*</span></label>
            <select class="form-control" name="gender" required>
              <option value="L" <?= $patient['gender']==='L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= $patient['gender']==='P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Gol. Darah</label>
            <select class="form-control" name="blood_type">
              <?php foreach (['unknown','A','B','AB','O'] as $bt): ?>
              <option value="<?= $bt ?>" <?= $patient['blood_type']===$bt ? 'selected' : '' ?>>
                <?= $bt === 'unknown' ? 'Tidak diketahui' : $bt ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Lahir <span class="req">*</span></label>
          <input class="form-control" type="date" name="birth_date"
                 value="<?= sanitize($patient['birth_date']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="address" rows="3"><?= sanitize($patient['address'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Riwayat Alergi</label>
          <textarea class="form-control" name="allergy" rows="2"
                    placeholder="Contoh: alergi penisilin, seafood..."><?= sanitize($patient['allergy'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Kontak + Asuransi -->
    <div class="flex flex-col gap-3">
      <div class="card">
        <div class="card-header"><span class="card-title">Kontak</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input class="form-control" type="tel" name="phone"
                   value="<?= sanitize($patient['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email"
                   value="<?= sanitize($patient['email'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Asuransi</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Jenis Asuransi</label>
            <select class="form-control" name="insurance_type">
              <?php foreach (['Umum','BPJS','Asuransi Lain'] as $ins): ?>
              <option value="<?= $ins ?>" <?= $patient['insurance_type']===$ins ? 'selected' : '' ?>><?= $ins ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nomor BPJS / Asuransi</label>
            <input class="form-control" type="text" name="insurance_number"
                   value="<?= sanitize($patient['insurance_number'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <a href="<?= BASE_URL ?>/patients/view?id=<?= $id ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
          Simpan Perubahan
        </button>
      </div>
    </div>

  </div>
</form>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
