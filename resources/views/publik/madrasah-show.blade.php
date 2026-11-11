<x-layouts.publik :judul="$madrasah->nama">
    @php
        $emas = $baris?->emas ?? 0;
        $perak = $baris?->perak ?? 0;
        $perunggu = $baris?->perunggu ?? 0;
        $namaCabor = $semuaCabor->keyBy('id');
        $maksRinci = max(1, collect($rincian)->max('total') ?? 1);
    @endphp

    <main class="wadah pb-18 pt-8">
        {{-- BREADCRUMB + PINDAH MADRASAH --}}
        <div class="mb-7 flex flex-wrap items-center justify-between gap-3">
            <div class="font-mono text-xs font-bold uppercase text-muted">
                <a href="{{ rute('madrasah.index') }}" class="text-muted underline">Madrasah</a> / {{ $madrasah->nama }}
            </div>
            <div class="flex flex-wrap items-center gap-2.5" x-data>
                <label for="pilih-madrasah" class="font-mono text-xs font-bold text-muted">LIHAT MADRASAH</label>
                <select id="pilih-madrasah"
                        @change="window.location = $el.value"
                        class="min-h-11 w-full min-w-0 rounded-[10px] border border-ink bg-white px-3.5 text-[15px] font-semibold sm:w-auto sm:min-w-[220px]">
                    @foreach ($opsi->groupBy(fn ($m) => $m->jenjang->value) as $kode => $grup)
                        <optgroup label="{{ $kode }}">
                            @foreach ($grup as $o)
                                <option value="{{ rute('madrasah.show', $o->npsn) }}" @selected($o->is($madrasah))>{{ $o->nama }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- HEADER --}}
        <div class="flex flex-wrap items-center gap-x-7 gap-y-5 border-b-2 border-ink pb-7">
            @if ($madrasah->logo)
                <img src="{{ asset('storage/'.$madrasah->logo) }}" alt="Logo {{ $madrasah->nama }}" class="size-22 flex-none rounded-[20px] border border-line bg-white object-contain p-2">
            @else
                <span class="flex size-22 flex-none items-center justify-center rounded-[20px] bg-ink font-judul text-[30px] font-extrabold text-white" style="font-stretch:110%" aria-hidden="true">{{ $inisial }}</span>
            @endif
            <div class="min-w-0 flex-[1_1_320px]">
                <h1 class="font-judul-tegas text-[clamp(30px,4vw,44px)] leading-[1.05] tracking-[-1px]">{{ $madrasah->nama }}</h1>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <span class="rounded-full bg-ink px-2.5 py-1 text-[13px] font-bold text-white">{{ $madrasah->jenjang->value }}</span>
                    <span class="rounded-full border border-pill-line px-2.5 py-1 text-[13px] font-semibold">{{ $madrasah->status->value }}</span>
                    <span class="rounded-full border border-pill-line px-2.5 py-1 text-[13px] font-semibold">{{ $madrasah->kota->value }}</span>
                    <span class="rounded-full border border-pill-line px-2.5 py-1 font-mono text-xs font-bold">NPSN {{ $madrasah->npsn }}</span>
                </div>
            </div>
            <div class="flex-none sm:text-right">
                <div class="font-mono text-xs font-bold text-muted">PERINGKAT</div>
                @if ($baris)
                    <div class="font-judul text-[56px] font-extrabold leading-none text-accent" style="font-stretch:110%">{{ $baris->labelPeringkat() }}</div>
                    <div class="text-[13px] text-muted">dari {{ $jumlahDiKlasemen }} madrasah</div>
                @else
                    <div class="font-judul text-[56px] font-extrabold leading-none text-muted" style="font-stretch:110%">—</div>
                    <div class="text-[13px] text-muted">Belum meraih medali</div>
                @endif
            </div>
        </div>

        {{-- RINGKASAN MEDALI --}}
        <div class="mt-7 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([[1, 'EMAS', $emas], [2, 'PERAK', $perak], [3, 'PERUNGGU', $perunggu]] as [$p, $label, $n])
                <div class="flex items-start justify-between gap-2 rounded bg-white px-5 py-4.5">
                    <div>
                        <div class="font-mono text-xs font-bold text-muted">{{ $label }}</div>
                        <div class="mt-1.5 font-judul text-[40px] font-extrabold leading-none" style="font-stretch:110%">{{ $n }}</div>
                    </div>
                    <x-medali :peringkat="$p" :ukuran="32" />
                </div>
            @endforeach
            <div class="rounded border-t-4 border-ink bg-ink px-5 py-4.5 text-white">
                <div class="font-mono text-xs font-bold text-[#B9BBC2]">TOTAL</div>
                <div class="mt-1.5 font-judul text-[40px] font-extrabold leading-none" style="font-stretch:110%">{{ $emas + $perak + $perunggu }}</div>
            </div>
        </div>

        <div class="mt-12 flex flex-wrap items-start gap-10">
            {{-- DAFTAR JUARA --}}
            <section class="min-w-0 flex-[999_1_560px]">
                <h2 class="font-judul-tegas mb-4 text-[26px] tracking-[-0.5px]">Daftar Juara</h2>
                <div class="border-t-2 border-ink">
                    @forelse ($juara as $h)
                        @php $md = $h->medali(); @endphp
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-line py-3.5">
                            <span class="flex w-[108px] flex-none items-center gap-2 font-mono text-xs font-bold uppercase">
                                <x-medali :peringkat="$h->peringkat" :ukuran="24" />{{ $md->label() }}
                            </span>
                            <span class="min-w-0 flex-[1_1_220px]">
                                <span class="block text-base font-bold">{{ $h->lomba->nama }} {{ $h->lomba->jenjang->value }} {{ $h->lomba->kategori->value }}</span>
                                @php $anggota = $h->anggota(); @endphp
                                @if ($anggota)
                                    <span class="mt-0.5 block text-sm leading-normal text-teks-2">
                                        @if ($h->tim())<span class="font-mono text-[11px] font-bold text-accent">TIM · {{ count($anggota) }} ANGGOTA · </span>@endif{{ implode(', ', $anggota) }}
                                    </span>
                                @endif
                            </span>
                            <a href="{{ rute('cabor.show', $h->lomba->cabor->slug) }}" class="flex-none font-mono text-xs font-bold uppercase text-accent">{{ $h->lomba->cabor->nama }}</a>
                        </div>
                    @empty
                        <p class="border-b border-line py-6 text-muted">Belum ada medali di {{ $edisi->nama }}.</p>
                    @endforelse
                </div>
            </section>

            <aside class="flex min-w-0 flex-[1_1_320px] flex-col gap-10">
                {{-- PER CABOR --}}
                <section>
                    <h2 class="font-judul-tegas mb-4 text-[26px] tracking-[-0.5px]">Per Cabor</h2>
                    <div class="border-t-2 border-ink">
                        @forelse ($semuaCabor as $c)
                            @continue(! isset($rincian[$c->id]))
                            @php $r = $rincian[$c->id]; @endphp
                            <div class="flex flex-col gap-2 border-b border-line py-3.5">
                                <div class="flex justify-between gap-2">
                                    <span class="font-bold">{{ $c->nama }}</span>
                                    <span class="font-mono text-[13px] font-bold">{{ $r['emas'] }} · {{ $r['perak'] }} · {{ $r['perunggu'] }}</span>
                                </div>
                                <x-batang-medali :emas="$r['emas']" :perak="$r['perak']" :perunggu="$r['perunggu']" :maks="$maksRinci" tinggi="h-2" />
                            </div>
                        @empty
                        @endforelse
                        @if ($rincian === [])
                            <p class="border-b border-line py-4 text-muted">—</p>
                        @endif
                    </div>
                </section>

                {{-- RIWAYAT --}}
                <section>
                    <h2 class="font-judul-tegas mb-4 text-[26px] tracking-[-0.5px]">Riwayat</h2>
                    <div class="border-t-2 border-ink text-[15px]">
                        <div class="grid grid-cols-[72px_minmax(0,1fr)_72px] gap-2 border-b border-line py-2.5 font-mono text-[11px] font-bold text-muted">
                            <span>EDISI</span><span>EMAS · PERAK · PRG</span><span class="text-right">RANK</span>
                        </div>
                        @foreach ($riwayat as $r)
                            @php $ini = $r['edisi']->is($edisi); $rb = $r['baris']; @endphp
                            <a href="{{ route('madrasah.show', ['madrasah' => $madrasah->npsn] + ($r['edisi']->is_aktif ? [] : ['edisi' => $r['edisi']->tahun])) }}"
                               class="grid min-h-11 grid-cols-[72px_minmax(0,1fr)_72px] items-center gap-2 border-b border-line py-2 font-mono no-underline {{ $ini ? 'font-bold text-ink' : 'text-muted' }}">
                                <span>{{ $r['edisi']->tahun }}</span>
                                <span>{{ $rb ? $rb->emas.' · '.$rb->perak.' · '.$rb->perunggu : '—' }}</span>
                                <span class="text-right {{ $ini && $rb ? 'text-accent' : '' }}">{{ $rb?->labelPeringkat() ?? '—' }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </main>
</x-layouts.publik>
