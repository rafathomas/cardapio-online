<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restringe o painel da plataforma a administradores. */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin() || $user->isBlocked()) {
            abort(403, 'Acesso restrito a administradores.');
        }

        return $next($request);
    }
}
