<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resi Tidak Ditemukan | City Courier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-sm border border-slate-200 text-center">
        <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center text-3xl mb-4">
            📦
        </div>
        <h1 class="text-xl font-bold text-slate-900 mb-2">Resi Tidak Ditemukan</h1>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed">
            Nomor resi <span class="font-mono font-bold text-slate-800">{{ $number }}</span> tidak terdaftar di sistem City Courier. Mohon periksa kembali nomor resi Anda.
        </p>
        <a href="/" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm transition">
            Kembali ke Beranda
        </a>
    </div>
</body>
</html>

