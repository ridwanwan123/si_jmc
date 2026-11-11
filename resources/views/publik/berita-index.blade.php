<x-layouts.publik judul="Berita">
    <x-berita.pita :berita="$pita" />

    <main class="wadah pb-18 pt-10">
        <div class="label-mono mb-2 text-xs">{{ mb_strtoupper($edisi->nama) }} · KABAR TERBARU</div>
        <div class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-6">
            <h1 class="judul-halaman">Berita</h1>
            <x-segmented :pilihan="$opsiKategori" label="Filter kategori berita" :gulir="true" />
        </div>

        @if (! $unggulan)
            <p class="py-10 text-muted">
                {{ $kategori ? 'Belum ada berita kategori '.mb_strtolower($kategori->label()).' untuk '.$edisi->nama.'.' : 'Belum ada berita untuk '.$edisi->nama.'.' }}
            </p>
        @else
            {{-- BERITA UNGGULAN --}}
            <article class="group relative grid items-center gap-6 py-6 lg:grid-cols-[minmax(0,1.12fr)_minmax(0,1fr)] lg:gap-8">
                <x-berita.sampul :berita="$unggulan" ikon="size-16" class="aspect-[16/10]" />
                <div>
                    <x-berita.meta :berita="$unggulan" />
                    <h2 class="mt-4 font-judul text-[clamp(26px,3.2vw,40px)] font-extrabold leading-[1.1] tracking-[-0.5px]" style="font-stretch: 110%">
                        <a href="{{ rute('berita.show', $unggulan->slug) }}" class="text-ink no-underline after:absolute after:inset-0 group-hover:text-accent">{{ $unggulan->judul }}</a>
                    </h2>
                    <p class="mt-4 text-[17px] leading-relaxed text-teks-2">{{ $unggulan->ringkasan }}</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 font-bold text-accent">Baca selengkapnya <x-ikon nama="panah-kanan" class="size-4" /></span>
                </div>
            </article>

            <div class="mt-8 grid gap-12 lg:grid-cols-[1fr_300px]">
                {{-- TERBARU --}}
                <section id="terbaru" aria-labelledby="judul-terbaru" class="min-w-0 scroll-mt-6">
                    <h2 id="judul-terbaru" class="font-judul-tegas border-b-2 border-ink pb-3 text-[22px]">Terbaru</h2>
                    @if ($terbaru->isEmpty())
                        <p class="py-8 text-muted">Belum ada berita lain.</p>
                    @else
                        <div class="mt-6 grid gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($terbaru as $b)
                                <x-berita.kartu :berita="$b" />
                            @endforeach
                        </div>
                    @endif
                    @if ($adaLagi)
                        <div class="mt-12 flex justify-center">
                            <a href="{{ $urlLagi }}" class="inline-flex min-h-12 items-center rounded-full border border-ink px-6 font-bold text-ink no-underline hover:bg-ink hover:text-white">Muat berita lainnya</a>
                        </div>
                    @endif
                </section>

                {{-- PENGUMUMAN --}}
                <aside aria-labelledby="judul-pengumuman" class="lg:pt-0">
                    <h2 id="judul-pengumuman" class="font-judul-tegas border-b-2 border-ink pb-3 text-[22px]">Pengumuman</h2>
                    @forelse ($pengumuman as $p)
                        <article class="group relative flex gap-4 border-b border-line py-4">
                            <div class="w-12 flex-none text-center">
                                <div class="font-angka text-2xl">{{ $p->terbit_pada->setTimezone(config('jmc.timezone'))->format('d') }}</div>
                                <div class="mt-1 font-mono text-[11px] font-bold text-muted">{{ $p->tanggal('M') }}</div>
                            </div>
                            <h3 class="text-[15px] font-bold leading-snug">
                                <a href="{{ rute('berita.show', $p->slug) }}" class="text-ink no-underline after:absolute after:inset-0 group-hover:text-accent">{{ $p->judul }}</a>
                            </h3>
                        </article>
                    @empty
                        <p class="py-4 text-sm text-muted">Belum ada pengumuman.</p>
                    @endforelse

                    @if ($dibuka)
                        <a href="{{ route('daftar') }}" class="mt-6 block rounded-xl bg-accent px-5 py-5 text-white no-underline hover:bg-accent-tua hover:text-white">
                            <span class="font-mono text-[11px] font-bold text-white/80">PENDAFTARAN DIBUKA</span>
                            <span class="mt-1 flex items-center gap-1.5 text-lg font-extrabold leading-snug">Daftarkan peserta madrasahmu <x-ikon nama="panah-kanan" class="size-5" /></span>
                        </a>
                    @endif
                </aside>
            </div>
        @endif
    </main>
</x-layouts.publik>
