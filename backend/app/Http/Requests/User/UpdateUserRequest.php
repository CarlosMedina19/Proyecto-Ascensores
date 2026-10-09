<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\MapsApiFields;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use MapsApiFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') instanceof User
            ? $this->route('user')->id
            : $this->route('user');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'correo' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'rol_id' => ['sometimes', 'required', 'exists:roles,id'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'name' => 'nombre',
            'email' => 'correo',
            'role_id' => 'rol_id',
            'is_active' => 'activo',
        ], $this->all()));
    }
}
