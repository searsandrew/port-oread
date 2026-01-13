<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $authSync = app(\App\Services\AuthSyncService::class);

        try {
            $authSync->register([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'password_confirmation' => $input['password_confirmation'] ?? $input['password'],
            ]);
        } catch (\App\Exceptions\TiberApiException $e) {
            if (! $e->isValidationError()) {
                \Illuminate\Support\Facades\Log::warning('Fortify registration API error ('.$e->getStatus().'), but proceeding with local user creation: '.$e->getMessage());
            } else {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'API Registration failed: '.$e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fortify registration: Unexpected error: '.$e->getMessage());
            // Proceed locally anyway
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
