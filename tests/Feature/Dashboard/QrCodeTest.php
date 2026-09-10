<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->establishment = makeEstablishment(owner: $this->user);
});

it('gera o qr code na primeira consulta', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.qrcode.show', $this->establishment))
        ->assertOk()
        ->assertJsonPath('data.target_url', $this->establishment->publicUrl())
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseHas('qrcodes', ['establishment_id' => $this->establishment->id]);
    expect($response->json('data.image_url'))->not->toBeNull();
});

it('baixa o qr code em png', function (): void {
    $response = $this->actingAs($this->user)
        ->get(route('api.qrcode.download', $this->establishment).'?format=png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toStartWith("\x89PNG");
});

it('baixa o qr code em svg', function (): void {
    $this->actingAs($this->user)
        ->get(route('api.qrcode.download', $this->establishment).'?format=svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', false);
});

it('gera nova versao ao regenerar', function (): void {
    $this->actingAs($this->user)->getJson(route('api.qrcode.show', $this->establishment));

    $this->actingAs($this->user)
        ->postJson(route('api.qrcode.regenerate', $this->establishment))
        ->assertOk()
        ->assertJsonPath('data.version', 2);

    $this->assertDatabaseHas('audit_logs', ['action' => 'qrcode.regenerated']);
});

it('atualiza o qr code quando o link do cardapio muda', function (): void {
    $this->actingAs($this->user)->getJson(route('api.qrcode.show', $this->establishment));

    $this->actingAs($this->user)->putJson(route('api.establishments.update', $this->establishment), [
        'name' => $this->establishment->name,
        'segment' => $this->establishment->segment->value,
        'whatsapp' => $this->establishment->whatsapp,
        'slug' => 'novo-endereco',
    ])->assertOk();

    $qrCode = $this->establishment->refresh()->qrCode;

    expect($qrCode->target_url)->toContain('novo-endereco')
        ->and($qrCode->version)->toBe(2);
});

it('impede acessar o qr code de outro estabelecimento', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->getJson(route('api.qrcode.show', $alheio))
        ->assertStatus(403);
});

it('exige autenticacao para baixar o qr code', function (): void {
    $this->getJson(route('api.qrcode.download', $this->establishment))->assertStatus(401);
});
