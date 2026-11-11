<?php

namespace Database\Seeders;

use App\Data\BarisKlasemen;
use App\Enums\KategoriBerita;
use App\Enums\StatusPendaftaran;
use App\Models\Berita;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\Pendaftaran;
use App\Services\HasilService;
use App\Services\KlasemenService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DATA DEMO untuk development: semua lomba edisi aktif diisi juara sesuai kuota, setiap juara
 * berasal dari pendaftaran yang sudah diverifikasi, ditambah satu berita rekap juara per cabor
 * dan berita contoh (BeritaContohSeeder).
 *
 * Menjalankan ulang MENGHAPUS hasil, pendaftaran, dan berita rekap edisi aktif lalu membuat ulang.
 * Jangan dijalankan di data sungguhan.
 *
 * php artisan db:seed --class=DemoJmcSeeder
 */
class DemoJmcSeeder extends Seeder
{
    private const PUTRA_DEPAN = ['Ahmad', 'Muhammad', 'Rizky', 'Fajar', 'Hafiz', 'Naufal', 'Farhan', 'Dimas', 'Raihan', 'Ilham', 'Zaki', 'Fikri'];

    private const PUTRA_BELAKANG = ['Pratama', 'Ramadhan', 'Hakim', 'Saputra', 'Maulana', 'Firdaus', 'Hidayat', 'Kurniawan', 'Rahman', 'Fauzan'];

    private const PUTRI_DEPAN = ['Aisyah', 'Annisa', 'Nabila', 'Zahra', 'Salsabila', 'Keisha', 'Nadia', 'Syifa', 'Khansa', 'Alya', 'Hana', 'Fatimah'];

    private const PUTRI_BELAKANG = ['Rahma', 'Putri', 'Azzahra', 'Nurhaliza', 'Lestari', 'Amalia', 'Utami', 'Safitri', 'Maharani', 'Kirana'];

    private const GURU = ['Siti Aminah, S.Pd.', 'Ahmad Fauzi, S.Ag.', 'Nur Hasanah, M.Pd.', 'Budi Santoso, S.Pd.I.', 'Dewi Kartika, S.Pd.',
        'Hendra Gunawan, M.Ag.', 'Rina Marlina, S.Si.', 'Yusuf Mansur, S.Pd.'];

    private int $nomor = 0;

    public function run(): void
    {
        mt_srand(2026);

        $edisi = Edisi::query()->where('is_aktif', true)->first();
        if (! $edisi) {
            $this->command?->warn('Belum ada edisi aktif. Jalankan DatabaseSeeder dulu.');

            return;
        }

        $this->bersihkan($edisi);

        $lomba = Lomba::query()->where('edisi_id', $edisi->id)->with('cabor')->orderBy('id')->get();
        $kandidat = $this->kandidatPerJenjang();
        $awal = $edisi->pendaftaran_buka ?? now()->subDays(10);

        DB::transaction(function () use ($lomba, $kandidat, $edisi, $awal) {
            foreach ($lomba as $l) {
                $kuota = [1 => $l->kuota_juara_1, 2 => $l->kuota_juara_2, 3 => $l->kuota_juara_3];
                $madrasah = $this->pilihMadrasah($kandidat[$l->jenjang->value], array_sum($kuota));

                $baris = [];
                $i = 0;
                foreach ($kuota as $peringkat => $n) {
                    for ($k = 0; $k < $n; $k++) {
                        $p = $this->daftar($edisi, $l, $madrasah[$i++], $awal);
                        $baris[] = [
                            'peringkat' => $peringkat,
                            'madrasah_id' => $p->madrasah_id,
                            'pendaftaran_id' => $p->id,
                            'nama_peserta' => $p->namaPeserta(),
                        ];
                    }
                }

                app(HasilService::class)->simpan($l, $baris);
            }
        });

        $this->call(BeritaContohSeeder::class);
        $this->beritaRekap($edisi);

        $this->command?->info(sprintf('%s: %d lomba, %d pendaftaran terverifikasi, %d juara, %d berita.',
            $edisi->nama, $lomba->count(), Pendaftaran::where('edisi_id', $edisi->id)->count(),
            Hasil::query()->edisi($edisi)->count(), Berita::where('edisi_id', $edisi->id)->count()));
    }

    private function bersihkan(Edisi $edisi): void
    {
        Hasil::query()->edisi($edisi)->delete();
        Pendaftaran::query()->where('edisi_id', $edisi->id)->get()->each->delete(); // ikut hapus surat tugas
        Berita::query()->where('edisi_id', $edisi->id)->where('slug', 'like', 'rekap-juara-%')->delete();
    }

    /**
     * Madrasah per jenjang: 10 "unggulan" (sering juara, agar klasemen menarik) + sisanya.
     *
     * @return array<string, array{unggulan: Collection<int, Madrasah>, lain: Collection<int, Madrasah>}>
     */
    private function kandidatPerJenjang(): array
    {
        $hasil = [];
        foreach (['RA', 'MI', 'MTs', 'MA'] as $j) {
            $semua = Madrasah::query()->where('jenjang', $j)->orderByRaw("status = 'Negeri' DESC")->orderBy('id')->get(['id', 'nama', 'jenjang']);
            $hasil[$j] = ['unggulan' => $semua->take(10)->values(), 'lain' => $semua->slice(10)->values()];
        }

        return $hasil;
    }

    /** Ambil $n madrasah berbeda: 65% peluang dari kelompok unggulan. */
    private function pilihMadrasah(array $kandidat, int $n): array
    {
        $dipilih = [];
        while (count($dipilih) < $n) {
            $kolam = mt_rand(1, 100) <= 65 || $kandidat['lain']->isEmpty() ? $kandidat['unggulan'] : $kandidat['lain'];
            $m = $kolam[mt_rand(0, $kolam->count() - 1)];
            $dipilih[$m->id] = $m;
        }

        return array_values($dipilih);
    }

