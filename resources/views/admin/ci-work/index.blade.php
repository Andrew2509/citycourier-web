@extends('layouts.admin')

@section('title', 'Ci-Work Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Ci-Work Dashboard</h1>
        <p class="text-sm text-slate-400 mt-1">Monitoring operasional kurir real-time</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-info/5 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Kurir Online</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['online_couriers'] }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-xl">two_wheeler</span>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 text-[10px] font-semibold bg-success/10 text-success px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse"></span> Live
            </span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Tugas Berjalan</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['active_tasks'] }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">task</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-success/5 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Selesai Hari Ini</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['completed_today'] }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-success/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-success text-xl">task_alt</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-warning/5 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Omzet Hari Ini</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-warning/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-warning text-xl">payments</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Tasks -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-surface-border overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">running_with_errors</span>
                    Tugas Aktif Terkini
                </h4>
                <a href="{{ route('admin.ci-work.tasks') }}" class="text-xs font-semibold text-primary hover:text-primary-dark transition-all flex items-center gap-1">
                    Lihat Semua
                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Nomor Resi</th>
                            <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kurir</th>
                            <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                            <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentTasks as $task)
                        <tr class="hover:bg-slate-50 transition-all">
                            <td class="py-3.5 px-5 text-sm font-semibold text-slate-700">{{ $task->tracking_number ?? $task->order_number }}</td>
                            <td class="py-3.5 px-5 text-sm text-slate-600">{{ $task->courier->user->name ?? 'Unassigned' }}</td>
                            <td class="py-3.5 px-5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-xs text-slate-400">{{ $task->updated_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-sm text-slate-400">Tidak ada tugas aktif saat ini</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">link</span>
                    Tautan Cepat
                </h4>
            </div>
            <div class="p-5 space-y-3">
                <a href="{{ route('admin.ci-work.attendance') }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-primary/30 hover:bg-primary/5 transition-all">
                    <span class="material-symbols-outlined text-primary text-xl">fingerprint</span>
                    <span class="text-sm font-semibold text-slate-700">Cek Presensi Kurir</span>
                </a>
                <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-primary/30 hover:bg-primary/5 transition-all">
                    <span class="material-symbols-outlined text-primary text-xl">checklist</span>
                    <span class="text-sm font-semibold text-slate-700">Pantau Antrean Tugas</span>
                </a>
                <a href="{{ route('admin.ci-work.finance') }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-primary/30 hover:bg-primary/5 transition-all">
                    <span class="material-symbols-outlined text-primary text-xl">account_balance</span>
                    <span class="text-sm font-semibold text-slate-700">Verifikasi Setoran</span>
                </a>
                <a href="{{ route('admin.couriers') }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-primary/30 hover:bg-primary/5 transition-all">
                    <span class="material-symbols-outlined text-primary text-xl">person_add</span>
                    <span class="text-sm font-semibold text-slate-700">Verifikasi Kurir Baru</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
