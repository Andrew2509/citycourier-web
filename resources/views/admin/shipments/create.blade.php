@extends('layouts.admin')

@section('title', 'Tambah Pengiriman Baru')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .map-container {
        height: 380px;
        width: 100%;
        border-radius: 0.5rem;
        z-index: 10;
    }
    .custom-map-pin {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: #ec5b13;
        color: white;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .custom-map-pin .pin-inner {
        transform: rotate(45deg);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .leaflet-popup-content-wrapper {
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        font-family: 'Inter', sans-serif;
    }
    .autocomplete-items {
        position: absolute;
        border: 1px solid #e2e8f0;
        border-top: none;
        z-index: 99;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 220px;
        overflow-y: auto;
        background-color: #fff;
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .autocomplete-item {
        padding: 9px 14px;
        cursor: pointer;
        font-size: 13px;
        line-height: 1.35;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }
    .autocomplete-item:hover {
        background-color: #f8fafc;
        color: #ec5b13;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb & Header Halaman --}}
    <div class="flex flex-col gap-2">
        <nav class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-800 transition">Dashboard</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <a href="{{ route('admin.shipments.index') }}" class="hover:text-slate-800 transition">Pengiriman</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-slate-900 font-semibold">Tambah Pengiriman Baru</span>
        </nav>
        <div class="page-header">
            <div>
                <h1 class="page-title text-xl font-bold text-slate-900">Tambah Pengiriman Baru</h1>
                <p class="page-subtitle text-xs text-slate-500">
                    Input pengiriman kurir dengan akurasi koordinat peta dan sinkronisasi otomatis wilayah RajaOngkir.
                </p>
            </div>
            <div class="page-actions">
                <a href="{{ route('admin.shipments.index') }}" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    <span>Kembali ke Daftar</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Form Tambah Pengiriman --}}
    <form id="formCreateShipment" action="{{ route('admin.shipments.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- KOLOM KIRI (7/12) -- ALAMAT & PENGIRIMAN --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- 1. PENGIRIM (PICKUP) --}}
                <div class="card p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-orange-50 border border-orange-200 text-brand-600 flex items-center justify-center font-bold text-sm">
                                1
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Lokasi Penjemputan (Pengirim)</h3>
                                <p class="text-[11.5px] text-slate-500">Tentukan alamat penjemputan paket kurir.</p>
                            </div>
                        </div>

                        {{-- Quick Drop Point Selector --}}
                        @if(isset($dropPoints) && $dropPoints->count() > 0)
                        <div class="relative">
                            <select id="quickDropPoint" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                                <option value="">+ Pilih dari Drop Point</option>
                                @foreach($dropPoints as $dp)
                                <option value="{{ $dp->id }}"
                                        data-name="{{ $dp->name }}"
                                        data-phone="{{ $dp->phone ?? '081343323155' }}"
                                        data-address="{{ $dp->address }}"
                                        data-lat="{{ $dp->latitude }}"
                                        data-lng="{{ $dp->longitude }}"
                                        data-city="{{ $dp->city ?? 'Surabaya' }}"
                                        data-district="{{ $dp->district ?? 'Wonokromo' }}">
                                    {{ $dp->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="sender_name">Nama Pengirim <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="sender_name"
                                   id="sender_name"
                                   value="{{ old('sender_name') }}"
                                   required
                                   placeholder="Contoh: Kantor Cabang CityCourier / Budi Santoso"
                                   class="form-input">
                            @error('sender_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="form-label" for="sender_phone">Nomor Telepon / WhatsApp <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="sender_phone"
                                   id="sender_phone"
                                   value="{{ old('sender_phone') }}"
                                   required
                                   placeholder="Contoh: 081234567890"
                                   class="form-input">
                            @error('sender_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Alamat Lengkap + Tombol Pilih dari Peta --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="form-label mb-0" for="sender_address">Alamat Lengkap Pengirim <span class="text-red-500">*</span></label>
                            <button type="button"
                                    onclick="openMapModal('sender')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-brand-600 bg-brand-50 border border-brand-200 rounded-md hover:bg-brand-100 transition shadow-2xs">
                                <span class="material-symbols-outlined text-[16px]">location_on</span>
                                <span>Pilih dari Peta</span>
                            </button>
                        </div>
                        <div class="relative">
                            <textarea name="sender_address"
                                      id="sender_address"
                                      rows="2"
                                      required
                                      placeholder="Masukkan alamat lengkap atau klik 'Pilih dari Peta'..."
                                      class="form-input pr-10">{{ old('sender_address') }}</textarea>
                            <button type="button"
                                    onclick="openMapModal('sender')"
                                    class="absolute right-2.5 top-2.5 text-slate-400 hover:text-brand-600 transition"
                                    title="Buka Peta Interaktif">
                                <span class="material-symbols-outlined text-[20px]">map</span>
                            </button>
                        </div>
                        @error('sender_address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Wilayah RajaOngkir Pengirim --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="form-label mb-0">Wilayah RajaOngkir (Origin) <span class="text-red-500">*</span></label>
                            <button type="button"
                                    id="btnToggleManualSender"
                                    onclick="toggleManualRegion('sender')"
                                    class="text-xs text-slate-500 hover:text-slate-800 font-medium underline">
                                Ubah Wilayah Manual
                            </button>
                        </div>

                        {{-- Display Wilayah Terdeteksi --}}
                        <div id="senderRegionDisplay" class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400 text-[18px]">verified</span>
                                <div>
                                    <span id="senderRegionText" class="text-xs font-semibold text-slate-800">
                                        {{ old('origin_name') ?: 'Pilih lokasi dari peta atau tentukan wilayah manual' }}
                                    </span>
                                    <div id="senderRegionBadge" class="{{ old('origin_id') ? 'inline-flex' : 'hidden' }} items-center gap-1 ml-2 px-1.5 py-0.5 text-[10.5px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 rounded">
                                        <span>✓ Wilayah terdeteksi otomatis</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono" id="senderRegionIdLabel">
                                {{ old('origin_id') ? 'ID: ' . old('origin_id') : '' }}
                            </span>
                        </div>

                        {{-- Hidden Inputs for Origin --}}
                        <input type="hidden" name="origin_id" id="sender_origin_id" value="{{ old('origin_id') }}">
                        <input type="hidden" name="origin_name" id="sender_origin_name" value="{{ old('origin_name') }}">
                        <input type="hidden" name="sender_latitude" id="sender_latitude" value="{{ old('sender_latitude') }}">
                        <input type="hidden" name="sender_longitude" id="sender_longitude" value="{{ old('sender_longitude') }}">

                        {{-- Manual Selection Dropdown (Fallback / Edit) --}}
                        <div id="senderManualRegionBox" class="hidden mt-2 p-3 bg-white border border-slate-200 rounded-lg space-y-3">
                            <p class="text-[11.5px] text-slate-500">Pilih wilayah RajaOngkir secara berjenjang:</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Provinsi</label>
                                    <select id="senderProvSelect" class="form-select text-xs" onchange="loadCities('sender', this.value)">
                                        <option value="">Pilih Provinsi</option>
                                        @foreach($provinces as $p)
                                        <option value="{{ $p['province_id'] }}">{{ $p['province'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Kota/Kabupaten</label>
                                    <select id="senderCitySelect" class="form-select text-xs" onchange="loadDistricts('sender', this.value)" disabled>
                                        <option value="">Pilih Kota</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Kecamatan/Wilayah</label>
                                    <select id="senderDistrictSelect" class="form-select text-xs" onchange="applyManualDistrict('sender')" disabled>
                                        <option value="">Pilih Kecamatan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        @error('origin_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- 2. PENERIMA (DESTINATION) --}}
                <div class="card p-5 space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 border border-orange-200 text-brand-600 flex items-center justify-center font-bold text-sm">
                            2
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Lokasi Tujuan (Penerima)</h3>
                            <p class="text-[11.5px] text-slate-500">Tentukan alamat tujuan pengantaran paket.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="receiver_name">Nama Penerima <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="receiver_name"
                                   id="receiver_name"
                                   value="{{ old('receiver_name') }}"
                                   required
                                   placeholder="Contoh: Andrew Prasetya"
                                   class="form-input">
                            @error('receiver_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="form-label" for="receiver_phone">Nomor Telepon / WhatsApp <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="receiver_phone"
                                   id="receiver_phone"
                                   value="{{ old('receiver_phone') }}"
                                   required
                                   placeholder="Contoh: 081233445566"
                                   class="form-input">
                            @error('receiver_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Alamat Lengkap + Tombol Pilih dari Peta --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="form-label mb-0" for="receiver_address">Alamat Lengkap Penerima <span class="text-red-500">*</span></label>
                            <button type="button"
                                    onclick="openMapModal('receiver')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-brand-600 bg-brand-50 border border-brand-200 rounded-md hover:bg-brand-100 transition shadow-2xs">
                                <span class="material-symbols-outlined text-[16px]">location_on</span>
                                <span>Pilih dari Peta</span>
                            </button>
                        </div>
                        <div class="relative">
                            <textarea name="receiver_address"
                                      id="receiver_address"
                                      rows="2"
                                      required
                                      placeholder="Masukkan alamat lengkap tujuan atau klik 'Pilih dari Peta'..."
                                      class="form-input pr-10">{{ old('receiver_address') }}</textarea>
                            <button type="button"
                                    onclick="openMapModal('receiver')"
                                    class="absolute right-2.5 top-2.5 text-slate-400 hover:text-brand-600 transition"
                                    title="Buka Peta Interaktif">
                                <span class="material-symbols-outlined text-[20px]">map</span>
                            </button>
                        </div>
                        @error('receiver_address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Wilayah RajaOngkir Penerima --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="form-label mb-0">Wilayah RajaOngkir (Destination) <span class="text-red-500">*</span></label>
                            <button type="button"
                                    id="btnToggleManualReceiver"
                                    onclick="toggleManualRegion('receiver')"
                                    class="text-xs text-slate-500 hover:text-slate-800 font-medium underline">
                                Ubah Wilayah Manual
                            </button>
                        </div>

                        {{-- Display Wilayah Terdeteksi --}}
                        <div id="receiverRegionDisplay" class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400 text-[18px]">verified</span>
                                <div>
                                    <span id="receiverRegionText" class="text-xs font-semibold text-slate-800">
                                        {{ old('destination_name') ?: 'Pilih lokasi dari peta atau tentukan wilayah manual' }}
                                    </span>
                                    <div id="receiverRegionBadge" class="{{ old('destination_id') ? 'inline-flex' : 'hidden' }} items-center gap-1 ml-2 px-1.5 py-0.5 text-[10.5px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 rounded">
                                        <span>✓ Wilayah terdeteksi otomatis</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono" id="receiverRegionIdLabel">
                                {{ old('destination_id') ? 'ID: ' . old('destination_id') : '' }}
                            </span>
                        </div>

                        {{-- Hidden Inputs for Destination --}}
                        <input type="hidden" name="destination_id" id="receiver_destination_id" value="{{ old('destination_id') }}">
                        <input type="hidden" name="destination_name" id="receiver_destination_name" value="{{ old('destination_name') }}">
                        <input type="hidden" name="receiver_latitude" id="receiver_latitude" value="{{ old('receiver_latitude') }}">
                        <input type="hidden" name="receiver_longitude" id="receiver_longitude" value="{{ old('receiver_longitude') }}">

                        {{-- Manual Selection Dropdown (Fallback / Edit) --}}
                        <div id="receiverManualRegionBox" class="hidden mt-2 p-3 bg-white border border-slate-200 rounded-lg space-y-3">
                            <p class="text-[11.5px] text-slate-500">Pilih wilayah RajaOngkir secara berjenjang:</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Provinsi</label>
                                    <select id="receiverProvSelect" class="form-select text-xs" onchange="loadCities('receiver', this.value)">
                                        <option value="">Pilih Provinsi</option>
                                        @foreach($provinces as $p)
                                        <option value="{{ $p['province_id'] }}">{{ $p['province'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Kota/Kabupaten</label>
                                    <select id="receiverCitySelect" class="form-select text-xs" onchange="loadDistricts('receiver', this.value)" disabled>
                                        <option value="">Pilih Kota</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] text-slate-500 font-medium">Kecamatan/Wilayah</label>
                                    <select id="receiverDistrictSelect" class="form-select text-xs" onchange="applyManualDistrict('receiver')" disabled>
                                        <option value="">Pilih Kecamatan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        @error('destination_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- 3. DETAIL BARANG / PAKET --}}
                <div class="card p-5 space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 border border-orange-200 text-brand-600 flex items-center justify-center font-bold text-sm">
                            3
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Informasi Paket</h3>
                            <p class="text-[11.5px] text-slate-500">Detail barang yang akan diantarkan oleh kurir.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label" for="package_description">Deskripsi Barang <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="package_description"
                                   id="package_description"
                                   value="{{ old('package_description') }}"
                                   required
                                   placeholder="Contoh: Dokumen Kontrak / Makanan Ringan / Sparepart"
                                   class="form-input">
                            @error('package_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="form-label" for="package_weight">Berat Paket (kg) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="number"
                                       step="0.1"
                                       min="0.1"
                                       name="package_weight"
                                       id="package_weight"
                                       value="{{ old('package_weight', '1.0') }}"
                                       required
                                       oninput="recalculateCosts()"
                                       class="form-input pr-10 font-semibold">
                                <span class="absolute right-3 top-2.5 text-xs text-slate-400 font-medium">kg</span>
                            </div>
                            @error('package_weight')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="notes">Catatan Tambahan untuk Kurir (Opsional)</label>
                        <input type="text"
                               name="notes"
                               id="notes"
                               value="{{ old('notes') }}"
                               placeholder="Contoh: Titipkan ke satpam gerbang depan / hubungi saat tiba"
                               class="form-input">
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN (4/12) -- KENDARAAN, ONGKIR & SUMMARY --}}
            <div class="lg:col-span-4 space-y-6">

                {{-- CARD KENDARAAN & TARIF --}}
                <div class="card p-5 space-y-4 sticky top-6">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-900">Layanan &amp; Biaya</h3>
                        <p class="text-[11.5px] text-slate-500">Pilih kendaraan dan konfirmasi ongkir.</p>
                    </div>

                    {{-- Pilihan Kendaraan --}}
                    <div>
                        <label class="form-label">Tipe Kendaraan Kurir <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="vehicle-option relative flex flex-col items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition text-center border-brand-600 bg-brand-50" id="vehicleCardMotor">
                                <input type="radio" name="vehicle" value="motor" checked onchange="onVehicleChange('motor')" class="sr-only">
                                <span class="material-symbols-outlined text-2xl text-brand-600 mb-1">two_wheeler</span>
                                <span class="text-xs font-bold text-slate-900">Motor</span>
                                <span class="text-[10px] text-slate-500">Maks. 20 kg</span>
                            </label>

                            <label class="vehicle-option relative flex flex-col items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition text-center border-slate-200 hover:border-slate-300" id="vehicleCardMobil">
                                <input type="radio" name="vehicle" value="mobil" onchange="onVehicleChange('mobil')" class="sr-only">
                                <span class="material-symbols-outlined text-2xl text-slate-600 mb-1">local_shipping</span>
                                <span class="text-xs font-bold text-slate-900">Mobil</span>
                                <span class="text-[10px] text-slate-500">Maks. 150 kg</span>
                            </label>
                        </div>
                    </div>

                    {{-- Layanan --}}
                    <div>
                        <label class="form-label" for="courier_service">Layanan Pengiriman</label>
                        <select name="courier_service" id="courier_service" class="form-select text-xs" onchange="recalculateCosts()">
                            <option value="Instant">City Instant (Kilat Dalam Kota)</option>
                            <option value="Same Day">City Same Day (Tiba Hari Ini)</option>
                        </select>
                    </div>

                    {{-- Indikator Jarak --}}
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="material-symbols-outlined text-slate-400 text-[18px]">straighten</span>
                            <span>Estimasi Jarak</span>
                        </div>
                        <span id="labelDistance" class="font-bold text-slate-900">0.0 km</span>
                    </div>

                    {{-- Rincian Ongkos Kirim --}}
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>Tarif Dasar</span>
                            <span id="labelBaseRate">Rp8.000</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>Tarif Jarak (<span id="labelPerKmRate">Rp2.500</span>/km)</span>
                            <span id="labelDistanceCost">Rp0</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>Ongkos Kirim Manual</span>
                            <div class="w-28">
                                <input type="number"
                                       name="shipping_cost"
                                       id="shipping_cost"
                                       value="{{ old('shipping_cost', '8000') }}"
                                       required
                                       oninput="onManualCostInput(this.value)"
                                       class="form-input text-right py-1 text-xs font-semibold">
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-baseline justify-between">
                            <span class="text-xs font-bold text-slate-900">Total Biaya</span>
                            <div class="text-right">
                                <div class="text-lg font-bold text-brand-600" id="labelTotalCost">Rp8.000</div>
                                <input type="hidden" name="total_cost" id="total_cost" value="{{ old('total_cost', '8000') }}">
                                <span class="text-[10.5px] text-slate-400">Termasuk pajak &amp; asuransi standar</span>
                            </div>
                        </div>
                    </div>

                    {{-- Metode Pembayaran --}}
                    <div class="pt-2">
                        <label class="form-label" for="payment_method">Metode Pembayaran <span class="text-red-500">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-select text-xs font-medium">
                            <option value="TUNAI">TUNAI (Bayar Tunai ke Kurir)</option>
                            <option value="COD">COD (Cash on Delivery)</option>
                        </select>
                    </div>

                    {{-- Tombol Submit --}}
                    <div class="space-y-2 pt-3">
                        <button type="submit" class="btn btn-primary w-full py-2.5 justify-center shadow-md">
                            <span class="material-symbols-outlined text-[18px]">send</span>
                            <span>Buat Pengiriman &amp; Resi</span>
                        </button>
                        <a href="{{ route('admin.shipments.index') }}" class="btn btn-ghost w-full py-2 justify-center text-xs text-slate-500">
                            Batal
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

{{-- MODAL PETA INTERAKTIF (LEAFLET.JS) --}}
<div id="mapModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        {{-- Overlay --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeMapModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        {{-- Dialog Modal Box --}}
        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full border border-slate-200">
            {{-- Modal Header --}}
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand-400">map</span>
                    <div>
                        <h3 class="text-sm font-bold text-white" id="modalMapTitle">Pilih Titik Lokasi dari Peta</h3>
                        <p class="text-[11.5px] text-slate-300">Peta interaktif dengan reverse geocoding &amp; pencocokan wilayah RajaOngkir otomatis.</p>
                    </div>
                </div>
                <button type="button" onclick="closeMapModal()" class="text-slate-400 hover:text-white transition">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Modal Search & Geolocation Controls --}}
            <div class="p-4 bg-slate-50 border-b border-slate-200 space-y-3">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    {{-- Search Input with Autocomplete --}}
                    <div class="relative flex-1">
                        <div class="relative">
                            <input type="text"
                                   id="mapSearchInput"
                                   placeholder="Cari nama jalan, gedung, atau kelurahan..."
                                   class="form-input text-xs pl-8 pr-8 py-2 w-full bg-white shadow-2xs">
                            <span class="material-symbols-outlined absolute left-2.5 top-2 text-slate-400 text-[18px]">search</span>
                            <button type="button" id="btnClearMapSearch" onclick="clearMapSearch()" class="hidden absolute right-2.5 top-2 text-slate-400 hover:text-slate-600">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                        <div id="autocompleteResults" class="autocomplete-items hidden"></div>
                    </div>

                    {{-- Button Gunakan Lokasi Saya --}}
                    <button type="button"
                            id="btnCurrentLocation"
                            onclick="getCurrentBrowserLocation()"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-slate-800 hover:bg-slate-900 border border-slate-700 rounded-lg shadow-2xs transition whitespace-nowrap">
                        <span class="material-symbols-outlined text-[16px] text-brand-400">my_location</span>
                        <span id="btnCurrentLocationText">Gunakan Lokasi Saya</span>
                    </button>
                </div>
            </div>

            {{-- Modal Map Container --}}
            <div class="p-4 space-y-3">
                <div class="relative">
                    <div id="leafletMap" class="map-container"></div>

                    {{-- Loading Indicator Overlay --}}
                    <div id="mapLoadingOverlay" class="absolute inset-0 bg-white/70 backdrop-blur-2xs z-20 flex flex-col items-center justify-center hidden rounded-lg">
                        <div class="w-7 h-7 border-3 border-brand-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                        <span class="text-xs font-semibold text-slate-800" id="mapLoadingText">Memeriksa lokasi &amp; mencocokkan wilayah RajaOngkir...</span>
                    </div>
                </div>

                {{-- PREVIEW LOKASI (SESUAI POIN 8 USER PROMPT) --}}
                <div id="locationPreviewCard" class="p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-1.5">
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-brand-600">near_me</span>
                            Preview Lokasi Pengiriman
                        </span>
                        {{-- Status Pills --}}
                        <div class="flex items-center gap-1.5" id="previewStatusBadges">
                            <span id="badgeLocationFound" class="hidden px-2 py-0.5 text-[10.5px] font-semibold bg-emerald-100 text-emerald-800 rounded">
                                ✓ Lokasi ditemukan
                            </span>
                            <span id="badgeRegionMatched" class="hidden px-2 py-0.5 text-[10.5px] font-semibold bg-emerald-100 text-emerald-800 rounded">
                                ✓ Wilayah RajaOngkir cocok
                            </span>
                            <span id="badgeRegionUnmatched" class="hidden px-2 py-0.5 text-[10.5px] font-semibold bg-amber-100 text-amber-800 rounded">
                                ⚠ Wilayah belum cocok otomatis
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                        <div class="sm:col-span-2">
                            <span class="text-[11px] font-medium text-slate-400 block mb-0.5">Alamat Lengkap</span>
                            <p id="previewAddressText" class="font-semibold text-slate-800 leading-snug">
                                Klik titik di peta atau gunakan lokasi saat ini...
                            </p>
                        </div>
                        <div>
                            <span class="text-[11px] font-medium text-slate-400 block mb-0.5">Koordinat</span>
                            <p id="previewCoordsText" class="font-mono text-slate-600 font-medium">-</p>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-200/80 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-medium text-slate-400 block mb-0.5">Wilayah RajaOngkir</span>
                            <p id="previewRegionText" class="font-bold text-brand-700">
                                Menunggu pilihan titik...
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 block" id="previewRegionIdText"></span>
                        </div>
                    </div>

                    {{-- Alert jika wilayah tidak cocok otomatis (Poin 6) --}}
                    <div id="unmatchedAlert" class="hidden p-2.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-amber-600 mt-0.5">warning</span>
                        <span>Wilayah belum dapat dicocokkan secara otomatis. Silakan pilih wilayah secara manual di form setelah menerapkan lokasi ini.</span>
                    </div>
                </div>
            </div>

            {{-- Modal Footer Actions --}}
            <div class="px-5 py-3.5 bg-slate-100 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-between gap-2">
                <span class="text-[11px] text-slate-500">
                    Geser peta atau klik titik mana saja untuk menyesuaikan posisi marker.
                </span>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="button" onclick="closeMapModal()" class="btn btn-secondary btn-sm flex-1 sm:flex-none justify-center">
                        Ubah Lokasi
                    </button>
                    <button type="button" id="btnApplyLocation" onclick="applySelectedLocation()" class="btn btn-primary btn-sm flex-1 sm:flex-none justify-center">
                        <span class="material-symbols-outlined text-[16px]">check</span>
                        <span>Gunakan Lokasi Ini</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    // State Variabel Modal & Form
    let activeTarget = 'sender'; // 'sender' atau 'receiver'
    let mapInstance = null;
    let mapMarker = null;
    let selectedGeo = {
        lat: null,
        lng: null,
        formatted_address: '',
        village: '',
        district: '',
        city: '',
        province: '',
        rajaongkir_region_id: '',
        rajaongkir_label: '',
        matched: false
    };

    // Default Kota Surabaya (Drop Point Bratang)
    const DEFAULT_LAT = -7.291240;
    const DEFAULT_LNG = 112.759210;

    // Rate konfigurasi City Courier
    const RATES = {
        motor: { base: 8000, perKm: 2500, maxWeight: 20 },
        mobil: { base: 20000, perKm: 4500, maxWeight: 150 }
    };

    let currentVehicle = 'motor';

    document.addEventListener('DOMContentLoaded', function () {
        // Drop Point Selector
        const dpSelect = document.getElementById('quickDropPoint');
        if (dpSelect) {
            dpSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                if (!opt || !opt.value) return;

                document.getElementById('sender_name').value = opt.getAttribute('data-name') || '';
                document.getElementById('sender_phone').value = opt.getAttribute('data-phone') || '';
                document.getElementById('sender_address').value = opt.getAttribute('data-address') || '';

                const lat = parseFloat(opt.getAttribute('data-lat'));
                const lng = parseFloat(opt.getAttribute('data-lng'));

                if (!isNaN(lat) && !isNaN(lng)) {
                    document.getElementById('sender_latitude').value = lat;
                    document.getElementById('sender_longitude').value = lng;

                    // Match region via backend
                    matchLocationFromCoords('sender', lat, lng, opt.getAttribute('data-address'));
                }
            });
        }

        // Search debounce
        const searchInput = document.getElementById('mapSearchInput');
        let searchTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                const q = this.value.trim();
                const clearBtn = document.getElementById('btnClearMapSearch');
                if (clearBtn) clearBtn.classList.toggle('hidden', q.length === 0);

                if (q.length < 3) {
                    hideAutocomplete();
                    return;
                }
                searchTimer = setTimeout(() => {
                    executeGeocodingSearch(q);
                }, 400);
            });
        }

        recalculateCosts();
    });

    // ─── Modal Peta ──────────────────────────────────────────────
    function openMapModal(target) {
        activeTarget = target;
        const modal = document.getElementById('mapModal');
        const title = document.getElementById('modalMapTitle');

        if (title) {
            title.textContent = target === 'sender'
                ? 'Pilih Titik Penjemputan (Pengirim)'
                : 'Pilih Titik Tujuan (Penerima)';
        }

        modal.classList.remove('hidden');

        // Init Leaflet setelah modal terlihat
        setTimeout(() => {
            initLeafletMap();
        }, 150);
    }

    function closeMapModal() {
        document.getElementById('mapModal').classList.add('hidden');
        hideAutocomplete();
    }

    function initLeafletMap() {
        const container = document.getElementById('leafletMap');
        if (!container) return;

        // Ambil koordinat yang sudah ada di form target jika tersedia
        let targetLat = parseFloat(document.getElementById(activeTarget + '_latitude').value);
        let targetLng = parseFloat(document.getElementById(activeTarget + '_longitude').value);

        if (isNaN(targetLat) || isNaN(targetLng)) {
            targetLat = DEFAULT_LAT;
            targetLng = DEFAULT_LNG;
        }

        if (!mapInstance) {
            mapInstance = L.map('leafletMap', {
                center: [targetLat, targetLng],
                zoom: 15,
                zoomControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors | CityCourier',
                maxZoom: 19
            }).addTo(mapInstance);

            // Custom Icon Pin
            const customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div class="custom-map-pin"><div class="pin-inner"><span class="material-symbols-outlined text-[16px]">location_on</span></div></div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            mapMarker = L.marker([targetLat, targetLng], {
                draggable: true,
                icon: customIcon
            }).addTo(mapInstance);

            // Event Marker Dragend
            mapMarker.on('dragend', function (e) {
                const pos = e.target.getLatLng();
                onLocationChanged(pos.lat, pos.lng);
            });

            // Event Map Click
            mapInstance.on('click', function (e) {
                mapMarker.setLatLng(e.latlng);
                onLocationChanged(e.latlng.lat, e.latlng.lng);
            });
        } else {
            mapInstance.invalidateSize();
            mapInstance.setView([targetLat, targetLng], 15);
            mapMarker.setLatLng([targetLat, targetLng]);
        }

        // Langsung cek koordinat saat buka
        onLocationChanged(targetLat, targetLng);
    }

    // ─── Browser Geolocation (Poin 2 User Prompt) ────────────────
    function getCurrentBrowserLocation() {
        const btn = document.getElementById('btnCurrentLocation');
        const btnText = document.getElementById('btnCurrentLocationText');

        if (!navigator.geolocation) {
            alert('Browser Anda tidak mendukung deteksi lokasi (Geolocation).');
            return;
        }

        btnText.textContent = 'Mendeteksi...';
        btn.classList.add('opacity-75');

        navigator.geolocation.getCurrentPosition(
            function (position) {
                btnText.textContent = 'Gunakan Lokasi Saya';
                btn.classList.remove('opacity-75');

                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (mapInstance && mapMarker) {
                    mapInstance.setView([lat, lng], 16);
                    mapMarker.setLatLng([lat, lng]);
                }

                onLocationChanged(lat, lng);
            },
            function (error) {
                btnText.textContent = 'Gunakan Lokasi Saya';
                btn.classList.remove('opacity-75');
                let msg = 'Gagal mengakses lokasi Anda.';
                if (error.code === error.PERMISSION_DENIED) {
                    msg = 'Izin lokasi ditolak oleh browser. Silakan izinkan akses lokasi atau cari alamat manual.';
                }
                alert(msg);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    // ─── Reverse Geocoding & RajaOngkir Matching ─────────────────
    function onLocationChanged(lat, lng) {
        showMapLoading(true, 'Memeriksa titik & mencocokkan wilayah RajaOngkir...');

        selectedGeo.lat = lat;
        selectedGeo.lng = lng;

        // Tampilkan koordinat di preview
        document.getElementById('previewCoordsText').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

        // Panggil endpoint backend matching
        fetch('{{ route("admin.shipments.match-region") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ lat: lat, lng: lng })
        })
        .then(res => res.json())
        .then(data => {
            showMapLoading(false);
            updateLocationPreview(data);
        })
        .catch(err => {
            showMapLoading(false);
            console.error('Match error:', err);
            updateLocationPreview({
                matched: false,
                formatted_address: `Titik koordinat (${lat.toFixed(6)}, ${lng.toFixed(6)})`,
                latitude: lat,
                longitude: lng
            });
        });
    }

    function updateLocationPreview(data) {
        const addressEl = document.getElementById('previewAddressText');
        const regionEl = document.getElementById('previewRegionText');
        const regionIdEl = document.getElementById('previewRegionIdText');

        const bFound = document.getElementById('badgeLocationFound');
        const bMatched = document.getElementById('badgeRegionMatched');
        const bUnmatched = document.getElementById('badgeRegionUnmatched');
        const unmatchedAlert = document.getElementById('unmatchedAlert');

        const addr = data.formatted_address || `Koordinat (${selectedGeo.lat?.toFixed(6)}, ${selectedGeo.lng?.toFixed(6)})`;
        addressEl.textContent = addr;
        selectedGeo.formatted_address = addr;

        if (data.matched) {
            selectedGeo.matched = true;
            selectedGeo.rajaongkir_region_id = data.rajaongkir_region_id || data.rajaongkir_subdistrict_id || data.rajaongkir_city_id;
            selectedGeo.rajaongkir_label = data.full_label || `${data.subdistrict_name}, ${data.district_name}, ${data.city_name}`;

            regionEl.textContent = selectedGeo.rajaongkir_label;
            regionIdEl.textContent = `RajaOngkir Region ID: ${selectedGeo.rajaongkir_region_id}`;

            bFound.classList.remove('hidden');
            bMatched.classList.remove('hidden');
            bUnmatched.classList.add('hidden');
            unmatchedAlert.classList.add('hidden');
        } else {
            selectedGeo.matched = false;
            selectedGeo.rajaongkir_region_id = '';
            selectedGeo.rajaongkir_label = '';

            regionEl.textContent = 'Wilayah belum terdeteksi otomatis';
            regionIdEl.textContent = '';

            bFound.classList.remove('hidden');
            bMatched.classList.add('hidden');
            bUnmatched.classList.remove('hidden');
            unmatchedAlert.classList.remove('hidden');
        }
    }

    // ─── Terapkan Lokasi dari Modal ke Form (Poin 5 & 7) ──────────
    function applySelectedLocation() {
        if (!selectedGeo.lat || !selectedGeo.lng) {
            alert('Silakan pilih titik lokasi pada peta terlebih dahulu.');
            return;
        }

        const prefix = activeTarget; // 'sender' atau 'receiver'

        // 1. Isi Alamat Lengkap
        const addrField = document.getElementById(prefix + '_address');
        if (selectedGeo.formatted_address) {
            addrField.value = selectedGeo.formatted_address;
        }

        // 2. Simpan Koordinat
        document.getElementById(prefix + '_latitude').value = selectedGeo.lat;
        document.getElementById(prefix + '_longitude').value = selectedGeo.lng;

        // 3. Wilayah RajaOngkir
        const regionText = document.getElementById(prefix + 'RegionText');
        const regionBadge = document.getElementById(prefix + 'RegionBadge');
        const regionIdLabel = document.getElementById(prefix + 'RegionIdLabel');
        const inputId = document.getElementById(prefix === 'sender' ? 'sender_origin_id' : 'receiver_destination_id');
        const inputName = document.getElementById(prefix === 'sender' ? 'sender_origin_name' : 'receiver_destination_name');

        if (selectedGeo.matched && selectedGeo.rajaongkir_region_id) {
            inputId.value = selectedGeo.rajaongkir_region_id;
            inputName.value = selectedGeo.rajaongkir_label;

            regionText.textContent = selectedGeo.rajaongkir_label;
            regionBadge.classList.remove('hidden');
            regionIdLabel.textContent = `ID: ${selectedGeo.rajaongkir_region_id}`;

            // Sembunyikan manual box jika sebelumnya terbuka
            document.getElementById(prefix + 'ManualRegionBox').classList.add('hidden');
        } else {
            // Poin 6: Jika tidak cocok, aktifkan fallback manual
            inputId.value = '';
            inputName.value = '';
            regionText.textContent = 'Wilayah belum cocok. Silakan pilih manual di bawah.';
            regionBadge.classList.add('hidden');
            regionIdLabel.textContent = '';

            // Buka manual box
            document.getElementById(prefix + 'ManualRegionBox').classList.remove('hidden');
        }

        closeMapModal();
        recalculateCosts();
    }

    // ─── Geocoding Autocomplete Search ───────────────────────────
    function executeGeocodingSearch(query) {
        fetch(`{{ url('/api/geocoding/search') }}?q=${encodeURIComponent(query)}&limit=5`)
            .then(res => res.json())
            .then(res => {
                const list = res.data || [];
                renderAutocomplete(list);
            })
            .catch(err => {
                console.error('Search error:', err);
                hideAutocomplete();
            });
    }

    function renderAutocomplete(items) {
        const box = document.getElementById('autocompleteResults');
        box.innerHTML = '';

        if (!items || items.length === 0) {
            box.innerHTML = '<div class="autocomplete-item text-slate-400">Tidak ada hasil ditemukan</div>';
            box.classList.remove('hidden');
            return;
        }

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.textContent = item.formatted_address || item.street_name || 'Lokasi';
            div.addEventListener('click', function () {
                const lat = parseFloat(item.latitude);
                const lng = parseFloat(item.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    document.getElementById('mapSearchInput').value = item.formatted_address;
                    hideAutocomplete();

                    if (mapInstance && mapMarker) {
                        mapInstance.setView([lat, lng], 16);
                        mapMarker.setLatLng([lat, lng]);
                    }
                    onLocationChanged(lat, lng);
                }
            });
            box.appendChild(div);
        });

        box.classList.remove('hidden');
    }

    function hideAutocomplete() {
        const box = document.getElementById('autocompleteResults');
        if (box) box.classList.add('hidden');
    }

    function clearMapSearch() {
        const inp = document.getElementById('mapSearchInput');
        if (inp) inp.value = '';
        hideAutocomplete();
        const clearBtn = document.getElementById('btnClearMapSearch');
        if (clearBtn) clearBtn.classList.add('hidden');
    }

    function showMapLoading(show, text = 'Memuat...') {
        const overlay = document.getElementById('mapLoadingOverlay');
        const textEl = document.getElementById('mapLoadingText');
        if (overlay) {
            overlay.classList.toggle('hidden', !show);
            if (textEl) textEl.textContent = text;
        }
    }

    // ─── Manual Region Selection (Fallback) ──────────────────────
    function toggleManualRegion(prefix) {
        const box = document.getElementById(prefix + 'ManualRegionBox');
        if (box) {
            box.classList.toggle('hidden');
        }
    }

    function loadCities(prefix, provinceId) {
        const citySelect = document.getElementById(prefix + 'CitySelect');
        const districtSelect = document.getElementById(prefix + 'DistrictSelect');

        citySelect.innerHTML = '<option value="">Pilih Kota</option>';
        districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
        districtSelect.disabled = true;

        if (!provinceId) {
            citySelect.disabled = true;
            return;
        }

        citySelect.disabled = true;
        fetch(`{{ route('admin.shipments.regions.cities') }}?province_id=${provinceId}`)
            .then(res => res.json())
            .then(res => {
                citySelect.disabled = false;
                const list = res.data || [];
                list.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.city_id;
                    opt.textContent = `${c.type || 'Kota'} ${c.city_name}`;
                    opt.setAttribute('data-name', c.city_name);
                    citySelect.appendChild(opt);
                });
            })
            .catch(() => {
                citySelect.disabled = false;
            });
    }

    function loadDistricts(prefix, cityId) {
        const districtSelect = document.getElementById(prefix + 'DistrictSelect');
        districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';

        if (!cityId) {
            districtSelect.disabled = true;
            return;
        }

        districtSelect.disabled = true;
        fetch(`{{ route('admin.shipments.regions.districts') }}?city_id=${cityId}`)
            .then(res => res.json())
            .then(res => {
                districtSelect.disabled = false;
                const list = res.data || [];
                list.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.subdistrict_id;
                    opt.textContent = d.subdistrict_name;
                    opt.setAttribute('data-name', d.subdistrict_name);
                    districtSelect.appendChild(opt);
                });
            })
            .catch(() => {
                districtSelect.disabled = false;
            });
    }

    function applyManualDistrict(prefix) {
        const provSelect = document.getElementById(prefix + 'ProvSelect');
        const citySelect = document.getElementById(prefix + 'CitySelect');
        const districtSelect = document.getElementById(prefix + 'DistrictSelect');

        const distOpt = districtSelect.options[districtSelect.selectedIndex];
        const cityOpt = citySelect.options[citySelect.selectedIndex];

        if (!distOpt || !distOpt.value) return;

        const subId = distOpt.value;
        const subName = distOpt.getAttribute('data-name') || distOpt.textContent;
        const cityName = cityOpt ? (cityOpt.getAttribute('data-name') || cityOpt.textContent) : '';
        const fullLabel = `${subName}, ${cityName}`;

        const inputId = document.getElementById(prefix === 'sender' ? 'sender_origin_id' : 'receiver_destination_id');
        const inputName = document.getElementById(prefix === 'sender' ? 'sender_origin_name' : 'receiver_destination_name');

        inputId.value = subId;
        inputName.value = fullLabel;

        document.getElementById(prefix + 'RegionText').textContent = fullLabel;
        document.getElementById(prefix + 'RegionBadge').classList.remove('hidden');
        document.getElementById(prefix + 'RegionBadge').innerHTML = '<span>✓ Dipilih manual</span>';
        document.getElementById(prefix + 'RegionIdLabel').textContent = `ID: ${subId}`;

        // Tutup manual box
        document.getElementById(prefix + 'ManualRegionBox').classList.add('hidden');
        recalculateCosts();
    }

    function matchLocationFromCoords(prefix, lat, lng, fullAddress) {
        fetch('{{ route("admin.shipments.match-region") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ lat: lat, lng: lng, formatted_address: fullAddress })
        })
        .then(res => res.json())
        .then(data => {
            const regionText = document.getElementById(prefix + 'RegionText');
            const regionBadge = document.getElementById(prefix + 'RegionBadge');
            const regionIdLabel = document.getElementById(prefix + 'RegionIdLabel');
            const inputId = document.getElementById(prefix === 'sender' ? 'sender_origin_id' : 'receiver_destination_id');
            const inputName = document.getElementById(prefix === 'sender' ? 'sender_origin_name' : 'receiver_destination_name');

            if (data.matched) {
                const subId = data.rajaongkir_region_id || data.rajaongkir_subdistrict_id || data.rajaongkir_city_id;
                inputId.value = subId;
                inputName.value = data.full_label;
                regionText.textContent = data.full_label;
                regionBadge.classList.remove('hidden');
                regionIdLabel.textContent = `ID: ${subId}`;
            }
            recalculateCosts();
        });
    }

    // ─── Kalkulasi Jarak & Biaya Pengiriman ───────────────────────
    function onVehicleChange(v) {
        currentVehicle = v;
        const cardMotor = document.getElementById('vehicleCardMotor');
        const cardMobil = document.getElementById('vehicleCardMobil');

        if (v === 'motor') {
            cardMotor.classList.add('border-brand-600', 'bg-brand-50');
            cardMotor.classList.remove('border-slate-200');
            cardMobil.classList.remove('border-brand-600', 'bg-brand-50');
            cardMobil.classList.add('border-slate-200');
        } else {
            cardMobil.classList.add('border-brand-600', 'bg-brand-50');
            cardMobil.classList.remove('border-slate-200');
            cardMotor.classList.remove('border-brand-600', 'bg-brand-50');
            cardMotor.classList.add('border-slate-200');
        }

        recalculateCosts();
    }

    function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function recalculateCosts() {
        const sLat = parseFloat(document.getElementById('sender_latitude').value);
        const sLng = parseFloat(document.getElementById('sender_longitude').value);
        const rLat = parseFloat(document.getElementById('receiver_latitude').value);
        const rLng = parseFloat(document.getElementById('receiver_longitude').value);

        let distanceKm = 0;
        if (!isNaN(sLat) && !isNaN(sLng) && !isNaN(rLat) && !isNaN(rLng)) {
            distanceKm = calculateHaversineDistance(sLat, sLng, rLat, rLng);
        }

        document.getElementById('labelDistance').textContent = `${distanceKm.toFixed(1)} km`;

        const cfg = RATES[currentVehicle] || RATES.motor;
        document.getElementById('labelBaseRate').textContent = `Rp${cfg.base.toLocaleString('id-ID')}`;
        document.getElementById('labelPerKmRate').textContent = `Rp${cfg.perKm.toLocaleString('id-ID')}`;

        let distanceCost = 0;
        if (distanceKm > 2) {
            distanceCost = Math.round((distanceKm - 2) * cfg.perKm);
        }
        document.getElementById('labelDistanceCost').textContent = `Rp${distanceCost.toLocaleString('id-ID')}`;

        const totalCalculated = Math.max(cfg.base, cfg.base + distanceCost);

        const costInput = document.getElementById('shipping_cost');
        costInput.value = totalCalculated;

        updateTotalCostDisplay(totalCalculated);
    }

    function onManualCostInput(val) {
        const cost = parseInt(val) || 0;
        updateTotalCostDisplay(cost);
    }

    function updateTotalCostDisplay(cost) {
        document.getElementById('total_cost').value = cost;
        document.getElementById('labelTotalCost').textContent = `Rp${cost.toLocaleString('id-ID')}`;
    }
</script>
@endpush
