<?php

declare(strict_types=1);

namespace App\Exceptions;

class CartException extends DomainException
{
    public static function emptyCart(): self
    {
        return new self('O carrinho está vazio.', 'cart_empty');
    }

    public static function productUnavailable(int|string $productId): self
    {
        return new self(
            'Um dos produtos do carrinho não está mais disponível.',
            'product_unavailable',
            ['product_id' => $productId],
        );
    }

    public static function addonUnavailable(int|string $addonId): self
    {
        return new self(
            'Um dos adicionais selecionados não está mais disponível.',
            'addon_unavailable',
            ['addon_id' => $addonId],
        );
    }

    public static function establishmentClosed(): self
    {
        return new self('O estabelecimento está fechado no momento.', 'establishment_closed');
    }

    public static function invalidQuantity(): self
    {
        return new self('Quantidade inválida para um dos itens.', 'invalid_quantity');
    }

    public static function addonGroupRuleViolated(string $groupName, int $min, int $max): self
    {
        return new self(
            "A seleção de adicionais do grupo \"{$groupName}\" é inválida.",
            'addon_group_rule',
            ['group' => $groupName, 'min' => $min, 'max' => $max],
        );
    }
}
