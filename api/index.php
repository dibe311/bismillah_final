<?php
require_once 'config/app.php';
require_once 'config/database.php';

if (isLoggedIn()) {
    redirect('dashboard');
}

$hospitalName = "MEDIREXRSUD CARUBAN";

// =========================================================
// LOGIKA BACKEND: DATA BPS & DOKTER
// =========================================================
$bps_stories = [];
$doctors = [];
$chart_tren_data = [];
$chart_faskes_data = [];
$db_status = true;

try {
    $db = getDB();

    // 1. Ambil Data Dokter
    $stmtDoc = $db->query("SELECT name FROM users WHERE role='dokter' AND is_active = 1 LIMIT 4");
    if ($stmtDoc) $doctors = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil Indikator BPS
    $stmtIndikator = $db->query("SELECT * FROM v_indikator_landing");
    $raw_indicators = $stmtIndikator ? $stmtIndikator->fetchAll(PDO::FETCH_ASSOC) : [];

    // 3. Ambil Tren Keluhan (Grafik Garis)
    $stmtTren = $db->query("SELECT tahun, tipe_daerah, persentase FROM tren_keluhan WHERE tipe_daerah != 'Total' ORDER BY tahun ASC");
    if ($stmtTren) {
        $tren_raw = $stmtTren->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tren_raw as $row) {
            $chart_tren_data[$row['tipe_daerah']]['labels'][] = $row['tahun'];
            $chart_tren_data[$row['tipe_daerah']]['data'][] = (float)$row['persentase'];
        }
    }

    // 4. Ambil Faskes (Grafik Batang)
    $stmtFaskes = $db->query("SELECT nama_faskes, total FROM faskes_digunakan WHERE tahun = 2024 ORDER BY urutan ASC");
    if ($stmtFaskes) {
        $faskes_raw = $stmtFaskes->fetchAll(PDO::FETCH_ASSOC);
        foreach ($faskes_raw as $row) {
            $chart_faskes_data['labels'][] = $row['nama_faskes'];
            $chart_faskes_data['data'][] = (float)$row['total'];
        }
    }

    if (empty($raw_indicators)) {
        throw new Exception("Tabel kosong");
    }

} catch (Throwable $e) {
    $db_status = false;
    $raw_indicators = [['kode'=>'KELUHAN_TOTAL', 'nilai'=>28.75], ['kode'=>'OBATI_SENDIRI', 'nilai'=>79.93], ['kode'=>'RAWAT_JALAN', 'nilai'=>39.36], ['kode'=>'JKN_PESERTA', 'nilai'=>77.30]];
    $chart_tren_data = ['Perkotaan' => ['labels'=>[2022,2023,2024], 'data'=>[27.32, 27.30, 26.85]], 'Perdesaan' => ['labels'=>[2022,2023,2024], 'data'=>[35.58, 27.31, 30.61]]];
    $chart_faskes_data = ['labels' => ['Praktik Dokter','Puskesmas','Klinik','RS Pemerintah','RS Swasta'], 'data' => [47.78, 20.11, 12.77, 11.25, 8.64]];
}

// Proses Angka menjadi Narasi
foreach ($raw_indicators as $data) {
    if ($data['kode'] == 'KELUHAN_TOTAL') $bps_stories[] = ['judul' => 'Kondisi Warga', 'angka' => $data['nilai'].'%', 'teks' => '3 dari 10 warga sempat mengalami keluhan kesehatan sebulan terakhir.'];
    if ($data['kode'] == 'OBATI_SENDIRI') $bps_stories[] = ['judul' => 'Obat Mandiri', 'angka' => $data['nilai'].'%', 'teks' => '80% warga memilih pengobatan mandiri. RSUD siap jika gejala berlanjut.'];
    if ($data['kode'] == 'RAWAT_JALAN') $bps_stories[] = ['judul' => 'Ke Dokter', 'angka' => $data['nilai'].'%', 'teks' => 'Kesadaran ke faskes naik, 39 dari 100 orang kini langsung ke ahli medis.'];
    if ($data['kode'] == 'JKN_PESERTA') $bps_stories[] = ['judul' => 'Proteksi JKN', 'angka' => $data['nilai'].'%', 'teks' => '77% warga telah terproteksi BPJS, akses medis kini semakin mudah bagi semua.'];
}

