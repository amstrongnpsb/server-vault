<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage database servers') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'string'],
        ];
    }
}
