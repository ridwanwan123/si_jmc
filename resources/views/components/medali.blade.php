@props(['peringkat', 'ukuran' => 28])
{{--
    Medali juara 1/2/3: rosette bergerigi warna medali + angka peringkat + pita kecil.
    Gaya flat (tanpa gradien/bayangan) sesuai desain B. Dipakai di mana pun peringkat juara
    ditampilkan (juara per lomba, daftar juara madrasah, pencarian, ticker).
--}}
@php
    $p = (int) $peringkat;
    [$isi, $tepi] = match ($p) {
        1 => ['#E3AE2A', '#A97C0E'],   // emas
        2 => ['#C9CED6', '#7F8792'],   // perak
        default => ['#C98A55', '#8E5023'], // perunggu
    };
    $label = match ($p) { 1 => 'Emas', 2 => 'Perak', default => 'Perunggu' };

    // Tepi bergerigi: 18 gigi di sekitar titik pusat (16, 15).
    $titik = [];
    for ($i = 0; $i < 36; $i++) {
        $r = $i % 2 === 0 ? 14 : 12.4;
        $a = deg2rad($i * 10 - 90);
        $titik[] = round(16 + $r * cos($a), 2).','.round(15 + $r * sin($a), 2);
    }
    $gerigi = implode(' ', $titik);
    $tinggi = round($ukuran * 40 / 32);
@endphp
<svg {{ $attributes->class(['shrink-0']) }} width="{{ $ukuran }}" height="{{ $tinggi }}" viewBox="0 0 32 40"
     role="img" aria-label="Juara {{ $p }} ({{ $label }})" xmlns="http://www.w3.org/2000/svg">
    {{-- pita --}}
    <path d="M10.5 22 L6 38 L9.6 35.6 L12.4 38.8 L15.4 25.5 Z" fill="{{ $tepi }}"/>
    <path d="M21.5 22 L26 38 L22.4 35.6 L19.6 38.8 L16.6 25.5 Z" fill="{{ $tepi }}"/>
    {{-- rosette --}}
    <polygon points="{{ $gerigi }}" fill="{{ $isi }}" stroke="{{ $tepi }}" stroke-width="1" stroke-linejoin="round"/>
    <circle cx="16" cy="15" r="9.6" fill="none" stroke="#FFFFFF" stroke-opacity=".7" stroke-width="1.2"/>
    <text x="16" y="15" dy=".36em" text-anchor="middle" fill="#0E0E10"
          font-family="'JetBrains Mono', ui-monospace, monospace" font-weight="800" font-size="14">{{ $p }}</text>
</svg>
