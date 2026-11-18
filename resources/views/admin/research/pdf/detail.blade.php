@php
    $ink = '#111827';
    $blue = '#0647FF';
    $muted = '#6B7280';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Responden {{ $submission->reference_code }}</title>
    <style>
        @page { margin: 30px 32px 34px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: {{ $ink }}; line-height: 1.55; }
        h1 { font-size: 15px; margin: 0 0 3px; }
        h2 { font-size: 10.5px; margin: 16px 0 0; padding: 6px 8px; background: #F3F4F6; border-left: 3px solid {{ $blue }}; }
        p { margin: 0; }
        .brand { font-size: 8.5px; font-weight: bold; color: {{ $blue }}; letter-spacing: .6px; }
        .meta { color: {{ $muted }}; font-size: 8.5px; }
        .hero { border-bottom: 1px solid #E5E7EB; padding-bottom: 9px; margin-bottom: 10px; }
        .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 13px; font-weight: bold; }
        .info { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
        .info td { border: 1px solid #E5E7EB; padding: 5px 7px; width: 33.3%; vertical-align: top; }
        .info .k { font-size: 7px; text-transform: uppercase; letter-spacing: .4px; color: #9CA3AF; }
        .info .v { font-size: 8.5px; font-weight: bold; }
        .qa { border-bottom: 1px solid #F3F4F6; padding: 7px 8px; page-break-inside: avoid; }
        .qa .q { font-size: 8.5px; color: {{ $muted }}; margin-bottom: 2px; }
        .qa .q .num { font-family: 'DejaVu Sans Mono', monospace; color: #9CA3AF; margin-right: 4px; }
        .qa .a { font-size: 9.5px; }
        .qa .a.empty { color: #9CA3AF; font-style: italic; }
        .contact { border: 1px solid #FDBA74; background: #FFF7ED; padding: 6px 8px; margin-top: 8px; font-size: 8.5px; }
        .skip { border: 1px solid #E5E7EB; background: #F9FAFB; padding: 6px 8px; font-size: 8px; color: {{ $muted }}; margin-top: 8px; }
        .footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7.5px; color: #9CA3AF; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">City Courier &middot; Studi Pendahuluan &middot; {{ $submission->reference_code }} &middot; halaman dokumen penelitian</div>

    <div class="brand">CITY COURIER &middot; HASIL STUDI PENDAHULUAN</div>
    <div class="hero">
        <h1>Detail Jawaban Responden</h1>
        <p class="meta">
            Kode <span class="code">{{ $submission->reference_code }}</span> &middot;
            dikirim {{ $submission->submitted_at?->format('d/m/Y H:i') ?? '—' }} WIB &middot;
            dicetak {{ $generatedAt->format('d/m/Y H:i') }} WIB
        </p>
    </div>

    <table class="info">
        <tr>
            <td><div class="k">Peran</div><div class="v">{{ \App\Support\ResearchReport::roleLabel($submission->role) }}</div></td>
            <td><div class="k">Usia</div><div class="v">{{ $submission->age_range ?? '—' }}</div></td>
            <td><div class="k">Frekuensi penggunaan</div><div class="v">{{ $submission->usage_frequency ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="k">Pengalaman layanan</div><div class="v">{{ $submission->has_used_service ? 'Pernah' : 'Belum pernah' }}</div></td>
            <td><div class="k">Bagian Customer</div><div class="v">{{ $submission->customer_section_applicable ? 'Diisi' : 'Tidak relevan' }}</div></td>
            <td><div class="k">Bagian Courier</div><div class="v">{{ $submission->courier_section_applicable ? 'Diisi' : 'Tidak relevan' }}</div></td>
        </tr>
    </table>

    @if(! $submission->customer_section_applicable || ! $submission->courier_section_applicable)
        <div class="skip">
            @if(! $submission->customer_section_applicable) Bagian Customer tidak ditampilkan karena responden belum pernah menggunakan layanan pengiriman. @endif
            @if(! $submission->courier_section_applicable) Bagian Courier tidak ditampilkan karena responden bukan kurir atau belum pernah bekerja sebagai kurir. @endif
        </div>
    @endif

    @foreach($sections as $section)
        <h2>{{ $section['key'] }}. {{ $section['title'] }}</h2>

        @foreach($section['numbers'] as $item)
            <div class="qa">
                <div class="q"><span class="num">Q{{ $item['number'] }}</span>{{ $item['label'] }}</div>
                @if(is_array($item['value']))
                    @if(count($item['value']) > 0)
                        <div class="a">{{ implode(' • ', $item['value']) }}</div>
                    @else
                        <div class="a empty">Tidak dijawab</div>
                    @endif
                @elseif(filled($item['value']))
                    <div class="a">{{ $item['value'] }}</div>
                @else
                    <div class="a empty">Tidak dijawab</div>
                @endif
            </div>
        @endforeach
    @endforeach

    @if($submission->contact)
        <div class="contact">
            <strong>Kontak wawancara:</strong> {{ $submission->contact }}
            <span style="color:#9A3412;">(hanya untuk admin peneliti)</span>
        </div>
    @endif
</body>
</html>
