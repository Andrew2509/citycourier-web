@php
    $currentMode = $mode ?? ($metrics['mode'] ?? ($config['mode'] ?? config('payment_wallet.mode', 'sandbox')));
    $modeStyle = match ($currentMode) {
        'production' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'demo' => 'bg-purple-100 text-purple-800 border-purple-200',
        default => 'bg-amber-100 text-amber-800 border-amber-200',
    };
    $tabs = [
        ['route' => 'admin.finance.index', 'label' => 'Overview', 'icon' => 'dashboard'],
        ['route' => 'admin.finance.wallets', 'label' => 'Wallets', 'icon' => 'account_balance_wallet'],
        ['route' => 'admin.finance.topups', 'label' => 'Top-Ups', 'icon' => 'add_card'],
        ['route' => 'admin.finance.payments', 'label' => 'Order Payments', 'icon' => 'receipt_long'],
        ['route' => 'admin.finance.withdrawals', 'label' => 'Customer Withdrawals', 'icon' => 'payments'],
        ['route' => 'admin.finance.earnings', 'label' => 'Courier Earnings', 'icon' => 'work'],
        ['route' => 'admin.finance.payouts', 'label' => 'Courier Payouts', 'icon' => 'send_money'],
        ['route' => 'admin.finance.refunds', 'label' => 'Refunds', 'icon' => 'assignment_return'],
        ['route' => 'admin.finance.destinations', 'label' => 'Destinations', 'icon' => 'account_balance'],
        ['route' => 'admin.finance.reconciliation', 'label' => 'Reconciliation', 'icon' => 'balance'],
        ['route' => 'admin.finance.reports', 'label' => 'Reports', 'icon' => 'summarize'],
        ['route' => 'admin.finance.audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
        ['route' => 'admin.finance.settings', 'label' => 'Settings', 'icon' => 'tune'],
    ];
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Payment &amp; Wallet Command Center</h1>
        <p class="text-xs text-slate-500">Ledger Double-Entry Source of Truth &bull; IDR Integer Accounting</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold {{ $modeStyle }}">
            <span class="h-2 w-2 rounded-full bg-current"></span>
            MODE: {{ strtoupper($currentMode) }}
        </span>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif

<div class="mb-5 flex flex-wrap gap-1.5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <span class="cc-icon !text-[16px]">{{ $tab['icon'] }}</span>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
