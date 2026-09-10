<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Valor monetario imutavel armazenado em centavos.
 *
 * Todo dinheiro no sistema trafega em inteiros para evitar erros de
 * arredondamento de ponto flutuante. A conversao para string acontece
 * apenas na borda (API Resources / views).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $cents,
        public string $currency = 'BRL',
    ) {}

    public static function fromCents(int $cents, string $currency = 'BRL'): self
    {
        return new self($cents, $currency);
    }

    /**
     * Cria a partir de um valor decimal ("29,90", "29.90", 29.9).
     *
     * A conversao e feita sobre a representacao decimal, nunca multiplicando
     * um float por 100: 1.005 em binario e 1.00499..., o que truncaria um
     * centavo do cliente.
     */
    public static function fromDecimal(int|float|string $value, string $currency = 'BRL'): self
    {
        $normalized = self::normalizeDecimalString($value);

        if (! preg_match('/^([+-]?)(\\d+)(?:\\.(\\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("Valor monetario invalido: {$value}");
        }

        [, $sign, $integer, $fraction] = [...$matches, ''];
        $fraction = str_pad(substr((string) ($matches[3] ?? ''), 0, 3), 3, '0');

        $cents = ((int) $integer) * 100 + (int) substr($fraction, 0, 2);

        // Arredondamento comercial na terceira casa decimal.
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        if ($sign === '-' && $cents > 0) {
            throw new InvalidArgumentException('Valor monetario nao pode ser negativo.');
        }

        return new self($cents, $currency);
    }

    /** Uniformiza notacao pt-BR e float para "1234.5678". */
    private static function normalizeDecimalString(int|float|string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Valor monetario invalido.');
            }

            return sprintf('%.4F', $value);
        }

        $normalized = trim($value);

        // Aceita "1.234,56" (pt-BR) e "1234.56" (padrao).
        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }

        return $normalized;
    }

    public static function zero(string $currency = 'BRL'): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->cents - $other->cents), $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplicador nao pode ser negativo.');
        }

        return new self($this->cents * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    /** Valor decimal para envio ao gateway: 29.90 */
    public function toDecimal(): float
    {
        return round($this->cents / 100, 2);
    }

    public function toDecimalString(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    /** Formatacao pt-BR: R$ 1.234,56 */
    public function format(): string
    {
        $symbol = $this->currency === 'BRL' ? 'R$ ' : $this->currency.' ';

        return $symbol.number_format($this->cents / 100, 2, ',', '.');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Nao e possivel operar moedas diferentes: {$this->currency} e {$other->currency}."
            );
        }
    }
}
