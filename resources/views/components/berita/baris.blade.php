@props(['berita', 'ukuran' => 'w-28 sm:w-32'])
{{-- Baris ringkas: gambar kecil di kiri, kategori + tanggal + judul di kanan. --}}
<article {{ $attributes->class(['group relative flex items-start gap-4']) }}>
    <x-berita.sampul :berita="$berita" ikon="size-6" class="aspect-[4/3] flex-none rounded-lg {{ $ukuran }}" />
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-1.5 font-mono text-[11px] font-bold">
            <span class="{{ $berita->kategori === \App\Enums\KategoriBerita::Pengumuman ? 'text-ink' : 'text-accent' }}">{{ mb_strtoupper($berita->kategori->label()) }}</span>
            <span class="text-muted">· {{ $berita->tanggal() }}</span>
        </div>
        <h3 class="mt-1.5 text-[15px] font-bold leading-snug">
            <a href="{{ rute('berita.show', $berita->slug) }}" class="text-ink no-underline after:absolute after:inset-0 group-hover:text-accent">{{ $berita->judul }}</a>
        </h3>
    </div>
</article>
