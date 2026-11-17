@php
    $isOn = (bool) ($enabled ?? true);
@endphp

<form method="POST" action="{{ route('admin.settings.providers.toggle', $provider) }}" class="inline-flex items-center">
    @csrf
    <input type="hidden" name="enabled" value="{{ $isOn ? '0' : '1' }}">
    <button type="submit"
            title="{{ $isOn ? 'Klik untuk mematikan provider ini' : 'Klik untuk mengaktifkan provider ini' }}"
            aria-label="Toggle {{ $provider }} {{ $isOn ? 'off' : 'on' }}"
            class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-bold transition {{ $isOn ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-300 bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
        <span class="relative inline-flex h-4 w-7 items-center rounded-full transition {{ $isOn ? 'bg-emerald-500' : 'bg-slate-400' }}">
            <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition {{ $isOn ? 'translate-x-3.5' : 'translate-x-0.5' }}"></span>
        </span>
        <span>{{ $isOn ? 'ON' : 'OFF' }}</span>
    </button>
</form>
