<?php
/**
 * login.php
 */
require_once 'config/app.php';
require_once 'config/database.php';

if (isLoggedIn()) redirect('dashboard');

$flash    = getFlash();
$error    = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Email atau password tidak valid.';
        } elseif (!(bool)$user['is_active']) {
            $error = 'Akun Anda telah dinonaktifkan. Hubungi administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Email atau password tidak valid.';
        } else {
            
            
            loginUser([
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ]);

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            flashMessage('success', 'Selamat datang, ' . $user['name'] . '!');
            redirect('dashboard');
        }
    }

    $oldEmail = sanitize($email);
}

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
:root{--navy:#0F2744;--navy-light:#1a3a5c;--blue:#1976D2;--blue-pale:#E3F0FB;--blue-muted:#BBDEFB;--green:#2E7D32;--green-bg:#E8F5E9;--green-border:#A5D6A7;--amber:#E65100;--amber-bg:#FFF3E0;--amber-border:#FFCC80;--red:#C62828;--red-bg:#FFEBEE;--red-border:#EF9A9A;--white:#FFFFFF;--gray-400:#BDBDBD;--gray-500:#9E9E9E;--gray-600:#757575;--gray-700:#616161;--gray-900:#212121;--font:'Inter',-apple-system,sans-serif;--r:6px;--r-md:8px;--ease:.15s ease}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--gray-900);line-height:1.55}
a{color:var(--blue);text-decoration:none}
a:hover{text-decoration:underline}
button,input{font-family:inherit;font-size:inherit}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r);font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background var(--ease);white-space:nowrap;line-height:1.4}
.btn-navy{background:var(--navy);color:var(--white);border-color:var(--navy)}
.btn-navy:hover{background:var(--navy-light);color:var(--white)}
.btn-lg{padding:10px 20px;font-size:15px}
.w-full{width:100%}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:5px}
.form-control{width:100%;padding:8px 11px;border:1px solid var(--gray-400);border-radius:var(--r);font-size:13px;color:var(--gray-900);background:var(--white);transition:border-color var(--ease),box-shadow var(--ease);outline:none;line-height:1.5}
.form-control:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(25,118,210,.12)}
.form-control::placeholder{color:var(--gray-400)}
.alert{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;border-radius:var(--r);font-size:13px;margin-bottom:16px;border:1px solid transparent}
.alert svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--green-bg);color:var(--green);border-color:var(--green-border)}
.alert-error{background:var(--red-bg);color:var(--red);border-color:var(--red-border)}
.alert-warning{background:var(--amber-bg);color:var(--amber);border-color:var(--amber-border)}
body.auth-body{background:#F0F4F8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.auth-wrapper{display:flex;width:100%;max-width:860px;border-radius:10px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);border:1px solid #D0D7DE}
.auth-panel-left{width:340px;flex-shrink:0;background:var(--navy);padding:44px 36px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
.auth-panel-left::after{content:'';position:absolute;bottom:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none}
.auth-brand{display:flex;align-items:center;gap:10px}
.auth-brand-icon{width:36px;height:36px;background:var(--blue);border-radius:var(--r);display:flex;align-items:center;justify-content:center}
.auth-brand-name{font-size:17px;font-weight:700;color:var(--white)}
.auth-hero-title{font-size:22px;font-weight:700;color:var(--white);line-height:1.35;margin-bottom:12px}
.auth-hero-title em{color:#90CAF9;font-style:normal}
.auth-hero-desc{font-size:13px;color:rgba(255,255,255,.65);line-height:1.65}
.auth-feature-item{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;color:rgba(255,255,255,.7)}
.auth-feature-item svg{width:14px;height:14px;flex-shrink:0;color:#90CAF9}
.auth-panel-right{flex:1;background:var(--white);padding:44px 40px;display:flex;flex-direction:column;justify-content:center}
.auth-form-title{font-size:20px;font-weight:700;color:var(--gray-900);margin-bottom:4px}
.auth-form-sub{font-size:13px;color:var(--gray-500);margin-bottom:24px}
.demo-accounts{background:var(--blue-pale);border:1px solid var(--blue-muted);border-radius:var(--r);padding:12px 14px;margin-top:18px}
.demo-accounts-title{font-size:11px;font-weight:700;color:var(--blue);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em}
.demo-row{display:grid;grid-template-columns:70px 1fr auto;gap:8px;align-items:center;font-size:12px;padding:4px 0;cursor:pointer;color:var(--gray-700);border-bottom:1px solid rgba(25,118,210,.1)}
.demo-row:last-child{border-bottom:none}
.demo-row:hover{color:var(--blue)}
.demo-role{font-weight:700;color:#424242}
.demo-pass{color:var(--gray-400);font-style:italic}
@media(max-width:700px){.auth-panel-left{display:none}.auth-panel-right{padding:32px 28px}.auth-wrapper{max-width:460px}}
</style>
</head>
<body class="auth-body">

<div class="auth-wrapper">
  <div class="auth-panel-left">
    <div class="auth-brand">
      <div class="auth-brand-icon">
        <svg width="20" height="20" viewBox="0 0 28 28" fill="none">
          <path d="M14 4v20M4 14h20" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="auth-brand-name"><?= APP_NAME ?></span>
    </div>
    <div>
      <h2 class="auth-hero-title">Rekam medis pasien,<br><em>lebih efisien dari sebelumnya.</em></h2>
      <p class="auth-hero-desc">Akses terpusat untuk dokter, perawat, dan tenaga medis lainnya.</p>
    </div>
    <div>
      <div class="auth-feature-item"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Dashboard berbasis role</div>
      <div class="auth-feature-item"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Sesi aman dengan timeout otomatis</div>
      <div class="auth-feature-item"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Rekam medis terintegrasi & aman</div>
    </div>
  </div>

  <div class="auth-panel-right">
    <h1 class="auth-form-title">Masuk ke Sistem</h1>
    <p class="auth-form-sub">Gunakan kredensial akun Anda</p>

    <?php if ($timeout): ?>
    <div class="alert alert-warning">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      Sesi Anda telah berakhir. Silakan masuk kembali.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="email@medirek.id"
               value="<?= $oldEmail ?>" required autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-navy btn-lg w-full" style="justify-content:center;margin-top:6px">
        Masuk ke Dashboard
      </button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--gray-500)">
      Belum punya akun?
      <a href="/register" style="font-weight:600">Daftar sekarang</a>
    </p>

    <div class="demo-accounts">
      <div class="demo-accounts-title">Akun Demo (password: <strong>password</strong>)</div>
      <div class="demo-row" data-email="admin@medirek.id" data-password="password">
        <span class="demo-role">Admin</span><span>admin@medirek.id</span><span class="demo-pass">password</span>
      </div>
      <div class="demo-row" data-email="dokter@medirek.id" data-password="password">
        <span class="demo-role">Dokter</span><span>dokter@medirek.id</span><span class="demo-pass">password</span>
      </div>
      <div class="demo-row" data-email="perawat@medirek.id" data-password="password">
        <span class="demo-role">Perawat</span><span>perawat@medirek.id</span><span class="demo-pass">password</span>
      </div>
      <div class="demo-row" data-email="pasien@medirek.id" data-password="password">
        <span class="demo-role">Pasien</span><span>pasien@medirek.id</span><span class="demo-pass">password</span>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => { el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(()=>el.remove(),400); }, 4500);
    });
    document.querySelectorAll('.demo-row').forEach(row => {
        row.addEventListener('click', () => {
            const e = document.getElementById('email');
            const p = document.getElementById('password');
            if (e) e.value = row.dataset.email;
            if (p) { p.value = row.dataset.password; p.type='text'; setTimeout(()=>p.type='password',600); }
        });
    });
});
</script>
</body>
</html>
