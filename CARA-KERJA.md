# Logo Corrector NTT — Cara Kerja Aplikasi

> **Satu gambar masuk. Satu logo diperbaiki. Seluruh desain tetap utuh.**

Logo Corrector NTT adalah aplikasi web sederhana untuk mengganti logo yang salah pada infografis atau poster dengan **logo resmi Pemerintah Provinsi Nusa Tenggara Timur**. Hanya area logo yang diubah. Semua bagian lain dari gambar (teks, warna, foto, tata letak, resolusi) tetap sama persis dengan aslinya.

---

## 1. Cara Menggunakan

Aplikasi hanya terdiri dari **satu halaman** dan **satu kartu kerja**.

| Langkah | Yang dilakukan pengguna | Yang dilakukan sistem |
|---|---|---|
| 1 | Klik **Unggah Foto** (atau seret gambar ke kartu) | Menyimpan gambar asli tanpa mengubahnya |
| 2 | Tunggu beberapa detik | Mendeteksi logo, menghapus logo lama, lalu menempel logo resmi |
| 3 | Lihat hasilnya | Menampilkan perbandingan **Sebelum / Sesudah**. Geser gambar untuk membandingkan |
| 4 | Klik **Unduh Hasil** | Mengirim file PNG hasil koreksi |

### Jika logo yang terdeteksi kurang tepat

1. Klik **Ubah Area** (di bagian bawah kartu).
2. Kotak merah muncul di atas gambar:
   - **Seret di area kosong** untuk menggambar kotak baru.
   - **Seret kotak** untuk memindahkannya.
   - **Tarik sudut kotak** untuk mengubah ukurannya.
   - **Klik kotak kuning** untuk memilih kandidat logo lain hasil deteksi.
3. Klik **Ganti Logo**. Hasil baru langsung tampil.

Jika logo sama sekali tidak terdeteksi, mode ini terbuka otomatis.

### Pengaturan (opsional)

Menu **Pengaturan** di bagian bawah kartu:

- **Hapus logo lama**
  - *Otomatis* (bawaan): sistem memilih cara terbaik berdasarkan latar di sekitar logo.
  - *Isi warna latar*: cocok untuk latar polos.
  - *Rekonstruksi latar*: cocok untuk latar bergradasi atau bertekstur.
  - *Tempel di atas saja*: logo lama tidak dihapus, logo resmi langsung ditempel di atasnya.
- **Ukuran logo**: ukuran logo resmi di dalam kotak (30–100%, bawaan 92%).

---

## 2. Alur Kerja Sistem

```
 Pengguna                 Laravel (PHP)                    Python (OpenCV + Pillow)
 ────────                 ─────────────                    ────────────────────────
 Unggah Foto ──────────►  Simpan original.(jpg|png|webp)
                          di storage/app/jobs/{id}/
                                │
 (otomatis) ───────────►  POST /gambar/{id}/deteksi ─────► detect
                                                            • cari kandidat logo
                          ◄──────────────────────────────── daftar kotak + skor
                                │
 (otomatis) ───────────►  POST /gambar/{id}/ganti ───────► replace
                                                            • hapus logo lama (dalam kotak)
                                                            • tempel logo resmi
                                                            • salin piksel luar kotak dari asli
                                                            • simpan PNG
                                                            • verifikasi piksel
                          ◄──────────────────────────────── hasil + laporan verifikasi
                                │
 Lihat & Unduh ◄────────  GET /gambar/{id}/unduh  (result.png)
```

Laravel menangani halaman, unggahan, validasi, dan penyimpanan file. Semua pemrosesan gambar dikerjakan oleh skrip Python `python/logo_corrector.py`, yang dipanggil Laravel melalui `Process`. Hasilnya dikembalikan dalam format JSON.

---

## 3. Deteksi Logo

Logo yang salah bisa berbentuk apa saja, jadi deteksi menggabungkan dua cara dan menghasilkan beberapa **kandidat** yang diurutkan berdasarkan skor.

1. **Kecocokan bentuk dan warna dengan lambang NTT**
   Logo resmi dicocokkan ke gambar berwarna dalam berbagai ukuran (*template matching* multi-skala, OpenCV `matchTemplate`), dengan beberapa titik terbaik per ukuran. Setiap titik lalu dicek susunan warnanya (merah di paruh atas, kuning/hijau di paruh bawah) agar pola acak pada foto tidak ikut terpilih. Cara ini menemukan logo NTT versi lama atau buram. Kecocokan yang kuat selalu diutamakan.

2. **Objek berbentuk logo**
   Tepi objek dicari dengan *Canny edge detection*, lalu objek dipilih jika:
   - ukurannya wajar (sekitar 0,15%–8% luas gambar),
   - bentuknya cenderung persegi (bukan baris teks yang memanjang),
   - posisinya di tempat logo biasa berada: bagian atas atau bawah, terutama di pojok.

Kandidat yang saling tumpang-tindih disaring, lalu setiap kotak diberi sedikit margin agar tepi logo lama ikut tercakup. Kandidat dengan skor tertinggi langsung dipakai. Kandidat lain tetap bisa dipilih lewat **Ubah Area**.

---

## 4. Penggantian Logo

Semua langkah di bawah hanya bekerja **di dalam kotak logo**.

### a. Menghapus logo lama

