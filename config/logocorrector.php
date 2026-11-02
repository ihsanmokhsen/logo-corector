<?php

return [

    // Interpreter Python yang memiliki OpenCV, Pillow, dan NumPy (lihat python/requirements.txt).
    'python' => env('LOGO_PYTHON', base_path('python/.venv/bin/python')),

    'script' => base_path('python/logo_corrector.py'),

    // File logo resmi Pemprov NTT yang ditempel apa adanya (PNG dengan transparansi).
    'logo' => storage_path('app/logo/logo-ntt.png'),

    // Lokasi penyimpanan sementara gambar unggahan dan hasil.
    'jobs_path' => storage_path('app/jobs'),

    // Ukuran unggahan maksimum dalam kilobyte.
    'max_upload_kb' => (int) env('LOGO_MAX_UPLOAD_KB', 12288),

    // Berkas kerja dihapus setelah sekian jam.
    'retention_hours' => (int) env('LOGO_RETENTION_HOURS', 24),

];
