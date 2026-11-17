@php
    $cssVersion = @filemtime(public_path('css/research-form.css')) ?: '1';
    $jsVersion = @filemtime(public_path('js/research-form.js')) ?: '1';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0095FC">
    <meta name="description" content="Formulir studi pendahuluan penelitian Tugas Akhir perancangan UI/UX aplikasi mobile City Courier. Untuk pengguna layanan pengiriman dan kurir.">
    <title>Studi Pendahuluan City Courier — Formulir Penelitian Tugas Akhir</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/research-form.css') }}?v={{ $cssVersion }}">
    <link rel="icon" href="{{ asset('images/logo-city-courier.png') }}">
</head>
<body>
    <a class="skip-link" href="#research-form">Lewati ke formulir</a>

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

    <main class="shell">
        <section class="masthead">
            <p class="eyebrow"><span class="dot" aria-hidden="true"></span> Studi Pendahuluan &middot; City Courier</p>
            <h1 class="page-title">Studi Pendahuluan Pengalaman dan Kebutuhan Pengguna pada Layanan Pengiriman Barang</h1>
            <p class="lede">
                Instrumen penelitian untuk merancang antarmuka aplikasi mobile City Courier berbasis Flutter
                dengan pendekatan User-Centered Design (UCD).
            </p>
            <ul class="meta">
                <li><span class="meta-dot" aria-hidden="true"></span> Sekitar 7&ndash;10 menit</li>
                <li><span class="meta-dot" aria-hidden="true"></span> 28 pertanyaan</li>
                <li><span class="meta-dot" aria-hidden="true"></span> Tanpa jawaban benar atau salah</li>
            </ul>
        </section>

        <section class="intro" aria-labelledby="intro-title">
            <div class="intro-head">
                <h2 id="intro-title">Tentang penelitian ini</h2>
                <button type="button" class="intro-toggle" id="intro-toggle" aria-expanded="true" aria-controls="intro-body">
                    Sembunyikan
                </button>
            </div>
            <div class="intro-body" id="intro-body">
                <p>Halo!</p>
                <p>
                    Saya sedang melakukan penelitian tugas akhir mengenai perancangan dan implementasi antarmuka
                    pengguna (UI/UX) pada aplikasi mobile City Courier berbasis Flutter dengan metode
                    <strong>User-Centered Design (UCD)</strong>.
                </p>
                <p>
                    Penelitian ini bertujuan untuk memahami pengalaman, kendala, serta kebutuhan pengguna dalam layanan
                    pengiriman barang, baik dari sisi Customer maupun Courier. Hasil pengumpulan data ini akan digunakan
                    sebagai dasar dalam mengidentifikasi kebutuhan pengguna dan merancang antarmuka aplikasi City Courier.
                </p>
                <p>
                    City Courier merupakan aplikasi yang masih dalam tahap awal pengembangan dan belum dioperasionalkan.
                    Oleh karena itu, pertanyaan dalam formulir ini berfokus pada pengalaman responden menggunakan layanan
                    pengiriman yang sudah tersedia.
                </p>
                <p>
                    Tidak ada jawaban benar atau salah. Silakan memberikan jawaban sesuai pengalaman pribadi.
                    Data yang dikumpulkan akan digunakan untuk keperluan penelitian tugas akhir.
                </p>
                <p>Terima kasih atas kesediaan Anda untuk berpartisipasi.</p>
            </div>
        </section>

        <section class="progress" aria-labelledby="progress-heading">
            <div class="progress-top">
                <h2 class="progress-label" id="progress-heading">Progres pengisian</h2>
                <p class="progress-count"><strong id="progress-current">1</strong> / <span id="progress-total">5</span> langkah</p>
            </div>
            <div class="progress-bar" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1" aria-labelledby="progress-heading">
                <span class="progress-fill" id="progress-fill"></span>
            </div>
            <ol class="steps-rail" id="steps-rail">
                <li data-rail="identity"><span class="rail-dot">1</span><span class="rail-label">Identitas</span></li>
                <li data-rail="customer"><span class="rail-dot">2</span><span class="rail-label">Customer</span></li>
                <li data-rail="courier"><span class="rail-dot">3</span><span class="rail-label">Courier</span></li>
                <li data-rail="consent"><span class="rail-dot">4</span><span class="rail-label">Saran</span></li>
                <li data-rail="review"><span class="rail-dot">5</span><span class="rail-label">Tinjauan</span></li>
            </ol>
        </section>

        <noscript>
            <p class="noscript">Formulir ini memerlukan JavaScript untuk berpindah antarbagian. Aktifkan JavaScript pada peramban Anda, lalu muat ulang halaman.</p>
        </noscript>

        <form
            class="research-form"
            id="research-form"
            method="POST"
            action="{{ route('research.store') }}"
            novalidate
        >
            @csrf
            <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

            <div class="hp-field" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-alert" id="form-alert" role="alert" tabindex="-1" hidden></div>

            {{-- ─── Bagian A ─────────────────────────────────────────── --}}
            <section class="step" data-step="identity" data-title="Bagian A &middot; Identitas dan Pengalaman Responden">
                <header class="step-head" tabindex="-1">
                    <p class="step-kicker">Bagian A</p>
                    <h2 class="step-title">Identitas dan pengalaman responden</h2>
                    <p class="step-desc">Sedikit informasi tentang Anda dan pengalaman menggunakan layanan pengiriman barang.</p>
                </header>

                <div class="questions">
                    <x-research.question number="1" id="q1" title="Peran Anda dalam layanan pengiriman barang?" required type="radio" name="role">
                        <x-research.radio-group name="role" :options="$options['roles']" />
                    </x-research.question>

                    <x-research.question number="2" id="q2" title="Berapa usia Anda?" required type="radio" name="age_range">
                        <x-research.radio-group name="age_range" :options="$options['ageRanges']" />
                    </x-research.question>

                    <x-research.question number="3" id="q3" title="Apakah Anda pernah menggunakan layanan pengiriman barang?" required type="radio" name="has_used_service">
                        <x-research.radio-group name="has_used_service" :options="['ya' => 'Ya', 'tidak' => 'Tidak']" />
                    </x-research.question>

                    <x-research.question
                        number="4"
                        id="q4"
                        title="Layanan pengiriman apa yang pernah Anda gunakan atau ketahui?"
                        help="Pilih semua yang sesuai."
                        type="checkbox"
                        name="services_used[]"
                    >
                        <x-research.checkbox-group
                            name="services_used[]"
                            :options="$options['services']"
                            other-name="services_other"
                            other-label="Nama layanan lainnya"
                            other-placeholder="Contoh: Anteraja, Paxel, atau layanan daerah"
                        />
                    </x-research.question>

                    <x-research.question number="5" id="q5" title="Seberapa sering Anda menggunakan layanan pengiriman barang?" required type="radio" name="usage_frequency">
                        <x-research.radio-group name="usage_frequency" :options="$options['usageFrequencies']" />
                    </x-research.question>
                </div>
            </section>

            {{-- ─── Bagian B ─────────────────────────────────────────── --}}
            <section class="step" data-step="customer" data-title="Bagian B &middot; Pertanyaan Customer" hidden>
                <header class="step-head" tabindex="-1">
                    <p class="step-kicker">Bagian B</p>
                    <h2 class="step-title">Pengalaman sebagai Customer</h2>
                    <p class="step-desc">
                        Bagian ini ditujukan kepada responden yang pernah menggunakan layanan pengiriman barang
                        sebagai Customer.
                    </p>
                </header>

                <div class="questions">
                    <x-research.question
                        number="6"
                        id="q6"
                        title="Aktivitas apa yang biasanya Anda lakukan menggunakan layanan pengiriman barang?"
                        help="Pilih semua yang sesuai."
                        type="checkbox"
                        name="q6_activities[]"
                    >
                        <x-research.checkbox-group
                            name="q6_activities[]"
                            :options="$options['customerActivities']"
                            other-name="q6_other"
                            other-label="Aktivitas lainnya"
                            other-placeholder="Tuliskan aktivitas lain yang biasa Anda lakukan"
                        />
                    </x-research.question>

                    <x-research.question number="7" id="q7" title="Apakah Anda pernah mengalami kesulitan ketika menggunakan aplikasi layanan pengiriman barang?" required type="radio" name="q7_difficulty">
                        <x-research.radio-group name="q7_difficulty" :options="$options['difficultyLevels']" />
                    </x-research.question>

                    <x-research.question
                        number="8"
                        id="q8"
                        title="Pada bagian apa Anda pernah mengalami kesulitan?"
                        help="Pilih semua yang pernah Anda alami."
                        type="checkbox"
                        name="q8_problem_areas[]"
                    >
                        <x-research.checkbox-group
                            name="q8_problem_areas[]"
                            :options="$options['customerProblemAreas']"
                            other-name="q8_other"
                            other-label="Bagian kesulitan lainnya"
                            other-placeholder="Tuliskan bagian lain yang menyulitkan Anda"
                        />
                    </x-research.question>

                    <x-research.question
                        number="9"
                        id="q9"
                        title="Seberapa mudah Anda menemukan fitur yang dibutuhkan pada aplikasi pengiriman?"
                        help="1 = sangat sulit, 5 = sangat mudah."
                        type="scale"
                        name="q9_find_features"
                    >
                        <x-research.scale name="q9_find_features" min-label="Sangat sulit" max-label="Sangat mudah" />
                    </x-research.question>

                    <x-research.question
                        number="10"
                        id="q10"
                        title="Seberapa mudah Anda memahami informasi status pengiriman yang ditampilkan aplikasi?"
                        help="1 = sangat sulit dipahami, 5 = sangat mudah dipahami."
                        type="scale"
                        name="q10_status_clarity"
                    >
                        <x-research.scale name="q10_status_clarity" min-label="Sangat sulit dipahami" max-label="Sangat mudah dipahami" />
                    </x-research.question>

                    <x-research.question
                        number="11"
                        id="q11"
                        title="Informasi apa yang menurut Anda penting ditampilkan pada aplikasi pengiriman barang?"
                        help="Pilih semua yang menurut Anda penting."
                        type="checkbox"
                        name="q11_important_info[]"
                    >
                        <x-research.checkbox-group
                            name="q11_important_info[]"
                            :options="$options['customerImportantInfo']"
                            other-name="q11_other"
                            other-label="Informasi lainnya"
                            other-placeholder="Tuliskan informasi lain yang penting bagi Anda"
                        />
                    </x-research.question>

                    <x-research.question
                        number="12"
                        id="q12"
                        title="Menurut Anda, bagian apa dari aplikasi pengiriman barang yang perlu diperbaiki?"
                        type="textarea"
                        name="q12_improvement_notes"
                    >
                        <x-research.textarea id="q12_improvement_notes" name="q12_improvement_notes" placeholder="Ceritakan bagian aplikasi yang menurut Anda paling perlu diperbaiki." :limit="$options['textLimit']" />
                    </x-research.question>

                    <x-research.question
                        number="13"
                        id="q13"
                        title="Apa kendala paling sering Anda alami ketika melakukan pengiriman atau menerima paket?"
                        type="textarea"
                        name="q13_common_obstacles"
                    >
                        <x-research.textarea id="q13_common_obstacles" name="q13_common_obstacles" placeholder="Tuliskan kendala yang paling sering Anda alami." :limit="$options['textLimit']" />
                    </x-research.question>

                    <x-research.question
                        number="14"
                        id="q14"
                        title="Fitur atau informasi apa yang Anda harapkan tersedia pada aplikasi pengiriman barang agar lebih mudah digunakan?"
                        type="textarea"
                        name="q14_expected_features"
                    >
                        <x-research.textarea id="q14_expected_features" name="q14_expected_features" placeholder="Tuliskan fitur atau informasi yang Anda harapkan." :limit="$options['textLimit']" />
                    </x-research.question>
                </div>
            </section>

            {{-- ─── Bagian C ─────────────────────────────────────────── --}}
            <section class="step" data-step="courier" data-title="Bagian C &middot; Pertanyaan Courier" hidden>
                <header class="step-head" tabindex="-1">
                    <p class="step-kicker">Bagian C</p>
                    <h2 class="step-title">Pengalaman sebagai Courier</h2>
                    <p class="step-desc">
                        Bagian ini ditujukan kepada responden yang memiliki pengalaman bekerja sebagai kurir
                        pengiriman barang.
                    </p>
                </header>

                <div class="questions">
                    <x-research.question number="15" id="q15" title="Apakah Anda pernah bekerja sebagai kurir pengiriman barang?" required type="radio" name="courier_status">
                        <x-research.radio-group name="courier_status" :options="$options['courierStatuses']" />
                    </x-research.question>

                    <div class="courier-details" data-courier-details hidden>
                        <div class="questions">
                            <x-research.question
                                number="16"
                                id="q16"
                                title="Aplikasi apa yang pernah Anda gunakan dalam pekerjaan sebagai kurir?"
                                help="Pilih semua yang sesuai."
                                type="checkbox"
                                name="q16_apps[]"
                            >
                                <x-research.checkbox-group
                                    name="q16_apps[]"
                                    :options="$options['courierApps']"
                                    other-name="q16_other"
                                    other-label="Nama aplikasi kurir lainnya"
                                    other-placeholder="Tuliskan nama aplikasi kurir lainnya"
                                    other-trigger="Aplikasi lainnya"
                                />
                            </x-research.question>

                            <x-research.question
                                number="17"
                                id="q17"
                                title="Aktivitas apa yang biasa Anda lakukan dalam proses pengiriman?"
                                help="Pilih semua yang sesuai."
                                type="checkbox"
                                name="q17_activities[]"
                            >
                                <x-research.checkbox-group
                                    name="q17_activities[]"
                                    :options="$options['courierActivities']"
                                    other-name="q17_other"
                                    other-label="Aktivitas lainnya"
                                    other-placeholder="Tuliskan aktivitas kurir lainnya"
                                />
                            </x-research.question>

                            <x-research.question number="18" id="q18" title="Apakah Anda pernah mengalami kesulitan ketika menggunakan aplikasi kurir?" required type="radio" name="q18_difficulty">
                                <x-research.radio-group name="q18_difficulty" :options="$options['difficultyLevels']" />
                            </x-research.question>

                            <x-research.question
                                number="19"
                                id="q19"
                                title="Pada bagian apa Anda pernah mengalami kesulitan ketika menggunakan aplikasi kurir?"
                                help="Pilih semua yang pernah Anda alami."
                                type="checkbox"
                                name="q19_problem_areas[]"
                            >
                                <x-research.checkbox-group
                                    name="q19_problem_areas[]"
                                    :options="$options['courierProblemAreas']"
                                    other-name="q19_other"
                                    other-label="Bagian kesulitan lainnya"
                                    other-placeholder="Tuliskan bagian lain yang menyulitkan Anda"
                                />
                            </x-research.question>

                            <x-research.question
                                number="20"
                                id="q20"
                                title="Seberapa mudah Anda memahami informasi pesanan yang ditampilkan pada aplikasi kurir?"
                                help="1 = sangat sulit, 5 = sangat mudah."
                                type="scale"
                                name="q20_order_clarity"
                            >
                                <x-research.scale name="q20_order_clarity" min-label="Sangat sulit" max-label="Sangat mudah" />
                            </x-research.question>

                            <x-research.question
                                number="21"
                                id="q21"
                                title="Seberapa mudah Anda mengikuti tahapan proses pengiriman melalui aplikasi?"
                                help="1 = sangat sulit, 5 = sangat mudah."
                                type="scale"
                                name="q21_flow_clarity"
                            >
                                <x-research.scale name="q21_flow_clarity" min-label="Sangat sulit" max-label="Sangat mudah" />
                            </x-research.question>

                            <x-research.question
                                number="22"
                                id="q22"
                                title="Informasi apa yang menurut Anda paling penting ditampilkan pada aplikasi kurir?"
                                help="Pilih semua yang menurut Anda penting."
                                type="checkbox"
                                name="q22_important_info[]"
                            >
                                <x-research.checkbox-group
                                    name="q22_important_info[]"
                                    :options="$options['courierImportantInfo']"
                                    other-name="q22_other"
                                    other-label="Informasi lainnya"
                                    other-placeholder="Tuliskan informasi lain yang penting bagi Anda"
                                />
                            </x-research.question>

                            <x-research.question
                                number="23"
                                id="q23"
                                title="Apa kendala yang paling sering Anda alami ketika menjalankan proses pengiriman barang?"
                                type="textarea"
                                name="q23_common_obstacles"
                            >
                                <x-research.textarea id="q23_common_obstacles" name="q23_common_obstacles" placeholder="Tuliskan kendala yang paling sering Anda alami saat bekerja." :limit="$options['textLimit']" />
                            </x-research.question>

                            <x-research.question
                                number="24"
                                id="q24"
                                title="Menurut Anda, bagian apa dari aplikasi kurir yang perlu diperbaiki agar pekerjaan lebih mudah dilakukan?"
                                type="textarea"
                                name="q24_improvement_notes"
                            >
                                <x-research.textarea id="q24_improvement_notes" name="q24_improvement_notes" placeholder="Ceritakan bagian aplikasi kurir yang perlu diperbaiki." :limit="$options['textLimit']" />
                            </x-research.question>

                            <x-research.question
                                number="25"
                                id="q25"
                                title="Fitur atau informasi apa yang Anda harapkan tersedia pada aplikasi kurir?"
                                type="textarea"
                                name="q25_expected_features"
                            >
                                <x-research.textarea id="q25_expected_features" name="q25_expected_features" placeholder="Tuliskan fitur atau informasi yang Anda harapkan." :limit="$options['textLimit']" />
                            </x-research.question>
                        </div>
                    </div>

                    <div class="inline-note" data-courier-skip-note hidden>
                        <span class="inline-note-icon" aria-hidden="true">i</span>
                        <p>Pertanyaan 16 sampai 25 tidak ditampilkan karena Anda belum pernah bekerja sebagai kurir. Silakan lanjut ke bagian berikutnya.</p>
                    </div>
                </div>
            </section>

            {{-- ─── Bagian D ─────────────────────────────────────────── --}}
            <section class="step" data-step="consent" data-title="Bagian D &middot; Saran dan Persetujuan" hidden>
                <header class="step-head" tabindex="-1">
                    <p class="step-kicker">Bagian D</p>
                    <h2 class="step-title">Saran dan persetujuan</h2>
                    <p class="step-desc">Bagian terakhir sebelum Anda meninjau seluruh jawaban.</p>
                </header>

                <div class="questions">
                    <x-research.question
                        number="26"
                        id="q26"
                        title="Apakah Anda bersedia apabila dihubungi kembali untuk wawancara lanjutan terkait penelitian ini?"
                        type="radio"
                        name="interview_willing"
                    >
                        <x-research.radio-group name="interview_willing" :options="$options['interviewConsent']" />
                    </x-research.question>

                    <div data-contact-question hidden>
                        <x-research.question
                            number="27"
                            id="q27"
                            title="Jika bersedia, silakan tuliskan kontak yang dapat dihubungi."
                            help="Nomor WhatsApp atau alamat email. Bersifat opsional dan hanya digunakan untuk keperluan wawancara penelitian."
                            type="text"
                            name="interview_contact"
                        >
                            <input
                                class="text-input"
                                type="text"
                                id="interview_contact"
                                name="interview_contact"
                                maxlength="120"
                                autocomplete="email"
                                placeholder="Contoh: 0812xxxxxxx atau nama@email.com"
                            >
                        </x-research.question>
                    </div>

                    <x-research.question
                        number="28"
                        id="q28"
                        title="Apakah Anda memiliki saran lain terkait aplikasi layanan pengiriman barang?"
                        help="Boleh dikosongkan."
                        type="textarea"
                        name="additional_suggestion"
                    >
                        <x-research.textarea id="additional_suggestion" name="additional_suggestion" placeholder="Tuliskan saran tambahan Anda, bila ada." :limit="$options['textLimit']" />
                    </x-research.question>
                </div>
            </section>

            {{-- ─── Tinjauan ─────────────────────────────────────────── --}}
            <section class="step" data-step="review" data-title="Tinjauan Jawaban" hidden>
                <header class="step-head" tabindex="-1">
                    <p class="step-kicker">Sebelum dikirim</p>
                    <h2 class="step-title">Tinjau jawaban Anda</h2>
                    <p class="step-desc">
                        Periksa kembali jawaban berikut. Anda masih dapat memperbaiki setiap bagian
                        sebelum formulir dikirim.
                    </p>
                </header>

                <div class="review-shell" id="review-body"></div>

                <p class="review-consent">
                    Dengan mengirim formulir ini, jawaban Anda disimpan untuk keperluan penelitian Tugas Akhir
                    dan tidak dipublikasikan secara terbuka.
                </p>
            </section>

            <div class="form-nav" id="form-nav">
                <button type="button" class="btn btn--ghost" id="btn-back">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M11.5 5 6.5 10l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Kembali</span>
                </button>
                <p class="nav-meta" id="nav-meta">Langkah 1 dari 5</p>
                <div class="nav-actions">
                    <button type="button" class="btn btn--primary" id="btn-next">
                        <span>Lanjut</span>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                        <button type="submit" class="btn btn--primary" id="btn-submit" hidden>
                        <span class="btn-spinner" aria-hidden="true"></span>
                        <span data-submit-label>Kirim formulir</span>
                    </button>
                </div>
            </div>
        </form>

        <p class="form-foot">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 4 5v4.4c0 3.4 2.5 6.5 6 7.6 3.5-1.1 6-4.2 6-7.6V5l-6-2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m7.6 9.9 1.7 1.7 3.2-3.3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Jawaban Anda hanya dapat dibaca oleh peneliti. Halaman ini bukan bagian dari operasional layanan City Courier.</span>
        </p>
    </main>

    <p class="sr-only" id="step-announcer" aria-live="polite"></p>

    <footer class="site-foot">
        <div class="site-foot-inner">
            <span>&copy; {{ date('Y') }} City Courier &middot; Pengiriman kilat dalam kota</span>
            <span>Instrumen penelitian Tugas Akhir &middot; User-Centered Design</span>
        </div>
    </footer>

    <script src="{{ asset('js/research-form.js') }}?v={{ $jsVersion }}" defer></script>
</body>
</html>
