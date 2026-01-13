<?php

namespace App\Services;

use App\Exceptions\TiberApiException;
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
        try {
            $response = Http::timeout(15)->acceptJson()->post($this->getUrl('login'), [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new TiberApiException($response->json('message') ?? 'Login failed', $response->status());
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new TiberApiException('Could not connect to the remote server. Please check your internet connection.', 0, $e);
        }
    }

    public function register(array $data): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($this->getUrl('register'), $data);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->serverError()) {
                Log::critical('Tiber API Server Error on registration', [
                    'url' => $this->getUrl('register'),
                    'status' => $response->status(),
                    'data' => array_merge($data, ['password' => '******', 'password_confirmation' => '******']),
                    'response' => $response->body(),
                ]);

                throw new TiberApiException("The registration server encountered an error ({$response->status()}). Your account might have been created on the remote server, but we did not receive an authentication token.", $response->status());
            }

            Log::error('Tiber API registration failed', [
                'url' => $this->getUrl('register'),
                'status' => $response->status(),
                'body' => $response->body(),
                'data' => array_merge($data, ['password' => '******', 'password_confirmation' => '******']),
            ]);

            $message = $response->json('message') ?? 'Registration failed';
            if ($response->status() === 403 || $response->status() === 401) {
                $message = "Access denied by the registration server ({$response->status()}). This might be due to a firewall or security block.";
            }

            throw new TiberApiException($message, $response->status());
        } catch (TiberApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Tiber API Connection Error: '.$e->getMessage());
            throw new TiberApiException('Could not connect to the registration server. Please check your internet connection.', 0, $e);
        } catch (\Exception $e) {
            Log::error('Tiber API Unexpected Error: '.$e->getMessage());
            throw new TiberApiException('An unexpected error occurred while communicating with the registration server: '.$e->getMessage(), 0, $e);
        }
    }

    public function getCatalogPlanets(): array
    {
        $response = Http::timeout(15)->acceptJson()->get($this->getUrl('catalog.planets'));

        if ($response->successful()) {
            return $response->json('data') ?? [];
        }

        throw new \Exception('Failed to fetch catalog planets');
    }

    public function getPlanets(string $token): array
    {
        $response = Http::timeout(15)->withToken($token)->acceptJson()->get($this->getUrl('planets'));

        if ($response->successful()) {
            return $response->json('data') ?? $response->json();
        }

        throw new \Exception('Failed to fetch planets');
    }

    public function getUserDetails(string $token): array
    {
        $response = Http::timeout(15)->withToken($token)->acceptJson()->get($this->getUrl('user'));

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch user details');
    }
}
