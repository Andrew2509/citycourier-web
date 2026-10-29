@php
    $statusChip = function (string $status) {
        return match ($status) {
            'assigned'   => ['b' => 'bg-amber-50 text-amber-700 border border-amber-200/60', 'd' => 'bg-amber-500', 'pulse' => false],
            'picking_up' => ['b' => 'bg-blue-50 text-blue-700 border border-blue-200/60', 'd' => 'bg-blue-500', 'pulse' => false],
            'picked_up'  => ['b' => 'bg-indigo-50 text-indigo-700 border border-indigo-200/60', 'd' => 'bg-indigo-500', 'pulse' => false],
            'delivering' => ['b' => 'bg-orange-50 text-orange-700 border border-orange-200/60', 'd' => 'bg-orange-500', 'pulse' => true],
            default      => ['b' => 'bg-slate-100 text-slate-600 border border-slate-200/60', 'd' => 'bg-slate-400', 'pulse' => false],
        };
    };
    $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

{{-- ═══ Live Task Card ═══ --}}
<div class="card p-4 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px]">near_me</span>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Tugas Aktif Terkini</h2>
                <span id="stat-active-label" class="text-xs text-slate-500">{{ $stats['active_tasks'] ?? 0 }} Penugasan live terhubung satelit GPS</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono text-slate-600 bg-slate-100">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live Tracking
            </span>
            <a class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700" href="{{ route('admin.ci-work.tasks') }}">
                Semua Tugas
                <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
            </a>
        </div>
    </div>

    {{-- ═══ Featured Active Task (kartu utama) ═══ --}}
    @if ($featured)
    @php $sc = $statusChip($featured['status']); @endphp
    <div
        class="border border-slate-200 rounded-xl hover:border-slate-300 transition-colors flex flex-col overflow-hidden bg-white"
        data-task
        data-order-id="{{ $featured['id'] }}"
        data-tracking="{{ $featured['tracking'] }}"
        data-status="{{ $featured['status'] }}"
        data-lat="{{ $featured['courier_lat'] ?? '' }}"
        data-lng="{{ $featured['courier_lng'] ?? '' }}"
        data-destlat="{{ $featured['dest_lat'] ?? '' }}"
        data-destlng="{{ $featured['dest_lng'] ?? '' }}"
        data-accuracy="{{ $featured['accuracy'] ?? '' }}"
        data-last-seen="{{ $featured['last_seen'] ?? '' }}"
        data-name="{{ $featured['courier_name'] }}"
        data-vehicle="{{ $featured['vehicle'] }}"
        data-plate="{{ $featured['plate'] ?? '-' }}"
        data-address="{{ $featured['dest_addr'] }}"
        data-wa="{{ $featured['wa'] ?? '' }}"
        data-phone="{{ $featured['phone'] }}"
    >
        <div class="p-4 space-y-3.5">
            {{-- Top Row: Resi & Status --}}
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-slate-100 rounded font-mono font-bold text-slate-800 text-xs">
                        #{{ $featured['tracking'] }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $sc['b'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $sc['d'] }} {{ $sc['pulse'] ? 'animate-pulse' : '' }}"></span>
                        {{ $featured['status_label'] }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="material-symbols-outlined text-[15px] text-slate-400">schedule</span>
                    <span>Estimasi: <strong class="text-slate-900 font-mono">{{ $featured['eta'] ? $featured['eta'] . ' Menit' : '—' }}</strong>
                        @if($featured['distance'] !== null) ({{ $featured['distance'] }} km sisa)@endif
                    </span>
                </div>
            </div>

            {{-- Mid Row: Driver + Route --}}
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                <div class="md:col-span-5 flex items-center gap-3">
                    <div class="relative w-10 h-10 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs shrink-0">
                        <span>{{ $featured['courier_initial'] }}</span>
                        @if($featured['courier_photo'])
                            <img class="w-10 h-10 rounded-full object-cover absolute inset-0" src="{{ $featured['courier_photo'] }}" alt="{{ $featured['courier_name'] }}" onerror="this.style.display='none'" loading="lazy" />
                        @endif
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-white"></span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-900 truncate">{{ $featured['courier_name'] }}</div>
                        <div class="text-[11px] text-slate-500 font-mono truncate">{{ $featured['vehicle'] }} • {{ $featured['plate'] ?? '-' }}</div>
                        @if($featured['rating_count'] > 0)
                            <div class="text-[11px] text-emerald-600 font-medium">★ {{ $featured['rating_avg'] }} ({{ $featured['rating_count'] }} review)</div>
                        @else
                            <div class="text-[11px] text-emerald-600 font-medium">Kurir terverifikasi ✓</div>
                        @endif
                    </div>
                </div>

                <div class="md:col-span-7 bg-slate-50 rounded-lg p-2.5 border border-slate-100 text-xs space-y-1.5">
                    <div class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[15px] text-slate-400 mt-0.5 shrink-0">upload_2</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jemput:</span>
                            <p class="text-slate-800 truncate font-medium">{{ Str::limit($featured['pickup_addr'] ?? '-', 50) }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 pt-1 border-t border-slate-200/60">
                        <span class="material-symbols-outlined text-[15px] text-primary-600 mt-0.5 shrink-0">location_on</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] uppercase font-semibold text-primary-700 block">Tujuan:</span>
                            <p class="text-slate-900 truncate font-semibold">{{ Str::limit($featured['dest_addr'] ?? '-', 50) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bottom Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="badge badge-neutral text-[11px] font-medium">
                        {{ $featured['desc'] ? $featured['desc'] . ' (' . $featured['weight'] . ' kg)' : $featured['weight'] . ' kg' }}
                    </span>
                    <span class="badge badge-neutral text-[11px] font-medium">
                        {{ $featured['price'] > 0 ? 'COD ' . $fmtRp($featured['price']) : 'Non-COD' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button data-act="track" class="btn btn-secondary text-xs h-8 px-3 gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-500">near_me</span>
                        <span>Lacak Live</span>
                    </button>
                    @if($featured['wa'])
                        <a href="https://wa.me/{{ $featured['wa'] }}" target="_blank" rel="noopener"
                           class="btn btn-primary text-xs h-8 px-3 gap-1 shadow-sm">
                            <span class="material-symbols-outlined text-[15px]">chat</span>
                            <span>Chat Kurir</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Live Route Map Preview Strip --}}
        @if($featured['courier_lat'] && $featured['courier_lng'])
        <div class="relative overflow-hidden border-t border-slate-200">
            <div id="featuredMapStrip" class="w-full h-36"
                 data-lat="{{ $featured['courier_lat'] }}" data-lng="{{ $featured['courier_lng'] }}"
                 data-destlat="{{ $featured['dest_lat'] ?? '' }}" data-destlng="{{ $featured['dest_lng'] ?? '' }}"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent pointer-events-none"></div>
            <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between text-white pointer-events-none text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="font-medium drop-shadow-sm text-[11px]">Satelit GPS Aktif (Akurasi {{ $featured['accuracy'] !== null ? $featured['accuracy'] . ' m' : '—' }})</span>
                </div>
                <span class="font-mono text-[10px] bg-black/40 px-2 py-0.5 rounded backdrop-blur">
                    Update: {{ $featured['last_seen'] !== null ? $featured['last_seen'] . ' dtk lalu' : 'Live' }}
                </span>
            </div>
        </div>
        @endif
    </div>
    @else
    <div class="p-8 text-center rounded-xl border border-dashed border-slate-200">
        <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">near_me_disabled</span>
        <p class="text-xs font-semibold text-slate-700">Tidak ada pengiriman aktif saat ini</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Saat kurir menerima tugas, pemantauan otomatis akan aktif di sini.</p>
    </div>
    @endif

    {{-- ═══ Additional Active Tasks (bila ada lebih dari satu) ═══ --}}
    @foreach($extraTasks as $t)
    @php $sc2 = $statusChip($t['status']); @endphp
    <div class="p-3 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors flex flex-wrap items-center justify-between gap-2 text-xs"
         data-task
         data-order-id="{{ $t['id'] }}"
         data-tracking="{{ $t['tracking'] }}"
         data-status="{{ $t['status'] }}"
         data-lat="{{ $t['courier_lat'] ?? '' }}"
         data-lng="{{ $t['courier_lng'] ?? '' }}"
         data-destlat="{{ $t['dest_lat'] ?? '' }}"
         data-destlng="{{ $t['dest_lng'] ?? '' }}"
         data-accuracy="{{ $t['accuracy'] ?? '' }}"
         data-last-seen="{{ $t['last_seen'] ?? '' }}"
         data-name="{{ $t['courier_name'] }}"
         data-vehicle="{{ $t['vehicle'] }}"
         data-plate="{{ $t['plate'] ?? '-' }}"
         data-address="{{ $t['dest_addr'] }}"
         data-wa="{{ $t['wa'] ?? '' }}"
         data-phone="{{ $t['phone'] }}"
    >
        <div class="flex items-center gap-2">
            <span class="font-mono font-bold text-slate-800">#{{ $t['tracking'] }}</span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $sc2['b'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $sc2['d'] }} {{ $sc2['pulse'] ? 'animate-pulse' : '' }}"></span>{{ $t['status_label'] }}
            </span>
        </div>
        <div class="flex items-center gap-1.5 min-w-[200px] flex-1">
            <span class="material-symbols-outlined text-[15px] text-slate-400">pin_drop</span>
            <span class="text-slate-700 truncate font-medium">{{ Str::limit($t['dest_addr'] ?? '-', 45) }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="font-mono text-[11px] text-slate-500">
                @if($t['distance'] !== null){{ $t['distance'] }} km • @endif@if($t['eta']){{ $t['eta'] }} mnt@endif
            </span>
            <button data-act="track" class="btn btn-secondary text-xs h-7 px-2.5 gap-1">
                <span class="material-symbols-outlined text-[14px] text-slate-500">near_me</span> Lacak
            </button>
            @if($t['wa'])
                <a href="https://wa.me/{{ $t['wa'] }}" target="_blank" rel="noopener" class="btn btn-primary text-xs h-7 px-2.5 gap-1">
                    <span class="material-symbols-outlined text-[14px]">chat</span> WA
                </a>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- ═══ Antrean Penugasan Otomatis (Pending Dispatch Queue) ═══ --}}
<div class="card p-4 space-y-3.5">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-slate-500">queue</span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Antrean Penugasan Otomatis</h2>
                <span class="text-xs text-slate-500">Pencocokan radius kurir terdekat sistem</span>
            </div>
        </div>
        @if(($stats['pending_count'] ?? 0) > 0)
            <span class="badge badge-warning text-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>{{ $stats['pending_count'] }} Dalam Antrean
            </span>
        @else
            <span class="badge badge-success text-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Algoritma Siaga
            </span>
        @endif
    </div>

    @forelse($queue as $q)
    <div class="p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors space-y-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100 text-xs">
            <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-slate-900">#{{ $q['tracking'] }}</span>
                <span class="badge badge-warning text-[10px]">Menunggu Kurir</span>
                <span class="text-slate-400 font-mono text-[11px]">{{ $q['created_at'] }}</span>
            </div>
            <button data-act="assign" data-id="{{ $q['id'] }}"
                    class="btn btn-primary text-xs h-7 px-3 gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[14px]">swap_calls</span>
                <span>Tetapkan Kurir</span>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
            <div class="flex items-start gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-slate-400 mt-0.5 shrink-0">upload_2</span>
                <div class="min-w-0 flex-1">
                    <span class="text-[10px] uppercase font-semibold text-slate-400 block">Jemput:</span>
                    <p class="text-slate-700 truncate">{{ Str::limit($q['pickup_addr'] ?? '-', 42) }}</p>
                </div>
            </div>
            <div class="flex items-start gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-primary-600 mt-0.5 shrink-0">location_on</span>
                <div class="min-w-0 flex-1">
                    <span class="text-[10px] uppercase font-semibold text-primary-700 block">Tujuan:</span>
                    <p class="text-slate-900 font-medium truncate">{{ Str::limit($q['dest_addr'] ?? '-', 42) }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 pt-1 border-t border-slate-100 text-[11px]">
            <span class="text-slate-500 font-medium">{{ $q['desc'] ? $q['desc'] . ' (' . $q['weight'] . ' kg)' : $q['weight'] . ' kg' }}</span>
            <span class="text-slate-300">•</span>
            <span class="font-mono font-semibold text-slate-800">{{ $fmtRp($q['price']) }}</span>
        </div>
    </div>
    @empty
    <div class="p-6 text-center rounded-lg border border-dashed border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3 text-left">
            <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">check</span>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-800">Semua order telah dialokasikan</p>
                <p class="text-[11px] text-slate-400">Tidak ada order tertunda dalam pool dispatching.</p>
            </div>
        </div>
        <button data-act="pool-check" class="btn btn-secondary text-xs h-8 px-3 shrink-0">
            Cek Pool Pesanan
        </button>
    </div>
    @endforelse
</div>
