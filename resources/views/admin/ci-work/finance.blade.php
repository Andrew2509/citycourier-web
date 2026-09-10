@extends('layouts.admin')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Keuangan & Setoran Kurir</h1>
        <p class="text-sm text-gray-500">Rekapitulasi penghasilan dan penarikan dana kurir</p>
    </div>
</div>

<div class="flex border-b border-gray-200 mb-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <button onclick="switchTab('earnings')" id="tab-earnings" class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 border-[#059669] text-[#059669] transition-colors">
        <span class="material-symbols-outlined text-lg">account_balance_wallet</span>
        Ringkasan Penghasilan
    </button>
    <button onclick="switchTab('withdrawals')" id="tab-withdrawals" class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-lg">savings</span>
        Penarikan Dana
    </button>
</div>

<div id="panel-earnings" class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-5 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Penghasilan Kurir</h2>
        <p class="text-sm text-gray-500">Total penghasilan dari pesanan yang telah selesai</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-semibold">Kurir</th>
                    <th class="text-left px-5 py-3 font-semibold">Total Pesanan Selesai</th>
                    <th class="text-left px-5 py-3 font-semibold">Total Penghasilan</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($earnings as $earning)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#059669] text-lg">person</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $earning->user->name ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ $earning->user->email ?? '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-600 font-medium">{{ $earning->completed_orders }}</td>
                    <td class="px-5 py-4 font-semibold text-[#059669]">Rp {{ number_format($earning->total_earnings, 0, ',', '.') }}</td>
                    <td class="px-5 py-4">
                        @if($earning->total_earnings > 0)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="material-symbols-outlined text-sm">trending_up</span>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                Belum Ada
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-gray-300 text-5xl block mb-2">paid</span>
                        <p class="text-gray-400">Belum ada data penghasilan</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-5 border-t border-gray-100">
        {{ $earnings->links() }}
    </div>
</div>

<div id="panel-withdrawals" class="bg-white rounded-xl shadow-sm border border-gray-100 hidden">
    <div class="p-5 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Penarikan Dana Kurir</h2>
        <p class="text-sm text-gray-500">Permohonan penarikan dana yang perlu diproses</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-semibold">Kurir</th>
                    <th class="text-left px-5 py-3 font-semibold">Jumlah</th>
                    <th class="text-left px-5 py-3 font-semibold">Rekening</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                    <th class="text-left px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($withdrawals as $withdrawal)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#059669] text-lg">person</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $withdrawal->courier->user->name ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($withdrawal->created_at)->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 font-semibold text-gray-800">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</td>
                    <td class="px-5 py-4 text-gray-600">
                        <div>
                            <p class="font-medium">{{ $withdrawal->bank_name }}</p>
                            <p class="text-xs text-gray-400">{{ $withdrawal->account_number }} - {{ $withdrawal->account_name }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        @if($withdrawal->status === 'pending')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                <span class="material-symbols-outlined text-sm">pending</span>
                                Menunggu
                            </span>
                        @elseif($withdrawal->status === 'approved')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Disetujui
                            </span>
                        @elseif($withdrawal->status === 'rejected')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                Ditolak
                            </span>
                        @elseif($withdrawal->status === 'completed')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                <span class="material-symbols-outlined text-sm">verified</span>
                                Selesai
                            </span>
                        @endif
                        @if($withdrawal->admin_notes && $withdrawal->status !== 'pending')
                            <p class="text-xs text-gray-400 mt-1" title="{{ $withdrawal->admin_notes }}">{{ Str::limit($withdrawal->admin_notes, 30) }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @if($withdrawal->status === 'pending')
                            <div class="flex items-center gap-2">
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-green-50 text-green-700 hover:bg-green-100 transition-colors">
                                        <span class="material-symbols-outlined text-sm">check</span>
                                        Setujui
                                    </button>
                                </form>
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 transition-colors">
                                        <span class="material-symbols-outlined text-sm">close</span>
                                        Tolak
                                    </button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-gray-300 text-5xl block mb-2">savings</span>
                        <p class="text-gray-400">Belum ada permohonan penarikan dana</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-5 border-t border-gray-100">
        {{ $withdrawals->links() }}
    </div>
</div>

@push('scripts')
<script>
    function switchTab(tab) {
        const earningsTab = document.getElementById('tab-earnings');
        const withdrawalsTab = document.getElementById('tab-withdrawals');
        const earningsPanel = document.getElementById('panel-earnings');
        const withdrawalsPanel = document.getElementById('panel-withdrawals');

        if (tab === 'earnings') {
            earningsTab.classList.add('border-[#059669]', 'text-[#059669]');
            earningsTab.classList.remove('border-transparent', 'text-gray-500');
            withdrawalsTab.classList.remove('border-[#059669]', 'text-[#059669]');
            withdrawalsTab.classList.add('border-transparent', 'text-gray-500');
            earningsPanel.classList.remove('hidden');
            withdrawalsPanel.classList.add('hidden');
        } else {
            withdrawalsTab.classList.add('border-[#059669]', 'text-[#059669]');
            withdrawalsTab.classList.remove('border-transparent', 'text-gray-500');
            earningsTab.classList.remove('border-[#059669]', 'text-[#059669]');
            earningsTab.classList.add('border-transparent', 'text-gray-500');
            withdrawalsPanel.classList.remove('hidden');
            earningsPanel.classList.add('hidden');
        }
    }
</script>
@endpush
@endsection
