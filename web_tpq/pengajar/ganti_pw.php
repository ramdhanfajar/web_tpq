<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN: Cek apakah sudah login dan rolenya pengajar ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pengajar') {
    header("Location: ../login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$nama_pengajar = $_SESSION['nama_lengkap'] ?? "Pengajar"; 
$file_foto_pengajar = null;

// Ambil foto profil untuk header
$stmt_profil = $koneksi->prepare("SELECT foto FROM data_pengajar WHERE user_id = ?");
$stmt_profil->bind_param("i", $user_id_login);
$stmt_profil->execute();
$res_profil = $stmt_profil->get_result();
if($res_profil->num_rows > 0) {
    $d_profil = $res_profil->fetch_assoc();
    $file_foto_pengajar = $d_profil['foto'];
}
$stmt_profil->close();

$status_msg = '';

// --- 2. LOGIKA SIMPAN PASSWORD BARU (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi 1: Cek apakah password baru dan konfirmasi cocok
    if ($new_password !== $confirm_password) {
        $status_msg = 'gagal_konfirmasi';
    } else {
        // Validasi 2: Cek apakah password lama benar
        $stmt_check = $koneksi->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_id_login);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $user_data = $result_check->fetch_assoc();
        $stmt_check->close();

        if ($user_data && password_verify($old_password, $user_data['password'])) {
            // --- Password lama BENAR ---
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt_update = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_hashed_password, $user_id_login);
            
            if ($stmt_update->execute()) {
                $status_msg = 'sukses';
            } else {
                $status_msg = 'gagal_db';
            }
            $stmt_update->close();
            
        } else {
            // --- Password lama SALAH ---
            $status_msg = 'gagal_lama';
        }
    }
    
    // Redirect kembali ke halaman ini dengan status di URL
    header("Location: ganti_pw.php?status=" . $status_msg);
    exit();
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - <?php echo htmlspecialchars($nama_pengajar); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* CSS Template (Sama seperti dashboard_pengajar.php) */
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
        .sidebar-nav li.active a { background-color: var(--warna-latar); color: var(--warna-hijau); border-left: 5px solid white; padding-left: 20px; }
        .sidebar-nav li.active i { color: var(--warna-hijau); }
        .sidebar-nav li a i { width: 30px; font-size: 1.2rem; margin-right: 15px; }
        .sidebar-nav li.logout { margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.1); }
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
        .user-profile .icon-wrapper { background-color: white; color: var(--warna-hijau); border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin-left: 10px; font-size: 1.1rem; overflow: hidden; }
        .icon-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .dashboard-area { padding: 25px 20px; }
        .dashboard-area h1 { color: var(--warna-teks); font-size: 1.8rem; margin-top: 0; margin-bottom: 20px; }
        
        /* --- CSS Khusus Halaman Ganti Password --- */
        .card-form {
            max-width: 500px;
            margin: 0 auto; 
            background-color: #E0E0E0; 
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .card-form h3 {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--warna-teks-abu);
            margin-top: 0;
            margin-bottom: 30px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 1rem; font-weight: 600; color: var(--warna-teks-abu); margin-bottom: 8px; }
        .password-wrapper { position: relative; }
        .form-group input {
            width: 100%;
            padding: 12px 45px 12px 20px; 
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            border: none;
            border-radius: 30px;
            background-color: #AFF0D1; 
            color: var(--warna-teks-abu);
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--warna-teks-abu);
            cursor: pointer;
        }
        .form-footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 30px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-batal { background-color: #D6D6D6; color: var(--warna-teks-abu); }
        .btn-batal:hover { background-color: #bfbfbf; }
        .btn-update { background-color: #84E0B3; color: var(--warna-teks-abu); }
        .btn-update:hover { background-color: var(--warna-hijau); color: white; }
        
        /* CSS Responsif Header di HP Kecil */
        @media (max-width: 480px) {
            .header { padding: 12px 15px; }
            .header-logo img { width: 30px; height: 30px; }
            .header-title { font-size: 0.8rem; }
            .user-profile .user-info { display: none; }
            .user-profile .icon-wrapper { margin-left: 0; }
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
            <li><a href="wali_kelas.php"><i class="fas fa-users-cog"></i> Wali Kelas</a></li>
            <li class="active"><a href="ganti_pw.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
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
            <h1>Ganti Password</h1>

            <div class="card-form">
                <h3>Change Password</h3>
                
                <form action="ganti_pw.php" method="POST" id="change-password-form">
                    <div class="form-group">
                        <label for="old_password">Old password</label>
                        <div class="password-wrapper">
                            <input type="password" id="old_password" name="old_password" required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New password</label>
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password" required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <a href="dashboard_pengajar.php" class="btn btn-batal">Batal</a>
                        <button type="submit" class="btn btn-update">Update</button>
                    </div>
                </form>
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
        
        // --- 2. JS Show/Hide Password ---
        document.querySelectorAll('.toggle-password').forEach(function(icon) {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                } else {
                    input.type = 'password';
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                }
            });
        });
        
        // --- 3. JS Validasi Form ---
        const changePasswordForm = document.getElementById('change-password-form');
        
        changePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                Swal.fire({
                    title: 'Oops...',
                    text: 'Password Baru dan Konfirmasi Password tidak cocok!',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
                return; 
            }
            if (newPass.length < 6) {
                 Swal.fire({
                    title: 'Peringatan',
                    text: 'Password baru minimal harus 6 karakter.',
                    icon: 'warning',
                    confirmButtonColor: '#fd7e14'
                });
                return;
            }
            
            Swal.fire({
                title: 'Memperbarui...',
                text: 'Mohon tunggu sebentar.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            changePasswordForm.submit();
        });
        
        // --- 4. JS Pop-up Status (PERBAIKAN DI SINI) ---
        document.addEventListener('DOMContentLoaded', function() {
            // Kita ambil dari URL parameter 'status'
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');

            if (status === 'sukses') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Password telah diubah.',
                    icon: 'success',
                    confirmButtonText: 'Ok',
                    confirmButtonColor: '#00a86b'
                });
            } else if (status === 'gagal_konfirmasi') {
                Swal.fire({
                    title: 'Gagal',
                    text: 'Password Baru dan Konfirmasi Password tidak cocok!',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            } else if (status === 'gagal_lama') {
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Password Lama salah.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            } else if (status === 'gagal_db') {
                 Swal.fire({
                    title: 'Error',
                    text: 'Terjadi kesalahan pada database.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
        
        // --- 5. JS Logout ---
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