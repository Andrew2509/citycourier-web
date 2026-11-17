<?php

namespace App\Http\Requests;

use App\Support\ResearchSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResearchSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['services_other', 'q6_other', 'q8_other', 'q11_other', 'q16_other', 'q17_other', 'q19_other', 'q22_other', 'interview_contact'] as $field) {
            $value = $this->input($field);
            $trimmed[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        foreach (['q12_improvement_notes', 'q13_common_obstacles', 'q14_expected_features', 'q23_common_obstacles', 'q24_improvement_notes', 'q25_expected_features', 'additional_suggestion'] as $field) {
            $value = $this->input($field);
            $trimmed[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        foreach (['q9_find_features', 'q10_status_clarity', 'q20_order_clarity', 'q21_flow_clarity'] as $field) {
            $value = $this->input($field);
            $trimmed[$field] = ($value === null || $value === '') ? null : (int) $value;
        }

        $this->merge($trimmed);
    }

    public function rules(): array
    {
        $role = $this->input('role');
        $includesCustomer = in_array($role, ['customer', 'both'], true);
        $includesCourier = in_array($role, ['courier', 'both'], true);
        $hasUsedService = $this->input('has_used_service') === 'ya';
        $customerSection = $includesCustomer && $hasUsedService;
        $courierStatus = $this->input('courier_status');
        $courierDetails = $includesCourier && in_array($courierStatus, ['active', 'former'], true);

        $rules = [
            'submission_token' => [
                'required',
                'uuid',
                Rule::unique('research_submissions', 'submission_token'),
            ],
            'role'                => ['required', Rule::in(array_keys(ResearchSurvey::ROLES))],
            'age_range'           => ['required', Rule::in(ResearchSurvey::AGE_RANGES)],
            'has_used_service'    => ['required', Rule::in(['ya', 'tidak'])],
            'services_used'       => ['nullable', 'array', 'max:8'],
            'services_used.*'     => [Rule::in(ResearchSurvey::SERVICES)],
            'services_other'      => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => $this->hasOption('services_used', 'Lainnya')),
            ],
            'usage_frequency'     => ['required', Rule::in(ResearchSurvey::USAGE_FREQUENCIES)],
            'interview_willing'   => ['nullable', Rule::in(['ya', 'tidak'])],
            'interview_contact'   => ['nullable', 'string', 'max:120'],
            'additional_suggestion' => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
            'website'             => ['prohibited'],
        ];

        if ($customerSection) {
            $rules += [
                'q6_activities'          => ['nullable', 'array', 'max:8'],
                'q6_activities.*'        => [Rule::in(ResearchSurvey::CUSTOMER_ACTIVITIES)],
                'q6_other'               => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q6_activities', 'Lainnya')),
                ],
                'q7_difficulty'          => ['required', Rule::in(ResearchSurvey::DIFFICULTY_LEVELS)],
                'q8_problem_areas'       => ['nullable', 'array', 'max:11'],
                'q8_problem_areas.*'     => [Rule::in(ResearchSurvey::CUSTOMER_PROBLEM_AREAS)],
                'q8_other'               => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q8_problem_areas', 'Lainnya')),
                ],
                'q9_find_features'       => ['nullable', 'integer', 'between:1,5'],
                'q10_status_clarity'     => ['nullable', 'integer', 'between:1,5'],
                'q11_important_info'     => ['nullable', 'array', 'max:10'],
                'q11_important_info.*'   => [Rule::in(ResearchSurvey::CUSTOMER_IMPORTANT_INFO)],
                'q11_other'              => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q11_important_info', 'Lainnya')),
                ],
                'q12_improvement_notes'  => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
                'q13_common_obstacles'   => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
                'q14_expected_features'  => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
            ];
        }

        if ($includesCourier) {
            $rules['courier_status'] = ['required', Rule::in(array_keys(ResearchSurvey::COURIER_STATUSES))];
        }

        if ($courierDetails) {
            $rules += [
                'q16_apps'               => ['nullable', 'array', 'max:8'],
                'q16_apps.*'             => [Rule::in(ResearchSurvey::COURIER_APPS)],
                'q16_other'              => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q16_apps', 'Aplikasi lainnya')),
                ],
                'q17_activities'         => ['nullable', 'array', 'max:10'],
                'q17_activities.*'       => [Rule::in(ResearchSurvey::COURIER_ACTIVITIES)],
                'q17_other'              => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q17_activities', 'Lainnya')),
                ],
                'q18_difficulty'         => ['required', Rule::in(ResearchSurvey::DIFFICULTY_LEVELS)],
                'q19_problem_areas'      => ['nullable', 'array', 'max:11'],
                'q19_problem_areas.*'    => [Rule::in(ResearchSurvey::COURIER_PROBLEM_AREAS)],
                'q19_other'              => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q19_problem_areas', 'Lainnya')),
                ],
                'q20_order_clarity'      => ['nullable', 'integer', 'between:1,5'],
                'q21_flow_clarity'       => ['nullable', 'integer', 'between:1,5'],
                'q22_important_info'     => ['nullable', 'array', 'max:13'],
                'q22_important_info.*'   => [Rule::in(ResearchSurvey::COURIER_IMPORTANT_INFO)],
                'q22_other'              => [
                    'nullable',
                    'string',
                    'max:120',
                    Rule::requiredIf(fn () => $this->hasOption('q22_important_info', 'Lainnya')),
                ],
                'q23_common_obstacles'   => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
                'q24_improvement_notes'  => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
                'q25_expected_features'  => ['nullable', 'string', 'max:' . ResearchSurvey::TEXT_LIMIT],
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $sessionToken = $this->session()->get('research_submission_token');

                if (! $sessionToken || $this->input('submission_token') !== $sessionToken) {
                    $validator->errors()->add(
                        'submission_token',
                        'Sesi formulir sudah tidak berlaku. Muat ulang halaman, lalu isi kembali formulir.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'required'          => ':attribute wajib diisi.',
            'in'                => 'Pilihan :attribute tidak dikenali. Muat ulang halaman lalu coba lagi.',
            'array'             => ':attribute harus berupa pilihan.',
            'integer'           => ':attribute harus berupa angka.',
            'between'           => ':attribute harus bernilai antara 1 sampai 5.',
            'string'            => ':attribute harus berupa teks.',
            'max'               => ':attribute maksimal :max karakter.',
            'uuid'              => 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.',
            'unique'            => 'Formulir ini sudah pernah dikirim.',
            'prohibited'        => 'Pengiriman formulir terdeteksi tidak valid.',
            'services_used.max' => 'Pilih maksimal 8 layanan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'role'                  => 'peran responden',
            'age_range'             => 'usia',
            'has_used_service'      => 'pengalaman menggunakan layanan pengiriman',
            'services_used'         => 'layanan pengiriman yang pernah digunakan',
            'services_other'        => 'nama layanan lainnya',
            'usage_frequency'       => 'frekuensi penggunaan layanan',
            'q6_activities'         => 'aktivitas pengiriman',
            'q6_other'              => 'aktivitas lainnya',
            'q7_difficulty'         => 'tingkat kesulitan penggunaan aplikasi',
            'q8_problem_areas'      => 'bagian yang pernah menyulitkan',
            'q8_other'              => 'bagian kesulitan lainnya',
            'q9_find_features'      => 'kemudahan menemukan fitur',
            'q10_status_clarity'    => 'kemudahan memahami status pengiriman',
            'q11_important_info'    => 'informasi penting pada aplikasi',
            'q11_other'             => 'informasi lainnya',
            'q12_improvement_notes' => 'bagian aplikasi yang perlu diperbaiki',
            'q13_common_obstacles'  => 'kendala yang paling sering dialami',
            'q14_expected_features' => 'fitur yang diharapkan',
            'courier_status'        => 'pengalaman bekerja sebagai kurir',
            'q16_apps'              => 'aplikasi kurir yang pernah digunakan',
            'q16_other'             => 'nama aplikasi kurir lainnya',
            'q17_activities'        => 'aktivitas proses pengiriman',
            'q17_other'             => 'aktivitas kurir lainnya',
            'q18_difficulty'        => 'tingkat kesulitan menggunakan aplikasi kurir',
            'q19_problem_areas'     => 'bagian aplikasi kurir yang menyulitkan',
            'q19_other'             => 'bagian kesulitan kurir lainnya',
            'q20_order_clarity'     => 'kemudahan memahami informasi pesanan',
            'q21_flow_clarity'      => 'kemudahan mengikuti tahapan pengiriman',
            'q22_important_info'    => 'informasi penting pada aplikasi kurir',
            'q22_other'             => 'informasi kurir lainnya',
            'q23_common_obstacles'  => 'kendala kerja sebagai kurir',
            'q24_improvement_notes' => 'bagian aplikasi kurir yang perlu diperbaiki',
            'q25_expected_features' => 'fitur kurir yang diharapkan',
            'interview_contact'     => 'kontak yang dapat dihubungi',
            'additional_suggestion' => 'saran tambahan',
        ];
    }

    private function hasOption(string $field, string $option): bool
    {
        return in_array($option, (array) $this->input($field, []), true);
    }
}
