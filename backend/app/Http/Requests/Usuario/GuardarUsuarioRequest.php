<?php

namespace App\Http\Requests\Usuario;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * RF-003: usuarios con nombre, correo único, un solo rol y estado.
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function usuarioActual(): ?Usuario
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => strtolower(trim($this->input('correo')))]);
        }
    }

    public function rules(): array
    {
        $actual = $this->usuarioActual();
        $requerido = $actual ? ['sometimes', 'required'] : ['required'];

        return [
            'nombre' => [...$requerido, 'string', 'max:255'],
            'correo' => [...$requerido, 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($actual?->id)],
            'password' => [$actual ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
            'rol_id' => [...$requerido, 'integer', 'exists:roles,id'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Un usuario con rol cliente debe quedar asociado a su cliente (RNF-02).
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $actual = $this->usuarioActual();
                $rolId = $this->input('rol_id', $actual?->rol_id);
                $clienteId = $this->exists('cliente_id') ? $this->input('cliente_id') : $actual?->cliente_id;

                if (Rol::whereKey($rolId)->value('nombre') === 'cliente' && blank($clienteId)) {
                    $validador->errors()->add('cliente_id', 'Un usuario con rol cliente debe estar asociado a un cliente.');
                }

                // Evita que el usuario se bloquee a sí mismo el acceso.
                if ($actual && $actual->id === $this->user()?->id) {
                    if ($this->has('activo') && ! $this->boolean('activo')) {
                        $validador->errors()->add('activo', 'No puede desactivar su propia cuenta.');
                    }

                    if ($this->has('rol_id') && (int) $this->input('rol_id') !== $actual->rol_id) {
                        $validador->errors()->add('rol_id', 'No puede cambiar su propio rol.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'contraseña',
            'rol_id' => 'rol',
            'cliente_id' => 'cliente',
        ];
    }
}
