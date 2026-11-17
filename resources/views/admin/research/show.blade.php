@extends('layouts.admin')

@section('title', 'Detail Responden ' . $submission->reference_code)

@push('styles')
<style>
    .detail-shell { display: flex; flex-direction: column; gap: 16px; }
    .detail-top {
        border: 1px solid #E2E8F0; border-radius: 14px; background: #fff; padding: 18px 20px;
    }
    .detail-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 22px; font-weight: 700; color: #0F172A; letter-spacing: .01em; }
    .meta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .meta-item { border: 1px solid #E8EEF5; border-radius: 10px; background: #F8FAFC; padding: 10px 12px; }
    .meta-item .k { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: #94A3B8; margin-bottom: 2px; }
    .meta-item .v { font-size: 12.5px; color: #1E293B; font-weight: 500; }
    .answer-section { border: 1px solid #E2E8F0; border-radius: 14px; background: #fff; overflow: hidden; }
    .answer-head { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #EDF2F8; background: #FBFDFF; }
    .answer-key { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; background: #EAF6FF; color: #0079CE; font-size: 11px; font-weight: 700; }
    .answer-title { font-size: 13px; font-weight: 600; color: #1E293B; }
    .answer-count { margin-left: auto; font-size: 10.5px; color: #94A3B8; }
    .qa { display: grid; grid-template-columns: minmax(0, 300px) minmax(0, 1fr); gap: 16px; padding: 13px 16px; border-bottom: 1px dashed #EDF2F8; }
    .qa:last-child { border-bottom: 0; }
    .qa-q { font-size: 12px; color: #64748B; line-height: 1.6; }
    .qa-q .num { font-family: ui-monospace, monospace; font-size: 10.5px; color: #94A3B8; margin-right: 4px; }
    .qa-a { font-size: 12.5px; color: #1E293B; line-height: 1.65; overflow-wrap: anywhere; white-space: pre-line; }
    .qa-a.is-empty { color: #A3B1C0; font-style: italic; }
    .chip-list { display: flex; flex-wrap: wrap; gap: 5px; margin: 0; padding: 0; list-style: none; }
    .chip-list li { border: 1px solid #DDEBF7; border-radius: 6px; background: #F4FAFF; color: #27546E; padding: 3px 7px; font-size: 11.5px; line-height: 1.4; }
    .contact-box { display: flex; align-items: flex-start; gap: 8px; border: 1px solid #FDE3C8; border-radius: 9px; background: #FFFBF5; padding: 9px 11px; }
    .contact-box .material-symbols-outlined { font-size: 17px; color: #C2410C; }
    .skip-note { display: flex; align-items: flex-start; gap: 8px; border: 1px solid #E6EEF7; border-radius: 10px; background: #F9FBFE; padding: 10px 12px; color: #64748B; font-size: 11.5px; line-height: 1.6; }
    @media (max-width: 767px) { .qa { grid-template-columns: 1fr; gap: 5px; } .meta-grid { grid-template-columns: 1fr; } }
    @media print { .no-print { display: none !important; } }
</style>
@endpush

@section('content')
<div class="detail-shell">
    {{-- Navigasi --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('admin.research.index') }}" class="btn btn-secondary btn-sm">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Kembali ke daftar responden</span>
        </a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.research.pdf-detail', $submission) }}" class="btn btn-secondary btn-sm">
                <span class="material-symbols-outlined">picture_as_pdf</span>
                <span>Unduh PDF</span>
            </a>
            @canPerm('research_study.delete')
            <form action="{{ route('admin.research.destroy', $submission) }}" method="POST"
                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus jawaban responden ini? Data yang dihapus tidak dapat dikembalikan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
                    <span class="material-symbols-outlined">delete</span>
                    <span>Hapus data</span>
                </button>
            </form>
            @endcanPerm
        </div>
    </div>

    {{-- Identitas responden --}}
    <div class="detail-top">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-[10.5px] font-mono uppercase tracking-[.12em] text-slate-400">Kode responden</div>
                <div class="detail-code">{{ $submission->reference_code }}</div>
                <div class="mt-1 text-[11.5px] text-slate-500">
                    Dikirim {{ $submission->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WIB
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @php
                    $roleBadge = match($submission->role) {
                        'customer' => 'badge-blue',
                        'courier' => 'badge-amber',
                        default => 'badge-indigo',
                    };
                @endphp
                <span class="badge {{ $roleBadge }}">{{ \App\Support\ResearchReport::roleLabel($submission->role) }}</span>
                <span class="badge badge-green"><span class="badge-dot"></span>Pengisian lengkap</span>
            </div>
        </div>

        <div class="meta-grid mt-4">
            <div class="meta-item">
                <div class="k">Usia</div>
                <div class="v">{{ $submission->age_range ?? '—' }}</div>
            </div>
            <div class="meta-item">
                <div class="k">Pengalaman layanan</div>
                <div class="v">{{ $submission->has_used_service ? 'Pernah menggunakan' : 'Belum pernah menggunakan' }}</div>
            </div>
            <div class="meta-item">
                <div class="k">Frekuensi penggunaan</div>
                <div class="v">{{ $submission->usage_frequency ?? '—' }}</div>
            </div>
            <div class="meta-item">
                <div class="k">Kesediaan wawancara</div>
                <div class="v">{{ \App\Support\ResearchReport::consentLabel($submission->interview_willing) }}</div>
            </div>
            <div class="meta-item">
                <div class="k">Bagian Customer</div>
                <div class="v">{{ $submission->customer_section_applicable ? 'Diisi' : 'Tidak relevan' }}</div>
            </div>
            <div class="meta-item">
                <div class="k">Bagian Courier</div>
                <div class="v">{{ $submission->courier_section_applicable ? 'Diisi' : 'Tidak relevan' }}</div>
            </div>
        </div>

        @if($submission->contact)
        <div class="contact-box mt-3">
            <span class="material-symbols-outlined">contact_phone</span>
            <div>
                <div class="text-[10.5px] font-medium uppercase tracking-wide text-orange-700">Kontak wawancara — hanya untuk admin</div>
                <div class="mt-0.5 font-mono text-[12.5px] text-slate-800">{{ $submission->contact }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Catatan bagian yang dilewati --}}
    @if(! $submission->customer_section_applicable || ! $submission->courier_section_applicable)
        <div class="skip-note">
            <span class="material-symbols-outlined text-[17px] text-slate-400">info</span>
            <div>
                @if(! $submission->customer_section_applicable)
                    Bagian Customer tidak ditampilkan karena responden tidak pernah menggunakan layanan pengiriman.
                @endif
                @if(! $submission->customer_section_applicable && ! $submission->courier_section_applicable) <br> @endif
                @if(! $submission->courier_section_applicable)
                    @if($submission->role === 'courier')
                        Bagian detail Courier tidak ditampilkan karena responden belum pernah bekerja sebagai kurir.
                    @else
                        Bagian Courier tidak ditampilkan karena responden bukan kurir.
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Jawaban --}}
    @foreach($sections as $section)
    <div class="answer-section">
        <div class="answer-head">
            <span class="answer-key">{{ $section['key'] }}</span>
            <span class="answer-title">{{ $section['title'] }}</span>
            <span class="answer-count">{{ count($section['numbers']) }} pertanyaan</span>
        </div>

        @foreach($section['numbers'] as $item)
        <div class="qa">
            <div class="qa-q">
                <span class="num">Q{{ $item['number'] }}</span>{{ $item['label'] }}
            </div>
            <div>
                @if(is_array($item['value']))
                    @if(count($item['value']) > 0)
                        <ul class="chip-list">
                            @foreach($item['value'] as $value)
                                <li>{{ $value }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="qa-a is-empty">Tidak dijawab</div>
                    @endif
                @elseif($item['number'] === 27 && $item['value'])
                    <div class="font-mono text-[12.5px] text-slate-800">{{ $item['value'] }}</div>
                @elseif(filled($item['value']))
                    <div class="qa-a">{{ $item['value'] }}</div>
                @else
                    <div class="qa-a is-empty">Tidak dijawab</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endforeach

    <div class="no-print flex justify-end">
        <a href="{{ route('admin.research.index') }}" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Kembali ke daftar</span>
        </a>
    </div>
</div>
@endsection
