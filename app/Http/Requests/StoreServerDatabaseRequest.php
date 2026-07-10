<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create servers'); // or specific permission if you have one
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'string'],
        ];
    }
}
