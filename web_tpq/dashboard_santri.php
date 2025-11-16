<?php
session_start();
require 'koneksi.php';

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya santri ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'santri') {
    header("Location: login.php");
    exit();
}

// --- 2. AMBIL DATA DINAMIS UNTUK KARTU ---
$user_id_login = $_SESSION['user_id'];
$nama_santri = "Santri";
$nama_kelas = "-";
$tahun_ajaran = "-";
$nama_wali = "-";

$sql_profil = "SELECT 
                    s.nama_lengkap AS nama_santri, 
                    k.nama_kelas, 
                    k.tahun_ajaran,
                    p.nama_lengkap AS nama_wali
                FROM data_santri s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN kelas k ON s.id_kelas = k.id
                LEFT JOIN data_pengajar p ON k.id_pengajar = p.id
                WHERE u.id = ?";
                
$stmt_profil = $koneksi->prepare($sql_profil);
$stmt_profil->bind_param("i", $user_id_login);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();

if ($result_profil->num_rows > 0) {
    $data_profil = $result_profil->fetch_assoc();
    $nama_santri = $data_profil['nama_santri'] ?: $nama_santri;
    $nama_kelas = $data_profil['nama_kelas'] ?: $nama_kelas;
    $tahun_ajaran = $data_profil['tahun_ajaran'] ?: $tahun_ajaran;
    $nama_wali = $data_profil['nama_wali'] ?: $nama_wali;
}
$stmt_profil->close();


// --- 3. AMBIL DATA UNTUK GRAFIK ---
$chart_labels = [];
$chart_data_nilai = [];

$sql_chart = "SELECT 
                AVG(CAST(n.nilai AS DECIMAL(5,2))) AS rata_rata, 
                n.tahun_ajaran
            FROM nilai_raport n
            JOIN data_santri s ON n.id_santri = s.id
            WHERE s.user_id = ?
            GROUP BY n.tahun_ajaran
            ORDER BY n.tahun_ajaran ASC";

$stmt_chart = $koneksi->prepare($sql_chart);
$stmt_chart->bind_param("i", $user_id_login);
$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();

while ($row_chart = $result_chart->fetch_assoc()) {
    $chart_labels[] = $row_chart['tahun_ajaran'];
    $chart_data_nilai[] = $row_chart['rata_rata'];
}
$stmt_chart->close();
$koneksi->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Santri - Sistem Raport TPQ</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        /* --- Sidebar & Overlay (Tidak Berubah) --- */
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; width: var(--lebar-sidebar); background-color: var(--warna-hijau); color: white; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease-out; display: flex; flex-direction: column; }
        .sidebar.active { transform: translateX(0); }
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
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-out, visibility 0s 0.3s linear; }
        .overlay.active { opacity: 1; visibility: visible; transition: opacity 0.3s ease-out; }

        /* --- Header (Tidak Berubah) --- */
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
        .dashboard-area {
            padding: 25px 20px;
        }
        .dashboard-area h1 {
            color: var(--warna-teks);
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
        }

        /* --- Grid Kartu (PERUBAHAN DI SINI) --- */
        .dashboard-grid {
            display: grid;
            /* Layout grid baru:
              Membuat kolom-kolom yang lebarnya minimal 150px.
              'auto-fit' akan otomatis mengisi baris sebanyak mungkin.
              '1fr' berarti sisa ruang akan dibagikan rata.
              Ini membuat grid lebih responsif.
            */
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            /* Shadow yang lebih halus */
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
        }

        /* Kartu Welcome (Penuh) */
        .card-welcome {
            grid-column: 1 / -1; /* Tetap ambil 1 baris penuh */
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

        /* Kartu kecil (PERUBAHAN DI SINI) */
        .card-small {
            text-align: center;
            padding: 25px 15px;
        }
        
        .card-small h3 {
            margin: 0 0 12px 0;
            font-size: 1rem; /* Sedikit lebih besar dari sebelumnya */
            font-weight: 600;
            color: var(--warna-hijau); /* Warna konsisten */
            
            /* Untuk merapikan ikon dan teks */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px; /* Jarak antara ikon dan teks */
        }
        
        .card-small p {
            margin: 0;
            font-size: 1.2rem; /* Ukuran font konsisten */
            font-weight: 600; /* Sedikit lebih tebal */
            color: var(--warna-teks-abu);
            line-height: 1.4;
        }
        
        /* Hapus style untuk .card-wali, sudah tidak perlu */

        /* Kartu Grafik (Penuh) */
        .card-chart {
            grid-column: 1 / -1; /* Tetap ambil 1 baris penuh */
        }
        .card-chart h3 {
            margin: 0 0 20px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--warna-teks);
            text-align: center;
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
            <li class="active"><a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="biodata_santri.php"><i class="fas fa-user"></i> Biodata</a></li>
            <li><a href="#"><i class="fas fa-chart-bar"></i> Informasi Nilai</a></li>
            <li><a href="#"><i class="fas fa-lock"></i> Ganti Password</a></li>
            <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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
                        <span><?php echo htmlspecialchars($nama_santri); ?></span>
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
                    <p>Selamat datang <?php echo htmlspecialchars($nama_santri); ?> di Sistem Raport Taman Pendidikan Al-Qur'an Daarul Hikmah</p>
                </div>

                <div class="card card-small">
                    <h3><i class="fas fa-calendar-alt"></i> Tahun Ajaran</h3>
                    <p><?php echo htmlspecialchars($tahun_ajaran); ?></p>
                </div>

                <div class="card card-small kelas">
                    <h3><i class="fas fa-school"></i> Kelas</h3>
                    <p><?php echo htmlspecialchars($nama_kelas); ?></p>
                </div>
                
                <div class="card card-small wali">
                    <h3><i class="fas fa-user-tie"></i> Wali Kelas</h3>
                    <p><?php echo htmlspecialchars($nama_wali); ?></p>
                </div>

                <div class="card card-chart">
                    <h3>Rata-Rata Nilai Anda</h3>
                    <canvas id="myChart" style="max-height: 250px;"></canvas>
                </div>

            </div>
        </main>
    </div>

    <script>
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
    </script>

    <script>
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartData = <?php echo json_encode($chart_data_nilai); ?>;

        const data = {
            labels: chartLabels,
            datasets: [{
                label: 'Rata-Rata Nilai',
                data: chartData,
                fill: false,
                borderColor: '#00a86b',
                backgroundColor: '#00a86b',
                tension: 0.1
            }]
        };

        const config = {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                    }
                }
            }
        };

        const myChart = new Chart(
            document.getElementById('myChart'),
            config
        );
    </script>

</body>
</html>