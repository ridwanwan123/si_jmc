@props(['emas' => 0, 'perak' => 0, 'perunggu' => 0, 'maks' => 1, 'tinggi' => 'h-2.5'])
{{-- Batang bertumpuk emas/perak/perunggu; lebar total sebanding (total / maks). --}}
@php
    $total = $emas + $perak + $perunggu;
    $lebar = $maks > 0 ? round($total / $maks * 100, 1) : 0;
@endphp
<span {{ $attributes->merge(['class' => 'flex '.$tinggi]) }} role="img" aria-label="{{ $emas }} emas, {{ $perak }} perak, {{ $perunggu }} perunggu">
    <span class="flex gap-0.5" style="width: {{ $lebar }}%">
        @if ($emas)<span class="bg-emas {{ $tinggi }}" style="flex: {{ $emas }} 0 0"></span>@endif
        @if ($perak)<span class="bg-perak {{ $tinggi }}" style="flex: {{ $perak }} 0 0"></span>@endif
        @if ($perunggu)<span class="bg-perunggu {{ $tinggi }}" style="flex: {{ $perunggu }} 0 0"></span>@endif
    </span>
</span>
