<x-layouts.publik judul="Klasemen Medali">
    @php
        $tabs = [
            ['klasemen', 'Klasemen'],
            ['peraih', 'Peraih Medali'],
            ['cari', 'Pencarian'],
        ];
        $namaCabor = $semuaCabor->keyBy('id');
        $kolom = 'grid-cols-[56px_minmax(0,1.3fr)_minmax(0,1fr)_48px_48px_48px_56px_40px] sm:grid-cols-[72px_minmax(0,1.3fr)_minmax(0,1fr)_56px_56px_56px_64px_40px]';
    @endphp

    <main class="wadah pb-18 pt-12">
        {{-- JUDUL + TAB --}}
        <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="label-mono mb-2.5">
                    {{ strtoupper($edisi->nama) }}@if ($terakhir) · {{ $edisi->selesai() ? 'HASIL AKHIR' : 'DIPERBARUI' }} {{ str_replace(' · ', ' ', $terakhir) }}@endif
                </div>
                <h1 class="judul-halaman">Klasemen <span class="text-accent">Medali</span></h1>
            </div>
            <nav aria-label="Tampilan" class="flex flex-wrap gap-x-6 gap-y-1">
                @foreach ($tabs as [$id, $label])
                    <a href="{{ rute('klasemen', array_filter(['tab' => $id === 'klasemen' ? null : $id] + ($id === 'cari' ? [] : $filter))) }}"
                       @if ($tab === $id) aria-current="page" @endif
                       class="flex min-h-11 items-center border-b-[3px] text-base no-underline {{ $tab === $id ? 'border-ink font-extrabold text-ink' : 'border-transparent font-semibold text-muted' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        {{-- FILTER (tab Klasemen & Peraih Medali) --}}
        @if ($tab !== 'cari')
            @php $filterBar = 'flex flex-wrap items-center justify-between gap-4 border-b border-line py-4'; @endphp

            @if ($tab === 'klasemen' && $baris->isNotEmpty())
                <x-podium :baris="$baris->take(3)" class="mx-auto mb-8 w-full max-w-[760px]" />
            @endif

            <div class="{{ $filterBar }} border-t-2 border-t-ink">
                <x-segmented :pilihan="$opsiJenjang" :label="$tab === 'peraih' ? 'Filter jenjang lomba' : 'Filter jenjang madrasah'" />
                <form method="get" action="{{ route('klasemen') }}" class="flex items-center gap-2.5" x-data>
                    @foreach ($edisiTerpilih->param() + array_filter(['tab' => $tab === 'klasemen' ? null : $tab, 'jenjang' => $jenjang?->value]) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <label for="filter-cabor" class="font-mono text-xs font-bold text-muted">CABOR</label>
                    <select id="filter-cabor" name="cabor" @change="$el.form.submit()"
                            class="min-h-11 min-w-[200px] rounded-[10px] border border-ink bg-white px-3.5 text-[15px] font-semibold">
                        <option value="">Semua cabor</option>
                        @foreach ($semuaCabor as $c)
                            <option value="{{ $c->slug }}" @selected($cabor?->is($c))>{{ $c->nama }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="min-h-11 rounded-full bg-ink px-4 font-bold text-white">Terapkan</button></noscript>
                </form>
            </div>
        @endif

        {{-- ===== TAB KLASEMEN ===== --}}
        @if ($tab === 'klasemen')
            <div class="mt-8 overflow-x-auto" x-data="{ buka: null }">
                <div class="min-w-[680px]">
                    <div class="grid {{ $kolom }} items-center gap-x-3 pb-3 font-mono text-[11px] font-bold text-muted">
                        <span>RANK</span><span>MADRASAH</span><span>PEROLEHAN</span>
                        <span class="flex items-center justify-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-emas"></span>EMAS</span>
                        <span class="flex items-center justify-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-perak"></span>PERAK</span>
                        <span class="flex items-center justify-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-perunggu"></span>PRNGGU</span>
                        <span class="text-center">TOTAL</span><span></span>
                    </div>

                    @forelse ($baris as $b)
                        @php $rinci = $rincian[$b->madrasahId] ?? []; @endphp
                        <div class="border-t border-line">
                            <button type="button" @click="buka = buka === {{ $b->madrasahId }} ? null : {{ $b->madrasahId }}"
                                    :aria-expanded="buka === {{ $b->madrasahId }}" aria-controls="rinci-{{ $b->madrasahId }}"
                                    class="grid w-full cursor-pointer {{ $kolom }} min-h-[68px] items-center gap-x-3 py-2 text-left text-base text-ink">
                                <span class="font-mono text-xl font-bold">{{ $b->labelPeringkat() }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-[17px] font-extrabold">{{ $b->nama }}</span>
                                    <span class="block text-[13px] text-muted">{{ $b->jenjang->value }} · {{ $b->status }} · {{ $b->kota }}</span>
                                </span>
                                <x-batang-medali :emas="$b->emas" :perak="$b->perak" :perunggu="$b->perunggu" :maks="$maks" />
                                <span class="text-center font-mono text-[17px] font-bold">{{ $b->emas }}</span>
                                <span class="text-center font-mono text-[17px] font-medium">{{ $b->perak }}</span>
                                <span class="text-center font-mono text-[17px] font-medium">{{ $b->perunggu }}</span>
                                <span class="text-center font-mono text-[17px] font-bold">{{ $b->total() }}</span>
                                <span class="flex justify-center">
                                    <x-ikon nama="plus" stroke="2.2" x-show="buka !== {{ $b->madrasahId }}" />
                                    <x-ikon nama="minus" stroke="2.2" x-cloak x-show="buka === {{ $b->madrasahId }}" />
                                </span>
                            </button>
                            <div id="rinci-{{ $b->madrasahId }}" x-cloak x-show="buka === {{ $b->madrasahId }}" class="pb-4.5 pt-1">
                                @php $maksRinci = max(1, collect($rinci)->max('total') ?? 1); @endphp
                                @foreach ($semuaCabor as $c)
                                    @continue(! isset($rinci[$c->id]))
                                    @php $r = $rinci[$c->id]; @endphp
                                    <div class="grid {{ $kolom }} min-h-10 items-center gap-x-3 text-[15px]">
                                        <span></span>
                                        <span class="font-mono text-[13px] font-bold uppercase text-accent">{{ $c->nama }}</span>
                                        <x-batang-medali :emas="$r['emas']" :perak="$r['perak']" :perunggu="$r['perunggu']" :maks="$maksRinci" tinggi="h-1.5" />
                                        <span class="text-center font-mono">{{ $r['emas'] }}</span>
                                        <span class="text-center font-mono text-muted">{{ $r['perak'] }}</span>
                                        <span class="text-center font-mono text-muted">{{ $r['perunggu'] }}</span>
                                        <span class="text-center font-mono font-bold">{{ $r['total'] }}</span>
                                        <span></span>
                                    </div>
                                @endforeach
                                <div class="pl-[68px] pt-2 sm:pl-[84px]">
                                    <a href="{{ rute('madrasah.show', $b->npsn) }}" class="text-sm font-bold text-accent underline">Profil madrasah &amp; daftar juara →</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="border-t border-line py-10 text-center text-muted">Belum ada medali untuk filter ini.</div>
                    @endforelse
                </div>
            </div>
            <p class="mt-8 text-[13px] text-muted">Peringkat: emas → perak → perunggu. Perolehan yang sama persis mendapat peringkat yang sama. Klik baris untuk rincian per cabor.</p>
        @endif

        {{-- ===== TAB PERAIH MEDALI ===== --}}
        @if ($tab === 'peraih')
            <div class="mt-2">
                @forelse ($lomba as $l)
                    <div class="flex flex-wrap gap-x-8 gap-y-2.5 border-b border-line py-5.5">
                        <div class="min-w-0 flex-[1_1_260px]">
                            <div class="font-mono text-xs font-bold uppercase text-accent">
                                <a href="{{ rute('cabor.show', $l->cabor->slug) }}" class="text-accent">{{ $l->cabor->nama }}</a> · {{ $l->jenjang->value }}
                            </div>
                            <div class="mt-1 text-[17px] font-bold">{{ $l->nama }} {{ $l->jenjang->value }} {{ $l->kategori->value }}</div>
                        </div>
                        <x-juara-lomba :lomba="$l" class="flex-[2_1_460px]" />
                    </div>
                @empty
                    <div class="py-10 text-center text-muted">Belum ada hasil lomba untuk filter ini.</div>
                @endforelse
            </div>
        @endif

        {{-- ===== TAB PENCARIAN ===== --}}
        @if ($tab === 'cari')
            <div class="border-t-2 border-ink pt-8">
                <form method="get" action="{{ route('klasemen') }}" class="flex max-w-[760px] flex-col gap-3" role="search">
                    <input type="hidden" name="tab" value="cari">
                    @foreach ($edisiTerpilih->param() as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <label for="cari" class="font-mono text-xs font-bold text-muted">CARI PESERTA ATAU MADRASAH</label>
                    <div class="flex items-end gap-3 border-b-2 border-ink">
                        <input id="cari" name="q" type="search" value="{{ $kata }}" placeholder="Ketik nama…" minlength="2" maxlength="100" autocomplete="off"
                               class="min-h-16 w-full min-w-0 bg-transparent px-1 font-judul text-2xl font-bold outline-none placeholder:text-muted/60" style="font-stretch:110%">
                        <button type="submit" class="mb-2 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-ink text-white" aria-label="Cari">
                            <x-ikon nama="cari" />
                        </button>
                    </div>
                    <p class="text-sm text-muted">Hasil menampilkan medali yang diraih beserta lombanya. Minimal 2 huruf.</p>
                </form>

                @if ($hasilCari)
                    <div class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
                        <section>
                            <h2 class="judul-seksi mb-4 text-[22px]">Madrasah <span class="font-mono text-base text-muted">{{ $hasilCari['madrasah']->count() }}</span></h2>
                            <div class="border-t-2 border-ink">
                                @forelse ($hasilCari['madrasah'] as $m)
                                    @php $bk = $klasemenUmum->get($m->id); @endphp
                                    <a href="{{ rute('madrasah.show', $m->npsn) }}" class="flex min-h-14 items-center justify-between gap-4 border-b border-line py-3 text-ink no-underline">
                                        <span class="min-w-0">
                                            <span class="block font-bold">{{ $m->nama }}</span>
                                            <span class="block text-[13px] text-muted">{{ $m->jenjang->value }} · {{ $m->kota->value }}</span>
                                        </span>
                                        <span class="shrink-0 font-mono text-sm font-bold">
                                            @if ($bk)
                                                {{ $bk->emas }} · {{ $bk->perak }} · {{ $bk->perunggu }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </span>
                                    </a>
                                @empty
                                    <p class="border-b border-line py-4 text-muted">Tidak ada madrasah yang cocok.</p>
                                @endforelse
                            </div>
                        </section>
                        <section>
                            <h2 class="judul-seksi mb-4 text-[22px]">Peserta <span class="font-mono text-base text-muted">{{ $hasilCari['peserta']->count() }}</span></h2>
                            <div class="border-t-2 border-ink">
                                @forelse ($hasilCari['peserta'] as $h)
                                    @php $md = $h->medali(); @endphp
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-line py-3.5">
                                        <span class="flex w-[108px] shrink-0 items-center gap-2 font-mono text-xs font-bold uppercase">
                                            <x-medali :peringkat="$h->peringkat" :ukuran="24" />{{ $md->label() }}
                                        </span>
                                        <span class="min-w-0 flex-[1_1_220px]">
                                            <span class="block font-bold">{{ $h->nama_peserta }}</span>
                                            <span class="block text-[13px] text-muted">
                                                {{ $h->lomba->namaLengkap() }} ·
                                                <a href="{{ rute('madrasah.show', $h->madrasah->npsn) }}" class="text-muted underline">{{ $h->madrasah->nama }}</a>
                                            </span>
                                        </span>
                                        <span class="shrink-0 font-mono text-xs font-bold uppercase text-accent">{{ $h->lomba->cabor->nama }}</span>
                                    </div>
                                @empty
                                    <p class="border-b border-line py-4 text-muted">Tidak ada peserta yang cocok.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @elseif ($kata !== '')
                    <p class="mt-6 text-muted">Ketik minimal 2 huruf.</p>
                @endif
            </div>
        @endif
    </main>
</x-layouts.publik>
