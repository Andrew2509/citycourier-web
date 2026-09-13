@php
    $statusChip = function (string $status) {
        return match ($status) {
            'assigned'   => ['b' => 'bg-amber-100 text-amber-900', 'd' => 'bg-amber-600', 'pulse' => false],
            'picking_up' => ['b' => 'bg-blue-50 text-blue-700', 'd' => 'bg-blue-600', 'pulse' => false],
            'delivering' => ['b' => 'bg-amber-50 text-amber-800', 'd' => 'bg-amber-600', 'pulse' => true],
            default      => ['b' => 'bg-surface-container text-secondary', 'd' => 'bg-secondary', 'pulse' => false],
        };
    };
    $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

{{-- ═══ Live Task Card ═══ --}}
<div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col mb-space-xl">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm pb-space-md">
        <div class="flex items-center gap-space-sm">
            <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                <span class="material-symbols-outlined text-[18px]">radiology</span>
            </div>
            <div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tugas Aktif Terkini</h2>
                <span id="stat-active-label" class="font-label-sm text-label-sm text-secondary">{{ $stats['active_tasks'] ?? 0 }} Penugasan live terhubung satelit GPS</span>
            </div>
        </div>
        <div class="flex items-center gap-space-xs">
            <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-md bg-surface-container-low text-secondary font-data-mono text-[12px]">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live Tracking
            </span>
            <a class="inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:text-surface-tint font-semibold pl-space-xs" href="{{ route('admin.ci-work.tasks') }}">
                Lihat Semua
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>

{{-- ═══ Featured Active Task (kartu utama) ═══ --}}
@if ($featured)
@php $sc = $statusChip($featured['status']); @endphp
<div
    class="bg-surface-container-low rounded-xl shadow-sm hover:bg-surface-container transition-colors flex flex-col gap-space-md mb-space-md overflow-hidden"
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
    <div class="p-space-md flex flex-col gap-space-md">
        {{-- Top Row: Resi & Status --}}
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-sm">
                <div class="px-space-xs py-space-2xs bg-surface-container-highest rounded font-data-mono font-bold text-on-surface text-body-sm">
                    #{{ $featured['tracking'] }}
                </div>
                <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full {{ $sc['b'] }} font-label-sm text-label-sm font-bold">
                    <span class="w-2 h-2 rounded-full {{ $sc['d'] }} {{ $sc['pulse'] ? 'animate-pulse' : '' }}"></span>
                    {{ $featured['status_label'] }}
                </span>
            </div>
            <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
                <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                <span>Estimasi: <strong class="text-on-surface font-data-mono">{{ $featured['eta'] ? $featured['eta'] . ' Menit' : '—' }}</strong>
                    @if($featured['distance'] !== null) ({{ $featured['distance'] }} km tersisa)@endif
                </span>
            </div>
        </div>

        {{-- Mid Row: Driver + Route --}}
        <div class="grid grid-cols-1 md:grid-cols-12 gap-space-md items-center py-space-xs">
            <div class="md:col-span-4 flex items-center gap-space-sm">
                <div class="relative w-11 h-11 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                    <span class="absolute inset-0 flex items-center justify-center font-bold">{{ $featured['courier_initial'] }}</span>
                    @if($featured['courier_photo'])
                        <img class="w-11 h-11 rounded-full object-cover shadow-sm absolute inset-0" src="{{ $featured['courier_photo'] }}" alt="{{ $featured['courier_name'] }}" onerror="this.style.display='none'" loading="lazy" />
                    @endif
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white"></span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="font-body-md text-body-md text-on-surface font-semibold leading-tight truncate">{{ $featured['courier_name'] }}</span>
                    <span class="font-label-sm text-label-sm text-secondary font-data-mono truncate">{{ $featured['vehicle'] }} • Plat {{ $featured['plate'] ?? '-' }}</span>
                    @if($featured['rating_count'] > 0)
                        <span class="font-label-sm text-label-sm text-emerald-700 font-medium mt-0.5">Rating {{ $featured['rating_avg'] }} ★ ({{ $featured['rating_count'] }} Pesanan)</span>
                    @else
                        <span class="font-label-sm text-label-sm text-emerald-700 font-medium mt-0.5">Kurir terverifikasi ✓</span>
                    @endif
                </div>
            </div>

            <div class="md:col-span-8 flex flex-col justify-center gap-space-xs bg-surface-container-lowest p-space-sm rounded-lg shadow-sm">
                <div class="flex items-start gap-space-xs">
                    <span class="material-symbols-outlined text-[18px] text-secondary mt-0.5">store</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">
                                {{ $featured['status'] === 'delivering' ? 'Penjemputan (Selesai)' : 'Penjemputan' }}
                            </span>
                            @if($featured['pickup_time'])
                                <span class="font-label-sm text-label-sm text-secondary font-data-mono">{{ $featured['pickup_time'] }} WIB</span>
                            @endif
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface font-medium truncate">{{ Str::limit($featured['pickup_addr'] ?? '-', 45) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-space-xs pl-2">
                    <div class="h-4 w-0.5 bg-primary"></div>
                    <div class="flex items-center gap-1 text-[11px] font-data-mono text-primary font-bold">
                        <span class="material-symbols-outlined text-[14px]">navigation</span>
                        @if($featured['distance'] !== null)
                            Sedang melintas • {{ $featured['distance'] }} km ke tujuan
                        @else
                            Menuju lokasi tujuan
                        @endif
                    </div>
                </div>
                <div class="flex items-start gap-space-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">pin_drop</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-label-sm text-label-sm text-primary uppercase font-bold">Tujuan Antar</span>
                            <span class="font-label-sm text-label-sm text-secondary font-data-mono">Target: {{ $featured['target'] ? $featured['target'] . ' WIB' : '—' }}</span>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate">{{ Str::limit($featured['dest_addr'] ?? '-', 50) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom Actions --}}
        <div class="flex flex-wrap items-center justify-between gap-space-sm pt-space-xs">
            <div class="flex items-center gap-space-xs">
                <span class="font-label-sm text-label-sm text-secondary">Tipe Paket:</span>
                <span class="px-space-xs py-space-2xs rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm font-semibold max-w-[220px] truncate">{{ $featured['desc'] ? $featured['desc'] . ' (' . $featured['weight'] . ' kg)' : $featured['weight'] . ' kg' }}</span>
                <span class="px-space-xs py-space-2xs rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm font-semibold">{{ $featured['price'] > 0 ? 'COD ' . $fmtRp($featured['price']) : 'Non-COD' }}</span>
            </div>
            <div class="flex items-center gap-space-xs">
                <button data-act="track" class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-surface-container-highest text-on-surface hover:bg-surface-container-high transition-colors font-label-md text-label-md font-semibold">
                    <span class="material-symbols-outlined text-[16px] text-primary">near_me</span>
                    <span>Lacak Live</span>
                </button>
                @if($featured['wa'])
                    <a href="https://wa.me/{{ $featured['wa'] }}" target="_blank" rel="noopener"
                       class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-primary-fixed text-on-primary-fixed-variant hover:bg-primary hover:text-on-primary transition-colors font-label-md text-label-md font-semibold">
                        <span class="material-symbols-outlined text-[16px]">call</span>
                        <span>Hubungi Kurir</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Live Route Map Preview Strip --}}
    @if($featured['courier_lat'] && $featured['courier_lng'])
    <div class="relative overflow-hidden">
        <div id="featuredMapStrip" class="w-full h-44"
             data-lat="{{ $featured['courier_lat'] }}" data-lng="{{ $featured['courier_lng'] }}"
             data-destlat="{{ $featured['dest_lat'] ?? '' }}" data-destlng="{{ $featured['dest_lng'] ?? '' }}"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-white pointer-events-none">
            <div class="flex items-center gap-space-xs">
                <div class="w-2.5 h-2.5 rounded-full bg-primary-container animate-ping"></div>
                <span class="font-label-md text-label-md font-semibold drop-shadow-sm">Tracking Real-Time Aktif (Akurasi GPS {{ $featured['accuracy'] !== null ? $featured['accuracy'] . ' Meter' : '—' }})</span>
            </div>
            <span class="font-data-mono text-[11px] bg-black/40 px-2 py-0.5 rounded backdrop-blur">Posisi Terakhir: {{ $featured['last_seen'] !== null ? $featured['last_seen'] . ' detik lalu' : 'Live' }}</span>
        </div>
    </div>
    @endif
