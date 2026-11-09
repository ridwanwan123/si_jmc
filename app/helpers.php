<?php

use App\Support\EdisiTerpilih;

if (! function_exists('rute')) {
    /**
     * route() untuk halaman publik yang membawa ?edisi= saat melihat arsip.
     */
    function rute(string $name, mixed $params = []): string
    {
        return app(EdisiTerpilih::class)->route($name, is_array($params) ? $params : [$params]);
    }
}

if (! function_exists('nomor')) {
    /** Angka dua digit ala papan skor: 7 → "07". */
    function nomor(int $n): string
    {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
