<?php

namespace App\Services;

use App\Enums\Medali;
use App\Enums\StatusPendaftaran;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Pendaftaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat validasi dan penyimpanan hasil (juara) lomba.
 * Dipakai oleh form admin (Filament) dan import Excel.
 *
 * Format satu baris juara:
 *   ['peringkat' => 1|2|3, 'madrasah_id' => int, 'nama_peserta' => ?string, 'pendaftaran_id' => ?int]
 *
 * Jika pendaftaran_id diisi, madrasah diambil dari pendaftaran dan nama_peserta (kosong)
 * diisi nama siswa terdaftar, satu per baris. Pendaftaran harus milik lomba itu dan sudah diverifikasi.
 */
class HasilService
{
    /**
     * Validasi seluruh daftar juara sebuah lomba.
     *
     * @param  list<array<string, mixed>>  $baris
     *
     * @throws ValidationException
     */
    public function validasi(Lomba $lomba, array $baris): void
    {
        $baris = $this->lengkapi($lomba, $baris);
        Validator::make(['baris' => $baris], $this->aturanBaris('baris.*.'), $this->pesan())->validate();

        $jumlah = collect($baris)->countBy(fn (array $b) => (int) $b['peringkat']);

        $this->cekKuota($lomba, fn (int $p) => $jumlah->get($p, 0));
    }

    /**
     * Ganti seluruh juara lomba (dipakai repeater Juara 1/2/3 di admin).
     * Semua-atau-tidak-sama-sekali: jika validasi gagal, data lama tidak berubah.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return Collection<int, Hasil>
     *
     * @throws ValidationException
     */
    public function simpan(Lomba $lomba, array $baris): Collection
    {
        $this->validasi($lomba, $baris);
        $baris = $this->lengkapi($lomba, $baris);

        return DB::transaction(function () use ($lomba, $baris) {
            $lomba->hasil()->delete();

            $urutan = [];

            return collect($baris)
                ->sortBy(fn (array $b) => (int) $b['peringkat'])
                ->values()
                ->map(function (array $b) use ($lomba, &$urutan) {
                    $p = (int) $b['peringkat'];
                    $urutan[$p] = ($urutan[$p] ?? 0) + 1;

                    return $lomba->hasil()->create([
                        'peringkat' => $p,
                        'madrasah_id' => (int) $b['madrasah_id'],
                        'pendaftaran_id' => $b['pendaftaran_id'] ?? null,
                        'nama_peserta' => filled($b['nama_peserta'] ?? null) ? trim($b['nama_peserta']) : null,
                        'urutan' => $urutan[$p],
                    ]);
                });
        });
    }

    /**
     * Tambah satu juara (dipakai import Excel, satu baris per juara).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function tambah(Lomba $lomba, array $data): Hasil
    {
        $data = $this->lengkapi($lomba, [$data])[0];
        Validator::make($data, $this->aturanBaris(), $this->pesan())->validate();

        return DB::transaction(function () use ($lomba, $data) {
            $p = (int) $data['peringkat'];

            // Kunci baris lomba agar dua import paralel tidak melewati kuota.
            Lomba::query()->whereKey($lomba->getKey())->lockForUpdate()->first();

            $sudahAda = $lomba->hasil()->where('peringkat', $p)->count();

            $this->cekKuota($lomba, fn (int $q) => $q === $p ? $sudahAda + 1 : 0);

            return $lomba->hasil()->create([
                'peringkat' => $p,
                'madrasah_id' => (int) $data['madrasah_id'],
                'pendaftaran_id' => $data['pendaftaran_id'] ?? null,
                'nama_peserta' => filled($data['nama_peserta'] ?? null) ? trim($data['nama_peserta']) : null,
                'urutan' => $sudahAda + 1,
            ]);
        });
    }

    /** Berapa juara lagi yang masih bisa ditambahkan di peringkat ini. */
    public function sisaKuota(Lomba $lomba, int $peringkat): int
    {
        return max(0, $lomba->kuota($peringkat) - $lomba->hasil()->where('peringkat', $peringkat)->count());
    }

