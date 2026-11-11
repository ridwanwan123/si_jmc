<?php

use App\Data\BarisKlasemen;
use App\Enums\Jenjang;
use App\Enums\StatusPendaftaran;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\Pendaftaran;
use App\Services\KlasemenService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026]);
    $this->caborA = Cabor::factory()->create(['nama' => 'Sains']);
    $this->caborB = Cabor::factory()->create(['nama' => 'Olahraga']);
    $this->lomba = Lomba::factory()->for($this->edisi)->for($this->caborA)->kuota(9, 9, 9)->create();
    $this->service = app(KlasemenService::class);

    $this->madrasah = fn (string $nama, Jenjang $jenjang = Jenjang::MTs) => Madrasah::factory()->jenjang($jenjang)->create(['nama' => $nama]);
});

/** @return list<array{string,int,int,int}> nama, emas, perak, perunggu */
function ringkas($klasemen): array
{
    return $klasemen->map(fn (BarisKlasemen $b) => [$b->nama, $b->emas, $b->perak, $b->perunggu])->all();
}

it('mengurutkan emas, lalu perak, lalu perunggu, lalu nama', function () {
    $a = ($this->madrasah)('MTs A');
    $b = ($this->madrasah)('MTs B');
    $c = ($this->madrasah)('MTs C');
    $d = ($this->madrasah)('MTs D');

    juara($this->lomba, $a, 1);
    juara($this->lomba, $b, 2, 3);
    juara($this->lomba, $c, 1);
    juara($this->lomba, $c, 3);
    juara($this->lomba, $d, 1);
    juara($this->lomba, $d, 2);

    $klasemen = $this->service->klasemen($this->edisi);

    expect(ringkas($klasemen))->toBe([
        ['MTs D', 1, 1, 0],
        ['MTs C', 1, 0, 1],
        ['MTs A', 1, 0, 0],
        ['MTs B', 0, 3, 0],
    ])
        ->and($klasemen->pluck('peringkat')->all())->toBe([1, 2, 3, 4])
        ->and($klasemen->contains(fn ($b) => $b->seri))->toBeFalse()
        ->and($klasemen->first()->total())->toBe(2);
});

it('memberi peringkat sama dengan tanda = dan melompatkan peringkat berikutnya', function () {
    juara($this->lomba, ($this->madrasah)('Satu'), 1, 2);
    juara($this->lomba, ($this->madrasah)('Dua'), 1);
    juara($this->lomba, ($this->madrasah)('Tiga Beta'), 2);
    juara($this->lomba, ($this->madrasah)('Tiga Alpha'), 2);
    juara($this->lomba, ($this->madrasah)('Lima'), 3);

    $klasemen = $this->service->klasemen($this->edisi);

    expect($klasemen->pluck('nama')->all())->toBe(['Satu', 'Dua', 'Tiga Alpha', 'Tiga Beta', 'Lima'])
        ->and($klasemen->pluck('peringkat')->all())->toBe([1, 2, 3, 3, 5])
        ->and($klasemen->map->labelPeringkat()->all())->toBe(['01', '02', '03', '03', '05'])
        ->and($klasemen->map->seri->all())->toBe([false, false, true, true, false]);
});

it('memfilter berdasarkan cabor lomba dan menghitung ulang peringkat', function () {
    $lombaB = Lomba::factory()->for($this->edisi)->for($this->caborB)->kuota(9, 9, 9)->create();
    $a = ($this->madrasah)('Madrasah A');
    $b = ($this->madrasah)('Madrasah B');

    juara($this->lomba, $a, 1);
    juara($lombaB, $b, 1, 2);
    juara($lombaB, $a, 3);

    expect(ringkas($this->service->klasemen($this->edisi)))->toBe([
        ['Madrasah B', 2, 0, 0],
        ['Madrasah A', 1, 0, 1],
    ]);

    $sains = $this->service->klasemen($this->edisi, caborId: $this->caborA->id);
    expect(ringkas($sains))->toBe([['Madrasah A', 1, 0, 0]])
        ->and($sains->first()->peringkat)->toBe(1);

    expect(ringkas($this->service->klasemen($this->edisi, caborId: $this->caborB->id)))->toBe([
        ['Madrasah B', 2, 0, 0],
        ['Madrasah A', 0, 0, 1],
    ]);
});

