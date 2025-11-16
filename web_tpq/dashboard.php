<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Raport TPQ</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --warna-hijau: #00a86b; /* Warna hijau utama */
            --warna-hijau-muda: #e6f7f0; /* Latar welcome card */
            --warna-latar: #f4f7f6; /* Latar belakang abu-abu */
            --warna-teks: #333333;
            --lebar-sidebar: 280px; /* Lebar sidebar saat terbuka */
        }

        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--warna-latar);
        }

        /* --- 1. Sidebar (Menu Samping) --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: var(--lebar-sidebar);
            background-color: var(--warna-hijau);
            color: white;
            z-index: 1000;
            transform: translateX(-100%); /* Sembunyi di kiri */
            transition: transform 0.3s ease-out;
            display: flex;
            flex-direction: column;
        }
        
        /* Status saat sidebar aktif (terbuka) */
        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .sidebar-header .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex-grow: 1; /* Mendorong logout ke bawah */
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .sidebar-nav li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Menu "Dashboard" yang aktif */
        .sidebar-nav li.active a {
            background-color: var(--warna-latar);
            color: var(--warna-hijau);
            border-left: 5px solid white;
            padding-left: 20px;
        }
        
        .sidebar-nav li.active i {
            color: var(--warna-hijau);
        }

        .sidebar-nav li a i {
            width: 30px; /* Memberi jarak ikon dan teks */
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        /* Menu Logout di paling bawah */
        .sidebar-nav li.logout {
            margin-top: auto; /* Mendorong ke bawah */
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* --- 2. Overlay (Latar Gelap) --- */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-out, visibility 0s 0.3s linear;
        }
        
        /* Status saat overlay aktif */
        .overlay.active {
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease-out;
        }
        
        /* --- 3. Konten Utama --- */
        .main-content {
            width: 100%;
            min-height: 100vh;
        }

        /* --- 4. Header (Bilah Atas) --- */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: var(--warna-hijau);
            color: white;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .hamburger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            margin-right: 15px;
        }

        .header-logo img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .header-title {
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.3;
        }

        .header-right .user-profile {
            display: flex;
            align-items: center;
            text-align: right;
            text-decoration: none;
            color: white;
        }

        .user-profile .user-info {
            display: flex; /* Sembunyikan nama di layar kecil jika perlu */
            flex-direction: column;
        }
        
        .user-profile span {
            font-size: 0.8rem;
            font-weight: 500;
        }

        .user-profile .icon-wrapper {
            background-color: white;
            color: var(--warna-hijau);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            font-size: 1.1rem;
        }

        /* --- 5. Area Dashboard --- */
        .dashboard-area {
            padding: 25px 20px;
        }

        .dashboard-area h1 {
            color: var(--warna-teks);
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
        }

        /* --- 6. Grid untuk Kartu (Cards) --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Dua kolom */
            gap: 20px;
        }

        .card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Kartu Welcome (Penuh) */
        .card-welcome {
            grid-column: 1 / -1; /* Ambil 2 kolom */
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 500;
        }
        .card-welcome h2 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
        }
        .card-welcome p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Kartu kecil (Tahun Ajaran, Kelas, Wali) */
        .card-small {
            text-align: center;
            padding: 25px 15px;
        }
        
        .card-small h3 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #fd7e14; /* Oranye untuk Tahun Ajaran */
        }
        
        .card-small p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--warna-teks);
        }
        
        /* Warna judul kartu yg berbeda */
        .card-small.kelas h3 {
            color: #0d6efd; /* Biru */
        }
        
        .card-small.wali h3 {
            color: var(--warna-hijau); /* Hijau */
        }
        
        .card-small.wali p {
            font-size: 1.1rem; /* Font lebih kecil untuk nama */
            font-weight: 600;
            line-height: 1.4;
        }
        
        /* Kartu Wali (Kolom penuh di HP) */
        .card-wali {
             grid-column: 1 / -1;
        }

        /* Kartu Grafik (Penuh) */
        .card-chart {
            grid-column: 1 / -1; /* Ambil 2 kolom */
        }
        
        .card-chart h3 {
            margin: 0 0 20px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--warna-teks);
            text-align: center;
        }
        
        /* Placeholder untuk grafik */
        .chart-placeholder {
            width: 100%;
            height: 200px;
            /* Ini hanya SVG sederhana untuk meniru grafik */
            background-image: url("data:image/svg+xml,%3Csvg width='300' height='100' viewBox='0 0 300 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 75 L80 73 L150 20 L220 30 L280 25' fill='none' stroke='%2300a86b' stroke-width='3'/%3E%3Cg fill='%23aaa' font-size='10' font-family='sans-serif'%3E%3Ctext x='20' y='95'%3E20/21%3C/text%3E%3Ctext x='150' y='95'%3E22/23%3C/text%3E%3Ctext x='280' y='95' text-anchor='end'%3E23/24%3C/text%3E%3C/g%3E%3Cg fill='%23aaa' font-size='10' font-family='sans-serif' text-anchor='end'%3E%3Ctext x='15' y='75'%3E72%3C/text%3E%3Ctext x='15' y='50'%3E75%3C/text%3E%3Ctext x='15' y='25'%3E80%3C/text%3E%3C/g%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="img/logo.jpg" alt="Logo">
            <i class="fas fa-arrow-left close-btn" id="close-btn"></i>
        </div>
        <ul class="sidebar-nav">
            <li class="active">
                <a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li>
                <a href="#"><i class="fas fa-user"></i> Biodata</a>
            </li>
            <li>
                <a href="#"><i class="fas fa-chart-bar"></i> Informasi Nilai</a>
            </li>
            <li>
                <a href="#"><i class="fas fa-lock"></i> Ganti Password</a>
            </li>
            <li class="logout">
                <a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <div class="overlay" id="overlay"></div>

    <div class="main-content">

        <header class="header">
            <div class="header-left">
                <button class="hamburger-btn" id="hamburger-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-logo">
                    <img src="img/logo.jpg" alt="Logo">
                </div>
                <div class="header-title">
                    Sistem Raport<br>Taman Pendidikan Al-Qur'an
                </div>
            </div>
            <div class="header-right">
                <a href="#" class="user-profile">
                    <div class="user-info">
                        <span>Ramdhan Fajar</span>
                    </div>
                    <div class="icon-wrapper">
                        <i class="fas fa-user"></i>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Dashboard</h1>

            <div class="dashboard-grid">

                <div class="card card-welcome">
                    <h2>Selamat Datang</h2>
                    <p>Selamat datang Ramdhan Fajar di Sistem Raport Taman Pendidikan Al-Qur'an Daarul Hikmah</p>
                </div>

                <div class="card card-small">
                    <h3>Tahun Ajaran</h3>
                    <p>2025/2026</p>
                </div>

                <div class="card card-small kelas">
                    <h3>Kelas</h3>
                    <p>1 MDA</p>
                </div>
                
                <div class="card card-small wali card-wali">
                    <h3>Wali Kelas</h3>
                    <p>Asep budiono,S.Pd</p>
                </div>

                <div class="card card-chart">
                     <h3>Rata-Rata Nilai Anda</h3>
                     <canvas id="myChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </main>

    </div>

    <script>
        // Ambil elemen-elemen yang dibutuhkan
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeBtn = document.getElementById('close-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        // Fungsi untuk membuka sidebar
        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        }

        // Fungsi untuk menutup sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Tambahkan event listener
        hamburgerBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
    </script>

</body>
</html>