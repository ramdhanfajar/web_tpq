<?php
session_start();
require '../../koneksi.php';

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// --- 2. AMBIL DATA KELAS UNTUK TABEL ---
// Query ini menggabungkan 3 tabel:
// 1. 'kelas' (untuk nama kelas & tahun)
// 2. 'data_pengajar' (untuk mendapatkan nama Wali Kelas)
// 3. 'data_santri' (untuk MENGHITUNG jumlah santri)
$sql = "SELECT 
            k.id, 
            k.nama_kelas, 
            k.tahun_ajaran, 
            dp.nama_lengkap AS nama_wali_kelas,
            (SELECT COUNT(id) FROM data_santri ds WHERE ds.id_kelas = k.id) AS jumlah_santri
        FROM kelas k
        LEFT JOIN data_pengajar dp ON k.id_pengajar = dp.id
        ORDER BY k.nama_kelas ASC";

$result = $koneksi->query($sql);

$data_kelas = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data_kelas[] = $row;
    }
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Kelas - Admin</title>
    
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
        .card-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #f0f0f0; }
        .btn-tambah { background-color: var(--warna-hijau); color: white; text-decoration: none; padding: 10px 15px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background-color 0.3s; }
        .btn-tambah:hover { background-color: #008a5a; }
        .btn-tambah i { margin-right: 5px; }
        .search-bar { position: relative; }
        .search-bar input { padding: 10px 15px 10px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; font-family: 'Poppins', sans-serif; }
        .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .card-body { padding: 0; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; color: var(--warna-teks-abu); white-space: nowrap; }
        .data-table th { background-color: #f9f9f9; color: var(--warna-teks); font-weight: 600; }
        .data-table tbody tr:hover { background-color: var(--warna-hijau-muda); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .btn-aksi { text-decoration: none; padding: 5px 10px; border-radius: 5px; color: white; font-weight: 500; font-size: 0.8rem; margin-right: 5px; }
        .btn-edit { background-color: #0d6efd; }
        .btn-hapus { background-color: #dc3545; }
        .no-data { text-align: center; padding: 40px; color: var(--warna-teks-abu); font-style: italic; }
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
            
            <li class="nav-item dropdown active">
                <a href="#" class="dropdown-toggle active">
                    <span><i class="fas fa-database fa-fw"></i> Master Data</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu active">
                    <li><a href="data_santri.php">Santri</a></li>
                    <li><a href="data_pengajar.php">Guru</a></li>
                    <li class="active-sub"><a href="data_kelas.php">Kelas</a></li>
                    <li><a href="tahun_ajar.php">Tahun Ajaran</a></li>
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
                    <li><a href="../laporan/laporan_guru.php">Laporan Daftar Guru</a></li>
                    <li><a href="../laporan/">Laporan Daftar Santri</a></li>
                    <li><a href="../laporan/">Laporan Daftar Nilai</a></li>
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
            <h1>Data Master Kelas</h1>

            <div class="card-table">
                <div class="card-header">
                    <a href="tambah_kelas.php" class="btn-tambah">
                        <i class="fas fa-plus"></i> Tambah Kelas
                    </a>
                    <div class="search-bar">
                        <input type="text" placeholder="Cari kelas...">
                    </div>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Kelas</th>
                                <th>Wali Kelas</th>
                                <th>Tahun Ajaran</th>
                                <th>Jumlah Santri</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_kelas)): ?>
                                <tr>
                                    <td colspan="6" class="no-data">
                                        Belum ada data kelas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($data_kelas as $kelas): ?>
                                <tr>
                                    <td><?php echo $no++; ?>.</td>
                                    <td><?php echo htmlspecialchars($kelas['nama_kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['nama_wali_kelas'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['tahun_ajaran']); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['jumlah_santri']); ?></td>
                                    <td>
                                        <a href="edit_kelas.php?id=<?php echo $kelas['id']; ?>" class="btn-aksi btn-edit"><i class="fas fa-pencil-alt"></i> Edit</a>
                                        <a href="hapus_kelas.php?id=<?php echo $kelas['id']; ?>" class="btn-aksi btn-hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus kelas ini?');"><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </td>
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