<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogoCorrectorTest extends TestCase
{
    private string $jobs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobs = storage_path('framework/testing/jobs');
        config(['logocorrector.jobs_path' => $this->jobs]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->jobs);
        parent::tearDown();
    }

    public function test_halaman_utama_tampil(): void
    {
        $this->get('/')->assertOk()->assertSee('Deteksi Logo')->assertSee('Ganti Logo');
    }

    public function test_hanya_area_logo_yang_berubah(): void
    {
        // Poster uji: latar gradasi dengan "logo salah" merah di pojok kiri atas.
        $img = imagecreatetruecolor(600, 400);
        for ($y = 0; $y < 400; $y++) {
            imageline($img, 0, $y, 599, $y, imagecolorallocate($img, 20, 60 + intdiv($y, 4), 160));
        }
        imagefilledellipse($img, 60, 60, 80, 80, imagecolorallocate($img, 220, 30, 30));
        imagestring($img, 5, 150, 50, 'INFOGRAFIS NTT 2026', imagecolorallocate($img, 255, 255, 255));
        $source = tempnam(sys_get_temp_dir(), 'poster').'.png';
        imagepng($img, $source);

        $upload = $this->post('/gambar', [
            'image' => new UploadedFile($source, 'poster.png', 'image/png', null, true),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['width' => 600, 'height' => 400]);
        $id = $upload->json('id');

        $this->postJson("/gambar/{$id}/deteksi")->assertOk()->assertJsonStructure(['candidates']);

        $box = ['x' => 15, 'y' => 15, 'w' => 90, 'h' => 90];
        $this->postJson("/gambar/{$id}/ganti", $box + ['removal' => 'auto', 'scale' => 0.9, 'align' => 'center'])
            ->assertOk()
            ->assertJsonPath('verification.outside_pixels_changed', 0)
            ->assertJsonPath('width', 600)
            ->assertJsonPath('height', 400);

        // Verifikasi independen dengan GD: setiap piksel di luar kotak identik.
        $before = imagecreatefrompng("{$this->jobs}/{$id}/original.png");
        $after = imagecreatefrompng("{$this->jobs}/{$id}/result.png");
        $this->assertSame([600, 400], [imagesx($after), imagesy($after)]);
        $changedOutside = 0;
        $changedInside = 0;
        for ($y = 0; $y < 400; $y++) {
            for ($x = 0; $x < 600; $x++) {
                $inside = $x >= 15 && $x < 105 && $y >= 15 && $y < 105;
                if (imagecolorat($before, $x, $y) !== imagecolorat($after, $x, $y)) {
                    $inside ? $changedInside++ : $changedOutside++;
                }
            }
        }
        $this->assertSame(0, $changedOutside);
        $this->assertGreaterThan(0, $changedInside);

        $this->get("/gambar/{$id}/unduh")->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_menolak_berkas_bukan_gambar(): void
    {
        $this->post('/gambar', ['image' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
