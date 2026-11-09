<x-layouts.publik :judul="'Cabor '.$terpilih->nama">
    <main class="wadah pb-18 pt-10">
        <div class="label-mono mb-2 text-xs">{{ strtoupper($edisi->nama) }} · CABANG LOMBA</div>
        <h1 class="judul-halaman mb-8">Cabor &amp; Lomba</h1>

        <div class="flex flex-wrap items-start gap-10">
            {{-- DAFTAR CABOR 01–07 --}}
            <nav aria-label="Daftar cabor" class="w-full min-w-0 border-t-2 border-ink md:max-w-[300px] md:flex-[1_1_240px]">
                @foreach ($semuaCabor as $c)
                    @php $aktif = $c->is($terpilih); @endphp
                    <a href="{{ rute('cabor.show', $c->slug) }}" @if ($aktif) aria-current="page" @endif
                       class="flex min-h-14 items-center gap-3.5 border-b px-3.5 no-underline {{ $aktif ? 'border-ink bg-ink text-white hover:text-white' : 'border-line text-ink' }}">
                        <span class="font-mono text-[13px] font-bold {{ $aktif ? 'text-[#8FB0FF]' : 'text-accent' }}">{{ $c->nomor() }}</span>
                        <span class="flex-auto text-base {{ $aktif ? 'font-bold' : 'font-semibold' }}">{{ $c->nama }}</span>
                        <span class="font-mono text-xs {{ $aktif ? 'text-[#B9BBC2]' : 'text-muted' }}">{{ $c->lomba_count }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- ISI CABOR --}}
            <section class="min-w-0 flex-[999_1_560px]" x-data="{ jenjang: 'Semua', buka: [] }">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-5">
                    <div class="flex items-center gap-4">
                        <span class="flex size-14 flex-none items-center justify-center rounded-[14px] border-[1.5px] border-ink">
                            <x-ikon :nama="$terpilih->ikon" class="size-7" stroke="1.8" />
                        </span>
                        <div>
                            <h2 class="font-judul-tegas text-[32px] tracking-[-0.5px]">{{ $terpilih->nama }}</h2>
                            <div class="mt-1 font-mono text-xs font-bold text-muted">{{ $terpilih->lomba_count }} LOMBA · {{ $terpilih->lomba_selesai_count }} SELESAI</div>
                        </div>
                    </div>
                    <div role="group" aria-label="Filter jenjang" class="flex flex-wrap rounded-full bg-pill p-1">
                        @foreach (['Semua', ...$perJenjang->keys()] as $j)
                            <button type="button" @click="jenjang = '{{ $j }}'" :aria-pressed="jenjang === '{{ $j }}'"
                                    :class="jenjang === '{{ $j }}' ? 'bg-ink font-bold text-white' : 'font-semibold text-teks-2'"
                                    class="min-h-10 cursor-pointer rounded-full px-4 text-sm">{{ $j }}</button>
                        @endforeach
                    </div>
                </div>

                @foreach ($perJenjang as $kode => $lomba)
                    @php $j = \App\Enums\Jenjang::from($kode); @endphp
                    <div x-show="jenjang === 'Semua' || jenjang === '{{ $kode }}'" class="flex flex-wrap gap-x-6 gap-y-2 border-b border-line py-6">
                        <div class="flex-[0_0_150px]">
                            <div class="font-judul text-2xl font-extrabold" style="font-stretch:110%">{{ $kode }}</div>
                            <div class="text-[13px] text-muted">{{ $j->label() }}</div>
                            <div class="mt-1.5 font-mono text-xs font-bold text-accent">{{ $lomba->count() }} LOMBA</div>
                        </div>
                        <div class="flex min-w-0 flex-[1_1_400px] flex-col">
                            @forelse ($lomba as $l)
                                @php
                                    $juara1 = $l->hasil->where('peringkat', 1);
                                    $panel = 'juara-lomba-'.$l->id;
                                @endphp
                                <div class="border-t border-garis-halus first:border-t-0">
                                    <div class="flex min-h-15 flex-wrap items-center gap-x-5 gap-y-2 py-3">
                                        <div class="flex min-w-0 flex-[1_1_200px] flex-wrap items-center gap-2.5">
                                            <span class="text-base font-bold">{{ $l->nama }}</span>
                                            <span class="flex-none rounded-full border border-pill-line px-2 py-0.5 text-xs font-semibold text-teks-2">{{ $l->kategori->value }}</span>
                                            @if ($albumLomba = $l->galeriAlbum->first())
                                                <a href="{{ rute('galeri.show', $albumLomba->slug) }}" class="inline-flex min-h-8 items-center gap-1 font-mono text-[11px] font-bold text-accent" title="Foto {{ $l->nama }}">
                                                    <x-ikon nama="kamera" class="size-4" stroke="1.8" />FOTO
                                                </a>
                                            @endif
                                        </div>

                                        @if ($l->hasil->isNotEmpty())
                                            <div class="flex min-w-0 flex-[1_1_180px] items-start gap-2 text-[15px] font-bold">
                                                <x-medali :peringkat="1" :ukuran="20" class="mt-px" />
                                                <span class="min-w-0">{{ $juara1->map(fn ($h) => $h->madrasah->nama)->implode(', ') ?: '—' }}</span>
                                            </div>
                                            <div class="ml-auto flex flex-none items-center gap-4">
                                                <span class="font-mono text-xs font-bold text-muted">{{ $l->hasil->count() }} JUARA</span>
                                                <button type="button" aria-controls="{{ $panel }}"
                                                        :aria-expanded="buka.includes({{ $l->id }})"
                                                        @click="buka.includes({{ $l->id }}) ? buka = buka.filter(i => i !== {{ $l->id }}) : buka.push({{ $l->id }})"
                                                        class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 text-sm font-bold text-accent">
                                                    <span x-text="buka.includes({{ $l->id }}) ? 'Tutup' : 'Lihat juara'">Lihat juara</span>
                                                    <x-ikon nama="chevron-bawah" class="size-4 transition-transform" stroke="2.5" ::class="buka.includes({{ $l->id }}) && 'rotate-180'" />
                                                </button>
                                            </div>
                                        @else
                                            <span class="ml-auto flex-none font-mono text-xs font-bold text-muted">MENUNGGU HASIL</span>
                                        @endif
                                    </div>

                                    @if ($l->hasil->isNotEmpty())
                                        <div id="{{ $panel }}" x-cloak x-show="buka.includes({{ $l->id }})" class="pb-6 pt-1">
                                            <x-juara-rinci :lomba="$l" />
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="py-1.5 text-[15px] text-muted">Tidak ada lomba di jenjang ini.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach

                @if ((int) $terpilih->lomba_count === 0)
                    <p class="pt-6 text-muted">Daftar lomba {{ $terpilih->nama }} untuk {{ $edisi->nama }} akan tampil di sini setelah diumumkan panitia.</p>
                @else
                    <p class="mt-6 text-[13px] text-muted">Klik "Lihat juara" untuk melihat nama siswa atau anggota tim.</p>
                @endif
            </section>
        </div>
    </main>
</x-layouts.publik>
