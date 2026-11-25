<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
        $maxKb = (int) config('chat.max_image_kb', 5120);
        $mimes = implode(',', config('chat.allowed_image_mimes', ['jpg', 'jpeg', 'png', 'webp']));

        return [
            'body' => ['nullable', 'string', 'max:4000', 'required_without:media'],
            'message_type' => ['nullable', 'string', 'in:text,image'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
            'media' => ['nullable', 'file', 'mimes:' . $mimes, 'max:' . $maxKb, 'required_without:body'],
        ];
    }
}
