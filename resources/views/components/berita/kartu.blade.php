@props(['berita'])
{{-- Kartu berita di grid "Terbaru": sampul, kategori + tanggal, judul, ringkasan. --}}
<article {{ $attributes->class(['group relative flex flex-col']) }}>
    <x-berita.sampul :berita="$berita" class="aspect-[16/10]" />
    <x-berita.meta :berita="$berita" class="mt-4" />
    <h3 class="mt-2 text-[19px] font-extrabold leading-snug">
        <a href="{{ rute('berita.show', $berita->slug) }}" class="text-ink no-underline after:absolute after:inset-0 group-hover:text-accent">{{ $berita->judul }}</a>
    </h3>
    <p class="mt-2 line-clamp-2 text-[15px] leading-relaxed text-muted">{{ $berita->ringkasan }}</p>
</article>
