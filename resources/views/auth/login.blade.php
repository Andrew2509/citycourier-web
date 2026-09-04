<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - City Courier Admin</title>
    <meta name="description" content="Login to City Courier Admin Panel">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#EC5B13',
                        'primary-light': '#FF8A00',
                        success: '#4CAF50',
                        warning: '#FF9800',
                        danger: '#F44336',
                        info: '#2196F3',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        {{-- Logo & Title --}}
        <div class="text-center mb-8">
            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary/30">
                <span class="material-symbols-outlined text-white text-4xl">local_shipping</span>
            </div>
            <h1 class="text-3xl font-bold text-white">CityCourier</h1>
            <p class="text-slate-400 mt-2">Admin Panel</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6">Masuk ke Akun</h2>

            @if ($errors->any())
                <div class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-400 text-lg">error</span>
                        <p class="text-sm text-red-200">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                            <span class="material-symbols-outlined text-slate-400 text-lg">mail</span>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"
                            placeholder="admin@citycourier.com"
                            required
                            autofocus
                        >
                    </div>
                </div>

                {{-- Password --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4">
                            <span class="material-symbols-outlined text-slate-400 text-lg">lock</span>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"
                            placeholder="••••••••"
                            required
                        >
                    </div>
                </div>

                {{-- Remember --}}
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-slate-500 bg-white/10 text-primary focus:ring-primary/50">
                        <span class="text-sm text-slate-300">Ingat saya</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="w-full py-3 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white font-semibold hover:shadow-lg hover:shadow-primary/30 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">login</span>
                    Masuk
                </button>
            </form>
        </div>

        {{-- Footer --}}
        <p class="text-center text-slate-500 text-xs mt-6">
            &copy; 2024 CityCourier. All rights reserved.
        </p>
    </div>
</body>
</html>
