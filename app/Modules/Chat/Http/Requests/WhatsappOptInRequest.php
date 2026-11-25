<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsappOptInRequest extends FormRequest
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
            'opt_in' => ['required', 'boolean'],
            'source' => ['nullable', 'string', 'max:64'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ];
    }
}
