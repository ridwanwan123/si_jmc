<?php

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Enums\Medali;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\User;
use Database\Seeders\CaborSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

describe('seeder', function () {
    it('membuat tepat 7 cabor dengan urutan 01–07', function () {
        $this->seed(CaborSeeder::class);
        $this->seed(CaborSeeder::class); // idempoten

        $cabor = Cabor::orderBy('urutan')->get();

        expect($cabor)->toHaveCount(7)
            ->and($cabor->pluck('nama')->all())
            ->toBe(['Keagamaan', 'Sains', 'Olahraga', 'Seni', 'Riset', 'Robotik', 'GTK'])
            ->and($cabor->first()->nomor())->toBe('01')
            ->and($cabor->last()->nomor())->toBe('07');
    });

    it('menjalankan DatabaseSeeder: cabor, superadmin, madrasah, mata lomba, edisi aktif + lombanya, tanpa data contoh', function () {
        $this->seed(DatabaseSeeder::class);

        expect(Cabor::count())->toBe(7)
            ->and(User::count())->toBe(1)
            ->and(User::first()->isSuperadmin())->toBeTrue()
            ->and(Madrasah::count())->toBe(1815)
            ->and(MataLomba::count())->toBe(92)
            ->and(Edisi::where('is_aktif', true)->value('tahun'))->toBe(2026)
            ->and(Lomba::count())->toBe(92)
            ->and(Lomba::whereColumn('pengelola_cabor_id', '!=', 'cabor_id')->count())->toBe(17) // lomba GTK
            ->and(\App\Models\Hasil::count())->toBe(0)
            ->and(\App\Models\Berita::count())->toBe(0);
    });
});

describe('edisi', function () {
    it('hanya mengizinkan satu edisi aktif', function () {
        $lama = Edisi::factory()->aktif()->create(['tahun' => 2025]);
        $baru = Edisi::factory()->aktif()->create(['tahun' => 2026]);

        expect($lama->fresh()->is_aktif)->toBeFalse()
            ->and($baru->fresh()->is_aktif)->toBeTrue();

        $lama->refresh()->update(['is_aktif' => true]);

        expect(Edisi::aktif()->pluck('tahun')->all())->toBe([2025]);
    });

    it('memilih edisi terbaru jika tidak ada yang aktif', function () {
        Edisi::factory()->create(['tahun' => 2024]);
        Edisi::factory()->create(['tahun' => 2025]);

        expect(Edisi::pilih()->tahun)->toBe(2025)
            ->and(Edisi::pilih(1999))->toBeNull();
    });
});

describe('relasi', function () {
    it('menghubungkan edisi, cabor, lomba, hasil, dan madrasah', function () {
        $edisi = Edisi::factory()->create();
        $cabor = Cabor::factory()->create();
        $madrasah = Madrasah::factory()->jenjang(Jenjang::MTs)->create();
        $lomba = Lomba::factory()->for($edisi)->for($cabor)->create([
            'nama' => 'Tenis Meja',
            'jenjang' => Jenjang::MTs,
            'kategori' => Kategori::Putri,
        ]);
        $hasil = Hasil::factory()->for($lomba)->for($madrasah)->emas()->create();

        expect($lomba->edisi->is($edisi))->toBeTrue()
            ->and($lomba->cabor->is($cabor))->toBeTrue()
            ->and($hasil->madrasah->is($madrasah))->toBeTrue()
            ->and($edisi->lomba)->toHaveCount(1)
            ->and($edisi->hasil)->toHaveCount(1)
            ->and($cabor->lomba)->toHaveCount(1)
            ->and($madrasah->hasil)->toHaveCount(1)
            ->and($madrasah->jenjang)->toBe(Jenjang::MTs)
            ->and($lomba->namaLengkap())->toBe('Tenis Meja · MTs · Putri')
            ->and($hasil->medali())->toBe(Medali::Emas)
            ->and(Hasil::edisi($edisi)->count())->toBe(1);
    });

    it('menghubungkan album galeri dengan foto dan sampul', function () {
        $album = GaleriAlbum::factory()->create();
        $foto = GaleriFoto::factory()->count(3)->for($album, 'album')->create();
        $album->update(['sampul_foto_id' => $foto[1]->id]);

        expect($album->fresh()->foto)->toHaveCount(3)
            ->and($album->fresh()->sampul->is($foto[1]))->toBeTrue()
            ->and($album->cabor)->toBeNull();

        // Sampul dihapus → pindah ke foto pertama yang tersisa.
        $foto[1]->delete();
        expect($album->fresh()->sampul_foto_id)->toBe($foto[0]->id);

        // Foto terakhir dihapus → album tanpa sampul.
        $foto[0]->delete();
        $foto[2]->delete();
        expect($album->fresh()->sampul_foto_id)->toBeNull();
    });

    it('menghapus lomba dan hasil saat edisi dihapus', function () {
        $lomba = Lomba::factory()->create();
        Hasil::factory()->for($lomba)->create();

        $lomba->edisi->delete();

        expect(Lomba::count())->toBe(0)->and(Hasil::count())->toBe(0);
    });

    it('menolak hapus madrasah yang masih punya hasil', function () {
        $hasil = Hasil::factory()->create();

        expect(fn () => $hasil->madrasah->delete())->toThrow(QueryException::class);
    });
});

describe('lomba dan juara', function () {
    it('memakai kuota default 1 / 2 / 3', function () {
        $lomba = Lomba::create([
            'edisi_id' => Edisi::factory()->create()->id,
            'cabor_id' => Cabor::factory()->create()->id,
            'nama' => 'Adzan',
            'jenjang' => Jenjang::RA,
            'kategori' => Kategori::Putra,
        ])->fresh();

        expect($lomba->kuota(1))->toBe(1)
            ->and($lomba->kuota(Medali::Perak))->toBe(2)
            ->and($lomba->kuota(3))->toBe(3);
    });

    it('menyimpan beberapa juara di peringkat yang sama', function () {
        $lomba = Lomba::factory()->create();
        $madrasah = Madrasah::factory()->create();

        Hasil::factory()->for($lomba)->emas()->create();
        Hasil::factory()->for($lomba)->perak()->count(2)->create();
        // madrasah yang sama boleh muncul dua kali di peringkat yang sama
        Hasil::factory()->for($lomba)->for($madrasah)->perunggu()->count(2)->create();
        Hasil::factory()->for($lomba)->perunggu()->create();

        $hasil = $lomba->hasil()->get();

        expect($hasil)->toHaveCount(6)
            ->and($hasil->where('peringkat', 2))->toHaveCount(2)
            ->and($hasil->where('peringkat', 3))->toHaveCount(3)
            ->and($hasil->pluck('peringkat')->all())->toBe([1, 2, 2, 3, 3, 3]);
    });

    it('mengambil daftar jenjang klasemen umum dari config', function () {
        expect(Jenjang::klasemenUmum())->toBe([Jenjang::RA, Jenjang::MI, Jenjang::MTs, Jenjang::MA]);

        config(['jmc.jenjang_klasemen_umum' => ['MI', 'MTs', 'MA']]);

        expect(Jenjang::klasemenUmum())->not->toContain(Jenjang::RA);
    });
});
