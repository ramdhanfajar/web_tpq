<?php
echo "<h1>Tes Verifikasi Password</h1>";

// --- SALIN HASH ANDA DARI DATABASE ---
// 1. Buka phpMyAdmin, tabel 'users'.
// 2. Salin isi kolom 'password' untuk user 'santri@tpq.com'.
// 3. Tempel di bawah ini, di dalam tanda kutip.
$hash_dari_database = '$2y$10$fA1N.6uF.5jT/P2s/5.YFeGjA.pM.jS4Q/T14u6m2jY8L.3P9s5om';
// -------------------------------------

$password_yang_diketik = '12345';

echo "<p><b>Password yang diketik:</b> " . $password_yang_diketik . "</p>";
echo "<p><b>Hash dari Database:</b> " . $hash_dari_database . "</p>";

if (password_verify($password_yang_diketik, $hash_dari_database)) {
    echo '<h2 style="color: green;">SUKSES! Password Cocok.</h2>';
    echo '<p>Ini artinya, masalah Anda 100% ada di file <b>proses_login.php</b>. Periksa kembali variabel $_POST Anda.</p>';
} else {
    echo '<h2 style="color: red;">GAGAL! Password TIDAK Cocok.</h2>';
    echo '<p>Ini artinya, masalah Anda 100% ada di <b>database</b>. Hash yang Anda salin di atas salah atau terpotong. Cek lagi Langkah 1 (pastikan VARCHAR(255)) dan masukkan ulang hash-nya.</p>';
}
?>