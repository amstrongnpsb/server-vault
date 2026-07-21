<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage server services') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
