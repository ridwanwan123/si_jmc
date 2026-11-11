<x-layouts.publik>
    @php
        $tanggal = collect([
            $edisi->tanggal_mulai?->translatedFormat($edisi->tanggal_selesai && $edisi->tanggal_mulai->isSameMonth($edisi->tanggal_selesai) ? 'j' : 'j F'),
            $edisi->tanggal_selesai?->translatedFormat('j F Y'),
        ])->filter()->implode('–');
    @endphp

    {{-- HERO --}}
    <section class="wadah flex flex-wrap items-end gap-12 pb-10 pt-10 sm:pt-14">
        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
            <div class="flex flex-wrap items-center gap-2.5 font-mono text-[13px] font-bold text-muted">
                @if ($tahap === \App\Enums\Tahap::Registrasi && ! $edisiTerpilih->arsip())
                    <span class="inline-flex items-center gap-2 rounded-full bg-ink px-3 py-1.5 text-white">
                        @if ($edisi->pendaftaranDibuka())
                            <span class="size-2 rounded-full bg-live"></span>PENDAFTARAN DIBUKA
                        @else
                            PENDAFTARAN SEGERA DIBUKA
                        @endif
                    </span>
                    @if ($edisi->pendaftaranDibuka() && $edisi->pendaftaran_tutup)
                        <span>{{ $edisi->labelTutupPendaftaran() }}</span>
                    @elseif ($edisi->pendaftaran_buka?->isFuture())
                        <span>MULAI {{ $edisi->pendaftaran_buka->format('d.m.Y') }}</span>
                    @elseif ($edisi->tanggal_mulai)
                        <span>LOMBA MULAI {{ $edisi->tanggal_mulai->format('d.m.Y') }}</span>
                    @endif
                @else
                    @if ($status)
                        <span class="inline-flex items-center gap-2 rounded-full bg-ink px-3 py-1.5 text-white">
                            @if ($status === 'BERLANGSUNG')<span class="size-2 rounded-full bg-live"></span>@endif
                            {{ $status }}
                        </span>
                    @endif
                    @if ($terakhir)<span>{{ $edisi->selesai() ? 'HASIL AKHIR' : 'DIPERBARUI' }} {{ $terakhir }}</span>@endif
                @endif
            </div>
            <h1 class="judul-hero">Jakarta<br>Madrasah<br><span class="text-accent">Competition</span></h1>
            <p class="max-w-[520px] text-[17px] leading-relaxed text-teks-2">
                Kompetisi madrasah tingkat provinsi DKI Jakarta. RA, MI, MTs dan MA bertanding di {{ $cabor->count() }} cabang lomba.
                @if ($tanggal || $edisi->lokasi)
                    <span class="whitespace-nowrap">{{ collect([$tanggal, $edisi->lokasi])->filter()->implode(' · ') }}</span>
                @endif
            </p>
            <div class="flex flex-wrap gap-3">
                @if ($tahap === \App\Enums\Tahap::Registrasi && ! $edisiTerpilih->arsip())
                    <a href="{{ route('daftar') }}" class="inline-flex min-h-13 items-center gap-2.5 rounded-full bg-accent px-6.5 font-bold text-white no-underline hover:bg-accent-tua hover:text-white">
                        Daftar sekarang <x-ikon nama="panah-kanan-atas" class="size-4.5" stroke="2.5" />
                    </a>
                @else
                    <a href="{{ rute('klasemen') }}" class="inline-flex min-h-13 items-center gap-2.5 rounded-full bg-ink px-6.5 font-bold text-white no-underline hover:text-white">
                        Klasemen medali <x-ikon nama="panah-kanan-atas" class="size-4.5" stroke="2.5" />
                    </a>
                @endif
                <a href="#cabor" class="inline-flex min-h-13 items-center rounded-full border border-ink px-6.5 font-bold text-ink no-underline">Lihat {{ $cabor->count() }} cabor</a>
            </div>

            {{-- TAHAPAN: Registrasi → Tanding → Klasemen --}}
            <ol aria-label="Tahapan JMC" class="mt-1 flex max-w-[560px] flex-wrap gap-y-2">
                @foreach (\App\Enums\Tahap::cases() as $t)
                    @php
                        $sekarang = $t === $tahap;
                        $lewat = $t->urutan() < $tahap->urutan();
                    @endphp
                    <li @if ($sekarang) aria-current="step" @endif
                        class="flex-[1_1_120px] border-t-[3px] pr-3 pt-2.5 {{ $sekarang ? 'border-accent' : ($lewat ? 'border-ink' : 'border-line') }}">
                        <div class="font-mono text-[11px] font-bold {{ $sekarang ? 'text-accent' : ($lewat ? 'text-ink' : 'text-muted') }}">{{ $t->nomor() }}{{ $sekarang ? ' · SEKARANG' : '' }}</div>
                        <div class="mt-0.5 text-[15px] font-bold {{ $sekarang || $lewat ? 'text-ink' : 'text-teks-2' }}">{{ $t->label() }}</div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Podium: berisi juara di tahap klasemen; sebelum itu tampil pudar ("segera") + info pendaftaran. --}}
        <div class="flex min-w-0 flex-[1_1_380px] flex-col gap-3.5">
            @if ($podium->isNotEmpty())
                <div class="flex items-baseline justify-between font-mono text-[13px] font-bold text-muted">
                    <span>{{ $edisi->selesai() ? 'PODIUM' : 'PODIUM SEMENTARA' }}</span>
                    <a href="{{ rute('klasemen') }}" class="text-accent no-underline">LENGKAP →</a>
                </div>
                <x-podium :baris="$podium" varian="beranda" />
            @else
                <div class="flex items-baseline justify-between gap-3 font-mono text-[13px] font-bold text-muted">
                    <span>PODIUM</span>
                    <span class="rounded-full border border-pill-line px-2.5 py-0.5 text-[11px]">
                        {{ $tahap === \App\Enums\Tahap::Tanding ? 'MENUNGGU HASIL PERTAMA' : 'SEGERA' }}
                    </span>
                </div>
                <x-podium :baris="$podium" varian="beranda" />

                @if ($pendaftaran)
                    {{-- Info pendaftaran langsung (dari tabel pendaftaran) --}}
                    <div class="border-t-2 border-ink pt-3.5">
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ([[$pendaftaran['tim'], 'Pendaftaran'], [$pendaftaran['madrasah'], 'Madrasah'], [$pendaftaran['siswa'], 'Peserta']] as [$n, $label])
                                <div>
                                    <div class="font-angka text-[28px] leading-none">{{ number_format($n, 0, ',', '.') }}</div>
                                    <div class="mt-1 text-[13px] text-muted">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                        @if ($pendaftaran['sisaHari'] !== null)
                            <p class="mt-3.5 flex items-center gap-2 font-mono text-xs font-bold text-teks-2">
                                <span class="size-2 rounded-full bg-live"></span>
                                {{ $pendaftaran['sisaHari'] === 0 ? 'PENDAFTARAN DITUTUP HARI INI' : 'PENDAFTARAN DITUTUP '.$pendaftaran['sisaHari'].' HARI LAGI' }}
                            </p>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-muted">Juara ditentukan setelah pertandingan. Podium terisi otomatis begitu hasil pertama diumumkan.</p>
                @endif
            @endif
        </div>
    </section>

    {{-- TICKER HASIL TERBARU --}}
    @if ($ticker->isNotEmpty())
        <section aria-label="Hasil terbaru" class="bg-ink text-white">
            <div class="wadah flex items-stretch overflow-x-auto [scrollbar-width:thin]" tabindex="0">
                <div class="mr-2 flex flex-none items-center border-r border-[#33343A] py-4.5 pr-6 font-mono text-xs font-bold text-live">HASIL<br>TERBARU</div>
                @foreach ($ticker as $l)
                    <a href="{{ rute('cabor.show', $l->cabor->slug) }}" class="flex min-w-[260px] flex-none flex-col gap-1.5 border-r border-[#33343A] px-6 py-4 text-white no-underline hover:bg-white/5 hover:text-white">
                        <span class="font-mono text-[11px] font-bold uppercase text-[#A3A6AE]">{{ $l->cabor->nama }}</span>
                        <span class="text-[15px] font-bold">{{ $l->nama }} {{ $l->jenjang->value }} {{ $l->kategori->value === 'Umum' ? '' : $l->kategori->value }}</span>
                        <span class="flex items-start gap-2 text-sm text-[#E7E8EC]">
                            <x-medali :peringkat="1" :ukuran="16" class="mt-0.5" />
                            <span>{{ $l->hasil->where('peringkat', 1)->map(fn ($h) => $h->madrasah->nama)->implode(', ') ?: '—' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ANGKA RINGKAS --}}
    <section class="wadah">
        @php
            $angka = [
                [$ringkasan['madrasah_peserta'], 'Madrasah'],
                [$cabor->count(), 'Cabang lomba'],
                [$ringkasan['jumlah_lomba'], 'Lomba'],
                [$ringkasan['total_medali'], 'Medali dibagikan'],
            ];
        @endphp
        <div class="grid grid-cols-2 border-b border-line md:grid-cols-4">
            @foreach ($angka as $i => [$n, $label])
                <div class="border-line py-7 {{ $i % 2 === 0 ? 'border-r pr-4' : 'pl-4 md:pl-6' }} {{ $i === 1 ? 'md:border-r' : '' }} {{ $i === 2 ? 'border-t md:border-t-0 md:pl-6' : '' }} {{ $i === 3 ? 'border-t md:border-t-0' : '' }}">
                    <div class="font-angka text-4xl">{{ nomor($n) }}</div>
                    <div class="mt-2 text-sm text-muted">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- BERITA (disembunyikan jika belum ada berita terbit) --}}
    @if ($beritaUtama)
        <section class="wadah pt-16" aria-labelledby="judul-berita">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b-2 border-ink pb-4">
                <h2 id="judul-berita" class="judul-seksi">Berita</h2>
                <a href="{{ rute('berita.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-ink underline underline-offset-4">Semua berita <x-ikon nama="panah-kanan" class="size-4" /></a>
            </div>
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.12fr)_minmax(0,1fr)]">
                <article class="group relative">
                    <x-berita.sampul :berita="$beritaUtama" ikon="size-14" class="aspect-[16/9]" />
                    <x-berita.meta :berita="$beritaUtama" class="mt-4" />
                    <h3 class="mt-2 font-judul text-[clamp(20px,2.2vw,26px)] font-extrabold leading-tight" style="font-stretch: 110%">
                        <a href="{{ rute('berita.show', $beritaUtama->slug) }}" class="text-ink no-underline after:absolute after:inset-0 group-hover:text-accent">{{ $beritaUtama->judul }}</a>
                    </h3>
                    <p class="mt-2 line-clamp-3 text-[15px] leading-relaxed text-muted">{{ $beritaUtama->ringkasan }}</p>
                </article>
                @if ($beritaLain->isNotEmpty())
                    <div class="flex flex-col">
                        @foreach ($beritaLain as $b)
                            <x-berita.baris :berita="$b" class="border-b border-line py-4 first:pt-0 lg:first:pt-0" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- KLASEMEN RINGKAS --}}
    <section class="wadah pb-6 pt-16">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <h2 class="judul-seksi">Klasemen</h2>
            <div class="flex items-center gap-4 text-[13px] font-semibold text-muted">
                <span class="flex items-center gap-1.5"><span class="size-3 rounded-[3px] bg-emas"></span>Emas</span>
                <span class="flex items-center gap-1.5"><span class="size-3 rounded-[3px] bg-perak"></span>Perak</span>
                <span class="flex items-center gap-1.5"><span class="size-3 rounded-[3px] bg-perunggu"></span>Perunggu</span>
            </div>
        </div>
        <div class="border-t-2 border-ink">
            @forelse ($top as $b)
                <a href="{{ rute('madrasah.show', $b->npsn) }}" class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-line py-4.5 text-ink no-underline">
                    <span class="w-14 font-mono text-xl font-bold">{{ $b->labelPeringkat() }}</span>
                    <span class="min-w-0 flex-[1_1_220px]">
                        <span class="block text-[17px] font-bold">{{ $b->nama }}</span>
                        <span class="block text-[13px] text-muted">{{ $b->jenjang->value }} · {{ $b->kota }}</span>
                    </span>
                    <span class="flex min-w-0 flex-[2_1_280px] items-center gap-3.5">
                        <x-batang-medali :emas="$b->emas" :perak="$b->perak" :perunggu="$b->perunggu" :maks="$maksTop" tinggi="h-3" class="flex-auto" />
                        <span class="flex w-32 flex-none justify-end gap-3.5 font-mono text-[15px] font-bold">
                            <span>{{ $b->emas }}</span><span class="text-muted">{{ $b->perak }}</span><span class="text-muted">{{ $b->perunggu }}</span>
                        </span>
                    </span>
                </a>
            @empty
                <p class="border-b border-line py-6 text-muted">Belum ada hasil yang diumumkan.</p>
            @endforelse
        </div>
        @if ($top->isNotEmpty())
            <a href="{{ rute('klasemen') }}" class="mt-4 inline-flex min-h-11 items-center font-bold">Klasemen lengkap →</a>
        @endif
    </section>

    {{-- 7 CABOR --}}
    <section id="cabor" class="wadah scroll-mt-4 pb-18 pt-16">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <h2 class="judul-seksi">{{ $cabor->count() }} Cabor</h2>
            <span class="text-sm text-muted">Klik cabor untuk melihat daftar lombanya</span>
        </div>
        <div class="border-t-2 border-ink" x-data="{ buka: null }">
            @foreach ($cabor as $c)
                @php $perJenjang = $mataLomba->get($c->id, collect()); @endphp
                <div class="border-b border-line">
                    <button type="button" @click="buka = buka === {{ $c->id }} ? null : {{ $c->id }}"
                            :aria-expanded="buka === {{ $c->id }}" aria-controls="lomba-cabor-{{ $c->id }}"
                            class="flex min-h-16 w-full cursor-pointer items-center gap-x-4 py-4 text-left text-ink sm:gap-x-6">
                        <span class="w-8 flex-none font-mono text-[15px] font-bold text-accent sm:w-10">{{ $c->nomor() }}</span>
                        <x-ikon :nama="$c->ikon" class="hidden size-5 text-muted sm:block" stroke="1.8" />
                        <span class="min-w-0 flex-1 truncate font-judul text-xl font-extrabold uppercase" style="font-stretch:112%">{{ $c->nama }}</span>
                        <span class="hidden flex-none font-mono text-xs font-bold text-muted md:block">{{ $perJenjang->keys()->implode(' · ') }}</span>
                        <span class="w-20 flex-none text-right text-sm text-muted">{{ $c->lomba_count }} lomba</span>
                        <span class="flex w-6 flex-none justify-center">
                            <x-ikon nama="plus" stroke="2.2" x-show="buka !== {{ $c->id }}" />
                            <x-ikon nama="minus" stroke="2.2" x-cloak x-show="buka === {{ $c->id }}" />
                        </span>
                    </button>

                    <div id="lomba-cabor-{{ $c->id }}" x-cloak x-show="buka === {{ $c->id }}" class="pb-6 sm:pl-16">
                        @if ($perJenjang->isEmpty())
                            <p class="text-sm text-muted">Belum ada lomba di edisi ini.</p>
                        @else
                            <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($perJenjang as $kode => $lomba)
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 border-b-2 border-ink pb-1.5">
                                            <span class="font-judul text-lg font-extrabold" style="font-stretch:110%">{{ $kode }}</span>
                                            <span class="font-mono text-[11px] font-bold text-muted">{{ $lomba->count() }} LOMBA</span>
                                        </div>
                                        <ul>
                                            @foreach ($lomba as $l)
                                                <li class="flex items-baseline justify-between gap-3 border-b border-garis-halus py-2 text-[15px]">
                                                    <span class="min-w-0">{{ $l->nama }}</span>
                                                    <span class="flex-none text-xs text-muted">{{ $l->kategori->value }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <a href="{{ rute('cabor.show', $c->slug) }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-bold text-accent underline">Lihat juara {{ $c->nama }} →</a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- GALERI PILIHAN (selalu tampil; kosong → kotak placeholder) --}}
        <section class="wadah -mt-8 pb-18">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h2 class="judul-seksi">Galeri</h2>
                <a href="{{ rute('galeri.index') }}" class="inline-flex min-h-11 items-center text-[15px] font-bold underline">Semua album →</a>
            </div>
            @if ($galeri->isEmpty())
                <div class="grid auto-rows-[200px] grid-cols-2 gap-2.5 md:grid-cols-3 lg:grid-cols-5" aria-hidden="true">
                    @foreach (range(1, 5) as $i)
                        <div class="flex items-center justify-center rounded-md bg-placeholder {{ $i === 1 ? 'col-span-2 row-span-2 md:col-span-1' : 'hidden md:flex' }}">
                            <x-ikon nama="kamera" class="size-8 text-muted/50" stroke="1.5" />
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[15px] text-muted">Dokumentasi {{ $edisi->nama }} akan tampil di sini setelah diunggah panitia.</p>
            @else
            <div class="grid auto-rows-[200px] grid-cols-2 gap-2.5 md:grid-cols-3 lg:grid-cols-5">
                @foreach ($galeri as $a)
                    <a href="{{ rute('galeri.show', $a->slug) }}"
                       class="group relative flex items-end overflow-hidden rounded-md bg-placeholder text-ink no-underline {{ $loop->first ? 'col-span-2 row-span-2 md:col-span-1' : '' }}">
                        @if ($a->sampul)
                            <img src="{{ $a->sampul->thumbUrl() }}" alt="" loading="lazy" decoding="async"
                                 class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
                        @endif
                        <span class="relative m-2 flex w-[calc(100%-1rem)] items-center justify-between gap-2 rounded bg-bg/95 px-3 py-2 text-sm">
                            <span class="truncate font-bold">{{ $a->judul }}</span>
                            <span class="flex-none font-mono text-xs text-muted">{{ $a->foto_count }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
            @endif
        </section>
</x-layouts.publik>
