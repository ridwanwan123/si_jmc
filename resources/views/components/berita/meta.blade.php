@props(['berita'])
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-2 gap-y-1']) }}>
    <x-berita.chip :kategori="$berita->kategori" />
    <span class="font-mono text-[11px] font-bold text-muted">{{ $berita->tanggal() }}</span>
    {{ $slot }}
</div>
