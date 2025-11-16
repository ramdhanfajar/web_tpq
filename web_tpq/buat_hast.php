<?php
// File ini akan membuat hash baru untuk password '12345'

$password_baru = '12345';
$hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);

echo "<h1>Hash Baru Anda</h1>";
echo "<p>Password: " . $password_baru . "</p>";
echo "<p>Salin semua teks di bawah ini persis apa adanya:</p>";
echo "<h2>" . $hash_baru . "</h2>";

?>