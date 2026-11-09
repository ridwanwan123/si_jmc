<?php

use App\Exports\TemplateExport;
use App\Filament\Resources\Lombas\Pages\ListLombas;
use App\Filament\Resources\Madrasahs\Pages\ListMadrasahs;
use App\Imports\HasilImport;
use App\Imports\LombaImport;
use App\Imports\MadrasahImport;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\User;
use Database\Seeders\CaborSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/** Buat file .xlsx sementara dan kembalikan path absolutnya. */
function xlsx(array $header, array $rows): string
{
    $nama = 'test-import/'.uniqid().'.xlsx';
    Excel::store(new TemplateExport($header, $rows), $nama, 'local');

    return Storage::disk('local')->path($nama);
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CaborSeeder::class);
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);
});

describe('import madrasah', function () {
    it('menambah dan memperbarui madrasah berdasarkan NSM', function () {
        Madrasah::factory()->create(['nsm' => '111231710001', 'nama' => 'Nama Lama']);

        $laporan = app(MadrasahImport::class)->import(xlsx(MadrasahImport::header(), [
            ['111231710001', 'MTsN 1 Jakarta', 'mts', 'negeri', 'jakarta pusat'],
            ['101231710002', 'MIN 2 Jakarta', 'MI', 'Negeri', 'Jakarta Timur'],
            ['', '', '', '', ''], // baris kosong diabaikan
        ]));

        expect($laporan->berhasil())->toBeTrue()
            ->and([$laporan->dibuat, $laporan->diperbarui])->toBe([1, 1])
            ->and(Madrasah::where('nsm', '111231710001')->first()->nama)->toBe('MTsN 1 Jakarta')
            ->and(Madrasah::where('nsm', '101231710002')->first()->jenjang->value)->toBe('MI');
    });

    it('tidak menyimpan apa pun jika ada baris yang salah', function () {
        $laporan = app(MadrasahImport::class)->import(xlsx(MadrasahImport::header(), [
            ['101231710002', 'MIN 2 Jakarta', 'MI', 'Negeri', 'Jakarta Timur'],
            ['101231710003', 'MIN 3', 'SD', 'Negeri', 'Bandung'],
            ['101231710002', 'Duplikat', 'MI', 'Swasta', 'Jakarta Timur'],
        ]));

        expect($laporan->berhasil())->toBeFalse()
            ->and($laporan->errors)->toHaveCount(2)
            ->and($laporan->errors[0])->toStartWith('Baris 3:')->toContain('jenjang')->toContain('wilayah')
            ->and($laporan->errors[1])->toContain('sudah ada di baris 2')
            ->and(Madrasah::count())->toBe(0);
    });
});

