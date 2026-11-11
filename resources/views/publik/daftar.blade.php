<x-layouts.publik judul="Pendaftaran Peserta" deskripsi="Formulir pendaftaran peserta Jakarta Madrasah Competition (JMC).">
    @php
        $tahap = $edisi->tahap();
        $belumBuka = $tahap === \App\Enums\Tahap::Registrasi && $edisi->pendaftaran_buka?->isFuture();
        $langkah = ['Madrasah', 'Cabor', 'Mata lomba', 'Data peserta', 'Preview'];
    @endphp

    <main class="mx-auto max-w-[820px] px-4 pb-20 pt-10 sm:px-6"
          @if ($dibuka)
          x-data="formDaftar(@js(['madrasah' => $madrasah, 'cabor' => $cabor, 'lomba' => $lomba, 'terdaftar' => $terdaftar, 'url' => route('daftar.store')]))"
          x-init="$watch('langkah', () => $nextTick(() => $refs.atas.scrollIntoView({ block: 'start' })))"
          @endif>

        {{-- JUDUL --}}
        <div x-ref="atas" class="label-mono mb-2 scroll-mt-4 text-xs">
            {{ strtoupper($edisi->nama) }}
            @if ($dibuka)
                · DIBUKA {{ $edisi->labelTutupPendaftaran() }}
            @elseif ($belumBuka)
                · PENDAFTARAN DIBUKA {{ $edisi->pendaftaran_buka->format('d.m.Y') }}
            @else
                · PENDAFTARAN DITUTUP
            @endif
        </div>
        <h1 class="judul-halaman text-[clamp(30px,4vw,44px)]">Pendaftaran <span class="text-accent">Peserta</span></h1>

        @if (! $dibuka)
            <section class="mt-8 rounded-xl border border-line bg-white px-6 py-7">
                <h2 class="text-2xl font-extrabold">{{ $belumBuka ? 'Pendaftaran belum dibuka' : 'Pendaftaran ditutup' }}</h2>
                <p class="mt-2 text-[15px] leading-relaxed text-teks-2">
                    @if ($belumBuka)
                        Pendaftaran {{ $edisi->nama }} dibuka mulai {{ $edisi->pendaftaran_buka->translatedFormat('j F Y') }}{{ $edisi->pendaftaran_tutup ? ' sampai '.$edisi->pendaftaran_tutup->translatedFormat('j F Y') : '' }}.
                    @elseif ($edisi->exists)
                        Masa pendaftaran {{ $edisi->nama }} sudah berakhir. Ikuti hasil pertandingan di halaman klasemen.
                    @else
                        Jadwal pendaftaran belum diumumkan panitia. Silakan kembali lagi nanti.
                    @endif
                </p>
                <a href="{{ rute('klasemen') }}" class="mt-5 inline-flex min-h-12 items-center gap-2 rounded-full bg-ink px-6 font-bold text-white no-underline hover:text-white">Lihat klasemen →</a>
            </section>
            <p class="mt-10 text-[15px] text-teks-2">Butuh bantuan? Hubungi panitia: {{ $kontak ?: 'Bidang Pendidikan Madrasah, Kanwil Kemenag Provinsi DKI Jakarta' }}</p>
        @else
        <p class="mt-2.5 text-base leading-relaxed text-teks-2">Satu formulir untuk satu lomba. Ingin mendaftarkan lomba lain? Setelah selesai, klik "Daftarkan lomba lain".</p>

        {{-- PROGRES 01–05 --}}
        <ol aria-label="Langkah pendaftaran" class="mt-7 grid grid-cols-5 gap-1.5">
            @foreach ($langkah as $i => $label)
                @php $n = $i + 1; @endphp
                <li class="min-w-0" :aria-current="langkah === {{ $n }} ? 'step' : null">
                    <div class="h-1 rounded-sm" :class="langkah === {{ $n }} ? 'bg-accent' : (langkah > {{ $n }} ? 'bg-ink' : 'bg-line')"></div>
                    <div class="mt-2 font-mono text-[11px] font-bold" :class="langkah === {{ $n }} ? 'text-accent' : (langkah > {{ $n }} ? 'text-ink' : 'text-[#8A8C92]')">{{ nomor($n) }}</div>
                    <div class="truncate text-[13px]" :class="langkah === {{ $n }} ? 'font-bold text-ink' : (langkah > {{ $n }} ? 'font-medium text-ink' : 'font-medium text-[#6B6D73]')">{{ $label }}</div>
                </li>
            @endforeach
        </ol>

        {{-- PILIHAN SEJAUH INI --}}
        <div x-show="langkah > 1 && langkah <= 5" x-cloak class="mt-6 flex flex-wrap gap-2">
            <template x-for="chip in chips()" :key="chip.label">
                <button type="button" @click="langkah = chip.ke"
                        class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full border border-pill-line bg-white px-3.5 text-left text-sm">
                    <span class="font-mono text-[11px] font-bold text-muted" x-text="chip.label"></span>
                    <span class="font-bold" x-text="chip.isi"></span>
                    <span class="text-[13px] font-semibold text-accent">Ubah</span>
                </button>
            </template>
        </div>

        {{-- ===== SELESAI ===== --}}
        <section x-show="langkah === 6" x-cloak class="mt-8 overflow-hidden rounded-xl border-t-4 border-live bg-white px-6 py-8 sm:px-8">
            <span class="flex size-13 items-center justify-center rounded-full bg-live/20 text-sukses">
                <x-ikon nama="centang" class="size-6" stroke="2.5" />
            </span>
            <h2 class="font-judul-tegas mt-5 text-[28px] tracking-[-0.5px]">Pendaftaran terkirim</h2>
            <p class="mt-2 text-[17px] leading-relaxed text-teks-2">
                <b class="text-ink" x-text="terkirim?.lomba"></b>
                (<span x-text="terkirim?.cabor"></span>) untuk <b class="text-ink" x-text="terkirim?.madrasah"></b>
                sudah kami terima dan sedang diverifikasi panitia.
            </p>
            <div class="mt-6 rounded-lg border border-dashed border-ink px-5 py-4">
                <div class="label-mono text-[11px]">NOMOR REGISTRASI</div>
                <div class="mt-1.5 font-mono text-[28px] font-bold tracking-wide" x-text="terkirim?.nomor"></div>
            </div>
            <p class="mt-5 text-[15px] text-teks-2">Simpan nomor ini. Konfirmasi verifikasi dikirim ke WhatsApp guru pendamping.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" @click="lombaLain()" class="inline-flex min-h-13 cursor-pointer items-center rounded-full bg-accent px-6 font-bold text-white hover:bg-accent-tua">Daftarkan lomba lain</button>
                <a :href="terkirim?.bukti" target="_blank" rel="noopener" class="inline-flex min-h-13 items-center rounded-full border border-ink px-6 font-bold text-ink no-underline">Unduh bukti pendaftaran</a>
            </div>
        </section>

        {{-- ===== FORMULIR ===== --}}
        <form x-show="langkah <= 5" @submit.prevent="lanjut()" novalidate class="mt-7">

            {{-- 1. MADRASAH --}}
            <section x-show="langkah === 1" class="flex flex-col gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold">Pilih madrasah</h2>
                    <p class="mt-1.5 text-[15px] text-muted">Cari nama madrasah atau NPSN. Lomba yang muncul nanti menyesuaikan jenjang madrasah.</p>
                </div>
                <label class="flex min-h-13 items-center gap-3 rounded-xl border border-ink bg-white px-4">
                    <x-ikon nama="cari" class="size-4.5 text-muted" />
                    <span class="sr-only">Cari madrasah</span>
                    <input type="search" x-model="cari" placeholder="Ketik nama madrasah atau NPSN…" autocomplete="off"
                           class="min-w-0 flex-1 bg-transparent py-3 text-base outline-none placeholder:text-[#8A8C92]">
                </label>
                <div role="radiogroup" aria-label="Madrasah" class="flex flex-col gap-2.5">
                    <template x-for="m in hasilCari()" :key="m.id">
                        <button type="button" role="radio" :aria-checked="pilihan.madrasah === m.id" @click="pilihMadrasah(m.id)"
                                :class="pilihan.madrasah === m.id ? 'border-accent ring-1 ring-accent' : 'border-line hover:border-pill-line'"
                                class="flex min-h-15 w-full cursor-pointer items-center gap-3.5 rounded-xl border-2 bg-white px-4 py-3 text-left">
                            <span class="flex size-10 flex-none items-center justify-center rounded-lg bg-ink font-judul text-[13px] font-extrabold text-white" x-text="m.jenjang"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-base font-bold" x-text="m.nama"></span>
                                <span class="block truncate text-[13px] text-teks-2" x-text="['NPSN ' + m.npsn, m.status, m.kelurahan, m.kota].filter(Boolean).join(' · ')"></span>
                            </span>
                            <span class="flex size-6 flex-none items-center justify-center rounded-full border-2"
                                  :class="pilihan.madrasah === m.id ? 'border-accent bg-accent text-white' : 'border-[#8A8C92]'">
                                <x-ikon nama="centang" class="size-3.5" stroke="3" x-show="pilihan.madrasah === m.id" />
                            </span>
                        </button>
                    </template>
                    <p x-show="data.madrasah.length === 0" class="rounded-xl border border-line bg-white px-4 py-5 text-sm text-muted">Data madrasah belum tersedia. Silakan hubungi panitia.</p>
                    <p x-show="data.madrasah.length > 0 && hasilCari().length === 0" class="rounded-xl border border-line bg-white px-4 py-5 text-sm text-muted">Madrasah tidak ditemukan. Coba kata lain atau periksa NPSN.</p>
                    <p x-show="sisaCari() > 0" class="text-[13px] text-muted" x-text="'+' + sisaCari() + ' madrasah lain. Ketik nama atau NPSN untuk mempersempit.'"></p>
                </div>
            </section>

            {{-- 2. CABOR --}}
            <section x-show="langkah === 2" x-cloak class="flex flex-col gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold">Pilih cabang lomba</h2>
                    <p class="mt-1.5 text-[15px] text-muted">Angka di setiap kartu adalah jumlah lomba yang tersedia untuk jenjang <span x-text="madrasahTerpilih()?.jenjang"></span>.</p>
                </div>
                <div role="radiogroup" aria-label="Cabang lomba" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($cabor as $c)
                        <button type="button" role="radio" :aria-checked="pilihan.cabor === {{ $c['id'] }}" @click="pilihCabor({{ $c['id'] }})"
                                :disabled="jumlahLomba({{ $c['id'] }}) === 0"
                                :class="pilihan.cabor === {{ $c['id'] }} ? 'border-accent ring-1 ring-accent' : 'border-line enabled:hover:border-pill-line'"
                                class="flex min-h-32 cursor-pointer flex-col rounded-xl border-2 bg-white p-4 text-left disabled:cursor-not-allowed disabled:opacity-45">
                            <span class="flex items-start justify-between">
                                <x-ikon :nama="$c['ikon']" class="size-6" stroke="1.8" />
                                <span class="font-mono text-xs font-bold text-accent">{{ $c['nomor'] }}</span>
                            </span>
                            <span class="mt-auto pt-6 text-[17px] font-bold">{{ $c['nama'] }}</span>
                            <span class="text-[13px] text-teks-2" x-text="jumlahLomba({{ $c['id'] }}) + ' lomba'"></span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- 3. MATA LOMBA --}}
            <section x-show="langkah === 3" x-cloak class="flex flex-col gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold">Pilih mata lomba</h2>
                    <p class="mt-1.5 text-[15px] text-muted">
                        Lomba <span x-text="caborTerpilih()?.nama"></span> untuk jenjang <span x-text="madrasahTerpilih()?.jenjang"></span>.
                        Satu madrasah mengirim satu peserta atau satu tim per lomba.
                    </p>
                </div>
                <div role="radiogroup" aria-label="Mata lomba" class="flex flex-col gap-2.5">
                    <template x-for="l in daftarLomba()" :key="l.id">
                        <button type="button" role="radio" :aria-checked="pilihan.lomba === l.id" @click="pilihLomba(l.id)"
                                :disabled="sudahTerdaftar(l.id)"
                                :class="pilihan.lomba === l.id ? 'border-accent ring-1 ring-accent' : 'border-line enabled:hover:border-pill-line'"
                                class="flex min-h-15 w-full cursor-pointer flex-wrap items-center gap-x-3.5 gap-y-1 rounded-xl border-2 bg-white px-4 py-3 text-left disabled:cursor-not-allowed disabled:opacity-55">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full border-2"
                                  :class="pilihan.lomba === l.id ? 'border-accent bg-accent text-white' : 'border-[#8A8C92]'">
                                <x-ikon nama="centang" class="size-3.5" stroke="3" x-show="pilihan.lomba === l.id" />
                            </span>
                            <span class="flex min-w-0 flex-1 flex-wrap items-center gap-2.5">
                                <span class="text-[17px] font-bold" x-text="l.nama"></span>
                                <span class="rounded-full border border-pill-line px-2 py-0.5 text-xs font-semibold text-teks-2" x-text="l.kategori"></span>
                            </span>
                            <span x-show="sudahTerdaftar(l.id)" class="font-mono text-[11px] font-bold text-accent">SUDAH TERDAFTAR</span>
                            <span class="flex flex-none items-center gap-1.5 text-sm font-semibold text-teks-2">
                                <x-ikon nama="orang" class="size-4" stroke="1.8" x-show="l.anggota === 1" />
                                <x-ikon nama="orang-dua" class="size-4" stroke="1.8" x-show="l.anggota > 1" />
                                <span x-text="l.anggota > 1 ? 'Tim maks. ' + l.anggota + ' orang' : 'Perorangan'"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- 4. DATA PESERTA --}}
            <section x-show="langkah === 4" x-cloak class="flex flex-col gap-6">
                <div>
                    <h2 class="text-2xl font-extrabold">Data peserta</h2>
                    <p class="mt-1.5 text-[15px] text-muted"
                       x-text="tim() ? 'Lomba tim, maksimal ' + jumlahAnggota() + ' orang. Isi minimal kapten tim; anggota lain ditambahkan sesuai kebutuhan. Anggota pertama adalah kapten.' : 'Lomba ini perorangan. Isi data satu peserta.'"></p>
                </div>

                <section aria-label="Peserta" class="overflow-hidden rounded-[14px] border border-line bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-garis-halus px-5 py-4">
                        <span class="text-[17px] font-extrabold" x-text="tim() ? 'Anggota tim' : 'Peserta'"></span>
                        <span class="font-mono text-xs font-bold text-accent" x-text="tim() ? peserta.length + ' / ' + jumlahAnggota() + ' ANGGOTA' : '1 PESERTA'"></span>
                    </div>
                    <div class="px-5 pb-1 pt-1.5">
                        <div aria-hidden="true" class="hidden gap-3 border-b border-garis-halus pb-1.5 pt-2.5 font-mono text-[11px] font-bold text-muted sm:flex">
                            <span class="w-24 flex-none"></span>
                            <span class="flex-[3_1_0]">NAMA LENGKAP</span>
                            <span class="flex-[2_1_0]" x-text="labelNomor()">NISN</span>
                        </div>
                        <template x-for="(p, i) in peserta" :key="i">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-garis-halus py-3 last:border-b-0 sm:flex-nowrap">
                                <span class="w-full flex-none text-[15px] font-bold sm:w-24" x-text="labelPeserta(i)"></span>
                                <label class="min-w-0 flex-[3_1_0]">
                                    <span class="sr-only" x-text="'Nama lengkap ' + labelPeserta(i).toLowerCase()"></span>
                                    <input type="text" x-model.trim="p.nama" placeholder="Nama lengkap" autocomplete="off"
                                           :class="cobaLanjut && !p.nama ? 'border-red-600' : 'border-pill-line'"
                                           class="min-h-12 w-full rounded-lg border bg-white px-3.5 text-base outline-none focus:border-ink">
                                </label>
                                <label class="min-w-[150px] flex-[2_1_0]">
                                    <span class="sr-only" x-text="labelNomor() + ' ' + labelPeserta(i).toLowerCase()"></span>
                                    <input type="text" x-model.trim="p.nisn" :placeholder="gtk() ? 'NUPTK (16 digit)' : 'NISN (10 digit)'" inputmode="numeric" :maxlength="gtk() ? 16 : 10" autocomplete="off"
                                           :class="cobaLanjut && !nomorValid(p.nisn) ? 'border-red-600' : 'border-pill-line'"
                                           class="min-h-12 w-full rounded-lg border bg-white px-3.5 font-mono text-[15px] outline-none focus:border-ink">
                                </label>
                                <button type="button" x-show="tim()" @click="hapusAnggota(i)" :disabled="i === 0"
                                        :aria-label="'Hapus ' + labelPeserta(i).toLowerCase()"
                                        :class="i === 0 ? 'invisible' : ''"
                                        class="grid size-11 flex-none place-items-center rounded-lg border border-pill-line text-muted hover:border-ink hover:text-ink">
                                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>
                        </template>
                        <div x-show="tim()" class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <button type="button" @click="tambahAnggota()" x-show="bisaTambahAnggota()"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-full border border-ink px-4 text-[15px] font-semibold text-ink">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                Tambah anggota
                            </button>
                            <span x-show="!bisaTambahAnggota()" class="text-sm text-muted">Jumlah anggota sudah maksimal.</span>
                            <span class="text-[13px] text-muted">Hanya kapten yang wajib diisi.</span>
                        </div>
                    </div>
                </section>

                <section aria-label="Guru pendamping" class="overflow-hidden rounded-[14px] border border-line bg-white">
                    <div class="border-b border-garis-halus px-5 py-4">
                        <div class="text-[17px] font-extrabold">Guru pendamping</div>
                        <p class="mt-0.5 text-sm text-teks-2">Kami hubungi lewat WhatsApp untuk konfirmasi dan jadwal tanding.</p>
                    </div>
                    <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-[15px] font-semibold">Nama lengkap</span>
                            <input type="text" x-model.trim="guru.nama" autocomplete="name"
                                   :class="cobaLanjut && !guru.nama ? 'border-red-600' : 'border-pill-line'"
                                   class="min-h-12 w-full rounded-lg border bg-white px-3.5 text-base outline-none focus:border-ink">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[15px] font-semibold">No. WhatsApp</span>
                            <input type="tel" x-model.trim="guru.wa" placeholder="08xxxxxxxxxx" inputmode="tel" autocomplete="tel"
                                   :class="cobaLanjut && !waValid() ? 'border-red-600' : 'border-pill-line'"
                                   class="min-h-12 w-full rounded-lg border bg-white px-3.5 text-base outline-none focus:border-ink">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-[15px] font-semibold">Surat tugas dari madrasah <span class="font-normal text-muted">(PDF, maks 2 MB)</span></span>
                            <span class="block rounded-lg border border-dashed px-3.5 py-3"
                                  :class="cobaLanjut && !guru.surat ? 'border-red-600' : 'border-pill-line bg-[#FAFAF7]'">
                                <input type="file" x-ref="surat" accept="application/pdf,.pdf" @change="pilihSurat($event)"
                                       class="w-full text-sm file:mr-3 file:cursor-pointer file:rounded-full file:border file:border-ink file:bg-white file:px-4 file:py-2 file:font-semibold">
                            </span>
                        </label>
                    </div>
                </section>
            </section>

            {{-- 5. PREVIEW --}}
            <section x-show="langkah === 5" x-cloak class="flex flex-col gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold">Periksa kembali</h2>
                    <p class="mt-1.5 text-[15px] text-muted">Pastikan semua data sudah benar sebelum dikirim.</p>
                </div>
                <dl class="overflow-hidden rounded-[14px] border border-line bg-white px-5">
                    <template x-for="baris in ringkasan()" :key="baris.label">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-1 border-b border-garis-halus py-4 last:border-b-0">
                            <dt class="w-36 flex-none pt-0.5 font-mono text-xs font-bold text-muted" x-text="baris.label"></dt>
                            <dd class="min-w-0 flex-1 text-base font-semibold">
                                <template x-for="isi in baris.isi"><span class="block" x-text="isi"></span></template>
                            </dd>
                            <button type="button" @click="langkah = baris.ke" class="min-h-8 flex-none cursor-pointer text-[15px] font-semibold text-accent">Ubah</button>
                        </div>
                    </template>
                </dl>
                <label class="flex cursor-pointer items-start gap-3 py-1 text-[15px] leading-relaxed">
                    <input type="checkbox" x-model="setuju" class="mt-1 size-5 flex-none accent-accent">
                    <span>Saya menyatakan data di atas benar dan peserta adalah siswa/guru aktif di madrasah tersebut.</span>
                </label>
            </section>

            {{-- PESAN KESALAHAN --}}
            <p x-show="galat" x-cloak x-text="galat" role="alert" class="mt-5 text-[15px] font-semibold text-red-700"></p>

            {{-- NAVIGASI --}}
            <div class="mt-7 flex items-center justify-between gap-3 border-t border-line pt-5">
                <button type="button" x-show="langkah > 1" x-cloak @click="kembali()"
                        class="inline-flex min-h-13 cursor-pointer items-center gap-2.5 rounded-full border border-ink px-6 font-bold">
                    <x-ikon nama="panah-kiri" class="size-4" stroke="2.5" />Kembali
                </button>
                <span x-show="langkah === 1"></span>
                <button type="submit" x-show="langkah < 5"
                        class="inline-flex min-h-13 cursor-pointer items-center gap-2.5 rounded-full bg-ink px-6.5 font-bold text-white">
                    <span x-text="langkah === 4 ? 'Lihat preview' : 'Lanjut'"></span>
                    <x-ikon nama="panah-kanan" class="size-4" stroke="2.5" />
                </button>
                <button type="submit" x-show="langkah === 5" x-cloak :disabled="mengirim"
                        class="inline-flex min-h-13 cursor-pointer items-center rounded-full bg-accent px-7 font-bold text-white hover:bg-accent-tua disabled:cursor-wait disabled:opacity-70"
                        x-text="mengirim ? 'Mengirim…' : 'Kirim pendaftaran'">Kirim pendaftaran</button>
            </div>
        </form>

        <p class="mt-10 text-[15px] text-teks-2">Butuh bantuan? Hubungi panitia: {{ $kontak ?: 'Bidang Pendidikan Madrasah, Kanwil Kemenag Provinsi DKI Jakarta' }}</p>
        @endif
    </main>
</x-layouts.publik>
