<x-filament-widgets::widget>
    <x-filament::section heading="Klasemen 5 besar" :description="$edisi?->nama">
        @if ($baris->isEmpty())
            <p style="opacity:.7">Belum ada hasil yang diinput.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid currentColor">
                            <th style="padding:.5rem">#</th>
                            <th style="padding:.5rem">Madrasah</th>
                            <th style="padding:.5rem;text-align:right;color:#B8860B">Emas</th>
                            <th style="padding:.5rem;text-align:right">Perak</th>
                            <th style="padding:.5rem;text-align:right;color:#B8692E">Perunggu</th>
                            <th style="padding:.5rem;text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr style="border-bottom:1px solid rgba(127,127,127,.25)">
                                <td style="padding:.5rem;font-family:monospace;font-weight:700">{{ $b->labelPeringkat() }}</td>
                                <td style="padding:.5rem">{{ $b->nama }} <span style="opacity:.6">· {{ $b->jenjang->value }}</span></td>
                                <td style="padding:.5rem;text-align:right;font-family:monospace">{{ $b->emas }}</td>
                                <td style="padding:.5rem;text-align:right;font-family:monospace">{{ $b->perak }}</td>
                                <td style="padding:.5rem;text-align:right;font-family:monospace">{{ $b->perunggu }}</td>
                                <td style="padding:.5rem;text-align:right;font-family:monospace;font-weight:700">{{ $b->total() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
