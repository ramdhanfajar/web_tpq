<?php
// Mulai session untuk cek apakah user sudah login
session_start();

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: dashboard_admin.php"); break;
        case 'pengajar': header("Location: dashboard_pengajar.php"); break;
        case 'santri': header("Location: dashboard_santri.php"); break;
    }
    exit();
}

// Ambil pesan error dari URL (jika ada)
$error_msg = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'wrongpass') {
        $error_msg = 'Password salah, silakan coba lagi.';
    } elseif ($_GET['error'] == 'notfound') {
        $error_msg = 'Email tidak terdaftar.';
    } elseif ($_GET['error'] == 'unknownrole') {
        $error_msg = 'Role pengguna tidak dikenal.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Raport TPQ</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .mobile-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border: 1px solid #eee;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .header-content {
            text-align: center;
            padding: 50px 30px 30px 30px;
            background-color: #ffffff;
            color: #00a86b;
        }
        .header-content h1 {
            font-size: 2.2em;
            font-weight: 700;
            margin: 0;
        }
        .header-content p {
            font-size: 1.1em;
            font-weight: 500;
            margin: 10px 0 0 0;
            line-height: 1.4;
        }
        .header-content .logo {
            width: 150px;
            height: 150px;
            margin-top: 25px;
            background-color: #f0f0f0;
            border-radius: 50%;
            object-fit: cover;
        }
        .login-container {
            background-color: #00a86b;
            color: white;
            padding: 40px 30px 50px 30px;
            border-radius: 45px 45px 0 0;
            text-align: center;
            margin-top: 20px;
            flex-grow: 1;
        }
        .login-container h2 {
            font-size: 2em;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 30px;
        }
        .login-form input,
        .login-form button {
            width: 100%;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 30px;
            font-size: 1em;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box; 
        }
        .login-form input {
            background-color: transparent;
            border: 2px solid white;
            color: white;
        }
        .login-form input::placeholder {
            color: rgba(255, 255, 255, 0.8);
            opacity: 1;
        }
        .login-form button {
            background-color: white;
            color: #00a86b;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .login-form button:hover {
            background-color: #f0f0f0;
        }
        .forgot-password {
            display: block;
            text-align: right;
            color: white;
            text-decoration: none;
            font-size: 0.9em;
            margin-top: -10px;
            margin-bottom: 25px;
            margin-right: 10px;
        }
        /* Ini untuk pesan error */
        .error-message {
            background-color: #ffcccc;
            color: #cc0000;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        
        <header class="header-content">
            <h1>Selamat Datang</h1>
            <p>Sistem Raport<br>Taman Pendidikan Al-Qur'an<br>Darul Hikmah</p>
            
            <img src="img/logo1.png" alt="Logo TPQ Darul Hikmah" class="logo">
        </header>

        <main class="login-container">
            <h2>Masuk</h2>
            
            <form action="proses_login.php" method="POST" class="login-form">
                
                <?php if (!empty($error_msg)): ?>
                    <div class="error-message"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <input type="email" name="email" placeholder="Masukan email" required>
                <input type="password" name="password" placeholder="Kata sandi" required>
                
                <a href="#" class="forgot-password">Lupa sandi?</a>
                
                <button type="submit">Masuk</button>
            </form>
        </main>

    </div>
</body>
</html>