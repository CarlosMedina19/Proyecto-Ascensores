<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'in:natural,juridica'],
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'nit' => ['nullable', 'string', 'max:50', 'unique:clients,nit'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_regime' => ['nullable', 'string', 'max:100'],
            'economic_activity' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
