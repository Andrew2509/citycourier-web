@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')

@section('content')
<div class="flex flex-col w-full gap-space-lg">
    <!-- Header Section with Quick Stats Bento Summary -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                <span>Operasional Logistik Harian</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Pesanan</h1>
            <p class="font-body-md text-body-md text-secondary max-w-2xl">
                Pantau, kelola, dan atur penugasan seluruh pesanan pengiriman pelanggan secara real-time antar titik penjemputan.
            </p>
        </div>
        <!-- Live Counter Stat Chips -->
        <div class="flex items-center gap-space-sm shrink-0">
            <div class="flex items-center gap-space-sm px-space-md py-space-xs rounded-lg bg-surface-container-lowest shadow-sm">
                <div class="w-2.5 h-2.5 rounded-full bg-primary-container animate-ping"></div>
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Total Pesanan</span>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ number_format($orders->total()) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status Filter Pills --}}
    @php
        $filters = [
            'all'        => ['label' => 'Semua',              'count' => $statusCounts['all'] ?? 0],
            'pending'    => ['label' => 'Pending',            'count' => $statusCounts['pending'] ?? 0],
            'assigned'   => ['label' => 'Ditugaskan',         'count' => $statusCounts['assigned'] ?? 0],
            'delivered'  => ['label' => 'Selesai',            'count' => $statusCounts['delivered'] ?? 0],
            'cancelled'  => ['label' => 'Dibatalkan',         'count' => $statusCounts['cancelled'] ?? 0],
        ];
        $activeStatus = request('status', 'all');
    @endphp

    <!-- Filter Controls & Action Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md p-space-md rounded-xl bg-surface-container-lowest shadow-sm">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-space-xs overflow-x-auto pb-space-xs lg:pb-0" id="statusFilterPills">
            @foreach($filters as $key => $filter)
            <a href="{{ route('admin.orders', $key === 'all' ? [] : ['status' => $key]) }}"
               class="filter-tab px-space-md py-space-xs rounded-lg font-label-md text-label-md font-semibold transition-all flex items-center gap-space-xs shrink-0
                      {{ $activeStatus === $key ? 'bg-primary-container text-on-primary shadow-sm' : 'text-secondary hover:bg-surface-container-low hover:text-on-surface' }}">
                <span>{{ $filter['label'] }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[11px] font-bold {{ $activeStatus === $key ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container text-secondary' }}">{{ $filter['count'] }}</span>
            </a>
            @endforeach
        </div>
        <!-- Search & Advanced Actions -->
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-space-sm">
            <!-- Search Input -->
            <form method="GET" action="{{ route('admin.orders') }}" class="relative flex-1 sm:w-80">
                @if($activeStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $activeStatus }}">
                @endif
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-secondary text-[18px] pointer-events-none">search</span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cari order ID, nama, telepon, alamat..."
                       class="w-full bg-surface pl-9 pr-8 py-2 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest transition-all shadow-sm" />
            </form>
            <!-- Create Order CTA -->
            <a href="{{ route('admin.orders') }}" class="flex items-center justify-center gap-space-xs px-space-lg py-2 rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-md transition-all active:scale-95 shrink-0">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>Buat Pesanan</span>
            </a>
        </div>
    </div>

    <!-- Primary Card Container: Order Table -->
    <div class="flex flex-col w-full bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <!-- Card Header / Meta Summary -->
        <div class="flex items-center justify-between px-space-xl py-space-md bg-surface-container-lowest">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">local_mall</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Daftar Pesanan</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Menampilkan <span class="font-bold text-on-surface">{{ $orders->count() }}</span> data pengiriman</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <button class="p-space-xs rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Muat Ulang Data" onclick="location.reload()" type="button">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                </button>
            </div>
        </div>

        <!-- Responsive Table Container -->
        @if($orders->count() > 0)
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Nomor Resi</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Customer</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Pengirim & Pickup</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Penerima & Tujuan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Harga</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-none">
                    @foreach($orders as $order)
                    @php
                        $statusStyles = match($order->status) {
                            'pending'    => 'bg-surface-container text-secondary',
                            'assigned'   => 'bg-secondary-container text-on-secondary-fixed-variant',
                            'picking_up' => 'bg-blue-50 text-blue-700',
                            'delivering' => 'bg-amber-50 text-amber-700',
                            'delivered'  => 'bg-emerald-50 text-emerald-700',
                            'cancelled'  => 'bg-red-50 text-red-700',
                            default      => 'bg-surface-container text-secondary',
                        };
                        $statusLabel = match($order->status) {
                            'pending'    => 'Pending',
                            'assigned'   => 'Assigned',
                            'picking_up' => 'Picking Up',
                            'delivering' => 'Delivering',
                            'delivered'  => 'Delivered',
                            'cancelled'  => 'Cancelled',
                            default      => ucfirst($order->status),
                        };
                        $courierName = $order->courier?->user?->name ?? '-';
                    @endphp
                    <tr class="order-row group hover:bg-surface transition-colors cursor-pointer">
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <span class="font-data-mono font-bold text-primary tracking-wide">{{ $order->tracking_number }}</span>
                                <button class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-surface-container-high text-secondary hover:text-primary transition-opacity" onclick="navigator.clipboard.writeText('{{ $order->tracking_number }}')" title="Salin Nomor Resi" type="button">
                                    <span class="material-symbols-outlined text-[15px]">content_copy</span>
                                </button>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold text-xs">
                                    {{ strtoupper(substr($order->customer_name, 0, 1)) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-headline-sm text-body-md font-semibold text-on-surface">{{ $order->customer_name }}</span>
                                    <a class="font-data-mono text-secondary hover:text-primary transition-colors text-xs flex items-center gap-0.5" href="tel:{{ $order->customer_phone }}">
                                        <span class="material-symbols-outlined text-[12px]">phone</span>
                                        <span>{{ $order->customer_phone }}</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 max-w-xs">
                            <div class="flex flex-col">
                                <span class="font-medium text-on-surface truncate">{{ $order->pickup_name ?? '-' }}</span>
                                <span class="text-xs text-secondary line-clamp-1">{{ $order->pickup_address }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 max-w-xs">
                            <div class="flex flex-col">
                                <span class="font-medium text-on-surface truncate">{{ $order->customer_name }}</span>
                                <span class="text-xs text-secondary line-clamp-1">{{ $order->delivery_address }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="font-data-mono font-bold text-emerald-600 text-sm">Rp {{ number_format($order->price, 0, ',', '.') }}</span>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $statusStyles }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $order->status === 'delivered' ? 'bg-emerald-600' : ($order->status === 'assigned' ? 'bg-blue-500' : 'bg-current') }}"></span>
                                <span class="font-label-sm text-xs font-bold">{{ $statusLabel }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.orders.detail', $order->id) }}" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Lihat Detail Pesanan">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                <form action="{{ route('admin.orders.destroy', $order->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan {{ $order->tracking_number }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors" title="Hapus Pesanan">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <!-- Empty State -->
        <div class="py-space-2xl px-space-md flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                <span class="material-symbols-outlined text-[32px] text-secondary">manage_search</span>
            </div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Tidak Ada Pesanan Ditemukan</h3>
            <p class="font-body-sm text-body-sm text-secondary max-w-sm mt-space-xs">
                @if(request('search'))
                    Tidak ditemukan pesanan yang sesuai dengan pencarian "{{ request('search') }}".
                @else
                    Belum ada pesanan masuk saat ini.
                @endif
            </p>
            <a href="{{ route('admin.orders') }}" class="mt-space-lg px-space-md py-1.5 bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-md text-label-md rounded-lg font-medium transition-colors">
                Reset Filter Pencarian
            </a>
        </div>
        @endif

        <!-- Table Pagination Footer -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-space-sm px-space-xl py-3.5 bg-surface-container-lowest">
            <div class="flex items-center gap-space-md">
                <span class="font-body-sm text-body-sm text-secondary">
                    Menampilkan <span class="font-semibold text-on-surface">{{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }}</span> dari <span class="font-semibold text-on-surface">{{ $orders->total() }}</span> pesanan
                </span>
            </div>
            <!-- Pagination Buttons -->
            <div class="flex items-center gap-1">
                {{ $orders->withQueryString()->links('pagination::tailwind') }}
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none flex items-center gap-space-sm px-space-md py-space-xs bg-inverse-surface text-inverse-on-surface rounded-xl shadow-xl" id="copyToast">
    <span class="material-symbols-outlined text-primary-container text-[20px]">check_circle</span>
    <span class="font-body-sm text-body-sm font-medium" id="toastMessage">Nomor resi berhasil disalin ke clipboard</span>
</div>

<script>
function showToast(msg) {
    const toast = document.getElementById('copyToast');
    const toastMsg = document.getElementById('toastMessage');
    toastMsg.textContent = msg;
    toast.classList.remove('translate-y-20', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }, 2500);
}
</script>
@endsection
