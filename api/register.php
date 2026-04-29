<?php
/**
 * register.php
 * TASK 1: Multi-role registration
 * TASK 4: Hardened auth logic
 */
require_once 'config/app.php';
require_once 'config/database.php';

// CATATAN: isLoggedIn() TIDAK diblokir di sini agar user yang sudah login
// tetap bisa mengakses halaman register (misal: admin mendaftarkan akun lain).
// Hapus komentar di bawah jika ingin membatasi kembali:
// if (isLoggedIn()) redirect('dashboard');

const ALLOWED_ROLES = ['admin', 'dokter', 'perawat', 'pasien'];

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'          => trim($_POST['name']          ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'phone'         => trim($_POST['phone']         ?? ''),
        'role'          => trim($_POST['role']          ?? 'pasien'),
        'province'      => trim($_POST['province']      ?? ''),
        'province_name' => trim($_POST['province_name'] ?? ''),
        'city'          => trim($_POST['city']          ?? ''),
        'city_name'     => trim($_POST['city_name']     ?? ''),
    ];
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($old['name']))                                   $errors['name']     = 'Nama wajib diisi.';
    elseif (strlen($old['name']) < 3)                          $errors['name']     = 'Nama minimal 3 karakter.';
    if (empty($old['email']))                                  $errors['email']    = 'Email wajib diisi.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email']    = 'Format email tidak valid.';
    if (strlen($password) < 6)                                 $errors['password'] = 'Password minimal 6 karakter.';
    if ($password !== $confirm)                                $errors['confirm']  = 'Konfirmasi password tidak cocok.';
    if (!in_array($old['role'], ALLOWED_ROLES, true)) {
        $errors['role'] = 'Role tidak valid.';
        $old['role']    = 'pasien';
    }
    if (empty($old['province'])) $errors['province'] = 'Provinsi wajib dipilih.';
    if (empty($old['city']))     $errors['city']     = 'Kabupaten/Kota wajib dipilih.';

    if (!$errors) {
        $db  = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$old['email']]);
        if ($chk->fetch()) $errors['email'] = 'Email sudah terdaftar.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare(
            "INSERT INTO users (name, email, password, phone, role, province_code, province_name, city_code, city_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $old['name'], $old['email'], $hash, $old['phone'], $old['role'],
            $old['province'], $old['province_name'], $old['city'], $old['city_name'],
        ]);
        flashMessage('success', 'Akun berhasil dibuat. Silakan masuk.');
        redirect('login');
    }
}

$_baseUrl   = BASE_URL;
$_savedProv = addslashes($old['province'] ?? '');
$_savedCity = addslashes($old['city']     ?? '');

$extraScript = <<<JSEOF
<script>
(function () {
  const WILAYAH_PROXY = '{$_baseUrl}/apb/wilayah';

  const selProv     = document.getElementById('province');
  const selCity     = document.getElementById('city');
  const hidProvName = document.getElementById('province_name');
  const hidCityName = document.getElementById('city_name');

  const savedProv = '{$_savedProv}';
  const savedCity = '{$_savedCity}';

  // ── Fetch provinces dari proxy lokal (PHP → emsifa, tidak kena CORS) ──
  async function fetchProvinces() {
    const res = await fetch(WILAYAH_PROXY + '?type=provinces');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!Array.isArray(json)) throw new Error('Response tidak valid');
    return json; // [{id, name}, ...]
  }

  async function fetchCities(provId) {
    const res = await fetch(WILAYAH_PROXY + '?type=cities&id=' + encodeURIComponent(provId));
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!Array.isArray(json)) return [];
    return json; // [{id, name}, ...]
  }

  function isiProvinsi(provinsi) {
    selProv.innerHTML = '<option value="">— Pilih Provinsi —</option>';
    provinsi.forEach(function(p) {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.name;
      opt.dataset.name = p.name;
      if (p.id === savedProv) opt.selected = true;
      selProv.appendChild(opt);
    });
    selProv.disabled = false;
    if (savedProv) loadKabupaten(savedProv);
  }

  async function loadKabupaten(provId) {
    selCity.innerHTML = '<option value="">Memuat kota…</option>';
    selCity.disabled  = true;
    try {
      const daftar = await fetchCities(provId);
      selCity.innerHTML = '<option value="">— Pilih Kabupaten/Kota —</option>';
      if (!daftar.length) {
        selCity.innerHTML = '<option value="">Tidak ada data untuk provinsi ini</option>';
        return;
      }
      daftar.forEach(function(c) {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        opt.dataset.name = c.name;
        if (c.id === savedCity) opt.selected = true;
        selCity.appendChild(opt);
      });
      selCity.disabled = false;
      if (savedCity) {
        const chosen = selCity.querySelector('option[value="' + savedCity + '"]');
        if (chosen) hidCityName.value = chosen.dataset.name || chosen.textContent;
      }
    } catch(e) {
      selCity.innerHTML = '<option value="">Gagal memuat — coba lagi</option>';
      console.error('[Wilayah] Error kota:', e.message);
    }
  }

  selProv.addEventListener('change', function() {
    const chosen = this.options[this.selectedIndex];
    hidProvName.value = chosen ? (chosen.dataset.name || '') : '';
    hidCityName.value = '';
    selCity.innerHTML = '<option value="">— Pilih Kabupaten/Kota —</option>';
    selCity.disabled  = true;
    if (this.value) loadKabupaten(this.value);
  });

  selCity.addEventListener('change', function() {
    const chosen = this.options[this.selectedIndex];
    hidCityName.value = chosen ? (chosen.dataset.name || '') : '';
  });

  // ── Init ──
  (async function() {
    selProv.disabled  = true;
    selProv.innerHTML = '<option value="">Memuat data provinsi…</option>';
    selCity.disabled  = true;
    try {
      const provinsi = await fetchProvinces();
      isiProvinsi(provinsi);
    } catch (e) {
      selProv.innerHTML = '<option value="">Gagal memuat — muat ulang halaman</option>';
      selProv.disabled  = false;
      console.error('[Wilayah] Error:', e.message);
    }
  })();
})();
</script>
JSEOF;