it('memfilter berdasarkan jenjang madrasah, bukan jenjang lomba', function () {
    $mi = ($this->madrasah)('MIN 1', Jenjang::MI);
    $mts = ($this->madrasah)('MTsN 1', Jenjang::MTs);

    $lombaMts = Lomba::factory()->for($this->edisi)->for($this->caborA)->create(['jenjang' => Jenjang::MTs]);

    juara($lombaMts, $mts, 1);
    juara($lombaMts, $mi, 2); // madrasah MI juara di lomba jenjang MTs

    $klasemenMi = $this->service->klasemen($this->edisi, jenjang: Jenjang::MI);

    expect(ringkas($klasemenMi))->toBe([['MIN 1', 0, 1, 0]])
        ->and($klasemenMi->first()->peringkat)->toBe(1)
        ->and($this->service->klasemen($this->edisi, jenjang: Jenjang::MTs)->pluck('nama')->all())->toBe(['MTsN 1'])
        ->and($this->service->klasemen($this->edisi, jenjang: Jenjang::MA))->toBeEmpty();
});

it('menggabungkan filter cabor dan jenjang', function () {
    $lombaB = Lomba::factory()->for($this->edisi)->for($this->caborB)->create();
    $mi = ($this->madrasah)('MIN 1', Jenjang::MI);
    $mts = ($this->madrasah)('MTsN 1', Jenjang::MTs);

    juara($this->lomba, $mi, 1);
    juara($lombaB, $mi, 2);
    juara($lombaB, $mts, 1);

    expect(ringkas($this->service->klasemen($this->edisi, $this->caborB->id, Jenjang::MI)))
        ->toBe([['MIN 1', 0, 1, 0]]);
});

it('menghitung lomba beregu sebagai satu medali untuk madrasah', function () {
    $beregu = Lomba::factory()->for($this->edisi)->for($this->caborB)->beregu()->create();
    $m = ($this->madrasah)('MAN 2', Jenjang::MA);

    juara($beregu, $m, 1, peserta: 'Ahmad, Budi, Citra, Dina, Eko');

    $baris = $this->service->klasemen($this->edisi)->first();

    expect($baris->emas)->toBe(1)->and($baris->total())->toBe(1);
});

it('menghitung juara ganda: 1 emas, 2 perak, 3 perunggu dalam satu lomba', function () {
    $lomba = Lomba::factory()->for($this->edisi)->for($this->caborA)->create(); // kuota default 1/2/3
    $a = ($this->madrasah)('A');
    $b = ($this->madrasah)('B');
    $c = ($this->madrasah)('C');
    $d = ($this->madrasah)('D');

    juara($lomba, $a, 1);
    juara($lomba, $b, 2);
    juara($lomba, $c, 2);
    juara($lomba, $a, 3);
    juara($lomba, $b, 3);
    juara($lomba, $d, 3);

    $klasemen = $this->service->klasemen($this->edisi);

    expect(ringkas($klasemen))->toBe([
        ['A', 1, 0, 1],
        ['B', 0, 1, 1],
        ['C', 0, 1, 0],
        ['D', 0, 0, 1],
    ])
        ->and($klasemen->sum(fn ($b) => $b->total()))->toBe(6);
});

it('menghitung dua perak untuk madrasah yang sama di satu lomba', function () {
    $m = ($this->madrasah)('MTsN 3');

    juara($this->lomba, $m, 2, 2);

    expect(ringkas($this->service->klasemen($this->edisi)))->toBe([['MTsN 3', 0, 2, 0]]);
});

it('menghitung medali cabor GTK ke madrasah tempat guru bertugas', function () {
    $gtk = Cabor::factory()->create(['nama' => 'GTK', 'slug' => 'gtk']);
    $lombaGtk = Lomba::factory()->for($this->edisi)->for($gtk)->create();
    $m = ($this->madrasah)('MAN 1', Jenjang::MA);

    juara($this->lomba, $m, 3);
    juara($lombaGtk, $m, 1, peserta: 'Ibu Guru Siti, S.Pd.');

    expect(ringkas($this->service->klasemen($this->edisi)))->toBe([['MAN 1', 1, 0, 1]]);
});

