<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Envia o link de redefinicao.
     *
     * A resposta e sempre a mesma, exista o e-mail ou nao: enumerar contas
     * cadastradas seria um vazamento de informacao.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:180']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá o link de redefinição.',
        ]);
    }

    public function reset(PasswordResetRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $this->audit->log('auth.password_reset', $user, $user);
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'Este link de redefinição é inválido ou expirou.',
            ]);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
