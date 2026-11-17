@php
    $cssVersion = @filemtime(public_path('css/research-form.css')) ?: '1';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0095FC">
    <meta name="robots" content="noindex">
    <title>Terima kasih — Studi Pendahuluan City Courier</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/research-form.css') }}?v={{ $cssVersion }}">
    <link rel="icon" href="{{ asset('images/logo-city-courier.png') }}">
</head>
<body class="thanks-page">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ url('/') }}">
                <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier">
                <span class="brand-sep" aria-hidden="true"></span>
                <span class="brand-label">Formulir Penelitian</span>
            </a>
            <span class="topbar-chip">
                <span class="chip-dot" aria-hidden="true"></span>
                Tugas Akhir &middot; UCD
            </span>
        </div>
    </header>

    <main class="thanks-wrap">
        <section class="thanks-card" aria-labelledby="thanks-title">
            <div class="thanks-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="#0095FC" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12.5l4.5 4.5L19 6.5"></path>
                </svg>
            </div>

            <p class="thanks-eyebrow">Jawaban tersimpan</p>
            <h1 class="thanks-title" id="thanks-title">Terima kasih atas partisipasi Anda</h1>

            <p class="thanks-text">
                Jawaban Anda sudah kami terima dan akan menjadi bahan penting dalam merancang antarmuka
                aplikasi City Courier yang lebih sesuai dengan kebutuhan pengguna.
            </p>
            <p class="thanks-text">
                Kesediaan Anda meluangkan waktu untuk mengisi formulir ini sangat berarti bagi penelitian tugas akhir ini.
            </p>

            <p class="thanks-ref">
                Kode referensi
                <strong>{{ $reference }}</strong>
            </p>

            <div class="thanks-actions">
                <a class="btn btn--primary" href="{{ url('/') }}">Kembali ke beranda</a>
            </div>

            <p class="thanks-note">
                Simpan kode referensi di atas bila Anda perlu menghubungi peneliti terkait data yang telah dikirim.
            </p>
        </section>
    </main>

    <footer class="site-foot">
        <div class="site-foot-inner">
            <span>&copy; {{ date('Y') }} City Courier &middot; Penelitian Tugas Akhir</span>
            <span>Data hanya digunakan untuk keperluan penelitian.</span>
        </div>
    </footer>
</body>
</html>
