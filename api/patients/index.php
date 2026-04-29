<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['admin','dokter','perawat']);

$db    = getDB();
$flash = getFlash();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where  = "WHERE p.is_active = 1";
$params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.phone LIKE ?)";
    $s      = "%$search%";
    $params = [$s, $s, $s];
}

$total = $db->prepare("SELECT COUNT(*) FROM patients p $where");
$total->execute($params);
$total      = (int)$total->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt = $db->prepare("
    SELECT p.*, u.name AS created_by_name
    FROM patients p
    LEFT JOIN users u ON p.created_by = u.id
    $where
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle  = 'Data Pasien';
$activeMenu = 'patients';
ob_start();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Data Pasien</h1>
        <p class="page-subtitle"><?= number_format($total) ?> pasien terdaftar</p>
    </div>
    <?php if (hasRole(['admin','perawat'])): ?>
    <a href="<?= BASE_URL ?>/patients/add" class="btn btn-primary">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        Tambah Pasien
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Pasien</span>
        <form method="GET" action="" style="display:flex;gap:8px">
            <div class="search-bar">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                <input type="text" name="q" placeholder="Cari nama, NIK, telepon..." value="<?= sanitize($search) ?>">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
            <?php if ($search): ?>
            <a href="<?= BASE_URL ?>/patients" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($patients): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Pasien</th>
                    <th>NIK</th>
                    <th>Gender</th>
                    <th>Usia</th>
                    <th>Telepon</th>
                    <th>Asuransi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): ?>
                <tr>
                    <td>
                        <div class="td-name"><?= sanitize($p['name']) ?></div>
                        <div class="text-xs text-muted"><?= sanitize($p['email'] ?? '—') ?></div>
                    </td>
                    <td class="text-sm"><?= sanitize($p['nik']) ?></td>
                    <td class="text-sm"><?= $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                    <td class="text-sm"><?= calculateAge($p['birth_date']) ?> th</td>
                    <td class="text-sm"><?= sanitize($p['phone'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $p['insurance_type'] === 'BPJS' ? 'badge-done' : 'badge-draft' ?>">
                            <?= sanitize($p['insurance_type']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="<?= BASE_URL ?>/patients/view?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Detail</a>
                            <?php if (hasRole(['admin','perawat'])): ?>
                            <a href="<?= BASE_URL ?>/patients/edit?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <?php endif; ?>
                            <?php if (hasRole('admin')): ?>
                            <a href="<?= BASE_URL ?>/patients/delete?id=<?= $p['id'] ?>"
                               class="btn btn-danger btn-sm"
                               data-confirm="Hapus pasien <?= sanitize($p['name']) ?>?">Hapus</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
        <span class="text-sm text-muted">Halaman <?= $page ?> dari <?= $totalPages ?></span>
        <div style="display:flex;gap:6px">
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"
               class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <p class="empty-state-title"><?= $search ? 'Pasien tidak ditemukan' : 'Belum ada pasien' ?></p>
        <?php if (!$search && hasRole(['admin','perawat'])): ?>
        <a href="<?= BASE_URL ?>/patients/add" class="btn btn-primary btn-sm">+ Tambah Pasien</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../includes/layout.php';
