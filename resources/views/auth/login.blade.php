<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Admin - CityCourier</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: {
                        50: '#fff7ed',
                        100: '#ffedd5',
                        200: '#fed7aa',
                        500: '#f97316',
                        600: '#ec5b13',
                        700: '#c2410c',
                        800: '#9a3412',
                        900: '#7c2d12',
                    },
                    navy: {
                        800: '#1e293b',
                        900: '#0f172a',
                        950: '#0b132b',
                    }
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    display: ['Plus Jakarta Sans', 'sans-serif'],
                    mono: ['JetBrains Mono', 'monospace'],
                }
            }
        }
    };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col justify-between">

    <div class="min-h-screen flex w-full">
        <!-- Left Column: Modern Hero Branding Showcase (Desktop Only) -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-5/12 bg-navy-950 flex-col justify-between p-12 relative overflow-hidden text-white border-r border-slate-800">
            <!-- Subtle Radial Glow Background -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-brand-600/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Brand Header -->
            <div class="relative z-10 flex items-center gap-3">
                <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier" class="h-10 w-auto max-w-[160px] object-contain shrink-0">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 font-mono">Operations Portal</span>
            </div>

            <!-- Middle Value Pitch -->
            <div class="relative z-10 my-auto py-12 max-w-lg">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-xs font-medium text-slate-300 mb-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Sistem Logistik Instan &amp; On-Demand Dalam Kota</span>
                </div>

                <h1 class="font-display text-3xl xl:text-4xl font-extrabold text-white leading-tight tracking-tight mb-4">
                    Pantau rute, armada kurir, dan pesanan secara <span class="text-brand-500">real-time</span>.
                </h1>

                <p class="text-slate-400 text-sm leading-relaxed mb-8">
                    Pusat kendali operasional logistik City Courier: penugasan otomatis kurir motor &amp; mobil, estimasi ongkir akurat, notifikasi otomatis WhatsApp Fonnte, dan live tracking interaktif.
                </p>

                <!-- 3 Feature Chips -->
                <div class="space-y-3.5">
                    <div class="flex items-center gap-3 text-slate-300 text-xs bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                        <div class="w-8 h-8 rounded-lg bg-brand-600/10 text-brand-500 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                        </div>
                        <div>
                            <span class="font-semibold text-white block">Armada Motor &amp; Mobil</span>
                            <span class="text-slate-400">Deteksi otomatis kapasitas dimensi &amp; berat pengiriman.</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-slate-300 text-xs bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[18px]">chat</span>
                        </div>
                        <div>
                            <span class="font-semibold text-white block">Notifikasi WhatsApp Fonnte</span>
                            <span class="text-slate-400">Kirim otomatis nomor resi, QR code, dan link live tracking.</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-slate-300 text-xs bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[18px]">map</span>
                        </div>
                        <div>
                            <span class="font-semibold text-white block">Live Courier Tracking</span>
                            <span class="text-slate-400">Peta langsung posisi kurir penjemputan hingga titik antar.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Security Footer -->
            <div class="relative z-10 pt-6 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-slate-400">lock</span>
                    <span>Enkripsi Sesi TLS 256-Bit</span>
                </div>
                <span>City Courier v2.4</span>
            </div>
        </div>

        <!-- Right Column: Clean, Structured Login Form Card -->
        <div class="w-full lg:w-1/2 xl:w-7/12 flex items-center justify-center p-6 sm:p-12 lg:p-16">
            <div class="w-full max-w-md">

                <!-- Mobile Only Brand Header -->
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier" class="h-9 w-auto max-w-[140px] object-contain shrink-0">
                    <span class="text-[11px] font-semibold text-brand-600 font-mono">Operations Portal</span>
                </div>

                <!-- Form Box Header -->
                <div class="mb-8">
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Masuk ke Portal</h2>
                    <p class="text-slate-500 text-sm mt-1.5">
                        Masukkan email dan kata sandi akun administrator Anda.
                    </p>
                </div>

                <!-- Error Alert -->
                @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-start gap-3 shadow-sm animate-shake">
                    <span class="material-symbols-outlined text-red-600 text-[20px] shrink-0 mt-0.5">error</span>
                    <div class="text-sm">
                        <p class="font-semibold">Gagal Masuk</p>
                        <p class="text-red-700 mt-0.5 text-xs leading-relaxed">{{ $errors->first() }}</p>
                    </div>
                </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Alamat Email
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">mail</span>
                            </span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full pl-11 pr-4 py-3 rounded-xl bg-white border border-slate-200 text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 transition-all shadow-sm"
                                placeholder="nama@citycourier.com"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Kata Sandi
                            </label>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">lock</span>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="w-full pl-11 pr-11 py-3 rounded-xl bg-white border border-slate-200 text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 transition-all shadow-sm"
                                placeholder="••••••••"
                                required
                            >
                            <button
                                type="button"
                                id="togglePasswordBtn"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                                title="Tampilkan sandi"
                            >
                                <span class="material-symbols-outlined text-[20px]" id="togglePasswordIcon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 focus:ring-offset-0 transition-colors"
                            >
                            <span class="text-xs font-medium text-slate-600">Ingat sesi saya</span>
                        </label>
                        <span class="text-xs text-slate-400">Hanya untuk perangkat terpercaya</span>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="w-full py-3.5 px-4 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-display font-bold text-sm tracking-wide shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 transition-all flex items-center justify-center gap-2 group cursor-pointer"
                    >
                        <span>Masuk ke Dashboard</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-0.5 transition-transform">arrow_forward</span>
                    </button>
                </form>

                <!-- Bottom Helper -->
                <div class="mt-8 pt-6 border-t border-slate-200/80 text-center">
                    <p class="text-xs text-slate-500">
                        Mengalami kendala akun? Hubungi superadmin atau tim IT support City Courier.
                    </p>
                    <p class="text-[11px] text-slate-400 mt-2">
                        &copy; {{ date('Y') }} CityCourier. Seluruh hak cipta dilindungi.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passwordInput && toggleIcon) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
            });
        }
    });
    </script>
</body>
</html>
