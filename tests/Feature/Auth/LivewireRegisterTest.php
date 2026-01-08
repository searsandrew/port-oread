<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Native\Mobile\Facades\SecureStorage;

test('it can register via livewire', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/register' => Http::response([
            'token' => 'fake-token',
            'user' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
        ]),
        'https://tiber.stellarempire.space/api/planets' => Http::response(['data' => []]),
    ]);

    SecureStorage::shouldReceive('set')->andReturn(true);
    SecureStorage::shouldReceive('get')->andReturn('fake-token', json_encode(['name' => 'Jane Doe']));

    Volt::test('auth.register')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
    expect(auth()->user()->email)->toBe('jane@example.com');
});

test('it handles registration failure', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/register' => Http::response(['message' => 'Email already taken'], 422),
    ]);

    Volt::test('auth.register')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'jane@example.com')->exists())->toBeFalse();
});
