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
        'cari' =>'M10.5 4a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13M15.5 15.5L21 21',
        default => 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18',
    };
@endphp
@php $adaUkuran = str_contains((string) $attributes->get('class'), 'size-'); @endphp
<svg {{ $attributes->class(['shrink-0', 'size-5' => ! $adaUkuran]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
