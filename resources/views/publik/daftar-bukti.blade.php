<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Bukti Pendaftaran {{ $p->nomor }} · {{ $p->edisi->nama }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,500..900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 16mm; }
        @media print { .jangan-cetak { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body class="min-h-screen">
    <main class="mx-auto max-w-[760px] px-4 py-10 sm:px-6 print:p-0">
        <div class="jangan-cetak mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('daftar') }}" class="inline-flex min-h-11 items-center gap-2 font-bold text-ink">← Kembali ke pendaftaran</a>
            <button type="button" onclick="window.print()" class="inline-flex min-h-11 cursor-pointer items-center rounded-full bg-ink px-5 font-bold text-white">Cetak / simpan PDF</button>
        </div>

        <article class="rounded-xl border border-line bg-white px-6 py-8 sm:px-9 print:border-ink">
            <header class="flex flex-wrap items-center justify-between gap-4 border-b-2 border-ink pb-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-ikon.png') }}" alt="Logo JMC" class="h-12 w-auto">
                    <div>
                        <div class="font-judul-tegas text-xl leading-tight">Jakarta Madrasah Competition</div>
                        <div class="text-[13px] text-muted">Bidang Pendidikan Madrasah · Kanwil Kemenag Provinsi DKI Jakarta</div>
                    </div>
                </div>
                <div class="label-mono text-xs">{{ strtoupper($p->edisi->nama) }}</div>
            </header>

            <h1 class="font-judul-tegas mt-6 text-[26px] tracking-[-0.5px]">Bukti Pendaftaran</h1>

            <div class="mt-4 rounded-lg border border-dashed border-ink px-5 py-4">
                <div class="label-mono text-[11px]">NOMOR REGISTRASI</div>
                <div class="mt-1 font-mono text-[28px] font-bold tracking-wide">{{ $p->nomor }}</div>
            </div>

            <dl class="mt-6">
                @php
                    $baris = [
                        'MADRASAH' => $p->madrasah->nama.' ('.$p->madrasah->jenjang->value.') · NPSN '.$p->madrasah->npsn,
                        'CABOR' => $p->lomba->cabor->nama,
                        'MATA LOMBA' => $p->lomba->namaLengkap().' · '.$p->lomba->labelAnggota(),
                        'PENDAMPING' => $p->guru_nama.' · '.$p->guru_wa,
                        'TANGGAL DAFTAR' => $p->created_at->timezone(config('jmc.timezone'))->format('d.m.Y · H:i').' WIB',
                        'STATUS' => $p->status->label(),
                    ];
                @endphp
                @foreach ($baris as $label => $isi)
                    <div class="flex flex-wrap gap-x-4 gap-y-0.5 border-b border-garis-halus py-3">
                        <dt class="w-36 flex-none pt-0.5 font-mono text-xs font-bold text-muted">{{ $label }}</dt>
                        <dd class="min-w-0 flex-1 text-[15px] font-semibold">{{ $isi }}</dd>
                    </div>
                @endforeach
            </dl>

            <h2 class="mt-7 text-lg font-extrabold">{{ $p->lomba->tim() ? 'Anggota tim' : 'Peserta' }}</h2>
            <table class="mt-2 w-full text-left text-[15px]">
                <thead class="border-b-2 border-ink font-mono text-[11px] text-muted">
                    <tr><th class="w-12 py-2">NO</th><th class="py-2">NAMA LENGKAP</th><th class="py-2">{{ $p->lomba->cabor->slug === 'gtk' ? 'NUPTK' : 'NISN' }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($p->peserta as $s)
                        <tr class="border-b border-garis-halus">
                            <td class="py-2.5 font-mono text-xs font-bold text-muted">{{ nomor($loop->iteration) }}</td>
                            <td class="py-2.5 font-semibold">
                                {{ $s->nama }}
                                @if ($p->lomba->tim() && $loop->first)
                                    <span class="ml-1.5 rounded-full border border-ink px-2 py-0.5 align-middle font-mono text-[10px] font-bold">KAPTEN</span>
                                @endif
                            </td>
                            <td class="py-2.5 font-mono">{{ $s->nisn }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="mt-7 text-sm leading-relaxed text-teks-2">
                Simpan bukti ini. Panitia memverifikasi data dan surat tugas, lalu mengirim konfirmasi serta jadwal tanding ke WhatsApp guru pendamping.
                Tunjukkan nomor registrasi saat daftar ulang di lokasi lomba.
            </p>
        </article>
    </main>
</body>
</html>
