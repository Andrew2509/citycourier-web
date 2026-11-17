<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResearchSubmissionRequest;
use App\Models\ResearchSubmission;
use App\Support\ResearchSurvey;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchStudyController extends Controller
{
    /**
     * Halaman formulir Studi Pendahuluan (multi-step, diisi oleh responden).
     */
    public function show(Request $request): View
    {
        $token = $request->session()->get('research_submission_token');

        // Token sekali pakai: jangan pakai ulang token yang sudah tersimpan
        // agar responden tidak terkena error duplikat saat membuka ulang form.
        if (! $token || ResearchSubmission::where('submission_token', $token)->exists()) {
            $token = (string) Str::uuid();
            $request->session()->put('research_submission_token', $token);
        }

        return view('research.form', [
            'options'         => ResearchSurvey::options(),
            'submissionToken' => $token,
        ]);
    }

    /**
     * Menyimpan jawaban responden secara terstruktur.
     */
    public function store(StoreResearchSubmissionRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $role = $data['role'];
        $includesCustomer = in_array($role, ['customer', 'both'], true);
        $includesCourier = in_array($role, ['courier', 'both'], true);
        $hasUsedService = $data['has_used_service'] === 'ya';
        $customerSection = $includesCustomer && $hasUsedService;

        $courierStatus = $includesCourier ? ($data['courier_status'] ?? null) : null;
        $courierSection = $includesCourier && in_array($courierStatus, ['active', 'former'], true);

        $services = array_values((array) ($data['services_used'] ?? []));
        $servicesOther = in_array('Lainnya', $services, true) ? ($data['services_other'] ?? null) : null;

        $interviewWilling = $data['interview_willing'] ?? null;
        $contact = $interviewWilling === 'ya' ? ($data['interview_contact'] ?? null) : null;

        try {
            $submission = ResearchSubmission::create([
                'submission_token'            => $data['submission_token'],
                'role'                        => $role,
                'age_range'                   => $data['age_range'],
                'has_used_service'            => $hasUsedService,
                'services_used'               => $services ?: null,
                'services_used_other'         => $servicesOther,
                'usage_frequency'             => $data['usage_frequency'],
                'customer_section_applicable' => $customerSection,
                'customer_answers'            => $customerSection ? $this->customerAnswers($data) : null,
                'courier_section_applicable'  => $courierSection,
                'courier_status'              => $courierStatus,
                'courier_answers'             => $courierSection ? $this->courierAnswers($data, $courierStatus) : null,
                'interview_willing'           => $interviewWilling,
                'contact'                     => $contact,
                'additional_suggestion'       => $data['additional_suggestion'] ?? null,
                'submitted_at'                => now(),
            ]);
        } catch (QueryException $e) {
            // Klik ganda atau submit paralel: token unik mencegah data ganda.
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages([
                    'submission_token' => 'Formulir ini sudah pernah dikirim.',
                ]);
            }

            throw $e;
        }

        $request->session()->forget('research_submission_token');
        $request->session()->put('research_success_reference', $submission->reference_code);
        $request->session()->put('research_success_at', now()->toIso8601String());

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('research.thankyou'),
            ], 201);
        }

        return redirect()->route('research.thankyou');
    }

    /**
     * Halaman konfirmasi setelah formulir berhasil dikirim.
     */
    public function thankyou(Request $request): Response|RedirectResponse
    {
        $reference = $request->session()->get('research_success_reference');

        if (! $reference) {
            return redirect()->route('research.create');
        }

        return response()
            ->view('research.thankyou', ['reference' => $reference])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    private function customerAnswers(array $data): array
    {
        return [
            'activities'            => $this->listOrNull($data['q6_activities'] ?? []),
            'activities_other'      => $this->otherText($data['q6_activities'] ?? [], 'Lainnya', $data['q6_other'] ?? null),
            'difficulty_frequency'  => $data['q7_difficulty'],
            'problem_areas'         => $this->listOrNull($data['q8_problem_areas'] ?? []),
            'problem_areas_other'   => $this->otherText($data['q8_problem_areas'] ?? [], 'Lainnya', $data['q8_other'] ?? null),
            'find_features_score'   => $data['q9_find_features'] ?? null,
            'status_clarity_score'  => $data['q10_status_clarity'] ?? null,
            'important_info'        => $this->listOrNull($data['q11_important_info'] ?? []),
            'important_info_other'  => $this->otherText($data['q11_important_info'] ?? [], 'Lainnya', $data['q11_other'] ?? null),
            'improvement_notes'     => $data['q12_improvement_notes'] ?? null,
            'common_obstacles'      => $data['q13_common_obstacles'] ?? null,
            'expected_features'     => $data['q14_expected_features'] ?? null,
        ];
    }

    private function courierAnswers(array $data, ?string $status): array
    {
        return [
            'status'                => $status,
            'status_label'          => ResearchSurvey::COURIER_STATUSES[$status] ?? null,
            'apps'                  => $this->listOrNull($data['q16_apps'] ?? []),
            'apps_other'            => $this->otherText($data['q16_apps'] ?? [], 'Aplikasi lainnya', $data['q16_other'] ?? null),
            'activities'            => $this->listOrNull($data['q17_activities'] ?? []),
            'activities_other'      => $this->otherText($data['q17_activities'] ?? [], 'Lainnya', $data['q17_other'] ?? null),
            'difficulty_frequency'  => $data['q18_difficulty'],
            'problem_areas'         => $this->listOrNull($data['q19_problem_areas'] ?? []),
            'problem_areas_other'   => $this->otherText($data['q19_problem_areas'] ?? [], 'Lainnya', $data['q19_other'] ?? null),
            'order_clarity_score'   => $data['q20_order_clarity'] ?? null,
            'flow_clarity_score'    => $data['q21_flow_clarity'] ?? null,
            'important_info'        => $this->listOrNull($data['q22_important_info'] ?? []),
            'important_info_other'  => $this->otherText($data['q22_important_info'] ?? [], 'Lainnya', $data['q22_other'] ?? null),
            'common_obstacles'      => $data['q23_common_obstacles'] ?? null,
            'improvement_notes'     => $data['q24_improvement_notes'] ?? null,
            'expected_features'     => $data['q25_expected_features'] ?? null,
        ];
    }

    private function listOrNull(array $values): ?array
    {
        $values = array_values(array_filter($values, fn ($value) => is_string($value) && $value !== ''));

        return $values === [] ? null : $values;
    }

    private function otherText(array $values, string $trigger, ?string $text): ?string
    {
        return in_array($trigger, $values, true) ? $text : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
