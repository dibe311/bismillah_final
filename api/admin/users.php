<?php
/**
 * admin/users.php
 * Manajemen User — hanya Admin.
 * Fitur: daftar user, tambah, edit, toggle aktif, hapus.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole('admin');

$db     = getDB();
$flash  = getFlash();
$errors = [];
$mode   = $_GET['mode'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

$ROLES = ['admin' => 'Admin', 'dokter' => 'Dokter', 'perawat' => 'Perawat', 'pasien' => 'Pasien'];

/* ================================================================
   POST HANDLERS
   ================================================================ */

// Tambah User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = trim($_POST['role']     ?? '');
    $password = trim($_POST['password'] ?? '');
    $spec     = trim($_POST['specialization'] ?? '');

    if (!$name)                                      $errors[] = 'Nama wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Format email tidak valid.';
    if (!array_key_exists($role, $ROLES))            $errors[] = 'Role tidak valid.';
    if (strlen($password) < 6)                       $errors[] = 'Password minimal 6 karakter.';

    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$email]);
        if ($dup->fetch()) $errors[] = 'Email sudah terdaftar.';
    }

    if (!$errors) {
        $db->prepare("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?,?,?,?,1,NOW())")
           ->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
        $newId = (int)$db->lastInsertId();

        if ($role === 'dokter' && $spec) {
            $db->prepare("INSERT INTO doctor_profiles (user_id, specialization) VALUES (?,?) ON DUPLICATE KEY UPDATE specialization=?")
               ->execute([$newId, $spec, $spec]);
        }

        flashMessage('success', "User <strong>" . htmlspecialchars($name) . "</strong> berhasil ditambahkan.");
        redirect('admin/users');
    }
    $mode = 'add';
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $editId   = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name']  ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role']  ?? '');
    $spec     = trim($_POST['specialization'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$name)                                      $errors[] = 'Nama wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Format email tidak valid.';
    if (!array_key_exists($role, $ROLES))            $errors[] = 'Role tidak valid.';
    if ($password && strlen($password) < 6)          $errors[] = 'Password baru minimal 6 karakter.';

    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dup->execute([$email, $editId]);
        if ($dup->fetch()) $errors[] = 'Email sudah digunakan user lain.';
    }

    if (!$errors) {
        if ($password) {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, password=?, updated_at=NOW() WHERE id=?")
               ->execute([$name, $email, $role, password_hash($password, PASSWORD_BCRYPT), $editId]);
        } else {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, updated_at=NOW() WHERE id=?")
               ->execute([$name, $email, $role, $editId]);
        }
        if ($role === 'dokter') {
            $db->prepare("INSERT INTO doctor_profiles (user_id, specialization) VALUES (?,?) ON DUPLICATE KEY UPDATE specialization=?")
               ->execute([$editId, $spec, $spec]);
        }
        flashMessage('success', "User berhasil diperbarui.");
        redirect('admin/users');
    }
    $mode = 'edit';
}

// Toggle Aktif / Nonaktif
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $tid  = (int)$_GET['toggle'];
    $self = (int)currentUser()['id'];
    if ($tid === $self) {
        flashMessage('error', 'Anda tidak bisa menonaktifkan akun sendiri.');
    } else {
        $cur = $db->prepare("SELECT is_active, name FROM users WHERE id=?");
        $cur->execute([$tid]);
        $cur = $cur->fetch();
        if ($cur) {
            $newStatus = $cur['is_active'] ? 0 : 1;
            $db->prepare("UPDATE users SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$newStatus, $tid]);
            $label = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
            flashMessage('success', "User <strong>" . htmlspecialchars($cur['name']) . "</strong> berhasil $label.");
        }
    }
    redirect('admin/users');
}

// Hapus User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $self = (int)currentUser()['id'];
    if ($did === $self) {
        flashMessage('error', 'Anda tidak bisa menghapus akun sendiri.');
    } else {
        $u = $db->prepare("SELECT name FROM users WHERE id=?");
        $u->execute([$did]);
        $u = $u->fetch();
        if ($u) {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$did]);
            flashMessage('success', "User <strong>" . htmlspecialchars($u['name']) . "</strong> berhasil dihapus.");
        }
    }
    redirect('admin/users');
}

/* ================================================================
   DATA TAMPILAN
   ================================================================ */

$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE (u.name LIKE ? OR u.email LIKE ? OR u.role LIKE ?)" : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.role, u.is_active, u.last_login, u.created_at,
           dp.specialization
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    $where
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Data untuk form edit
$editUser = null;
$editSpec = '';
if ($mode === 'edit' && $editId) {
    $eu = $db->prepare("SELECT u.*, dp.specialization FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id WHERE u.id=?");
    $eu->execute([$editId]);
    $editUser = $eu->fetch();
    $editSpec = $editUser['specialization'] ?? '';
    if (!$editUser) $mode = 'list';
}

