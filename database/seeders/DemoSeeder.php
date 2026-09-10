<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstablishmentSegment;
use App\Models\AddonGroup;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Establishment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use App\Services\QrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dados de demonstracao: uma lanchonete no plano FREE e uma pizzaria no PRO.
 * Permite abrir o sistema logo apos `migrate --seed` e ver tudo funcionando.
 */
class DemoSeeder extends Seeder
{
    public function run(SubscriptionManager $subscriptions, QrCodeService $qrCodes): void
    {
        $free = Plan::where('slug', 'free')->firstOrFail();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->createDemo(
            email: 'demo@cardapio.test',
            userName: 'Rafael da Lanchonete',
            establishmentName: 'Sabor & Ponto',
            slug: 'sabor-e-ponto',
            segment: EstablishmentSegment::Lanchonete,
            plan: $free,
            catalog: $this->burgerCatalog(),
            subscriptions: $subscriptions,
            qrCodes: $qrCodes,
            primaryColor: '#E11D48',
        );

        $this->createDemo(
            email: 'pro@cardapio.test',
            userName: 'Marina da Pizzaria',
            establishmentName: 'Forno di Napoli',
            slug: 'forno-di-napoli',
            segment: EstablishmentSegment::Pizzaria,
            plan: $pro,
            catalog: $this->pizzaCatalog(),
            subscriptions: $subscriptions,
            qrCodes: $qrCodes,
            primaryColor: '#16A34A',
        );
    }