    /**
     * Cegah kuota lomba diturunkan di bawah jumlah juara yang sudah tersimpan.
     *
     * @param  array{1?:int, 2?:int, 3?:int}  $kuotaBaru
     *
     * @throws ValidationException
     */
    public function validasiPerubahanKuota(Lomba $lomba, array $kuotaBaru): void
    {
        $jumlah = $lomba->hasil()->reorder()->toBase()
            ->selectRaw('peringkat, COUNT(*) AS n')->groupBy('peringkat')
            ->pluck('n', 'peringkat');

        $errors = [];
        foreach (Medali::cases() as $m) {
            $baru = $kuotaBaru[$m->value] ?? $lomba->kuota($m);
            $ada = (int) ($jumlah[$m->value] ?? 0);

            if ($baru < $ada) {
                $errors["kuota_juara_{$m->value}"] = "Kuota juara {$m->value} tidak boleh kurang dari {$ada} (jumlah juara yang sudah diinput).";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    // ------------------------------------------------------------------

    /**
     * Isi madrasah_id dan nama_peserta dari pendaftaran yang dipilih.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function lengkapi(Lomba $lomba, array $baris): array
    {
        $ids = collect($baris)->pluck('pendaftaran_id')->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return array_values($baris);
        }

        $dobel = collect($baris)->pluck('pendaftaran_id')->filter()->map(fn ($id) => (int) $id)->duplicates()->first();
        if ($dobel) {
            throw ValidationException::withMessages(['juara_1' => 'Satu peserta/tim tidak boleh dipilih lebih dari sekali.']);
        }

        $daftar = Pendaftaran::query()->whereKey($ids)->with('peserta')->get()->keyBy('id');

        return collect($baris)->values()->map(function (array $b, int $i) use ($lomba, $daftar) {
            if (blank($b['pendaftaran_id'] ?? null)) {
                $b['pendaftaran_id'] = null;

                return $b;
            }

            $d = $daftar->get((int) $b['pendaftaran_id']);

            if (! $d || $d->lomba_id !== $lomba->id) {
                throw ValidationException::withMessages(["juara_{$b['peringkat']}" => 'Peserta terdaftar yang dipilih bukan peserta lomba ini.']);
            }
            if ($d->status !== StatusPendaftaran::Diverifikasi) {
                throw ValidationException::withMessages(["juara_{$b['peringkat']}" => "Pendaftaran {$d->nomor} belum diverifikasi."]);
            }

            $b['pendaftaran_id'] = $d->id;
            $b['madrasah_id'] = $d->madrasah_id;
            if (blank($b['nama_peserta'] ?? null)) {
                $b['nama_peserta'] = $d->namaPeserta();
            }

            return $b;
        })->all();
    }

    /**
     * @param  callable(int): int  $jumlah  jumlah juara per peringkat setelah perubahan
     *
     * @throws ValidationException
     */
    private function cekKuota(Lomba $lomba, callable $jumlah): void
    {
        $errors = [];

        foreach (Medali::cases() as $m) {
            $n = $jumlah($m->value);
            $kuota = $lomba->kuota($m);

            if ($n > $kuota) {
                $errors["juara_{$m->value}"] = "Juara {$m->value} ({$m->label()}) maksimal {$kuota} orang untuk lomba {$lomba->nama}, tetapi diisi {$n}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function aturanBaris(string $prefix = ''): array
    {
        return [
            "{$prefix}peringkat" => ['required', 'integer', Rule::in([1, 2, 3])],
            "{$prefix}madrasah_id" => ['required', 'integer', Rule::exists('madrasah', 'id')],
            "{$prefix}nama_peserta" => ['nullable', 'string', 'max:2000'],
            "{$prefix}pendaftaran_id" => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return [
            '*.required' => 'Kolom :attribute wajib diisi.',
            'peringkat.in' => 'Peringkat harus 1, 2, atau 3.',
            'baris.*.peringkat.in' => 'Peringkat harus 1, 2, atau 3.',
            'madrasah_id.required' => 'Madrasah wajib dipilih.',
            'baris.*.madrasah_id.required' => 'Madrasah wajib dipilih.',
            'madrasah_id.exists' => 'Madrasah tidak ditemukan.',
            'baris.*.madrasah_id.exists' => 'Madrasah tidak ditemukan.',
        ];
    }
}