describe('import lomba', function () {
    it('membuat lomba baru dengan kuota default bila kosong dan memperbarui yang sudah ada', function () {
        $ada = Lomba::factory()->for($this->edisi)->create([
            'cabor_id' => Cabor::where('slug', 'olahraga')->value('id'),
            'nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri',
        ]);

        // Lomba hanya bisa diimport jika sudah ada di master mata lomba.
        MataLomba::factory()->create(['cabor_id' => Cabor::where('slug', 'keagamaan')->value('id'), 'nama' => 'Adzan', 'jenjang' => 'RA', 'kategori' => 'Putra']);
        MataLomba::factory()->create(['cabor_id' => Cabor::where('slug', 'seni')->value('id'), 'nama' => 'Paduan Suara', 'jenjang' => 'MA', 'kategori' => 'Beregu', 'kuota_juara_3' => 2]);

        $laporan = app(LombaImport::class)->import($this->edisi, xlsx(LombaImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '2', '4'],
            ['keagamaan', 'Adzan', 'RA', 'Putra', '', '', ''],
            ['Seni', 'Paduan Suara', 'MA', 'Beregu', '', '', ''],
        ]));

        $paduan = Lomba::where('nama', 'Paduan Suara')->first();

        expect($laporan->berhasil())->toBeTrue()
            ->and([$laporan->dibuat, $laporan->diperbarui])->toBe([2, 1])
            ->and($ada->fresh()->kuota_juara_3)->toBe(4)
            ->and(Lomba::where('nama', 'Adzan')->first()->only('kuota_juara_1', 'kuota_juara_2', 'kuota_juara_3'))
            ->toBe(['kuota_juara_1' => 1, 'kuota_juara_2' => 2, 'kuota_juara_3' => 3])
            ->and($paduan->kuota_juara_3)->toBe(2) // kuota kosong = kuota master
            ->and($paduan->mata_lomba_id)->not->toBeNull()
            ->and($this->edisi->lomba()->count())->toBe(3);
    });

    it('menolak lomba yang belum ada di master, cabor tidak dikenal, dan tidak menyimpan apa pun', function () {
        MataLomba::factory()->create(['cabor_id' => Cabor::where('slug', 'sains')->value('id'), 'nama' => 'Olimpiade', 'jenjang' => 'MI', 'kategori' => 'Umum']);
        MataLomba::factory()->nonaktif()->create(['cabor_id' => Cabor::where('slug', 'seni')->value('id'), 'nama' => 'Tari', 'jenjang' => 'MI', 'kategori' => 'Umum']);

        $laporan = app(LombaImport::class)->import($this->edisi, xlsx(LombaImport::header(), [
            ['Olahraga', 'Futsal', 'MA', 'Putra', '', '', ''],
            ['E-Sport', 'Mobile Legends', 'MA', 'Beregu', '', '', ''],
            ['Sains', 'Olimpiade', 'MI', 'Umum', 'x', '', ''],
            ['Seni', 'Tari', 'MI', 'Umum', '', '', ''],
            ['Sains', 'Olimpiade', 'MI', 'Umum', '', '', ''],
        ]));

        expect($laporan->errors)->toHaveCount(4)
            ->and($laporan->errors[0])->toContain('belum ada di master Mata Lomba')
            ->and($laporan->errors[1])->toContain('cabor "E-Sport" tidak dikenal')
            ->and($laporan->errors[2])->toContain('kuota juara 1')
            ->and($laporan->errors[3])->toContain('nonaktif')
            ->and(Lomba::count())->toBe(0);
    });

    it('menolak kuota baru di bawah jumlah juara yang sudah tersimpan', function () {
        $lomba = Lomba::factory()->for($this->edisi)->create([
            'cabor_id' => Cabor::where('slug', 'sains')->value('id'),
            'nama' => 'Olimpiade', 'jenjang' => 'MI', 'kategori' => 'Umum',
        ]);
        juara($lomba, Madrasah::factory()->create(), 3, 3);

        $laporan = app(LombaImport::class)->import($this->edisi, xlsx(LombaImport::header(), [
            ['Sains', 'Olimpiade', 'MI', 'Umum', '1', '2', '2'],
        ]));

        expect($laporan->berhasil())->toBeFalse()
            ->and($laporan->errors[0])->toContain('Kuota juara 3')
            ->and($lomba->fresh()->kuota_juara_3)->toBe(3);
    });
});

