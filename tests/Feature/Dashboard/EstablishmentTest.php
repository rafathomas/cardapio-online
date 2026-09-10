<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Establishment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedPlans();
    $this->user = User::factory()->create();
});

it('cria o estabelecimento com slug, horarios, assinatura e qr code', function (): void {
    Storage::fake('public');

    $response = $this->actingAs($this->user)->postJson(route('api.establishments.store'), [
        'name' => 'Minha Lanchonete',
        'segment' => 'lanchonete',
        'whatsapp' => '11988887777',
        'description' => 'A melhor da região',
    ]);

    $response->assertCreated()->assertJsonPath('data.slug', 'minha-lanchonete');

    $establishment = Establishment::firstOrFail();

    expect($establishment->user_id)->toBe($this->user->id)
        ->and($establishment->businessHours()->count())->toBe(7)
        ->and($establishment->subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($establishment->subscription->plan->slug)->toBe('free')
        ->and($establishment->qrCode)->not->toBeNull();

    Storage::disk('public')->assertExists($establishment->qrCode->path);

    $this->assertDatabaseHas('audit_logs', ['action' => 'establishment.created']);
});

it('gera slug unico quando o nome ja existe', function (): void {
    Storage::fake('public');
    Establishment::factory()->create(['slug' => 'minha-lanchonete']);

    $this->actingAs($this->user)->postJson(route('api.establishments.store'), [
        'name' => 'Minha Lanchonete',
        'segment' => 'lanchonete',
        'whatsapp' => '11988887777',
    ])->assertCreated()->assertJsonPath('data.slug', 'minha-lanchonete-2');
});

it('nao aceita slug reservado da plataforma', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user)->postJson(route('api.establishments.store'), [
        'name' => 'Admin',
        'slug' => 'admin',
        'segment' => 'lanchonete',
        'whatsapp' => '11988887777',
    ])->assertCreated()->assertJsonPath('data.slug', 'admin-2');
});

it('valida os campos obrigatorios', function (array $payload, string $field): void {
    $this->actingAs($this->user)
        ->postJson(route('api.establishments.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'sem nome' => [['segment' => 'lanchonete', 'whatsapp' => '11988887777'], 'name'],
    'sem whatsapp' => [['name' => 'Teste', 'segment' => 'lanchonete'], 'whatsapp'],
    'segmento invalido' => [['name' => 'Teste', 'segment' => 'nave-espacial', 'whatsapp' => '11988887777'], 'segment'],
    'cor invalida' => [['name' => 'Teste', 'segment' => 'bar', 'whatsapp' => '11988887777', 'primary_color' => 'vermelho'], 'primary_color'],
    'whatsapp curto' => [['name' => 'Teste', 'segment' => 'bar', 'whatsapp' => '119'], 'whatsapp'],
]);

it('lista apenas os estabelecimentos do proprio usuario', function (): void {
    $meu = makeEstablishment(owner: $this->user);
    makeEstablishment();

    $response = $this->actingAs($this->user)->getJson(route('api.establishments.index'));

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $meu->id);
});

it('impede ver o estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->getJson(route('api.establishments.show', $alheio))
        ->assertStatus(403);
});

it('impede editar o estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)->putJson(route('api.establishments.update', $alheio), [
        'name' => 'Invadido',
        'segment' => 'bar',
        'whatsapp' => '11999999999',
    ])->assertStatus(403);

    expect($alheio->refresh()->name)->not->toBe('Invadido');
});

it('impede excluir o estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->deleteJson(route('api.establishments.destroy', $alheio))
        ->assertStatus(403);

    $this->assertDatabaseHas('establishments', ['id' => $alheio->id, 'deleted_at' => null]);
});

it('atualiza os dados basicos', function (): void {
    $establishment = makeEstablishment(owner: $this->user);

    $this->actingAs($this->user)->putJson(route('api.establishments.update', $establishment), [
        'name' => 'Novo Nome',
        'segment' => 'pizzaria',
        'whatsapp' => '11977776666',
        'description' => 'Nova descrição',
    ])->assertOk()->assertJsonPath('data.name', 'Novo Nome');

    expect($establishment->refresh()->segment->value)->toBe('pizzaria');
});

