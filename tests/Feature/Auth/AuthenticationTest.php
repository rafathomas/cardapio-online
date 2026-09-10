<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    RateLimiter::clear('auth-ip:127.0.0.1');
});

it('cadastra um usuario e ja autentica', function (): void {
    Event::fake([Registered::class]);

    $response = $this->postJson(route('api.auth.register'), [
        'name' => 'Rafael Souza',
        'email' => 'rafael@example.com',
        'password' => 'senhaForte123',
        'password_confirmation' => 'senhaForte123',
    ]);

    $response->assertCreated()->assertJsonPath('user.email', 'rafael@example.com');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'rafael@example.com', 'is_admin' => false]);

    // A senha nunca e gravada em texto puro.
    expect(User::first()->password)->not->toBe('senhaForte123')
        ->and(Hash::check('senhaForte123', User::first()->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('nunca expoe o hash da senha na resposta', function (): void {
    $response = $this->postJson(route('api.auth.register'), [
        'name' => 'Teste',
        'email' => 'teste@example.com',
        'password' => 'senhaForte123',
        'password_confirmation' => 'senhaForte123',
    ]);

    expect($response->json('user'))->not->toHaveKey('password');
});

it('recusa cadastro invalido', function (array $payload, string $field): void {
    $this->postJson(route('api.auth.register'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'email invalido' => [['name' => 'A B', 'email' => 'nao-e-email', 'password' => 'senhaForte123', 'password_confirmation' => 'senhaForte123'], 'email'],
    'senha curta' => [['name' => 'A B', 'email' => 'a@example.com', 'password' => 'abc1', 'password_confirmation' => 'abc1'], 'password'],
    'senha sem numero' => [['name' => 'A B', 'email' => 'a@example.com', 'password' => 'somenteletras', 'password_confirmation' => 'somenteletras'], 'password'],
    'confirmacao diferente' => [['name' => 'A B', 'email' => 'a@example.com', 'password' => 'senhaForte123', 'password_confirmation' => 'outraSenha123'], 'password'],
    'nome vazio' => [['name' => '', 'email' => 'a@example.com', 'password' => 'senhaForte123', 'password_confirmation' => 'senhaForte123'], 'name'],
    'sem campos' => [[], 'email'],
]);

it('recusa e-mail duplicado', function (): void {
    User::factory()->create(['email' => 'ocupado@example.com']);

    $this->postJson(route('api.auth.register'), [
        'name' => 'Outro',
        'email' => 'ocupado@example.com',
        'password' => 'senhaForte123',
        'password_confirmation' => 'senhaForte123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('autentica com credenciais corretas', function (): void {
    $user = User::factory()->create(['email' => 'login@example.com']);

    $this->postJson(route('api.auth.login'), [
        'email' => 'login@example.com',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('recusa senha incorreta sem revelar se o e-mail existe', function (): void {
    User::factory()->create(['email' => 'login@example.com']);

    $existente = $this->postJson(route('api.auth.login'), [
        'email' => 'login@example.com',
        'password' => 'senha-errada',
    ])->assertStatus(422);

    RateLimiter::clear('auth-ip:127.0.0.1');

    $inexistente = $this->postJson(route('api.auth.login'), [
        'email' => 'nao-existe@example.com',
        'password' => 'qualquer',
    ])->assertStatus(422);

    expect($existente->json('errors.email'))->toBe($inexistente->json('errors.email'));
    $this->assertGuest();
});

it('bloqueia login de conta bloqueada', function (): void {
    User::factory()->blocked()->create(['email' => 'bloqueado@example.com']);

    $this->postJson(route('api.auth.login'), [
        'email' => 'bloqueado@example.com',
        'password' => 'password',
    ])->assertStatus(422);

    $this->assertGuest();
});

it('aplica rate limit no login', function (): void {
    User::factory()->create(['email' => 'alvo@example.com']);

    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('api.auth.login'), [
            'email' => 'alvo@example.com',
            'password' => 'errada',
        ]);
    }

    $this->postJson(route('api.auth.login'), [
        'email' => 'alvo@example.com',
        'password' => 'errada',
    ])->assertStatus(429);
});

it('encerra a sessao no logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.auth.logout'))
        ->assertOk();

    $this->assertGuest('web');

    // O que importa de verdade: a proxima requisicao nao passa mais.
    $this->app['auth']->forgetGuards();
    $this->postJson(route('api.auth.logout'))->assertStatus(401);
});

it('exige autenticacao nas rotas do painel', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertStatus(401);
})->with([
    ['GET', '/api/me'],
    ['GET', '/api/establishments'],
    ['POST', '/api/establishments'],
    ['POST', '/api/auth/logout'],
]);

it('envia o link de redefinicao de senha', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'esqueci@example.com']);

    $this->postJson(route('api.auth.forgot'), ['email' => 'esqueci@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('responde igual para e-mail inexistente na recuperacao', function (): void {
    Notification::fake();

    $conhecido = $this->postJson(route('api.auth.forgot'), ['email' => 'a@example.com']);
    RateLimiter::clear('pwd:b@example.com|127.0.0.1');
    $desconhecido = $this->postJson(route('api.auth.forgot'), ['email' => 'b@example.com']);

    expect($conhecido->json('message'))->toBe($desconhecido->json('message'));
});

it('redefine a senha com token valido', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->postJson(route('api.auth.reset'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'novaSenha123',
        'password_confirmation' => 'novaSenha123',
    ])->assertOk();

    expect(Hash::check('novaSenha123', $user->refresh()->password))->toBeTrue();
});

it('recusa token de redefinicao invalido', function (): void {
    User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson(route('api.auth.reset'), [
        'token' => 'token-falso',
        'email' => 'reset@example.com',
        'password' => 'novaSenha123',
        'password_confirmation' => 'novaSenha123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('altera a senha exigindo a senha atual', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/me/password', [
        'current_password' => 'senha-errada',
        'password' => 'novaSenha123',
        'password_confirmation' => 'novaSenha123',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    $this->actingAs($user)->putJson('/api/me/password', [
        'current_password' => 'password',
        'password' => 'novaSenha123',
        'password_confirmation' => 'novaSenha123',
    ])->assertOk();

    expect(Hash::check('novaSenha123', $user->refresh()->password))->toBeTrue();
});

it('marca o e-mail como verificado pelo link assinado', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->get($url)->assertRedirect();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('recusa link de verificacao com hash errado', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1('outro@email.com')],
    );

    $this->get($url)->assertStatus(403);

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});
