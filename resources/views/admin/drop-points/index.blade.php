@extends('layouts.admin')

@section('title', 'Manajemen Drop Point')

@section('content')
<div class="flex flex-col w-full gap-space-lg">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">warehouse</span>
                <span>Titik Distribusi</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Drop Point</h1>
            <p class="font-body-md text-body-md text-secondary">Kelola lokasi drop point dan titik distribusi paket</p>
        </div>
        <a href="{{ route('admin.drop-points.create') }}" class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            <span>Tambah Drop Point</span>
        </a>
    </div>

    @if($dropPoints->count())
    <!-- Drop Points Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-space-md">
        @foreach($dropPoints as $dp)
        @php $isActive = $dp->is_active; @endphp
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-all {{ $isActive ? 'border-l-4 border-l-primary' : 'border-l-4 border-l-gray-300' }}">
            <!-- Card Body -->
            <div class="p-space-lg flex flex-col gap-space-sm">
                <div class="flex items-start justify-between gap-space-sm">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold leading-tight">{{ $dp->name }}</h3>
                    <span class="inline-flex items-center gap-space-2xs px-space-xs py-space-2xs rounded-full font-label-sm text-label-sm font-semibold {{ $isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-surface-container text-secondary' }}">
                        <span class="material-symbols-outlined text-[14px]">{{ $isActive ? 'check_circle' : 'pause_circle' }}</span>
                        {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                @if($dp->address)
                <div class="flex items-start gap-space-xs text-body-sm text-secondary">
                    <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">pin_drop</span>
                    <span>{{ $dp->address }}</span>
                </div>
                @endif

                @if($dp->phone)
                <div class="flex items-center gap-space-xs text-body-sm text-secondary">
                    <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">call</span>
                    <span>{{ $dp->phone }}</span>
                </div>
                @endif

                @if($dp->schedule)
                <div class="flex items-center gap-space-xs text-body-sm text-secondary">
                    <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">schedule</span>
                    <span>{{ $dp->schedule }}</span>
                </div>
                @endif

                <div class="flex items-center gap-space-xs text-body-sm text-secondary">
                    <div class="flex items-center">
                        @for($i = 1; $i <= 5; $i++)
                        <span class="material-symbols-outlined text-[16px] {{ $i > round($dp->rating) ? 'text-gray-300' : 'text-amber-400' }}" {{ $i <= round($dp->rating) ? "style=font-variation-settings:'FILL' 1" : '' }}>star</span>
                        @endfor
                    </div>
                    <span class="font-semibold text-on-surface">{{ number_format($dp->rating, 1) }}</span>
                </div>
            </div>

            <!-- Card Actions -->
            <div class="px-space-lg py-space-sm bg-surface flex items-center gap-space-xs border-t border-surface-container-high">
                <form action="{{ route('admin.drop-points.toggle-active', $dp->id) }}" method="POST" class="inline-block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-lg font-label-sm text-label-sm font-semibold transition-colors {{ $isActive ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }}">
                        <span class="material-symbols-outlined text-[16px]">{{ $isActive ? 'toggle_on' : 'toggle_off' }}</span>
                        {{ $isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </form>
                <a href="{{ route('admin.drop-points.edit', $dp->id) }}" class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-lg font-label-sm text-label-sm font-semibold bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">edit</span>
                    Edit
                </a>
                <form action="{{ route('admin.drop-points.destroy', $dp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus drop point ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-lg font-label-sm text-label-sm font-semibold bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                        Hapus
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-space-xs">
        {{ $dropPoints->links() }}
    </div>
    @else
    <!-- Empty State -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm py-space-2xl px-space-md flex flex-col items-center justify-center text-center">
        <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
            <span class="material-symbols-outlined text-[32px] text-secondary">warehouse</span>
        </div>
        <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Belum ada drop point</h3>
        <p class="font-body-sm text-body-sm text-secondary max-w-sm mt-space-xs">Mulai dengan menambahkan drop point baru untuk mendistribusikan paket.</p>
        <a href="{{ route('admin.drop-points.create') }}" class="mt-space-lg inline-flex items-center gap-space-xs px-space-md py-space-xs bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-semibold rounded-lg shadow-sm transition-all">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Drop Point
        </a>
    </div>
    @endif
</div>
@endsection
