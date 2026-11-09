<?php

use App\Filament\Pages\InputJuara;
use App\Filament\Resources\Edisis\Pages\CreateEdisi;
use App\Filament\Resources\Edisis\Pages\EditEdisi;
use App\Filament\Resources\Edisis\Pages\ListEdisis;
use App\Filament\Resources\Lombas\Pages\CreateLomba;
use App\Filament\Resources\Lombas\Pages\EditLomba;
use App\Filament\Resources\Lombas\Pages\ListLombas;
use App\Filament\Resources\Madrasahs\Pages\CreateMadrasah;
use App\Filament\Resources\Madrasahs\Pages\EditMadrasah;
use App\Filament\Resources\Madrasahs\Pages\ListMadrasahs;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

describe('akses', function () {
    it('mengalihkan tamu ke halaman login', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('menampilkan dashboard dan semua halaman daftar untuk admin', function () {
        $edisi = Edisi::factory()->aktif()->create();
        $lomba = Lomba::factory()->for($edisi)->create();
        Hasil::factory()->for($lomba)->create();

        $this->actingAs($this->admin);

        foreach (['/admin', '/admin/edisi', '/admin/madrasah', '/admin/lomba', "/admin/lomba/{$lomba->id}/edit", "/admin/kelola-cabor/{$lomba->cabor->slug}", "/admin/kelola-cabor/{$lomba->cabor->slug}?tab=peserta", "/admin/input-juara/{$lomba->id}"] as $url) {
            $this->get($url)->assertOk();
        }
    });
});

describe('edisi', function () {
    beforeEach(fn () => $this->actingAs($this->admin));

    it('membuat edisi aktif dan menonaktifkan edisi lain', function () {
        $lama = Edisi::factory()->aktif()->create(['tahun' => 2025]);

        Livewire::test(CreateEdisi::class)
            ->fillForm(['tahun' => 2026, 'nama' => 'JMC 2026', 'jumlah_madrasah_peserta' => 150, 'is_aktif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Edisi::pilih()->tahun)->toBe(2026)
            ->and($lama->fresh()->is_aktif)->toBeFalse();
    });

    it('menolak tahun edisi yang sudah ada', function () {
        Edisi::factory()->create(['tahun' => 2026]);

        Livewire::test(CreateEdisi::class)
            ->fillForm(['tahun' => 2026, 'nama' => 'JMC 2026', 'jumlah_madrasah_peserta' => 0])
            ->call('create')
            ->assertHasFormErrors(['tahun' => 'unique']);
    });

    it('menjadikan edisi aktif lewat aksi tabel', function () {
        $aktif = Edisi::factory()->aktif()->create(['tahun' => 2026]);
        $lama = Edisi::factory()->create(['tahun' => 2025]);

        Livewire::test(ListEdisis::class)
            ->assertTableActionHidden('jadikanAktif', $aktif)
            ->callTableAction('jadikanAktif', $lama);

        expect(Edisi::pilih()->is($lama))->toBeTrue();
    });

    it('tidak mengizinkan hapus edisi aktif', function () {
        $aktif = Edisi::factory()->aktif()->create();

        Livewire::test(EditEdisi::class, ['record' => $aktif->getRouteKey()])
            ->assertActionHidden('delete');
    });
});

describe('madrasah', function () {
    beforeEach(fn () => $this->actingAs($this->admin));

    it('membuat madrasah dan menolak NSM duplikat', function () {
        $data = ['nsm' => '121131710001', 'nama' => 'MTsN 1 Jakarta', 'jenjang' => 'MTs', 'status' => 'Negeri', 'wilayah' => 'Jakarta Pusat'];

        Livewire::test(CreateMadrasah::class)->fillForm($data)->call('create')->assertHasNoFormErrors();

        Livewire::test(CreateMadrasah::class)
            ->fillForm(['nama' => 'Lain'] + $data)
            ->call('create')
            ->assertHasFormErrors(['nsm' => 'unique']);

        expect(Madrasah::count())->toBe(1);
    });

    it('menolak hapus madrasah yang sudah punya hasil', function () {
        $hasil = Hasil::factory()->create();

        Livewire::test(EditMadrasah::class, ['record' => $hasil->madrasah->getRouteKey()])
            ->callAction('delete');

        expect(Madrasah::whereKey($hasil->madrasah_id)->exists())->toBeTrue();
    });

    it('menghapus madrasah tanpa hasil', function () {
        $m = Madrasah::factory()->create();

        Livewire::test(EditMadrasah::class, ['record' => $m->getRouteKey()])->callAction('delete');

        expect(Madrasah::count())->toBe(0);
    });

    it('mencari madrasah di tabel', function () {
        $a = Madrasah::factory()->create(['nama' => 'MAN 4 Jakarta']);
        $b = Madrasah::factory()->create(['nama' => 'MIN 9 Jakarta']);

        Livewire::test(ListMadrasahs::class)
            ->searchTable('MAN 4')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    });
});

describe('lomba dan input hasil', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->edisi = Edisi::factory()->aktif()->create();
        $this->cabor = Cabor::factory()->create();
        $this->lomba = Lomba::factory()->for($this->edisi)->for($this->cabor)->create(['nama' => 'Tenis Meja']);
        $this->m = Madrasah::factory()->count(4)->create();
        $this->undoRepeaterFake = Repeater::fake();
    });

    afterEach(fn () => ($this->undoRepeaterFake)());

    it('membuat lomba dari master dengan kuota master lalu diarahkan ke halaman edit', function () {
        $master = MataLomba::factory()->for($this->cabor)->create([
            'nama' => 'Adzan', 'jenjang' => 'RA', 'kategori' => 'Putra', 'kuota_juara_3' => 2,
        ]);

        Livewire::test(CreateLomba::class)
            ->fillForm(['edisi_id' => $this->edisi->id, 'mata_lomba_id' => $master->id])
            ->assertSchemaStateSet(['kuota_juara_3' => 2])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $lomba = Lomba::where('mata_lomba_id', $master->id)->first();

        expect($lomba->only('nama', 'cabor_id'))->toBe(['nama' => 'Adzan', 'cabor_id' => $this->cabor->id])
            ->and($lomba->jenjang->value)->toBe('RA')
            ->and($lomba->kategori->value)->toBe('Putra')
            ->and([$lomba->kuota_juara_1, $lomba->kuota_juara_2, $lomba->kuota_juara_3])->toBe([1, 2, 2]);
    });

    it('menolak mata lomba yang sudah ada di edisi yang sama', function () {
        Livewire::test(CreateLomba::class)
            ->fillForm(['edisi_id' => $this->edisi->id, 'mata_lomba_id' => $this->lomba->mata_lomba_id])
            ->call('create')
            ->assertHasFormErrors(['mata_lomba_id']);

        expect(Lomba::where('mata_lomba_id', $this->lomba->mata_lomba_id)->count())->toBe(1);
    });

    it('menampilkan lomba edisi aktif secara default', function () {
        $lain = Lomba::factory()->for(Edisi::factory()->create())->create();

        Livewire::test(ListLombas::class)
            ->assertCanSeeTableRecords([$this->lomba])
            ->assertCanNotSeeTableRecords([$lain]);
    });

    it('menyimpan juara ganda lewat halaman Input Juara (isi manual)', function () {
        Livewire::test(InputJuara::class, ['lomba' => $this->lomba])
            ->fillForm([
                'juara_1' => [['manual' => true, 'madrasah_id' => $this->m[0]->id, 'nama_peserta' => 'Ahmad']],
                'juara_2' => [
                    ['manual' => true, 'madrasah_id' => $this->m[1]->id, 'nama_peserta' => 'Budi'],
                    ['manual' => true, 'madrasah_id' => $this->m[2]->id, 'nama_peserta' => 'Citra'],
                ],
                'juara_3' => [
                    ['manual' => true, 'madrasah_id' => $this->m[0]->id, 'nama_peserta' => 'Dina'],
                    ['manual' => true, 'madrasah_id' => $this->m[3]->id, 'nama_peserta' => null],
                ],
            ])
            ->call('simpan')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $hasil = $this->lomba->hasil()->get();

        expect($hasil->map(fn ($h) => [$h->peringkat, $h->nama_peserta, $h->urutan])->all())->toBe([
            [1, 'Ahmad', 1],
            [2, 'Budi', 1],
            [2, 'Citra', 2],
            [3, 'Dina', 1],
            [3, null, 2],
        ]);
    });

    it('mengisi form Input Juara dengan hasil yang sudah tersimpan', function () {
        juara($this->lomba, $this->m[1], 2, peserta: 'Budi');

        Livewire::test(InputJuara::class, ['lomba' => $this->lomba])
            ->assertSchemaStateSet(['juara_2' => [[
                'manual' => true, 'pendaftaran_id' => null, 'madrasah_id' => $this->m[1]->id, 'nama_peserta' => 'Budi',
            ]]]);
    });

    it('menolak juara melebihi kuota lomba', function () {
        Livewire::test(InputJuara::class, ['lomba' => $this->lomba])
            ->set('data.juara_1', [
                ['manual' => true, 'madrasah_id' => $this->m[0]->id, 'nama_peserta' => 'A'],
                ['manual' => true, 'madrasah_id' => $this->m[1]->id, 'nama_peserta' => 'B'],
            ])
            ->call('simpan')
            ->assertHasErrors();

        expect($this->lomba->hasil()->count())->toBe(0);
    });

    it('menaikkan kuota di Pengaturan lomba lalu menerima dua juara 1', function () {
        Livewire::test(EditLomba::class, ['record' => $this->lomba->getRouteKey()])
            ->fillForm(['kuota_juara_1' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(InputJuara::class, ['lomba' => $this->lomba])
            ->fillForm(['juara_1' => [
                ['manual' => true, 'madrasah_id' => $this->m[0]->id, 'nama_peserta' => 'A'],
                ['manual' => true, 'madrasah_id' => $this->m[1]->id, 'nama_peserta' => 'B'],
            ]])
            ->call('simpan')
            ->assertHasNoFormErrors();

        expect($this->lomba->fresh()->kuota_juara_1)->toBe(2)
            ->and($this->lomba->hasil()->where('peringkat', 1)->count())->toBe(2);
    });

    it('menolak kuota diturunkan di bawah jumlah juara yang tersimpan', function () {
        juara($this->lomba, $this->m[0], 3, 3);

        Livewire::test(EditLomba::class, ['record' => $this->lomba->getRouteKey()])
            ->fillForm(['kuota_juara_3' => 2])
            ->call('save')
            ->assertHasFormErrors(['kuota_juara_3']);

        expect($this->lomba->fresh()->kuota_juara_3)->toBe(3)
            ->and($this->lomba->hasil()->count())->toBe(3);
    });

    it('mewajibkan madrasah di setiap baris juara', function () {
        Livewire::test(InputJuara::class, ['lomba' => $this->lomba])
            ->fillForm(['juara_1' => [['manual' => true, 'madrasah_id' => null, 'nama_peserta' => 'Tanpa madrasah']]])
            ->call('simpan')
            ->assertHasFormErrors();

        expect($this->lomba->hasil()->count())->toBe(0);
    });
});
