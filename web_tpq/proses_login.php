<?php
session_start();
require 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = $_POST['email'];
    $password_input = $_POST['password'];

    $sql = "SELECT id, email, password, role FROM users WHERE email = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // -- SEKARANG KITA BISA LANGSUNG TUTUP STATEMENT SETELAH MENDAPAT HASIL --
    $stmt->close();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password_input, $user['password'])) {
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Tutup koneksi SEBELUM redirect
            $koneksi->close();

            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard_admin.php");
                    break;
                case 'pengajar':
                    header("Location: pengajar/dashboard_pengajar.php");
                    break;
                case 'santri':
                    header("Location: santri/dashboard_santri.php");
                    break;
                default:
                    header("Location: login.php?error=unknownrole");
                    break;
            }
            exit();

        } else {
            // Password salah
            $koneksi->close(); // Tutup koneksi
            header("Location: login.php?error=wrongpass");
            exit();
        }
    } else {
        // Email tidak ditemukan
        $koneksi->close(); // Tutup koneksi
        header("Location: login.php?error=notfound");
        exit();
    }

} else {
    header("Location: login.php");
    exit();
}
// Baris $stmt->close() dan $koneksi->close() sudah dihapus dari sini
?>