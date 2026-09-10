@extends('layouts.admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Page Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-1">
            <span class="material-symbols-outlined text-[#059669] text-4xl">local_shipping</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ $viewTitle }}</h1>
        </div>
        <p class="text-gray-500 ml-12">{{ $viewSubtitle }}</p>
    </div>

    {{-- Stat Filter Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php
            $allCount = $couriers->total();
            $unverifiedCount = $couriers->getCollection()->where('is_verified', false)->count();
            $verifiedCount = $couriers->getCollection()->where('is_verified', true)->count();
            $activeCount = $couriers->getCollection()->where('is_active', true)->count();
        @endphp

        <a href="{{ route('admin.couriers') }}"
           class="group relative overflow-hidden rounded-xl border-2 {{ !request('filter') ? 'border-[#059669] bg-[#059669]/5 shadow-md shadow-[#059669]/10' : 'border-gray-200 bg-white hover:border-[#059669]/40 hover:shadow-md' }} p-5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 {{ !request('filter') ? 'text-[#059669]' : 'group-hover:text-[#059669]' }} transition-colors">Semua</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $allCount }}</p>
                </div>
                <div class="rounded-full {{ !request('filter') ? 'bg-[#059669] text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-[#059669]/10 group-hover:text-[#059669]' }} p-3 transition-all duration-200">
                    <span class="material-symbols-outlined text-2xl">group</span>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
           class="group relative overflow-hidden rounded-xl border-2 {{ request('filter') === 'unverified' ? 'border-orange-500 bg-orange-50 shadow-md shadow-orange-500/10' : 'border-gray-200 bg-white hover:border-orange-400/40 hover:shadow-md' }} p-5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 {{ request('filter') === 'unverified' ? 'text-orange-600' : 'group-hover:text-orange-600' }} transition-colors">Menunggu Verifikasi</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $unverifiedCount }}</p>
                </div>
                <div class="rounded-full {{ request('filter') === 'unverified' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-orange-50 group-hover:text-orange-500' }} p-3 transition-all duration-200">
                    <span class="material-symbols-outlined text-2xl">pending</span>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
           class="group relative overflow-hidden rounded-xl border-2 {{ request('filter') === 'verified' ? 'border-emerald-500 bg-emerald-50 shadow-md shadow-emerald-500/10' : 'border-gray-200 bg-white hover:border-emerald-400/40 hover:shadow-md' }} p-5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 {{ request('filter') === 'verified' ? 'text-emerald-600' : 'group-hover:text-emerald-600' }} transition-colors">Terverifikasi</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $verifiedCount }}</p>
                </div>
                <div class="rounded-full {{ request('filter') === 'verified' ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-emerald-50 group-hover:text-emerald-500' }} p-3 transition-all duration-200">
                    <span class="material-symbols-outlined text-2xl">verified</span>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.couriers', ['filter' => 'active']) }}"
           class="group relative overflow-hidden rounded-xl border-2 {{ request('filter') === 'active' ? 'border-blue-500 bg-blue-50 shadow-md shadow-blue-500/10' : 'border-gray-200 bg-white hover:border-blue-400/40 hover:shadow-md' }} p-5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 {{ request('filter') === 'active' ? 'text-blue-600' : 'group-hover:text-blue-600' }} transition-colors">Kurir Aktif</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $activeCount }}</p>
                </div>
                <div class="rounded-full {{ request('filter') === 'active' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-500' }} p-3 transition-all duration-200">
                    <span class="material-symbols-outlined text-2xl">check_circle</span>
                </div>
            </div>
        </a>
    </div>

    {{-- Filter Tabs & Search --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.couriers') }}"
               class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all duration-200
                      {{ !request('filter') ? 'bg-[#059669] text-white shadow-md shadow-[#059669]/25' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <span class="material-symbols-outlined text-lg">list</span>
                Semua
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
               class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all duration-200
                      {{ request('filter') === 'unverified' ? 'bg-orange-500 text-white shadow-md shadow-orange-500/25' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <span class="material-symbols-outlined text-lg">hourglass_empty</span>
                Belum Verifikasi
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
               class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all duration-200
                      {{ request('filter') === 'verified' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/25' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <span class="material-symbols-outlined text-lg">verified</span>
                Terverifikasi
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'active']) }}"
               class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all duration-200
                      {{ request('filter') === 'active' ? 'bg-blue-500 text-white shadow-md shadow-blue-500/25' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <span class="material-symbols-outlined text-lg">bolt</span>
                Aktif
            </a>
        </div>

        <div class="relative">
            <form method="GET" action="{{ route('admin.couriers') }}">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cari kurir..."
                       class="w-full sm:w-72 rounded-full border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition-all duration-200 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-[#059669]/20">
            </form>
        </div>
    </div>

    {{-- Couriers Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        @if($couriers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80">
                            <th class="text-left px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Kurir</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Telepon</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Kendaraan</th>
                            <th class="text-left px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Status</th>
                            <th class="text-center px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Rating</th>
                            <th class="text-center px-6 py-4 font-semibold text-gray-500 uppercase tracking-wider text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($couriers as $courier)
                            <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-[#059669]/10 text-[#059669] flex items-center justify-center font-bold text-sm flex-shrink-0">
                                            {{ strtoupper(substr($courier->user->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 truncate">{{ $courier->user->name }}</p>
                                            <p class="text-gray-400 text-xs truncate">{{ $courier->user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-gray-400 text-base">phone</span>
                                        {{ $courier->phone ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-gray-400 text-base">two_wheeler</span>
                                        <div>
                                            <span class="font-medium">{{ $courier->vehicle_type ?? '-' }}</span>
                                            @if($courier->vehicle_plate)
                                                <span class="text-gray-400 mx-1">&middot;</span>
                                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ $courier->vehicle_plate }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($courier->is_verified)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                                                <span class="material-symbols-outlined text-sm">verified</span>
                                                Terverifikasi
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700 ring-1 ring-orange-600/20">
                                                <span class="material-symbols-outlined text-sm">pending</span>
                                                Belum Verifikasi
                                            </span>
                                        @endif

                                        @if($courier->is_active)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-600/20">
                                                <span class="material-symbols-outlined text-sm">bolt</span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-gray-500/20">
                                                <span class="material-symbols-outlined text-sm">pause</span>
                                                Nonaktif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-amber-400 text-base">star</span>
                                        <span class="font-semibold text-gray-900">{{ $courier->rating ?? '0.0' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <form action="{{ route('admin.couriers.verify', $courier->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-all duration-200
                                                           {{ $courier->is_verified
                                                               ? 'bg-gray-100 text-gray-600 hover:bg-orange-50 hover:text-orange-600'
                                                               : 'bg-[#059669]/10 text-[#059669] hover:bg-[#059669] hover:text-white shadow-sm hover:shadow-md' }}">
                                                <span class="material-symbols-outlined text-base">
                                                    {{ $courier->is_verified ? 'cancel' : 'verified' }}
                                                </span>
                                                {{ $courier->is_verified ? 'Batalkan' : 'Verifikasi' }}
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-all duration-200
                                                           {{ $courier->is_active
                                                               ? 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600'
                                                               : 'bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white shadow-sm hover:shadow-md' }}">
                                                <span class="material-symbols-outlined text-base">
                                                    {{ $courier->is_active ? 'pause' : 'play_arrow' }}
                                                </span>
                                                {{ $courier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($couriers->hasPages())
                <div class="border-t border-gray-100 px-6 py-4">
                    {{ $couriers->withQueryString()->links('pagination::tailwind') }}
                </div>
            @endif
        @else
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-20 px-6">
                <div class="rounded-full bg-gray-100 p-5 mb-5">
                    <span class="material-symbols-outlined text-5xl text-gray-300">search_off</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Tidak ada kurir ditemukan</h3>
                <p class="text-sm text-gray-400 text-center max-w-sm">
                    @if(request('search'))
                        Tidak ada hasil untuk pencarian "<span class="font-medium text-gray-500">{{ request('search') }}</span>".
                    @elseif(request('filter'))
                        Tidak ada kurir yang cocok dengan filter ini.
                    @else
                        Belum ada data kurir yang terdaftar dalam sistem.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
@endsection