    private function createDemo(
        string $email,
        string $userName,
        string $establishmentName,
        string $slug,
        EstablishmentSegment $segment,
        Plan $plan,
        array $catalog,
        SubscriptionManager $subscriptions,
        QrCodeService $qrCodes,
        string $primaryColor,
    ): void {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $userName,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $establishment = Establishment::updateOrCreate(
            ['slug' => $slug],
            [
                'user_id' => $user->id,
                'name' => $establishmentName,
                'legal_name' => $establishmentName.' LTDA',
                'segment' => $segment,
                'description' => 'Peça pelo WhatsApp e retire em poucos minutos.',
                'phone' => '1133334444',
                'whatsapp' => '11988887777',
                'instagram' => Str::slug($establishmentName),
                'address_street' => 'Rua das Flores',
                'address_number' => '120',
                'address_district' => 'Centro',
                'address_city' => 'São Paulo',
                'address_state' => 'SP',
                'address_zipcode' => '01001-000',
                'primary_color' => $primaryColor,
                'secondary_color' => '#0F172A',
                'is_published' => true,
                'is_indexable' => true,
                'published_at' => now(),
            ],
        );

        $this->seedBusinessHours($establishment);

        if ($establishment->subscriptions()->doesntExist()) {
            $subscription = $subscriptions->startFreePlan($establishment, $plan);

            if (! $plan->isFree()) {
                $subscriptions->activate($subscription->refresh());
            }
        }

        $sortOrder = 0;

        foreach ($catalog as $categoryName => $products) {
            $category = Category::updateOrCreate(
                ['establishment_id' => $establishment->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'is_active' => true, 'sort_order' => $sortOrder++],
            );

            $productOrder = 0;

            foreach ($products as $product) {
                Product::updateOrCreate(
                    ['establishment_id' => $establishment->id, 'slug' => Str::slug($product['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'price_cents' => $product['price_cents'],
                        'promo_price_cents' => $product['promo_price_cents'] ?? null,
                        'is_active' => true,
                        'is_featured' => $product['featured'] ?? false,
                        'sort_order' => $productOrder++,
                    ],
                );
            }
        }

        $this->seedAddons($establishment);

        $qrCodes->generate($establishment->refresh());
    }

    private function seedBusinessHours(Establishment $establishment): void
    {
        foreach (range(0, 6) as $weekday) {
            BusinessHour::updateOrCreate(
                ['establishment_id' => $establishment->id, 'weekday' => $weekday],
                [
                    'is_closed' => $weekday === 1,
                    'opens_at' => '18:00:00',
                    'closes_at' => '23:30:00',
                ],
            );
        }
    }

    private function seedAddons(Establishment $establishment): void
    {
        $group = AddonGroup::updateOrCreate(
            ['establishment_id' => $establishment->id, 'name' => 'Adicionais'],
            ['is_required' => false, 'min_options' => 0, 'max_options' => 5, 'is_active' => true],
        );

        foreach ([['Queijo', 300], ['Bacon', 500], ['Ovo', 200], ['Cheddar', 400]] as [$name, $price]) {
            ProductAddon::updateOrCreate(
                ['addon_group_id' => $group->id, 'name' => $name],
                ['price_cents' => $price, 'is_active' => true],
            );
        }

        $featured = $establishment->products()->where('is_featured', true)->get();

        foreach ($featured as $product) {
            $product->addonGroups()->syncWithoutDetaching([$group->id]);
        }
    }

    private function burgerCatalog(): array
    {
        return [
            'Hambúrgueres' => [
                ['name' => 'X-Burger Clássico', 'description' => 'Pão brioche, blend 150g, queijo prato, alface, tomate e maionese da casa.', 'price_cents' => 2490, 'featured' => true],
                ['name' => 'X-Bacon Duplo', 'description' => 'Dois blends 150g, bacon crocante, cheddar e barbecue.', 'price_cents' => 3290, 'featured' => true],
                ['name' => 'Veggie Burger', 'description' => 'Hambúrguer de grão-de-bico, rúcula, tomate seco e maionese vegana.', 'price_cents' => 2750],
            ],
            'Porções' => [
                ['name' => 'Batata Frita P', 'description' => 'Porção individual com sal e alecrim.', 'price_cents' => 1200],
                ['name' => 'Onion Rings', 'description' => 'Anéis de cebola empanados com molho especial.', 'price_cents' => 1890],
            ],
            'Bebidas' => [
                ['name' => 'Coca-Cola Lata', 'description' => 'Lata 350ml gelada.', 'price_cents' => 700],
                ['name' => 'Suco Natural de Laranja', 'description' => '500ml, feito na hora.', 'price_cents' => 950],
                ['name' => 'Água Mineral', 'description' => '500ml sem gás.', 'price_cents' => 450],
            ],
            'Sobremesas' => [
                ['name' => 'Petit Gâteau', 'description' => 'Bolinho de chocolate com sorvete de creme.', 'price_cents' => 1890, 'promo_price_cents' => 1490, 'featured' => true],
                ['name' => 'Pudim de Leite', 'description' => 'Receita tradicional da casa.', 'price_cents' => 1200],
            ],
        ];
    }

    private function pizzaCatalog(): array
    {
        return [
            'Pizzas Salgadas' => [
                ['name' => 'Pizza Margherita', 'description' => 'Molho de tomate, muçarela de búfala e manjericão fresco.', 'price_cents' => 4500, 'featured' => true],
                ['name' => 'Pizza Calabresa', 'description' => 'Calabresa fatiada, cebola roxa e azeitonas.', 'price_cents' => 4200],
                ['name' => 'Pizza Quatro Queijos', 'description' => 'Muçarela, provolone, parmesão e gorgonzola.', 'price_cents' => 4800],
                ['name' => 'Pizza Portuguesa', 'description' => 'Presunto, ovos, cebola, ervilha e azeitonas.', 'price_cents' => 4600],
            ],
            'Pizzas Doces' => [
                ['name' => 'Pizza de Chocolate', 'description' => 'Chocolate ao leite com morangos.', 'price_cents' => 4200],
                ['name' => 'Pizza Romeu e Julieta', 'description' => 'Goiabada cremosa com queijo minas.', 'price_cents' => 3900],
            ],
            'Bordas' => [
                ['name' => 'Borda de Catupiry', 'description' => 'Recheio cremoso na borda.', 'price_cents' => 900],
                ['name' => 'Borda de Cheddar', 'description' => 'Cheddar derretido na borda.', 'price_cents' => 900],
            ],
            'Bebidas' => [
                ['name' => 'Refrigerante 2L', 'description' => 'Coca-Cola, Guaraná ou Fanta.', 'price_cents' => 1400],
                ['name' => 'Cerveja Long Neck', 'description' => 'Heineken 330ml.', 'price_cents' => 1200],
            ],
        ];
    }
}
