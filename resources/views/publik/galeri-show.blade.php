<x-layouts.publik :judul="$album->judul.' · Galeri'">
    @php
        $fotos = $album->foto->map(fn ($f) => [
            'src' => $f->url(),
            'ket' => $f->keterangan,
        ])->values();
        $label = $album->labelCabor();
    @endphp

    <main class="wadah pb-18 pt-10"
          x-data="lightbox(@js($fotos))"
          @keydown.window="tombol($event)">
        <a href="{{ rute('galeri.index') }}" class="inline-flex min-h-11 items-center gap-2 font-mono text-xs font-bold text-ink no-underline">
            <x-ikon nama="panah-kiri" class="size-4" stroke="2.5" />SEMUA ALBUM
        </a>

        <div class="mb-6 mt-3 flex flex-wrap items-end justify-between gap-3 border-b-2 border-ink pb-5">
            <div>
                <div class="font-mono text-xs font-bold text-accent">
                    @if ($album->cabor)
                        <a href="{{ rute('cabor.show', $album->cabor->slug) }}" class="text-accent">{{ $label }}</a>
                    @else
                        {{ $label }}
                    @endif
                    @if ($album->tanggal) · {{ $album->tanggal->format('d.m.Y') }}@endif
                </div>
                <h1 class="mt-1.5 font-judul-tegas text-[clamp(28px,3.5vw,40px)] leading-[1.05] tracking-[-0.5px]">{{ $album->judul }}</h1>
            </div>
            <span class="font-mono text-[13px] font-bold text-muted">{{ $album->foto->count() }} FOTO</span>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($album->foto as $i => $f)
                <button type="button" @click="buka({{ $i }}, $el)" aria-label="Buka foto {{ $i + 1 }}{{ $f->keterangan ? ': '.$f->keterangan : '' }}"
                        class="relative block aspect-square cursor-zoom-in overflow-hidden rounded-md bg-placeholder">
                    <img src="{{ $f->thumbUrl() }}" alt="{{ $f->keterangan ?? '' }}" loading="lazy" decoding="async"
                         class="absolute inset-0 size-full object-cover transition-transform duration-300 hover:scale-[1.03]">
                </button>
            @endforeach
        </div>

        @if ($lainnya->isNotEmpty())
            <section class="mt-16">
                <h2 class="judul-seksi mb-6 text-[26px]">Album lain</h2>
                <div class="grid grid-cols-1 gap-x-5 gap-y-7 min-[480px]:grid-cols-2 lg:grid-cols-4">
                    @foreach ($lainnya as $a)
                        <x-kartu-album :album="$a" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- LIGHTBOX --}}
        <div x-cloak x-show="aktif !== null" x-transition.opacity
             role="dialog" aria-modal="true" aria-label="Foto {{ $album->judul }}"
             class="fixed inset-0 z-50 flex flex-col bg-ink text-white"
             @touchstart.passive="sentuhMulai($event)" @touchend.passive="sentuhSelesai($event)">
            <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <span class="font-mono text-xs font-bold text-[#A3A6AE]">
                    {{ $label }} · <span x-text="(aktif ?? 0) + 1"></span> / {{ $album->foto->count() }}
                </span>
                <button type="button" x-ref="tutup" @click="tutup()" aria-label="Tutup"
                        class="flex size-11 cursor-pointer items-center justify-center rounded-full border border-white/25 hover:bg-white/10">
                    <x-ikon nama="tutup" stroke="2.2" />
                </button>
            </div>

            <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-20">
                <button type="button" @click="mundur()" aria-label="Foto sebelumnya" x-show="fotos.length > 1"
                        class="absolute left-2 top-1/2 z-10 flex size-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-white/25 bg-ink/60 hover:bg-white/10 sm:left-6">
                    <x-ikon nama="chevron-kiri" stroke="2.2" />
                </button>
                <template x-if="aktif !== null">
                    <img :src="fotos[aktif].src" :alt="fotos[aktif].ket || ''" class="max-h-full max-w-full rounded-md object-contain">
                </template>
                <button type="button" @click="maju()" aria-label="Foto berikutnya" x-show="fotos.length > 1"
                        class="absolute right-2 top-1/2 z-10 flex size-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-white/25 bg-ink/60 hover:bg-white/10 sm:right-6">
                    <x-ikon nama="chevron-kanan" stroke="2.2" />
                </button>
            </div>

            <div class="px-4 py-5 text-center sm:px-6">
                <div class="font-bold">{{ $album->judul }}</div>
                <div class="mt-0.5 min-h-5 text-sm text-[#C9CBD1]" x-text="aktif !== null ? (fotos[aktif].ket || '') : ''"></div>
            </div>
        </div>
    </main>
</x-layouts.publik>
