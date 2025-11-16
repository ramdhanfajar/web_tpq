<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya pengajar ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pengajar') {
    header("Location: ../login.php");
    exit();
}

// --- 2. AMBIL DATA DINAMIS UNTUK KARTU & TABEL ---
$user_id_login = $_SESSION['user_id'];
$nama_pengajar = "Pengajar";
$id_pengajar_db = null;
$file_foto_pengajar = null; // Variabel untuk foto

// Query untuk mengambil data profil pengajar (ID, Nama, & Foto)
$sql_profil = "SELECT 
                    dp.id,
                    dp.nama_lengkap AS nama_pengajar,
                    dp.foto 
                FROM data_pengajar dp
                JOIN users u ON dp.user_id = u.id
                WHERE u.id = ?";
                
$stmt_profil = $koneksi->prepare($sql_profil);
$stmt_profil->bind_param("i", $user_id_login);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();

if ($result_profil->num_rows > 0) {
    $data_profil = $result_profil->fetch_assoc();
    $id_pengajar_db = $data_profil['id'];
    $nama_pengajar = $data_profil['nama_pengajar'] ?: $nama_pengajar;
    $file_foto_pengajar = $data_profil['foto']; // Ambil nama file foto
    $_SESSION['nama_lengkap'] = $nama_pengajar;
}
$stmt_profil->close();