    private function daftar(Edisi $edisi, Lomba $lomba, Madrasah $madrasah, $awal): Pendaftaran
    {
        $gtk = $lomba->cabor->slug === 'gtk';
        // Tim cukup 2–4 orang (tidak harus penuh); anggota pertama = kapten.
        $jumlah = $lomba->tim() ? min($lomba->jumlah_anggota, mt_rand(2, 4)) : 1;
        $waktu = $awal->copy()->addHours(mt_rand(1, 160));

        $p = Pendaftaran::create([
            'edisi_id' => $edisi->id,
            'lomba_id' => $lomba->id,
            'madrasah_id' => $madrasah->id,
            'nomor' => 'JMC'.substr((string) $edisi->tahun, -2).'-'.str_pad((string) ++$this->nomor, 4, '0', STR_PAD_LEFT),
            'guru_nama' => self::GURU[mt_rand(0, count(self::GURU) - 1)],
            'guru_wa' => '08'.mt_rand(11, 99).mt_rand(1000, 9999).mt_rand(1000, 9999),
            'status' => StatusPendaftaran::Diverifikasi,
            'diverifikasi_pada' => $waktu->copy()->addDay(),
        ]);
        $p->forceFill(['created_at' => $waktu, 'updated_at' => $waktu])->saveQuietly();

        for ($i = 1; $i <= $jumlah; $i++) {
            $nama = $this->namaAcak($lomba->kategori->value);
            $p->peserta()->create([
                'nama' => $gtk ? $nama.', S.Pd.' : $nama,
                'nisn' => $gtk ? '3171'.str_pad((string) mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT) : '00'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'urutan' => $i,
            ]);
        }

        return $p->load('peserta');
    }

    /** Nama sesuai kategori: Putra → nama laki-laki, Putri → perempuan, Umum/Beregu → campur. */
    private function namaAcak(string $kategori): string
    {
        $putri = match ($kategori) {
            'Putra' => false,
            'Putri' => true,
            default => mt_rand(0, 1) === 1,
        };
        $depan = $putri ? self::PUTRI_DEPAN : self::PUTRA_DEPAN;
        $belakang = $putri ? self::PUTRI_BELAKANG : self::PUTRA_BELAKANG;

        return $depan[mt_rand(0, count($depan) - 1)].' '.$belakang[mt_rand(0, count($belakang) - 1)];
    }

    /** Satu berita "Hasil" per cabor: peraih emas tiap lomba + pemuncak cabor. */
    private function beritaRekap(Edisi $edisi): void
    {
        $klasemen = app(KlasemenService::class);
        $cabor = Cabor::query()->orderBy('urutan')->get();

        foreach ($cabor as $i => $c) {
            $baris = $klasemen->klasemen($edisi, caborId: $c->id);
            if ($baris->isEmpty()) {
                continue;
            }

            $pemuncak = $baris->filter(fn (BarisKlasemen $b) => $b->peringkat === 1);
            $namaPemuncak = $pemuncak->pluck('nama')->join(', ', ' dan ');
            $emas = Hasil::query()->edisi($edisi)->where('peringkat', 1)
                ->whereHas('lomba', fn ($q) => $q->where('cabor_id', $c->id))
                ->with(['lomba', 'madrasah'])->get()
                ->sortBy(fn (Hasil $h) => [array_search($h->lomba->jenjang->value, ['RA', 'MI', 'MTs', 'MA'], true), $h->lomba->nama]);

            $tabel = $emas->map(fn (Hasil $h) => '<tr><td>'.e($h->lomba->namaLengkap()).'</td><td>'.e($h->madrasah->nama)
                .($h->nama_peserta ? ' — '.e(str_replace("\n", ', ', $h->nama_peserta)) : '').'</td></tr>')->implode('');
            $top = $baris->take(3)->map(fn (BarisKlasemen $b) => '<li><strong>'.e($b->nama).'</strong>: '
                ."{$b->emas} emas, {$b->perak} perak, {$b->perunggu} perunggu</li>")->implode('');

            $isi = '<p>Seluruh lomba cabor '.e($c->nama).' di '.e($edisi->nama).' telah selesai dinilai. '
                .$emas->count().' medali emas diperebutkan, dan '.e($namaPemuncak).' tampil sebagai pemuncak cabor.</p>'
                .'<h2>Peraih medali emas</h2><table><tbody>'.$tabel.'</tbody></table>'
                .'<h2>Tiga besar cabor '.e($c->nama).'</h2><ol>'.$top.'</ol>'
                .'<p>Daftar lengkap juara 1, 2, dan 3 setiap lomba dapat dilihat di halaman Cabor &amp; Lomba. Perolehan medali sudah masuk ke klasemen umum.</p>';

            Berita::create([
                'edisi_id' => $edisi->id,
                'cabor_id' => $c->id,
                'kategori' => KategoriBerita::Hasil,
                'judul' => "Rekap Juara Cabor {$c->nama}: {$namaPemuncak} Jadi Pemuncak",
                'slug' => 'rekap-juara-'.$c->slug.'-'.$edisi->tahun,
                'ringkasan' => "Hasil lengkap {$emas->count()} lomba cabor {$c->nama}. {$namaPemuncak} memimpin perolehan medali cabor ini.",
                'isi' => $isi,
                'tag' => ['Hasil', $c->nama, 'JMC'.$edisi->tahun],
                // Terbit berurutan beberapa hari terakhir, tidak di masa depan.
                'terbit_pada' => now()->subDays(count($cabor) - $i)->setTime(16, 0)->min(now()),
            ]);
        }
    }
}
