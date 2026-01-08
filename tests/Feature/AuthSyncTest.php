<?php

use App\Services\AuthSyncService;
use App\Services\LocalStoreService;
use App\Services\TiberApiService;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Facades\SecureStorage;

beforeEach(function () {
    config(['services.tiber.url' => 'https://api.port-oread.test']);

    $this->api = new TiberApiService;
    $this->local = new LocalStoreService;
    $this->service = new AuthSyncService($this->api, $this->local);
});

test('it can login and sync planets', function () {
    Http::fake([
        'https://api.port-oread.test/api/login' => Http::response([
            'token' => 'test-token',
            'user' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]),
        'https://api.port-oread.test/api/planets' => Http::response([
            'data' => [
                ['id' => 'P1', 'name' => 'Test Planet'],
            ],
        ]),
    ]);

    // Mock SecureStorage facade
    SecureStorage::shouldReceive('set')->andReturn(true);
    SecureStorage::shouldReceive('get')->with('auth_token')->andReturn('test-token');
    SecureStorage::shouldReceive('get')->with('user_data')->andReturn(json_encode(['name' => 'John Doe']));

    $result = $this->service->login('john@example.com', 'password');

    expect($result)->toBeTrue();
    expect($this->service->isAuthenticated())->toBeTrue();
    expect($this->service->getUser())->toHaveKey('name', 'John Doe');
});

test('it can register', function () {
    Http::fake([
        'https://api.port-oread.test/api/register' => Http::response([
            'token' => 'new-token',
            'user' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
        ]),
        'https://api.port-oread.test/api/planets' => Http::response(['data' => []]),
    ]);

    SecureStorage::shouldReceive('set')->andReturn(true);
    SecureStorage::shouldReceive('get')->with('auth_token')->andReturn('new-token');

    $result = $this->service->register([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($result)->toBeTrue();
    expect($this->service->isAuthenticated())->toBeTrue();
});

test('it fallbacks to offline login', function () {
    Http::fake([
        'https://api.port-oread.test/api/login' => Http::response([], 500),
    ]);

    SecureStorage::shouldReceive('get')
        ->with('user_data')
        ->andReturn(json_encode(['email' => 'john@example.com', 'name' => 'John Doe']));

    $result = $this->service->login('john@example.com', 'password');

    expect($result)->toBeTrue();
});
