<?php

declare(strict_types=1);

namespace App\Exceptions;

class PlanLimitExceededException extends DomainException
{
    public static function products(int $limit, string $planName): self
    {
        return new self(
            "Seu plano {$planName} permite até {$limit} produtos. Faça upgrade para cadastrar mais.",
            'plan_limit_products',
            ['limit' => $limit, 'plan' => $planName, 'resource' => 'products'],
        );
    }

    public static function categories(int $limit, string $planName): self
    {
        return new self(
            "Seu plano {$planName} permite até {$limit} categorias. Faça upgrade para cadastrar mais.",
            'plan_limit_categories',
            ['limit' => $limit, 'plan' => $planName, 'resource' => 'categories'],
        );
    }

    public static function establishments(int $limit, string $planName): self
    {
        return new self(
            "Seu plano {$planName} permite até {$limit} estabelecimento(s).",
            'plan_limit_establishments',
            ['limit' => $limit, 'plan' => $planName, 'resource' => 'establishments'],
        );
    }

    public static function feature(string $feature, string $planName): self
    {
        return new self(
            "Este recurso não está disponível no plano {$planName}.",
            'plan_feature_unavailable',
            ['feature' => $feature, 'plan' => $planName],
            403,
        );
    }
}
