<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi format kiriman form /daftar. Aturan yang butuh database
 * (masa pendaftaran, jenjang, jumlah anggota, duplikat) ada di PendaftaranService.
 */
class DaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'madrasah_id' => ['required', 'integer'],
            'lomba_id' => ['required', 'integer'],
            'peserta' => ['required', 'array', 'min:1', 'max:30'],
            'peserta.*.nama' => ['required', 'string', 'min:3', 'max:100'],
            // NISN 10 digit; NUPTK 16 digit untuk cabor GTK (dicek per lomba di PendaftaranService).
            'peserta.*.nisn' => ['required', 'regex:/^(\d{10}|\d{16})$/'],
            'guru_nama' => ['required', 'string', 'min:3', 'max:100'],
            'guru_wa' => ['required', 'string', 'regex:/^(\+62|62|0)8[\d\s-]{7,15}$/'],
            'surat_tugas' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'setuju' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'madrasah_id.required' => 'Pilih madrasah terlebih dahulu.',
            'lomba_id.required' => 'Pilih mata lomba terlebih dahulu.',
            'peserta.*.nama.required' => 'Isi nama lengkap semua peserta.',
            'peserta.*.nama.min' => 'Nama peserta minimal 3 huruf.',
            'peserta.*.nisn.required' => 'Isi NISN semua peserta.',
            'peserta.*.nisn.regex' => 'NISN harus 10 digit angka (NUPTK 16 digit untuk GTK).',
            'guru_nama.required' => 'Isi nama guru pendamping.',
            'guru_wa.required' => 'Isi nomor WhatsApp guru pendamping.',
            'guru_wa.regex' => 'Nomor WhatsApp tidak valid (contoh 081234567890).',
            'surat_tugas.required' => 'Unggah surat tugas dari madrasah (PDF).',
            'surat_tugas.mimes' => 'Surat tugas harus berupa file PDF.',
            'surat_tugas.max' => 'Ukuran surat tugas maksimal 2 MB.',
            'setuju.accepted' => 'Centang pernyataan terlebih dahulu.',
        ];
    }
}