it('bloqueia personalizacao de cores no plano free', function (): void {
    $establishment = makeEstablishment(owner: $this->user);

    $this->actingAs($this->user)->putJson(route('api.establishments.update', $establishment), [
        'name' => $establishment->name,
        'segment' => $establishment->segment->value,
        'whatsapp' => $establishment->whatsapp,
        'primary_color' => '#123456',
    ])->assertStatus(403)->assertJsonPath('error_code', 'plan_feature_unavailable');

    expect($establishment->refresh()->primary_color)->not->toBe('#123456');
});

it('permite personalizacao de cores no plano pro', function (): void {
    $establishment = makeEstablishment(proPlan(), owner: $this->user);

    $this->actingAs($this->user)->putJson(route('api.establishments.update', $establishment), [
        'name' => $establishment->name,
        'segment' => $establishment->segment->value,
        'whatsapp' => $establishment->whatsapp,
        'primary_color' => '#123456',
    ])->assertOk();

    expect($establishment->refresh()->primary_color)->toBe('#123456');
});

it('publica o cardapio quando ha produto ativo', function (): void {
    Storage::fake('public');
    $establishment = makeEstablishment(owner: $this->user, attributes: ['is_published' => false]);
    Product::factory()->create(['establishment_id' => $establishment->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.establishments.publish', $establishment))
        ->assertOk()
        ->assertJsonPath('public_url', $establishment->publicUrl());

    expect($establishment->refresh()->is_published)->toBeTrue();
});

it('recusa publicar sem produto ativo', function (): void {
    $establishment = makeEstablishment(owner: $this->user, attributes: ['is_published' => false]);

    $this->actingAs($this->user)
        ->postJson(route('api.establishments.publish', $establishment))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'no_active_products');

    expect($establishment->refresh()->is_published)->toBeFalse();
});

it('recusa publicar com e-mail nao verificado', function (): void {
    $user = User::factory()->unverified()->create();
    $establishment = makeEstablishment(owner: $user, attributes: ['is_published' => false]);
    Product::factory()->create(['establishment_id' => $establishment->id]);

    $this->actingAs($user)
        ->postJson(route('api.establishments.publish', $establishment))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'email_unverified');
});

it('atualiza os horarios de funcionamento', function (): void {
    $establishment = makeEstablishment(owner: $this->user);

    $hours = collect(range(0, 6))->map(fn (int $weekday) => [
        'weekday' => $weekday,
        'is_closed' => $weekday === 0,
        'opens_at' => '11:00',
        'closes_at' => '22:00',
    ])->all();

    $this->actingAs($this->user)
        ->putJson(route('api.establishments.hours', $establishment), ['hours' => $hours])
        ->assertOk()
        ->assertJsonCount(7, 'data');

    expect($establishment->businessHours()->where('weekday', 0)->first()->is_closed)->toBeTrue()
        ->and($establishment->businessHours()->where('weekday', 3)->first()->opens_at)->toContain('11:00');
});

it('envia logo e grava a imagem otimizada', function (): void {
    Storage::fake('public');
    $establishment = makeEstablishment(owner: $this->user);

    $this->actingAs($this->user)->post(route('api.establishments.update', $establishment), [
        'name' => $establishment->name,
        'segment' => $establishment->segment->value,
        'whatsapp' => $establishment->whatsapp,
        'logo' => UploadedFile::fake()->image('logo.png', 1200, 1200),
    ])->assertOk();

    $path = $establishment->refresh()->logo_path;

    expect($path)->not->toBeNull()->and($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('recusa arquivo que nao e imagem no logo', function (): void {
    Storage::fake('public');
    $establishment = makeEstablishment(owner: $this->user);

    $this->actingAs($this->user)->post(route('api.establishments.update', $establishment), [
        'name' => $establishment->name,
        'segment' => $establishment->segment->value,
        'whatsapp' => $establishment->whatsapp,
        'logo' => UploadedFile::fake()->create('virus.exe', 100),
    ])->assertStatus(422)->assertJsonValidationErrors('logo');
});

it('retorna 404 para estabelecimento inexistente', function (): void {
    $this->actingAs($this->user)->getJson('/api/establishments/999999')->assertStatus(404);
});
