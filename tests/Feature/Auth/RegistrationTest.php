<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    \Illuminate\Support\Facades\Http::fake([
        'https://tiber.stellarempire.space/api/register' => \Illuminate\Support\Facades\Http::response([
            'token' => 'fake-token',
            'user' => ['name' => 'John Doe', 'email' => 'test@example.com'],
        ]),
        'https://tiber.stellarempire.space/api/planets' => \Illuminate\Support\Facades\Http::response(['data' => []]),
    ]);

    \Native\Mobile\Facades\SecureStorage::shouldReceive('set')->andReturn(true);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
