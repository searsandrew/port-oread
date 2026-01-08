<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiberApiService
{
    protected string $baseUrl;

    protected array $endpoints;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.tiber.url', 'https://tiber.stellarempire.space'), '/');
        $this->endpoints = config('services.tiber.endpoints', []);
    }

    protected function getUrl(string $key): string
    {
        $endpoint = $this->endpoints[$key] ?? "/api/{$key}";

        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }

    public function login(string $email, string $password): array
    {
        $response = Http::acceptJson()->post($this->getUrl('login'), [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception($response->json('message') ?? 'Login failed');
    }

    public function register(array $data): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->post($this->getUrl('register'), $data);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Tiber API registration failed', [
            'url' => $this->getUrl('register'),
            'status' => $response->status(),
            'body' => $response->body(),
            'data' => array_merge($data, ['password' => '******', 'password_confirmation' => '******']),
        ]);

        throw new \Exception($response->json('message') ?? 'Registration failed');
    }

    public function getPlanets(string $token): array
    {
        $response = Http::withToken($token)->acceptJson()->get($this->getUrl('planets'));

        if ($response->successful()) {
            return $response->json('data') ?? $response->json();
        }

        throw new \Exception('Failed to fetch planets');
    }

    public function getUserDetails(string $token): array
    {
        $response = Http::withToken($token)->acceptJson()->get($this->getUrl('user'));

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch user details');
    }
}
