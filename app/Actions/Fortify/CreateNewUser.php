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
        } catch (\Exception $e) {
            // If API registration fails, we still create local user?
            // User's requirement says "query the API... store it locally"
            // If they can't register on API, they shouldn't probably be able to register at all
            // if we want them to stay in sync.
            // But for now let's just log it and proceed if we want robustness,
            // OR fail if we want strict sync.
            // Given the user's issue, failing is better so they know it didn't work.
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'API Registration failed: '.$e->getMessage(),
            ]);
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