</div>
@else
<div class="bg-surface-container-low rounded-xl p-space-xl flex flex-col items-center justify-center text-center mb-space-md">
    <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-sm">
        <span class="material-symbols-outlined text-[24px]">radiology</span>
    </div>
    <p class="font-body-md text-body-md text-on-surface font-semibold">Tidak ada tugas aktif saat ini</p>
    <p class="font-body-sm text-body-sm text-secondary">Saat kurir menerima pesanan, tugas akan otomatis tampil di sini.</p>
</div>
@endif

{{-- ═══ Additional Active Tasks (bila lebih dari satu) ═══ --}}
@foreach($extraTasks as $t)
@php $sc2 = $statusChip($t['status']); @endphp
<div class="bg-surface-container-low rounded-xl p-space-md hover:bg-surface-container transition-colors flex flex-wrap items-center gap-space-md"
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
    <div class="px-space-xs py-space-2xs bg-surface-container-highest rounded font-data-mono font-bold text-on-surface text-body-sm">#{{ $t['tracking'] }}</div>
    <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full {{ $sc2['b'] }} font-label-sm text-label-sm font-bold">
        <span class="w-2 h-2 rounded-full {{ $sc2['d'] }} {{ $sc2['pulse'] ? 'animate-pulse' : '' }}"></span>{{ $t['status_label'] }}
    </span>
    <div class="flex items-center gap-space-sm flex-1 min-w-[180px]">
        <span class="material-symbols-outlined text-[18px] text-primary">pin_drop</span>
        <span class="font-body-sm text-body-sm text-on-surface font-medium truncate">{{ Str::limit($t['dest_addr'] ?? '-', 45) }}</span>
    </div>
    <span class="font-data-mono text-[12px] text-primary font-bold">
        @if($t['distance'] !== null){{ $t['distance'] }} km • @endif@if($t['eta']){{ $t['eta'] }} mnt@endif
    </span>
    <div class="flex items-center gap-space-xs">
        <button data-act="track" class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-surface-container-highest text-on-surface hover:bg-surface-container-high transition-colors font-label-md text-label-md font-semibold">
            <span class="material-symbols-outlined text-[16px] text-primary">near_me</span> Lacak
        </button>
        @if($t['wa'])
            <a href="https://wa.me/{{ $t['wa'] }}" target="_blank" rel="noopener" class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-primary-fixed text-on-primary-fixed-variant hover:bg-primary hover:text-on-primary transition-colors font-label-md text-label-md font-semibold">
                <span class="material-symbols-outlined text-[16px]">call</span> WA
            </a>
        @endif
    </div>
