<?php

namespace Database\Seeders;

use App\Credits\CreditLedger;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The demo user and admin from config/demo.php. Safe to run on every deploy: it resets their
 * password and role to the configured ones, and the $10 signup credit is only ever granted once.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(CreditLedger $ledger): void
    {
        /** @var list<array{name: string, email: string, password: string, admin: bool}> $accounts */
        $accounts = config()->array('demo.accounts');

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'password' => $account['password'],         // hashed by the model's cast
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_admin' => $account['admin'],
            ])->save();

            $ledger->grantSignupBonus($user);
        }
    }
}
