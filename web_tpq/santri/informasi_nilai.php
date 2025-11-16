<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya santri ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'santri') {
    header("Location: ../login.php");
    exit();
}

// --- 2. AMBIL DATA SANTRI & TAHUN AJARAN ---
$user_id_login = $_SESSION['user_id'];
$nama_santri = "Santri";
$file_foto_santri = null; // Variabel untuk foto

// Query 0: Ambil nama & foto untuk header
$stmt_profil = $koneksi->prepare("SELECT nama_lengkap, foto FROM data_santri WHERE user_id = ?");
$stmt_profil->bind_param("i", $user_id_login);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();
if($result_profil->num_rows > 0) {
    $data_profil = $result_profil->fetch_assoc();
    $nama_santri = $data_profil['nama_lengkap'];
    $file_foto_santri = $data_profil['foto'];
    $_SESSION['nama_lengkap'] = $nama_santri;
}
$stmt_profil->close();


// Query 1: Ambil SEMUA tahun ajaran unik yang dimiliki santri ini (untuk dropdown)
$list_tahun_ajaran = [];
$stmt_tahun = $koneksi->prepare("SELECT DISTINCT n.tahun_ajaran 
                                FROM nilai_raport n 
                                JOIN data_santri s ON n.id_santri = s.id 
                                WHERE s.user_id = ? 
                                ORDER BY n.tahun_ajaran DESC");
$stmt_tahun->bind_param("i", $user_id_login);
$stmt_tahun->execute();
$result_tahun = $stmt_tahun->get_result();
while($row_tahun = $result_tahun->fetch_assoc()) {
    $list_tahun_ajaran[] = $row_tahun['tahun_ajaran'];
}
$stmt_tahun->close();

// --- 3. TENTUKAN TAHUN AJARAN YANG DIPILIH ---
$selected_tahun = $_GET['tahun_ajaran'] ?? ($list_tahun_ajaran[0] ?? null);

// --- 4. AMBIL DATA NILAI & RATA-RATA (JIKA TAHUN AJARAN DIPILIH) ---
$data_nilai = [];
$rata_rata = 0;

if ($selected_tahun) {
    // Query 2: Ambil data nilai untuk tabel
    $sql_data = "SELECT m.id AS materi_id, m.nama_materi, k.nama_kelas, n.nilai
                 FROM nilai_raport n
                 JOIN data_santri s ON n.id_santri = s.id
                 JOIN materi m ON n.id_materi = m.id
                 LEFT JOIN kelas k ON s.id_kelas = k.id
                 WHERE s.user_id = ? AND n.tahun_ajaran = ?
                 ORDER BY m.id ASC";
    $stmt_data = $koneksi->prepare($sql_data);
    $stmt_data->bind_param("is", $user_id_login, $selected_tahun);
    $stmt_data->execute();
    $result_data = $stmt_data->get_result();
    while($row_data = $result_data->fetch_assoc()) {
        $data_nilai[] = $row_data;
    }
    $stmt_data->close();

    // Query 3: Ambil rata-rata
    $sql_avg = "SELECT AVG(CAST(nilai AS DECIMAL(5,2))) AS rata_rata
                FROM nilai_raport n
                JOIN data_santri s ON n.id_santri = s.id
                WHERE s.user_id = ? AND n.tahun_ajaran = ?";
    $stmt_avg = $koneksi->prepare($sql_avg);
    $stmt_avg->bind_param("is", $user_id_login, $selected_tahun);
    $stmt_avg->execute();
    $result_avg = $stmt_avg->get_result();
    $avg_row = $result_avg->fetch_assoc();
    $rata_rata = $avg_row['rata_rata'];
    $stmt_avg->close();
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi Nilai - <?php echo htmlspecialchars($nama_santri); ?></title>
    
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

        /* --- CSS Khusus Halaman Ini --- */
        .dashboard-area {
            padding: 25px 20px;
        }
        .dashboard-area h1 {
            color: var(--warna-teks);
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        /* Style untuk Filter Dropdown */
        .filter-form {
            margin-bottom: 20px;
        }
        .filter-form label {
            font-weight: 600;
            color: var(--warna-teks-abu);
            font-size: 0.9rem;
            display: block;
            margin-bottom: 8px;
        }
        .filter-form select {
            width: 100%;
            padding: 12px 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2300a86b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 10px;
            cursor: pointer;
        }
        
        /* Style untuk Kartu Tabel */
        .card-table {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            border: 1px solid #ddd;
        }
        .card-body {
            padding: 0;
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
            color: var(--warna-teks-abu);
            white-space: nowrap;
        }
        .data-table th {
            background-color: #f9f9f9;
            color: var(--warna-teks);
            font-weight: 600;
        }
        .data-table td:last-child {
            font-weight: 700;
            color: var(--warna-hijau);
            font-size: 1rem;
            text-align: center;
        }
        
        .data-table tfoot tr {
            background-color: #f9f9f9;
            border-top: 2px solid #ccc;
        }
        .data-table tfoot td {
            font-weight: 700;
            color: var(--warna-teks);
        }
        .data-table tfoot td:first-child {
            text-align: right;
        }
        .data-table tfoot td:last-child {
            color: var(--warna-hijau);
            font-size: 1.1rem;
            text-align: center;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            font-style: italic;
            color: var(--warna-teks-abu);
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
            <li><a href="dashboard_santri.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="biodata_santri.php"><i class="fas fa-user"></i> Biodata</a></li>
            <li class="active"><a href="informasi_nilai.php"><i class="fas fa-chart-bar"></i> Informasi Nilai</a></li>
            <li><a href="ganti_password_santri.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
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
                <a href="biodata_santri.php" class="user-profile">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($nama_santri); ?></span>
                    </div>
                    <div class="icon-wrapper">
                        <?php if (!empty($file_foto_santri)): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($file_foto_santri); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        </header>

        <main class="dashboard-area">
            <h1>Informasi Nilai</h1>
            
            <form action="informasi_nilai.php" method="GET" class="filter-form">
                <label for="tahun_ajaran">Tahun ajaran</label>
                <select name="tahun_ajaran" id="tahun_ajaran" onchange="this.form.submit()">
                    <?php if (empty($list_tahun_ajaran)): ?>
                        <option>Belum ada data</option>
                    <?php else: ?>
                        <?php foreach ($list_tahun_ajaran as $tahun): ?>
                            <option value="<?php echo htmlspecialchars($tahun); ?>" <?php if ($tahun == $selected_tahun) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($tahun); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <div class="card-table">
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_nilai)): ?>
                                <tr>
                                    <td colspan="4" class="no-data">
                                        <?php if(empty($list_tahun_ajaran)): ?>
                                            Belum ada data nilai yang dimasukkan.
                                        <?php else: ?>
                                            Tidak ada data untuk tahun ajaran ini.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data_nilai as $nilai): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($nilai['materi_id']); ?></td>
                                        <td><?php echo htmlspecialchars($nilai['nama_materi']); ?></td>
                                        <td><?php echo htmlspecialchars($nilai['nama_kelas'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($nilai['nilai']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($data_nilai)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Rata-rata</strong></td>
                                <td><strong><?php echo number_format($rata_rata, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
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
        function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
        hamburgerBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        
        // --- 2. JS Pop-up Selamat Datang (Sama seperti dashboard) ---
        document.addEventListener('DOMContentLoaded', function() {
            const namaSantri = "<?php echo htmlspecialchars($nama_santri, ENT_QUOTES); ?>";
            
        });
        
        // --- 3. JS Pop-up Konfirmasi Logout ---
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