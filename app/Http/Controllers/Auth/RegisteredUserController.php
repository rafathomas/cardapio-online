<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $this->audit->log('auth.registered', $user, $user);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Conta criada com sucesso.',
        ], 201);
    }

    /** Reenvio do e-mail de verificacao. */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail já verificado.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Enviamos um novo link de verificação.']);
    }
}
