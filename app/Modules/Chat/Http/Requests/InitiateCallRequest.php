<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('order_identifier') && is_scalar($this->input('order_identifier'))) {
            $this->merge([
                'order_identifier' => trim((string) $this->input('order_identifier')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'order_identifier' => ['nullable', 'string', 'max:64'],
            'offer' => ['nullable', 'array'],
            'offer.sdp' => ['required_with:offer', 'string', 'max:32768'],
            'offer.type' => ['required_with:offer', 'string', 'in:offer'],
        ];
    }
}
