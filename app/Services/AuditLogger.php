<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Registro de acoes sensiveis.
 *
 * Nunca recebe senhas, tokens ou payloads de cartao: o chamador envia
 * apenas metadados, e as chaves sensiveis conhecidas sao removidas aqui.
 */
class AuditLogger
{
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token',
        'secret', 'authorization', 'card', 'card_number', 'cvv', 'cvc',
        'x-signature', 'remember_token', 'api_key',
    ];

    public function log(
        string $action,
        ?Model $auditable = null,
        ?User $user = null,
        ?Establishment $establishment = null,
        array $meta = [],
    ): AuditLog {
        $meta = $this->redact($meta);

        $record = AuditLog::create([
            'user_id' => $user?->id,
            'establishment_id' => $establishment?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $this->clientIp(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            'meta' => $meta === [] ? null : $meta,
        ]);

        Log::info("audit.{$action}", array_filter([
            'audit_id' => $record->id,
            'user_id' => $user?->id,
            'establishment_id' => $establishment?->id,
            'auditable' => $auditable ? $auditable::class.'#'.$auditable->getKey() : null,
            'meta' => $meta === [] ? null : $meta,
        ], static fn ($v) => $v !== null));

        return $record;
    }

    private function clientIp(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function redact(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $meta[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $meta[$key] = $this->redact($value);
            }
        }

        return $meta;
    }
}
