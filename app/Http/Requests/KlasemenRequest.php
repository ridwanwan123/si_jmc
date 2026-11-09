<?php

namespace App\Http\Requests;

use App\Enums\Jenjang;
use App\Models\Cabor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parameter halaman /klasemen: ?tab=klasemen|peraih|cari&jenjang=MI&cabor=sains&q=...
 * Nilai tidak valid diabaikan (diganti default), bukan memunculkan error.
 */
class KlasemenRequest extends FormRequest
{
    public const TAB = ['klasemen', 'peraih', 'cari'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bersih = [];

        foreach (['tab', 'jenjang', 'cabor', 'q'] as $k) {
            $v = $this->query($k);
            $bersih[$k] = is_string($v) && trim($v) !== '' ? trim($v) : null;
        }

        if (! in_array($bersih['tab'], self::TAB, true)) {
            $bersih['tab'] = 'klasemen';
        }

        if ($bersih['jenjang'] !== null && ! Jenjang::tryFrom($bersih['jenjang'])) {
            $bersih['jenjang'] = null;
        }

        $this->merge($bersih);
    }

    public function rules(): array
    {
        return [
            'tab' => ['required', Rule::in(self::TAB)],
            'jenjang' => ['nullable', Rule::enum(Jenjang::class)],
            'cabor' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab');
    }

    public function jenjang(): ?Jenjang
    {
        return Jenjang::tryFrom((string) $this->validated('jenjang'));
    }

    public function cabor(): ?Cabor
    {
        $slug = $this->validated('cabor');

        return $slug ? Cabor::where('slug', $slug)->first() : null;
    }

    public function kata(): string
    {
        return (string) $this->validated('q');
    }
}
