@extends('layouts.admin')

@section('title', 'Studi Pendahuluan — Data Responden')

@push('styles')
<style>
    .research-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        background: linear-gradient(180deg, #FFFFFF 0%, #F7FBFF 100%);
        padding: 20px 22px;
    }
    .research-hero::after {
        content: "";
        position: absolute;
        top: -60px;
        right: -40px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(closest-side, rgba(0, 149, 252, .14), transparent);
        pointer-events: none;
    }
    .research-hero .eyebrow {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 10.5px;
        letter-spacing: .13em;
        text-transform: uppercase;
        color: #64748B;
        margin-bottom: 6px;
    }
    .stat-card { border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; padding: 14px 15px; }
    .stat-card .label { font-size: 11.5px; color: #64748B; display: flex; align-items: center; gap: 6px; }
    .stat-card .value { font-size: 25px; line-height: 1.2; font-weight: 700; color: #0F172A; margin-top: 5px; font-variant-numeric: tabular-nums; }
    .stat-card .hint { font-size: 11px; color: #94A3B8; margin-top: 2px; }
    .stat-card .icon { margin-left: auto; font-size: 18px; color: #94A3B8; }
    .chart-card { border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; padding: 15px 16px 12px; }
    .chart-card h3 { font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 3px; }
    .chart-card p { font-size: 11px; color: #94A3B8; margin-bottom: 8px; }
    .chart-box { position: relative; height: 220px; }
    .chart-box.tall { height: 270px; }
    .role-pill { display: inline-flex; align-items: center; gap: 5px; font-weight: 500; }
    .role-dot { width: 6px; height: 6px; border-radius: 50%; }
    .table-note { font-size: 11px; color: #94A3B8; }
    .section-anchor { scroll-margin-top: 84px; }
    @media (max-width: 767px) {
        .research-hero { padding: 16px; }
    }
</style>
@endpush

@section('content')
<div class="space-y-5">
    {{-- Header --}}
    <div class="research-hero">
        <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow">Penelitian &middot; Tugas Akhir UCD</div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="page-title">Studi Pendahuluan</h1>
                    <span class="badge badge-gray">{{ $stats['total'] }} responden</span>
                </div>
                <p class="page-subtitle max-w-2xl">
                    Hasil pengisian formulir pengalaman dan kebutuhan pengguna layanan pengiriman barang.
                    Data ditampilkan langsung dari jawaban responden.
                </p>
            </div>

            <div class="page-actions">
                @canPerm('research_study.export')
                <a href="{{ route('admin.research.export.excel') }}" class="btn btn-primary">
                    <span class="material-symbols-outlined">table_view</span>
                    <span>Export Excel</span>
                </a>
                <a href="{{ route('admin.research.export.csv') }}" class="btn btn-secondary">
                    <span class="material-symbols-outlined">csv</span>
                    <span>CSV</span>
                </a>
                <a href="{{ route('admin.research.export.pdf') }}" class="btn btn-secondary">
                    <span class="material-symbols-outlined">picture_as_pdf</span>
                    <span>PDF</span>
                </a>
                @endcanPerm
            </div>
        </div>
    </div>

    {{-- Statistik --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">groups</span>Total responden<span class="material-symbols-outlined icon">science</span></div>
            <div class="value">{{ $stats['total'] }}</div>
            <div class="hint">Seluruh jawaban masuk</div>
        </div>
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">person</span>Customer<span class="material-symbols-outlined icon">shopping_bag</span></div>
            <div class="value">{{ $stats['customer'] }}</div>
            <div class="hint">Hanya peran Customer</div>
        </div>
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">two_wheeler</span>Courier<span class="material-symbols-outlined icon">local_shipping</span></div>
            <div class="value">{{ $stats['courier'] }}</div>
            <div class="hint">Hanya peran Courier</div>
        </div>
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">swap_horiz</span>Keduanya<span class="material-symbols-outlined icon">join_inner</span></div>
            <div class="value">{{ $stats['both'] }}</div>
            <div class="hint">Customer &amp; Courier</div>
        </div>
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">record_voice_over</span>Bersedia wawancara<span class="material-symbols-outlined icon">mic</span></div>
            <div class="value">{{ $stats['willing'] }}</div>
            <div class="hint">Bersedia dihubungi kembali</div>
        </div>
        <div class="stat-card">
            <div class="label"><span class="material-symbols-outlined text-[16px]">task_alt</span>Sudah mengisi<span class="material-symbols-outlined icon">check_circle</span></div>
            <div class="value">{{ $stats['submitted'] }}</div>
            <div class="hint">Formulir terkirim lengkap</div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card p-3 sm:p-4">
        <form method="GET" action="{{ route('admin.research.index') }}" class="grid grid-cols-2 items-end gap-3 md:grid-cols-4">
            <div class="col-span-2 md:col-span-4">
                <label for="filter-q" class="mb-1 block text-[11.5px] font-medium text-slate-500">Cari responden</label>
                <div class="search-box">
                    <span class="material-symbols-outlined">search</span>
                    <input id="filter-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-input" placeholder="Kode referensi, usia, frekuensi...">
                </div>
            </div>
            <div class="col-span-1">
                <label for="filter-role" class="mb-1 block text-[11.5px] font-medium text-slate-500">Peran</label>
                <select id="filter-role" name="role" class="form-input">
                    <option value="">Semua peran</option>
                    <option value="customer" @selected($filters['role'] === 'customer')>Customer</option>
                    <option value="courier" @selected($filters['role'] === 'courier')>Courier</option>
                    <option value="both" @selected($filters['role'] === 'both')>Keduanya</option>
                </select>
            </div>
            <div class="col-span-1">
                <label for="filter-from" class="mb-1 block text-[11.5px] font-medium text-slate-500">Dari tanggal</label>
                <input id="filter-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-input">
            </div>
            <div class="col-span-1">
                <label for="filter-to" class="mb-1 block text-[11.5px] font-medium text-slate-500">Sampai tanggal</label>
                <input id="filter-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-input">
            </div>
            <div class="col-span-1">
                <label for="filter-sort" class="mb-1 block text-[11.5px] font-medium text-slate-500">Urutkan</label>
                <select id="filter-sort" name="sort" class="form-input">
                    <option value="latest" @selected($filters['sort'] === 'latest')>Terbaru</option>
                    <option value="oldest" @selected($filters['sort'] === 'oldest')>Terlama</option>
                </select>
            </div>
            <div class="col-span-2 flex flex-wrap gap-2 md:col-span-4">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">filter_alt</span>
                    Terapkan filter
                </button>
                @if($filters['q'] !== '' || $filters['role'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['sort'] !== 'latest')
                <a href="{{ route('admin.research.index') }}" class="btn btn-secondary">
                    <span class="material-symbols-outlined">restart_alt</span>
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Responden --}}
    <div class="card overflow-hidden section-anchor" id="responden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
            <div>
                <h2 class="text-[13.5px] font-semibold text-slate-800">Data Responden</h2>
                <p class="table-note">Jawaban lengkap dibuka melalui tombol Detail agar tabel tetap ringkas.</p>
            </div>
            <span class="badge badge-blue">{{ $submissions->total() }} hasil</span>
        </div>

        @if($submissions->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">No.</th>
                        <th>ID Responden</th>
                        <th>Tanggal Pengisian</th>
                        <th>Peran Responden</th>
                        <th>Usia</th>
                        <th>Pengalaman Layanan</th>
                        <th>Kesediaan Wawancara</th>
                        <th>Status Pengisian</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $index => $submission)
                    @php
                        $roleBadge = match($submission->role) {
                            'customer' => 'badge-blue',
                            'courier' => 'badge-amber',
                            default => 'badge-indigo',
                        };
                        $roleText = match($submission->role) {
                            'customer' => 'Customer',
                            'courier' => 'Courier',
                            default => 'Keduanya',
                        };
                    @endphp
                    <tr>
                        <td class="text-slate-400 tabular-nums">{{ $submissions->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('admin.research.show', $submission) }}" class="font-mono text-[12px] font-semibold text-brand-700 hover:underline">
                                {{ $submission->reference_code }}
                            </a>
                            <div class="font-mono text-[10.5px] text-slate-400">#{{ $submission->id }}</div>
                        </td>
                        <td class="whitespace-nowrap">
                            <div class="text-[12.5px] text-slate-700">{{ $submission->submitted_at?->format('d/m/Y') ?? '—' }}</div>
                            <div class="font-mono text-[10.5px] text-slate-400">{{ $submission->submitted_at?->format('H:i') }} WIB</div>
                        </td>
                        <td>
                            <span class="badge {{ $roleBadge }}">{{ $roleText }}</span>
                        </td>
                        <td class="whitespace-nowrap text-[12.5px]">{{ $submission->age_range ?? '—' }}</td>
                        <td>
                            <div class="text-[12.5px] text-slate-700">{{ $submission->has_used_service ? 'Pernah menggunakan' : 'Belum pernah' }}</div>
                            <div class="text-[10.5px] text-slate-400">{{ $submission->usage_frequency ?? '—' }}</div>
                        </td>
                        <td>
                            @if($submission->interview_willing === 'ya')
                                <span class="badge badge-green"><span class="badge-dot"></span>Bersedia</span>
                            @elseif($submission->interview_willing === 'tidak')
                                <span class="badge badge-gray">Tidak bersedia</span>
                            @else
                                <span class="text-[11.5px] text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-green"><span class="badge-dot"></span>Lengkap</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.research.show', $submission) }}" class="btn-icon" title="Detail jawaban">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                @canPerm('research_study.delete')
                                <form action="{{ route('admin.research.destroy', $submission) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus jawaban responden ini? Data yang dihapus tidak dapat dikembalikan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Hapus responden">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endcanPerm
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            <div>
                Menampilkan <strong>{{ $submissions->firstItem() }}</strong>–<strong>{{ $submissions->lastItem() }}</strong>
                dari <strong>{{ $submissions->total() }}</strong> responden
            </div>
            <div>{{ $submissions->links() }}</div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">science</span>
            <div class="empty-title">Belum ada jawaban responden</div>
            <div class="empty-text">
                @if($filters['q'] !== '' || $filters['role'] !== '' || $filters['from'] !== '' || $filters['to'] !== '')
                    Tidak ada data yang cocok dengan filter. Coba ubah kata kunci atau rentang tanggal.
                @else
                    Data akan muncul di sini setelah responden mengirim formulir Studi Pendahuluan.
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Analisis --}}
    <div class="section-anchor" id="analisis">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-[15px] font-semibold text-slate-900">Analisis Hasil</h2>
                <p class="text-[12px] text-slate-500">Dihitung otomatis dari seluruh jawaban yang tersimpan. Grafik ikut berubah saat ada responden baru.</p>
            </div>
            <span class="badge badge-gray">Basis data: {{ $stats['total'] }} responden</span>
        </div>

        @if($stats['total'] === 0)
        <div class="card empty-state">
            <span class="material-symbols-outlined">query_stats</span>
            <div class="empty-title">Grafik belum tersedia</div>
            <div class="empty-text">Grafik akan tampil otomatis setelah ada jawaban responden.</div>
        </div>
        @else
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($charts as $chart)
            <div class="chart-card {{ in_array($chart['type'], ['horizontalBar'], true) ? 'xl:col-span-2' : '' }}">
                <h3>{{ $chart['title'] }}</h3>
                <p>{{ $chart['type'] === 'doughnut' ? 'Proporsi peran responden' : 'Jumlah responden per kategori' }}</p>
                <div class="chart-box {{ $chart['type'] === 'horizontalBar' ? 'tall' : '' }}">
                    <canvas id="{{ $chart['id'] }}" aria-label="{{ $chart['title'] }}" role="img"></canvas>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const charts = @json($charts);
    const blue = '#0095FC';
    const orange = '#FF5A00';
    const inks = ['#0095FC', '#FF5A00', '#0F172A', '#7DD3FC', '#FDBA74'];
    const gridColor = 'rgba(148, 163, 184, .16)';
    const tickColor = '#64748B';

    Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = tickColor;

    charts.forEach(function (chart) {
        const canvas = document.getElementById(chart.id);
        if (!canvas) return;

        const labels = chart.data.map(item => item.label);
        const values = chart.data.map(item => item.value);

        if (chart.type === 'doughnut') {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: inks, borderWidth: 0, hoverOffset: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, usePointStyle: true, pointStyle: 'circle', padding: 14 } }
                    }
                }
            });
            return;
        }

        const horizontal = chart.type === 'horizontalBar';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: horizontal
                        ? labels.map((_, i) => i === 0 ? orange : 'rgba(0, 149, 252, .82)')
                        : 'rgba(0, 149, 252, .82)',
                    hoverBackgroundColor: horizontal ? orange : blue,
                    borderRadius: 4,
                    maxBarThickness: horizontal ? 18 : 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: tickColor, precision: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { display: horizontal ? false : true, color: gridColor, drawBorder: false },
                        ticks: { color: tickColor, precision: 0 }
                    }
                }
            }
        });
    });
});
</script>
@endpush
