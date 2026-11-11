@props(['berita'])
{{-- Pita hitam pengumuman yang disematkan (halaman berita). --}}
@if ($berita)
    <div class="bg-ink text-white">
        <div class="wadah flex min-h-14 flex-wrap items-center gap-x-4 gap-y-1 py-3">
            <span class="flex items-center gap-2 font-mono text-[11px] font-bold text-live">
                <span class="size-2 rounded-full bg-live" aria-hidden="true"></span>PENGUMUMAN
            </span>
            <p class="min-w-0 flex-1 text-sm">{{ $berita->judul }}</p>
            <a href="{{ rute('berita.show', $berita->slug) }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-[#8FB0FF] no-underline hover:text-white">
                Baca <x-ikon nama="panah-kanan" class="size-4" />
            </a>
        </div>
    </div>
@endif
