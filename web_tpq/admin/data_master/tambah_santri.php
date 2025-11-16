<?php
session_start();
require '../../koneksi.php'; // Path sudah benar (naik 2 level)

// --- 1. KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$status_msg = '';

// --- 2. LOGIKA SIMPAN (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data Akun
    $email = $_POST['email'];
    $password = $_POST['password']; // Password mentah
    
    // Ambil data Diri
    $nama_lengkap = $_POST['nama_lengkap'];
    $nis = $_POST['nis'];
    $id_kelas = $_POST['id_kelas'];
    $nik = $_POST['nik'];
    $no_kk = $_POST['no_kk'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];
    
    // Ambil data Ortu
    $nama_ayah = $_POST['nama_ayah'];
    $pekerjaan_ayah = $_POST['pekerjaan_ayah'];
    $nama_ibu = $_POST['nama_ibu'];
    $pekerjaan_ibu = $_POST['pekerjaan_ibu'];
    $gaji_per_bulan = $_POST['gaji_per_bulan'];

    // Validasi dasar
    if (empty($email) || empty($password) || empty($nama_lengkap) || empty($nis)) {
        $status_msg = "<div class='alert alert-danger'>Email, Password, Nama Lengkap, dan NIS wajib diisi.</div>";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'santri';

        // Mulai Transaksi
        $koneksi->begin_transaction();
        
        try {
            // Langkah 1: Insert ke tabel 'users'
            $stmt_user = $koneksi->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt_user->bind_param("sss", $email, $hashed_password, $role);
            $stmt_user->execute();
            
            // Ambil ID user yang baru saja dibuat
            $new_user_id = $koneksi->insert_id;
            $stmt_user->close();

            // Langkah 2: Insert ke tabel 'data_santri'
            $stmt_santri = $koneksi->prepare(
                "INSERT INTO data_santri (user_id, nama_lengkap, nis, id_kelas, nik, no_kk, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, no_hp, nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu, gaji_per_bulan) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_santri->bind_param(
                "ississssssssssss", 
                $new_user_id, $nama_lengkap, $nis, $id_kelas, $nik, $no_kk, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat, $no_hp, $nama_ayah, $pekerjaan_ayah, $nama_ibu, $pekerjaan_ibu, $gaji_per_bulan
            );
            $stmt_santri->execute();
            $stmt_santri->close();

            // Jika semua berhasil, commit
            $koneksi->commit();
            $status_msg = "<div class='alert alert-success'>Santri baru berhasil ditambahkan. Akun login telah dibuat.</div>";

        } catch (Exception $e) {
            // Jika ada error (misal email/NIS duplikat), batalkan
            $koneksi->rollback();
            if ($koneksi->errno == 1062) { // Error duplikat
                $status_msg = "<div class='alert alert-danger'>Gagal menambahkan: Email atau NIS sudah terdaftar.</div>";
            } else {
                $status_msg = "<div class='alert alert-danger'>Gagal menambahkan: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// --- 3. AMBIL DATA KELAS UNTUK DROPDOWN ---
$list_kelas = [];
$result_kelas = $koneksi->query("SELECT id, nama_kelas, tahun_ajaran FROM kelas ORDER BY nama_kelas ASC");
if ($result_kelas->num_rows > 0) {
    while($row_kelas = $result_kelas->fetch_assoc()) {
        $list_kelas[] = $row_kelas;
    }
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Santri Baru - Admin</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
        
        /* --- Sidebar & Header (Sama seperti dashboard_admin.php) --- */
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
        
        /* --- CSS Khusus Halaman Form --- */
        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 20px;
        }
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 {
            margin: 0;
            color: var(--warna-hijau);
        }
        .btn-kembali {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .btn-kembali:hover { background-color: #5a6268; }
        
        .card-body {
            padding: 20px;
        }
        .form-tambah {
            display: grid;
            grid-template-columns: 1fr; /* 1 kolom di HP */
            gap: 20px;
        }
        
        /* Layout 2 kolom di desktop */
        @media (min-width: 768px) {
            .form-tambah {
                grid-template-columns: 1fr 1fr;
            }
            .form-group-full {
                grid-column: 1 / -1; /* Ambil 2 kolom */
            }
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; color: var(--warna-teks-abu); font-size: 0.9rem; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group.required label::after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-footer {
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #f0f0f0;
            text-align: right;
        }
        .btn-simpan {
            background-color: var(--warna-hijau);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-simpan:hover { background-color: #008a5a; }
        
        .alert { padding: 15px; border-radius: 8px; font-weight: 500; margin-bottom: 20px; }
        .alert-success { background-color: var(--warna-hijau-muda); color: var(--warna-hijau); }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../../img/logo.jpg" alt="Logo">
            <i class="fas fa-arrow-left close-btn" id="close-btn"></i>
        </div>
        <ul class="sidebar-nav">
            <li><a href="../dashboard_admin.php"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a></li>
            <li class="nav-item dropdown active">
                <a href="#" class="dropdown-toggle active">
                    <span><i class="fas fa-database fa-fw"></i> Master Data</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </a>
                <ul class="submenu active">
                    <li class="active-sub"><a href="data_santri.php">Santri</a></li>
                    <li><a href="data_pengajar.php">Guru</a></li>
                    <li><a href="data_kelas.php">Kelas</a></li>
                    <li><a href="tahun_ajaran.php">Tahun Ajaran</a></li>
                </ul>
            </li>
            <li><a href="#"><i class="fas fa-chart-bar fa-fw"></i> Pengolahan Nilai</a></li>
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
            <li><a href="#"><i class="fas fa-lock fa-fw"></i> Ganti Password</a></li>
            <li class="logout"><a href="../../logout.php" id="btn-logout"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
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
                    <img src="../../img/logo.jpg" alt="Logo">
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
            <h1>Tambah Santri Baru</h1>
            
            <?php echo $status_msg; // Tampilkan pesan sukses/gagal ?>

            <form action="tambah_santri.php" method="POST" id="form-tambah-santri">
                
                <div class="card">
                    <div class="card-header">
                        <h3>Akun Login Santri</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-tambah">
                            <div class="form-group required">
                                <label for="email">Email (untuk login)</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group required">
                                <label for="password">Password Awal</label>
                                <input type="password" id="password" name="password" required placeholder="Min. 6 karakter">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Data Diri Santri</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-tambah">
                            <div class="form-group required">
                                <label for="nama_lengkap">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" required>
                            </div>
                            <div class="form-group required">
                                <label for="nis">NIS (Nomor Induk Santri)</label>
                                <input type="text" id="nis" name="nis" required>
                            </div>
                            <div class="form-group">
                                <label for="id_kelas">Kelas</label>
                                <select id="id_kelas" name="id_kelas">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($list_kelas as $kelas): ?>
                                        <option value="<?php echo $kelas['id']; ?>">
                                            <?php echo htmlspecialchars($kelas['nama_kelas'] . ' (' . $kelas['tahun_ajaran'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="jenis_kelamin">Jenis Kelamin</label>
                                <select id="jenis_kelamin" name="jenis_kelamin">
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="nik">NIK</label>
                                <input type="text" id="nik" name="nik">
                            </div>
                            <div class="form-group">
                                <label for="no_kk">No. KK</label>
                                <input type="text" id="no_kk" name="no_kk">
                            </div>
                            <div class="form-group">
                                <label for="tempat_lahir">Tempat Lahir</label>
                                <input type="text" id="tempat_lahir" name="tempat_lahir">
                            </div>
                            <div class="form-group">
                                <label for="tanggal_lahir">Tanggal Lahir</label>
                                <input type="date" id="tanggal_lahir" name="tanggal_lahir">
                            </div>
                            <div class="form-group">
                                <label for="no_hp">No. Hp Santri</label>
                                <input type="text" id="no_hp" name="no_hp">
                            </div>
                            <div class="form-group form-group-full">
                                <label for="alamat">Alamat</label>
                                <textarea id="alamat" name="alamat"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Data Orang Tua / Wali</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-tambah">
                            <div class="form-group">
                                <label for="nama_ayah">Nama Ayah</label>
                                <input type="text" id="nama_ayah" name="nama_ayah">
                            </div>
                            <div class="form-group">
                                <label for="pekerjaan_ayah">Pekerjaan Ayah</label>
                                <input type="text" id="pekerjaan_ayah" name="pekerjaan_ayah">
                            </div>
                            <div class="form-group">
                                <label for="nama_ibu">Nama Ibu</label>
                                <input type="text" id="nama_ibu" name="nama_ibu">
                            </div>
                            <div class="form-group">
                                <label for="pekerjaan_ibu">Pekerjaan Ibu</label>
                                <input type="text" id="pekerjaan_ibu" name="pekerjaan_ibu">
                            </div>
                            <div class="form-group form-group-full">
                                <label for="gaji_per_bulan">Total Gaji Per Bulan</label>
                                <input type="text" id="gaji_per_bulan" name="gaji_per_bulan">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <a href="data_santri.php" class="btn-kembali" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600;">Batal</a>
                        <button type="submit" class="btn-simpan"><i class="fas fa-save"></i> Simpan Santri</button>
                    </div>
                </div>
                
            </form>
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

        // --- 2. Script Dropdown Sidebar ---
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
        
        // --- 4. JS Validasi & Pop-up Loading ---
        const formTambah = document.getElementById('form-tambah-santri');
        formTambah.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const nama = document.getElementById('nama_lengkap').value;
            const nis = document.getElementById('nis').value;
            
            if (email.trim() === '' || password.trim() === '' || nama.trim() === '' || nis.trim() === '') {
                Swal.fire({
                    title: 'Oops...',
                    text: 'Email, Password, Nama Lengkap, dan NIS wajib diisi!',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            if (password.length < 6) {
                Swal.fire({
                    title: 'Oops...',
                    text: 'Password minimal harus 6 karakter.',
                    icon: 'warning',
                    confirmButtonColor: '#fd7e14'
                });
                return;
            }
            
            // Tampilkan loading
            Swal.fire({
                title: 'Menyimpan...',
                text: 'Mohon tunggu sebentar, akun santri sedang dibuat.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit form
            formTambah.submit();
        });
    </script>

</body>
</html>