it('tidak memasukkan madrasah tanpa medali dan hasil edisi lain', function () {
    $lain = Edisi::factory()->create(['tahun' => 2025]);
    $lombaLain = Lomba::factory()->for($lain)->for($this->caborA)->create();
    $a = ($this->madrasah)('Juara');
    ($this->madrasah)('Tanpa Medali');

    juara($this->lomba, $a, 2);
    juara($lombaLain, $a, 1, 1);

    expect(ringkas($this->service->klasemen($this->edisi)))->toBe([['Juara', 0, 1, 0]])
        ->and(ringkas($this->service->klasemen($lain)))->toBe([['Juara', 1, 0, 0]]);
});

it('mengikutkan RA di klasemen umum dan bisa dikeluarkan lewat config', function () {
    $ra = ($this->madrasah)('RA Al-Hikmah', Jenjang::RA);
    $mi = ($this->madrasah)('MIN 5', Jenjang::MI);

    juara($this->lomba, $ra, 1);
    juara($this->lomba, $mi, 2);

    expect($this->service->klasemen($this->edisi)->pluck('nama')->all())->toBe(['RA Al-Hikmah', 'MIN 5']);

    config(['jmc.jenjang_klasemen_umum' => ['MI', 'MTs', 'MA']]);

    $umum = $this->service->klasemen($this->edisi);

    expect($umum->pluck('nama')->all())->toBe(['MIN 5'])
        ->and($umum->first()->peringkat)->toBe(1)
        // filter jenjang RA tetap bisa dipakai
        ->and($this->service->klasemen($this->edisi, jenjang: Jenjang::RA)->pluck('nama')->all())->toBe(['RA Al-Hikmah']);
});

it('menyediakan rincian medali per cabor untuk banyak madrasah sekaligus', function () {
    $lombaB = Lomba::factory()->for($this->edisi)->for($this->caborB)->kuota(9, 9, 9)->create();
    $a = ($this->madrasah)('A');
    $b = ($this->madrasah)('B');

    juara($this->lomba, $a, 1);
    juara($this->lomba, $a, 3);
    juara($lombaB, $a, 2, 2);
    juara($lombaB, $b, 1);

    $rincian = $this->service->rincianPerCabor($this->edisi, [$a->id, $b->id]);

    expect($rincian[$a->id][$this->caborA->id])->toBe(['emas' => 1, 'perak' => 0, 'perunggu' => 1, 'total' => 2])
        ->and($rincian[$a->id][$this->caborB->id])->toBe(['emas' => 0, 'perak' => 2, 'perunggu' => 0, 'total' => 2])
        ->and($rincian[$b->id])->toHaveKey($this->caborB->id)
        ->and($rincian[$b->id])->not->toHaveKey($this->caborA->id)
        ->and($this->service->rincianPerCabor($this->edisi, []))->toBe([]);
});

it('menentukan pemuncak tiap cabor, termasuk jika seri', function () {
    $lombaB = Lomba::factory()->for($this->edisi)->for($this->caborB)->kuota(9, 9, 9)->create();
    $a = ($this->madrasah)('A');
    $b = ($this->madrasah)('B');
    $c = ($this->madrasah)('C');

    juara($this->lomba, $a, 1, 2);
    juara($this->lomba, $b, 1);
    juara($lombaB, $b, 2);
    juara($lombaB, $c, 2);

    $pemuncak = $this->service->pemuncakCabor($this->edisi);

    expect($pemuncak[$this->caborA->id]->pluck('nama')->all())->toBe(['A'])
        ->and($pemuncak[$this->caborB->id]->pluck('nama')->all())->toBe(['B', 'C'])
        ->and($pemuncak[$this->caborB->id]->first()->labelPeringkat())->toBe('01')
        ->and($pemuncak[$this->caborB->id]->first()->seri)->toBeTrue();
});

it('mengambil peringkat satu madrasah dan riwayat antar-edisi', function () {
    $lama = Edisi::factory()->create(['tahun' => 2025]);
    $lombaLama = Lomba::factory()->for($lama)->for($this->caborA)->create();
    $a = ($this->madrasah)('A');
    $b = ($this->madrasah)('B');

    juara($this->lomba, $b, 1);
    juara($this->lomba, $a, 2);
    juara($lombaLama, $a, 1);

    expect($this->service->peringkatMadrasah($this->edisi, $a)->peringkat)->toBe(2)
        ->and($this->service->peringkatMadrasah($this->edisi, ($this->madrasah)('C')))->toBeNull();

    $riwayat = $this->service->riwayat($a);

    expect($riwayat->map(fn ($r) => [$r['edisi']->tahun, $r['baris']?->peringkat])->all())
        ->toBe([[2026, 2], [2025, 1]]);
});

