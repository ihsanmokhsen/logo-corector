# Logo Corrector NTT

Mengganti logo yang salah pada infografis/poster dengan logo resmi Pemerintah Provinsi Nusa Tenggara Timur.
**Hanya piksel di dalam area logo yang berubah** — setiap hasil diverifikasi piksel demi piksel terhadap gambar asli.

## Persyaratan
- PHP 8.3+ dengan ekstensi `gd` dan `fileinfo`; Composer
- Python 3.10+ dengan OpenCV, Pillow, NumPy

## Instalasi
```bash
composer install
cp .env.example .env && php artisan key:generate
python3 -m venv python/.venv
python/.venv/bin/pip install -r python/requirements.txt
php artisan serve
```
Buka http://localhost:8000.

Logo resmi ada di `storage/app/logo/logo-ntt.png` (PNG transparan). Ganti berkas ini untuk memperbarui logo.
Lokasi interpreter Python dapat diubah lewat `LOGO_PYTHON` di `.env`.

## Cara kerja
1. Unggah gambar (JPG/PNG/WEBP). Berkas asli disimpan tanpa perubahan.
2. **Deteksi Logo** mencari kandidat (template matching multi-skala terhadap lambang NTT + analisis kontur objek berbentuk logo). Area juga bisa digambar dan diatur manual.
3. **Ganti Logo**: logo lama dihapus hanya di dalam kotak (isi warna latar sekitar atau inpainting lokal OpenCV), lalu logo resmi ditempel dengan alpha compositing; bentuk, warna, dan proporsi asli dipertahankan.
4. Seluruh piksel di luar kotak disalin dari gambar asli, lalu berkas hasil dibaca ulang dan dibandingkan dengan gambar asli. Jika satu piksel saja berubah di luar kotak, proses ditolak.
5. Hasil diunduh sebagai PNG lossless dengan dimensi yang sama (profil ICC, DPI, dan EXIF dipertahankan).

> Gambar JPG disimpan sebagai PNG karena menyimpan ulang ke JPG akan mengompresi ulang seluruh gambar dan mengubah piksel di luar area logo.

Berkas kerja di `storage/app/jobs` dihapus otomatis setelah 24 jam (`LOGO_RETENTION_HOURS`).

## Pengujian
```bash
php artisan test
```
