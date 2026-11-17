<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchSubmission;
use App\Support\ResearchReport;
use App\Support\ResearchSurvey;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchStudyAdminController extends Controller
{
    /**
     * Daftar responden, statistik, dan grafik analisis.
     */
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => $request->query('sort') === 'oldest' ? 'oldest' : 'latest',
        ];

        $query = ResearchSubmission::query();

        if ($filters['q'] !== '') {
            $search = '%' . $filters['q'] . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('reference_code', 'like', $search)
                    ->orWhere('age_range', 'like', $search)
                    ->orWhere('usage_frequency', 'like', $search)
                    ->orWhere('role', 'like', $search);
            });
        }

        if (in_array($filters['role'], ['customer', 'courier', 'both'], true)) {
            $query->where('role', $filters['role']);
        }

        if ($filters['from'] !== '') {
            $query->whereDate('submitted_at', '>=', $filters['from']);
        }

        if ($filters['to'] !== '') {
            $query->whereDate('submitted_at', '<=', $filters['to']);
        }

        $submissions = $query
            ->orderBy('submitted_at', $filters['sort'] === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $all = ResearchSubmission::query()->get();

        $stats = [
            'total' => $all->count(),
            'customer' => $all->where('role', 'customer')->count(),
            'courier' => $all->where('role', 'courier')->count(),
            'both' => $all->where('role', 'both')->count(),
            'willing' => $all->where('interview_willing', 'ya')->count(),
            'submitted' => $all->whereNotNull('submitted_at')->count(),
        ];

        return view('admin.research.index', [
            'submissions' => $submissions,
            'filters' => $filters,
            'stats' => $stats,
            'charts' => $this->charts($all),
        ]);
    }

    public function show(ResearchSubmission $submission): View
    {
        return view('admin.research.show', [
            'submission' => $submission,
            'sections' => ResearchReport::sections($submission),
        ]);
    }

    public function destroy(ResearchSubmission $submission)
    {
        $reference = $submission->reference_code;
        $submission->delete();

        return redirect()
            ->route('admin.research.index')
            ->with('success', 'Jawaban responden ' . $reference . ' berhasil dihapus.');
    }

    /**
     * Ekspor Excel seluruh jawaban (satu responden satu baris).
     */
    public function exportExcel(): StreamedResponse
    {
        $submissions = ResearchSubmission::orderBy('submitted_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Responden');

        $headers = ['No', 'Kode Referensi', 'Tanggal Pengisian', 'Waktu Pengisian'];

        foreach (ResearchReport::SHORT_LABELS as $number => $label) {
            $headers[] = 'Q' . $number . '. ' . $label;
        }

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($submissions as $index => $submission) {
            $answers = ResearchReport::answers($submission);

            $line = [
                $index + 1,
                $submission->reference_code,
                $submission->submitted_at?->format('d/m/Y'),
                $submission->submitted_at?->format('H:i'),
            ];

            foreach (array_keys(ResearchReport::SHORT_LABELS) as $number) {
                $line[] = $this->exportValue($answers[$number] ?? null);
            }

            $sheet->fromArray($line, null, 'A' . $row);
            $row++;
        }

        $lastRow = max($row - 1, 1);
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->freezePane('E2');
        $sheet->setAutoFilter('A1:' . $lastColumn . $lastRow);

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(12);

        foreach (range(5, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setWidth(30);
        }

        $sheet->getStyle('A2:' . $lastColumn . $lastRow)->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        $filename = 'studi-pendahuluan-' . now()->format('Ymd-Hi') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Ekspor CSV (dibuka rapi di Microsoft Excel).
     */
    public function exportCsv(): StreamedResponse
    {
        $submissions = ResearchSubmission::orderBy('submitted_at')->get();

        $headers = ['No', 'Kode Referensi', 'Tanggal Pengisian', 'Waktu Pengisian'];

        foreach (ResearchReport::SHORT_LABELS as $number => $label) {
            $headers[] = 'Q' . $number . '. ' . $label;
        }

        $filename = 'studi-pendahuluan-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($submissions, $headers) {
            $handle = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';');

            foreach ($submissions as $index => $submission) {
                $answers = ResearchReport::answers($submission);

                $line = [
                    $index + 1,
                    $submission->reference_code,
                    $submission->submitted_at?->format('d/m/Y'),
                    $submission->submitted_at?->format('H:i'),
                ];

                foreach (array_keys(ResearchReport::SHORT_LABELS) as $number) {
                    $line[] = $this->exportValue($answers[$number] ?? null);
                }

                fputcsv($handle, $line, ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Laporan ringkasan penelitian (statistik + grafik utama).
     */
    public function exportPdf()
    {
        $all = ResearchSubmission::query()->get();

        return $this->pdfResponse('admin.research.pdf.summary', [
            'generatedAt' => now(),
            'total' => $all->count(),
            'stats' => [
                'customer' => $all->where('role', 'customer')->count(),
                'courier' => $all->where('role', 'courier')->count(),
                'both' => $all->where('role', 'both')->count(),
                'willing' => $all->where('interview_willing', 'ya')->count(),
            ],
            'charts' => $this->charts($all),
        ], 'laporan-studi-pendahuluan-' . now()->format('Ymd-Hi') . '.pdf');
    }

    /**
     * Detail satu responden dalam bentuk PDF.
     */
    public function exportDetailPdf(ResearchSubmission $submission)
    {
        return $this->pdfResponse('admin.research.pdf.detail', [
            'submission' => $submission,
            'sections' => ResearchReport::sections($submission),
            'generatedAt' => now(),
        ], 'responden-' . $submission->reference_code . '.pdf');
    }

    /**
     * Cegah jawaban teks bebas dibaca sebagai formula oleh Excel.
     */
    private function exportValue(mixed $value): string
    {
        $text = ResearchReport::formatValue($value);

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'" . $text;
        }

        return $text;
    }

    private function pdfResponse(string $view, array $data, string $filename)
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Data grafik, dihitung langsung dari seluruh baris database.
     *
     * @param  \Illuminate\Support\Collection<int, ResearchSubmission>  $all
     * @return array<int, array<string, mixed>>
     */
    private function charts($all): array
    {
        $countBy = function (array $labels, callable $resolver) use ($all): array {
            return array_map(function ($label) use ($all, $resolver) {
                return [
                    'label' => $label,
                    'value' => $all->filter(fn ($submission) => $resolver($submission) === $label)->count(),
                ];
            }, $labels);
        };

        $customer = fn (ResearchSubmission $submission, string $key) => $submission->customer_answers[$key] ?? null;
        $courier = fn (ResearchSubmission $submission, string $key) => $submission->courier_answers[$key] ?? null;

        $roleCounts = [
            ['label' => 'Customer', 'value' => $all->where('role', 'customer')->count()],
            ['label' => 'Courier', 'value' => $all->where('role', 'courier')->count()],
            ['label' => 'Keduanya', 'value' => $all->where('role', 'both')->count()],
        ];

        $scaleCounts = function (callable $resolver): array {
            $values = [];

            foreach (range(1, 5) as $score) {
                $values[] = [
                    'label' => (string) $score,
                    'value' => $this->countScale($resolver, $score),
                ];
            }

            return $values;
        };

        $collectOptions = function (array $sources): array {
            $counts = [];

            foreach ($this->allSubmissionsForCharts() as $submission) {
                foreach ($sources as [$answers, $key]) {
                    $values = $submission->{$answers}[$key] ?? [];

                    foreach ((array) $values as $value) {
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        $counts[$value] = ($counts[$value] ?? 0) + 1;
                    }
                }
            }

            arsort($counts);

            $rows = [];
            foreach (array_slice($counts, 0, 10, true) as $label => $value) {
                $rows[] = ['label' => $label, 'value' => $value];
            }

            return $rows;
        };

        $this->chartSource = $all;

        return [
            ['id' => 'roleChart', 'title' => 'Distribusi peran responden', 'type' => 'doughnut', 'data' => $roleCounts],
            ['id' => 'ageChart', 'title' => 'Distribusi usia responden', 'type' => 'bar', 'data' => $countBy(ResearchSurvey::AGE_RANGES, fn ($s) => $s->age_range)],
            ['id' => 'frequencyChart', 'title' => 'Frekuensi penggunaan layanan', 'type' => 'bar', 'data' => $countBy(ResearchSurvey::USAGE_FREQUENCIES, fn ($s) => $s->usage_frequency)],
            ['id' => 'customerDifficultyChart', 'title' => 'Kesulitan aplikasi Customer', 'type' => 'bar', 'data' => $countBy(ResearchSurvey::DIFFICULTY_LEVELS, fn ($s) => $customer($s, 'difficulty_frequency'))],
            ['id' => 'courierDifficultyChart', 'title' => 'Kesulitan aplikasi Courier', 'type' => 'bar', 'data' => $countBy(ResearchSurvey::DIFFICULTY_LEVELS, fn ($s) => $courier($s, 'difficulty_frequency'))],
            ['id' => 'findFeaturesChart', 'title' => 'Kemudahan menemukan fitur (Customer)', 'type' => 'bar', 'data' => $scaleCounts(fn ($s) => $customer($s, 'find_features_score'))],
            ['id' => 'statusClarityChart', 'title' => 'Kejelasan status pengiriman (Customer)', 'type' => 'bar', 'data' => $scaleCounts(fn ($s) => $customer($s, 'status_clarity_score'))],
            ['id' => 'orderClarityChart', 'title' => 'Kejelasan informasi pesanan (Courier)', 'type' => 'bar', 'data' => $scaleCounts(fn ($s) => $courier($s, 'order_clarity_score'))],
            ['id' => 'flowClarityChart', 'title' => 'Kejelasan tahapan pengiriman (Courier)', 'type' => 'bar', 'data' => $scaleCounts(fn ($s) => $courier($s, 'flow_clarity_score'))],
            ['id' => 'obstacleChart', 'title' => 'Kendala yang paling sering dipilih', 'type' => 'horizontalBar', 'data' => $collectOptions([['customer_answers', 'problem_areas'], ['courier_answers', 'problem_areas']])],
            ['id' => 'customerInfoChart', 'title' => 'Informasi yang dibutuhkan Customer', 'type' => 'horizontalBar', 'data' => $collectOptions([['customer_answers', 'important_info']])],
            ['id' => 'courierInfoChart', 'title' => 'Informasi yang dibutuhkan Courier', 'type' => 'horizontalBar', 'data' => $collectOptions([['courier_answers', 'important_info']])],
        ];
    }

    /** Sumber data sementara untuk agregasi pilihan ganda. */
    private $chartSource = null;

    private function allSubmissionsForCharts()
    {
        return $this->chartSource ?? ResearchSubmission::all();
    }

    private function countScale(callable $resolver, int $score): int
    {
        return $this->allSubmissionsForCharts()->filter(function ($submission) use ($resolver, $score) {
            return (int) $resolver($submission) === $score;
        })->count();
    }
}
