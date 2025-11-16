<?php
$host = 'localhost';      // Server database Anda
$user = 'root';           // Username database
$pass = '';               // Password database
$db   = 'tpq_raport';     // Nama database yang Anda buat

$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi ke database gagal: " . $koneksi->connect_error);
}

// Mengatur timezone default, penting untuk fungsi tanggal
date_default_timezone_set('Asia/Jakarta');
?>