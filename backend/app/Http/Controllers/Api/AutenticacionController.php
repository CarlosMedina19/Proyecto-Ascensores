<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Autenticacion\IniciarSesionRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutenticacionController extends Controller
{
    public function iniciarSesion(IniciarSesionRequest $solicitud)
    {
        $credenciales = $solicitud->validated();

        if (! Auth::attempt($credenciales)) {
            return response()->json(['mensaje' => 'Credenciales incorrectas'], 401);
        }

        /** @var Usuario $usuario */
        $usuario = Auth::user();

        if (! $usuario->activo) {
            Auth::logout();

            return response()->json(['mensaje' => 'Usuario inactivo'], 403);
        }

        $tokenAcceso = $usuario->createToken('api-token')->plainTextToken;

        return response()->json([
            'usuario' => new UsuarioResource($usuario->load('rol')),
            'token_acceso' => $tokenAcceso,
        ]);
    }

    public function cerrarSesion(Request $solicitud)
    {
        $solicitud->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada correctamente']);
    }

    public function perfil(Request $solicitud)
    {
        return new UsuarioResource($solicitud->user()->load('rol'));
    }
}
