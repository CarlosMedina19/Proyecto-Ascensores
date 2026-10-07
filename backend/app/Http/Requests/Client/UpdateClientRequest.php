<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client') instanceof \App\Models\Client 
            ? $this->route('client')->id 
            : $this->route('client');

        return [
            'type' => ['nullable', 'in:natural,juridica'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'nit' => ['nullable', 'string', 'max:50', Rule::unique('clients')->ignore($clientId)],
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
