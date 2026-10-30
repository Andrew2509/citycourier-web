@extends('layouts.admin')

@section('title', 'Transaksi DANA')

@section('content')
<div class="space-y-5">
    <!-- Flash -->
    @if(session('success'))
    <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-semibold flex items-center gap-2">
        <span class="material-symbols-outlined text-[17px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Transaksi DANA
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Penarikan Saldo Kurir</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Daftar Permintaan Penarikan ke DANA</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola dan verifikasi transfer saldo kurir mitra ke akun DANA</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <a href="{{ url()->current() }}" class="btn btn-secondary text-xs h-9 px-2.5 gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-500">refresh</span>
                <span>Segarkan</span>
            </a>
            <button onclick="openGuideModal()" type="button" class="btn btn-secondary text-xs h-8 px-2.5 gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-500">help</span>
                <span>Panduan</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card p-4 border-slate-100 bg-slate-50/50 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Status</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="statusFilter" value="all" checked onchange="updateDanaTable()"
                            class="accent-primary-600 {{ $statusFilter === 'all' || !$statusFilter ? 'checked' : '' }}">Semua
                    </label>
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="statusFilter" value="pending" onchange="updateDanaTable()"
                            class="accent-primary-600 {{ $statusFilter === 'pending' ? 'checked' : '' }}">Menunggu
                    </label>
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="statusFilter" value="processing" onchange="updateDanaTable()"
                            class="accent-primary-600 {{ $statusFilter === 'processing' ? 'checked' : '' }}">Diproses
                    </label>
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="statusFilter" value="completed" onchange="updateDanaTable()"
                            class="accent-primary-600 {{ $statusFilter === 'completed' ? 'checked' : '' }}">Berhasil
                    </label>
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="statusFilter" value="rejected" onchange="updateDanaTable()"
                            class="accent-primary-600 {{ $statusFilter === 'rejected' ? 'checked' : '' }}">Ditolak
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Cari Kurir / Referensi</label>
                <div class="relative">
                    <span class="material-symbols-outlined text-[16px] text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2">search</span>
                    <input type="text" id="danaSearch" oninput="updateDanaTable()"
                        placeholder="Nama kurir, no HP, atau nomor referensi..."
                        class="form-input h-8 pl-8 text-xs w-full sm:w-80">
                </div>
            </div>
        </div>
    </div>

    <!-- DANA Transactions Table -->
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/50">
            <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                <span>Menampilkan {{ $withdrawals->total() > 0 ? $withdrawals->firstItem() . ' - ' . $withdrawals->lastItem() : 0 }} dari {{ $withdrawals->total() }} penarikan DANA</span>
                {{ $withdrawals->withQueryString()->links() }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kurir</th>
                        <th>Jumlah Penarikan</th>
                        <th>Metode & Akun</th>
                        <th>No. Referensi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $wd)
                    @php
                        $wdStatus = in_array($wd->status, ['approved', 'completed', 'success']) ? 'completed' : (in_array($wd->status, ['rejected', 'failed', 'cancelled']) ? 'rejected' : 'pending');
                        $providerName = strtoupper($wd->provider ?? $wd->bank_name ?? 'DANA');
                        $providerHasApi = $providerName === 'DANA';
                        // Nomor tujuan disamarkan untuk tampilan admin (raw tetap di DB).
                        $wdAccountMasked = $wd->account_number
                            ? (\App\Services\WithdrawalService::maskPhone($wd->account_number))
                            : ($wd->danaConnection?->masked_phone ?? '-');
                    @endphp
                    <tr class="wd-row" data-status="{{ $wdStatus }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($wd->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900 text-xs">{{ $wd->courier->user->name ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-400 font-mono">{{ \Carbon\Carbon::parse($wd->created_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono font-bold text-slate-900 text-xs whitespace-nowrap">
                            {{ 'Rp ' . number_format((float) $wd->amount, 0, ',', '.') }}
                        </td>
                        <td>
                            <div class="text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-800">
                                    @if($providerName === 'DANA')
                                        <span class="w-2 h-2 rounded-full bg-[#118EEA] inline-block"></span>
                                        <span>DANA</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-sky-500 inline-block"></span>
                                        <span>{{ $providerName }}</span>
                                    @endif
                                </span>
                                <p class="text-[11px] text-slate-600 font-mono">{{ $wdAccountMasked }}</p>
                                <p class="text-[10px] text-slate-400">a.n {{ $wd->account_name ?: ($wd->danaConnection?->account_name ?? 'Kurir') }}</p>
                            </div>
                        </td>
                        <td>
                            <span class="text-[11px] font-mono text-slate-600 block">{{ $wd->provider_reference ?: ($wd->idempotency_key ?: '-') }}</span>
                        </td>
                        <td>
                            @if(in_array($wd->status, ['pending', 'reserved']))
                                <span class="badge badge-warning text-[11px]">Menunggu</span>
                            @elseif($wd->status === 'processing')
                                <span class="badge badge-info text-[11px]">Diproses</span>
                            @elseif($wd->status === 'approved')
                                <span class="badge badge-success text-[11px]">Disetujui</span>
                            @elseif(in_array($wd->status, ['completed', 'success']))
                                <span class="badge badge-primary text-[11px]">Berhasil</span>
                            @elseif(in_array($wd->status, ['rejected', 'failed', 'cancelled']))
                                <span class="badge badge-danger text-[11px]">Gagal</span>
                                @if($wd->failure_reason)
                                    <p class="text-[10px] text-rose-500 mt-0.5 max-w-[180px] truncate" title="{{ $wd->failure_reason }}">{{ $wd->failure_reason }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="text-right">
                            <button type="button"
                                class="btn btn-secondary text-xs h-7 px-2 gap-1 align-middle"
                                title="Lihat detail transaksi"
                                data-withdrawal="{{ json_encode([
                                    'id'            => $wd->id,
                                    'courier'       => $wd->courier->user->name ?? '-',
                                    'phone'         => $wd->courier->user->phone ?? null,
                                    'amount'        => (float) $wd->amount,
                                    'fee'           => (float) $wd->fee,
                                    'net_amount'    => (float) $wd->net_amount,
                                    'provider'      => $providerName,
                                    'account'       => $wdAccountMasked,
                                    'account_name'  => $wd->account_name ?: ($wd->danaConnection?->account_name ?? '-'),
                                    'status'        => $wd->status,
                                    'reference'     => $wd->provider_reference,
                                    'idempotency'   => $wd->idempotency_key,
                                    'failure'       => $wd->failure_reason,
                                    'notes'         => $wd->admin_notes,
                                    'status_history'=> $wd->status_history ?? [],
                                    'created_at'    => \Carbon\Carbon::parse($wd->created_at)->format('d M Y, H:i:s'),
                                    'processed_at'  => $wd->processed_at ? \Carbon\Carbon::parse($wd->processed_at)->format('d M Y, H:i:s') : null,
                                    'is_terminal'   => $wd->isTerminal(),
                                    'has_api'       => $providerHasApi,
                                    'check_url'     => route('admin.ci-work.finance.withdrawal.check', $wd->id),
                                ]) }}"
                                onclick="openWithdrawalDetail(this)">Detail
                            </button>
                            @if(in_array($wd->status, ['pending', 'reserved']))
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST"
                                    onsubmit="return confirm('Setujui penarikan {{ 'Rp ' . number_format($wd->amount, 0, ',', '.') }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                    @csrf
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-primary text-xs h-7 px-2.5 gap-1">
                                        <span class="material-symbols-outlined text-[14px]">check</span>
                                        <span>Setujui</span>
                                    </button>
                                </form>
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST"
                                    onsubmit="return confirm('Tolak penarikan {{ 'Rp ' . number_format($wd->amount, 0, ',', '.') }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                    @csrf
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1 text-rose-600 hover:bg-rose-50">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                        <span>Tolak</span>
                                    </button>
                                </form>
                            @elseif($wd->status === 'approved' && !$providerHasApi)
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST"
                                    onsubmit="return confirm('Tandai penarikan {{ 'Rp ' . number_format($wd->amount, 0, ',', '.') }} telah selesai ditransfer?')">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1" title="Tandai Selesai">
                                        <span class="material-symbols-outlined text-[14px] text-emerald-600">task_alt</span>
                                        <span>Tandai Selesai</span>
                                    </button>
                                </form>
                            @elseif($providerHasApi && !$wd->isTerminal())
                                <form action="{{ route('admin.ci-work.finance.withdrawal.check', $wd->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1"
                                        title="Verifikasi status resmi transaksi via API {{ $providerName }}. Hasil API-lah yang memfinalkan status.">
                                        <span class="material-symbols-outlined text-[14px] {{ $providerName === 'DANA' ? 'text-[#118EEA]' : 'text-[#4c3494]' }}">sync</span>
                                        <span>Cek Status {{ $providerName }}</span>
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    @if($withdrawals->total() === 0)
                    <tr class="wd-row" data-status="pending" data-empty="1">
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">pending_actions</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada permintaan penarikan DANA</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Detail Penarikan Kurir -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="withdrawalDetailModal" onclick="if(event.target === this) closeWithdrawalDetail()">
    <div class="card w-full max-w-lg overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">receipt_long</span>
                <h4 class="text-sm font-bold text-slate-900">Detail Penarikan</h4>
            </div>
            <button onclick="closeWithdrawalDetail()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5" id="withdrawalDetailBody"></div>
        <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <div id="withdrawalDetailCheck"></div>
            <button onclick="closeWithdrawalDetail()" type="button" class="btn btn-primary text-xs h-8 px-3.5">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal: Panduan Persetujuan -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="guideModal" onclick="if(event.target === this) closeGuideModal()">
    <div class="card w-full max-w-md overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-blue-600">help</span>
                <h4 class="text-sm font-bold text-slate-900">Panduan Persetujuan Penarikan DANA</h4>
            </div>
            <button onclick="closeGuideModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5 space-y-2.5 text-xs text-slate-600">

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">1. Cek Status Awal</span>
                <p class="text-slate-500 leading-relaxed">Pastikan status penarikan saat ini adalah <strong>Menunggu</strong> atau <strong>Diproses</strong>. Jika sudah <strong>Berhasil</strong> atau <strong>Ditolak</strong>, tindakan manual tidak diperlukan.</p>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">2. Gunakan Tombol Cek Status API</span>
                <p class="text-slate-500 leading-relaxed">Klik tombol <strong>Cek Status {{ $providerName }}</strong> di setiap baris transaksi. Sistem akan memanggil API resmi DANA untuk mendapatkan status terverifikasi.</p>
                <p class="text-slate-500 leading-relaxed">Hasil dari API adalah satu-satunya penentu akhir apakah penarikan <strong>Berhasil</strong> atau <strong>Gagal</strong>.</p>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">3. Penjelasan Status</span>
                <ul class="text-slate-500 space-y-1 text-xs">
                    <li><strong>Berhasil:</strong> Dana sudah diterima di akun DANA kurir. Saldo bisa ditarik lagi.</li>
                    <li><strong>Gagal:</strong> Transaksi tidak berhasil. Saldo yang tertahan akan dikembalikan ke dompet kurir.</li>
                    <li><strong>Ditolak:</strong> Admin memilih menolak penarikan. Saldo tertahan dikembalikan otomatis.</li>
                    <li><strong>Dibatalkan:</strong> Proses penarikan dibatalkan sebelum dikirim ke DANA.</li>
                </ul>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">4. Proteksi Double Withdrawal</span>
                <p class="text-slate-500 leading-relaxed">Setiap permintaan penarikan menggunakan <strong>idempotency key</strong>. Jika ada permintaan ganda, sistem akan menolak yang kedua dan mencegah saldo terpotong dua kali.</p>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">5. Komisi & Biaya</span>
                <p class="text-slate-500 leading-relaxed">Biaya admin tersesuaikan dengan konfigurasi di panel Settings DANA. Jika biaya nol, platform menanggung biaya transfer.</p>
            </div>

        </div>
        <div class="flex items-center justify-end px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeGuideModal()" type="button" class="btn btn-secondary text-xs h-8 px-3">Tutup</button>
        </div>
    </div>
