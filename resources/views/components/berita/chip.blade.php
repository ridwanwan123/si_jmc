@props(['kategori'])
{{-- Label kategori berita: PENGUMUMAN (hitam), LIPUTAN (biru), HASIL (kuning). --}}
<span {{ $attributes->class(['inline-block rounded px-1.5 py-0.5 font-mono text-[11px] font-bold leading-none tracking-wide', $kategori->kelasChip()]) }}>{{ mb_strtoupper($kategori->label()) }}</span>
