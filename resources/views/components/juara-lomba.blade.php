@props(['lomba', 'ukuran' => 'besar'])
{{--
    Juara satu lomba: tiga kolom Juara 1 / 2 / 3, masing-masing diawali medali bernomor
    (x-medali) dan label "JUARA N". Jika satu peringkat punya beberapa juara, nama-namanya
    ditumpuk di kolom yang sama. $lomba->hasil harus sudah di-eager load beserta madrasah.
--}}
@php
    $per = $lomba->hasil->groupBy('peringkat');
    $besar = $ukuran === 'besar';
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-3 '.($besar ? 'gap-3 text-[15px]' : 'gap-2.5 text-[13px]')]) }}>
    @foreach ([1, 2, 3] as $p)
        <div class="min-w-0">
            <div class="mb-1.5 flex items-center gap-1.5 {{ $besar ? 'mb-2' : '' }}">
                <x-medali :peringkat="$p" :ukuran="$besar ? 28 : 24" />
                <span class="font-mono text-[11px] font-bold text-muted" aria-hidden="true">JUARA {{ $p }}</span>
            </div>
            @forelse ($per->get($p, collect()) as $h)
                <a href="{{ rute('madrasah.show', $h->madrasah->nsm) }}"
                   class="block break-words leading-snug text-ink {{ $p === 1 ? 'font-bold' : '' }} {{ ! $loop->first ? 'mt-1' : '' }}">{{ $h->madrasah->nama }}</a>
            @empty
                <span class="text-muted">—</span>
            @endforelse
        </div>
    @endforeach
</div>
