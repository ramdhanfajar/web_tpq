<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya pengajar ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pengajar') {
    header("Location: ../login.php");
    exit();
}

// --- 2. AMBIL ID & PROFIL PENGGUNA (TERMASUK FOTO) ---
$user_id_login = $_SESSION['user_id'];
$id_pengajar_db = null;
$nama_pengajar = "Pengajar";
$file_foto_pengajar = null; // Variabel untuk foto

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

// --- 3. LOGIKA SIMPAN NILAI (POST REQUEST) ---
$status_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // (Logika PHP POST Anda sudah benar, tidak diubah)
    $id_kelas = $_POST['id_kelas'];
    $id_materi = $_POST['id_materi'];
    $semester = $_POST['semester'];
    $tahun_ajaran = $_POST['tahun_ajaran'];
    $santri_ids = $_POST['santri_id'];
    $id_nilais = $_POST['id_nilai'];
    $nilais = $_POST['nilai'];
    $catatans = $_POST['catatan'];
    $koneksi->begin_transaction();
    try {
        $stmt_update = $koneksi->prepare("UPDATE nilai_raport SET nilai = ?, catatan = ? WHERE id = ?");
        $stmt_insert = $koneksi->prepare("INSERT INTO nilai_raport 
            (id_santri, id_materi, id_pengajar, nilai, catatan, semester, tahun_ajaran) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($santri_ids); $i++) {
            $id_nilai = $id_nilais[$i];
            $id_santri = $santri_ids[$i];
            $nilai = $nilais[$i];
            $catatan = $catatans[$i];
            if (!empty($id_nilai)) {
                if (!empty($nilai)) {
                    $stmt_update->bind_param("ssi", $nilai, $catatan, $id_nilai);
                    $stmt_update->execute();
                }
            } elseif (!empty($nilai)) {
                $stmt_insert->bind_param("iiissss", $id_santri, $id_materi, $id_pengajar_db, $nilai, $catatan, $semester, $tahun_ajaran);
                $stmt_insert->execute();
            }
        }
        $koneksi->commit();
        $status_msg = "sukses";
    } catch (Exception $e) {
        $koneksi->rollback();
        $status_msg = "gagal";
    }
    $stmt_update->close();
    $stmt_insert->close();
    header("Location: pengolahan_nilai.php?id_kelas=$id_kelas&id_materi=$id_materi&semester=$semester&tahun_ajaran=$tahun_ajaran&status=$status_msg");
    exit();
}

// --- 4. AMBIL DATA UNTUK FILTER DROPDOWN (GET REQUEST) ---
$selected_kelas_id = $_GET['id_kelas'] ?? null;
$selected_materi_id = $_GET['id_materi'] ?? null;
$selected_semester = $_GET['semester'] ?? null;
$selected_tahun = $_GET['tahun_ajaran'] ?? null;

// Query 1: Ambil daftar kelas yang diajar pengajar ini
$list_kelas = [];
$sql_kelas = "SELECT k.id, k.nama_kelas, k.tahun_ajaran 
              FROM kelas k 
              WHERE k.id_pengajar = ? 
              ORDER BY k.tahun_ajaran DESC, k.nama_kelas ASC";
$stmt_kelas = $koneksi->prepare($sql_kelas);
$stmt_kelas->bind_param("i", $id_pengajar_db);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
while($row_kelas = $result_kelas->fetch_assoc()) {
    $list_kelas[] = $row_kelas;
}
$stmt_kelas->close();

// Query 2: Ambil daftar materi
$list_materi = [];
$result_materi = $koneksi->query("SELECT id, nama_materi FROM materi ORDER BY nama_materi ASC");
while($row_materi = $result_materi->fetch_assoc()) {
    $list_materi[] = $row_materi;
}

