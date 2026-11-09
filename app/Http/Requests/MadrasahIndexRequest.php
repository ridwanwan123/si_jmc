<?php

namespace App\Http\Requests;

use App\Enums\Jenjang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Parameter halaman /madrasah: ?q=nama&jenjang=MI. Nilai tidak valid diabaikan.
 */
class MadrasahIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $q = $this->query('q');
        $jenjang = $this->query('jenjang');

        $this->merge([
            'q' => is_string($q) && trim($q) !== '' ? mb_substr(trim($q), 0, 100) : null,
            'jenjang' => is_string($jenjang) && Jenjang::tryFrom($jenjang) ? $jenjang : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string'],
        ];
    }

    public function kata(): string
    {
        return (string) $this->validated('q');
    }

    public function jenjang(): ?Jenjang
    {
        return Jenjang::tryFrom((string) $this->validated('jenjang'));
    }
}
