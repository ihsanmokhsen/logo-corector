<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class LogoProcessor
{
    public function jobPath(string $id, string $file = ''): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('ID pekerjaan tidak valid.');
        }

        return rtrim(config('logocorrector.jobs_path').'/'.$id.'/'.$file, '/');
    }

    /** Lokasi gambar asli untuk suatu pekerjaan, atau null jika tidak ada. */
    public function originalPath(string $id): ?string
    {
        $matches = File::glob($this->jobPath($id).'/original.*');

        return $matches[0] ?? null;
    }

    public function resultPath(string $id): string
    {
        return $this->jobPath($id, 'result.png');
    }

    public function detect(string $imagePath): array
    {
        return $this->run(['detect', $imagePath, config('logocorrector.logo')]);
    }

    /**
     * @param  array{0:int,1:int,2:int,3:int}  $box
     */
    public function replace(string $id, array $box, string $removal, float $scale, string $align): array
    {
        $original = $this->originalPath($id) ?? throw new RuntimeException('Gambar asli tidak ditemukan.');

        return $this->run([
            'replace', $original, config('logocorrector.logo'), $this->resultPath($id),
            '--box', implode(',', $box),
            '--removal', $removal,
            '--scale', (string) $scale,
            '--align', $align,
        ]);
    }

    /** Hapus folder pekerjaan yang lebih tua dari masa simpan. */
    public function prune(): void
    {
        $root = config('logocorrector.jobs_path');
        if (! File::isDirectory($root)) {
            return;
        }

        $cutoff = now()->subHours(config('logocorrector.retention_hours'))->getTimestamp();
        foreach (File::directories($root) as $dir) {
            if (File::lastModified($dir) < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function run(array $args): array
    {
        $result = Process::timeout(120)->run([
            config('logocorrector.python'),
            config('logocorrector.script'),
            ...$args,
        ]);

        $data = json_decode(trim($result->output()), true);

        if (! is_array($data)) {
            report(new RuntimeException('Logo processor error: '.$result->errorOutput()));
            throw new RuntimeException('Mesin pemrosesan gambar tidak dapat dijalankan. Periksa instalasi Python/OpenCV.');
        }

        if (! ($data['ok'] ?? false)) {
            throw new RuntimeException($data['error'] ?? 'Pemrosesan gambar gagal.');
        }

        return $data;
    }
}
