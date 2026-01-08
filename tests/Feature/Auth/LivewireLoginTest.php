<?php

use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Native\Mobile\Facades\SecureStorage;

test('login component can authenticate and sync local user', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/login' => Http::response([
            'token' => 'test-token',
            'user' => ['name' => 'Test Commander', 'email' => 'test@example.com'],
        ]),
        'https://tiber.stellarempire.space/api/planets' => Http::response([
            'data' => [],
        ]),
    ]);

    // Mock SecureStorage
    SecureStorage::shouldReceive('set')->andReturn(true);
    SecureStorage::shouldReceive('get')->with('auth_token')->andReturn('test-token');
    SecureStorage::shouldReceive('get')->with('user_data')->andReturn(json_encode(['name' => 'Test Commander', 'email' => 'test@example.com']));

    $component = Volt::test('auth.login')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->call('login');

    $component->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test Commander',
    ]);
});
