@extends('layouts.admin')

@section('title', 'Tambah Role Baru')

@section('content')
<div class="space-y-5" id="roleCreatePage">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}" class="back-link" title="Kembali ke Daftar Role">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Tambah Role Baru</h1>
                <p class="page-subtitle">Tetapkan nama peran dan checklist izin akses untuk setiap fitur sistem.</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.roles.store') }}" method="POST" id="roleForm" class="space-y-5">
        @csrf

        {{-- Informasi Role --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Informasi Dasar Role</div>
            </div>
            <div class="card-body">
                <div>
                    <label for="name" class="form-label">
                        Nama Role <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           required
                           placeholder="Contoh: Operator, Keuangan, Dispatcher..."
                           class="form-input @error('name') form-invalid @enderror">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Checklist Permissions --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Matriks Hak Akses (Permissions)</div>
                    <div class="card-subtitle">Pilih modul yang diizinkan untuk diakses role ini</div>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-800">
                    <input type="checkbox" id="selectAll" class="form-check">
                    <span>Pilih Semua Hak Akses</span>
                </label>
            </div>

            <div class="card-body space-y-4">
                {{-- Search Filter --}}
                <div class="search-box">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           id="permSearch"
                           placeholder="Filter permission atau modul..."
                           class="form-input">
                </div>

                @error('permissions')
                    <p class="form-error">{{ $message }}</p>
                @enderror

                @php
                    $groupedMenus = collect($menus)->groupBy('section');
                @endphp

                <div class="space-y-5" id="permGroups">
                    @foreach($sections as $sectionKey => $sectionLabel)
                        @if(isset($groupedMenus[$sectionKey]) && $groupedMenus[$sectionKey]->isNotEmpty())
                            <div class="perm-section space-y-2.5" data-section="{{ $sectionKey }}">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 pb-1">
                                    {{ $sectionLabel }}
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    @foreach($groupedMenus[$sectionKey] as $menu)
                                        <div class="perm-menu-card rounded-lg border border-slate-200 bg-white overflow-hidden shadow-xs"
                                             data-menu-id="{{ $menu['id'] }}"
                                             data-search-text="{{ strtolower($menu['name'] . ' ' . $menu['description'] . ' ' . implode(' ', $menu['actions'])) }}">

                                            {{-- Menu Header --}}
                                            <div class="flex items-center justify-between px-3.5 py-2.5 bg-slate-50 border-b border-slate-200">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-[18px] text-brand-600">{{ $menu['icon'] }}</span>
                                                    <span class="text-xs font-semibold text-slate-900">{{ $menu['name'] }}</span>
                                                    <span class="text-[11px] text-slate-400 hidden sm:inline">&bull; {{ $menu['description'] }}</span>
                                                </div>
                                                <label class="flex items-center gap-1.5 cursor-pointer text-[11px] font-medium text-slate-600">
                                                    <input type="checkbox"
                                                           data-parent-menu="{{ $menu['id'] }}"
                                                           class="perm-parent-check form-check">
                                                    <span>Pilih Modul</span>
                                                </label>
                                            </div>

                                            {{-- Action Checkboxes Table --}}
                                            <div class="table-wrap">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="border-b border-slate-100 text-slate-400">
                                                            <th class="px-3.5 py-1.5 text-left font-medium">Izin Akses</th>
                                                            @foreach($menu['actions'] as $action)
                                                                <th class="px-3 py-1.5 text-center font-medium">{{ $actionLabels[$action] ?? ucfirst($action) }}</th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="px-3.5 py-2 text-slate-700 font-medium">
                                                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                                                    <input type="checkbox"
                                                                           data-parent-menu="{{ $menu['id'] }}"
                                                                           data-action-type="access"
                                                                           value="{{ $menu['id'] }}.view"
                                                                           class="perm-access-check form-check">
                                                                    <span>Buka Menu</span>
                                                                </label>
                                                            </td>
                                                            @foreach($menu['actions'] as $action)
                                                                <td class="px-3 py-2 text-center">
                                                                    <label class="inline-flex items-center justify-center cursor-pointer">
                                                                        <input type="checkbox"
                                                                               name="permissions[]"
                                                                               value="{{ $menu['id'] }}.{{ $action }}"
                                                                               data-parent-menu="{{ $menu['id'] }}"
                                                                               data-action="{{ $action }}"
                                                                               class="perm-check form-check">
                                                                    </label>
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Empty Search State --}}
                <div id="permEmpty" class="empty-state py-8 hidden">
                    <span class="material-symbols-outlined text-[32px] text-slate-300">search_off</span>
                    <div class="empty-title">Tidak ada izin akses yang cocok</div>
                </div>
            </div>

            <div class="card-footer">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    Simpan Role
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('permSearch');
    const permEmpty = document.getElementById('permEmpty');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.perm-menu-card');
            let hasVisible = false;

            cards.forEach(card => {
                const text = card.getAttribute('data-search-text') || '';
                const match = text.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) hasVisible = true;
            });

            document.querySelectorAll('.perm-section').forEach(sec => {
                const visibleInSec = sec.querySelectorAll('.perm-menu-card:not([style*="display: none"])').length;
                sec.style.display = visibleInSec > 0 ? '' : 'none';
            });

            if (permEmpty) permEmpty.classList.toggle('hidden', hasVisible);
        });
    }

    // Select All
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checks = document.querySelectorAll('.perm-check, .perm-access-check, .perm-parent-check');
            checks.forEach(c => c.checked = selectAll.checked);
        });
    }

    // Parent Checkbox per Menu Card
    document.querySelectorAll('.perm-parent-check').forEach(parent => {
        parent.addEventListener('change', function() {
            const menuId = this.getAttribute('data-parent-menu');
            const children = document.querySelectorAll(`input[data-parent-menu="${menuId}"]:not(.perm-parent-check)`);
            children.forEach(c => c.checked = parent.checked);
            updateSelectAllState();
        });
    });

    // Child Checkbox
    document.querySelectorAll('.perm-check, .perm-access-check').forEach(child => {
        child.addEventListener('change', function() {
            const menuId = this.getAttribute('data-parent-menu');
            updateParentState(menuId);
            updateSelectAllState();
        });
    });

    function updateParentState(menuId) {
        const parent = document.querySelector(`.perm-parent-check[data-parent-menu="${menuId}"]`);
        if (!parent) return;
        const children = document.querySelectorAll(`input[data-parent-menu="${menuId}"]:not(.perm-parent-check)`);
        const total = children.length;
        const checked = Array.from(children).filter(c => c.checked).length;
        parent.checked = (checked === total && total > 0);
    }

    function updateSelectAllState() {
        if (!selectAll) return;
        const allChecks = document.querySelectorAll('.perm-check');
        const checked = Array.from(allChecks).filter(c => c.checked).length;
        selectAll.checked = (checked === allChecks.length && allChecks.length > 0);
    }
});
</script>
@endpush