- **Isi warna latar**: sistem membaca garis tipis piksel tepat di luar kotak (hanya dibaca, tidak diubah), lalu mengisi kotak dengan warna median-nya.
- **Rekonstruksi latar**: *inpainting* OpenCV (metode Telea) mengisi kotak berdasarkan pola di sekitarnya. Yang diambil hanya hasil di dalam kotak.
- **Otomatis**: jika warna di sekitar kotak seragam, sistem memakai *isi warna latar*. Jika tidak, sistem memakai *rekonstruksi latar*.

### b. Menempel logo resmi

- Logo diambil dari file asli `storage/app/logo/logo-ntt.png`. Logo **tidak digambar ulang dan tidak dihasilkan AI**.
- Margin transparan di sekeliling logo dipangkas, lalu logo diperkecil atau diperbesar **dengan proporsi tetap** (metode Lanczos) agar muat di dalam kotak.
- Logo ditempel di tengah kotak dengan *alpha compositing*, sehingga tepi transparannya menyatu dengan latar.

---

## 5. Jaminan "Hanya Logo yang Berubah"

Ini aturan terpenting aplikasi dan dijaga dalam beberapa lapis:

1. **Salin dari asli.** Gambar hasil dibuat dari salinan gambar asli. Hanya bagian di dalam kotak yang diganti.
2. **Verifikasi di memori.** Sebelum disimpan, setiap piksel di luar kotak dibandingkan dengan aslinya.
3. **Verifikasi dari file.** Setelah disimpan, file PNG dibaca ulang dari disk dan dibandingkan lagi dengan aslinya. Dimensinya juga harus sama.
4. **Gagal = ditolak.** Jika ada **satu piksel saja** di luar kotak yang berbeda, proses dibatalkan dan pengguna menerima pesan kesalahan. Hasil yang tidak lolos verifikasi tidak pernah diberikan.

Label *"Logo diganti · desain lain tidak berubah"* hanya muncul setelah verifikasi lulus.

### Mengapa hasilnya selalu PNG?

Menyimpan ulang gambar sebagai JPG berarti **mengompresi ulang seluruh gambar**, sehingga piksel di luar logo ikut berubah. PNG bersifat *lossless*, jadi piksel yang disalin tetap identik. Dimensi, resolusi, profil warna (ICC), DPI, dan data EXIF dari gambar asli tetap dipertahankan.

---

## 6. Struktur File Penting

| File | Fungsi |
|---|---|
| `resources/views/logo-corrector.blade.php` | Halaman tunggal: tampilan (Bootstrap 5) dan interaksi (JavaScript) |
| `app/Http/Controllers/LogoCorrectorController.php` | Unggah, deteksi, ganti, tampilkan, dan unduh gambar |
| `app/Services/LogoProcessor.php` | Penghubung Laravel ↔ Python, pengelolaan folder kerja |
| `python/logo_corrector.py` | Mesin deteksi, penggantian, dan verifikasi piksel |
| `config/logocorrector.php` | Lokasi Python, logo resmi, batas unggahan, masa simpan file |
| `storage/app/logo/logo-ntt.png` | **Logo resmi Pemprov NTT** (PNG transparan) |
| `storage/app/jobs/{id}/` | File kerja per gambar: `original.*`, `result.png`, `name.txt` |
| `tests/Feature/LogoCorrectorTest.php` | Pengujian otomatis, termasuk cek piksel independen dengan GD |

### Rute

| Metode | Alamat | Kegunaan |
|---|---|---|
| GET | `/` | Halaman utama |
| GET | `/logo-resmi` | Menampilkan logo resmi |
| POST | `/gambar` | Unggah gambar |
| POST | `/gambar/{id}/deteksi` | Deteksi kandidat logo |
| POST | `/gambar/{id}/ganti` | Ganti logo pada area yang dipilih |
| GET | `/gambar/{id}/original` · `/result` | Menampilkan gambar asli / hasil |
| GET | `/gambar/{id}/unduh` | Unduh hasil (PNG) |

---

## 7. Instalasi & Menjalankan

**Persyaratan:** PHP 8.3+ (ekstensi `gd`, `fileinfo`), Composer, Python 3.10+.

```bash
composer install
cp .env.example .env && php artisan key:generate
python3 -m venv python/.venv
python/.venv/bin/pip install -r python/requirements.txt
php artisan serve
```

Buka **http://localhost:8000**.

**Pengaturan `.env` (opsional):**

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `LOGO_PYTHON` | `python/.venv/bin/python` | Lokasi interpreter Python |
| `LOGO_MAX_UPLOAD_KB` | `12288` (12 MB) | Ukuran unggahan maksimum. Sesuaikan juga `upload_max_filesize` di `php.ini` |
| `LOGO_RETENTION_HOURS` | `24` | File kerja dihapus otomatis setelah sekian jam |

**Mengganti logo resmi:** timpa file `storage/app/logo/logo-ntt.png` dengan PNG transparan yang baru.

**Menjalankan pengujian:**

```bash
php artisan test
```

---

## 8. Batasan

- **Deteksi otomatis adalah perkiraan.** Pada poster yang ramai atau berisi banyak logo, kandidat pertama bisa salah. Pakai **Ubah Area** untuk memastikan.
- **Latar yang rumit.** Pada latar berupa foto atau motif detail, hasil rekonstruksi di sela-sela logo baru bisa sedikit buram. Perubahan ini tetap hanya di dalam kotak.
- **Format hasil selalu PNG**, sehingga ukuran file bisa lebih besar daripada JPG aslinya.
- **Gambar CMYK** dikonversi ke RGB sebelum diproses. Sistem menampilkan peringatan jika ini terjadi.

---

<sub>IT-BPAD · [ihsanmokhsen.com](https://ihsanmokhsen.com)</sub>
