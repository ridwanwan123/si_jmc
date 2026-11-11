<?php

use App\Exports\TemplateExport;
use App\Filament\Resources\Cabors\Pages\CreateCabor;
use App\Filament\Resources\Cabors\Pages\EditCabor;
use App\Filament\Resources\Lombas\LombaResource;
use App\Filament\Resources\Lombas\Pages\CreateLomba;
use App\Filament\Resources\Lombas\Pages\ListLombas;
use App\Filament\Resources\MataLombas\Pages\CreateMataLomba;
use App\Filament\Resources\MataLombas\Pages\EditMataLomba;
use App\Filament\Resources\MataLombas\Pages\ListMataLombas;
use App\Imports\MataLombaImport;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\User;
use App\Services\LombaService;
use Database\Seeders\CaborSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(CaborSeeder::class);
    $this->olahraga = Cabor::where('slug', 'olahraga')->first();
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);
    $this->lama = Edisi::factory()->create(['tahun' => 2025, 'nama' => 'JMC 2025']);
});

describe('master cabor', function () {
    it('menampilkan halaman cabor dan mata lomba', function () {
        $this->get('/admin/cabor')->assertOk();
        $this->get('/admin/mata-lomba')->assertOk();
        $this->get('/admin/mata-lomba/create')->assertOk();
    });

    it('menambah cabor dengan slug otomatis', function () {
        Livewire::test(CreateCabor::class)
            ->fillForm(['nama' => 'Bahasa', 'ikon' => 'kaca-pembesar', 'urutan' => 8])
            ->assertSchemaStateSet(['slug' => 'bahasa'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Cabor::where('slug', 'bahasa')->exists())->toBeTrue();
        $this->withoutVite()->get('/cabor/bahasa')->assertOk()->assertSee('Bahasa');
    });

    it('tidak menghapus cabor yang sudah punya mata lomba', function () {
        MataLomba::factory()->for($this->olahraga)->create();

        Livewire::test(EditCabor::class, ['record' => $this->olahraga->getRouteKey()])->callAction('delete');
        expect(Cabor::whereKey($this->olahraga->id)->exists())->toBeTrue();

        $kosong = Cabor::factory()->create();
        Livewire::test(EditCabor::class, ['record' => $kosong->getRouteKey()])->callAction('delete');
        expect(Cabor::whereKey($kosong->id)->exists())->toBeFalse();
    });
});

describe('master mata lomba', function () {
    it('membuat mata lomba dan menolak kombinasi yang sama', function () {
        $data = ['cabor_id' => $this->olahraga->id, 'nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri'];

        Livewire::test(CreateMataLomba::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateMataLomba::class)->fillForm($data)->call('create')->assertHasFormErrors(['nama' => 'unique']);

        // Nama sama tapi kategori berbeda boleh.
        Livewire::test(CreateMataLomba::class)->fillForm(['kategori' => 'Putra'] + $data)->call('create')->assertHasNoFormErrors();

        expect(MataLomba::count())->toBe(2)
            ->and(MataLomba::first()->only('kuota_juara_1', 'kuota_juara_2', 'kuota_juara_3', 'is_aktif'))
            ->toBe(['kuota_juara_1' => 1, 'kuota_juara_2' => 2, 'kuota_juara_3' => 3, 'is_aktif' => true]);
    });

    it('tidak menghapus mata lomba yang sudah dipakai di edisi', function () {
        $dipakai = MataLomba::factory()->for($this->olahraga)->create();
        app(LombaService::class)->dariMaster($this->edisi, $dipakai);

        Livewire::test(EditMataLomba::class, ['record' => $dipakai->getRouteKey()])->callAction('delete');
        expect(MataLomba::whereKey($dipakai->id)->exists())->toBeTrue();

        $belum = MataLomba::factory()->for($this->olahraga)->create();
        Livewire::test(EditMataLomba::class, ['record' => $belum->getRouteKey()])->callAction('delete');
        expect(MataLomba::whereKey($belum->id)->exists())->toBeFalse();
    });

    it('memfilter dan mencari mata lomba di tabel', function () {
        $a = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Futsal', 'jenjang' => 'MA']);
        $b = MataLomba::factory()->for(Cabor::where('slug', 'seni')->first())->create(['nama' => 'Kaligrafi', 'jenjang' => 'MI']);

        Livewire::test(ListMataLombas::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->filterTable('cabor_id', $this->olahraga->id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);

        Livewire::test(ListMataLombas::class)
            ->searchTable('Kali')
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a]);
    });

    it('mengimpor master mata lomba dari Excel', function () {
        Storage::fake('local');
        MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Futsal', 'jenjang' => 'MA', 'kategori' => 'Putra']);

        $path = 'test/mata-lomba.xlsx';
        Excel::store(new TemplateExport(MataLombaImport::header(), [
            ['Olahraga', 'Futsal', 'MA', 'Putra', '10', '1', '1', '1'],
            ['Seni', 'Kaligrafi', 'mi', 'umum', '', '', '', ''],
            ['E-Sport', 'Mobile Legends', 'MA', 'Beregu', '5', '', '', ''],
        ]), $path, 'local');

        $gagal = app(MataLombaImport::class)->import(Storage::disk('local')->path($path));
        expect($gagal->errors)->toHaveCount(1)->and(MataLomba::count())->toBe(1);

        Excel::store(new TemplateExport(MataLombaImport::header(), [
            ['Olahraga', 'Futsal', 'MA', 'Putra', '10', '1', '1', '1'],
            ['Seni', 'Kaligrafi', 'mi', 'umum', '', '', '', ''],
        ]), $path, 'local');

        $laporan = app(MataLombaImport::class)->import(Storage::disk('local')->path($path));

        expect($laporan->berhasil())->toBeTrue()
            ->and([$laporan->dibuat, $laporan->diperbarui])->toBe([1, 1])
            ->and(MataLomba::firstWhere('nama', 'Futsal')->kuota_juara_3)->toBe(1)
            ->and(MataLomba::firstWhere('nama', 'Futsal')->jumlah_anggota)->toBe(10)
            ->and(MataLomba::firstWhere('nama', 'Kaligrafi')->jumlah_anggota)->toBe(1)
            ->and(MataLomba::firstWhere('nama', 'Kaligrafi')->jenjang->value)->toBe('MI');
    });
});

