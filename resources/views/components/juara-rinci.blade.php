@props(['lomba'])
{{--
    Rincian juara satu lomba (panel "Lihat juara" di halaman Cabor): tiga kolom Juara 1 / 2 / 3
    dengan medali bernomor. Tiap juara: nama madrasah + nama siswa; lomba beregu menampilkan
    "TIM · n ANGGOTA" lalu nama anggota bernomor, satu per baris.
    $lomba->hasil harus sudah di-eager load beserta madrasah.
--}}
@php $per = $lomba->hasil->groupBy('peringkat'); @endphp
<div {{ $attributes->merge(['class' => 'grid gap-x-5 gap-y-6 sm:grid-cols-3']) }}>
    @foreach ([1, 2, 3] as $p)
        <div class="min-w-0">
            <div class="mb-3 flex items-center gap-2 border-b border-line pb-2.5">
                <x-medali :peringkat="$p" :ukuran="28" />
                <span class="font-mono text-[11px] font-bold text-muted">{{ strtoupper(\App\Enums\Medali::from($p)->label()) }} · JUARA {{ $p }}</span>
            </div>
            @forelse ($per->get($p, collect()) as $h)
                @php $anggota = $h->anggota(); @endphp
                <div class="{{ $loop->first ? '' : 'mt-4' }}">
                    <a href="{{ rute('madrasah.show', $h->madrasah->npsn) }}" class="block break-words text-base font-bold leading-snug text-ink">{{ $h->madrasah->nama }}</a>
                    @if ($h->tim() && $anggota)
                        <div class="mt-0.5 font-mono text-[11px] font-bold text-accent">TIM · {{ count($anggota) }} ANGGOTA</div>
                        <ol class="mt-1.5 space-y-0.5">
                            @foreach ($anggota as $i => $nama)
                                <li class="flex gap-3 text-[15px] leading-normal text-teks-2">
                                    <span class="w-5 flex-none pt-px font-mono text-[11px] font-bold leading-6 text-[#8A8C92]">{{ nomor($i + 1) }}</span>
                                    <span class="min-w-0 break-words">{{ $nama }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @elseif ($anggota)
                        <div class="mt-0.5 break-words text-[15px] leading-normal text-teks-2">{{ $anggota[0] }}</div>
                    @endif
                </div>
            @empty
                <span class="text-[15px] text-muted">Tidak ada</span>
            @endforelse
        </div>
    @endforeach
</div>
