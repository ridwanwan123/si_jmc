<?php

use App\Enums\KategoriBerita;
use App\Enums\Role;
use App\Filament\Resources\Beritas\Pages\CreateBerita;
use App\Filament\Resources\Beritas\Pages\EditBerita;
use App\Filament\Resources\Beritas\Pages\ListBeritas;
use App\Models\Berita;
use App\Models\Edisi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);
    $this->penulis = User::factory()->create(['role' => Role::AdminBerita]);
    $this->actingAs($this->penulis);
});

it('membuka daftar, form buat, dan form ubah berita', function () {
    $b = Berita::factory()->for($this->edisi)->create();

    $this->get('/admin/berita')->assertOk();
    $this->get('/admin/berita/create')->assertOk();
    $this->get("/admin/berita/{$b->getRouteKey()}/edit")->assertOk();
});

it('menolak admin cabor membuka berita', function () {
    $this->actingAs(User::factory()->create(['role' => Role::AdminCabor, 'cabor_id' => \App\Models\Cabor::factory()->create()->id]));

    $this->get('/admin/berita')->assertForbidden();
});

it('membuat berita draf dengan slug otomatis dan sampul terkompres WebP', function () {
    Storage::fake('public');

    Livewire::test(CreateBerita::class)
        ->fillForm([
            'judul' => 'Pembukaan JMC 2026 Berlangsung Meriah',
            'edisi_id' => $this->edisi->id,
            'kategori' => 'liputan',
            'ringkasan' => 'Ringkasan singkat.',
            'isi' => '<p>Isi berita.</p>',
            'sampul' => UploadedFile::fake()->image('foto.jpg', 2400, 1600),
            'tag' => ['Pembukaan'],
        ])
        ->assertSet('data.slug', 'pembukaan-jmc-2026-berlangsung-meriah')
        ->call('create')
        ->assertHasNoFormErrors();

    $b = Berita::firstOrFail();
    expect($b->status())->toBe('draf')
        ->and($b->sampul)->toEndWith('.webp')
        ->and(getimagesizefromstring(Storage::disk('public')->get($b->sampul))[0])->toBe(1600)
        ->and($b->tag)->toBe(['Pembukaan']);

    // Draf belum tampil di web.
    $this->get('/berita/pembukaan-jmc-2026-berlangsung-meriah')->assertNotFound();
});

it('menerbitkan, menjadwalkan, dan menjadikan draf', function () {
    $b = Berita::factory()->for($this->edisi)->draf()->create(['slug' => 'kabar']);

    Livewire::test(ListBeritas::class)->callTableAction('terbitkan', $b);
    expect($b->fresh()->status())->toBe('terbit');
    $this->get('/berita/kabar')->assertOk();

    Livewire::test(ListBeritas::class)->callTableAction('jadikanDraf', $b);
    expect($b->fresh()->status())->toBe('draf');

    Livewire::test(EditBerita::class, ['record' => $b->getRouteKey()])
        ->fillForm(['terbit_pada' => now()->addDay()])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($b->fresh()->status())->toBe('terjadwal');
    $this->get('/berita/kabar')->assertNotFound();
});

it('hanya ada satu berita unggulan dan satu pita per edisi', function () {
    $lama = Berita::factory()->for($this->edisi)->kategori(KategoriBerita::Pengumuman)->create(['unggulan' => true, 'sematkan_di_pita' => true]);
    $baru = Berita::factory()->for($this->edisi)->kategori(KategoriBerita::Pengumuman)->create();
    $edisiLain = Berita::factory()->create(['unggulan' => true]);

    Livewire::test(ListBeritas::class)
        ->callTableAction('unggulkan', $baru)
        ->callTableAction('sematkan', $baru);

    expect($baru->fresh()->unggulan)->toBeTrue()
        ->and($baru->fresh()->sematkan_di_pita)->toBeTrue()
        ->and($lama->fresh()->unggulan)->toBeFalse()
        ->and($lama->fresh()->sematkan_di_pita)->toBeFalse()
        ->and($edisiLain->fresh()->unggulan)->toBeTrue(); // edisi lain tidak terpengaruh
});

it('tidak menyematkan berita non-pengumuman di pita', function () {
    Livewire::test(CreateBerita::class)
        ->fillForm([
            'judul' => 'Liputan Biasa', 'edisi_id' => $this->edisi->id, 'kategori' => 'liputan',
            'ringkasan' => 'R', 'isi' => '<p>I</p>', 'sematkan_di_pita' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Berita::firstOrFail()->sematkan_di_pita)->toBeFalse();
});

it('memfilter berita berdasarkan status', function () {
    $draf = Berita::factory()->for($this->edisi)->draf()->create();
    $terbit = Berita::factory()->for($this->edisi)->create();

    Livewire::test(ListBeritas::class)
        ->filterTable('status', 'draf')
        ->assertCanSeeTableRecords([$draf])
        ->assertCanNotSeeTableRecords([$terbit]);
});
