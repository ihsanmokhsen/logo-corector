<?php

namespace App\Http\Controllers;

use App\Services\LogoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogoCorrectorController extends Controller
{
    public function __construct(private LogoProcessor $processor) {}

    public function index()
    {
        return view('logo-corrector', [
            'maxUploadKb' => config('logocorrector.max_upload_kb'),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('logocorrector.max_upload_kb')],
        ], [
            'image.required' => 'Pilih gambar terlebih dahulu.',
            'image.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar terlalu besar.',
            'image.uploaded' => 'Gambar gagal diunggah. Ukurannya mungkin melebihi batas server.',
        ]);

        $this->processor->prune();

        $file = $request->file('image');
        $id = (string) Str::uuid();
        $dir = $this->processor->jobPath($id);
        File::ensureDirectoryExists($dir);

        // Simpan byte asli tanpa diubah sedikit pun.
        $file->move($dir, 'original.'.strtolower($file->guessExtension() ?: $file->getClientOriginalExtension()));
        File::put($dir.'/name.txt', $file->getClientOriginalName());

        [$width, $height] = getimagesize($this->processor->originalPath($id)) ?: [0, 0];

        return response()->json([
            'id' => $id,
            'name' => $file->getClientOriginalName(),
            'width' => $width,
            'height' => $height,
            'url' => route('file', ['id' => $id, 'type' => 'original']),
        ]);
    }

    public function detect(string $id): JsonResponse
    {
        return $this->handle(function () use ($id) {
            $original = $this->processor->originalPath($id) ?? abort(404, 'Gambar tidak ditemukan.');

            return $this->processor->detect($original);
        });
    }

    public function replace(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'w' => ['required', 'integer', 'min:4'],
            'h' => ['required', 'integer', 'min:4'],
            'removal' => ['required', 'in:auto,fill,inpaint,none'],
            'scale' => ['required', 'numeric', 'between:0.3,1'],
            'align' => ['required', 'in:center,left,right'],
        ], [
            'w.min' => 'Area logo terlalu kecil.',
            'h.min' => 'Area logo terlalu kecil.',
        ]);

        return $this->handle(function () use ($id, $data) {
            $this->processor->originalPath($id) ?? abort(404, 'Gambar tidak ditemukan.');

            $result = $this->processor->replace(
                $id,
                [$data['x'], $data['y'], $data['w'], $data['h']],
                $data['removal'],
                (float) $data['scale'],
                $data['align'],
            );

            return $result + [
                'url' => route('file', ['id' => $id, 'type' => 'result']).'?v='.now()->getTimestampMs(),
                'download' => route('download', ['id' => $id]),
            ];
        });
    }

    public function file(string $id, string $type): BinaryFileResponse
    {
        $path = $this->resolve($id, $type);

        return response()->file($path, ['Cache-Control' => 'no-store']);
    }

    public function download(string $id): BinaryFileResponse
    {
        $path = $this->resolve($id, 'result');
        $name = File::exists($this->processor->jobPath($id, 'name.txt'))
            ? pathinfo(File::get($this->processor->jobPath($id, 'name.txt')), PATHINFO_FILENAME)
            : 'gambar';

        return response()->download($path, Str::slug($name).'-logo-ntt.png', ['Content-Type' => 'image/png']);
    }

    private function resolve(string $id, string $type): string
    {
        abort_unless(Str::isUuid($id), 404);

        $path = $type === 'result'
            ? $this->processor->resultPath($id)
            : $this->processor->originalPath($id);

        abort_unless($path && File::exists($path), 404);

        return $path;
    }

    private function handle(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
