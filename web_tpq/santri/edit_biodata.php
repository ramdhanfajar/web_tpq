<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'santri') {
    header("Location: ../login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$status_msg = '';

// --- 2. LOGIKA SIMPAN (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil ID santri dari data_santri
    $stmt_get_id = $koneksi->prepare("SELECT id FROM data_santri WHERE user_id = ?");
    $stmt_get_id->bind_param("i", $user_id_login);
    $stmt_get_id->execute();
    $id_santri_db = $stmt_get_id->get_result()->fetch_assoc()['id'];
    $stmt_get_id->close();
    
    // Ambil data form
    $nama_lengkap = $_POST['nama_lengkap'];
    $nik = $_POST['nik'];
    $no_kk = $_POST['no_kk'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];
    $email = $_POST['email']; // Email ada di tabel 'users'
    // Data Orang Tua
    $nama_ayah = $_POST['nama_ayah'];
    $pekerjaan_ayah = $_POST['pekerjaan_ayah'];
    $nama_ibu = $_POST['nama_ibu'];
    $pekerjaan_ibu = $_POST['pekerjaan_ibu'];
    $gaji_per_bulan = $_POST['gaji_per_bulan'];
    
    $foto_sql_part = "";
    $params_foto = [];

    // --- LOGIKA UPLOAD FOTO (SUDAH DIPERBAIKI) ---
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $upload_dir = '../uploads/';
        // Buat nama file unik: santri_id_timestamp_namafile
        $filename = 'santri_' . $id_santri_db . '_' . time() . '_' . basename($_FILES['foto']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
            // Jika upload berhasil, siapkan query SQL
            $foto_sql_part = ", foto = ?";
            $params_foto[] = $filename;
        } else {
            // Jika gagal upload, siapkan pesan error
            $status_msg = 'gagal_upload';
        }
    }
    // --- Kurung kurawal '}' ekstra sudah dihapus dari sini ---

    $koneksi->begin_transaction();
    try {
        // --- UPDATE TABEL data_santri ---
        $sql_update_santri = "UPDATE data_santri SET 
                                nama_lengkap = ?, nik = ?, no_kk = ?, jenis_kelamin = ?, 
                                tempat_lahir = ?, tanggal_lahir = ?, alamat = ?, no_hp = ?,
                                nama_ayah = ?, pekerjaan_ayah = ?, nama_ibu = ?,
                                pekerjaan_ibu = ?, gaji_per_bulan = ?
                                $foto_sql_part 
                              WHERE id = ?";
        
        // --- INI ADALAH BAGIAN YANG DIPERBAIKI (LOGIKA BIND_PARAM) ---
        
        // 1. Tipe data untuk 13 field pertama (semua string 's')
        $types = "sssssssssssss"; // 13 's'
        
        // 2. Siapkan 13 parameter pertama
        $params = [
            $nama_lengkap, $nik, $no_kk, $jenis_kelamin, 
            $tempat_lahir, $tanggal_lahir, $alamat, $no_hp,
            $nama_ayah, $pekerjaan_ayah, $nama_ibu,
            $pekerjaan_ibu, $gaji_per_bulan
        ];
        
        // 3. Tambahkan tipe & param FOTO (jika ada)
        if (!empty($params_foto)) {
            $types .= "s"; // Tambah 's' untuk foto
            $params = array_merge($params, $params_foto); // Tambah nama file foto ke array
        }
        
        // 4. Tambahkan tipe & param ID (di paling akhir)
        $types .= "i"; // Tambah 'i' untuk ID
        $params[] = $id_santri_db; // Tambah nilai ID ke array
        
        // --- AKHIR PERBAIKAN ---

        $stmt_update = $koneksi->prepare($sql_update_santri);
        $stmt_update->bind_param($types, ...$params);
        $stmt_update->execute();
        $stmt_update->close();
        
        // --- UPDATE TABEL users (untuk email) ---
        $stmt_update_user = $koneksi->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt_update_user->bind_param("si", $email, $user_id_login);
        $stmt_update_user->execute();
        $stmt_update_user->close();

        $koneksi->commit();
        
        // Update session
        $_SESSION['nama_lengkap'] = $nama_lengkap;
        
        // Redirect kembali ke halaman view
        header("Location: biodata_santri.php?status=sukses");
        exit();

    } catch (Exception $e) {
        $koneksi->rollback();
        // Redirect kembali ke halaman edit dengan pesan error
        header("Location: edit_biodata_santri.php?status=gagal_db&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// --- 3. AMBIL DATA UNTUK TAMPILKAN DI FORM ---
if(isset($_GET['status'])) {
    if($_GET['status'] == 'gagal_db') {
        $status_msg = "<div class='alert alert-danger'>Gagal menyimpan ke database. Silakan coba lagi.<br><small>(" . htmlspecialchars($_GET['error'] ?? '') . ")</small></div>";
    } elseif ($_GET['status'] == 'gagal_upload') {
        $status_msg = "<div class='alert alert-danger'>Gagal mengupload foto. Periksa izin folder 'uploads'.</div>";
    }
}

$sql = "SELECT s.*, u.email
        FROM data_santri s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?
        LIMIT 1";
        
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $user_id_login);
$stmt->execute();
$result = $stmt->get_result();
$santri_data = $result->fetch_assoc();
$stmt->close();
$koneksi->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Biodata Santri</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* (CSS Anda dari sebelumnya sudah benar dan responsif) */
        :root {
            --warna-hijau: #00a86b;
            --warna-latar: #f4f7f6;
            --warna-teks: #333333;
        }
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #333;
        }
        .biodata-container {
            width: 100%;
            max-width: 420px;
            min-height: 100vh;
            margin: 0 auto;
            background-color: var(--warna-latar);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-left: 5px solid #1a1a1a;
            border-right: 5px solid #1a1a1a;
        }
        .profile-header {
            background-color: var(--warna-hijau);
            color: white;
            padding: 30px 20px 40px 20px;
            text-align: center;
            position: relative;
        }
        .back-btn {
            position: absolute;
            top: 20px;
            left: 15px;
            text-decoration: none;
            color: white;
            font-size: 1.2rem;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .profile-pic-edit {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 20px auto 15px auto;
        }
        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            background-color: #333;
            overflow: hidden;
        }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-pic .icon { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #ccc; }
        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 35px;
            height: 35px;
            background-color: white;
            color: var(--warna-hijau);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            border: 2px solid var(--warna-hijau);
        }
        #foto-upload { display: none; }
        #file-name-display {
            font-size: 0.8rem;
            color: white;
            margin-top: 5px;
            font-style: italic;
        }
        .profile-header h2 { margin: 0; font-size: 1.2rem; font-weight: 600; }
        .nis-badge { display: inline-block; background-color: white; color: var(--warna-hijau); padding: 8px 25px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; margin-top: 10px; }
        .profile-content { background-color: var(--warna-latar); padding: 25px 20px; flex-grow: 1; border-radius: 30px 30px 0 0; margin-top: -25px; z-index: 10; }
        .card { background-color: white; border: 2px solid #ddd; border-radius: 15px; margin-bottom: 25px; overflow: hidden; }
        .card h3 { margin: 0; padding: 12px 20px; color: var(--warna-teks); font-size: 1.2rem; font-weight: 700; }
        .card-body { padding: 15px 20px; border-top: 2px solid #ddd; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--warna-teks-abu); margin-bottom: 5px; }
        .form-group.required label::after {
            content: ' *';
            color: #dc3545;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .btn-save {
            background-color: var(--warna-hijau);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-save:hover { background-color: #008a5a; }
        .alert { padding: 15px; border-radius: 8px; font-weight: 500; margin-bottom: 20px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px 20px;
        }
        @media (min-width: 960px) {
            .biodata-container {
                max-width: 960px; 
                margin-top: 40px;
                margin-bottom: 40px;
                border-radius: 15px;
            }
            .profile-header { border-radius: 15px 15px 0 0; }
            .profile-content { border-radius: 0 0 15px 15px; }
            .form-grid { grid-template-columns: 1fr 1fr; }
            .form-group.full-width { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>

    <div class="biodata-container">
        
        <form method="POST" enctype="multipart/form-data" id="edit-form">

            <header class="profile-header">
                <a href="biodata_santri.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                
                <div class="profile-pic-edit">
                    <label for="foto-upload">
                        <div class="profile-pic">
                            <?php if (!empty($santri_data['foto'])): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($santri_data['foto']); ?>" alt="Foto Profil" id="profile-image-preview">
                            <?php else: ?>
                                <div class="icon" id="profile-icon-preview"><i class="fas fa-user"></i></div>
                                <img src="" alt="Foto Profil" id="profile-image-preview" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <div class="upload-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </label>
                    <input type="file" id="foto-upload" name="foto" accept="image/*">
                    <div id="file-name-display"></div>
                </div>
                
                <h2><?php echo htmlspecialchars($santri_data['nama_lengkap']); ?></h2>
                <div class="nis-badge">
                    ID = <?php echo htmlspecialchars($santri_data['nis']); ?>
                </div>
            </header>

            <main class="profile-content">
                <?php echo $status_msg; ?>
                
                <div class="card">
                    <h3>DATA DIRI</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nik">NIK</label>
                                <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($santri_data['nik']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="no_kk">No. KK</label>
                                <input type="text" id="no_kk" name="no_kk" value="<?php echo htmlspecialchars($santri_data['no_kk']); ?>">
                            </div>
                            <div class="form-group required">
                                <label for="nama_lengkap">Nama Lengkap</labe >
                                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($santri_data['nama_lengkap']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="jenis_kelamin">Jenis Kelamin</label>
                                <select id="jenis_kelamin" name="jenis_kelamin">
                                    <option value="Laki-laki" <?php if($santri_data['jenis_kelamin'] == 'Laki-laki') echo 'selected'; ?>>Laki-laki</option>
                                    <option value="Perempuan" <?php if($santri_data['jenis_kelamin'] == 'Perempuan') echo 'selected'; ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tempat_lahir">Tempat Lahir</label>
                                <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($santri_data['tempat_lahir']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="tanggal_lahir">Tanggal Lahir</label>
                                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($santri_data['tanggal_lahir']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="no_hp">No. Hp</label>
                                <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($santri_data['no_hp']); ?>">
                            </div>
                            <div class="form-group required">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($santri_data['email']); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="alamat">Alamat</label>
                                <textarea id="alamat" name="alamat"><?php echo htmlspecialchars($santri_data['alamat']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>DATA ORANGTUA</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nama_ayah">Nama Ayah</label>
                                <input type="text" id="nama_ayah" name="nama_ayah" value="<?php echo htmlspecialchars($santri_data['nama_ayah']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="pekerjaan_ayah">Pekerjaan Ayah</label>
                                <input type="text" id="pekerjaan_ayah" name="pekerjaan_ayah" value="<?php echo htmlspecialchars($santri_data['pekerjaan_ayah']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="nama_ibu">Nama Ibu</label>
                                <input type="text" id="nama_ibu" name="nama_ibu" value="<?php echo htmlspecialchars($santri_data['nama_ibu']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="pekerjaan_ibu">Pekerjaan Ibu</label>
                                <input type="text" id="pekerjaan_ibu" name="pekerjaan_ibu" value="<?php echo htmlspecialchars($santri_data['pekerjaan_ibu']); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="gaji_per_bulan">Total Gaji Per Bulan</label>
                                <input type="text" id="gaji_per_bulan" name="gaji_per_bulan" value="<?php echo htmlspecialchars($santri_data['gaji_per_bulan']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">Save</button>
            </main>

        </form>
    </div>
    
    <script>
        // --- 1. Script untuk preview foto & nama file ---
        document.getElementById('foto-upload').onchange = function(event) {
            var reader = new FileReader();
            var file = event.target.files[0];
            var fileNameDisplay = document.getElementById('file-name-display');
            if(file) {
                fileNameDisplay.textContent = file.name;
            } else {
                fileNameDisplay.textContent = '';
            }
            reader.onload = function(){
                var output = document.getElementById('profile-image-preview');
                var icon = document.getElementById('profile-icon-preview');
                output.src = reader.result;
                output.style.display = 'block';
                if(icon) icon.style.display = 'none';
            };
            reader.readAsDataURL(file);
        };

        // --- 2. Script Validasi & Pop-up Loading ---
        const editForm = document.getElementById('edit-form');
        
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const nama = document.getElementById('nama_lengkap').value;
            const email = document.getElementById('email').value;
            
            if (nama.trim() === '' || email.trim() === '') {
                Swal.fire({
                    title: 'Oops...',
                    text: 'Nama Lengkap dan Email wajib diisi!',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            Swal.fire({
                title: 'Menyimpan...',
                text: 'Mohon tunggu sebentar.',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            editForm.submit();
        });
    </script>
</body>
</html>