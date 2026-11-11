@props(['nama', 'stroke' => '2'])
@php
    // Ikon garis (stroke). Path cabor diambil dari mockup docs/mockup/source/CaborB.dc.html.
    $path = match ($nama) {
        // cabor (kolom cabor.ikon)
        'bulan' => 'M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z',
        'tabung' => 'M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3M7.5 15h9',
        'bola' => 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18M12 3c3 3 3 15 0 18M3 12h18',
        'palet' => 'M12 3a9 9 0 1 0 0 18c1.2 0 1.8-1 1.3-2l-.4-.9a1.6 1.6 0 0 1 1.5-2.3H17a4 4 0 0 0 4-4c0-4.9-4-8.8-9-8.8zM7.5 11h.01M11 7h.01M15.5 8h.01',
        'kaca-pembesar' => 'M10.5 4a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13M15.5 15.5L21 21',
        'robot' => 'M7.5 8h9a2.5 2.5 0 0 1 2.5 2.5v6a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 16.5v-6A2.5 2.5 0 0 1 7.5 8zM12 8V4M9.5 13h.01M14.5 13h.01M9.5 16.5h5',
        'toga' => 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5',
        // antarmuka
        'panah-kanan-atas' => 'M7 17L17 7M9 7h8v8',
        'chevron-bawah' => 'M6 9l6 6 6-6',
        'chevron-kiri' => 'M15 6l-6 6 6 6',
        'chevron-kanan' => 'M9 6l6 6-6 6',
        'panah-kiri' => 'M19 12H5M11 6l-6 6 6 6',
        'tutup' => 'M6 6l12 12M18 6L6 18',
        'kamera' => 'M4 8h3l2-3h6l2 3h3v11H4zM12 10.5a3.5 3.5 0 1 0 0 7a3.5 3.5 0 1 0 0-7',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M5 12h14',
        'panah-kanan' => 'M5 12h14M13 6l6 6-6 6',
        'centang' => 'M5 12.5l4.5 4.5L19 7.5',
        'orang' => 'M12 4a4 4 0 1 0 0 8a4 4 0 1 0 0-8M4.5 20a7.5 7.5 0 0 1 15 0',
        'orang-dua' => 'M9 4.5a3.5 3.5 0 1 0 0 7a3.5 3.5 0 1 0 0-7M2.5 19.5a6.5 6.5 0 0 1 13 0M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14a6.5 6.5 0 0 1 3.5 5.5',
        'gambar' => 'M4 5h16v14H4zM4 16l4.5-4.5 4 4 2.5-2.5L20 18M15 9.5h.01',
        'tautan' => 'M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1',
        'pesan' => 'M4 20l1.5-4.2A8 8 0 1 1 8.4 18.6z',
        'instagram' => 'M7.5 3h9A4.5 4.5 0 0 1 21 7.5v9a4.5 4.5 0 0 1-4.5 4.5h-9A4.5 4.5 0 0 1 3 16.5v-9A4.5 4.5 0 0 1 7.5 3zM12 8.2a3.8 3.8 0 1 0 0 7.6a3.8 3.8 0 1 0 0-7.6M17.2 6.8h.01',
        'youtube' => 'M3.5 8.2c.2-1.6 1.4-2.8 3-2.9C8.4 5.1 10.2 5 12 5s3.6.1 5.5.3c1.6.1 2.8 1.3 3 2.9a24 24 0 0 1 0 7.6c-.2 1.6-1.4 2.8-3 2.9-1.9.2-3.7.3-5.5.3s-3.6-.1-5.5-.3c-1.6-.1-2.8-1.3-3-2.9a24 24 0 0 1 0-7.6zM10 9.2v5.6l4.8-2.8z',
        'lokasi' => 'M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 1 1 13 0c0 5.4-6.5 11-6.5 11zM12 7.5a2.5 2.5 0 1 0 0 5a2.5 2.5 0 1 0 0-5',
        'cari' =>'M10.5 4a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13M15.5 15.5L21 21',
        default => 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18',
    };
@endphp
@php $adaUkuran = str_contains((string) $attributes->get('class'), 'size-'); @endphp
<svg {{ $attributes->class(['shrink-0', 'size-5' => ! $adaUkuran]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
