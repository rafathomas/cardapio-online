<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Excecao de regra de negocio. Vira uma resposta 422 previsivel na API.
 */
class DomainException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_error',
        public readonly array $context = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
        ], $this->status);
    }
}
