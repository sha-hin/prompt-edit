<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The only way to make an operator: is_admin is never mass-assignable or settable from the app.
 */
#[Description('Let an existing user see the admin report')]
#[Signature('users:make-admin {email : The email the user signed up with}')]
final class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user has the email {$email}.");

            return self::FAILURE;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("{$user->email} can now see the admin report.");

        return self::SUCCESS;
    }
}
