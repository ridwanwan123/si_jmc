@props(['album'])
{{-- Kartu album galeri: sampul 4:3, badge jumlah foto, label cabor · tanggal, judul. --}}
<a href="{{ rute('galeri.show', $album->slug) }}" {{ $attributes->merge(['class' => 'group flex flex-col gap-3 text-ink no-underline']) }}>
    <span class="relative block aspect-[4/3] w-full overflow-hidden rounded-md bg-placeholder">
        @if ($album->sampul)
            <img src="{{ $album->sampul->thumbUrl() }}" alt="" loading="lazy" decoding="async"
                 class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
        @endif
        <span class="absolute bottom-3 left-3 rounded-full bg-ink px-2.5 py-1 font-mono text-[11px] font-bold text-white">{{ $album->foto_count }} FOTO</span>
    </span>
    <span class="flex flex-col gap-1">
        <span class="font-mono text-[11px] font-bold text-accent">{{ $album->labelCabor() }}@if ($album->tanggal) · {{ $album->tanggal->format('d.m.Y') }}@endif</span>
        <span class="text-[17px] font-bold group-hover:text-accent">{{ $album->judul }}</span>
    </span>
</a>
