@forelse($recentTasks as $task)
<div class="bg-surface-container-low rounded-xl p-space-md hover:bg-surface-container transition-colors flex flex-col gap-space-md {{ !$loop->last ? 'mb-space-md' : '' }}">
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <div class="flex items-center gap-space-sm">
            <div class="px-space-xs py-space-2xs bg-surface-container-highest rounded font-data-mono font-bold text-on-surface text-body-sm">
                #{{ $task->shipment->tracking_number ?? '-' }}
            </div>
            @php
                $taskStatusStyles = [
                    'assigned' => 'bg-amber-100 text-amber-900',
                    'picking_up' => 'bg-blue-50 text-blue-700',
                    'delivering' => 'bg-amber-50 text-amber-800',
                    'delivered' => 'bg-emerald-50 text-emerald-700',
                ];
                $taskStatusLabel = [
                    'assigned' => 'Ditugaskan',
                    'picking_up' => 'Sedang Jemput',
                    'delivering' => 'Dalam Pengantaran',
                    'delivered' => 'Selesai',
                ];
            @endphp
            <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full {{ $taskStatusStyles[$task->status] ?? 'bg-surface-container text-secondary' }} font-label-sm text-label-sm font-bold">
                <span class="w-2 h-2 rounded-full {{ $task->status === 'delivering' ? 'bg-amber-600 animate-pulse' : 'bg-current' }}"></span>
                {{ $taskStatusLabel[$task->status] ?? $task->status }}
            </span>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md items-center py-space-xs">
        <div class="flex items-center gap-space-sm">
            <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                {{ strtoupper(substr($task->courier->user->name ?? 'K', 0, 1)) }}
            </div>
            <div class="flex flex-col">
                <span class="font-body-md text-body-md text-on-surface font-semibold leading-tight">{{ $task->courier->user->name ?? '-' }}</span>
                <span class="font-label-sm text-label-sm text-secondary">{{ $task->courier->vehicle_type ?? 'Motor' }}</span>
            </div>
        </div>
        <div class="flex flex-col justify-center gap-space-xs bg-surface-container-lowest p-space-sm rounded-lg shadow-sm">
            <div class="flex items-start gap-space-xs">
                <span class="material-symbols-outlined text-[18px] text-secondary mt-0.5">store</span>
                <div class="min-w-0 flex-1">
                    <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Penjemputan</span>
                    <p class="font-body-sm text-body-sm text-on-surface font-medium truncate">{{ Str::limit($task->shipment->sender_address ?? '-', 40) }}</p>
                </div>
            </div>
            <div class="flex items-start gap-space-xs">
                <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">pin_drop</span>
                <div class="min-w-0 flex-1">
                    <span class="font-label-sm text-label-sm text-primary uppercase font-bold">Tujuan Antar</span>
                    <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate">{{ Str::limit($task->shipment->receiver_address ?? '-', 40) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@empty
<div class="bg-surface-container-low rounded-xl p-space-xl flex flex-col items-center justify-center text-center">
    <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-sm">
        <span class="material-symbols-outlined text-[24px]">check_circle</span>
    </div>
    <p class="font-body-md text-body-md text-on-surface font-semibold">Semua antrean telah dialokasikan</p>
    <p class="font-body-sm text-body-sm text-secondary">Tidak ada tugas aktif saat ini.</p>
</div>
@endforelse