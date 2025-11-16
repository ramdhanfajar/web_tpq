<?php
session_start();
require '../../koneksi.php'; // Path sudah benar (naik 2 level)

// --- 1. KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$status_msg = '';

// --- 2. LOGIKA PROSES POST (Tambah, Aktifkan, Hapus) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- AKSI: TAMBAH TAHUN AJARAN ---
    if (isset($_POST['tambah_tahun'])) {
        $tahun_baru = $_POST['tahun_ajaran'];
        $status_baru = $_POST['status'];

        // Jika status 'aktif', non-aktifkan semua yang lain dulu
        if ($status_baru == 'aktif') {
            $koneksi->query("UPDATE tahun_ajaran SET status = 'tidak aktif'");
        }
        
        $stmt_insert = $koneksi->prepare("INSERT INTO tahun_ajaran (tahun_ajaran, status) VALUES (?, ?)");
        $stmt_insert->bind_param("ss", $tahun_baru, $status_baru);
        if ($stmt_insert->execute()) {
            $status_msg = "<div class='alert alert-success'>Tahun ajaran berhasil ditambahkan.</div>";
        } else {
            $status_msg = "<div class='alert alert-danger'>Gagal menambahkan: " . $koneksi->error . "</div>";
        }
        $stmt_insert->close();
    
    // --- AKSI: AKTIFKAN TAHUN AJARAN ---
    } elseif (isset($_POST['aktifkan_tahun'])) {
        $id_aktifkan = $_POST['id_tahun'];
        
        $koneksi->begin_transaction();
        try {
            // 1. Set semua jadi 'tidak aktif'
            $koneksi->query("UPDATE tahun_ajaran SET status = 'tidak aktif'");
            // 2. Set satu yg dipilih jadi 'aktif'
            $stmt_aktif = $koneksi->prepare("UPDATE tahun_ajaran SET status = 'aktif' WHERE id = ?");
            $stmt_aktif->bind_param("i", $id_aktifkan);
            $stmt_aktif->execute();
            $stmt_aktif->close();
            
            $koneksi->commit();
            $status_msg = "<div class='alert alert-success'>Status tahun ajaran berhasil diperbarui.</div>";
        } catch (Exception $e) {
            $koneksi->rollback();
            $status_msg = "<div class='alert alert-danger'>Gagal memperbarui status.</div>";
        }

    // --- AKSI: HAPUS TAHUN AJARAN ---
    } elseif (isset($_POST['hapus_tahun'])) {
        $id_hapus = $_POST['id_tahun'];
        
        $stmt_hapus = $koneksi->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
        $stmt_hapus->bind_param("i", $id_hapus);
        if ($stmt_hapus->execute()) {
            $status_msg = "<div class='alert alert-success'>Tahun ajaran berhasil dihapus.</div>";
        } else {
            $status_msg = "<div class='alert alert-danger'>Gagal menghapus.</div>";
        }
        $stmt_hapus->close();
    }
}

// --- 3. AMBIL DATA TAHUN AJARAN UNTUK TABEL ---
$sql = "SELECT id, tahun_ajaran, status FROM tahun_ajaran ORDER BY tahun_ajaran DESC";
$result = $koneksi->query($sql);
$data_tahun_ajaran = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data_tahun_ajaran[] = $row;
    }
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Tahun Ajaran - Admin</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- (CSS Template: Sama seperti file data_master lainnya) --- */
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
        
        /* --- CSS Khusus Halaman Ini --- */
        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 20px;
        }
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .card-header h3 {
            margin: 0;
            color: var(--warna-hijau);
        }
        .card-body {
            padding: 20px;
        }
        
        /* Form Tambah */
        .form-tambah {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; color: var(--warna-teks-abu); font-size: 0.9rem; margin-bottom: 8px; }
        .form-group input, .form-group select {
            padding: 10px 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
        }
        .btn-tambah {
            background-color: var(--warna-hijau);
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            height: 42px; /* Samakan tinggi */
        }
        .btn-tambah:hover { background-color: #008a5a; }

        /* Tabel Data */
        .card-table { overflow: hidden; }
        .card-body-table { padding: 0; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; color: var(--warna-teks-abu); white-space: nowrap; }
        .data-table th { background-color: #f9f9f9; color: var(--warna-teks); font-weight: 600; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background-color: var(--warna-hijau-muda); }
        .no-data { text-align: center; padding: 40px; font-style: italic; }
        
        /* Status & Tombol Aksi */
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .status-aktif { background-color: var(--warna-hijau-muda); color: var(--warna-hijau); }
        .status-tidak-aktif { background-color: #f8d7da; color: #721c24; }
        
        .btn-aksi {
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            font-size: 0.8rem;
            margin-right: 5px;
            border: none;
            cursor: pointer;
        }
        .btn-aktifkan { background-color: #0d6efd; }
        .btn-hapus { background-color: #dc3545; }
        
        .alert { padding: 15px; border-radius: 8px; font-weight: 500; margin-bottom: 20px; }
        .alert-success { background-color: var(--warna-hijau-muda); color: var(--warna-hijau); }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
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
                    <li><a href="data_kelas.php">Kelas</a></li>
                    <li class="active-sub"><a href="tahun_ajaran.php">Tahun Ajaran</a></li>
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
            <h1>Data Master Tahun Ajaran</h1>

            <?php echo $status_msg; // Tampilkan pesan sukses/gagal ?>

            <div class="card">
                <div class="card-header">
                    <h3>Tambah Tahun Ajaran Baru</h3>
                </div>
                <div class="card-body">
                    <form action="tahun_ajaran.php" method="POST" class="form-tambah">
                        <div class="form-group">
                            <label for="tahun_ajaran">Tahun Ajaran</label>
                            <input type="text" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2025/2026" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="tidak aktif">Tidak Aktif</option>
                                <option value="aktif">Aktif</option>
                            </select>
                        </div>
                        <button type="submit" name="tambah_tahun" class="btn-tambah"><i class="fas fa-plus"></i> Tambah</button>
                    </form>
                </div>
            </div>
            
            <div class="card card-table">
                <div class="card-header">
                    <h3>Daftar Tahun Ajaran</h3>
                </div>
                <div class="card-body-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tahun Ajaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_tahun_ajaran)): ?>
                                <tr>
                                    <td colspan="4" class="no-data">
                                        Belum ada data tahun ajaran.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($data_tahun_ajaran as $ta): ?>
                                <tr>
                                    <td><?php echo $no++; ?>.</td>
                                    <td><?php echo htmlspecialchars($ta['tahun_ajaran']); ?></td>
                                    <td>
                                        <?php if ($ta['status'] == 'aktif'): ?>
                                            <span class="status status-aktif">Aktif</span>
                                        <?php else: ?>
                                            <span class="status status-tidak-aktif">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="tahun_ajaran.php" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="id_tahun" value="<?php echo $ta['id']; ?>">
                                            <?php if ($ta['status'] == 'tidak aktif'): ?>
                                                <button type="submit" name="aktifkan_tahun" class="btn-aksi btn-aktifkan"><i class="fas fa-check"></i> Aktifkan</button>
                                            <?php endif; ?>
                                            <button type="submit" name="hapus_tahun" class="btn-aksi btn-hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus tahun ajaran ini?');"><i class="fas fa-trash-alt"></i> Hapus</button>
                                        </form>
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