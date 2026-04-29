<?php
/**
 * includes/sidebar.php
 * Sidebar navigasi utama MediRek.
 * PERUBAHAN: Hapus menu "Analitik" dan "Artikel Kesehatan".
 * BUGFIX: URL antrian diperbaiki dari /queue/ menjadi /queues/.
 */
$user        = currentUser();
$role        = $user['role'];
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="8" fill="#0EA5E9"/>
                <path d="M14 6v16M6 14h16" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <span class="brand-name"><?= APP_NAME ?></span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= sanitize($user['name']) ?></div>
            <div class="user-role badge-<?= $role ?>"><?= ucfirst($role) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>

        <!-- Dashboard — semua role -->
        <a href="<?= BASE_URL ?>/dashboard"
           class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
            </svg>
            Dashboard
        </a>

        <!-- Data Pasien — admin, dokter, perawat -->
        <?php if (hasRole(['admin', 'dokter', 'perawat'])): ?>
        <a href="<?= BASE_URL ?>/patients"
           class="nav-item <?= (strpos($currentPage, 'patient') !== false || (isset($activeMenu) && $activeMenu === 'patients')) ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v1h-3zM4.75 12.094A5.973 5.973 0 004 15v1H1v-1a3 3 0 013.75-2.906z"/>
            </svg>
            Data Pasien
        </a>
        <?php endif; ?>

        <!-- Antrian & Pemeriksaan Awal — admin, perawat -->
        <?php if (hasRole(['admin', 'perawat'])): ?>
        <a href="<?= BASE_URL ?>/queues"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'queues') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            Antrian
        </a>
        <a href="<?= BASE_URL ?>/initial_checks"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'initial_checks') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V7h2v2z" clip-rule="evenodd"/>
            </svg>
            Pemeriksaan Awal
        </a>
        <a href="<?= BASE_URL ?>/medical_records"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'medical_records') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            </svg>
            Rekam Medis
        </a>
        <?php endif; ?>

        <!-- Antrian Saya & Rekam Medis — dokter -->
        <?php if (hasRole(['dokter'])): ?>
        <a href="<?= BASE_URL ?>/queues"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'queues') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            Antrian Saya
        </a>
        <a href="<?= BASE_URL ?>/medical_records"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'medical_records') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            </svg>
            Rekam Medis
        </a>
        <a href="<?= BASE_URL ?>/medical_records/new"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'medical_records_new') ? 'active' : '' ?>"
           style="padding-left:36px;font-size:12.5px">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Catat Rekam Medis
        </a>
        <?php endif; ?>

        <!-- Rekam Medis Saya — pasien -->
        <?php if (hasRole(['pasien'])): ?>
        <a href="<?= BASE_URL ?>/patients/my_records"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'my_records') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
            </svg>
            Rekam Medis Saya
        </a>
        <?php endif; ?>

        <!-- Administrasi — admin -->
        <?php if (hasRole(['admin'])): ?>
        <div class="nav-section-label" style="margin-top:16px">Administrasi</div>
        <a href="<?= BASE_URL ?>/admin/users"
           class="nav-item <?= (isset($activeMenu) && $activeMenu === 'users') ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
            </svg>
            Manajemen User
        </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout" class="nav-item nav-item-logout">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            Keluar
        </a>
    </div>
</aside>
