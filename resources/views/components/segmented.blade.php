@props(['pilihan', 'label' => 'Filter jenjang'])
{{--
    Segmented control berbasis link (filter lewat query string).
    $pilihan: list of [label, url, aktif(bool)].
--}}
<div role="group" aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'flex max-w-full flex-wrap rounded-full bg-pill p-1']) }}>
    @foreach ($pilihan as [$teks, $url, $aktif])
        <a href="{{ $url }}" @if ($aktif) aria-current="true" @endif
           class="flex min-h-10 items-center rounded-full px-4 text-sm no-underline sm:px-5 {{ $aktif ? 'bg-ink font-bold text-white hover:text-white' : 'font-semibold text-teks-2' }}">{{ $teks }}</a>
    @endforeach
</div>
