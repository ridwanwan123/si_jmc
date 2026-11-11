@props(['berita', 'ikon' => 'size-12'])
{{-- Foto sampul berita, atau kotak placeholder berikon cabor jika belum ada foto. --}}
<div {{ $attributes->class(['relative overflow-hidden rounded-xl bg-placeholder']) }}>
    @if ($url = $berita->urlSampul())
        <img src="{{ $url }}" alt="{{ $berita->keterangan_sampul ?: $berita->judul }}" loading="lazy" class="size-full object-cover">
    @else
        <div class="grid size-full place-items-center text-[#9A9890]">
            <x-ikon :nama="$berita->ikonPlaceholder()" :class="$ikon" stroke="1.5" />
        </div>
    @endif
</div>
