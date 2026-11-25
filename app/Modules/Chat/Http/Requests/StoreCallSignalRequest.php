<?php

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Models\CallSignal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signal_type' => ['required', 'string', Rule::in([
                CallSignal::TYPE_OFFER,
                CallSignal::TYPE_ANSWER,
                CallSignal::TYPE_ICE_CANDIDATE,
            ])],
            'payload' => ['required', 'array'],
        ];
    }
}
