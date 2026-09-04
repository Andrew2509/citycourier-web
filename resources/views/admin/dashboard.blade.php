@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Metric Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Pesanan -->
        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden group hover:shadow-lg hover:shadow-primary/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Pesanan</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $totalOrders ?? 128 }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">shopping_bag</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-success">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                <span>+12.4% dari kemarin</span>
            </div>
        </div>

        <!-- Pendapatan -->
        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden group hover:shadow-lg hover:shadow-success/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-success/5 rounded-full blur-xl group-hover:bg-success/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Pendapatan</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">Rp {{ number_format($revenue ?? 1250000, 0, ',', '.') }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-success/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-success text-xl">payments</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-success">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                <span>+8.1% target harian</span>
            </div>
        </div>

        <!-- Kurir Aktif -->
        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden group hover:shadow-lg hover:shadow-info/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-info/5 rounded-full blur-xl group-hover:bg-info/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Kurir Aktif</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $activeCouriers ?? 24 }} <span class="text-sm font-normal text-slate-400">/ {{ $totalCouriers ?? 42 }}</span></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-xl">group</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                <span>{{ $pendingVerification ?? 3 }} Menunggu Verifikasi</span>
            </div>
        </div>

        <!-- Status Pengiriman -->
        <div class="bg-white rounded-2xl p-5 border border-surface-border relative overflow-hidden group hover:shadow-lg hover:shadow-warning/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-warning/5 rounded-full blur-xl group-hover:bg-warning/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Status Pengiriman</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $delivering ?? 37 }} <span class="text-sm font-normal text-slate-400">Diantar</span></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-warning/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-warning text-xl">local_shipping</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Pending: <strong class="text-slate-700">{{ $pendingOrders ?? 8 }}</strong></span>
                <span>Selesai: <strong class="text-slate-700">{{ $completedOrders ?? 59 }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Charts and Couriers -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Charts -->
        <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-surface-border">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
                <div>
                    <h4 class="text-lg font-bold text-slate-800">Analisis Tren & Pendapatan</h4>
                    <p class="text-sm text-slate-400">Performa pengiriman dan omset real-time</p>
                </div>
                <div class="flex items-center bg-slate-100 p-1 rounded-xl gap-1">
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-white shadow-sm">Hari ini</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">7 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">30 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">Bulan ini</button>
                </div>
            </div>
            <!-- Chart -->
            <div class="h-56 w-full flex items-end justify-between gap-2 pt-4 px-2 pb-2 relative">
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-30">
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                </div>
                @foreach(['Sen' => 60, 'Sel' => 85, 'Rab' => 70, 'Kam' => 95, 'Jum' => 75, 'Sab' => 100, 'Min' => 80] as $day => $height)
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-slate-100 rounded-t-lg h-{{ $height === 100 ? 'full' : ($height >= 80 ? '4/5' : ($height >= 60 ? '3/5' : '1/2')) }} group-hover:bg-primary/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">{{ intval($height * 0.32) }} Pesanan</span>
                        <div class="w-full bg-gradient-to-t from-primary to-primary-light rounded-t-lg h-full"></div>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">{{ $day }}</span>
                </div>
                @endforeach
            </div>
            <div class="flex items-center justify-between pt-4 mt-3 border-t border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-gradient-to-r from-primary to-primary-light"></span>
                        <span class="text-xs text-slate-500">Jumlah Pesanan</span>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400">Update: Baru saja</span>
            </div>
        </div>

        <!-- Status Kurir -->
        <div class="bg-white rounded-2xl p-5 border border-surface-border">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-bold text-slate-800">Status Kurir</h4>
                <span class="text-[11px] bg-success/10 text-success px-2.5 py-1 rounded-full font-semibold">{{ $activeCouriers ?? 24 }} Online</span>
            </div>
            <p class="text-sm text-slate-400 mb-4">Pemantauan armada kurir aktif.</p>
            <div class="space-y-3">
                @foreach($couriers ?? [] as $courier)
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm">
                                {{ strtoupper(substr($courier->name ?? 'A', 0, 1)) }}
                            </div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white" style="background: {{ ($courier->is_online ?? false) ? '#4CAF50' : '#94A3B8' }}"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-semibold text-slate-700">{{ $courier->name ?? 'N/A' }}</h5>
                            <span class="text-[11px] text-slate-400">{{ $courier->location ?? 'N/A' }} • {{ $courier->deliveries ?? 0 }} Pengiriman</span>
                        </div>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold" style="background: {{ ($courier->is_online ?? false) ? 'rgba(76,175,80,0.1)' : 'rgba(148,163,184,0.1)' }}; color: {{ ($courier->is_online ?? false) ? '#4CAF50' : '#94A3B8' }}">
                        {{ ($courier->is_online ?? false) ? 'Aktif' : 'Offline' }}
                    </span>
                </div>
                @endforeach

                @if(!isset($couriers) || empty($couriers))
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm">P</div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-success border-2 border-white"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-semibold text-slate-700">Princenton</h5>
                            <span class="text-[11px] text-slate-400">Kec. Lowokwaru • 3 Pengiriman</span>
                        </div>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full bg-success/10 text-success font-semibold">Aktif</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-info to-blue-400 flex items-center justify-center text-white font-bold text-sm">B</div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-success border-2 border-white"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-semibold text-slate-700">Budi</h5>
                            <span class="text-[11px] text-slate-400">Kec. Blimbing • 5 Pengiriman</span>
                        </div>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full bg-success/10 text-success font-semibold">Aktif</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-400 to-slate-500 flex items-center justify-center text-white font-bold text-sm">A</div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-slate-400 border-2 border-white"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-semibold text-slate-700">Andi</h5>
                            <span class="text-[11px] text-slate-400">Kec. Klojen • Selesai tugas</span>
                        </div>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-400 font-semibold">Offline</span>
                </div>
                @endif
            </div>
            <a href="{{ route('admin.couriers') }}" class="w-full mt-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-sm font-semibold flex items-center justify-center gap-2">
                <span>Lihat Semua Kurir</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>

    <!-- Pesanan Terbaru -->
    <div class="bg-white rounded-2xl p-5 border border-surface-border">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h4 class="text-lg font-bold text-slate-800">Pesanan Terbaru</h4>
                <p class="text-sm text-slate-400">Daftar transaksi terbaru</p>
            </div>
            <div class="flex items-center gap-3">
                <select class="bg-slate-50 border border-slate-200 text-slate-600 text-sm pl-4 pr-8 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <option>Semua Status</option>
                    <option>Sedang Diantar</option>
                    <option>Pending</option>
                    <option>Selesai</option>
                </select>
                <button class="bg-gradient-to-r from-primary to-primary-light text-white px-4 py-2 rounded-xl text-sm font-semibold hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Buat Pesanan
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ID Pesanan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pelanggan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kurir</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tujuan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($orders ?? [] as $order)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#{{ $order->tracking_number ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">{{ $order->customer_name ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4 text-sm text-slate-600">{{ $order->courier_name ?? 'Belum ditugaskan' }}</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">{{ $order->destination_address ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ match($order->status ?? 'pending') { 'delivered' => 'bg-success/10 text-success', 'delivering', 'in_transit' => 'bg-primary/10 text-primary', 'pending' => 'bg-warning/10 text-warning', default => 'bg-slate-100 text-slate-500' } }}">
                                {{ $order->status_label ?? 'Pending' }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.orders.detail', $order->id) ?? '#' }}" class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                <a href="#" class="p-1.5 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all" title="Lacak">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    @if(!isset($orders) || empty($orders))
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#CC-9821</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">Siti Rahma</td>
                        <td class="py-3.5 px-4 text-sm text-slate-600">Princenton</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">Jl. Soekarno Hatta No. 12, Malang</td>
                        <td class="py-3.5 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">Sedang Diantar</span></td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all"><span class="material-symbols-outlined text-[18px]">visibility</span></button>
                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all"><span class="material-symbols-outlined text-[18px]">map</span></button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#CC-9820</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">Ahmad Fauzi</td>
                        <td class="py-3.5 px-4 text-sm text-slate-600">Budi</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">Jl. Ijen Boulevard 45, Malang</td>
                        <td class="py-3.5 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-success/10 text-success">Selesai</span></td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all"><span class="material-symbols-outlined text-[18px]">visibility</span></button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#CC-9819</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">Dewi Lestari</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 italic">Belum ditugaskan</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">Jl. Borobudur No. 8, Malang</td>
                        <td class="py-3.5 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-warning/10 text-warning">Pending</span></td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button class="p-1.5 rounded-lg bg-primary text-white hover:opacity-90 transition-all"><span class="material-symbols-outlined text-[18px]">person_add</span></button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#CC-9818</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">Reza Pratama</td>
                        <td class="py-3.5 px-4 text-sm text-slate-600">Princenton</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">Jl. Dieng Atas No. 99, Malang</td>
                        <td class="py-3.5 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">Sedang Diantar</span></td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all"><span class="material-symbols-outlined text-[18px]">visibility</span></button>
                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all"><span class="material-symbols-outlined text-[18px]">map</span></button>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between pt-4 mt-4 border-t border-slate-100">
            <span class="text-sm text-slate-400">Menampilkan 1-4 dari {{ $totalOrders ?? 128 }} pesanan</span>
            <div class="flex items-center gap-1.5">
                <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-400 text-sm font-medium" disabled>Sebelumnya</button>
                <button class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-semibold shadow-sm shadow-primary/20">1</button>
                <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium hover:bg-slate-200">2</button>
                <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium hover:bg-slate-200">3</button>
                <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium hover:bg-slate-200">Selanjutnya</button>
            </div>
        </div>
    </div>
</div>
@endsection
