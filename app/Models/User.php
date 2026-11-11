<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna panel admin. Role: superadmin, admin cabor (terikat satu cabor), admin berita.
 * Aturan akses ada di sini (boleh...), dipakai resource & halaman Filament.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'cabor_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'superadmin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'cabor_id' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Admin cabor tanpa cabor belum bisa mengerjakan apa pun.
        return $this->role !== Role::AdminCabor || $this->cabor_id !== null;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    public function isAdminCabor(): bool
    {
        return $this->role === Role::AdminCabor;
    }

    public function isAdminBerita(): bool
    {
        return $this->role === Role::AdminBerita;
    }

    /** Master data, kelola user, pengaturan/hapus lomba. */
    public function bolehKelolaMaster(): bool
    {
        return $this->isSuperadmin();
    }

    /** Berita dan galeri. */
    public function bolehKelolaBerita(): bool
    {
        return $this->isSuperadmin() || $this->isAdminBerita();
    }

    /** Akses menu cabor / pendaftaran / input juara secara umum. */
    public function bolehKelolaLombaApaPun(): bool
    {
        return $this->isSuperadmin() || ($this->isAdminCabor() && $this->cabor_id !== null);
    }

    /** Halaman kerja satu cabor (lomba, juara, peserta). */
    public function bolehKelolaCabor(Cabor|int $cabor): bool
    {
        $id = $cabor instanceof Cabor ? $cabor->id : $cabor;

        return $this->isSuperadmin() || ($this->isAdminCabor() && $this->cabor_id === $id);
    }

    /** Lomba milik cabornya, atau lomba GTK yang dikelola cabornya. */
    public function bolehKelolaLomba(Lomba $lomba): bool
    {
        return $this->isSuperadmin()
            || ($this->isAdminCabor() && $this->cabor_id !== null
                && in_array($this->cabor_id, [$lomba->cabor_id, $lomba->pengelola_cabor_id], true));
    }

    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }
}
