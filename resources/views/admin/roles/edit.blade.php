@extends('layouts.admin')

@section('title', 'Edit Role')

@push('styles')
<style>
    /* Indeterminate checkbox state */
    .perm-parent-indeterminate .perm-parent-check {
        background-color: var(--color-primary-container);
        border-color: var(--color-primary-container);
    }
    .perm-parent-indeterminate .perm-parent-check::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 8px;
        height: 2px;
        background: white;
        border-radius: 1px;
    }
</style>
@endpush

@section('content')
<div class="flex flex-col gap-space-xl" id="roleEditPage"
     data-role-permissions='@json($rolePermissions)'>

    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.roles.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-space-md">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[22px] text-primary">edit_note</span>
            </div>
            <div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Edit Role</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola informasi role dan hak akses pengguna</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.roles.update', $role->id) }}" method="POST" id="roleForm">
        @csrf
        @method('PUT')

        {{-- Informasi Role --}}
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden mb-space-xl">
            <div class="px-space-xl py-space-md border-b border-surface-container-high">
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                    Informasi Role
                </h2>
            </div>
            <div class="p-space-xl">
                {{-- Name --}}
                <div class="mb-space-lg">
                    <label for="name" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                        Nama Role <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}" required
                        class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('name') border-red-500 @enderror"
                        placeholder="Masukkan nama role">
                    @error('name')
                        <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Hak Akses --}}
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden mb-space-xl">
            <div class="px-space-xl py-space-md border-b border-surface-container-high">
                <div class="flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">key</span>
                        Hak Akses
                    </h2>
                    <label class="flex items-center gap-space-xs cursor-pointer px-space-md py-space-xs rounded-lg hover:bg-surface-container-low transition-colors">
                        <input type="checkbox" id="selectAll"
                            class="h-4 w-4 rounded border-surface-container-high text-primary focus:ring-primary">
                        <span class="font-label-md text-label-md text-on-surface font-semibold">Pilih Semua</span>
                    </label>
                </div>
            </div>
            <div class="p-space-xl">
                {{-- Search --}}
                <div class="mb-space-lg">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-space-md top-1/2 -translate-y-1/2 text-secondary text-[18px]">search</span>
                        <input type="text" id="permSearch" placeholder="Cari menu atau permission..."
                            class="w-full pl-10 pr-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                    </div>
                </div>

                @error('permissions')
                    <p class="mb-space-sm text-xs text-red-500">{{ $message }}</p>
                @enderror

                {{-- Permission Groups by Section --}}
                @php
                    $groupedMenus = collect($menus)->groupBy('section');
                @endphp

                <div class="flex flex-col gap-space-lg" id="permGroups">
                    @foreach($sections as $sectionKey => $sectionLabel)
                        @if(isset($groupedMenus[$sectionKey]) && $groupedMenus[$sectionKey]->isNotEmpty())
                            <div class="perm-section" data-section="{{ $sectionKey }}">
                                {{-- Section Header --}}
                                <div class="mb-space-sm">
                                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">{{ $sectionLabel }}</span>
                                </div>

                                {{-- Menu Cards --}}
                                <div class="flex flex-col gap-space-sm">
                                    @foreach($groupedMenus[$sectionKey] as $menu)
                                        @php
                                            $menuPerms = collect($menu['actions'])->map(fn($action) => "{$menu['id']}.{$action}")->toArray();
                                            $checkedCount = collect($menuPerms)->filter(fn($p) => in_array($p, $rolePermissions))->count();
                                            $totalActions = count($menu['actions']);
                                            $isAllChecked = $checkedCount === $totalActions;
                                            $isIndeterminate = $checkedCount > 0 && $checkedCount < $totalActions;
                                        @endphp

                                        <div class="perm-menu-card rounded-xl border border-surface-container-high bg-surface overflow-hidden transition-all hover:border-primary-container/50"
                                             data-menu-id="{{ $menu['id'] }}"
                                             data-search-text="{{ strtolower($menu['name'] . ' ' . $menu['description'] . ' ' . implode(' ', $menu['actions'])) }}">

                                            {{-- Menu Header --}}
                                            <div class="flex items-center justify-between px-space-lg py-space-md bg-surface-container-low/50 border-b border-surface-container-high">
                                                <div class="flex items-center gap-space-sm">
                                                    <span class="material-symbols-outlined text-[20px] text-primary">{{ $menu['icon'] }}</span>
                                                    <div>
                                                        <span class="font-body-md text-body-md text-on-surface font-semibold">{{ $menu['name'] }}</span>
                                                        <p class="font-label-sm text-label-sm text-secondary">{{ $menu['description'] }}</p>
                                                    </div>
                                                </div>
                                                <label class="flex items-center gap-space-xs cursor-pointer">
                                                    <input type="checkbox"
                                                        data-parent-menu="{{ $menu['id'] }}"
                                                        {{ $isAllChecked ? 'checked' : '' }}
                                                        {{ $isIndeterminate ? 'indeterminate' : '' }}
                                                        class="perm-parent-check h-4 w-4 rounded border-surface-container-high text-primary focus:ring-primary">
                                                </label>
                                            </div>

                                            {{-- Permission Actions Table --}}
                                            <div class="overflow-x-auto">
                                                <table class="w-full">
                                                    <thead>
                                                        <tr class="border-b border-surface-container-high">
                                                            <th class="px-space-lg py-space-xs text-left font-label-sm text-label-sm text-secondary font-semibold">Akses Menu</th>
                                                            @foreach($menu['actions'] as $action)
                                                                <th class="px-space-lg py-space-xs text-center font-label-sm text-label-sm text-secondary font-semibold">{{ $actionLabels[$action] }}</th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="px-space-lg py-space-sm">
                                                                <label class="flex items-center gap-space-xs cursor-pointer">
                                                                    <input type="checkbox"
                                                                        data-parent-menu="{{ $menu['id'] }}"
                                                                        data-action-type="access"
                                                                        value="{{ $menu['id'] }}.view"
                                                                        {{ in_array("{$menu['id']}.view", $rolePermissions) ? 'checked' : '' }}
                                                                        class="perm-access-check h-4 w-4 rounded border-surface-container-high text-primary focus:ring-primary">
                                                                    <span class="font-body-sm text-body-sm text-on-surface">Akses Menu</span>
                                                                </label>
                                                            </td>
                                                            @foreach($menu['actions'] as $action)
                                                                <td class="px-space-lg py-space-sm text-center">
                                                                    <label class="flex items-center justify-center cursor-pointer">
                                                                        <input type="checkbox"
                                                                            name="permissions[]"
                                                                            value="{{ $menu['id'] }}.{{ $action }}"
                                                                            data-parent-menu="{{ $menu['id'] }}"
                                                                            data-action="{{ $action }}"
                                                                            {{ in_array("{$menu['id']}.{$action}", $rolePermissions) ? 'checked' : '' }}
                                                                            class="perm-check h-4 w-4 rounded border-surface-container-high text-primary focus:ring-primary">
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

                {{-- Empty State --}}
                <div id="permEmpty" class="hidden rounded-lg border border-dashed border-surface-container-high bg-surface p-space-2xl text-center">
                    <span class="material-symbols-outlined mx-auto mb-space-sm block text-[36px] text-secondary">search_off</span>
                    <p class="font-body-sm text-body-sm text-secondary">Tidak ditemukan menu atau permission yang cocok.</p>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-space-md">
            <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Simpan Perubahan
            </button>
            <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container font-label-md text-label-md font-semibold transition-all">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const page = document.getElementById('roleEditPage');
    const rolePermissions = JSON.parse(page.dataset.rolePermissions || '[]');
    const form = document.getElementById('roleForm');
    let isDirty = false;

    // ─── Search Filter ────────────────────────────────────────
    const searchInput = document.getElementById('permSearch');
    const permGroups = document.getElementById('permGroups');
    const permEmpty = document.getElementById('permEmpty');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.perm-menu-card');
        const sections = document.querySelectorAll('.perm-section');
        let hasVisible = false;

        cards.forEach(card => {
            const text = card.dataset.searchText;
            const match = !query || text.includes(query);
            card.classList.toggle('hidden', !match);
            if (match) hasVisible = true;
        });

        // Hide empty sections
        sections.forEach(section => {
            const visibleCards = section.querySelectorAll('.perm-menu-card:not(.hidden)');
            section.classList.toggle('hidden', visibleCards.length === 0);
        });

        permEmpty.classList.toggle('hidden', hasVisible);
        permGroups.classList.toggle('hidden', !hasVisible);
    });

    // ─── Parent Checkbox Logic ────────────────────────────────
    document.querySelectorAll('.perm-parent-check').forEach(parentCheck => {
        parentCheck.addEventListener('change', function() {
            const menuId = this.dataset.parentMenu;
            const permChecks = document.querySelectorAll(`.perm-check[data-parent-menu="${menuId}"]`);
            const checked = this.checked;

            permChecks.forEach(check => {
                check.checked = checked;
                isDirty = true;
            });

            updateParentState(menuId);
            updateSelectAllState();
        });
    });

    // ─── Individual Permission Checkboxes ─────────────────────
    document.querySelectorAll('.perm-check').forEach(check => {
        check.addEventListener('change', function() {
            const menuId = this.dataset.parentMenu;
            const action = this.dataset.action;

            // Dependency: view is required for create, edit, delete
            if (['create', 'edit', 'delete'].includes(action) && this.checked) {
                const viewCheck = document.querySelector(`.perm-check[data-parent-menu="${menuId}"][data-action="view"]`);
                if (viewCheck && !viewCheck.checked) {
                    viewCheck.checked = true;
                }
            }

            // If view is unchecked, uncheck create, edit, delete
            if (action === 'view' && !this.checked) {
                document.querySelectorAll(`.perm-check[data-parent-menu="${menuId}"][data-action]:not([data-action="view"])`).forEach(c => {
                    c.checked = false;
                });
            }

            updateParentState(menuId);
            updateSelectAllState();
            isDirty = true;
        });
    });

    // ─── Update Parent Checkbox State ──────────────────────────
    function updateParentState(menuId) {
        const permChecks = document.querySelectorAll(`.perm-check[data-parent-menu="${menuId}"]`);
        const parentCheck = document.querySelector(`.perm-parent-check[data-parent-menu="${menuId}"]`);
        const accessCheck = document.querySelector(`.perm-access-check[data-parent-menu="${menuId}"]`);
        const checkedCount = [...permChecks].filter(c => c.checked).length;
        const total = permChecks.length;

        if (parentCheck) {
            parentCheck.checked = checkedCount === total;
            parentCheck.indeterminate = checkedCount > 0 && checkedCount < total;
        }

        if (accessCheck) {
            accessCheck.checked = checkedCount > 0;
        }
    }

    // ─── Select All ───────────────────────────────────────────
    const selectAll = document.getElementById('selectAll');
    selectAll.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.perm-check').forEach(check => {
            check.checked = checked;
        });
        document.querySelectorAll('.perm-parent-check').forEach(check => {
            check.checked = checked;
            check.indeterminate = false;
        });
        document.querySelectorAll('.perm-access-check').forEach(check => {
            check.checked = checked;
        });
        isDirty = true;
    });

    function updateSelectAllState() {
        const allChecks = document.querySelectorAll('.perm-check');
        const checkedCount = [...allChecks].filter(c => c.checked).length;
        selectAll.checked = checkedCount === allChecks.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < allChecks.length;
    }

    // ─── Initialize Parent States ─────────────────────────────
    document.querySelectorAll('.perm-parent-check').forEach(check => {
        updateParentState(check.dataset.parentMenu);
    });
    updateSelectAllState();

    // ─── Unsaved Changes Warning ──────────────────────────────
    form.addEventListener('input', () => { isDirty = true; });
    form.addEventListener('change', () => { isDirty = true; });

    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    form.addEventListener('submit', function() {
        isDirty = false;
    });
});
</script>
@endpush
