<?php

use App\Enums\Jenjang;
use App\Enums\StatusPendaftaran;
use App\Filament\Pages\InputJuara;
use App\Filament\Pages\KelolaCabor;
use App\Filament\Resources\MataLombas\Pages\CreateMataLomba;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Filament\Resources\Pendaftarans\Pages\ViewPendaftaran;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\HasilService;
use App\Services\LombaService;
use App\Services\PendaftaranService;
use Database\Seeders\CaborSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed(CaborSeeder::class);

    $this->edisi = Edisi::factory()->aktif()->create([
        'tahun' => 2026, 'nama' => 'JMC 2026',
        'pendaftaran_buka' => now()->subWeek(), 'pendaftaran_tutup' => now()->addWeek(),
        'tanggal_mulai' => now()->addWeeks(3), 'tanggal_selesai' => now()->addWeeks(3)->addDays(4),
    ]);
    $this->keagamaan = Cabor::where('slug', 'keagamaan')->first();
    $this->gtk = Cabor::where('slug', 'gtk')->first();
    $this->mts = Madrasah::factory()->jenjang(Jenjang::MTs)->create(['nama' => 'MTsN 3 Jakarta']);
    $this->ma = Madrasah::factory()->jenjang(Jenjang::MA)->create(['nama' => 'MAN 4 Jakarta']);

    $this->cc = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->beregu()
        ->create(['nama' => 'Cerdas Cermat PAI', 'jenjang' => 'MTs', 'jumlah_anggota' => 3]);
    $this->mtq = Lomba::factory()->for($this->edisi)->for($this->keagamaan)
        ->create(['nama' => 'MTQ', 'jenjang' => 'MTs', 'kategori' => 'Putri']);
});

function kirimanDaftar(array $ubah = []): array
{
    return array_replace([
        'madrasah_id' => test()->mts->id,
        'lomba_id' => test()->cc->id,
        'peserta' => [
            ['nama' => 'Rizky Pratama', 'nisn' => '0101234561'],
            ['nama' => 'Annisa Rahma', 'nisn' => '0101234562'],
            ['nama' => 'Farhan Hakim', 'nisn' => '0101234563'],
        ],
        'guru_nama' => 'Siti Aminah, S.Pd.',
        'guru_wa' => '0812-3456-7890',
        'surat_tugas' => UploadedFile::fake()->create('surat.pdf', 120, 'application/pdf'),
        'setuju' => '1',
    ], $ubah);
}

