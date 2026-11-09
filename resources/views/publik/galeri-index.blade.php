<x-layouts.publik judul="Galeri">
    @php
        // Hanya tampilkan chip cabor yang punya album.
        $adaCabor = $album->pluck('cabor_id')->filter()->unique();
        $chips = $semuaCabor->filter(fn ($c) => $adaCabor->contains($c->id));
    @endphp

    <main class="wadah pb-18 pt-10" x-data="{ cabor: 'semua' }">
        <div class="label-mono mb-2 text-xs">{{ strtoupper($edisi->nama) }} · DOKUMENTASI</div>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h1 class="judul-halaman">Galeri</h1>
            <span class="font-mono text-[13px] font-bold text-muted">{{ $album->count() }} ALBUM</span>
        </div>

        @if ($album->isEmpty())
            <p class="border-t-2 border-ink py-10 text-muted">Belum ada album foto untuk {{ $edisi->nama }}.</p>
        @else
            <div role="group" aria-label="Filter cabor" class="mb-7 flex flex-wrap gap-2 border-y border-line border-t-2 border-t-ink py-4">
                @foreach ([['semua', 'Semua'], ...$chips->map(fn ($c) => [$c->slug, $c->nama])->all()] as [$nilai, $label])
                    <button type="button" @click="cabor = '{{ $nilai }}'" :aria-pressed="cabor === '{{ $nilai }}'"
                            :class="cabor === '{{ $nilai }}' ? 'border-ink bg-ink font-bold text-white' : 'border-pill-line font-semibold text-ink'"
                            class="min-h-10 cursor-pointer rounded-full border px-4 text-sm">{{ $label }}</button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-x-5 gap-y-7 min-[480px]:grid-cols-2 lg:grid-cols-4">
                @foreach ($album as $a)
                    <x-kartu-album :album="$a" x-show="cabor === 'semua' || cabor === '{{ $a->cabor?->slug }}'" />
                @endforeach
            </div>
        @endif
    </main>
</x-layouts.publik>
