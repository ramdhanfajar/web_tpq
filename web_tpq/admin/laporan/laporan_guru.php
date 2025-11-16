<?php
session_start();
require '../../koneksi.php';

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// --- 2. AMBIL DATA GURU UNTUK LAPORAN ---
// Query ini lebih canggih:
// 1. Mengambil data dari 'data_pengajar' dan 'users'
// 2. Menggunakan GROUP_CONCAT untuk menggabungkan semua nama kelas yang diajar
//    oleh satu guru menjadi satu baris (misal: "1 MDA, 2 MDA")
$sql = "SELECT 
            dp.id, 
            dp.nip, 
            dp.nama_lengkap, 
            dp.no_telepon, 
            dp.alamat,
            u.email,
            GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') AS kelas_diampu
        FROM 
            data_pengajar dp
        JOIN 
            users u ON dp.user_id = u.id
        LEFT JOIN -- Pakai LEFT JOIN agar guru yang belum punya kelas tetap tampil
            kelas k ON k.id_pengajar = dp.id
        GROUP BY 
            dp.id, dp.nama_lengkap, dp.nip, dp.no_telepon, dp.alamat, u.email
        ORDER BY 
            dp.nama_lengkap ASC";

$result = $koneksi->query($sql);

$data_guru = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data_guru[] = $row;
    }
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Guru - Admin</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- (CSS SAMA PERSIS DENGAN data_santri.php & data_guru.php) --- */
        :root {
            --warna-hijau: #00a86b;
            --warna-hijau-muda: #e6f7f0;
            --warna-latar: #f4f7f6;
            --warna-teks: #333333;
            --warna-teks-abu: #555;
            --lebar-sidebar: 280px;
        }
        body, html { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background-color: var(--warna-latar); box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
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
        .sidebar-nav li.dropdown { position: relative; }
        .sidebar-nav .dropdown-toggle { display: flex; justify-content: space-between; align-items: center; }
        .sidebar-nav .dropdown-toggle .toggle-icon { font-size: 0.8rem; transition: transform 0.3s ease; }
        .sidebar-nav .submenu { list-style: none; padding-left: 0; margin: 0; background-color: rgba(0, 0, 0, 0.15); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .sidebar-nav .submenu li a { padding-left: 65px; font-size: 0.9rem; font-weight: 400; }
        .sidebar-nav .submenu li a:hover { background-color: rgba(255, 255, 255, 0.05); }
        .sidebar-nav .submenu.active { max-height: 500px; }
        .sidebar-nav .dropdown-toggle.active .toggle-icon { transform: rotate(180deg); }
        .sidebar-nav .submenu li.active-sub > a { background-color: rgba(255, 255, 255, 0.2); font-weight: 600; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-out, visibility 0s 0.3s linear; }
        .overlay.active { opacity: 1; visibility: visible; transition: opacity 0.3s ease-out; }
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
        .dashboard-area { padding: 25px 20px; }
        .dashboard-area h1 { color: var(--warna-teks); font-size: 1.8rem; margin-top: 0; margin-bottom: 20px; }
        .card-table { background-color: white; border-radius: 15px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07); overflow: hidden; }
        
        /* Modifikasi Card Header */
        .card-header {
            display: flex;
            justify-content: flex-end; /* Pindahkan tombol ke kanan */
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .btn-cetak {
            background-color: #0d6efd; /* Biru */
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .btn-cetak:hover {
            background-color: #0b5ed7;
        }
        .btn-cetak i {
            margin-right: 5px;
        }

        .card-body { padding: 0; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; color: var(--warna-teks-abu); white-space: nowrap; }
        .data-table th { background-color: #f9f9f9; color: var(--warna-teks); font-weight: 600; }
        .data-table tbody tr:hover { background-color: var(--warna-hijau-muda); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .no-data { text-align: center; padding: 40px; color: var(--warna-teks-abu); font-style: italic; }
        
        /* --- CSS KHUSUS UNTUK PRINT --- */
        @media print {
            body {
                background-color: white;
                font-family: 'Times New Roman', Times, serif;
                font-size: 12pt;
                color: black;
            }
            
            /* Sembunyikan semua elemen yang tidak perlu dicetak */
            .sidebar, .header, .card-header, .hamburger-btn, .user-profile {
                display: none !important;
            }
            
            /* Atur ulang konten utama agar mengisi seluruh halaman */
            .main-content {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .dashboard-area {
                padding: 0 !important;
            }
            
            /* Tambahkan judul di atas halaman cetak */
            .dashboard-area::before {
                content: "Laporan Data Guru - TPQ Daarul Hikmah";
                display: block;
                text-align: center;
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 20px;
            }
            
            h1 {
                display: none; /* Sembunyikan h1 asli */
            }

            .card-table {
                box-shadow: none !important;
                border: 1px solid #000 !important; /* Beri border pada tabel */
                border-radius: 0 !important;
            }
            
            .data-table, .data-table th, .data-table td {
                border: 1px solid #000 !important; /* Border tabel yang jelas */
                font-size: 11pt !important;
                color: #000 !important;
                white-space: normal; /* Biarkan teks wrap di kertas */
            }
            
            .data-table th {
                background-color: #eee !important; /* Latar abu-abu tipis untuk header */
            }
            
            .data-table a {
                text-decoration: none;
                color: #000;
            }
        }
        
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../../img/logo1.png" alt="Logo">
            <i class="fas fa-arrow-left close-btn" id="close-btn"></i>
        </div>
        <ul class="sidebar-nav">
            <li>
                <a href="../dashboard_admin.php"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a>
            </li>
            
            <li class="nav-item dropdown">
                <a href="#" class="dropdown-toggle">
                    <span><i class="fas fa-database fa-fw"></i> Master Data</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu">
                    <li><a href="../data_master/data_santri.php">Santri</a></li>
                    <li><a href="../data_master/data_pengajar.php">Guru</a></li>
                    <li><a href="../data_master/data_kelas.php">Kelas</a></li>
                    <li><a href="#">Tahun Ajaran</a></li>
                </ul>
            </li>
            
            <li>
                <a href="#"><i class="fas fa-chart-bar fa-fw"></i> Pengolahan Nilai</a>
            </li>

            <li class="nav-item dropdown active">
                <a href="#" class="dropdown-toggle active">
                    <span><i class="fas fa-file-alt fa-fw"></i> Laporan</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu active">
                    <li class="active-sub"><a href="laporan_guru.php">Laporan Daftar Guru</a></li>
                    <li><a href="#">Laporan Daftar Santri</a></li>
                    <li><a href="#">Laporan Daftar Nilai</a></li>
                </ul>
            </li>
            
            <li>
                <a href="#"><i class="fas fa-lock fa-fw"></i> Ganti Password</a>
            </li>
            <li class="logout">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
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
                    <img src="../../img/logo1.png" alt="Logo">
                </div>
                <div class="header-title">
                    Sistem Raport<br>Taman Pendidikan Al-Qur'an
                </div>
            </div>
            <div class="header-right">
                <a href="#" class="user-profile">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                    </div>
                    <div class="icon-wrapper">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Laporan Data Guru</h1>

            <div class="card-table">
                <div class="card-header">
                    <a href="javascript:window.print()" class="btn-cetak">
                        <i class="fas fa-print"></i> Cetak Laporan
                    </a>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIP</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>No. Telepon</th>
                                <th>Kelas Diampu</th>
                                <th>Alamat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_guru)): ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        Belum ada data guru.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($data_guru as $guru): ?>
                                <tr>
                                    <td><?php echo $no++; ?>.</td>
                                    <td><?php echo htmlspecialchars($guru['nip'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($guru['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($guru['email']); ?></td>
                                    <td><?php echo htmlspecialchars($guru['no_telepon'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($guru['kelas_diampu'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($guru['alamat'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeBtn = document.getElementById('close-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
        hamburgerBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                let submenu = this.nextElementSibling;
                let isAlreadyActive = this.classList.contains('active');
                document.querySelectorAll('.submenu.active').forEach(s => s.classList.remove('active'));
                document.querySelectorAll('.dropdown-toggle.active').forEach(t => t.classList.remove('active'));
                if (!isAlreadyActive) {
                    this.classList.add('active');
                    submenu.classList.add('active');
                }
            });
        });
    </script>

</body>
</html>