describe('import hasil', function () {
    beforeEach(function () {
        $olahraga = Cabor::where('slug', 'olahraga')->value('id');
        $this->lomba = Lomba::factory()->for($this->edisi)->create([
            'cabor_id' => $olahraga, 'nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri',
        ]);
        $this->lombaLain = Lomba::factory()->for($this->edisi)->create([
            'cabor_id' => $olahraga, 'nama' => 'Futsal', 'jenjang' => 'MA', 'kategori' => 'Beregu',
        ]);
        $this->a = Madrasah::factory()->create(['nsm' => '121231710001']);
        $this->b = Madrasah::factory()->create(['nsm' => '121231710002']);
        $this->c = Madrasah::factory()->create(['nsm' => '131231710003']);
    });

    it('mengimpor juara ganda satu baris per juara', function () {
        $laporan = app(HasilImport::class)->import($this->edisi, xlsx(HasilImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710001', 'Siti'],
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '2', '121231710002', 'Ani'],
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '2', '121231710001', 'Rina'],
            ['olahraga', 'tenis meja', 'mts', 'putri', '3', '121231710002', 'Dewi'],
            ['Olahraga', 'Futsal', 'MA', 'Beregu', '1', '131231710003', 'Tim A: Ali, Budi, Cecep'],
        ]));

        expect($laporan->berhasil())->toBeTrue()
            ->and($laporan->dibuat)->toBe(5)
            ->and($this->lomba->hasil()->pluck('peringkat')->all())->toBe([1, 2, 2, 3])
            ->and($this->lombaLain->hasil()->first()->nama_peserta)->toBe('Tim A: Ali, Budi, Cecep');
    });

    it('mengganti juara lama lomba yang ada di file tanpa menyentuh lomba lain', function () {
        juara($this->lomba, $this->c, 1, peserta: 'Lama');
        juara($this->lombaLain, $this->c, 1, peserta: 'Tetap');

        app(HasilImport::class)->import($this->edisi, xlsx(HasilImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710001', 'Baru'],
        ]));

        // import ulang file yang sama tidak menggandakan
        app(HasilImport::class)->import($this->edisi, xlsx(HasilImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710001', 'Baru'],
        ]));

        expect($this->lomba->hasil()->pluck('nama_peserta')->all())->toBe(['Baru'])
            ->and($this->lombaLain->hasil()->pluck('nama_peserta')->all())->toBe(['Tetap']);
    });

    it('menolak seluruh file jika kuota terlampaui', function () {
        $laporan = app(HasilImport::class)->import($this->edisi, xlsx(HasilImport::header(), [
            ['Olahraga', 'Futsal', 'MA', 'Beregu', '1', '131231710003', 'Tim A'],
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710001', 'Siti'],
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710002', 'Ani'],
        ]));

        expect($laporan->berhasil())->toBeFalse()
            ->and($laporan->errors[0])->toContain('Tenis Meja · MTs · Putri')->toContain('maksimal 1')
            ->and(Hasil::count())->toBe(0);
    });

    it('melaporkan NSM, lomba, dan peringkat yang salah dengan nomor baris', function () {
        $laporan = app(HasilImport::class)->import($this->edisi, xlsx(HasilImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '999', 'X'],
            ['Olahraga', 'Bulu Tangkis', 'MTs', 'Putri', '1', '121231710001', 'Y'],
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '4', '121231710001', 'Z'],
        ]));

        expect($laporan->errors)->toHaveCount(3)
            ->and($laporan->errors[0])->toBe('Baris 2: madrasah dengan NSM 999 tidak ditemukan.')
            ->and($laporan->errors[1])->toStartWith('Baris 3:')->toContain('Bulu Tangkis')->toContain('tidak ada di JMC 2026')
            ->and($laporan->errors[2])->toBe('Baris 4: peringkat harus 1, 2, atau 3.')
            ->and(Hasil::count())->toBe(0);
    });

    it('tidak mencocokkan lomba dari edisi lain', function () {
        $lain = Edisi::factory()->create(['tahun' => 2025]);

        $laporan = app(HasilImport::class)->import($lain, xlsx(HasilImport::header(), [
            ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '121231710001', 'Siti'],
        ]));

        expect($laporan->berhasil())->toBeFalse()->and(Hasil::count())->toBe(0);
    });
});

describe('tombol template di admin', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->create()));

    it('mengunduh template madrasah', function () {
        Livewire::test(ListMadrasahs::class)
            ->callAction('template_MadrasahImport')
            ->assertFileDownloaded('template-madrasah.xlsx');
    });

    it('menampilkan tombol import di halaman lomba', function () {
        Livewire::test(ListLombas::class)
            ->assertActionVisible('importLomba')
            ->assertActionVisible('importHasil');
    });
});
