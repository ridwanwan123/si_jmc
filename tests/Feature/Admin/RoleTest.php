<?php

use App\Enums\Role;
use App\Filament\Pages\InputJuara;
use App\Filament\Pages\KelolaCabor;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\Pendaftaran;
use App\Models\User;
use Database\Seeders\CaborSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CaborSeeder::class);
    $this->c = Cabor::query()->get()->keyBy('slug');
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);

    $buat = fn (string $cabor, string $nama, string $jenjang = 'MA') => Lomba::factory()->for($this->edisi)
        ->create(['cabor_id' => $this->c[$cabor]->id, 'nama' => $nama, 'jenjang' => $jenjang, 'kategori' => 'Umum']);

    $this->futsal = $buat('olahraga', 'Futsal');
    $this->kaligrafi = $buat('seni', 'Kaligrafi');
    $this->badmintonGtk = $buat('gtk', 'Badminton Tunggal'); // ditebak: dikelola Olahraga
    $this->kultumGtk = $buat('gtk', 'Kultum');               // ditebak: dikelola Keagamaan

    $this->super = User::factory()->create(['role' => Role::Superadmin]);
    $this->olahraga = User::factory()->create(['role' => Role::AdminCabor, 'cabor_id' => $this->c['olahraga']->id]);
    $this->berita = User::factory()->create(['role' => Role::AdminBerita]);

    $m = Madrasah::factory()->create(['jenjang' => 'MA']);
    $daftar = fn (Lomba $l, string $nomor) => Pendaftaran::create([
        'edisi_id' => $this->edisi->id, 'lomba_id' => $l->id, 'madrasah_id' => $m->id,
        'nomor' => $nomor, 'guru_nama' => 'Guru', 'guru_wa' => '0812',
    ]);
    $this->pFutsal = $daftar($this->futsal, 'JMC26-0001');
    $this->pKaligrafi = $daftar($this->kaligrafi, 'JMC26-0002');
    $this->pBadminton = $daftar($this->badmintonGtk, 'JMC26-0003');
});

describe('pengelola lomba GTK', function () {
    it('menebak cabor pengelola dari nama, dan medali tetap milik GTK', function () {
        expect($this->badmintonGtk->pengelola_cabor_id)->toBe($this->c['olahraga']->id)
            ->and($this->badmintonGtk->cabor_id)->toBe($this->c['gtk']->id)
            ->and($this->kultumGtk->pengelola_cabor_id)->toBe($this->c['keagamaan']->id)
            ->and($this->futsal->pengelola_cabor_id)->toBe($this->c['olahraga']->id);
    });

    it('memaksa pengelola = cabor sendiri untuk lomba non-GTK', function () {
        $m = MataLomba::find($this->futsal->mata_lomba_id);
        $m->update(['pengelola_cabor_id' => $this->c['seni']->id]);

        expect($m->fresh()->pengelola_cabor_id)->toBe($this->c['olahraga']->id);
    });

    it('memperbarui pengelola di lomba saat master GTK diganti', function () {
        MataLomba::find($this->badmintonGtk->mata_lomba_id)->update(['pengelola_cabor_id' => $this->c['gtk']->id]);

        expect($this->badmintonGtk->fresh()->pengelola_cabor_id)->toBe($this->c['gtk']->id);
    });
});

