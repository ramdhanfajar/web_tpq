<?php
session_start();
require '../koneksi.php'; // Path sudah benar (naik 1 level)

// --- 1. KEAMANAN & AMBIL DATA ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'santri') {
    header("Location: ../login.php");
    exit();
}

// Cek status dari URL (untuk pop-up sukses)
$status_pop_up = $_GET['status'] ?? '';

$user_id_login = $_SESSION['user_id'];
$santri_data = [];

$sql = "SELECT s.*, u.email
        FROM data_santri s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?
        LIMIT 1";
        
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $user_id_login);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $santri_data = $result->fetch_assoc();
    $_SESSION['nama_lengkap'] = $santri_data['nama_lengkap'];
} else {
    header("Location: dashboard_santri.php");
    exit();
}
$stmt->close();
$koneksi->close();

// Helper function
function tampilkanData($data) {
    return htmlspecialchars(!empty($data) ? $data : '-');
}

// Format tanggal
$tanggal_lahir_formatted = '-';
if (!empty($santri_data['tanggal_lahir'])) {
    $timestamp = strtotime($santri_data['tanggal_lahir']);
    $tanggal_lahir_formatted = date('d-m-Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biodata Santri</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* (Salin CSS dari biodata_pengajar.php) */
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
        .back-btn, .edit-btn {
            position: absolute;
            top: 20px;
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
        .back-btn { left: 15px; }
        .edit-btn { 
            right: 15px; 
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 20px;
            width: auto;
            height: auto;
            padding: 8px 15px;
        }
        .edit-btn i { margin-right: 5px; }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 20px auto 15px auto;
            border: 4px solid rgba(255, 255, 255, 0.3);
            background-color: #333;
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
            font-size: 3rem;
            color: #ccc;
        }

        .profile-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .nis-badge {
            display: inline-block;
            background-color: white;
            color: var(--warna-hijau);
            padding: 8px 25px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .profile-content {
            background-color: var(--warna-latar);
            padding: 25px 20px;
            flex-grow: 1;
            border-radius: 30px 30px 0 0;
            margin-top: -25px;
            z-index: 10;
        }
        .card {
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 15px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .card h3 {
            margin: 0;
            padding: 12px 20px;
            color: var(--warna-teks);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .card-body {
            padding: 0px 20px 15px 20px;
            border-top: 2px solid #ddd;
        }
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 2px solid var(--warna-hijau);
            border-radius: 10px;
            padding: 10px;
        }
        .info-list li {
            display: flex;
            padding: 8px 5px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .info-list li span {
            color: var(--warna-teks-abu);
            flex-basis: 35%;
            flex-shrink: 0;
        }
        .info-list li strong {
            color: var(--warna-teks);
            flex-basis: 65%;
            word-break: break-word;
        }
        
        /* CSS Responsif */
        @media (min-width: 960px) {
            .biodata-container {
                max-width: 960px; 
                margin-top: 40px;
                margin-bottom: 40px;
                border-radius: 15px;
            }
            .profile-header {
                border-radius: 15px 15px 0 0;
            }
            .profile-content {
                border-radius: 0 0 15px 15px;
            }
            /* Grid untuk 2 kartu */
            .card-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
            }
            .card {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>

    <div class="biodata-container">
        
        <header class="profile-header">
            <a href="dashboard_santri.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <a href="edit_biodata.php" class="edit-btn">
                <i class="fas fa-pencil-alt"></i> Edit
            </a>

            <div class="profile-pic">
                <?php if (!empty($santri_data['foto'])): ?>

                    <img src="../uploads/<?php echo htmlspecialchars($santri_data['foto']); ?>" alt="Foto Profil">

                <?php else: ?>
                    <div class="icon"><i class="fas fa-user"></i></div>
                 <?php endif; ?>
            </div>
            
            <h2><?php echo tampilkanData($santri_data['nama_lengkap']); ?></h2>
            
            <div class="nis-badge">
                ID = <?php echo tampilkanData($santri_data['nis']); ?>
            </div>
        </header>

        <main class="profile-content">
            <div class="card-grid">
                <div class="card">
                    <h3>DATA DIRI</h3>
                    <div class="card-body">
                        <ul class="info-list">
                            <li><span>NIK</span> <strong>: <?php echo tampilkanData($santri_data['nik']); ?></strong></li>
                            <li><span>No. KK</span> <strong>: <?php echo tampilkanData($santri_data['no_kk']); ?></strong></li>
                            <li><span>Nama Lengkap</span> <strong>: <?php echo tampilkanData($santri_data['nama_lengkap']); ?></strong></li>
                            <li><span>Jenis Kelamin</span> <strong>: <?php echo tampilkanData($santri_data['jenis_kelamin']); ?></strong></li>
                            <li><span>Tempat Lahir</span> <strong>: <?php echo tampilkanData($santri_data['tempat_lahir']); ?></strong></li>
                            <li><span>Tanggal Lahir</span> <strong>: <?php echo $tanggal_lahir_formatted; ?></strong></li>
                            <li><span>Alamat</span> <strong>: <?php echo tampilkanData($santri_data['alamat']); ?></strong></li>
                            <li><span>No. Hp</span> <strong>: <?php echo tampilkanData($santri_data['no_hp']); ?></strong></li>
                            <li><span>Email</span> <strong>: <?php echo tampilkanData($santri_data['email']); ?></strong></li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <h3>DATA ORANGTUA</h3>
                    <div class="card-body">
                         <ul class="info-list">
                            <li><span>Nama Ayah</span> <strong>: <?php echo tampilkanData($santri_data['nama_ayah']); ?></strong></li>
                            <li><span>Pekerjaan Ayah</span> <strong>: <?php echo tampilkanData($santri_data['pekerjaan_ayah']); ?></strong></li>
                            <li><span>Nama Ibu</span> <strong>: <?php echo tampilkanData($santri_data['nama_ibu']); ?></strong></li>
                            <li><span>Pekerjaan Ibu</span> <strong>: <?php echo tampilkanData($santri_data['pekerjaan_ibu']); ?></strong></li>
                            <li><span>Total Gaji</span> <strong>: <?php echo tampilkanData($santri_data['gaji_per_bulan']); ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'sukses') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data biodata Anda telah diperbarui.',
                    icon: 'success',
                    confirmButtonColor: '#00a86b'
                });
            }
        });
    </script>
</body>
</html>