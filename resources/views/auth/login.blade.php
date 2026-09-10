<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - CityCourier Admin</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>@layer base { html, body { margin: 0; padding: 0; } } ::-webkit-scrollbar { display: none; }</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "secondary-fixed": "#dae2fd",
                    "on-primary": "#ffffff",
                    "outline": "#8c7164",
                    "on-secondary-fixed-variant": "#3f465c",
                    "on-tertiary-fixed": "#0d1c2e",
                    "on-error": "#ffffff",
                    "on-primary-fixed": "#341100",
                    "surface-tint": "#9d4300",
                    "primary": "#9d4300",
                    "surface-container-high": "#dce9ff",
                    "primary-fixed": "#ffdbca",
                    "surface-variant": "#d3e4fe",
                    "on-surface": "#0b1c30",
                    "surface-container-highest": "#d3e4fe",
                    "on-secondary-container": "#5c647a",
                    "primary-fixed-dim": "#ffb690",
                    "inverse-surface": "#213145",
                    "on-tertiary": "#ffffff",
                    "tertiary-container": "#8d9bb2",
                    "on-surface-variant": "#584237",
                    "error-container": "#ffdad6",
                    "secondary": "#565e74",
                    "on-background": "#0b1c30",
                    "on-primary-container": "#582200",
                    "on-secondary": "#ffffff",
                    "secondary-container": "#dae2fd",
                    "tertiary-fixed-dim": "#b9c7df",
                    "inverse-on-surface": "#eaf1ff",
                    "on-tertiary-container": "#253345",
                    "primary-container": "#f97316",
                    "on-secondary-fixed": "#131b2e",
                    "surface-container-lowest": "#ffffff",
                    "surface-bright": "#f8f9ff",
                    "tertiary": "#515f74",
                    "outline-variant": "#e0c0b1",
                    "on-tertiary-fixed-variant": "#3a485b",
                    "surface": "#f8f9ff",
                    "background": "#f8f9ff",
                    "on-primary-fixed-variant": "#783200",
                    "surface-container": "#e5eeff",
                    "error": "#ba1a1a",
                    "secondary-fixed-dim": "#bec6e0",
                    "tertiary-fixed": "#d5e3fc",
                    "on-error-container": "#93000a",
                    "inverse-primary": "#ffb690",
                    "surface-container-low": "#eff4ff",
                    "surface-dim": "#cbdbf5"
                },
                "fontFamily": {
                    "display-lg": ["Inter"],
                    "headline-sm": ["Inter"],
                    "data-mono": ["Inter"],
                    "body-lg": ["Inter"],
                    "label-sm": ["Inter"],
                    "body-sm": ["Inter"],
                    "body-md": ["Inter"],
                    "label-md": ["Inter"],
                    "headline-lg": ["Inter"],
                    "headline-xl": ["Inter"]
                },
                "fontSize": {
                    "display-lg": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                    "headline-sm": ["16px", { "lineHeight": "24px", "letterSpacing": "-0.005em", "fontWeight": "600" }],
                    "data-mono": ["13px", { "lineHeight": "18px", "letterSpacing": "-0.01em", "fontWeight": "500" }],
                    "body-lg": ["15px", { "lineHeight": "22px", "letterSpacing": "0em", "fontWeight": "400" }],
                    "label-sm": ["11px", { "lineHeight": "14px", "letterSpacing": "0.03em", "fontWeight": "600" }],
                    "body-sm": ["13px", { "lineHeight": "18px", "letterSpacing": "0.005em", "fontWeight": "400" }],
                    "body-md": ["14px", { "lineHeight": "20px", "letterSpacing": "0em", "fontWeight": "400" }],
                    "label-md": ["12px", { "lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "500" }],
                    "headline-lg": ["20px", { "lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                    "headline-xl": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.015em", "fontWeight": "600" }]
                }
            }
        }
    };
    </script>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased">
    <div class="min-h-screen flex items-center justify-center p-space-xl">
        <div class="w-full max-w-md">
            {{-- Logo & Title --}}
            <div class="text-center mb-space-2xl">
                <div class="w-16 h-16 rounded-xl bg-primary-container flex items-center justify-center mx-auto mb-space-md shadow-sm">
                    <span class="material-symbols-outlined text-on-primary text-[32px]">local_shipping</span>
                </div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold">CityCourier</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-xs">Admin Panel</p>
            </div>

            {{-- Login Card --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-xl">
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-semibold mb-space-lg">Masuk ke Akun</h2>

                @if ($errors->any())
                    <div class="mb-space-md p-space-md rounded-lg bg-red-50 border border-red-200">
                        <div class="flex items-center gap-space-xs">
                            <span class="material-symbols-outlined text-red-600 text-[18px]">error</span>
                            <p class="font-body-sm text-body-sm text-red-700">{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    {{-- Email --}}
                    <div class="mb-space-md">
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Email</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-space-md">
                                <span class="material-symbols-outlined text-secondary text-[18px]">mail</span>
                            </span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full pl-11 pr-space-md py-space-sm rounded-lg bg-surface border border-surface-container-high text-on-surface placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all"
                                placeholder="admin@citycourier.com"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    {{-- Password --}}
                    <div class="mb-space-md">
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-space-md">
                                <span class="material-symbols-outlined text-secondary text-[18px]">lock</span>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="w-full pl-11 pr-space-md py-space-sm rounded-lg bg-surface border border-surface-container-high text-on-surface placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all"
                                placeholder="••••••••"
                                required
                            >
                        </div>
                    </div>

                    {{-- Remember --}}
                    <div class="flex items-center justify-between mb-space-lg">
                        <label class="flex items-center gap-space-xs cursor-pointer">
                            <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-surface-container-high bg-surface text-primary focus:ring-primary">
                            <span class="font-body-sm text-body-sm text-on-surface-variant">Ingat saya</span>
                        </label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" class="w-full py-space-sm rounded-lg bg-primary-container hover:bg-primary text-on-primary font-semibold shadow-sm transition-all flex items-center justify-center gap-space-xs">
                        <span class="material-symbols-outlined text-[18px]">login</span>
                        Masuk
                    </button>
                </form>
            </div>

            {{-- Footer --}}
            <p class="text-center font-label-sm text-label-sm text-secondary mt-space-lg">
                &copy; 2024 CityCourier. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
