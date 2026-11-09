@props(['baris', 'varian' => 'klasemen'])
{{--
    Podium peringkat MADRASAH (bukan juara lomba). Urutan tampil 2 – 1 – 3.
    $baris: koleksi BarisKlasemen terurut. Jika kosong (belum ada juara), podium tampil
    pudar dengan nama placeholder ("segera").
--}}
@php
    $besar = $varian === 'klasemen';
    $kosong = $baris->isEmpty();
    $slot3 = [
        [$baris->get(1), '2', $besar ? 'h-[125px] sm:h-[125px]' : 'h-[110px]', 'bg-podium-perak'],
        [$baris->get(0), '1', $besar ? 'h-[170px]' : 'h-[150px]', 'bg-podium-emas'],
        [$baris->get(2), '3', $besar ? 'h-[90px]' : 'h-[80px]', 'bg-podium-perunggu'],
    ];
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-3 items-end '.($besar ? 'gap-2.5' : 'gap-2')]) }}>
    @foreach ($slot3 as [$b, $pos, $tinggi, $warna])
        <div class="flex min-w-0 flex-col gap-2.5 {{ $besar ? 'items-center gap-3 text-center' : '' }}">
            @if ($b)
                <div class="min-w-0">
                    <a href="{{ rute('madrasah.show', $b->nsm) }}" class="block font-extrabold leading-tight text-ink {{ $besar ? 'text-[17px]' : 'text-[15px]' }}">{{ $b->nama }}</a>
                    @if ($besar)
                        <div class="mt-1 font-mono text-xs font-bold text-muted">{{ $b->emas }} EMAS · {{ $b->total() }} TOTAL</div>
                    @else
                        <div class="font-mono text-xs font-bold text-muted">{{ $b->emas }} E · {{ $b->perak }} P · {{ $b->perunggu }} P</div>
                    @endif
                </div>
            @elseif ($kosong)
                {{-- Placeholder nama: garis abu-abu seperti kerangka teks --}}
                <div class="w-full min-w-0 {{ $besar ? 'flex flex-col items-center' : '' }}" aria-hidden="true">
                    <div class="h-3.5 w-3/4 rounded-sm bg-line"></div>
                    <div class="mt-2 h-2.5 w-1/2 rounded-sm bg-garis-halus"></div>
                </div>
            @else
                <div class="text-sm text-muted">—</div>
            @endif
            <div class="flex w-full justify-center rounded-t-md pt-3.5 {{ $tinggi }} {{ $warna }} {{ $b ? 'text-ink' : ($kosong ? 'text-ink/50 opacity-35' : 'text-ink opacity-40') }}">
                <span class="font-angka {{ $besar ? 'text-[44px]' : 'text-[40px]' }}">{{ $pos }}</span>
            </div>
        </div>
    @endforeach
</div>
