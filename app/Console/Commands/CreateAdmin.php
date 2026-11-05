<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates (or promotes) a Super Admin account, e.g. the first user on a new server. */
class CreateAdmin extends Command
{
    protected $signature = 'smartcard:create-admin {email : Sign-in email} {--name= : Display name}';

    protected $description = 'Create a Super Admin account, or make an existing user Super Admin';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:190']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            if (! $this->confirm("{$user->name} <{$email}> already exists. Make them Super Admin?", true)) {
                return self::SUCCESS;
            }
        } else {
            $name = $this->option('name') ?: $this->ask('Name');
            $password = $this->secret('Password');
            $validator = Validator::make(
                ['name' => $name, 'password' => $password, 'password_confirmation' => $this->secret('Repeat password')],
                ['name' => ['required', 'string', 'max:120'], 'password' => ['required', 'confirmed', Password::defaults()]],
            );
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->syncRoles([User::ROLE_SUPER_ADMIN]);
        $this->info("{$email} is a Super Admin. Sign in at ".url('/login'));

        return self::SUCCESS;
    }
}
