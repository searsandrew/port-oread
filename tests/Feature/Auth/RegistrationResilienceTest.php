<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Native\Mobile\Facades\SecureStorage;

test('it proceeds with local registration if API returns 500', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/register' => Http::response('Server Error', 500),
    ]);

    // SecureStorage will throw/fail because we are not in native environment
    SecureStorage::shouldReceive('set')->andThrow(new \Exception('Native bridge not found'));

    Volt::test('auth.register')
        ->set('name', 'Resilient User')
        ->set('email', 'resilient@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertHasNoErrors();

    expect(User::where('email', 'resilient@example.com')->exists())->toBeTrue();
    expect(auth()->user()->email)->toBe('resilient@example.com');

    // Check if session has the warning message
    expect(session('status'))->toContain('Account created locally');
});

test('it proceeds with local registration if API times out', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/register' => Http::response(null, 408), // Or just mock a timeout
    ]);

    // Actually mocking a timeout in Http::fake is better done by throwing ConnectionException
    Http::preventStrayRequests();
    Http::fake(['*' => function () {
        throw new \Illuminate\Http\Client\ConnectionException('Operation timed out');
    }]);

    Volt::test('auth.register')
        ->set('name', 'Timeout User')
        ->set('email', 'timeout@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'timeout@example.com')->exists())->toBeTrue();
    expect(session('status'))->toContain('Account created locally');
});

test('it proceeds with local registration if API returns 403', function () {
    Http::fake([
        'https://tiber.stellarempire.space/api/register' => Http::response('Forbidden', 403),
    ]);

    Volt::test('auth.register')
        ->set('name', 'Forbidden User')
        ->set('email', 'forbidden@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'forbidden@example.com')->exists())->toBeTrue();
    expect(session('status'))->toContain('Account created locally');
});
