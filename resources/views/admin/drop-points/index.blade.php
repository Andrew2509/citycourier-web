@extends('layouts.admin')

@section('title', 'Manajemen Drop Point')

@section('content')
<style>
    :root {
        --emerald-50: #ecfdf5;
        --emerald-100: #d1fae5;
        --emerald-200: #a7f3d0;
        --emerald-400: #34d399;
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-700: #047857;
        --emerald-800: #065f46;
        --emerald-900: #064e3b;
    }

    .dp-page-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
    }

    .dp-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--emerald-600), var(--emerald-400));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(16, 185, 129, .35);
    }

    .dp-header-icon .material-symbols-outlined {
        font-size: 28px;
    }

    .dp-header-text h1 {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 700;
        color: #1a2e35;
    }

    .dp-header-text p {
        margin: 4px 0 0;
        color: #6b7c85;
        font-size: .925rem;
    }

    .dp-top-bar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 24px;
    }

    .dp-btn-create {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: linear-gradient(135deg, var(--emerald-600), var(--emerald-500));
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .925rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 2px 10px rgba(16, 185, 129, .3);
        transition: box-shadow .2s, transform .15s;
    }

    .dp-btn-create:hover {
        box-shadow: 0 4px 18px rgba(16, 185, 129, .45);
        transform: translateY(-1px);
        color: #fff;
    }

    .dp-btn-create .material-symbols-outlined {
        font-size: 20px;
    }

    .dp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(370px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .dp-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
        overflow: hidden;
        display: flex;
        border-left: 4px solid #d1d5db;
        transition: box-shadow .2s, transform .15s;
    }

    .dp-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,.09);
        transform: translateY(-2px);
    }

    .dp-card.active {
        border-left-color: var(--emerald-500);
    }

    .dp-card-body {
        flex: 1;
        padding: 20px 22px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }

    .dp-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .dp-card-name {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1a2e35;
        margin: 0;
        line-height: 1.35;
    }

    .dp-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 600;
        white-space: nowrap;
        flex-shrink: 0;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .dp-badge .material-symbols-outlined {
        font-size: 14px;
    }

    .dp-badge.active {
        background: var(--emerald-100);
        color: var(--emerald-700);
    }

    .dp-badge.inactive {
        background: #f3f4f6;
        color: #6b7280;
    }

    .dp-card-detail {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: .85rem;
        color: #4b5563;
        line-height: 1.45;
    }

    .dp-card-detail .material-symbols-outlined {
        font-size: 18px;
        color: var(--emerald-500);
        flex-shrink: 0;
        margin-top: 1px;
    }

    .dp-card-rating {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .85rem;
        color: #4b5563;
    }

    .dp-stars {
        display: flex;
        gap: 1px;
    }

    .dp-stars .material-symbols-outlined {
        font-size: 17px;
        color: #facc15;
    }

    .dp-stars .material-symbols-outlined.empty {
        color: #d1d5db;
    }

    .dp-card-actions {
        display: flex;
        gap: 6px;
        margin-top: 10px;
        padding-top: 12px;
        border-top: 1px solid #f3f4f6;
    }

    .dp-card-actions form {
        margin: 0;
    }

    .dp-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border: none;
        border-radius: 8px;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background .2s, transform .1s;
    }

    .dp-action-btn:hover {
        transform: translateY(-1px);
    }

    .dp-action-btn .material-symbols-outlined {
        font-size: 17px;
    }

    .dp-action-btn.toggle {
        background: var(--emerald-50);
        color: var(--emerald-700);
    }

    .dp-action-btn.toggle:hover {
        background: var(--emerald-100);
    }

    .dp-action-btn.toggle.deactivate {
        background: #fef2f2;
        color: #b91c1c;
    }

    .dp-action-btn.toggle.deactivate:hover {
        background: #fee2e2;
    }

    .dp-action-btn.edit {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .dp-action-btn.edit:hover {
        background: #dbeafe;
    }

    .dp-action-btn.delete {
        background: #fef2f2;
        color: #dc2626;
    }

    .dp-action-btn.delete:hover {
        background: #fee2e2;
    }

    .dp-empty {
        text-align: center;
        padding: 64px 24px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
    }

    .dp-empty .material-symbols-outlined {
        font-size: 64px;
        color: var(--emerald-300, #6ee7b7);
        margin-bottom: 16px;
    }

    .dp-empty h3 {
        margin: 0 0 8px;
        color: #374151;
        font-size: 1.1rem;
    }

    .dp-empty p {
        margin: 0 0 20px;
        color: #6b7280;
        font-size: .9rem;
    }

    .dp-empty a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 24px;
        background: linear-gradient(135deg, var(--emerald-600), var(--emerald-500));
        color: #fff;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: .9rem;
    }

    .dp-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .dp-pagination a,
    .dp-pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 38px;
        padding: 0 10px;
        border-radius: 8px;
        font-size: .85rem;
        font-weight: 500;
        text-decoration: none;
        transition: background .15s;
    }

    .dp-pagination a {
        background: #fff;
        color: #374151;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }

    .dp-pagination a:hover {
        background: var(--emerald-50);
        color: var(--emerald-700);
    }

    .dp-pagination span.current {
        background: var(--emerald-600);
        color: #fff;
        box-shadow: 0 2px 8px rgba(16,185,129,.3);
    }

    .dp-pagination span.disabled {
        color: #d1d5db;
        background: #f9fafb;
    }

    @media (max-width: 768px) {
        .dp-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dp-page-header">
    <div class="dp-header-icon">
        <span class="material-symbols-outlined">location_on</span>
    </div>
    <div class="dp-header-text">
        <h1>Manajemen Drop Point</h1>
        <p>Kelola lokasi drop point dan titik distribusi paket</p>
    </div>
</div>

<div class="dp-top-bar">
    <a href="{{ route('admin.drop-points.create') }}" class="dp-btn-create">
        <span class="material-symbols-outlined">add</span>
        Tambah Drop Point
    </a>
</div>

@if($dropPoints->count())
    <div class="dp-grid">
        @foreach($dropPoints as $dp)
            @php $isActive = $dp->is_active; @endphp
            <div class="dp-card {{ $isActive ? 'active' : '' }}">
                <div class="dp-card-body">
                    <div class="dp-card-top">
                        <h3 class="dp-card-name">{{ $dp->name }}</h3>
                        <span class="dp-badge {{ $isActive ? 'active' : 'inactive' }}">
                            <span class="material-symbols-outlined">{{ $isActive ? 'check_circle' : 'pause_circle' }}</span>
                            {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    @if($dp->address)
                        <div class="dp-card-detail">
                            <span class="material-symbols-outlined">pin_drop</span>
                            <span>{{ $dp->address }}</span>
                        </div>
                    @endif

                    @if($dp->phone)
                        <div class="dp-card-detail">
                            <span class="material-symbols-outlined">call</span>
                            <span>{{ $dp->phone }}</span>
                        </div>
                    @endif

                    @if($dp->schedule)
                        <div class="dp-card-detail">
                            <span class="material-symbols-outlined">schedule</span>
                            <span>{{ $dp->schedule }}</span>
                        </div>
                    @endif

                    <div class="dp-card-rating">
                        <div class="dp-stars">
                            @for($i = 1; $i <= 5; $i++)
                                <span class="material-symbols-outlined {{ $i > round($dp->rating) ? 'empty' : '' }}">star</span>
                            @endfor
                        </div>
                        <span>{{ number_format($dp->rating, 1) }}</span>
                    </div>

                    <div class="dp-card-actions">
                        <form action="{{ route('admin.drop-points.toggle-active', $dp->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="dp-action-btn toggle {{ $isActive ? 'deactivate' : '' }}">
                                <span class="material-symbols-outlined">{{ $isActive ? 'toggle_on' : 'toggle_off' }}</span>
                                {{ $isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>

                        <a href="{{ route('admin.drop-points.edit', $dp->id) }}" class="dp-action-btn edit">
                            <span class="material-symbols-outlined">edit</span>
                            Edit
                        </a>

                        <form action="{{ route('admin.drop-points.destroy', $dp->id) }}" method="POST"
                              onsubmit="return confirm('Yakin ingin menghapus drop point ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dp-action-btn delete">
                                <span class="material-symbols-outlined">delete</span>
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dp-pagination">
        {{ $dropPoints->links() }}
    </div>
@else
    <div class="dp-empty">
        <span class="material-symbols-outlined">inventory_2</span>
        <h3>Belum ada drop point</h3>
        <p>Mulai dengan menambahkan drop point baru untuk mendistribusikan paket.</p>
        <a href="{{ route('admin.drop-points.create') }}">
            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
            Tambah Drop Point
        </a>
    </div>
@endif
@endsection