// Query untuk mengambil "Mata Pelajaran" dan "Kelas" yang diampu
$data_mapel_diampu = [];
if ($id_pengajar_db) { 
    $sql_mapel_diampu = "SELECT 
                            m.nama_materi, 
                            k.nama_kelas
                        FROM nilai_raport nr
                        JOIN materi m ON nr.id_materi = m.id
                        JOIN data_santri ds ON nr.id_santri = ds.id
                        JOIN kelas k ON ds.id_kelas = k.id
                        WHERE nr.id_pengajar = ? 
                        GROUP BY m.nama_materi, k.nama_kelas
                        ORDER BY m.nama_materi, k.nama_kelas";

    $stmt_mapel_diampu = $koneksi->prepare($sql_mapel_diampu);
    $stmt_mapel_diampu->bind_param("i", $id_pengajar_db);
    $stmt_mapel_diampu->execute();
    $result_mapel_diampu = $stmt_mapel_diampu->get_result();

    if ($result_mapel_diampu->num_rows > 0) {
        while ($row = $result_mapel_diampu->fetch_assoc()) {
            $data_mapel_diampu[] = $row;
        }
    }
    $stmt_mapel_diampu->close();
}
$koneksi->close(); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengajar - Sistem Raport TPQ</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        /* Selalu 'fixed' (melayang) dan tersembunyi di awal */
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100%; 
            width: var(--lebar-sidebar); 
            background-color: var(--warna-hijau); 
            color: white; 
            z-index: 1000; 
            transform: translateX(-100%); 
            transition: transform 0.3s ease-out; 
            display: flex; 
            flex-direction: column; 
        }
        .sidebar.active { 
            transform: translateX(0); 
        }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .sidebar-header .close-btn { font-size: 1.5rem; cursor: pointer; }
        .sidebar-nav { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 15px 25px; color: white; text-decoration: none; font-size: 1rem; font-weight: 500; transition: background-color 0.2s; }
        .sidebar-nav li a:hover { background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-nav li.active a { background-color: var(--warna-latar); color: var(--warna-hijau); border-left: 5px solid white; padding-left: 20px; }
        .sidebar-nav li.active i { color: var(--warna-hijau); }
        .sidebar-nav li a i { width: 30px; font-size: 1.2rem; margin-right: 15px; }
        .sidebar-nav li.logout { margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Overlay selalu aktif di semua layar */
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-out, visibility 0s 0.3s linear; }
        .overlay.active { opacity: 1; visibility: visible; transition: opacity 0.3s ease-out; }

        /* --- Header --- */
        .main-content { 
            width: 100%; 
            min-height: 100vh;
        }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background-color: var(--warna-hijau); color: white; }
        .header-left { display: flex; align-items: center; }
        /* Tombol hamburger selalu terlihat */
        .hamburger-btn { font-size: 1.5rem; background: none; border: none; color: white; cursor: pointer; margin-right: 15px; }
        .header-logo img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        .header-title { font-size: 0.9rem; font-weight: 500; line-height: 1.3; }
        .header-right .user-profile { display: flex; align-items: center; text-align: right; text-decoration: none; color: white; }
        .user-profile .user-info { display: flex; flex-direction: column; }
        .user-profile span { font-size: 0.8rem; font-weight: 500; }
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
            overflow: hidden; 
        }
        .icon-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- Area Dashboard --- */
        .dashboard-area {
            padding: 25px 20px;
        }
        .dashboard-area h1 {
            color: var(--warna-teks);
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr; /* Selalu 1 kolom */
            gap: 20px;
        }
        
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
        }
        .card-welcome {
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 500;
        }
        .card-welcome h2 { margin: 0 0 5px 0; font-size: 1.2rem; }
        .card-welcome p { margin: 0; font-size: 0.9rem; line-height: 1.5; }

        .table-card {
            padding: 20px;
            background-color: white;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            overflow-x: auto; /* Penting untuk scroll di HP */
        }
        .table-card h3 {
            color: #d90000;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 15px;
            padding: 0;
            text-align: left;
        }
        .table-card table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            border: 2px solid #777;
        }
        .table-card th, .table-card td {
            padding: 10px 15px;
            text-align: left;
            border: 1px solid #aaa;
            white-space: nowrap; /* Agar tidak turun baris */
        }
        .table-card th {
            background-color: #EFEFEF;
            color: #333;
            font-weight: 600;
            text-align: center;
        }
        .table-card td {
            color: var(--warna-teks-abu);
            background-color: white;
        }
        .table-card .no-data {
            text-align: center;
            padding: 20px;
            color: var(--warna-teks-abu);
            font-style: italic;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-welcome, .table-card {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* CSS Responsif untuk Header di HP Kecil */
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
            <li class="active"><a href="dashboard_pengajar.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="biodata_pengajar.php"><i class="fas fa-user"></i> Biodata</a></li>
            <li><a href="pengolahan_nilai.php"><i class="fas fa-edit"></i> Pengolahan Nilai</a></li>
            <li><a href="wali_kelas.php"><i class="fas fa-users-cog"></i> Wali Kelas</a></li>
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
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Dashboard</h1>

            <div class="dashboard-grid">

                <div class="card card-welcome">
                    <h2>Selamat Datang</h2>
                    <p>Selamat datang <?php echo htmlspecialchars($nama_pengajar); ?> di Sistem Raport Taman Pendidikan Al-Qur'an Daarul Hikmah</p>
                </div>
                
                <div class="card table-card">
                    <h3>Mata Pelajaran Yang Diampu</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_mapel_diampu)): ?>
                                <?php $no = 1; foreach ($data_mapel_diampu as $mapel): ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no++; ?>.</td>
                                    <td><?php echo htmlspecialchars($mapel['nama_materi']); ?></td>
                                    <td><?php echo htmlspecialchars($mapel['nama_kelas']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="no-data">
                                        Anda belum mengampu mata pelajaran apapun.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php 
                            $baris_tersisa = 4 - count($data_mapel_diampu);
                            if ($baris_tersisa < 0) $baris_tersisa = 0;
                            for ($i = 0; $i < $baris_tersisa; $i++): 
                            ?>
                                <tr>
                                    <td style="text-align: center;"><?php if (!empty($data_mapel_diampu)) echo ($no + $i) . '.'; else echo '&nbsp;'; ?></td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <script>
        // --- 1. Script Sidebar ---
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
        
        // --- 2. JS Pop-up Selamat Datang ---
        document.addEventListener('DOMContentLoaded', function() {
            const namaPengajar = "<?php echo htmlspecialchars($nama_pengajar, ENT_QUOTES); ?>";
            
            if (!sessionStorage.getItem('dashboardWelcomed')) {
                Swal.fire({
                    title: 'Selamat Datang!',
                    text: `Halo, ${namaPengajar}. Selamat bertugas.`,
                    icon: 'success',
                    timer: 2500,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
                sessionStorage.setItem('dashboardWelcomed', 'true');
            }
        });
        
        // --- 3. JS Pop-up Konfirmasi Logout ---
        const btnLogout = document.getElementById('btn-logout');
        
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
    </script>

</body>
</html>