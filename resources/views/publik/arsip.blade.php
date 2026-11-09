<x-layouts.publik judul="Arsip Edisi">
    <main class="wadah pb-18 pt-10">
        <div class="label-mono mb-2 text-xs">JAKARTA MADRASAH COMPETITION · SEMUA EDISI</div>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h1 class="judul-halaman">Arsip <span class="text-accent">Edisi</span></h1>
            <span class="font-mono text-[13px] font-bold text-muted">{{ $daftar->count() }} EDISI</span>
        </div>

        <div class="border-t-2 border-ink">
            @foreach ($daftar as $d)
                @php
                    $e = $d['edisi'];
                    $r = $d['ringkasan'];
                    $tanggal = collect([
                        $e->tanggal_mulai?->translatedFormat($e->tanggal_selesai && $e->tanggal_mulai->isSameMonth($e->tanggal_selesai) ? 'j' : 'j F'),
                        $e->tanggal_selesai?->translatedFormat('j F Y'),
                    ])->filter()->implode('–');
                @endphp
                <article class="grid gap-x-8 gap-y-5 border-b border-line py-7 lg:grid-cols-[160px_minmax(0,1fr)_minmax(0,1.1fr)_auto] lg:items-center">
                    {{-- Tahun --}}
                    <div class="flex items-center gap-3 lg:block">
                        <div class="font-angka text-[44px] {{ $e->is($edisi) ? 'text-accent' : '' }}">{{ $e->tahun }}</div>
                        <div class="flex flex-wrap gap-1.5 lg:mt-2">
                            @if ($e->is_aktif)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-ink px-2.5 py-1 font-mono text-[11px] font-bold text-white">
                                    @if ($e->status() === 'BERLANGSUNG')<span class="size-1.5 rounded-full bg-live"></span>@endif AKTIF
                                </span>
                            @endif
                            @if ((int) $e->lomba_count === 0)
                                <span class="rounded-full border border-pill-line px-2.5 py-1 font-mono text-[11px] font-bold text-muted">BELUM ADA DATA</span>
                            @elseif ($e->selesai())
                                <span class="rounded-full border border-pill-line px-2.5 py-1 font-mono text-[11px] font-bold text-muted">HASIL AKHIR</span>
                            @endif
                        </div>
                    </div>

                    {{-- Nama, tanggal, angka --}}
                    <div class="min-w-0">
                        <h2 class="text-xl font-extrabold">{{ $e->nama }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ collect([$tanggal, $e->lokasi])->filter()->implode(' · ') ?: '—' }}</p>
                        <dl class="mt-3 grid grid-cols-3 gap-3 font-mono text-sm">
                            <div><dt class="text-[11px] font-bold text-muted">LOMBA</dt><dd class="text-lg font-bold">{{ $r['jumlah_lomba'] }}</dd></div>
                            <div><dt class="text-[11px] font-bold text-muted">MEDALI</dt><dd class="text-lg font-bold">{{ $r['total_medali'] }}</dd></div>
                            <div><dt class="text-[11px] font-bold text-muted">MADRASAH</dt><dd class="text-lg font-bold">{{ $r['madrasah_peserta'] ?: $r['madrasah_peraih'] }}</dd></div>
                        </dl>
                    </div>

                    {{-- Juara umum --}}
                    <div class="min-w-0">
                        <div class="font-mono text-[11px] font-bold text-muted">{{ $e->selesai() ? 'JUARA UMUM' : 'PEMUNCAK SEMENTARA' }}</div>
                        @forelse ($d['juaraUmum'] as $j)
                            <a href="{{ route('madrasah.show', ['madrasah' => $j->nsm] + $d['param']) }}" class="mt-1.5 flex items-center gap-2.5 text-ink no-underline">
                                <x-medali :peringkat="1" :ukuran="20" />
                                <span class="min-w-0">
                                    <span class="block truncate font-bold">{{ $j->nama }}</span>
                                    <span class="block font-mono text-xs text-muted">{{ $j->emas }} E · {{ $j->perak }} P · {{ $j->perunggu }} P</span>
                                </span>
                            </a>
                        @empty
                            <p class="mt-1.5 text-sm text-muted">Belum ada hasil.</p>
                        @endforelse
                    </div>

                    {{-- Link --}}
                    <div class="flex flex-wrap gap-2 lg:flex-col lg:items-end">
                        <a href="{{ route('klasemen', $d['param']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-ink px-5 text-sm font-bold text-white no-underline hover:text-white">
                            Klasemen <x-ikon nama="panah-kanan-atas" class="size-4" stroke="2.5" />
                        </a>
                        <a href="{{ route('beranda', $d['param']) }}" class="inline-flex min-h-11 items-center rounded-full border border-ink px-5 text-sm font-bold text-ink no-underline">Beranda edisi</a>
                    </div>
                </article>
            @endforeach
        </div>
    </main>
</x-layouts.publik>
