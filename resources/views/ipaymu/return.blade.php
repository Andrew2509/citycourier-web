<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $cancelled ? 'Pembayaran Dibatalkan' : 'Pembayaran Selesai' }} · City Courier</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 28px 22px;
            text-align: center;
        }
        .icon {
            width: 56px; height: 56px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; line-height: 1;
            background: {{ $cancelled ? '#fee2e2' : '#dcfce7' }};
        }
        h1 { font-size: 18px; margin: 0 0 8px; }
        p  { font-size: 13px; line-height: 1.6; color: #475569; margin: 0 0 16px; }
        .rows { text-align: left; border-top: 1px solid #e2e8f0; padding-top: 14px; margin-top: 6px; }
        .row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 6px 0; }
        .row span:first-child { color: #64748b; }
        .row span:last-child { font-weight: 600; word-break: break-all; text-align: right; }
        .badge {
            display: inline-block; padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-paid    { background: #dcfce7; color: #166534; }
        .badge-failed  { background: #fee2e2; color: #991b1b; }
        .note { font-size: 12px; color: #94a3b8; margin-top: 18px; }
        .brand { font-size: 12px; font-weight: 700; color: #ec5b13; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">City Courier</div>
        <div class="icon">{{ $cancelled ? '✕' : '✓' }}</div>

        <h1>{{ $cancelled ? 'Pembayaran Dibatalkan' : 'Terima kasih' }}</h1>

        <p>
            @if ($cancelled)
                Kamu keluar dari halaman pembayaran iPaymu tanpa menyelesaikan transaksi.
                Pesanan tetap tersimpan dan bisa dibayar lagi dari aplikasi City Courier.
            @else
                Permintaan pembayaran sudah dikirim ke iPaymu. Status di aplikasi
                diperbarui otomatis setelah iPaymu mengonfirmasi pembayaran.
            @endif
        </p>

        @if ($payment)
            <div class="rows">
                <div class="row">
                    <span>Status</span>
                    <span>
                        @php
                            $badge = match ($payment->status) {
                                'paid' => ['badge-paid', 'Terbayar'],
                                'expired', 'failed', 'canceled' => ['badge-failed', 'Tidak berhasil'],
                                default => ['badge-pending', 'Menunggu konfirmasi'],
                            };
                        @endphp
                        <span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
                    </span>
                </div>
                <div class="row">
                    <span>Nominal</span>
                    <span>{{ $payment->formatted_amount }}</span>
                </div>
                @if ($payment->shipment_id)
                    <div class="row">
                        <span>Pengiriman</span>
                        <span>#{{ $payment->shipment_id }}</span>
                    </div>
                @endif
                @if ($reference !== '')
                    <div class="row">
                        <span>Referensi</span>
                        <span>{{ $reference }}</span>
                    </div>
                @endif
            </div>
        @endif

        <p class="note">
            Konfirmasi resmi hanya datang dari iPaymu. Jika status belum berubah,
            buka aplikasi City Courier beberapa saat lagi.
        </p>
    </div>
</body>
</html>