</div>

<script>
function updateDanaTable() {
    const statusFilter = document.querySelector('input[name="statusFilter"]:checked')?.value ?? 'all';
    const searchTerm = document.getElementById('danaSearch')?.value ?? '';

    // Build URL with filters
    let url = '{{ route("finance.dana-transactions") }}?status=' + encodeURIComponent(statusFilter);

    if (searchTerm.trim()) {
        url += '&search=' + encodeURIComponent(searchTerm);
    }

    // Reload table via AJAX or just reload page
    window.location.href = url;
}

// Simple init - mark active status
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="statusFilter"]');
    radios.forEach(radio => {
        if (radio.value === '{{ $statusFilter ?? "all" }}') {
            radio.checked = true;
        }
    });
});

function rupiah(n) {
    return 'Rp ' + Number(n ?? 0).toLocaleString('id-ID');
}

// Detail penarikan — dibaca dari atribut data baris (tanpa data fiktif).
// Riwayat perubahan status (status_history) ikut ditampilkan.
function openWithdrawalDetail(btn) {
    const raw = btn.getAttribute('data-withdrawal');
    if (!raw) return;

    let w;
    try {
        w = JSON.parse(raw);
    } catch (e) {
        return;
    }

    const statusLabel = {
        pending: 'Menunggu',
        reserved: 'Dana ditahan',
        processing: 'Diproses',
        approved: 'Disetujui',
        completed: 'Berhasil',
        success: 'Berhasil',
        rejected: 'Ditolak',
        failed: 'Gagal',
        cancelled: 'Dibatalkan',
    }[w.status] || w.status;

    const statusClass = ['completed', 'success'].includes(w.status)
        ? 'badge-primary'
        : (['rejected', 'failed', 'cancelled'].includes(w.status) ? 'badge-danger'
            : (w.status === 'processing' || w.status === 'approved' ? 'badge-info' : 'badge-warning'));

    const rows = [
        ['Kurir', w.courier + (w.phone ? ' • ' + w.phone : '')],
        ['Metode', w.provider],
        ['No. HP DANA tujuan', w.account],
        ['Nama penerima', w.account_name],
        ['Nominal penarikan', rupiah(w.amount)],
        ['Biaya administrasi', rupiah(w.fee)],
        ['Total dana diterima', rupiah(w.net_amount)],
        ['Kode idempotency', w.idempotency || '-'],
        ['Referensi iPaymu', w.reference || 'belum ada'],
        ['Diajukan', w.created_at],
        ['Diproses', w.processed_at || '-'],
    ];

    if (w.failure) rows.push(['Alasan gagal', w.failure]);
    if (w.notes) rows.push(['Catatan admin', w.notes]);

    // Riwayat perubahan status (terbaru di bawah → tampilkan kronologis).
    let historyHtml = '';
    const history = Array.isArray(w.status_history) ? w.status_history : [];
    if (history.length > 0) {
        historyHtml = `
            <div class="mt-3 pt-3 border-t border-slate-100">
                <p class="text-[11px] font-semibold text-slate-700 mb-2">Riwayat Status</p>
                <ol class="space-y-1.5">
                    ${history.map(h => `
                        <li class="flex items-start gap-2 text-[11px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300 mt-1.5 shrink-0"></span>
                            <span class="text-slate-700 font-medium">${statusLabel[h.status] || h.status}</span>
                            ${h.note ? `<span class="text-slate-400">— ${h.note}</span>` : ''}
                            <span class="text-slate-400 ml-auto whitespace-nowrap font-mono">${h.at || ''}</span>
                        </li>`).join('')}
                </ol>
            </div>`;
    } else {
        historyHtml = `
            <div class="mt-3 pt-3 border-t border-slate-100">
                <p class="text-[11px] text-slate-400">Belum ada riwayat perubahan status tercatat.</p>
            </div>`;
    }

    document.getElementById('withdrawalDetailBody').innerHTML = `
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
            <div>
                <p class="text-[11px] text-slate-500">Penarikan #${w.id}</p>
                <p class="text-lg font-bold text-slate-900 font-mono">${rupiah(w.amount)}</p>
            </div>
            <span class="badge ${statusClass} text-[11px]">${statusLabel}</span>
        </div>
        <dl class="space-y-1.5 text-xs">
            ${rows.map(([k, v]) => `
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500 shrink-0">${k}</dt>
                    <dd class="text-slate-800 font-medium text-right break-all">${v}</dd>
                </div>`).join('')}
        </dl>${historyHtml}`;

    // Tombol verifikasi resmi hanya untuk transaksi belum final.
    const checkBox = document.getElementById('withdrawalDetailCheck');
    if (w.has_api && !w.is_terminal && w.check_url) {
        checkBox.innerHTML = `
            <form action="${w.check_url}" method="POST">
                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                <button type="submit" class="btn btn-secondary text-xs h-8 px-3 gap-1">
                    <span class="material-symbols-outlined text-[14px] text-[#118EEA]">sync</span>
                    <span>Cek Status ${w.provider}</span>
                </button>
            </form>`;
    } else {
        checkBox.innerHTML = '';
    }

    const m = document.getElementById('withdrawalDetailModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeWithdrawalDetail() {
    const m = document.getElementById('withdrawalDetailModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openGuideModal() {
    const m = document.getElementById('guideModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeGuideModal() {
    const m = document.getElementById('guideModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        ['guideModal', 'withdrawalDetailModal'].forEach(id => {
            const m = document.getElementById(id);
            if (m && !m.classList.contains('hidden')) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        });
    }
});
</script>
@endsection