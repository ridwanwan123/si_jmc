<x-layouts.publik :judul="$berita->judul">
    <x-berita.pita :berita="$pita?->is($berita) ? null : $pita" />

    <main class="wadah pb-18 pt-8">
        <a href="{{ rute('berita.index') }}" class="inline-flex min-h-11 items-center gap-2 font-mono text-xs font-bold text-ink no-underline">
            <x-ikon nama="panah-kiri" class="size-4" /> SEMUA BERITA
        </a>

        <article>
            <header class="mx-auto mt-4 max-w-[720px]">
                <x-berita.meta :berita="$berita">
                    <span class="font-mono text-[11px] font-bold text-muted">· {{ $berita->menitBaca() }} MENIT BACA</span>
                </x-berita.meta>
                <h1 class="mt-4 font-judul text-[clamp(28px,4vw,44px)] font-extrabold leading-[1.08] tracking-[-0.5px]" style="font-stretch: 110%">{{ $berita->judul }}</h1>
                <p class="mt-5 text-lg leading-relaxed text-teks-2">{{ $berita->ringkasan }}</p>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-y border-line py-3"
                     x-data="{ disalin: false, salin() { navigator.clipboard?.writeText(window.location.href.split('#')[0]).then(() => { this.disalin = true; setTimeout(() => this.disalin = false, 2000) }) } }">
                    <span class="text-sm text-muted">Oleh <strong class="font-bold text-ink">{{ $berita->penulisTampil() }}</strong></span>
                    <div class="flex flex-wrap gap-2">
                        <a href="https://wa.me/?text={{ rawurlencode($berita->judul.' '.url()->current()) }}" target="_blank" rel="noopener"
                           class="inline-flex min-h-11 items-center gap-2 rounded-full border border-pill-line bg-white px-4 text-sm font-semibold text-ink no-underline hover:border-ink hover:text-ink">
                            <x-ikon nama="pesan" class="size-4" /> Bagikan ke WhatsApp
                        </a>
                        <button type="button" @click="salin()"
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-pill-line bg-white px-4 text-sm font-semibold text-ink hover:border-ink">
                            <x-ikon nama="tautan" class="size-4" />
                            <span x-text="disalin ? 'Tautan disalin' : 'Salin tautan'">Salin tautan</span>
                        </button>
                    </div>
                </div>
            </header>

            <figure class="mx-auto mt-8 max-w-[960px]">
                <x-berita.sampul :berita="$berita" ikon="size-16" class="aspect-[16/9]" />
                @if ($berita->keterangan_sampul)
                    <figcaption class="mx-auto mt-3 max-w-[720px] text-[13px] text-muted">{{ $berita->keterangan_sampul }}</figcaption>
                @endif
            </figure>

            <div class="isi-berita mx-auto mt-10 max-w-[720px]">
                {!! $berita->isiAman() !!}
            </div>

            <footer class="mx-auto mt-10 max-w-[720px]">
                @if ($berita->tag)
                    <ul class="flex flex-wrap gap-2" aria-label="Tag">
                        @foreach ($berita->tag as $tag)
                            <li class="rounded-full border border-pill-line px-3 py-1.5 text-[13px] font-semibold">#{{ ltrim($tag, '#') }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($dibuka)
                    <div class="mt-10 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-ink px-6 py-5 text-white">
                        <span class="text-lg font-extrabold">Siap mendaftar?</span>
                        <a href="{{ route('daftar') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-full bg-accent px-5 font-bold text-white no-underline hover:bg-accent-tua hover:text-white">
                            Daftar sekarang <x-ikon nama="panah-kanan" class="size-4" />
                        </a>
                    </div>
                @endif
            </footer>
        </article>

        @if ($lainnya->isNotEmpty())
            <section aria-labelledby="judul-lainnya" class="mx-auto mt-16 max-w-[960px]">
                <h2 id="judul-lainnya" class="font-judul-tegas border-b-2 border-ink pb-3 text-[22px]">Berita lainnya</h2>
                <div class="mt-6 grid gap-6 md:grid-cols-3">
                    @foreach ($lainnya as $b)
                        <x-berita.baris :berita="$b" ukuran="w-24" />
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts.publik>
