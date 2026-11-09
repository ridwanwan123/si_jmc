{{-- Tab halaman cabor: Lomba & Juara | Peserta --}}
<x-filament::tabs label="Bagian cabor">
    <x-filament::tabs.item tag="a" :href="$url('lomba')" :active="$tab === 'lomba'" icon="heroicon-o-trophy"
                           :badge="$jumlah['lomba']">
        Lomba &amp; Juara
    </x-filament::tabs.item>
    <x-filament::tabs.item tag="a" :href="$url('peserta')" :active="$tab === 'peserta'" icon="heroicon-o-users"
                           :badge="$jumlah['menunggu'] > 0 ? $jumlah['menunggu'].' menunggu' : $jumlah['peserta']"
                           :badge-color="$jumlah['menunggu'] > 0 ? 'warning' : null">
        Peserta
    </x-filament::tabs.item>
</x-filament::tabs>
