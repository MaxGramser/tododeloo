<?php

use App\Models\User;

it('signs in as the dev user when env is local', function () {
    $this->post('/__dev/quick-login')
        ->assertRedirect(route('today.show'));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('dev@tododeloo.test');

    expect(User::where('email', 'dev@tododeloo.test')->count())->toBe(1);
});

it('reuses the dev user on subsequent calls', function () {
    $this->post('/__dev/quick-login');
    auth()->logout();
    $this->post('/__dev/quick-login');

    expect(User::where('email', 'dev@tododeloo.test')->count())->toBe(1);
});
