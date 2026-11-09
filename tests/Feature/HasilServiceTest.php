<?php

use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Services\HasilService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(HasilService::class);
    $this->lomba = Lomba::factory()->create(['nama' => 'Tenis Meja']); // kuota default 1/2/3
    $this->m = Madrasah::factory()->count(4)->create();

    $this->baris = fn (int $peringkat, int $i = 0, ?string $peserta = null) => [
        'peringkat' => $peringkat,
        'madrasah_id' => $this->m[$i]->id,
        'nama_peserta' => $peserta,
    ];
});

/** Jalankan $fn dan kembalikan pesan error validasi (kosong jika lolos). */
function errorValidasi(callable $fn): array
{
    try {
        $fn();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('menyimpan juara sesuai kuota default 1 emas, 2 perak, 3 perunggu', function () {
    $hasil = $this->service->simpan($this->lomba, [
        ($this->baris)(3, 0), ($this->baris)(1, 0, ' Ahmad '), ($this->baris)(2, 1),
        ($this->baris)(2, 2), ($this->baris)(3, 1), ($this->baris)(3, 0),
    ]);

    expect($hasil)->toHaveCount(6)
        ->and(Hasil::count())->toBe(6)
        ->and($this->lomba->hasil()->get()->map(fn ($h) => [$h->peringkat, $h->urutan])->all())
        ->toBe([[1, 1], [2, 1], [2, 2], [3, 1], [3, 2], [3, 3]])
        ->and($this->lomba->hasil()->where('peringkat', 1)->first()->nama_peserta)->toBe('Ahmad');
});

it('menolak juara 1 melebihi kuota default', function () {
    $errors = errorValidasi(fn () => $this->service->simpan($this->lomba, [
        ($this->baris)(1, 0), ($this->baris)(1, 1),
    ]));

    expect($errors)->toHaveKey('juara_1')
        ->and($errors['juara_1'][0])->toContain('maksimal 1')
        ->and(Hasil::count())->toBe(0);
});

it('menolak juara 2 dan juara 3 melebihi kuota default', function () {
    $errors = errorValidasi(fn () => $this->service->simpan($this->lomba, [
        ($this->baris)(2, 0), ($this->baris)(2, 1), ($this->baris)(2, 2),
        ($this->baris)(3, 0), ($this->baris)(3, 1), ($this->baris)(3, 2), ($this->baris)(3, 3),
    ]));

    expect(array_keys($errors))->toBe(['juara_2', 'juara_3']);
});

it('mengikuti kuota khusus yang diatur per lomba', function () {
    $lomba = Lomba::factory()->kuota(2, 0, 4)->create();

    $this->service->simpan($lomba, [
        ($this->baris)(1, 0), ($this->baris)(1, 1),
        ($this->baris)(3, 0), ($this->baris)(3, 1), ($this->baris)(3, 2), ($this->baris)(3, 3),
    ]);
    expect($lomba->hasil()->count())->toBe(6);

    $errors = errorValidasi(fn () => $this->service->simpan($lomba, [($this->baris)(2, 0)]));
    expect($errors)->toHaveKey('juara_2')
        ->and($lomba->hasil()->count())->toBe(6);
});

it('tidak mengubah data lama jika simpan gagal validasi', function () {
    $this->service->simpan($this->lomba, [($this->baris)(1, 0), ($this->baris)(2, 1)]);

    errorValidasi(fn () => $this->service->simpan($this->lomba, [
        ($this->baris)(1, 2), ($this->baris)(1, 3),
    ]));

    expect($this->lomba->hasil()->pluck('madrasah_id')->all())->toBe([$this->m[0]->id, $this->m[1]->id]);
});

it('mengosongkan hasil lomba jika simpan dengan daftar kosong', function () {
    $this->service->simpan($this->lomba, [($this->baris)(1, 0)]);
    $this->service->simpan($this->lomba, []);

    expect($this->lomba->hasil()->count())->toBe(0);
});

it('menolak madrasah yang tidak ada dan peringkat di luar 1–3', function () {
    $errors = errorValidasi(fn () => $this->service->simpan($this->lomba, [
        ['peringkat' => 1, 'madrasah_id' => 999999, 'nama_peserta' => null],
        ['peringkat' => 4, 'madrasah_id' => $this->m[0]->id, 'nama_peserta' => null],
        ['peringkat' => 2, 'madrasah_id' => null, 'nama_peserta' => null],
    ]));

    expect($errors)->toHaveKeys(['baris.0.madrasah_id', 'baris.1.peringkat', 'baris.2.madrasah_id'])
        ->and($errors['baris.0.madrasah_id'][0])->toBe('Madrasah tidak ditemukan.');
});

it('menambah juara satu per satu sampai kuota lalu menolak sisanya (import Excel)', function () {
    expect($this->service->sisaKuota($this->lomba, 2))->toBe(2);

    $this->service->tambah($this->lomba, ($this->baris)(2, 0));
    $h = $this->service->tambah($this->lomba, ($this->baris)(2, 0, 'Peserta lain'));

    expect($h->urutan)->toBe(2)
        ->and($this->service->sisaKuota($this->lomba, 2))->toBe(0);

    $errors = errorValidasi(fn () => $this->service->tambah($this->lomba, ($this->baris)(2, 1)));

    expect($errors)->toHaveKey('juara_2')
        ->and($this->lomba->hasil()->where('peringkat', 2)->count())->toBe(2);
});

it('menolak penurunan kuota di bawah jumlah juara yang sudah ada', function () {
    $this->service->simpan($this->lomba, [($this->baris)(3, 0), ($this->baris)(3, 1), ($this->baris)(3, 2)]);

    $errors = errorValidasi(fn () => $this->service->validasiPerubahanKuota($this->lomba, [3 => 2]));
    expect($errors)->toHaveKey('kuota_juara_3');

    expect(errorValidasi(fn () => $this->service->validasiPerubahanKuota($this->lomba, [1 => 0, 2 => 0, 3 => 3])))->toBe([]);
});
