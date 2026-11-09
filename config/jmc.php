<?php

return [

    /*
    | Jenjang madrasah yang ikut klasemen umum (Keputusan #3 di CLAUDE.md).
    | Hapus 'RA' dari daftar ini jika RA kelak dipisah dari klasemen umum.
    */
    'jenjang_klasemen_umum' => ['RA', 'MI', 'MTs', 'MA'],

    // Format tampilan "Terakhir diperbarui", mis. 05.11.2026 · 11:30 WIB
    'format_waktu' => 'd.m.Y · H:i',

    'timezone' => 'Asia/Jakarta',

    // Pendaftaran peserta.
    'pendaftaran' => [
        // Kontak panitia di halaman Daftar, mis. "0812-xxxx-xxxx (Panitia JMC)".
        'kontak_panitia' => env('JMC_KONTAK_PANITIA'),
    ],

    // Akun admin awal (dipakai AdminSeeder).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin JMC'),
        'email' => env('ADMIN_EMAIL', 'admin@jmc.test'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