// --- 5. AMBIL DATA SISWA & NILAI JIKA SEMUA FILTER TERPILIH ---
$data_siswa_nilai = [];
if ($selected_kelas_id && $selected_materi_id && $selected_semester && $selected_tahun) {
    // (Query Anda sudah benar)
    $sql_nilai = "SELECT 
                    ds.id, ds.nis, ds.nama_lengkap, 
                    nr.id AS id_nilai, nr.nilai, nr.catatan
                  FROM data_santri ds
                  LEFT JOIN nilai_raport nr ON ds.id = nr.id_santri 
                                             AND nr.id_materi = ? 
                                             AND nr.semester = ? 
                                             AND nr.tahun_ajaran = ?
                  WHERE ds.id_kelas = ?
                  ORDER BY ds.nama_lengkap ASC";
    
    $stmt_nilai = $koneksi->prepare($sql_nilai);
    $stmt_nilai->bind_param("issi", $selected_materi_id, $selected_semester, $selected_tahun, $selected_kelas_id);
    $stmt_nilai->execute();
    $result_nilai = $stmt_nilai->get_result();
    while($row_nilai = $result_nilai->fetch_assoc()) {
        $data_siswa_nilai[] = $row_nilai;
    }
    $stmt_nilai->close();
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengolahan Nilai - <?php echo htmlspecialchars($nama_pengajar); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* === INI ADALAH CSS GABUNGAN (NON-RESPONSIVE) === */
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
        
        /* --- CSS Halaman Ini --- */
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 20px;
        }
        
        /* Form Filter */
        .filter-form {
            display: grid;
            grid-template-columns: 1fr; /* Selalu 1 kolom */
            gap: 15px;
        }
        @media (min-width: 576px) {
            .filter-form {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                align-items: flex-end;
            }
        }
        .filter-form .form-group {
            display: flex;
            flex-direction: column;
        }
        .filter-form label {
            font-weight: 600;
            color: var(--warna-teks-abu);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .filter-form select, .filter-form button {
            padding: 12px 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
        }
        .filter-form button {
            background-color: var(--warna-hijau);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .filter-form button:hover {
            background-color: #008a5a;
        }
        .filter-form button:disabled {
            background-color: #aaa;
            cursor: not-allowed;
        }

        /* Alert Pop-up (via JS) */
        .alert {
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .alert-success { background-color: var(--warna-hijau-muda); color: var(--warna-hijau); }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        
        /* Tabel Nilai */
        .card-table {
            overflow: hidden;
            padding: 0;
        }
        .card-body {
            overflow-x: auto; /* Penting agar bisa di-scroll di HP */
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap; /* Agar tidak turun baris */
        }
        .data-table th {
            background-color: var(--warna-hijau-muda);
            color: var(--warna-hijau);
            font-weight: 600;
        }
        .data-table td {
            color: var(--warna-teks-abu);
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .data-table .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
        }
        
        /* Input Form di dalam Tabel */
        .input-nilai {
            width: 70px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            text-align: center;
        }
        .input-nilai.input-error {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        .input-catatan {
            width: 100%;
            min-width: 150px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .form-footer {
            padding: 20px;
            text-align: right;
            border-top: 1px solid #f0f0f0;
        }
        .btn-simpan {
            background-color: #0d6efd;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-simpan:hover {
            background-color: #0b5ed7;
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
            <li class="active"><a href="pengolahan_nilai.php"><i class="fas fa-edit"></i> Pengolahan Nilai</a></li>
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
            <h1>Pengolahan Nilai Santri</h1>

            <div class="card">
                <form action="pengolahan_nilai.php" method="GET" class="filter-form" id="filter-form">
                    <div class="form-group">
                        <label for="id_kelas">Pilih Kelas</label>
                        <select name="id_kelas" id="id_kelas" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($list_kelas as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>" <?php if ($kelas['id'] == $selected_kelas_id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($kelas['nama_kelas'] . ' (' . $kelas['tahun_ajaran'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_materi">Pilih Materi</label>
                        <select name="id_materi" id="id_materi" required>
                            <option value="">-- Pilih Materi --</option>
                            <?php foreach ($list_materi as $materi): ?>
                                <option value="<?php echo $materi['id']; ?>" <?php if ($materi['id'] == $selected_materi_id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($materi['nama_materi']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" required>
                            <option value="">-- Pilih --</option>
                            <option value="1" <?php if ('1' == $selected_semester) echo 'selected'; ?>>1 (Ganjil)</option>
                            <option value="2" <?php if ('2' == $selected_semester) echo 'selected'; ?>>2 (Genap)</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="tahun_ajaran" id="tahun_ajaran" value="<?php echo htmlspecialchars($selected_tahun); ?>">
                    
                    <button type="submit" id="btn-tampilkan"><i class="fas fa-search"></i> Tampilkan Data</button>
                </form>
            </div>

            <?php if (!empty($data_siswa_nilai)): ?>
            <div class="card card-table">
                <form action="pengolahan_nilai.php" method="POST" id="form-nilai">
                    <input type="hidden" name="id_kelas" value="<?php echo htmlspecialchars($selected_kelas_id); ?>">
                    <input type="hidden" name="id_materi" value="<?php echo htmlspecialchars($selected_materi_id); ?>">
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selected_semester); ?>">
                    <input type="hidden" name="tahun_ajaran" value="<?php echo htmlspecialchars($selected_tahun); ?>">
                
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>NIS</th>
                                    <th>Nama Santri</th>
                                    <th>Nilai</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_siswa_nilai as $siswa): ?>
                                <tr>
                                    <td><?php echo $no++; ?>.</td>
                                    <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td>
                                        <input type="number" name="nilai[]" class="input-nilai nilai-input" value="<?php echo htmlspecialchars($siswa['nilai'] ?? ''); ?>" min="0" max="100">
                                    </td>
                                    <td>
                                        <input type="text" name="catatan[]" class="input-catatan" value="<?php echo htmlspecialchars($siswa['catatan'] ?? ''); ?>">
                                    </td>
                                    <input type="hidden" name="santri_id[]" value="<?php echo $siswa['id']; ?>">
                                    <input type="hidden" name="id_nilai[]" value="<?php echo $siswa['id_nilai'] ?? ''; ?>">
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn-simpan"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
            <?php elseif ($selected_kelas_id): ?>
                <div class="card no-data">
                    Tidak ada data santri di kelas ini.
                </div>
            <?php else: ?>
                <div class="card no-data">
                    Silakan pilih filter di atas untuk menampilkan data nilai santri.
                </div>
            <?php endif; ?>

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
        
        // --- 2. Script auto-fill tahun ajaran ---
        document.getElementById('id_kelas').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var optionText = selectedOption.text;
            var match = optionText.match(/\(([^)]+)\)/);
            if (match) {
                document.getElementById('tahun_ajaran').value = match[1];
            } else {
                document.getElementById('tahun_ajaran').value = '';
            }
        });
        if (document.getElementById('id_kelas').value) {
            document.getElementById('id_kelas').dispatchEvent(new Event('change'));
        }

        // --- 3. JS Pop-up Sukses/Gagal (dari URL) ---
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');

            if (status === 'sukses') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data nilai telah berhasil disimpan.',
                    icon: 'success',
                    confirmButtonColor: '#00a86b'
                });
            } else if (status === 'gagal') {
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan. Data nilai tidak tersimpan.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });

        // --- 4. JS Loading Tombol "Tampilkan Data" ---
        const filterForm = document.getElementById('filter-form');
        const btnTampilkan = document.getElementById('btn-tampilkan');
        
        if(filterForm) {
            filterForm.addEventListener('submit', function() {
                // Hanya disable jika form valid
                if (document.getElementById('id_kelas').value && document.getElementById('id_materi').value && document.getElementById('semester').value) {
                    btnTampilkan.disabled = true;
                    btnTampilkan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';
                }
            });
        }
        
        // --- 5. JS Validasi & Loading Tombol "Simpan Perubahan" ---
        const formNilai = document.getElementById('form-nilai');
        
        if(formNilai) {
            formNilai.addEventListener('submit', function(e) {
                e.preventDefault(); // Hentikan submit
                
                let isValid = true;
                
                // Hapus error sebelumnya
                document.querySelectorAll('.input-nilai').forEach(input => {
                    input.classList.remove('input-error');
                });
                
                // Cek setiap input nilai
                const nilaiInputs = document.querySelectorAll('.input-nilai');
                for (let input of nilaiInputs) {
                    const nilai = input.value;
                    
                    if (nilai === '') {
                        continue; // Boleh kosong
                    }
                    
                    const nilaiNum = parseFloat(nilai);
                    
                    if (isNaN(nilaiNum) || nilaiNum < 0 || nilaiNum > 100) {
                        isValid = false;
                        input.classList.add('input-error'); // Tandai input yg error
                    }
                }
                
                if (!isValid) {
                    // Tampilkan error jika ada nilai tidak valid
                    Swal.fire({
                        title: 'Oops... Ada Kesalahan',
                        text: 'Nilai harus berupa angka antara 0 dan 100. Silakan perbaiki data yang ditandai merah.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                } else {
                    // Jika valid, tampilkan loading dan submit form
                    Swal.fire({
                        title: 'Menyimpan...',
                        text: 'Mohon tunggu sebentar.',
                        icon: 'info',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    formNilai.submit(); // Lanjutkan submit
                }
            });
        }
        
        // --- 6. JS Pop-up Konfirmasi Logout ---
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