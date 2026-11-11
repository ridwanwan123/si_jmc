@props(['pilihan', 'label' => 'Filter jenjang', 'gulir' => false])
{{--
    Segmented control berbasis link (filter lewat query string).
    $pilihan: list of [label, url, aktif(bool)].
    gulir: tetap satu baris dan bisa digeser horizontal di layar sempit (bukan turun baris).
--}}
<div role="group" aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'flex max-w-full rounded-full bg-pill p-1 '.($gulir ? 'overflow-x-auto [scrollbar-width:none]' : 'flex-wrap')]) }}>
    @foreach ($pilihan as [$teks, $url, $aktif])
        <a href="{{ $url }}" @if ($aktif) aria-current="true" @endif
           class="flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full px-4 text-sm no-underline sm:px-5 {{ $aktif ? 'bg-ink font-bold text-white hover:text-white' : 'font-semibold text-teks-2' }}">{{ $teks }}</a>
    @endforeach
</div>
