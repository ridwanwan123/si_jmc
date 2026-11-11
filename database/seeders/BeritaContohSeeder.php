<?php

namespace Database\Seeders;

use App\Enums\KategoriBerita;
use App\Models\Berita;
use App\Models\Cabor;
use App\Models\Edisi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Berita contoh (pengumuman & liputan) untuk melihat tampilan. Bukan data wajib; dipanggil DemoJmcSeeder.
 * Berita hasil dibuat DemoJmcSeeder dari juara sungguhan.
 * Dimasukkan ke edisi aktif. Aman dijalankan ulang (berdasarkan slug).
 *
 * php artisan db:seed --class=BeritaContohSeeder
 */
class BeritaContohSeeder extends Seeder
{
    public function run(): void
    {
        $edisi = Edisi::query()->where('is_aktif', true)->first() ?? Edisi::query()->orderByDesc('tahun')->first();
        if (! $edisi) {
            $this->command?->warn('Belum ada edisi. Buat edisi dulu.');

            return;
        }

        $cabor = Cabor::query()->pluck('id', 'slug');
        $t = $edisi->tahun;
        $nama = $edisi->nama;
        $tutup = $edisi->pendaftaran_tutup?->translatedFormat('j F Y') ?? "20 Oktober {$t}";
        $mulai = $edisi->tanggal_mulai?->translatedFormat('j F Y') ?? "3 November {$t}";
        $selesai = $edisi->tanggal_selesai?->translatedFormat('j F Y') ?? "7 November {$t}";
        $lokasi = $edisi->lokasi ?: 'Jakarta';

        $daftar = [
            [
                'judul' => "Pendaftaran {$nama} Resmi Dibuka, Tujuh Cabang Lomba Siap Dipertandingkan",
                'kategori' => KategoriBerita::Pengumuman,
                'cabor' => null,
                'tanggal' => "{$t}-11-02 08:00",
                'unggulan' => true,
                'ringkasan' => "Madrasah se-DKI Jakarta dari jenjang RA hingga MA dapat mendaftarkan siswa dan gurunya secara daring hingga {$tutup}. Satu formulir untuk satu mata lomba.",
                'tag' => ['Pendaftaran', "JMC{$t}"],
                'isi' => <<<HTML
<p>Kanwil Kemenag Provinsi DKI Jakarta melalui Bidang Pendidikan Madrasah membuka pendaftaran Jakarta Madrasah Competition (JMC) {$t}. Kompetisi tahunan ini mempertemukan siswa dan guru madrasah se-DKI Jakarta dari jenjang RA, MI, MTs, hingga MA.</p>
<p>Tahun ini JMC mempertandingkan tujuh cabang lomba: Keagamaan, Sains, Olahraga, Seni, Riset, Robotik, dan GTK. Setiap cabang terdiri dari beberapa mata lomba yang disesuaikan dengan jenjang madrasah.</p>
<h2>Cara mendaftar</h2>
<p>Pendaftaran dilakukan secara daring melalui halaman Daftar di website ini. Satu formulir digunakan untuk satu mata lomba, dengan langkah sebagai berikut:</p>
<ol>
<li>Pilih madrasah, lalu pilih cabang dan mata lomba.</li>
<li>Isi data peserta dan guru pendamping, serta unggah surat tugas.</li>
<li>Periksa kembali data, lalu kirim untuk mendapatkan nomor registrasi.</li>
</ol>
<blockquote><p>JMC adalah ruang bagi siswa madrasah untuk menunjukkan bahwa prestasi akademik, seni, olahraga, dan karakter bisa tumbuh bersama.</p><cite>Panitia {$nama}</cite></blockquote>
<h2>Jadwal penting</h2>
<table><tbody>
<tr><td>{$tutup}</td><td>Pendaftaran ditutup</td></tr>
<tr><td>{$mulai}</td><td>Pelaksanaan lomba dimulai</td></tr>
<tr><td>{$selesai}</td><td>Penutupan dan pengumuman juara umum</td></tr>
</tbody></table>
<p>Informasi lebih lanjut dapat ditanyakan kepada panitia melalui kontak yang tercantum di halaman Daftar.</p>
HTML,
            ],
            [
                'judul' => 'Batas Pendaftaran Diperpanjang, Madrasah Masih Bisa Mengirim Formulir',
                'kategori' => KategoriBerita::Pengumuman,
                'cabor' => null,
                'tanggal' => "{$t}-11-03 07:30",
                'pita' => true,
                'ringkasan' => "Panitia memperpanjang batas pendaftaran hingga {$tutup}. Madrasah yang belum mendaftar masih bisa mengirim formulir.",
                'tag' => ['Pendaftaran'],
                'isi' => "<p>Menindaklanjuti banyaknya permintaan dari madrasah, panitia {$nama} memperpanjang batas pendaftaran hingga {$tutup}.</p><p>Pendaftaran yang sudah masuk tetap berlaku dan tidak perlu dikirim ulang. Pendaftaran yang ditolak karena kelengkapan berkas dapat diperbaiki dan dikirim kembali.</p>",
            ],
            [
                'judul' => 'Petunjuk Teknis Cabor Sains dan Riset Sudah Bisa Diunduh',
                'kategori' => KategoriBerita::Pengumuman,
                'cabor' => 'sains',
                'tanggal' => "{$t}-11-04 10:00",
                'ringkasan' => 'Juknis memuat ketentuan peserta, format karya tulis, serta sistem penilaian untuk lomba sains dan riset di semua jenjang.',
                'tag' => ['Juknis', 'Sains', 'Riset'],
                'isi' => '<p>Petunjuk teknis (juknis) cabor Sains dan Riset kini tersedia. Juknis memuat ketentuan peserta, format karya tulis, batas pengumpulan naskah, serta sistem penilaian.</p><h2>Hal yang perlu diperhatikan</h2><ul><li>Naskah riset dikirim dalam format PDF.</li><li>Satu madrasah mengirim satu tim per mata lomba.</li><li>Presentasi dilakukan di hari pelaksanaan sesuai jadwal.</li></ul>',
            ],
            [
                'judul' => 'Technical Meeting Cabor Olahraga Diikuti Perwakilan Madrasah',
                'kategori' => KategoriBerita::Liputan,
                'cabor' => 'olahraga',
                'tanggal' => "{$t}-11-05 14:00",
                'ringkasan' => 'Pertemuan membahas aturan pertandingan futsal, tenis meja, dan bulu tangkis, termasuk jadwal dan sistem gugur.',
                'tag' => ['Olahraga', 'TechnicalMeeting'],
                'isi' => '<p>Technical meeting cabor Olahraga berlangsung lancar dan diikuti guru pendamping dari berbagai madrasah. Panitia memaparkan aturan pertandingan, jadwal, serta sistem gugur yang digunakan.</p><p>Peserta diminta hadir 30 menit sebelum jadwal tanding untuk daftar ulang dengan menunjukkan nomor registrasi.</p>',
            ],
            [
                'judul' => 'Tim Robotik Madrasah Bersiap Hadapi Arena Line Follower',
                'kategori' => KategoriBerita::Liputan,
                'cabor' => 'robotik',
                'tanggal' => "{$t}-11-06 09:00",
                'ringkasan' => 'Sejumlah madrasah mulai menguji robot di lintasan latihan. Panitia menyiapkan dua arena untuk babak penyisihan.',
                'tag' => ['Robotik'],
                'isi' => '<p>Sehari menjelang lomba, sejumlah tim robotik madrasah mulai menguji robot line follower di lintasan latihan yang disediakan panitia.</p><p>Panitia menyiapkan dua arena untuk babak penyisihan agar pertandingan berjalan tepat waktu.</p>',
            ],
            [
                'judul' => 'Perubahan Lokasi Lomba MTQ Jenjang MI',
                'kategori' => KategoriBerita::Pengumuman,
                'cabor' => 'keagamaan',
                'tanggal' => "{$t}-11-07 08:00",
                'ringkasan' => "Lomba MTQ Putra dan Putri jenjang MI dipindahkan ke aula utama {$lokasi} karena penyesuaian jadwal.",
                'tag' => ['Keagamaan', 'MTQ'],
                'isi' => "<p>Lomba MTQ Putra dan Putri jenjang MI dipindahkan ke aula utama {$lokasi}. Waktu pelaksanaan tidak berubah.</p><p>Guru pendamping diharapkan menyampaikan informasi ini kepada peserta.</p>",
            ],
            [
                'judul' => 'Mengenal Lomba Adzan, Satu-satunya Lomba untuk Jenjang RA',
                'kategori' => KategoriBerita::Liputan,
                'cabor' => 'keagamaan',
                'tanggal' => "{$t}-11-08 10:00",
                'ringkasan' => 'Lomba adzan menjadi ajang pertama bagi siswa RA untuk tampil di tingkat provinsi. Penilaian meliputi suara, tajwid, dan adab.',
                'tag' => ['RA', 'Adzan'],
                'isi' => '<p>Lomba Adzan menjadi satu-satunya mata lomba untuk jenjang RA di JMC. Bagi banyak peserta, ini adalah pengalaman pertama tampil di tingkat provinsi.</p><p>Dewan juri menilai kualitas suara, ketepatan tajwid, serta adab selama tampil.</p>',
            ],
        ];

        foreach ($daftar as $b) {
            Berita::query()->updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($b['judul'])],
                [
                    'edisi_id' => $edisi->id,
                    'cabor_id' => $b['cabor'] ? $cabor[$b['cabor']] ?? null : null,
                    'kategori' => $b['kategori'],
                    'judul' => $b['judul'],
                    'ringkasan' => $b['ringkasan'],
                    'isi' => $b['isi'],
                    'tag' => $b['tag'],
                    'unggulan' => $b['unggulan'] ?? false,
                    'sematkan_di_pita' => $b['pita'] ?? false,
                    'terbit_pada' => Carbon::parse($b['tanggal'], config('jmc.timezone')),
                ],
            );
        }
    }
}