</div>
@endforeach
</div>
{{-- end Live Task Card ═══ --}}

{{-- ═══ Antrean Penugasan Otomatis (Pending Dispatch Queue) ═══ --}}
<div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
    <div class="flex items-center justify-between mb-space-md">
        <div class="flex items-center gap-space-sm">
            <div class="w-8 h-8 rounded-lg bg-surface-container-high flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined text-[18px]">queue</span>
            </div>
            <div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Antrean Penugasan Otomatis</h2>
                <span class="font-label-sm text-label-sm text-secondary">Penyortiran algoritma pencocokan kurir terdekat</span>
            </div>
        </div>
        @if(($stats['pending_count'] ?? 0) > 0)
            <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-amber-50 text-amber-800 font-label-sm text-label-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ $stats['pending_count'] }} Order Dalam Antrean
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-800 font-label-sm text-label-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Mesin Algoritma: Siaga
            </span>
        @endif
    </div>

    @forelse($queue as $q)
    <div class="bg-surface-container-low rounded-xl p-space-md hover:bg-surface-container transition-colors flex flex-col gap-space-sm mb-space-md {{ !$loop->last ? '' : 'mb-0' }}">
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-xs">
                <div class="px-space-xs py-space-2xs bg-surface-container-highest rounded font-data-mono font-bold text-on-surface text-body-sm">#{{ $q['tracking'] }}</div>
                <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-amber-100 text-amber-900 font-label-sm text-label-sm font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Menunggu Assign
                </span>
                <span class="font-label-sm text-label-sm text-secondary font-data-mono">{{ $q['created_at'] }}</span>
            </div>
            <button data-act="assign" data-id="{{ $q['id'] }}"
                    class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-primary text-on-primary hover:bg-surface-tint transition-colors font-label-md text-label-md font-semibold">
                <span class="material-symbols-outlined text-[16px]">swap_calls</span>
                <span>Tetapkan Kurir</span>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm text-body-sm">
            <div class="flex items-start gap-space-xs">
                <span class="material-symbols-outlined text-[16px] text-secondary mt-0.5">store</span>
                <div class="min-w-0 flex-1">
                    <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Penjemputan</span>
                    <p class="font-body-sm text-body-sm text-on-surface font-medium truncate">{{ Str::limit($q['pickup_addr'] ?? '-', 42) }}</p>
                </div>
            </div>
            <div class="flex items-start gap-space-xs">
                <span class="material-symbols-outlined text-[16px] text-primary mt-0.5">pin_drop</span>
                <div class="min-w-0 flex-1">
                    <span class="font-label-sm text-label-sm text-primary uppercase font-bold">Tujuan</span>
                    <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate">{{ Str::limit($q['dest_addr'] ?? '-', 42) }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-space-xs pt-space-2xs">
            <span class="px-space-xs py-space-2xs rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm font-semibold">{{ $q['desc'] ? $q['desc'] . ' (' . $q['weight'] . ' kg)' : $q['weight'] . ' kg' }}</span>
            <span class="px-space-xs py-space-2xs rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm font-semibold">{{ $fmtRp($q['price']) }}</span>
        </div>
    </div>
    @empty
    <div class="bg-surface-container-low rounded-xl p-space-lg flex flex-col sm:flex-row items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-md">
            <div class="w-12 h-12 rounded-full bg-surface-container-lowest shadow-sm flex items-center justify-center text-primary shrink-0">
                <span class="material-symbols-outlined text-[24px]">verified</span>
            </div>
            <div>
                <p class="font-body-md text-body-md text-on-surface font-semibold">Semua antrean telah dialokasikan dengan lancar</p>
                <p class="font-body-sm text-body-sm text-secondary">Tidak ada order tertunda. Sistem akan otomatis memancarkan notifikasi jika ada pesanan baru.</p>
            </div>
        </div>
        <div class="flex items-center gap-space-xs shrink-0">
            <button data-act="pool-check" class="px-space-md py-space-xs rounded-lg bg-surface-container-highest hover:bg-surface-container-high text-on-surface font-label-md text-label-md font-semibold transition-colors">
                Cek Pool Pesanan
            </button>
        </div>
    </div>
    @endforelse
</div>