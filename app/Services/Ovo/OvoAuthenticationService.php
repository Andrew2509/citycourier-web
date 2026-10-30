<?php

namespace App\Services\Ovo;

use App\Services\OvoProvider;

/**
 * OVO Authentication Service.
 * Bertanggung jawab khusus atas autentikasi B2B ke OVO SNAP:
 * generate access token via POST /OVOSNAP/v1.0/access-token/b2b.
 *
 * Token di-cache oleh provider sampai mendekati kedaluwarsa (resmi 15 menit),
 * sehingga pemanggil bebas memanggil getAccessToken() berkali-kali — token baru
 * hanya diminta saat cache kedaluwarsa atau ditolak server.
 */
class OvoAuthenticationService
{
    private OvoProvider $provider;

    public function __construct(?OvoProvider $provider = null)
    {
        $this->provider = $provider ?? (new \App\Services\OvoService())->activeProvider();
    }

    /**
     * Access token B2B (dengan cache). Gagal bila kredensial OVO belum diisi.
     *
     * @return array ['success' => bool, 'access_token' => string|null, 'expires_in' => int|null, 'error' => string|null]
     */
    public function getAccessToken(): array
    {
        return $this->provider->getB2BAccessToken();
    }
}
