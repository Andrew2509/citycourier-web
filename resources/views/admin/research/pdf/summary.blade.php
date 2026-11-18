@php
    $ink = '#111827';
    $blue = '#0647FF';
    $muted = '#6B7280';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Studi Pendahuluan City Courier</title>
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: {{ $ink }}; line-height: 1.5; }
        h1 { font-size: 17px; margin: 0 0 4px; line-height: 1.3; }
        h2 { font-size: 11px; margin: 16px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #E5E7EB; color: {{ $ink }}; }
        h3 { font-size: 9.5px; margin: 0 0 5px; color: #374151; }
        p { margin: 0 0 8px; }
        .brand { font-size: 9px; font-weight: bold; color: {{ $blue }}; letter-spacing: .6px; }
        .meta { color: {{ $muted }}; font-size: 8.5px; }
        .hero { border-left: 3px solid {{ $blue }}; padding: 4px 0 4px 10px; margin-bottom: 12px; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .stats td { width: 16.6%; border: 1px solid #E5E7EB; background: #F9FAFB; padding: 7px 8px; }
        .stats .k { font-size: 7.5px; color: {{ $muted }}; text-transform: uppercase; letter-spacing: .4px; }
        .stats .v { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .grid td.left { padding-right: 8px; }
        .chart { border: 1px solid #E5E7EB; padding: 8px 9px; margin-bottom: 8px; }
        .chart table { width: 100%; border-collapse: collapse; }
        .chart td { font-size: 8px; padding: 1.5px 0; vertical-align: middle; }
        .chart .lbl { width: 42%; color: #4B5563; padding-right: 6px; }
        .chart .bar-cell { width: 48%; }
        .chart .bar { height: 6px; background: {{ $blue }}; }
        .chart .num { width: 10%; text-align: right; font-weight: bold; color: #374151; }
        .note { border: 1px solid #FDE68A; background: #FFFBEB; padding: 7px 9px; font-size: 8px; color: #78350F; margin-top: 10px; }
        .footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7.5px; color: #9CA3AF; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="footer">City Courier &middot; Laporan Studi Pendahuluan &middot; dibuat {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="brand">CITY COURIER &middot; PENELITIAN TUGAS AKHIR</div>
    <div class="hero">
        <h1>Laporan Studi Pendahuluan</h1>
        <p class="meta">
            Pengalaman dan Kebutuhan Pengguna pada Layanan Pengiriman Barang<br>
            Pendekatan User-Centered Design (UCD) &middot; {{ $total }} responden &middot; dibuat {{ $generatedAt->format('d/m/Y H:i') }} WIB
        </p>
    </div>

    <h2>Ringkasan Responden</h2>
    <table class="stats">
        <tr>
            <td><div class="k">Total</div><div class="v">{{ $total }}</div></td>
            <td><div class="k">Customer</div><div class="v">{{ $stats['customer'] }}</div></td>
            <td><div class="k">Courier</div><div class="v">{{ $stats['courier'] }}</div></td>
            <td><div class="k">Keduanya</div><div class="v">{{ $stats['both'] }}</div></td>
            <td><div class="k">Bersedia wawancara</div><div class="v">{{ $stats['willing'] }}</div></td>
            <td><div class="k">Sudah mengisi</div><div class="v">{{ $total }}</div></td>
        </tr>
    </table>

    <h2>Grafik Utama</h2>
    <table class="grid">
        <tr>
            @foreach($charts as $index => $chart)
                @if($index % 2 === 0 && $index > 0)
                    </tr><tr>
                @endif
                <td class="{{ $index % 2 === 0 ? 'left' : '' }}">
                    @php
                        $max = max(1, collect($chart['data'])->max('value') ?? 1);
                        $totalChart = collect($chart['data'])->sum('value');
                    @endphp
                    <div class="chart">
                        <h3>{{ $chart['title'] }}</h3>
                        <table>
                            @foreach($chart['data'] as $row)
                                <tr>
                                    <td class="lbl">{{ $row['label'] }}</td>
                                    <td class="bar-cell">
                                        <div class="bar" style="width: {{ $max > 0 ? round(($row['value'] / $max) * 100) : 0 }}%;"></div>
                                    </td>
                                    <td class="num">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                            @if($totalChart === 0)
                                <tr><td class="lbl" colspan="3" style="color:#9CA3AF;">Belum ada jawaban pada kategori ini.</td></tr>
                            @endif
                        </table>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="note">
        Laporan ini dihasilkan otomatis dari jawaban responden yang tersimpan pada basis data City Courier.
        Grafik batang menunjukkan jumlah responden per kategori; data tidak menggunakan angka perkiraan.
    </div>
</body>
</html>