describe('superadmin', function () {
    it('membuka semua menu', function () {
        $this->actingAs($this->super);
        foreach (['/admin/edisi', '/admin/madrasah', '/admin/mata-lomba', '/admin/cabor', '/admin/pengguna', '/admin/galeri',
            '/admin/kelola-cabor/seni', '/admin/kelola-cabor/gtk', "/admin/input-juara/{$this->kaligrafi->id}", "/admin/pendaftaran/{$this->pKaligrafi->id}"] as $url) {
            $this->get($url)->assertOk();
        }
    });

    it('membuat admin cabor (cabor wajib) dan mengosongkan cabor untuk role lain', function () {
        $this->actingAs($this->super);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Admin Seni', 'email' => 'seni@jmc.test', 'password' => 'rahasia123', 'role' => 'admin_cabor'])
            ->call('create')
            ->assertHasFormErrors(['cabor_id' => 'required']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Admin Seni', 'email' => 'seni@jmc.test', 'password' => 'rahasia123', 'role' => 'admin_cabor', 'cabor_id' => $this->c['seni']->id])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Humas', 'email' => 'humas@jmc.test', 'password' => 'rahasia123', 'role' => 'admin_berita'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(User::firstWhere('email', 'seni@jmc.test')->cabor_id)->toBe($this->c['seni']->id)
            ->and(User::firstWhere('email', 'humas@jmc.test')->cabor_id)->toBeNull()
            ->and(User::firstWhere('email', 'humas@jmc.test')->isAdminBerita())->toBeTrue();
    });
});

describe('admin cabor', function () {
    beforeEach(fn () => $this->actingAs($this->olahraga));

    it('hanya membuka cabornya sendiri', function () {
        $this->get('/admin/kelola-cabor/olahraga')->assertOk();
        $this->get('/admin/kelola-cabor/seni')->assertForbidden();
        $this->get('/admin/kelola-cabor/gtk')->assertForbidden();
    });

    it('tidak membuka master data, pengguna, berita, atau galeri', function () {
        foreach (['/admin/edisi', '/admin/madrasah', '/admin/mata-lomba', '/admin/cabor', '/admin/lomba', '/admin/pengguna', '/admin/galeri'] as $url) {
            $this->get($url)->assertForbidden();
        }
    });

    it('melihat lomba cabornya ditambah lomba GTK yang dikelolanya', function () {
        Livewire::test(KelolaCabor::class, ['cabor' => $this->c['olahraga']])
            ->assertCanSeeTableRecords([$this->futsal, $this->badmintonGtk])
            ->assertCanNotSeeTableRecords([$this->kaligrafi, $this->kultumGtk])
            ->assertSee('medali dihitung ke GTK');
    });

    it('hanya menampilkan menu cabornya di sidebar', function () {
        $menu = collect(KelolaCabor::getNavigationItems())->map->getLabel();

        expect($menu->all())->toHaveCount(1)
            ->and($menu->first())->toContain('Olahraga');
    });

    it('menginput juara lomba GTK yang dikelolanya, tapi tidak lomba cabor lain', function () {
        $this->get("/admin/input-juara/{$this->badmintonGtk->id}")->assertOk();
        $this->get("/admin/input-juara/{$this->futsal->id}")->assertOk();
        $this->get("/admin/input-juara/{$this->kaligrafi->id}")->assertForbidden();
        $this->get("/admin/input-juara/{$this->kultumGtk->id}")->assertForbidden();
    });

    it('hanya melihat dan memverifikasi pendaftaran lomba yang dikelolanya', function () {
        Livewire::test(ListPendaftarans::class)
            ->assertCanSeeTableRecords([$this->pFutsal, $this->pBadminton])
            ->assertCanNotSeeTableRecords([$this->pKaligrafi]);

        $this->get("/admin/pendaftaran/{$this->pBadminton->id}")->assertOk();
        $this->get("/admin/pendaftaran/{$this->pKaligrafi->id}")->assertNotFound();

        Livewire::test(KelolaCabor::class, ['cabor' => $this->c['olahraga'], 'tab' => 'peserta'])
            ->callTableAction('verifikasi', $this->pBadminton);
        expect($this->pBadminton->fresh()->status->value)->toBe('diverifikasi');
    });

    it('tidak melihat aksi pengaturan dan hapus lomba', function () {
        Livewire::test(KelolaCabor::class, ['cabor' => $this->c['olahraga']])
            ->assertTableActionHidden('delete', $this->futsal)
            ->assertTableActionHidden('pengaturan', $this->futsal);
    });

    it('admin cabor tanpa cabor tidak bisa masuk panel', function () {
        $tanpa = User::factory()->create(['role' => Role::AdminCabor, 'cabor_id' => null]);

        $this->actingAs($tanpa)->get('/admin')->assertForbidden();
    });
});

describe('admin berita', function () {
    beforeEach(fn () => $this->actingAs($this->berita));

    it('membuka galeri, tapi tidak cabor, pendaftaran, master, atau pengguna', function () {
        $this->get('/admin/galeri')->assertOk();
        foreach (['/admin/kelola-cabor/olahraga', "/admin/input-juara/{$this->futsal->id}", '/admin/pendaftaran', '/admin/edisi', '/admin/pengguna'] as $url) {
            $this->get($url)->assertForbidden();
        }
        expect(KelolaCabor::getNavigationItems())->toBe([]);
    });
});
