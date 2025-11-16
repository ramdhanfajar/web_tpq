<?php
session_start();
require '../koneksi.php';

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Jika bukan admin, lempar kembali ke halaman login
    header("Location: ../login.php");
    exit();
}

// --- 2. AMBIL DATA DINAMIS UNTUK KARTU-KARTU ---
$user_id_login = $_SESSION['user_id'];
$email_admin = $_SESSION['email']; // Mengambil email admin dari session

// Card: Jumlah Santri
$result_santri = $koneksi->query("SELECT COUNT(id) AS total FROM data_santri");
$jumlah_santri = $result_santri->fetch_assoc()['total'];

// Card: Jumlah Guru (Pengajar)
$result_guru = $koneksi->query("SELECT COUNT(id) AS total FROM data_pengajar");
$jumlah_guru = $result_guru->fetch_assoc()['total'];

// Card: Jumlah Kelas
$result_kelas = $koneksi->query("SELECT COUNT(id) AS total FROM kelas");
$jumlah_kelas = $result_kelas->fetch_assoc()['total'];

// Card: Tahun Ajaran (Kita hardcode sesuai gambar, karena di DB tidak ada status 'aktif')
$tahun_ajaran_aktif = '2025/2026'; 

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Raport TPQ</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --warna-hijau: #00a86b;
            --warna-hijau-muda: #e6f7f0;
            --warna-latar: #f4f7f6;
            --warna-teks: #333333;
            --warna-teks-abu: #555;
            --lebar-sidebar: 280px;
        }

        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--warna-latar);
            box-sizing: border-box;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }

        /* --- Sidebar & Overlay --- */
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; width: var(--lebar-sidebar); background-color: var(--warna-hijau); color: white; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease-out; display: flex; flex-direction: column; }
        .sidebar.active { transform: translateX(0); }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .sidebar-header .close-btn { font-size: 1.5rem; cursor: pointer; }
        .sidebar-nav { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 15px 25px; color: white; text-decoration: none; font-size: 1rem; font-weight: 500; transition: background-color 0.2s; }
        .sidebar-nav li a:hover { background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-nav li.active > a { background-color: var(--warna-latar); color: var(--warna-hijau); border-left: 5px solid white; padding-left: 20px; }
        .sidebar-nav li.active > a i { color: var(--warna-hijau); }
        .sidebar-nav li a i.fa-fw { width: 30px; font-size: 1.2rem; margin-right: 15px; }
        .sidebar-nav li.logout { margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* --- Style untuk Dropdown Sidebar --- */
        .sidebar-nav li.dropdown { position: relative; }
        .sidebar-nav .dropdown-toggle { display: flex; justify-content: space-between; align-items: center; }
        .sidebar-nav .dropdown-toggle .toggle-icon { font-size: 0.8rem; transition: transform 0.3s ease; }
        .sidebar-nav .submenu {
            list-style: none;
            padding-left: 0;
            margin: 0;
            background-color: rgba(0, 0, 0, 0.15);
            max-height: 0; /* Sembunyi by default */
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .sidebar-nav .submenu li a {
            padding-left: 65px; /* Inden untuk submenu */
            font-size: 0.9rem;
            font-weight: 400;
        }
        .sidebar-nav .submenu li a:hover { background-color: rgba(255, 255, 255, 0.05); }
        /* Kelas 'active' ditambahkan oleh JS */
        .sidebar-nav .submenu.active { max-height: 500px; /* Nilai besar agar bisa expand */ }
        .sidebar-nav .dropdown-toggle.active .toggle-icon { transform: rotate(180deg); }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-out, visibility 0s 0.3s linear; }
        .overlay.active { opacity: 1; visibility: visible; transition: opacity 0.3s ease-out; }

        /* --- Header --- */
        .main-content { width: 100%; min-height: 100vh; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background-color: var(--warna-hijau); color: white; }
        .header-left { display: flex; align-items: center; }
        .hamburger-btn { font-size: 1.5rem; background: none; border: none; color: white; cursor: pointer; margin-right: 15px; }
        .header-logo img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        .header-title { font-size: 0.9rem; font-weight: 500; line-height: 1.3; }
        .header-right .user-profile { display: flex; align-items: center; text-align: right; text-decoration: none; color: white; }
        .user-profile .user-info { display: flex; flex-direction: column; }
        .user-profile span { font-size: 0.8rem; font-weight: 500; }
        .user-profile .icon-wrapper { background-color: white; color: var(--warna-hijau); border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin-left: 10px; font-size: 1.1rem; }

        /* --- Area Dashboard --- */
        .dashboard-area { padding: 25px 20px; }
        .dashboard-area h1 { color: var(--warna-teks); font-size: 1.8rem; margin-top: 0; margin-bottom: 20px; }

        .card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
        }

        /* Kartu Welcome (Penuh) */
        .card-welcome {
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 500;
            margin-bottom: 20px;
        }
        .card-welcome h2 { margin: 0 0 5px 0; font-size: 1.2rem; }
        .card-welcome p { margin: 0; font-size: 0.9rem; line-height: 1.5; }
        
        /* Grid 2x2 untuk Kartu Statistik */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Selalu 2 kolom */
            gap: 20px;
        }
        
        .card-small {
            text-align: center;
            padding: 25px 15px;
        }
        .card-small h3 {
            margin: 0 0 12px 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--warna-hijau);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px; /* Jarak ikon & teks */
        }
        .card-small p {
            margin: 0;
            font-size: 2.2rem; /* Font angka lebih besar */
            font-weight: 700;
            color: var(--warna-teks-abu);
        }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../img/logo1.png" alt="Logo">
            <i class="fas fa-arrow-left close-btn" id="close-btn"></i>
        </div>
        <ul class="sidebar-nav">
            <li class="active">
                <a href="#"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a>
            </li>
            
            <li class="nav-item dropdown">
                <a href="#" class="dropdown-toggle">
                    <span><i class="fas fa-database fa-fw"></i> Master Data</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu">
                    <li><a href="data_master/data_santri.php">Santri</a></li>
                    <li><a href="data_master/data_pengajar.php">Guru</a></li>
                    <li><a href="data_master/data_kelas.php">Kelas</a></li>
                    <li><a href="data_master/tahun_ajaran.php">Tahun Ajaran</a></li>
                </ul>
            </li>
            
            <li>
                <a href="#"><i class="fas fa-chart-bar fa-fw"></i> Pengolahan Nilai</a>
            </li>

            <li class="nav-item dropdown">
                <a href="#" class="dropdown-toggle">
                    <span><i class="fas fa-file-alt fa-fw"></i> Laporan</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu">
                    <li><a href="laporan/laporan_guru.php">Laporan Daftar Guru</a></li>
                    <li><a href="#">Laporan Daftar Santri</a></li>
                    <li><a href="#">Laporan Daftar Nilai</a></li>
                </ul>
            </li>
            
            <li>
                <a href="#"><i class="fas fa-lock fa-fw"></i> Ganti Password</a>
            </li>
            <li class="logout">
                <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
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
                    <img src="../img/logo1.png" alt="Logo">
                </div>
                <div class="header-title">
                    Sistem Raport<br>Taman Pendidikan Al-Qur'an
                </div>
            </div>
            <div class="header-right">
                <a href="#" class="user-profile">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($email_admin); ?></span>
                    </div>
                    <div class="icon-wrapper">
                        <i class="fas fa-user-shield"></i> </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Dashboard</h1>

            <div class="card card-welcome">
                <h2>Selamat Datang</h2>
                <p>Selamat datang <?php echo htmlspecialchars($email_admin); ?> di Sistem Raport Taman Pendidikan Al-Qur'an Daarul Hikmah</p>
            </div>

            <div class="dashboard-grid">

                <div class="card card-small">
                    <h3><i class="fas fa-calendar-alt"></i> Tahun Ajaran</h3>
                    <p><?php echo htmlspecialchars($tahun_ajaran_aktif); ?></p>
                </div>

                <div class="card card-small">
                    <h3><i class="fas fa-school"></i> Kelas</h3>
                    <p><?php echo htmlspecialchars($jumlah_kelas); ?></p>
                </div>
                
                <div class="card card-small">
                    <h3><i class="fas fa-user-graduate"></i> Santri</h3>
                    <p><?php echo htmlspecialchars($jumlah_santri); ?></p>
                </div>

                <div class="card card-small">
                    <h3><i class="fas fa-user-tie"></i> Guru</h3>
                    <p><?php echo htmlspecialchars($jumlah_guru); ?></p>
                </div>

            </div>
        </main>
    </div>

    <script>
        // --- JavaScript untuk Buka/Tutup Sidebar ---
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeBtn = document.getElementById('close-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
        hamburgerBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        // --- JavaScript untuk Sidebar Dropdowns ---
        document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault(); // Mencegah link berpindah halaman
                
                // Ambil submenu yg berhubungan
                let submenu = this.nextElementSibling;
                
                // Toggle kelas 'active' pada tombol
                this.classList.toggle('active');
                
                // Toggle kelas 'active' pada submenu
                submenu.classList.toggle('active');
            });
        });
    </script>

</body>
</html>