$pageTitle  = 'Manajemen User';
$activeMenu = 'users';
ob_start();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($mode === 'add' || $mode === 'edit'): ?>
<!-- ============================================================
     FORM TAMBAH / EDIT
     ============================================================ -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $mode === 'add' ? 'Tambah User Baru' : 'Edit User' ?></h1>
        <p class="page-subtitle"><?= $mode === 'add' ? 'Buat akun untuk staf atau pasien' : 'Perbarui data akun' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users" class="btn btn-ghost">← Kembali</a>
</div>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/users">
            <input type="hidden" name="action" value="<?= $mode ?>">
            <?php if ($mode === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Nama Lengkap <span style="color:var(--red)">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="<?= sanitize($editUser['name'] ?? $_POST['name'] ?? '') ?>"
                       placeholder="Nama lengkap" required>
            </div>

            <div class="form-group">
                <label class="form-label">Email <span style="color:var(--red)">*</span></label>
                <input type="email" name="email" class="form-control"
                       value="<?= sanitize($editUser['email'] ?? $_POST['email'] ?? '') ?>"
                       placeholder="email@contoh.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Role <span style="color:var(--red)">*</span></label>
                <select name="role" class="form-control" id="roleSelect" required>
                    <?php foreach ($ROLES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($editUser['role'] ?? $_POST['role'] ?? '') === $k ? 'selected' : '' ?>>
                        <?= $v ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="specGroup" style="display:none">
                <label class="form-label">Spesialisasi Dokter</label>
                <input type="text" name="specialization" class="form-control"
                       value="<?= sanitize($editSpec ?: ($_POST['specialization'] ?? '')) ?>"
                       placeholder="contoh: Dokter Umum, Spesialis Anak…">
            </div>

            <div class="form-group">
                <label class="form-label">
                    Password
                    <?php if ($mode === 'edit'): ?>
                        <span class="text-xs text-muted">(kosongkan jika tidak ingin diubah)</span>
                    <?php else: ?>
                        <span style="color:var(--red)">*</span>
                    <?php endif; ?>
                </label>
                <input type="password" name="password" class="form-control"
                       placeholder="<?= $mode === 'add' ? 'Min. 6 karakter' : 'Isi untuk ganti password' ?>"
                       <?= $mode === 'add' ? 'required' : '' ?>>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <?= $mode === 'add' ? 'Tambah User' : 'Simpan Perubahan' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/users" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const roleSelect = document.getElementById('roleSelect');
    const specGroup  = document.getElementById('specGroup');
    function toggleSpec() {
        specGroup.style.display = roleSelect.value === 'dokter' ? 'block' : 'none';
    }
    roleSelect.addEventListener('change', toggleSpec);
    toggleSpec();
})();
</script>

<?php else: ?>
<!-- ============================================================
     DAFTAR USER
     ============================================================ -->
<div class="page-header">
    <div>
        <h1 class="page-title">Manajemen User</h1>
        <p class="page-subtitle"><?= count($users) ?> user terdaftar</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users?mode=add" class="btn btn-primary">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        Tambah User
    </a>
</div>

<form method="GET" action="" style="margin-bottom:16px;display:flex;gap:8px">
    <div class="search-bar">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
        <input type="text" name="q" placeholder="Cari nama, email, role…" value="<?= sanitize($search) ?>">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Cari</button>
    <?php if ($search): ?><a href="<?= BASE_URL ?>/admin/users" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users): ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="td-name"><?= sanitize($u['name']) ?></div>
                        <?php if (!empty($u['specialization'])): ?>
                        <div class="text-xs text-muted"><?= sanitize($u['specialization']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= sanitize($u['email']) ?></td>
                    <td>
                        <span class="badge badge-<?= $u['role'] ?>">
                            <?= $ROLES[$u['role']] ?? ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span class="badge badge-done">Aktif</span>
                        <?php else: ?>
                        <span class="badge badge-cancelled">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs text-muted">
                        <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="<?= BASE_URL ?>/admin/users?mode=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
                            <a href="<?= BASE_URL ?>/admin/users?toggle=<?= $u['id'] ?>"
                               class="btn btn-sm <?= $u['is_active'] ? 'btn-ghost' : 'btn-success' ?>"
                               data-confirm="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> user <?= sanitize($u['name']) ?>?">
                               <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </a>
                            <a href="<?= BASE_URL ?>/admin/users?delete=<?= $u['id'] ?>"
                               class="btn btn-danger btn-sm"
                               data-confirm="Hapus permanen user <?= sanitize($u['name']) ?>? Tindakan ini tidak bisa dibatalkan.">
                               Hapus
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-muted" style="align-self:center">(akun Anda)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <p class="empty-state-title"><?= $search ? 'User tidak ditemukan' : 'Belum ada user' ?></p>
                            <a href="<?= BASE_URL ?>/admin/users?mode=add" class="btn btn-primary btn-sm">+ Tambah User</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
