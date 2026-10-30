<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/health — penanda versi deploy.
 *
 * Endpoint publik dan sengaja RINGAN: tidak menyentuh database, tidak
 * memanggil layanan luar, dan tidak mengembalikan kredensial apa pun.
 * Gunanya untuk memastikan server produksi benar-benar sudah menjalankan
 * commit terbaru setelah `git pull` (bug sering kali masih ada karena kode
 * lama masih terpasang, bukan karena kodenya salah).
 *
 * Sumber commit, berurutan:
 * 1. variabel lingkungan APP_COMMIT (paling andal di hosting tanpa akses git),
 * 2. file penanda `storage/app/deploy-commit` (tulis saat deploy),
 * 3. perintah `git log -1` bila folder .git tersedia dan exec diizinkan.
 */
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app'    => config('app.name'),
            'env'    => config('app.env'),
            'php'    => PHP_VERSION,
            'time'   => now()->toIso8601String(),
            'deploy' => $this->deployInfo(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{commit:?string, subject:?string, committed_at:?string, source:string}
     */
    private function deployInfo(): array
    {
        $fromEnv = trim((string) config('app.commit', ''));
        if ($fromEnv !== '') {
            return $this->shape($fromEnv, null, null, 'env');
        }

        $marker = trim($this->readMarker());
        if ($marker !== '') {
            return $this->shape($marker, null, null, 'file');
        }

        // Sengaja tanpa cache: CACHE_STORE produksi memakai database, dan
        // endpoint ini harus tetap menjawab walau database sedang mati.
        return $this->fromGit();
    }

    /** Isi `storage/app/deploy-commit` (boleh satu baris hash saja). */
    private function readMarker(): string
    {
        $path = storage_path('app/deploy-commit');
        if (!is_file($path)) {
            return '';
        }

        return (string) @file_get_contents($path);
    }

    /**
     * @return array{commit:?string, subject:?string, committed_at:?string, source:string}
     */
    private function fromGit(): array
    {
        if (!function_exists('exec') || !is_dir(base_path('.git'))) {
            return $this->shape(null, null, null, 'unknown');
        }

        $command = 'git -C ' . escapeshellarg(base_path()) . ' log -1 --pretty=%h%x1f%s%x1f%cI';
        // `2>/dev/null` bukan sintaks shell Windows.
        if (DIRECTORY_SEPARATOR !== '\\') {
            $command .= ' 2>/dev/null';
        }

        $output = [];
        $exit   = 0;
        @exec($command, $output, $exit);

        if ($exit !== 0 || empty($output[0])) {
            return $this->shape(null, null, null, 'unknown');
        }

        $parts = explode("\x1f", (string) $output[0]);

        return $this->shape(
            $parts[0] ?? null,
            $parts[1] ?? null,
            $parts[2] ?? null,
            'git'
        );
    }

    /**
     * @return array{commit:?string, subject:?string, committed_at:?string, source:string}
     */
    private function shape(?string $commit, ?string $subject, ?string $committedAt, string $source): array
    {
        $commit = $commit !== null ? trim($commit) : null;

        return [
            'commit'       => ($commit === null || $commit === '') ? null : $commit,
            'subject'      => $subject,
            'committed_at' => $committedAt,
            'source'       => $source,
        ];
    }
}