describe('form /daftar', function () {
    it('menampilkan lomba edisi dengan jumlah anggota', function () {
        $this->get('/daftar')->assertOk()
            ->assertSeeInOrder(['Madrasah', 'Cabor', 'Mata lomba', 'Data peserta', 'Preview'])
            ->assertSee('MTsN 3 Jakarta')
            ->assertSee('Cerdas Cermat PAI')
            ->assertSee('DIBUKA S.D.');
    });

    it('menyimpan pendaftaran tim beserta peserta dan surat tugas', function () {
        $res = $this->postJson('/daftar', kirimanDaftar())->assertCreated()
            ->assertJsonPath('nomor', 'JMC26-0001')
            ->assertJsonPath('madrasah', 'MTsN 3 Jakarta');

        $p = Pendaftaran::with('peserta')->firstOrFail();

        expect($p->status)->toBe(StatusPendaftaran::Menunggu)
            ->and($p->guru_wa)->toBe('081234567890')
            ->and($p->peserta->pluck('nama')->all())->toBe(['Rizky Pratama', 'Annisa Rahma', 'Farhan Hakim'])
            ->and($p->peserta->pluck('urutan')->all())->toBe([1, 2, 3]);
        Storage::disk('local')->assertExists($p->surat_tugas_path);

        // Bukti hanya bisa dibuka lewat link bertanda tangan.
        $this->get($res->json('bukti'))->assertOk()->assertSee('JMC26-0001')->assertSee('0101234562')
            ->assertSee('Tim maks. 3 orang')
            ->assertSeeInOrder(['Rizky Pratama', 'KAPTEN', 'Annisa Rahma']);
        $this->get('/daftar/bukti/JMC26-0001')->assertForbidden();

        // Nomor berikutnya berurutan.
        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $this->mtq->id, 'peserta' => [['nama' => 'Keisha Amara', 'nisn' => '0101234570']]]))
            ->assertCreated()->assertJsonPath('nomor', 'JMC26-0002');
    });

    it('menerima tim dengan kapten saja (jumlah anggota adalah batas maksimal)', function () {
        $this->postJson('/daftar', kirimanDaftar(['peserta' => [['nama' => 'Rizky Pratama', 'nisn' => '0101234561']]]))
            ->assertCreated();

        $p = Pendaftaran::with('peserta.pendaftaran.lomba')->firstOrFail();
        expect($p->peserta)->toHaveCount(1)
            ->and($p->peserta->first()->isKapten())->toBeTrue();
    });

    it('menerima tim yang kurang dari maksimal dan menandai anggota pertama sebagai kapten', function () {
        $this->postJson('/daftar', kirimanDaftar(['peserta' => [
            ['nama' => 'Rizky Pratama', 'nisn' => '0101234561'],
            ['nama' => 'Annisa Rahma', 'nisn' => '0101234562'],
        ]]))->assertCreated();

        $peserta = Pendaftaran::with('peserta.pendaftaran.lomba')->firstOrFail()->peserta;
        expect($peserta->map->isKapten()->all())->toBe([true, false]);
    });

    it('menolak tim yang melebihi maksimal anggota', function () {
        $this->postJson('/daftar', kirimanDaftar(['peserta' => [
            ['nama' => 'Rizky Pratama', 'nisn' => '0101234561'],
            ['nama' => 'Annisa Rahma', 'nisn' => '0101234562'],
            ['nama' => 'Farhan Hakim', 'nisn' => '0101234563'],
            ['nama' => 'Dimas Saputra', 'nisn' => '0101234564'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['peserta'])
            ->assertJsonPath('errors.peserta.0', 'Lomba ini tim maksimal 3 orang; isi minimal kapten tim.');
    });

    it('menolak tim tanpa anggota', function () {
        $this->postJson('/daftar', kirimanDaftar(['peserta' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors(['peserta']);
    });

    it('lomba perorangan tetap tepat satu peserta', function () {
        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $this->mtq->id, 'peserta' => [
            ['nama' => 'Keisha Amara', 'nisn' => '0101234570'],
            ['nama' => 'Nadia Putri', 'nisn' => '0101234571'],
        ]]))->assertUnprocessable()->assertJsonPath('errors.peserta.0', 'Lomba ini perorangan, isi satu peserta.');

        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $this->mtq->id, 'peserta' => [['nama' => 'Keisha Amara', 'nisn' => '0101234570']]]))
            ->assertCreated();
    });

    it('menolak lomba yang bukan jenjang madrasahnya', function () {
        $this->postJson('/daftar', kirimanDaftar(['madrasah_id' => $this->ma->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['lomba_id']);
    });

    it('menolak madrasah yang sudah terdaftar di lomba yang sama', function () {
        $this->postJson('/daftar', kirimanDaftar())->assertCreated();

        $this->postJson('/daftar', kirimanDaftar(['peserta' => [
            ['nama' => 'Siswa Lain', 'nisn' => '0109999991'],
            ['nama' => 'Siswa Dua', 'nisn' => '0109999992'],
            ['nama' => 'Siswa Tiga', 'nisn' => '0109999993'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['lomba_id'])
            ->assertJsonPath('errors.lomba_id.0', 'MTsN 3 Jakarta sudah terdaftar di lomba Cerdas Cermat PAI · MTs · Beregu (nomor JMC26-0001). '
                .'Satu madrasah hanya boleh mengirim satu peserta/tim per mata lomba.');

        expect(Pendaftaran::count())->toBe(1);
    });

    it('mengizinkan daftar ulang setelah pendaftaran sebelumnya ditolak', function () {
        $this->postJson('/daftar', kirimanDaftar())->assertCreated();
        $lama = Pendaftaran::firstOrFail();

        // Masih menunggu: belum boleh daftar ulang, dan form menandai "sudah terdaftar".
        $this->postJson('/daftar', kirimanDaftar())->assertUnprocessable()->assertJsonValidationErrors(['lomba_id']);
        expect($this->get('/daftar')->viewData('terdaftar')->all())->toBe(["{$this->mts->id}-{$this->cc->id}"]);

        app(PendaftaranService::class)->tolak($lama, 'Surat tugas salah.');

        // Setelah ditolak: tanda hilang dan madrasah boleh mendaftar lagi (NISN yang sama pun boleh).
        expect($this->get('/daftar')->viewData('terdaftar')->all())->toBe([]);
        $this->postJson('/daftar', kirimanDaftar())->assertCreated()->assertJsonPath('nomor', 'JMC26-0002');

        // Tapi tidak boleh daftar untuk ketiga kalinya selama yang kedua masih aktif.
        $this->postJson('/daftar', kirimanDaftar())->assertUnprocessable()
            ->assertJsonPath('errors.lomba_id.0', fn (string $pesan) => str_contains($pesan, 'nomor JMC26-0002'));
    });

    it('tidak bisa memverifikasi pendaftaran lama yang ditolak jika madrasah sudah daftar ulang', function () {
        $this->postJson('/daftar', kirimanDaftar())->assertCreated();
        $lama = Pendaftaran::firstOrFail();
        app(PendaftaranService::class)->tolak($lama, 'Surat tugas salah.');
        $this->postJson('/daftar', kirimanDaftar())->assertCreated();

        expect(fn () => app(PendaftaranService::class)->verifikasi($lama->fresh()))
            ->toThrow(ValidationException::class, 'sudah mendaftar ulang');

        $this->actingAs(User::factory()->create());
        Livewire::test(ListPendaftarans::class)->callTableAction('verifikasi', $lama)->assertNotified('Tidak bisa diverifikasi');
        expect($lama->fresh()->status)->toBe(StatusPendaftaran::Ditolak);
    });

    it('mengizinkan madrasah yang sama di mata lomba lain, dan madrasah lain di mata lomba yang sama', function () {
        $lain = Madrasah::factory()->jenjang(Jenjang::MTs)->create();

        $this->postJson('/daftar', kirimanDaftar())->assertCreated();
        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $this->mtq->id, 'peserta' => [['nama' => 'Keisha Amara', 'nisn' => '0101234570']]]))
            ->assertCreated();
        $this->postJson('/daftar', kirimanDaftar(['madrasah_id' => $lain->id, 'peserta' => [['nama' => 'Siswa Lain', 'nisn' => '0109999991']]]))
            ->assertCreated();

        expect(Pendaftaran::count())->toBe(3);
    });

    it('database hanya mengizinkan satu pendaftaran aktif per madrasah per mata lomba', function () {
        $data = ['edisi_id' => $this->edisi->id, 'lomba_id' => $this->cc->id, 'madrasah_id' => $this->mts->id,
            'guru_nama' => 'Guru', 'guru_wa' => '0812'];

        // Beberapa pendaftaran ditolak boleh menumpuk.
        Pendaftaran::create($data + ['nomor' => 'X-1', 'status' => StatusPendaftaran::Ditolak]);
        Pendaftaran::create($data + ['nomor' => 'X-2', 'status' => StatusPendaftaran::Ditolak]);
        Pendaftaran::create($data + ['nomor' => 'X-3']); // menunggu

        expect(fn () => Pendaftaran::create($data + ['nomor' => 'X-4', 'status' => StatusPendaftaran::Diverifikasi]))
            ->toThrow(QueryException::class);
    });

    it('menolak NISN dobel di lomba yang sama', function () {
        $mts2 = Madrasah::factory()->jenjang(Jenjang::MTs)->create();
        $this->postJson('/daftar', kirimanDaftar())->assertCreated();

        $this->postJson('/daftar', kirimanDaftar(['madrasah_id' => $mts2->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['peserta']);
    });

    it('memvalidasi format NISN, WhatsApp, surat tugas, dan pernyataan', function () {
        $this->postJson('/daftar', kirimanDaftar([
            'peserta' => [['nama' => 'Rizky', 'nisn' => '12345'], ['nama' => 'B', 'nisn' => '0101234562'], ['nama' => 'Farhan', 'nisn' => '0101234563']],
            'guru_wa' => '12345',
            'surat_tugas' => UploadedFile::fake()->create('surat.docx', 100, 'application/msword'),
            'setuju' => '0',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'peserta.0.nisn', 'peserta.1.nama', 'guru_wa', 'surat_tugas', 'setuju',
        ]);

        $this->postJson('/daftar', kirimanDaftar(['surat_tugas' => UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf')]))
            ->assertUnprocessable()->assertJsonValidationErrors(['surat_tugas']);
    });

    it('meminta NUPTK 16 digit untuk lomba GTK', function () {
        $guru = Lomba::factory()->for($this->edisi)->for($this->gtk)->create(['nama' => 'Guru Berprestasi', 'jenjang' => 'MTs', 'kategori' => 'Umum']);

        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $guru->id, 'peserta' => [['nama' => 'Ahmad Fauzi, S.Pd.', 'nisn' => '0101234561']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['peserta']);

        $this->postJson('/daftar', kirimanDaftar(['lomba_id' => $guru->id, 'peserta' => [['nama' => 'Ahmad Fauzi, S.Pd.', 'nisn' => '1234567890123456']]]))
            ->assertCreated();
    });

    it('menolak pendaftaran di luar masa pendaftaran', function () {
        $this->edisi->update(['pendaftaran_tutup' => now()->subDay(), 'pendaftaran_buka' => now()->subMonth()]);

        $this->postJson('/daftar', kirimanDaftar())->assertUnprocessable()->assertJsonValidationErrors(['lomba_id']);
        $this->get('/daftar')->assertOk()->assertSee('Pendaftaran ditutup');
        expect(Pendaftaran::count())->toBe(0);
    });
});

describe('beranda tahap registrasi', function () {
    it('menampilkan podium segera dan angka pendaftaran', function () {
        $this->get('/')->assertOk()->assertSee('SEGERA')->assertSee('Podium terisi otomatis');

        $this->postJson('/daftar', kirimanDaftar())->assertCreated();

        $this->get('/')->assertOk()
            ->assertSee('SEGERA')
            ->assertSeeInOrder(['1', 'Pendaftaran', '1', 'Madrasah', '3', 'Peserta'])
            ->assertSee('PENDAFTARAN DITUTUP 7 HARI LAGI')
            ->assertDontSee('PODIUM SEMENTARA');
    });
});

describe('admin pendaftaran', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        $this->daftar = app(PendaftaranService::class)->daftar($this->edisi, kirimanDaftar(), UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'));
    });

    it('menampilkan daftar dan detail pendaftaran', function () {
        Livewire::test(ListPendaftarans::class)
            ->assertCanSeeTableRecords([$this->daftar])
            ->assertTableColumnExists('nomor')->assertTableColumnExists('lomba.nama')->assertTableColumnExists('madrasah.nama')
            ->assertTableColumnExists('status')->assertTableColumnExists('created_at')
            ->assertTableColumnDoesNotExist('peserta.nama')
            ->assertSeeHtml('Aksi') // label kolom menu titik tiga
            // Nama peserta tidak tampil di tabel, tapi tetap bisa dicari.
            ->searchTable('Farhan')->assertCanSeeTableRecords([$this->daftar])
            ->searchTable('Tidak Ada')->assertCanNotSeeTableRecords([$this->daftar]);

        Livewire::test(ViewPendaftaran::class, ['record' => $this->daftar->getRouteKey()])->assertOk()
            ->assertSee('JMC26-0001')->assertSee('0101234563')
            ->assertSeeInOrder(['Guru pendamping', 'Anggota tim', 'Rizky Pratama (Kapten)']);
    });

    it('memverifikasi dan menolak dengan catatan', function () {
        Livewire::test(ListPendaftarans::class)->callTableAction('verifikasi', $this->daftar);
        expect($this->daftar->fresh()->status)->toBe(StatusPendaftaran::Diverifikasi)
            ->and($this->daftar->fresh()->diverifikasi_pada)->not->toBeNull();

        Livewire::test(ListPendaftarans::class)
            ->callTableAction('tolak', $this->daftar, data: ['catatan' => 'Surat tugas tidak ditandatangani.'])
            ->assertHasNoTableActionErrors();
        expect($this->daftar->fresh()->status)->toBe(StatusPendaftaran::Ditolak)
            ->and($this->daftar->fresh()->catatan)->toBe('Surat tugas tidak ditandatangani.');
    });
});

describe('admin per cabor', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        $this->daftar = app(PendaftaranService::class)->daftar($this->edisi, kirimanDaftar());
        $this->olahraga = Cabor::where('slug', 'olahraga')->first();
        $this->futsal = Lomba::factory()->for($this->edisi)->for($this->olahraga)->create(['nama' => 'Futsal', 'jenjang' => 'MTs', 'kategori' => 'Putra']);
    });

    it('menampilkan satu menu per cabor dengan badge antrean verifikasi', function () {
        $menu = collect(KelolaCabor::getNavigationItems());

        expect($menu)->toHaveCount(7)
            ->and($menu->first()->getLabel())->toContain('Keagamaan')
            ->and($menu->first()->getBadge())->toBe('1')        // 1 pendaftaran menunggu
            ->and($menu->get(2)->getBadge())->toBe('0/1');     // Olahraga: 0 dari 1 lomba punya juara
    });

    it('tab Lomba & Juara hanya berisi lomba cabor itu', function () {
        Livewire::test(KelolaCabor::class, ['cabor' => $this->keagamaan])
            ->assertCanSeeTableRecords([$this->cc, $this->mtq])
            ->assertCanNotSeeTableRecords([$this->futsal])
            ->assertSee('Input juara');
    });

    it('tab Peserta hanya berisi pendaftaran cabor itu dan bisa memverifikasi', function () {
        Livewire::withQueryParams(['tab' => 'peserta'])
            ->test(KelolaCabor::class, ['cabor' => $this->keagamaan])
            ->assertCanSeeTableRecords([$this->daftar])
            ->callTableAction('verifikasi', $this->daftar);

        expect($this->daftar->fresh()->status)->toBe(StatusPendaftaran::Diverifikasi);

        Livewire::withQueryParams(['tab' => 'peserta'])
            ->test(KelolaCabor::class, ['cabor' => $this->olahraga])
            ->assertCanNotSeeTableRecords([$this->daftar]);
    });

    it('menyiapkan lomba otomatis dari master saat halaman cabor dibuka', function () {
        $adzan = MataLomba::factory()->for($this->keagamaan)->create(['nama' => 'Adzan', 'jenjang' => 'MI', 'kategori' => 'Putra', 'jumlah_anggota' => 1]);
        $nonaktif = MataLomba::factory()->for($this->keagamaan)->create(['nama' => 'Lomba Lama', 'jenjang' => 'MI', 'kategori' => 'Umum', 'is_aktif' => false]);
        $silat = MataLomba::factory()->for($this->olahraga)->create(['nama' => 'Pencak Silat', 'jenjang' => 'MTs', 'kategori' => 'Putra']);

        Livewire::test(KelolaCabor::class, ['cabor' => $this->keagamaan])->assertOk();

        $mataLombaEdisi = Lomba::where('edisi_id', $this->edisi->id)->pluck('mata_lomba_id');
        expect($mataLombaEdisi)->toContain($adzan->id)
            ->not->toContain($nonaktif->id)
            ->not->toContain($silat->id); // cabor lain baru disiapkan saat halamannya dibuka

        // Dibuka lagi tidak menggandakan lomba.
        Livewire::test(KelolaCabor::class, ['cabor' => $this->keagamaan]);
        expect(Lomba::where('edisi_id', $this->edisi->id)->where('mata_lomba_id', $adzan->id)->count())->toBe(1);
    });

    it('menghapus lomba yang mata lombanya dinonaktifkan, kecuali sudah ada juara atau pendaftar', function () {
        $kosong = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->create(['nama' => 'Tanpa Peserta', 'jenjang' => 'MI']);
        $kosong->mataLomba->update(['is_aktif' => false]);
        $this->cc->mataLomba->update(['is_aktif' => false]); // punya pendaftar → tetap

        app(LombaService::class)->sinkronMaster($this->edisi);

        expect(Lomba::find($kosong->id))->toBeNull()
            ->and(Lomba::find($this->cc->id))->not->toBeNull();
    });

    it('membuat lomba untuk edisi aktif begitu mata lomba ditambahkan di admin', function () {
        Livewire::test(CreateMataLomba::class)
            ->fillForm(['cabor_id' => $this->keagamaan->id, 'nama' => 'Kaligrafi', 'jenjang' => 'MTs', 'kategori' => 'Umum', 'jumlah_anggota' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Lomba::where('edisi_id', $this->edisi->id)->where('nama', 'Kaligrafi')->exists())->toBeTrue();
    });

    it('menolak peserta yang sama dipilih dua kali di Input Juara', function () {
        app(PendaftaranService::class)->verifikasi($this->daftar);
        $this->cc->update(['kuota_juara_1' => 2]);

        Livewire::test(InputJuara::class, ['lomba' => $this->cc])
            ->set('data.juara_1', [
                ['manual' => false, 'pendaftaran_id' => $this->daftar->id, 'madrasah_id' => null, 'nama_peserta' => null],
                ['manual' => false, 'pendaftaran_id' => $this->daftar->id, 'madrasah_id' => null, 'nama_peserta' => null],
            ])
            ->call('simpan')
            ->assertHasErrors();

        expect($this->cc->hasil()->count())->toBe(0);
    });
});

describe('juara dari pendaftaran', function () {
    beforeEach(function () {
        $this->daftar = app(PendaftaranService::class)->daftar($this->edisi, kirimanDaftar());
    });

    it('mengisi madrasah dan nama siswa pemenang dari pendaftaran terverifikasi', function () {
        app(PendaftaranService::class)->verifikasi($this->daftar);

        $hasil = app(HasilService::class)->simpan($this->cc, [['peringkat' => 1, 'pendaftaran_id' => $this->daftar->id]]);

        expect($hasil->first()->madrasah_id)->toBe($this->mts->id)
            ->and($hasil->first()->pendaftaran_id)->toBe($this->daftar->id)
            ->and($hasil->first()->anggota())->toBe(['Rizky Pratama', 'Annisa Rahma', 'Farhan Hakim']);

        $this->get('/cabor/keagamaan')->assertSee('TIM · 3 ANGGOTA')->assertSeeInOrder(['Rizky Pratama', 'Annisa Rahma', 'Farhan Hakim']);
    });

    it('menolak pendaftaran yang belum diverifikasi atau dari lomba lain', function () {
        expect(fn () => app(HasilService::class)->simpan($this->cc, [['peringkat' => 1, 'pendaftaran_id' => $this->daftar->id]]))
            ->toThrow(ValidationException::class);

        app(PendaftaranService::class)->verifikasi($this->daftar);
        expect(fn () => app(HasilService::class)->simpan($this->mtq, [['peringkat' => 1, 'pendaftaran_id' => $this->daftar->id]]))
            ->toThrow(ValidationException::class);
    });

    it('tidak bisa menolak pendaftaran yang sudah jadi juara', function () {
        app(PendaftaranService::class)->verifikasi($this->daftar);
        app(HasilService::class)->simpan($this->cc, [['peringkat' => 2, 'pendaftaran_id' => $this->daftar->id]]);

        expect(fn () => app(PendaftaranService::class)->tolak($this->daftar, 'x'))->toThrow(ValidationException::class);
    });

    it('memilih juara dari peserta terdaftar di form admin', function () {
        $this->actingAs(User::factory()->create());
        app(PendaftaranService::class)->verifikasi($this->daftar);

        Livewire::test(InputJuara::class, ['lomba' => $this->cc])
            ->fillForm(['juara_1' => [['manual' => false, 'pendaftaran_id' => $this->daftar->id, 'madrasah_id' => null, 'nama_peserta' => null]]])
            ->call('simpan')
            ->assertHasNoFormErrors();

        expect($this->cc->hasil()->first()->nama_peserta)->toBe("Rizky Pratama\nAnnisa Rahma\nFarhan Hakim");
    });
});
