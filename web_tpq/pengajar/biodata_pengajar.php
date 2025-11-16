<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN & AMBIL DATA PENGGUNA---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pengajar') {
    header("Location: ../login.php");
    exit();
}

// Cek status dari URL (untuk pop-up sukses)
$status_pop_up = $_GET['status'] ?? ''; // Akan digunakan oleh JS

$user_id_login = $_SESSION['user_id'];
$pengajar_data = [];

// Query Anda sudah benar, mengambil semua data
$sql = "SELECT dp.*, u.email
        FROM data_pengajar dp
        JOIN users u ON dp.user_id = u.id
        WHERE dp.user_id = ?
        LIMIT 1";
        
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $user_id_login);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $pengajar_data = $result->fetch_assoc();
    $_SESSION['nama_lengkap'] = $pengajar_data['nama_lengkap'];
} else {
    die("Error: Data pengajar tidak ditemukan.");
}
$stmt->close();
$koneksi->close();

// Helper function
function tampilkanData($data) {
    return htmlspecialchars(!empty($data) ? $data : '-');
}

// Format tanggal
$tanggal_lahir_formatted = '-';
if (!empty($pengajar_data['tanggal_lahir'])) {
    // Ganti format tanggal
    $timestamp = strtotime($pengajar_data['tanggal_lahir']);
    $tanggal_lahir_formatted = date('d F Y', $timestamp); // Cth: 15 Agustus 1990
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biodata Pengajar</title>
    
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, html {
            font-family: 'Poppins', sans-serif;
            background-color: var(--warna-latar);
            color: var(--warna-teks);
        }

        /* ========== SIDEBAR ========== */
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
            object-fit: cover;
        }
        
        .sidebar-header .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex-grow: 1;
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
            width: 30px;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .sidebar-nav li.logout {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
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
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease-out;
        }

        /* ========== HEADER ========== */
        .main-content {
            width: 100%;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: var(--warna-hijau);
            color: white;
            position: sticky;
            top: 0;
            z-index: 100;
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
            object-fit: cover;
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
            display: flex;
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
            overflow: hidden;
        }
        
        .icon-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ========== MAIN CONTENT ========== */
        .dashboard-area {
            padding: 20px 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .page-header h1 {
            color: var(--warna-teks);
            font-size: 1.6rem;
            margin: 0;
        }
        
        /* === PERBAIKAN CSS TOMBOL EDIT === */
        .edit-btn {
            background-color: var(--warna-hijau);
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 18px; /* Dikecilkan */
            transition: background-color 0.3s;
            display: inline-flex; /* Diubah */
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: auto; /* Diubah */
            margin-top: 10px; /* Tambahan margin */
        }
        
        .edit-btn:hover {
            background-color: #008a5a;
        }

        /* ========== BIODATA GRID ========== */
        .biodata-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }
        
        /* Profile Card */
        .card-profile {
            padding: 25px 20px;
            text-align: center;
        }
        
        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 15px auto;
            border: 4px solid var(--warna-hijau-muda);
            background-color: #eee;
            overflow: hidden;
        }
        
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-pic .icon {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #ccc;
        }
        
        .card-profile h2 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--warna-teks);
            word-break: break-word;
        }
        
        .nis-badge {
            display: inline-block;
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        /* Details Card */
        .card-details h3 {
            margin: 0;
            padding: 15px 20px;
            color: var(--warna-hijau);
            font-size: 1.1rem;
            font-weight: 700;
            background-color: var(--warna-hijau-muda);
        }
        
        .card-body {
            padding: 20px;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        /* --- === PERBAIKAN TAMPILAN DATA DIRI === --- */
        .info-list li {
            display: flex;
            flex-direction: row; /* Diubah */
            justify-content: space-between; /* Diubah */
            align-items: flex-start; /* Diubah */
            padding: 12px 0;
            font-size: 0.9rem;
            line-height: 1.5;
            border-bottom: 1px dashed #eee;
            gap: 15px; /* Diubah */
        }
        
        .info-list li:last-child {
            border-bottom: none;
        }
        
        .info-list li span {
            color: var(--warna-teks-abu);
            font-weight: 500;
            font-size: 0.85rem;
            flex-basis: 35%; /* Ditambahkan */
            flex-shrink: 0; /* Ditambahkan */
        }
        
        .info-list li strong {
            color: var(--warna-teks);
            word-break: break-word;
            padding-left: 0;
            flex-basis: 65%; /* Ditambahkan */
            text-align: right; /* Ditambahkan */
        }
        
        .info-list li.riwayat {
            flex-direction: column; /* Dikembalikan */
            align-items: flex-start;
            gap: 5px;
        }
        .info-list li.riwayat strong {
            text-align: left; /* Dikembalikan */
        }
        /* --- === AKHIR PERBAIKAN === --- */
        
        .riwayat pre {
            font-family: 'Poppins', sans-serif;
            margin: 5px 0 0 0;
            padding: 10px;
            color: var(--warna-teks);
            background-color: #f9f9f9;
            border-radius: 8px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 0.85rem;
        }

        /* Responsif HP Kecil (Header) */
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
            .page-header h1 {
                font-size: 1.4rem;
            }
            .dashboard-area {
                padding: 15px 12px;
            }
            .profile-pic {
                width: 120px;
                height: 120px;
            }
            .profile-pic .icon {
                font-size: 3rem;
            }
            .card-profile h2 {
                font-size: 1.2rem;
            }
            .nis-badge {
                font-size: 0.8rem;
                padding: 6px 14px;
            }
            .card-details h3 {
                font-size: 1rem;
                padding: 12px 15px;
            }
            .card-body {
                padding: 15px;
            }
            .info-list li {
                font-size: 0.85rem;
                padding: 10px 0;
            }
            .info-list li span {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../img/logo.jpg" alt="Logo">
            <i class="fas fa-arrow-left close-btn" id="close-btn"></i>
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard_pengajar.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="biodata_pengajar.php"><i class="fas fa-user"></i> Biodata</a></li>
            <li><a href="pengolahan_nilai.php"><i class="fas fa-edit"></i> Pengolahan Nilai</a></li>
            <li><a href="wali_kelas.php"><i class="fas fa-users-cog"></i> Wali Kelas</a></li>
            <li><a href="ganti_password.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
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
                    <img src="../img/logo.jpg" alt="Logo">
                </div>
                <div class="header-title">
                    Sistem Raport<br>Taman Pendidikan Al-Qur'an
                </div>
            </div>
            <div class="header-right">
                <a href="biodata_pengajar.php" class="user-profile">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($pengajar_data['nama_lengkap']); ?></span>
                    </div>
                    <div class="icon-wrapper">
                        <?php if (!empty($pengajar_data['foto'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($pengajar_data['foto']); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fas fa-user-tie"></i>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            
            <div class="page-header">
                <h1>Biodata Pengajar</h1>
                </div>

            <div class="biodata-grid">

                <div class="card card-profile">
                    <div class="profile-pic">
                        <?php if (!empty($pengajar_data['foto'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($pengajar_data['foto']); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <div class="icon"><i class="fas fa-user-tie"></i></div>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo tampilkanData($pengajar_data['nama_lengkap']); ?></h2>
                    <div class="nis-badge">
                        ID = <?php echo tampilkanData($pengajar_data['nip']); ?>
                    </div>
                </div>

                <div class="card card-details">
                    <h3>DATA DIRI</h3>
                    <div class="card-body">
                        <ul class="info-list">
                            <li>
                                <span>NIK</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['nik']); ?></strong>
                            </li>
                            <li>
                                <span>No. KK</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['no_kk']); ?></strong>
                            </li>
                            <li>
                                <span>Nama Lengkap</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['nama_lengkap']); ?></strong>
                            </li>
                            <li>
                                <span>Jenis Kelamin</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['jenis_kelamin']); ?></strong>
                            </li>
                            <li>
                                <span>Tempat Lahir</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['tempat_lahir']); ?></strong>
                            </li>
                            <li>
                                <span>Tanggal Lahir</span>
                                <strong>: <?php echo $tanggal_lahir_formatted; ?></strong>
                            </li>
                            <li>
                                <span>Alamat</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['alamat']); ?></strong>
                            </li>
                            <li>
                                <span>No. Hp</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['no_hp']); ?></strong>
                            </li>
                            <li>
                                <span>Email</span>
                                <strong>: <?php echo tampilkanData($pengajar_data['email']); ?></strong>
                            </li>
                            <li class="riwayat">
                                <span>Riwayat Pendidikan</span>
                                <strong>
                                    <pre><?php echo tampilkanData($pengajar_data['riwayat_pendidikan']); ?></pre>
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
            
            <a href="edit_biodata_pengajar.php" class="edit-btn">
                <i class="fas fa-pencil-alt"></i> Edit Biodata
            </a>

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

        // --- 2. Pop-up Sukses ---
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            // Cek jika variabel PHP $status_pop_up berisi 'sukses'
            if ("<?php echo $status_pop_up; ?>" === 'sukses') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data biodata Anda telah diperbarui.',
                    icon: 'success',
                    confirmButtonColor: '#00a86b'
                });
            }
        });
        
        // --- 3. JS BARU: Pop-up Konfirmasi Logout ---
        const btnLogout = document.getElementById('btn-logout');
        
        if(btnLogout) {
            btnLogout.addEventListener('click', function(e) {
                // Hentikan link agar tidak langsung logout
                e.preventDefault(); 
                
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Anda akan keluar dari sesi ini.",
                    icon: 'warning',
                    showCancelButton: true, // Tampilkan tombol "Tidak"
                    confirmButtonColor: '#00a86b', // Warna tombol "Ya"
                    cancelButtonColor: '#d33', // Warna tombol "Tidak"
                    confirmButtonText: 'Ya, keluar!',
                    cancelButtonText: 'Tidak'
                }).then((result) => {
                    // Jika user menekan tombol "Ya, keluar!"
                    if (result.isConfirmed) {
                        // Arahkan ke halaman logout.php
                        window.location.href = btnLogout.href; 
                    }
                });
            });
        }
    </script>

</body>
</html>