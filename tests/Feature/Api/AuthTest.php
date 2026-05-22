<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('issues a token for valid credentials', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone van Max',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    expect($user->tokens()->count())->toBe(1);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.login'), [
        'email' => $user->email,
        'password' => 'verkeerd',
        'device_name' => 'iPhone',
    ])->assertStatus(422);

    expect($user->tokens()->count())->toBe(0);
});

it('requires authentication for protected endpoints', function () {
    $this->getJson(route('api.today'))->assertUnauthorized();
});

it('returns the current user from /me', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson(route('api.me'))
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

it('revokes the token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)->postJson(route('api.logout'))->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
