<x-layouts.publik judul="Madrasah">
    <main class="wadah pb-18 pt-10">
        <div class="label-mono mb-2 text-xs">{{ strtoupper($edisi->nama) }} · PROFIL PESERTA</div>
        <h1 class="judul-halaman mb-8">Madrasah</h1>

        <div class="flex flex-wrap items-center justify-between gap-4 border-y border-line border-t-2 border-t-ink py-4">
            <x-segmented :pilihan="$opsiJenjang" />
            <form method="get" action="{{ route('madrasah.index') }}" role="search" class="flex w-full max-w-sm items-center gap-2 sm:w-auto">
                @foreach ($edisiTerpilih->param() + array_filter(['jenjang' => $jenjang?->value]) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label for="cari-madrasah" class="sr-only">Cari nama atau NSM</label>
                <input id="cari-madrasah" name="q" type="search" value="{{ $kata }}" placeholder="Cari nama atau NSM…"
                       class="min-h-11 w-full min-w-0 rounded-[10px] border border-ink bg-white px-3.5 text-[15px] sm:w-64">
                <button type="submit" class="flex size-11 flex-none cursor-pointer items-center justify-center rounded-full bg-ink text-white" aria-label="Cari">
                    <x-ikon nama="cari" />
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[560px]">
                <div class="grid grid-cols-[minmax(0,1fr)_64px_minmax(0,0.6fr)_120px] items-center gap-x-4 py-3 font-mono text-[11px] font-bold text-muted">
                    <span>MADRASAH</span><span>JENJANG</span><span>WILAYAH</span><span class="text-right">E · P · P</span>
                </div>
                @forelse ($madrasah as $m)
                    @php $b = $medali->get($m->id); @endphp
                    <a href="{{ rute('madrasah.show', $m->nsm) }}"
                       class="grid min-h-14 grid-cols-[minmax(0,1fr)_64px_minmax(0,0.6fr)_120px] items-center gap-x-4 border-t border-line py-3 text-ink no-underline">
                        <span class="min-w-0">
                            <span class="block truncate font-bold">{{ $m->nama }}</span>
                            <span class="block font-mono text-xs text-muted">NSM {{ $m->nsm }} · {{ $m->status->value }}</span>
                        </span>
                        <span class="font-semibold">{{ $m->jenjang->value }}</span>
                        <span class="truncate text-sm text-muted">{{ $m->wilayah->value }}</span>
                        <span class="text-right font-mono font-bold">
                            @if ($b)
                                {{ $b->emas }} · <span class="text-muted">{{ $b->perak }} · {{ $b->perunggu }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </a>
                @empty
                    <p class="border-t border-line py-10 text-center text-muted">Tidak ada madrasah yang cocok.</p>
                @endforelse
            </div>
        </div>

        @if ($madrasah->hasPages())
            <div class="mt-8">{{ $madrasah->links() }}</div>
        @endif
        <p class="mt-6 text-[13px] text-muted">Perolehan medali (emas · perak · perunggu) untuk {{ $edisi->nama }}.</p>
    </main>
</x-layouts.publik>