describe('lomba dari master', function () {
    it('hanya menawarkan mata lomba aktif yang belum ada di edisi', function () {
        $aktif = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Futsal']);
        $nonaktif = MataLomba::factory()->for($this->olahraga)->nonaktif()->create(['nama' => 'Kasti']);
        $sudahAda = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Voli']);
        app(LombaService::class)->dariMaster($this->edisi, $sudahAda);

        $opsi = collect(LombaResource::opsiMataLomba($this->edisi->id))->flatMap(fn ($g) => array_keys($g))->all();

        expect($opsi)->toContain($aktif->id)
            ->not->toContain($nonaktif->id)
            ->not->toContain($sudahAda->id);

        // Di edisi lain, Voli masih bisa dipilih.
        expect(collect(LombaResource::opsiMataLomba($this->lama->id))->flatMap(fn ($g) => array_keys($g))->all())
            ->toContain($sudahAda->id);
    });

    it('menyalin nama, cabor, jenjang, kategori dari master sebagai snapshot', function () {
        $master = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri', 'kuota_juara_2' => 1]);
        $lomba = app(LombaService::class)->dariMaster($this->lama, $master);

        // Master diganti namanya kemudian → lomba lama tetap memakai nama lama.
        $master->update(['nama' => 'Tenis Meja Baru']);

        expect($lomba->fresh()->nama)->toBe('Tenis Meja')
            ->and($lomba->kuota_juara_2)->toBe(1)
            ->and(fn () => app(LombaService::class)->dariMaster($this->lama, $master))->toThrow(ValidationException::class);
    });

    it('menambah banyak lomba dari master lewat aksi', function () {
        $m = MataLomba::factory()->for($this->olahraga)->count(3)->create();
        app(LombaService::class)->dariMaster($this->edisi, $m[0]);

        Livewire::test(ListLombas::class)
            ->callAction('tambahDariMaster', data: [
                'edisi_id' => $this->edisi->id,
                'mata_lomba_ids' => [$m[1]->id, $m[2]->id],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('2 lomba ditambahkan ke JMC 2026');

        expect($this->edisi->lomba()->count())->toBe(3);
    });

    it('menyalin daftar lomba dari edisi lain tanpa hasil, melewati yang nonaktif dan yang sudah ada', function () {
        $a = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'A']);
        $b = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'B']);
        $c = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'C']);
        $service = app(LombaService::class);

        $la = $service->dariMaster($this->lama, $a, [1 => 2, 2 => 2, 3 => 2]);
        $service->dariMaster($this->lama, $b);
        $service->dariMaster($this->lama, $c);
        juara($la, Madrasah::factory()->create(), 1);
        $c->update(['is_aktif' => false]);
        $service->dariMaster($this->edisi, $b); // sudah ada di tujuan

        Livewire::test(ListLombas::class)
            ->callAction('salinDariEdisi', data: ['sumber_id' => $this->lama->id, 'tujuan_id' => $this->edisi->id, 'salin_kuota' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('1 lomba disalin dari JMC 2025 ke JMC 2026');

        $baru = Lomba::where('edisi_id', $this->edisi->id)->where('mata_lomba_id', $a->id)->first();

        expect($this->edisi->lomba()->count())->toBe(2)
            ->and($baru->kuota_juara_1)->toBe(2)
            ->and($baru->hasil()->count())->toBe(0);
    });

    it('menolak salin ke edisi yang sama', function () {
        Livewire::test(ListLombas::class)
            ->callAction('salinDariEdisi', data: ['sumber_id' => $this->edisi->id, 'tujuan_id' => $this->edisi->id, 'salin_kuota' => true])
            ->assertHasActionErrors(['tujuan_id']);
    });

    it('tidak menampilkan field nama bebas di form lomba', function () {
        Livewire::test(CreateLomba::class)
            ->assertFormFieldExists('mata_lomba_id')
            ->assertFormFieldDoesNotExist('nama')
            ->assertFormFieldDoesNotExist('cabor_id');
    });
});