$json_tren = json_encode($chart_tren_data);
$json_faskes = json_encode($chart_faskes_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi — <?= $hospitalName ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* TEMA: ADAPTASI PALET WARNA DARI GAMBAR */
        :root {
            --primary: #FFFFFF;
            --secondary: #2563EB;  /* Royal Blue dari gambar */
            --accent: #EFF6FF;     /* Soft Light Blue */
            --text-main: #0F172A;  /* Deep Navy/Slate */
            --text-dim: #475569;   /* Muted Slate */
            --theme-grad: linear-gradient(135deg, #1D4ED8 0%, #3B82F6 100%);
            --transition: all 0.4s ease;
            --shadow-color: rgba(37, 99, 235, 0.15); /* Shadow selaras dengan warna utama */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--primary); color: var(--text-main); line-height: 1.6; overflow-x: hidden; }
        h1, h2, h3 { font-family: 'Playfair Display', serif; font-weight: 900; }

        /* NAVBAR */
        .navbar { position: fixed; top: 0; width: 100%; padding: 25px 5%; z-index: 1000; transition: var(--transition); display: flex; justify-content: space-between; align-items: center; }
        .navbar.scrolled { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 15px 5%; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .brand { font-size: 24px; color: var(--secondary); display: flex; align-items: center; gap: 10px; font-weight: 900; }
        .brand-box { width: 35px; height: 35px; background: var(--theme-grad); color: #FFF; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
        
        .btn { padding: 14px 30px; border-radius: 6px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: var(--transition); border: none; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-theme { background: var(--theme-grad); color: #FFF; box-shadow: 0 10px 20px var(--shadow-color); }
        .btn-theme:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(37, 99, 235, 0.3); }

        /* HERO SECTION */
        .hero { 
            position: relative; height: 100vh; display: flex; align-items: center; padding: 0 5%; 
            background: url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2073&auto=format&fit=crop') center/cover no-repeat;
            background-attachment: fixed;
        }
        
        /* Glassmorphism Card */
        .hero-card { 
            position: relative; z-index: 10; max-width: 650px; 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(15px); 
            padding: 50px; 
            border-radius: 16px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.1); 
            border: 1px solid rgba(255,255,255,0.5);
        }
        .hero-card h1 { font-size: clamp(36px, 5vw, 56px); line-height: 1.1; margin-bottom: 20px; color: var(--secondary); }
        .hero-card p { font-size: 17px; color: var(--text-main); margin-bottom: 30px; }

        /* SECTIONS */
        .section { padding: 100px 5%; }
        .section-header { margin-bottom: 60px; text-align: center; }
        .section-header h2 { font-size: 40px; margin-bottom: 10px; color: var(--secondary); }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 60px; }
        .info-card { background: var(--white); padding: 40px; border-left: 4px solid var(--secondary); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); transition: var(--transition); }
        .info-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px var(--shadow-color); }
        .info-card h3 { font-size: 14px; color: var(--secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; font-family: 'Plus Jakarta Sans'; }
        .info-card .number { font-size: 42px; font-family: 'Playfair Display'; margin-bottom: 5px; color: var(--text-main); }

        .charts-container { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; }
        .chart-box { background: var(--white); padding: 30px; border: 1px solid rgba(37, 99, 235, 0.1); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }

        /* FASILITAS IMAGE SECTION */
        .facilities-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; background: var(--accent); padding: 80px 5%; border-radius: 16px; margin: 0 5%; }
        .facility-frame { border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px var(--shadow-color); height: 100%; min-height: 400px; }
        .facility-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* DOCTORS */
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; }
        .doc-card { text-align: center; padding: 30px; background: var(--white); border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); transition: var(--transition); }
        .doc-card:hover { box-shadow: 0 10px 30px var(--shadow-color); border-color: transparent; }
        .doc-img { width: 120px; height: 120px; background: var(--accent); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: var(--secondary); font-weight: bold; border: 3px solid var(--primary); box-shadow: 0 0 0 2px var(--secondary); }

        /* KONTAK & MAPS */
        .contact-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 60px; align-items: center; }
        .map-frame { border-radius: 12px; overflow: hidden; height: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }

        @media (max-width: 992px) {
            .charts-container, .facilities-grid, .contact-grid { grid-template-columns: 1fr; }
            .facilities-grid { margin: 0; border-radius: 0; }
            .hero-card { padding: 30px; }
        }
    </style>
