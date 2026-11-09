<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\Pendaftaran;
use App\Models\PendaftaranPeserta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat membuat, memverifikasi, dan menolak pendaftaran.
 * Dipakai form publik /daftar, panel admin, dan seeder.
 */
class PendaftaranService
{
    /**
     * Simpan pendaftaran baru. Data dasar (format NISN, WA, file) sudah divalidasi Form Request;
     * di sini aturan yang butuh database: masa pendaftaran, jenjang, jumlah anggota, duplikat.
     *
     * @param  array{lomba_id:int, madrasah_id:int, guru_nama:string, guru_wa:string, peserta:list<array{nama:string, nisn:string}>}  $data
     *
     * @throws ValidationException
     */
    public function daftar(Edisi $edisi, array $data, ?UploadedFile $surat = null, bool $abaikanMasa = false): Pendaftaran
    {
        if (! $abaikanMasa && ! $edisi->pendaftaranDibuka()) {
            throw ValidationException::withMessages(['lomba_id' => "Pendaftaran {$edisi->nama} sedang tidak dibuka."]);
        }

        $lomba = Lomba::query()->with('cabor')->where('edisi_id', $edisi->id)->find($data['lomba_id']);
        $madrasah = Madrasah::find($data['madrasah_id']);

        if (! $madrasah) {
            throw ValidationException::withMessages(['madrasah_id' => 'Madrasah tidak ditemukan.']);
        }
        if (! $lomba) {
            throw ValidationException::withMessages(['lomba_id' => "Lomba tidak tersedia di {$edisi->nama}."]);
        }
        if ($lomba->jenjang !== $madrasah->jenjang) {
            throw ValidationException::withMessages(['lomba_id' => "Lomba {$lomba->namaLengkap()} bukan untuk jenjang {$madrasah->jenjang->value}."]);
        }

        $peserta = array_values($data['peserta']);
        if (count($peserta) !== $lomba->jumlah_anggota) {
            throw ValidationException::withMessages(['peserta' => $lomba->tim()
                ? "Lomba ini tim beranggotakan tepat {$lomba->jumlah_anggota} orang."
                : 'Lomba ini perorangan, isi satu peserta.']);
        }

        $gtk = $lomba->cabor?->slug === 'gtk';
        foreach ($peserta as $p) {
            if (! preg_match($gtk ? '/^\d{16}$/' : '/^\d{10}$/', (string) $p['nisn'])) {
                throw ValidationException::withMessages(['peserta' => $gtk ? 'NUPTK harus 16 digit angka.' : 'NISN harus 10 digit angka.']);
            }
        }

        $nisn = array_column($peserta, 'nisn');
        if (count($nisn) !== count(array_unique($nisn))) {
            throw ValidationException::withMessages(['peserta' => 'NISN/NUPTK tidak boleh sama antar-anggota tim.']);
        }

        return DB::transaction(function () use ($edisi, $lomba, $madrasah, $data, $peserta, $nisn, $surat) {
            // Kunci lomba agar dua kiriman bersamaan tidak lolos cek duplikat / nomor.
            Lomba::query()->whereKey($lomba->id)->lockForUpdate()->first();

            if (Pendaftaran::query()->where('lomba_id', $lomba->id)->where('madrasah_id', $madrasah->id)->exists()) {
                throw ValidationException::withMessages(['lomba_id' => "{$madrasah->nama} sudah terdaftar di lomba {$lomba->namaLengkap()}."]);
            }

            $dobel = PendaftaranPeserta::query()
                ->whereIn('nisn', $nisn)
                ->whereHas('pendaftaran', fn ($q) => $q->where('lomba_id', $lomba->id)->where('status', '!=', StatusPendaftaran::Ditolak->value))
                ->value('nisn');
            if ($dobel) {
                throw ValidationException::withMessages(['peserta' => "NISN/NUPTK {$dobel} sudah terdaftar di lomba ini."]);
            }

            $pendaftaran = Pendaftaran::create([
                'edisi_id' => $edisi->id,
                'lomba_id' => $lomba->id,
                'madrasah_id' => $madrasah->id,
                'nomor' => $this->nomorBaru($edisi),
                'guru_nama' => trim($data['guru_nama']),
                'guru_wa' => $this->rapikanWa($data['guru_wa']),
                'surat_tugas_path' => $surat?->store("surat-tugas/{$edisi->tahun}", Pendaftaran::DISK),
            ]);

            foreach ($peserta as $i => $p) {
                $pendaftaran->peserta()->create([
                    'nama' => trim($p['nama']),
                    'nisn' => $p['nisn'],
                    'urutan' => $i + 1,
                ]);
            }

            return $pendaftaran->load(['lomba.cabor', 'madrasah', 'peserta']);
        });
    }

    public function verifikasi(Pendaftaran $pendaftaran, ?string $catatan = null): Pendaftaran
    {
        $pendaftaran->update([
            'status' => StatusPendaftaran::Diverifikasi,
            'catatan' => $catatan,
            'diverifikasi_pada' => now(),
        ]);

        return $pendaftaran;
    }

    /** @throws ValidationException */
    public function tolak(Pendaftaran $pendaftaran, string $catatan): Pendaftaran
    {
        if ($pendaftaran->hasil()->exists()) {
            throw ValidationException::withMessages(['catatan' => 'Pendaftaran ini sudah tercatat sebagai juara. Hapus dulu dari hasil lomba.']);
        }

        $pendaftaran->update([
            'status' => StatusPendaftaran::Ditolak,
            'catatan' => $catatan,
            'diverifikasi_pada' => null,
        ]);

        return $pendaftaran;
    }

    /** Nomor registrasi berurutan per edisi: JMC26-0001, JMC26-0002, ... */
    public function nomorBaru(Edisi $edisi): string
    {
        $awalan = 'JMC'.substr((string) $edisi->tahun, -2).'-';

        $terakhir = Pendaftaran::query()
            ->where('nomor', 'like', $awalan.'%')
            ->lockForUpdate()
            ->max('nomor');

        $urut = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    /** "0812-3456 7890" → "081234567890"; "+62812…" → "0812…". */
    public function rapikanWa(string $wa): string
    {
        $wa = preg_replace('/[^\d+]/', '', $wa);

        return preg_replace('/^(\+62|62)/', '0', $wa);
    }
}
