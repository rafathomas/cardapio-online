<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\DTOs\CartAddonLine;
use App\DTOs\CartLine;
use App\DTOs\CartSummary;
use App\Models\Establishment;
use Illuminate\Support\Str;

/**
 * Monta a mensagem estruturada do pedido e a URL do WhatsApp.
 */
class WhatsAppMessageBuilder
{
    /** Emoji por categoria, apenas cosmetico. Fallback neutro para o resto. */
    private const CATEGORY_EMOJI = [
        'hamburguer' => '🍔', 'hamburgueres' => '🍔', 'lanche' => '🍔', 'lanches' => '🍔',
        'pizza' => '🍕', 'pizzas' => '🍕',
        'bebida' => '🥤', 'bebidas' => '🥤',
        'sobremesa' => '🍰', 'sobremesas' => '🍰', 'doce' => '🍰', 'doces' => '🍰',
        'porcao' => '🍟', 'porcoes' => '🍟', 'batata' => '🍟',
        'combo' => '🍱', 'combos' => '🍱',
        'cafe' => '☕', 'cafes' => '☕',
        'acai' => '🍧', 'sorvete' => '🍨', 'sorvetes' => '🍨',
        'cerveja' => '🍺', 'cervejas' => '🍺', 'drink' => '🍹', 'drinks' => '🍹',
        'pao' => '🥖', 'paes' => '🥖', 'salgado' => '🥐', 'salgados' => '🥐',
        'bolo' => '🎂', 'bolos' => '🎂',
    ];

    private const FALLBACK_EMOJI = '🍽️';

    /**
     * @param  array{name?: string|null, phone?: string|null, delivery_type?: string|null, address?: string|null, payment_method?: string|null, notes?: string|null, code?: string|null}  $customer
     */
    public function build(Establishment $establishment, CartSummary $cart, array $customer = []): string
    {
        $lines = ['Olá! Gostaria de fazer um pedido:', ''];

        if (! empty($customer['code'])) {
            $lines[] = "*Pedido {$customer['code']}*";
            $lines[] = '';
        }

        foreach ($cart->lines as $line) {
            $lines[] = $this->formatItem($line);

            if ($line->addons !== []) {
                $names = implode(', ', array_map(
                    static fn (CartAddonLine $a): string => $a->price->isZero()
                        ? $a->name
                        : "{$a->name} (+{$a->price->format()})",
                    $line->addons,
                ));
                $lines[] = "   ➕ {$names}";
            }

            if ($line->notes !== null && $line->notes !== '') {
                $lines[] = "   📝 {$line->notes}";
            }
        }

        $lines[] = '';
        $lines[] = "*Total: {$cart->total->format()}*";

        $details = $this->formatCustomerBlock($customer);

        if ($details !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $details);
        }

        if (! empty($customer['notes'])) {
            $lines[] = '';
            $lines[] = 'Observação:';
            $lines[] = (string) $customer['notes'];
        }

        $lines[] = '';
        $lines[] = "Pedido feito pelo cardápio de {$establishment->name}.";

        return implode("\n", $lines);
    }

    /** URL pronta para abrir o WhatsApp do estabelecimento com a mensagem. */
    public function url(Establishment $establishment, string $message): string
    {
        $base = rtrim((string) config('whatsapp.base_url'), '/');
        $number = $establishment->whatsappNumber();

        return "{$base}/{$number}?text=".rawurlencode($message);
    }

    public function buildUrl(Establishment $establishment, CartSummary $cart, array $customer = []): string
    {
        return $this->url($establishment, $this->build($establishment, $cart, $customer));
    }

    private function formatItem(CartLine $line): string
    {
        $emoji = $this->emojiFor($line->product->category?->name);

        return "{$emoji} {$line->quantity}x {$line->product->name} — {$line->lineTotal->format()}";
    }

    private function emojiFor(?string $categoryName): string
    {
        if ($categoryName === null || $categoryName === '') {
            return self::FALLBACK_EMOJI;
        }

        $key = Str::of($categoryName)->lower()->ascii()->trim()->value();

        foreach (self::CATEGORY_EMOJI as $needle => $emoji) {
            if (str_contains($key, $needle)) {
                return $emoji;
            }
        }

        return self::FALLBACK_EMOJI;
    }

    /** @return list<string> */
    private function formatCustomerBlock(array $customer): array
    {
        $lines = [];

        if (! empty($customer['name'])) {
            $lines[] = "*Cliente:* {$customer['name']}";
        }

        if (! empty($customer['phone'])) {
            $lines[] = "*Telefone:* {$customer['phone']}";
        }

        if (! empty($customer['delivery_type'])) {
            $label = $customer['delivery_type'] === 'delivery' ? 'Entrega' : 'Retirada no local';
            $lines[] = "*Tipo:* {$label}";
        }

        if (! empty($customer['address'])) {
            $lines[] = "*Endereço:* {$customer['address']}";
        }

        if (! empty($customer['payment_method'])) {
            $lines[] = "*Pagamento:* {$customer['payment_method']}";
        }

        return $lines;
    }
}