</head>
<body>

    <nav class="navbar" id="mainNav">
        <div class="brand">
            <div class="brand-box">C</div> <?= $hospitalName ?>
        </div>
        <div>
            <a href="/login" class="btn btn-theme">LOGIN/DAFTAR</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-card">
            <p style="color: var(--secondary); font-weight: 700; letter-spacing: 2px; margin-bottom: 10px;">EXCELLENCE IN CARE</p>
            <h1>Pemulihan Medis yang Profesional.</h1>
            <p>RSUD Caruban menghadirkan pelayanan kesehatan modern di Kabupaten Madiun. Kami mengutamakan keselamatan dan kenyamanan demi kesembuhan optimal Anda.</p>
            <a href="/register" class="btn btn-theme">Daftar Pasien Baru</a>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2>Pusat Informasi Kesehatan</h2>
            <p style="color: var(--text-dim);">Mengolah data BPS Jawa Timur menjadi panduan kesehatan warga Caruban.</p>
        </div>

        <div class="info-grid">
            <?php foreach($bps_stories as $s): ?>
            <div class="info-card">
                <h3><?= $s['judul'] ?></h3>
                <div class="number"><?= $s['angka'] ?></div>
                <p style="color: var(--text-dim); font-size: 14px;"><?= $s['teks'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="charts-container">
            <div class="chart-box">
                <p style="margin-bottom: 20px; color: var(--secondary); font-weight: 700;">Tren Keluhan Kesehatan Warga</p>
                <canvas id="trenChart" height="250"></canvas>
            </div>
            <div class="chart-box">
                <p style="margin-bottom: 20px; color: var(--secondary); font-weight: 700;">Fasilitas Pilihan Warga</p>
                <canvas id="faskesChart" height="250"></canvas>
            </div>
        </div>
    </section>

    <section>
        <div class="facilities-grid">
            <div>
                <h2 style="font-size: 36px; margin-bottom: 20px; color: var(--text-main);">Fasilitas <span style="color: var(--secondary);">Unggulan Kami</span></h2>
                <p style="color: var(--text-dim); margin-bottom: 30px;">Lingkungan yang bersih, terawat, dan didukung alat medis modern untuk memastikan kenyamanan pasien selama masa perawatan di RSUD Caruban.</p>
                <a href="/register" class="btn btn-theme">Ambil Antrian Online</a>
            </div>
            <div class="facility-frame">
                <img src="Screenshot 2026-04-26 213704.png" alt="Fasilitas RSUD Caruban">
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2>Dokter Spesialis Kami</h2>
            <p style="color: var(--text-dim);">Tenaga ahli profesional yang siap melayani dengan sepenuh hati.</p>
        </div>
        <div class="doc-grid">
            <?php foreach($doctors as $d): ?>
            <div class="doc-card">
                <div class="doc-img"><?= substr($d['name'], 0, 1) ?></div>
                <h3 style="font-family: 'Plus Jakarta Sans'; font-size: 18px;"><?= $d['name'] ?></h3>
                <p style="color: var(--secondary); font-size: 12px; font-weight: bold; margin-top: 5px;">SPESIALIS MEDIS</p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section" style="background: var(--primary);">
        <div class="contact-grid">
            <div>
                <h2 style="font-size: 40px; margin-bottom: 20px;">RSUD <span style="color: var(--secondary);">Caruban</span></h2>
                <p style="color: var(--text-dim); margin-bottom: 30px;">Siaga 24 Jam melayani kegawatdaruratan dan perawatan intensif untuk masyarakat Kabupaten Madiun dan sekitarnya.</p>
                <p><strong>Alamat:</strong> Jl. Raya Madiun-Surabaya Km. 2, Caruban</p>
                <p><strong>Hotline IGD:</strong> (0351) 388141</p>
                <p><strong>Email:</strong> info@rsudcaruban.go.id</p>
            </div>
            <div class="map-frame">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3954.912643534571!2d111.6601449!3d-7.5843477!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e79b8a8b13d2f9f%3A0x286395e54d89e4ad!2sRSUD%20Caruban!5e0!3m2!1sid!2sid!4v1716174500000!5m2!1sid!2sid" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <footer style="padding: 40px 5%; border-top: 1px solid rgba(37, 99, 235, 0.1); text-align: center; color: var(--text-dim); font-size: 14px;">
        © <?= date('Y') ?> RSUD Caruban. Premium Health System Integrity.
    </footer>

    <script>
        // Navbar Scroll 
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('mainNav');
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });

        // CHARTS CONFIG (Disesuaikan dengan Palet Baru)
        Chart.defaults.color = '#475569';
        Chart.defaults.font.family = "'Plus Jakarta Sans'";

        // 1. Line Chart 
        const trenData = <?= $json_tren ?>;
        new Chart(document.getElementById('trenChart'), {
            type: 'line',
            data: {
                labels: trenData.Perkotaan.labels,
                datasets: [
                    { label: 'Kota', data: trenData.Perkotaan.data, borderColor: '#2563EB', tension: 0.4, fill: true, backgroundColor: 'rgba(37, 99, 235, 0.05)' },
                    { label: 'Desa', data: trenData.Perdesaan.data, borderColor: '#60A5FA', borderDash: [5,5], tension: 0.4 }
                ]
            },
            options: { responsive: true, scales: { y: { grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { grid: { display: false } } } }
        });

        // 2. Bar Chart 
        const faskesData = <?= $json_faskes ?>;
        new Chart(document.getElementById('faskesChart'), {
            type: 'bar',
            data: {
                labels: faskesData.labels,
                datasets: [{ data: faskesData.data, backgroundColor: ['#1E40AF', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD'], borderRadius: 4 }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.05)' } }, x: { grid: { display: false } } } }
        });
    </script>
</body>
</html>