$pageTitle = 'Daftar Akun';
$cssFile   = 'auth';
$bodyClass = 'auth-body';
require_once 'includes/header.php';
?>

<div class="auth-wrapper">
  <!-- LEFT PANEL -->
  <div class="auth-panel-left">
    <div class="auth-brand">
      <div class="auth-brand-icon">
        <svg width="20" height="20" viewBox="0 0 28 28" fill="none">
          <path d="M14 4v20M4 14h20" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="auth-brand-name"><?= APP_NAME ?></span>
    </div>
    <div class="auth-hero">
      <h2 class="auth-hero-title">Sistem Rekam Medis<br><em>Terintegrasi</em></h2>
      <p class="auth-hero-desc">Platform digital untuk tenaga medis dan pasien dalam ekosistem layanan kesehatan yang efisien.</p>
    </div>
    <div class="auth-features">
      <div class="auth-feature-item">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Role akses berbasis jabatan
      </div>
      <div class="auth-feature-item">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Data rekam medis terenkripsi
      </div>
      <div class="auth-feature-item">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Audit trail & keamanan sesi
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-panel-right">
    <h1 class="auth-form-title">Buat Akun</h1>
    <p class="auth-form-sub">Daftarkan akun Anda ke sistem <?= APP_NAME ?></p>

    <?php if ($errors): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      <div>Mohon periksa kembali isian di bawah ini.</div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="form-group">
        <label class="form-label" for="name">Nama Lengkap <span class="req">*</span></label>
        <input class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
               type="text" id="name" name="name"
               placeholder="Nama lengkap sesuai identitas"
               value="<?= sanitize($old['name'] ?? '') ?>" required autofocus>
        <?php if (isset($errors['name'])): ?><div class="form-error-text"><?= sanitize($errors['name']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email <span class="req">*</span></label>
        <input class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
               type="email" id="email" name="email"
               placeholder="email@instansi.id"
               value="<?= sanitize($old['email'] ?? '') ?>" required>
        <?php if (isset($errors['email'])): ?><div class="form-error-text"><?= sanitize($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">No. Telepon</label>
        <input class="form-control" type="tel" id="phone" name="phone"
               placeholder="08xxxxxxxxxx" value="<?= sanitize($old['phone'] ?? '') ?>">
      </div>

      <!-- Provinsi -->
      <div class="form-group">
        <label class="form-label" for="province">Provinsi <span class="req">*</span></label>
        <select class="form-control <?= isset($errors['province']) ? 'error' : '' ?>"
                id="province" name="province" required>
          <option value="">— Memuat provinsi… —</option>
        </select>
        <input type="hidden" id="province_name" name="province_name"
               value="<?= sanitize($old['province_name'] ?? '') ?>">
        <?php if (isset($errors['province'])): ?><div class="form-error-text"><?= sanitize($errors['province']) ?></div><?php endif; ?>
      </div>

      <!-- Kabupaten / Kota -->
      <div class="form-group">
        <label class="form-label" for="city">Kabupaten / Kota <span class="req">*</span></label>
        <select class="form-control <?= isset($errors['city']) ? 'error' : '' ?>"
                id="city" name="city" required disabled>
          <option value="">— Pilih provinsi dulu —</option>
        </select>
        <input type="hidden" id="city_name" name="city_name"
               value="<?= sanitize($old['city_name'] ?? '') ?>">
        <?php if (isset($errors['city'])): ?><div class="form-error-text"><?= sanitize($errors['city']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="role">Role / Jabatan <span class="req">*</span></label>
        <select class="form-control <?= isset($errors['role']) ? 'error' : '' ?>"
                id="role" name="role" required>
          <?php foreach (ALLOWED_ROLES as $r): ?>
            <option value="<?= $r ?>" <?= ($old['role'] ?? 'pasien') === $r ? 'selected' : '' ?>>
              <?= ucfirst($r) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Role dokter/perawat/admin dapat diverifikasi ulang oleh administrator.</div>
        <?php if (isset($errors['role'])): ?><div class="form-error-text"><?= sanitize($errors['role']) ?></div><?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">Password <span class="req">*</span></label>
          <input class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
                 type="password" id="password" name="password"
                 placeholder="Min. 6 karakter" required>
          <?php if (isset($errors['password'])): ?><div class="form-error-text"><?= sanitize($errors['password']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="confirm_password">Konfirmasi Password <span class="req">*</span></label>
          <input class="form-control <?= isset($errors['confirm']) ? 'error' : '' ?>"
                 type="password" id="confirm_password" name="confirm_password"
                 placeholder="Ulangi password" required>
          <?php if (isset($errors['confirm'])): ?><div class="form-error-text"><?= sanitize($errors['confirm']) ?></div><?php endif; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-navy btn-lg w-full" style="justify-content:center;margin-top:6px">
        Buat Akun
      </button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--gray-500)">
      Sudah punya akun?
      <a href="/login" style="font-weight:600">Masuk di sini</a>
    </p>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
