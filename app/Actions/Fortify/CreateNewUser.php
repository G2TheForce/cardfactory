<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Self-registration creates a "customer" account plus its customer record.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole(config('smartcard.default_role'));

            Customer::create([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
            ]);

            $this->logger->log('user.registered', $user, 'Self-registered as customer', userId: $user->id);

            return $user;
        });
    }
}