it('menghitung angka ringkas edisi', function () {
    Lomba::factory()->for($this->edisi)->for($this->caborB)->count(2)->create(); // belum ada hasil
    $a = ($this->madrasah)('A');
    $b = ($this->madrasah)('B');

    juara($this->lomba, $a, 1);
    juara($this->lomba, $b, 2, 2);
    juara($this->lomba, $a, 3);

    expect($this->service->ringkasan($this->edisi))->toBe([
        'jumlah_lomba' => 3,
        'lomba_selesai' => 1,
        'emas' => 1,
        'perak' => 2,
        'perunggu' => 1,
        'total_medali' => 4,
        'madrasah_peraih' => 2,
        'madrasah_peserta' => 2,
    ]);
});

it('menghitung madrasah peserta dari pendaftaran diverifikasi dan peraih medali', function () {
    [$lomba2, $lomba3] = Lomba::factory()->for($this->edisi)->for($this->caborB)->count(2)->create()->all();
    $daftar = function (Lomba $lomba, Madrasah $m, StatusPendaftaran $status) {
        static $n = 0;
        Pendaftaran::create([
            'edisi_id' => $lomba->edisi_id, 'lomba_id' => $lomba->id, 'madrasah_id' => $m->id,
            'nomor' => 'T-'.++$n, 'guru_nama' => 'Guru', 'guru_wa' => '0812', 'status' => $status,
        ]);
    };

    $a = ($this->madrasah)('A'); // 2 lomba diverifikasi -> dihitung 1
    $b = ($this->madrasah)('B'); // diverifikasi + juara -> dihitung 1
    $c = ($this->madrasah)('C'); // juara tanpa pendaftaran -> dihitung
    $d = ($this->madrasah)('D'); // masih menunggu -> tidak dihitung
    $e = ($this->madrasah)('E'); // ditolak -> tidak dihitung

    $daftar($this->lomba, $a, StatusPendaftaran::Diverifikasi);
    $daftar($lomba2, $a, StatusPendaftaran::Diverifikasi);
    $daftar($this->lomba, $b, StatusPendaftaran::Diverifikasi);
    $daftar($lomba3, $d, StatusPendaftaran::Menunggu);
    $daftar($lomba3, $e, StatusPendaftaran::Ditolak);
    juara($this->lomba, $b, 1);
    juara($this->lomba, $c, 2);

    // Pendaftaran di edisi lain tidak ikut dihitung.
    $lain = Edisi::factory()->create(['tahun' => 2020]);
    $daftar(Lomba::factory()->for($lain)->for($this->caborB)->create(), $d, StatusPendaftaran::Diverifikasi);

    expect($this->service->jumlahMadrasahPeserta($this->edisi))->toBe(3)
        ->and($this->service->ringkasan($this->edisi)['madrasah_peserta'])->toBe(3)
        ->and($this->service->jumlahMadrasahPeserta($lain))->toBe(1);
});

it('menampilkan waktu terakhir diperbarui dalam WIB', function () {
    expect($this->service->labelTerakhirDiperbarui($this->edisi))->toBeNull();

    $this->travelTo(Carbon::create(2026, 11, 4, 9, 0, 0, 'Asia/Jakarta'));
    juara($this->lomba, ($this->madrasah)('A'), 1);

    $this->travelTo(Carbon::create(2026, 11, 5, 11, 30, 0, 'Asia/Jakarta'));
    juara($this->lomba, ($this->madrasah)('B'), 2);

    // hasil di edisi lain yang lebih baru tidak dihitung
    $this->travelTo(Carbon::create(2026, 11, 6, 8, 0, 0, 'Asia/Jakarta'));
    juara(Lomba::factory()->create(), ($this->madrasah)('C'), 1);

    expect($this->service->labelTerakhirDiperbarui($this->edisi))->toBe('05.11.2026 · 11:30 WIB');
});
