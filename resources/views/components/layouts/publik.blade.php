@props(['judul' => null, 'deskripsi' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ? $judul.' · ' : '' }}JMC {{ $edisi->tahun }} — Jakarta Madrasah Competition</title>
    <meta name="description" content="{{ $deskripsi ?? 'Hasil Jakarta Madrasah Competition (JMC): klasemen medali, cabor dan lomba, juara, dan profil madrasah DKI Jakarta.' }}">
    <link rel="canonical" href="{{ request()->fullUrlWithoutQuery(['page']) }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,500..900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-ink focus:px-4 focus:py-2 focus:text-white">Lewati ke konten</a>

    @php
        $menu = [
            ['Beranda', 'beranda', 'beranda'],
            ['Klasemen', 'klasemen', 'klasemen'],
            ['Cabor', 'cabor.index', 'cabor.*'],
            ['Berita', 'berita.index', 'berita.*'],
            ['Madrasah', 'madrasah.index', 'madrasah.*'],
            ['Galeri', 'galeri.index', 'galeri.*'],
        ];
    @endphp

    <header class="border-b border-line">
        <div class="wadah flex flex-wrap items-center gap-x-10 gap-y-3 py-3.5">
            <a href="{{ rute('beranda') }}" class="flex items-center gap-2.5 text-ink no-underline hover:text-ink">
                <img src="{{ asset('images/logo-ikon.png') }}" alt="Logo JMC" class="h-10 w-[38px] object-contain">
                <span class="font-judul text-xl font-black tracking-[-0.5px]" style="font-stretch:125%">JMC</span>
            </a>

            <nav aria-label="Menu utama" class="order-3 flex w-full flex-wrap md:order-none md:w-auto md:flex-auto gap-x-4 gap-y-1 text-sm font-semibold sm:gap-x-7 sm:text-[15px]">
                @foreach ($menu as [$label, $nama, $pola])
                    @php $aktif = request()->routeIs($pola); @endphp
                    @if (Route::has($nama))
                        <a href="{{ rute($nama) }}"
                           @if ($aktif) aria-current="page" @endif
                           class="flex min-h-11 items-center border-b-2 no-underline {{ $aktif ? 'border-ink text-ink' : 'border-transparent text-muted' }}">{{ $label }}</a>
                    @else
                        <span class="flex min-h-11 items-center border-b-2 border-transparent text-muted/60" title="Segera hadir">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>

            <div class="order-2 ml-auto flex items-center gap-3 md:order-none md:gap-8">
            <a href="{{ route('daftar') }}" @if (request()->routeIs('daftar')) aria-current="page" @endif
               class="inline-flex min-h-11 items-center rounded-full bg-accent px-4.5 text-sm font-bold text-white no-underline hover:bg-accent-tua hover:text-white">Daftar</a>

            {{-- Pilihan edisi --}}
            <div x-data="{ buka: false }" class="relative" @keydown.escape.window="buka = false" @click.outside="buka = false">
                <button type="button" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="true" aria-label="Pilih edisi, saat ini {{ $edisi->tahun }}"
                        class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-ink px-4 font-mono text-[13px] font-bold">
                    {{ $edisi->tahun }}
                    <x-ikon nama="chevron-bawah" class="size-3.5" stroke="2.5" />
                </button>
                <div x-cloak x-show="buka" x-transition.opacity
                     class="absolute right-0 z-30 mt-2 min-w-44 overflow-hidden rounded-xl border border-ink bg-white py-1">
                    @foreach ($edisiTerpilih->pilihan() as $e)
                        <a href="{{ $edisiTerpilih->urlUntuk($e) }}"
                           class="flex min-h-11 items-center justify-between gap-4 px-4 font-mono text-[13px] font-bold no-underline {{ $e->is($edisi) ? 'bg-ink text-white hover:text-white' : 'text-ink' }}">
                            {{ $e->nama }}
                            @if ($e->is_aktif)
                                <span class="text-[11px] {{ $e->is($edisi) ? 'text-live' : 'text-accent' }}">AKTIF</span>
                            @elseif ((int) $e->lomba_count === 0)
                                <span class="text-[11px] font-medium {{ $e->is($edisi) ? 'text-white/70' : 'text-muted' }}">BELUM ADA DATA</span>
                            @endif
                        </a>
                    @endforeach
                    <a href="{{ route('arsip') }}" class="flex min-h-11 items-center border-t border-line px-4 text-sm font-bold text-accent no-underline">Semua edisi →</a>
                </div>
            </div>
            </div>
        </div>
    </header>

    @if ($edisiTerpilih->arsip())
        <div class="border-b border-line bg-pill">
            <div class="wadah flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                <span><span class="label-mono text-ink">ARSIP</span> · Anda melihat hasil {{ $edisi->nama }}.</span>
                <a href="{{ $edisiTerpilih->urlUntuk($edisiTerpilih->semua()->firstWhere('is_aktif', true) ?? $edisi) }}" class="font-bold text-accent">Ke edisi aktif →</a>
            </div>
        </div>
    @endif

    @if ($edisiTerpilih->arsip() && $edisiTerpilih->kosong())
        <div class="border-b border-line">
            <div class="wadah py-6">
                <p class="text-[15px]"><span class="label-mono text-ink">BELUM ADA DATA</span> · Belum ada data lomba untuk {{ $edisi->nama }}. Hasil akan tampil di sini setelah diinput panitia.</p>
                <a href="{{ route('arsip') }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-bold text-accent">Lihat edisi sebelumnya →</a>
            </div>
        </div>
    @endif

    <div id="konten" class="flex-1">
        {{ $slot }}
    </div>

    @php($penyelenggara = config('jmc.penyelenggara'))
    <footer class="mt-6 border-t-2 border-ink bg-white">
        <div class="wadah grid gap-10 py-12 md:grid-cols-2 lg:grid-cols-[1.4fr_0.8fr_1.2fr]">
            {{-- Identitas + penyelenggara --}}
            <div>
                <img src="{{ asset('images/logo-tulisan.png') }}" alt="JMC" class="h-10 w-auto">
                <p class="mt-4 max-w-sm text-[15px] leading-relaxed text-teks-2">
                    Jakarta Madrasah Competition: kompetisi tahunan siswa dan guru madrasah se-DKI Jakarta, jenjang RA, MI, MTs, dan MA.
                </p>
                <div class="label-mono mt-7 text-[11px]">DISELENGGARAKAN OLEH</div>
                <div class="mt-3 flex flex-wrap items-center gap-5">
                    <img src="{{ asset('images/kemenag.png') }}" alt="Kementerian Agama Republik Indonesia" class="h-14 w-auto">
                    <img src="{{ asset('images/penmad.png') }}" alt="Penmad Jakarta" class="h-11 w-auto">
                </div>
            </div>

            {{-- Tautan --}}
            <nav aria-label="Tautan footer">
                <div class="label-mono text-[11px]">JELAJAHI</div>
                <ul class="mt-3 grid grid-cols-2 gap-x-4 md:grid-cols-1">
                    @foreach ([...$menu, ['Daftar', 'daftar', 'daftar']] as [$label, $nama])
                        <li><a href="{{ $nama === 'daftar' ? route('daftar') : rute($nama) }}" class="inline-flex min-h-10 items-center text-[15px] font-semibold text-ink no-underline">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            {{-- Kontak --}}
            <div>
                <div class="label-mono text-[11px]">KONTAK</div>
                <p class="mt-3 text-[15px] font-bold text-ink">{{ $penyelenggara['nama'] }}</p>
                <p class="text-[15px] text-teks-2">{{ $penyelenggara['instansi'] }}</p>
                <p class="mt-3 flex gap-2 text-[15px] leading-relaxed text-teks-2">
                    <x-ikon nama="lokasi" class="mt-1 size-4" />
                    <span>{{ $penyelenggara['alamat'] }}</span>
                </p>
                <div class="mt-5 flex gap-2">
                    @foreach ([['youtube', 'YouTube', $penyelenggara['youtube']], ['instagram', 'Instagram', $penyelenggara['instagram']]] as [$ikon, $nama, $url])
                        @if ($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $nama }} {{ $penyelenggara['instansi'] }}"
                               class="grid size-11 place-items-center rounded-full border border-pill-line text-ink no-underline hover:border-ink hover:bg-ink hover:text-white">
                                <x-ikon :nama="$ikon" class="size-5" stroke="1.8" />
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wadah"><div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-line py-5 text-[13px] text-muted">
            <span>© {{ now()->year }} {{ $penyelenggara['nama'] }} · {{ $penyelenggara['instansi'] }}</span>
            <nav aria-label="Arsip edisi" class="flex flex-wrap items-center gap-x-2 font-mono text-[13px] font-bold">
                <a href="{{ route('arsip') }}" class="inline-flex min-h-11 items-center {{ request()->routeIs('arsip') ? 'text-ink underline underline-offset-4' : 'text-muted' }}">ARSIP</a>
                @foreach ($edisiTerpilih->pilihan() as $e)
                    <span aria-hidden="true">·</span>
                    <a href="{{ $edisiTerpilih->urlUntuk($e) }}"
                       class="inline-flex min-h-11 items-center {{ $e->is($edisi) ? 'text-ink underline underline-offset-4' : 'text-muted' }}">{{ $e->tahun }}</a>
                @endforeach
            </nav>
        </div></div>
    </footer>
</body>
</html>
