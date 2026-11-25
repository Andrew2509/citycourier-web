<?php

namespace App\Modules\Chat\Support;

use Illuminate\Database\Eloquent\Model;

class AvatarUrlResolver
{
    public static function resolveForUser(?Model $user, bool $preferCourierPhoto = false): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($preferCourierPhoto && $user->relationLoaded('courier') && $user->courier !== null) {
            $courierPhoto = trim((string) ($user->courier->photo ?? ''));
            if ($courierPhoto !== '') {
                return self::normalizeUrl($courierPhoto);
            }
        }

        $avatar = trim((string) ($user->avatar ?? ''));
        if ($avatar !== '') {
            return self::normalizeUrl($avatar);
        }

        return null;
    }

    private static function normalizeUrl(string $rawPath): string
    {
        if (filter_var($rawPath, FILTER_VALIDATE_URL)) {
            return $rawPath;
        }

        return url('storage/' . ltrim($rawPath, '/'));
    }
}
