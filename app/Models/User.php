<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'address', 'password', 'role', 'firebase_uid', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Apakah akun ini benar-benar terdaftar melalui Google?
     *
     * `firebase_uid` saja tidak cukup: akun lama hasil auto-register OTP juga
     * pernah menyimpan UID Firebase. Karena itu email hasil generate
     * (`<phone>@citycourier.local`, `pelanggan<phone>@...`) tidak dianggap
     * sebagai bukti akun Google. Nomor telepon yang hanya diisi di profil
     * tanpa autentikasi Google juga tidak lolos pengecekan ini.
     */
    public function hasGoogleAuth(): bool
    {
        if (trim((string) $this->getAttribute('firebase_uid')) === '') {
            return false;
        }

        $email = strtolower(trim((string) $this->getAttribute('email')));
        if ($email === '' || !str_contains($email, '@')) {
            return false;
        }

        [$localPart, $domain] = explode('@', $email, 2);

        if ($domain === 'citycourier.local') {
            return false;
        }

        // Email yang dibuat sistem untuk akun lama hasil login OTP:
        // pelanggan<phone>@<domain>.
        if (preg_match('/^pelanggan\.?\d{8,}$/', $localPart) === 1) {
            return false;
        }

        return true;
    }

    /**
     * Get the full URL for the user's avatar.
     */
    public function getPhotoUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        return url('storage/' . $this->avatar);
    }

    /**
     * Get the courier profile associated with this user.
     */
    public function courier()
    {
        return $this->hasOne(Courier::class);
    }
}
