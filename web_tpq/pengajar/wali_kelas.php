<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN & AMBIL DATA PENGGUNA ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pengajar') {
    header("Location: ../login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$id_pengajar_db = null;
$nama_pengajar = "Pengajar";
$file_foto_pengajar = null; // Variabel untuk foto

// Query untuk mengambil ID, Nama, dan Foto
$stmt_pengajar = $koneksi->prepare("SELECT id, nama_lengkap, foto FROM data_pengajar WHERE user_id = ?");
$stmt_pengajar->bind_param("i", $user_id_login);
$stmt_pengajar->execute();
$result_pengajar = $stmt_pengajar->get_result();
if ($result_pengajar->num_rows > 0) {
    $data_pengajar = $result_pengajar->fetch_assoc();
    $id_pengajar_db = $data_pengajar['id'];
    $nama_pengajar = $data_pengajar['nama_lengkap'];
    $file_foto_pengajar = $data_pengajar['foto']; // Ambil foto
    $_SESSION['nama_lengkap'] = $nama_pengajar; // Simpan nama di session
}
$stmt_pengajar->close();
if ($id_pengajar_db === null) {
    die("Error: Data pengajar tidak ditemukan.");
}

// --- 2. LOGIKA "GUARD": CEK APAKAH DIA WALI KELAS ---
$kelas_data = null;
$stmt_kelas_check = $koneksi->prepare("SELECT id, nama_kelas, tahun_ajaran FROM kelas WHERE id_pengajar = ? LIMIT 1");
$stmt_kelas_check->bind_param("i", $id_pengajar_db);
$stmt_kelas_check->execute();
$result_kelas_check = $stmt_kelas_check->get_result();
if ($result_kelas_check->num_rows > 0) {
    // YA, DIA WALI KELAS
    $kelas_data = $result_kelas_check->fetch_assoc();
}
$stmt_kelas_check->close();


// --- 3. JIKA DIA WALI KELAS, AMBIL SEMUA DATA YANG DIPERLUKAN ---
if ($kelas_data) {
    $id_kelas_wali = $kelas_data['id'];
    
    // Query 1: Total Santri
    $stmt_count_santri = $koneksi->prepare("SELECT COUNT(id) AS total FROM data_santri WHERE id_kelas = ?");
    $stmt_count_santri->bind_param("i", $id_kelas_wali);
    $stmt_count_santri->execute();
    $total_santri = $stmt_count_santri->get_result()->fetch_assoc()['total'];
    $stmt_count_santri->close();

    // Query 2: Total Mata Pelajaran
    $total_materi = $koneksi->query("SELECT COUNT(id) AS total FROM materi")->fetch_assoc()['total'];

    // Query 3: Ambil daftar Materi (untuk header tabel)
    $list_materi = [];
    $result_materi_list = $koneksi->query("SELECT id, nama_materi FROM materi ORDER BY id ASC");
    while($row_materi = $result_materi_list->fetch_assoc()) {
        $list_materi[] = $row_materi;
    }

    // Query 4: Ambil data tabel utama (Pivot Nilai)
    $data_tabel = [];
    $sql_santri_nilai = "
        SELECT 
            ds.id, 
            ds.nis, 
            ds.nama_lengkap,
            AVG(CAST(nr.nilai AS DECIMAL(5,2))) AS rata_rata,
            GROUP_CONCAT(CONCAT(nr.id_materi, ':', nr.nilai) SEPARATOR ',') AS nilai_map
        FROM data_santri ds
        LEFT JOIN nilai_raport nr ON ds.id = nr.id_santri
        WHERE ds.id_kelas = ?
        GROUP BY ds.id, ds.nis, ds.nama_lengkap
        ORDER BY ds.nama_lengkap ASC
    ";
    $stmt_santri_nilai = $koneksi->prepare($sql_santri_nilai);
    $stmt_santri_nilai->bind_param("i", $id_kelas_wali);
    $stmt_santri_nilai->execute();
    $result_santri_nilai = $stmt_santri_nilai->get_result();
    while($row_santri = $result_santri_nilai->fetch_assoc()) {
        $data_tabel[] = $row_santri;
    }
    $stmt_santri_nilai->close();

    // Query 5: Ambil rata-rata per mapel (untuk footer)
    $rata_rata_kelas_map = [];
    $sql_rata_kelas = "
        SELECT 
            nr.id_materi,
            AVG(CAST(nr.nilai AS DECIMAL(5,2))) AS rata_rata
        FROM nilai_raport nr
        JOIN data_santri ds ON nr.id_santri = ds.id
        WHERE ds.id_kelas = ?
        GROUP BY nr.id_materi
    ";
    $stmt_rata_kelas = $koneksi->prepare($sql_rata_kelas);
    $stmt_rata_kelas->bind_param("i", $id_kelas_wali);
    $stmt_rata_kelas->execute();
    $result_rata_kelas = $stmt_rata_kelas->get_result();
    while($row_rata = $result_rata_kelas->fetch_assoc()) {
        $rata_rata_kelas_map[$row_rata['id_materi']] = $row_rata['rata_rata'];
    }
    $stmt_rata_kelas->close();
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wali Kelas - <?php echo htmlspecialchars($nama_pengajar); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* === CSS TEMPLATE GABUNGAN (NON-RESPONSIVE) === */
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

        /* --- Sidebar & Overlay --- */
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; width: var(--lebar-sidebar); background-color: var(--warna-hijau); color: white; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease-out; display: flex; flex-direction: column; }
        .sidebar.active { transform: translateX(0); }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .sidebar-header .close-btn { font-size: 1.5rem; cursor: pointer; }
        .sidebar-nav { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 15px 25px; color: white; text-decoration: none; font-size: 1rem; font-weight: 500; transition: background-color 0.2s; }
        .sidebar-nav li a:hover { background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-nav li.active a { background-color: var(--warna-latar); color: var(--warna-hijau); border-left: 5px solid white; padding-left: 20px; }
        .sidebar-nav li.active i { color: var(--warna-hijau); }
        .sidebar-nav li a i { width: 30px; font-size: 1.2rem; margin-right: 15px; }
        .sidebar-nav li.logout { margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.1); }
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
        .user-profile .icon-wrapper { background-color: white; color: var(--warna-hijau); border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin-left: 10px; font-size: 1.1rem; overflow: hidden; }
        .icon-wrapper img { width: 100%; height: 100%; object-fit: cover; }

        /* --- Area Konten --- */
        .dashboard-area { padding: 25px 20px; }
        .dashboard-area h1 { color: var(--warna-teks); font-size: 1.8rem; margin-top: 0; margin-bottom: 20px; }
        
        /* --- CSS Baru untuk Halaman Ini --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr; /* 1 kolom di HP */
            gap: 20px;
        }
        
        /* Layout di Desktop (lebih dari 768px) */
        @media (min-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr 1fr; /* 2 kolom */
            }
            .card-main {
                grid-column: 1 / -1; /* Kartu utama ambil 1 baris penuh */
            }
            .card-table {
                grid-column: 1 / -1; /* Tabel ambil 1 baris penuh */
            }
        }

        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            padding: 20px;
        }

        /* Kartu Utama (Info Kelas) */
        .card-main h3 {
            font-size: 1rem;
            color: #fd7e14; /* Oranye */
            margin: 0;
            font-weight: 600;
        }
        .card-main h2 {
            font-size: 1.8rem;
            color: var(--warna-teks);
            margin: 5px 0;
        }
        .card-main p {
            font-size: 1rem;
            color: var(--warna-teks-abu);
            margin: 0;
            font-weight: 500;
        }

        /* Kartu Statistik Kecil */
        .card-small {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-small .info h3 {
            font-size: 1rem;
            color: var(--warna-teks-abu);
            margin: 0 0 5px 0;
            font-weight: 500;
        }
        .card-small .info p {
            font-size: 2.2rem;
            color: var(--warna-teks);
            margin: 0;
            font-weight: 700;
        }
        .card-small .icon {
            font-size: 2.5rem;
            color: var(--warna-hijau);
            opacity: 0.7;
        }
        
        /* Kartu Tabel Nilai */
        .card-table {
            padding: 0;
            overflow: hidden;
        }
        .card-table h3 {
            font-size: 1.1rem;
            color: var(--warna-teks);
            margin: 20px 20px 0 20px;
        }
        .card-body {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }
        .data-table th {
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 600;
        }
        .data-table td {
            color: var(--warna-teks-abu);
        }
        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .data-table tfoot td {
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 700;
            border-bottom: none;
        }
        .data-table tfoot td:first-child {
            text-align: right;
        }
        
        /* Kartu Pesan Error (Bukan Wali Kelas) */
        .card-error {
            text-align: center;
            padding: 40px;
        }
        .card-error i {
            font-size: 4rem;
            color: #dc3545; /* Merah */
            margin-bottom: 20px;
        }
        .card-error h2 {
            font-size: 1.3rem;
            color: var(--warna-teks);
            margin-bottom: 10px;
        }
        .card-error p {
            font-size: 1rem;
            color: var(--warna-teks-abu);
            margin: 0;
        }

        /* CSS Responsif Header di HP Kecil */
        @media (max-width: 480px) {
            .header {
                padding: 12px 15px;
            }
            .header-logo img {
                width: 30px;
                height: 30px;
            }
            .header-title {
                font-size: 0.8rem;
            }
            .user-profile .user-info {
                display: none; 
            }
            .user-profile .icon-wrapper {
                margin-left: 0;
            }
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
            <li><a href="dashboard_pengajar.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="biodata_pengajar.php"><i class="fas fa-user"></i> Biodata</a></li>
            <li><a href="pengolahan_nilai.php"><i class="fas fa-edit"></i> Pengolahan Nilai</a></li>
            <li class="active"><a href="wali_kelas.php"><i class="fas fa-users-cog"></i> Wali Kelas</a></li>
            <li><a href="ganti_pw.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
            <li class="logout"><a href="../logout.php" id="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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
                <a href="biodata_pengajar.php" class="user-profile">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($nama_pengajar); ?></span>
                    </div>
                    <div class="icon-wrapper">
                        <?php if (!empty($file_foto_pengajar)): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($file_foto_pengajar); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fas fa-user-tie"></i>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Wali Kelas</h1>

            <div class="dashboard-grid">
            
            <?php if (is_null($kelas_data)): ?>
                
                <div class="card card-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Akses Ditolak</h2>
                    <p>Halaman ini hanya untuk Wali Kelas. Saat ini Anda tidak terdaftar sebagai Wali Kelas.</p>
                </div>

            <?php else: ?>
                
                <div class="card card-main">
                    <h3>Kelas</h3>
                    <h2><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></h2>
                    <p>Wali Kelas: <?php echo htmlspecialchars($nama_pengajar); ?></p>
                    <p>Tahun Ajaran: <?php echo htmlspecialchars($kelas_data['tahun_ajaran']); ?></p>
                </div>

                <div class="card card-small">
                    <div class="info">
                        <h3>Total Santri</h3>
                        <p><?php echo $total_santri; ?></p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <div class="card card-small">
                    <div class="info">
                        <h3>Total Mata Pelajaran</h3>
                        <p><?php echo $total_materi; ?></p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>

                <div class="card card-table">
                    <h3>Daftar Santri & Nilai Kelas <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></h3>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>NIS</th>
                                    <th>Nama Santri</th>
                                    <?php foreach ($list_materi as $materi): ?>
                                        <th><?php echo htmlspecialchars($materi['nama_materi']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_tabel as $santri): ?>
                                    <tr>
                                        <td><?php echo $no++; ?>.</td>
                                        <td><?php echo htmlspecialchars($santri['nis']); ?></td>
                                        <td><?php echo htmlspecialchars($santri['nama_lengkap']); ?></td>
                                        
                                        <?php
                                        $nilai_santri_map = [];
                                        if (!empty($santri['nilai_map'])) {
                                            $pairs = explode(',', $santri['nilai_map']);
                                            foreach ($pairs as $pair) {
                                                list($id_materi_map, $nilai_map) = explode(':', $pair);
                                                $nilai_santri_map[$id_materi_map] = $nilai_map;
                                            }
                                        }
                                        
                                        foreach ($list_materi as $materi):
                                            $id_materi = $materi['id'];
                                            $nilai_tampil = $nilai_santri_map[$id_materi] ?? '-';
                                        ?>
                                            <td><?php echo htmlspecialchars($nilai_tampil); ?></td>
                                        <?php endforeach; ?>
                                        
                                        <td><strong><?php echo number_format($santri['rata_rata'], 1); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3"><strong>Rata-rata Kelas</strong></td>
                                    <?php
                                    $total_rata_rata_kelas = 0;
                                    $jumlah_mapel_rata_rata = 0;
                                    
                                    foreach ($list_materi as $materi):
                                        $id_materi = $materi['id'];
                                        $rata_mapel = $rata_rata_kelas_map[$id_materi] ?? 0;
                                        
                                        if ($rata_mapel > 0) {
                                            $total_rata_rata_kelas += $rata_mapel;
                                            $jumlah_mapel_rata_rata++;
                                        }
                                    ?>
                                        <td><strong><?php echo ($rata_mapel > 0) ? number_format($rata_mapel, 1) : '-'; ?></strong></td>
                                    <?php endforeach; ?>
                                    
                                    <?php
                                    $rata_rata_total_akhir = ($jumlah_mapel_rata_rata > 0) ? ($total_rata_rata_kelas / $jumlah_mapel_rata_rata) : 0;
                                    ?>
                                    <td><strong><?php echo number_format($rata_rata_total_akhir, 1); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

            </div> </main>
    </div>

    <script>
        // --- 1. Script Sidebar ---
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeBtn = document.getElementById('close-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
        hamburgerBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        
        // --- 2. JS Pop-up Konfirmasi Logout ---
        const btnLogout = document.getElementById('btn-logout');
        
        if(btnLogout) {
            btnLogout.addEventListener('click', function(e) {
                e.preventDefault(); 
                
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Anda akan keluar dari sesi ini.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#00a86b',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, keluar!',
                    cancelButtonText: 'Tidak'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = btnLogout.href; 
                    }
                });
            });
        }
    </script>

</body>
</html>