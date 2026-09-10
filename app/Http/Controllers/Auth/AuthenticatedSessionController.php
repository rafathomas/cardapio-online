<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            // Mensagem generica: nao revela se o e-mail existe.
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas não conferem.',
            ]);
        }

        $user = Auth::user();

        if ($user->isBlocked()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta conta está bloqueada. Entre em contato com o suporte.',
            ]);
        }

        $request->session()->regenerate();

        $this->audit->log('auth.login', $user, $user);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Login realizado.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->audit->log('auth.logout', $user, $user);
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
