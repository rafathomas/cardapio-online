<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\DTOs\CartAddonLine;
use App\DTOs\CartLine;
use App\DTOs\CartSummary;
use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\Establishment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Persiste o pedido e produz a mensagem do WhatsApp.
 *
 * Os itens sao gravados como snapshot: editar ou excluir um produto depois
 * nao altera pedidos ja registrados.
 */
class OrderService
{
    public function __construct(
        private readonly WhatsAppMessageBuilder $messageBuilder,
    ) {}

    /**
     * @param  array{name: string, phone?: string|null, delivery_type?: string|null, address?: string|null, payment_method?: string|null, notes?: string|null}  $customer
     * @return array{order: Order, message: string, whatsapp_url: string}
     */
    public function place(
        Establishment $establishment,
        CartSummary $cart,
        array $customer,
        ?string $ip = null,
    ): array {
        return DB::transaction(function () use ($establishment, $cart, $customer, $ip): array {
            $order = Order::create([
                'establishment_id' => $establishment->id,
                'code' => $this->generateCode($establishment),
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'] ?? null,
                'delivery_type' => $customer['delivery_type'] ?? 'pickup',
                'address_line' => $customer['address'] ?? null,
                'payment_method' => $customer['payment_method'] ?? null,
                'notes' => $customer['notes'] ?? null,
                'subtotal_cents' => $cart->subtotal->cents,
                'total_cents' => $cart->total->cents,
                'status' => OrderStatus::Received,
                'channel' => 'whatsapp',
                'customer_ip' => $ip,
            ]);

            foreach ($cart->lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'unit_price_cents' => $line->unitPrice->cents,
                    'quantity' => $line->quantity,
                    'addons_total_cents' => $line->addonsPerUnit->multipliedBy($line->quantity)->cents,
                    'total_cents' => $line->lineTotal->cents,
                    'addons' => $this->serializeAddons($line),
                    'notes' => $line->notes,
                ]);
            }

            $message = $this->messageBuilder->build($establishment, $cart, [
                ...$customer,
                'code' => $order->code,
            ]);

            $order->update(['whatsapp_message' => $message]);

            event(new OrderPlaced($order->fresh(['items', 'establishment'])));

            return [
                'order' => $order->refresh(),
                'message' => $message,
                'whatsapp_url' => $this->messageBuilder->url($establishment, $message),
            ];
        });
    }

    /** @return list<array{id: int, name: string, price_cents: int}> */
    private function serializeAddons(CartLine $line): array
    {
        return array_map(
            static fn (CartAddonLine $addon): array => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price_cents' => $addon->price->cents,
            ],
            $line->addons,
        );
    }

    /**
     * Codigo curto e legivel, unico por estabelecimento.
     * Caracteres ambiguos (0/O, 1/I) sao evitados.
     */
    private function generateCode(Establishment $establishment): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '#';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $exists = Order::query()
                ->where('establishment_id', $establishment->id)
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
