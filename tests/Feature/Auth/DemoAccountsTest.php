<?php

use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a verified demo user and admin, each with the $10 signup credit', function (): void {
    $this->seed(DemoAccountsSeeder::class);

    $user = User::query()->where('email', 'user@promptedit.test')->sole();
    $admin = User::query()->where('email', 'admin@promptedit.test')->sole();

    expect($user->is_admin)->toBeFalse()
        ->and($admin->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->balance_nanos)->toBe(10_000_000_000)
        ->and($admin->balance_nanos)->toBe(10_000_000_000);
});

it('can run on every deploy without duplicating accounts or credit', function (): void {
    $this->seed(DemoAccountsSeeder::class);
    User::query()->where('email', 'user@promptedit.test')->sole()->forceFill(['password' => 'changed'])->save();

    $this->seed(DemoAccountsSeeder::class);

    $user = User::query()->where('email', 'user@promptedit.test')->sole();
    expect(User::query()->count())->toBe(2)
        ->and($user->creditTransactions()->count())->toBe(1)
        ->and($user->balance_nanos)->toBe(10_000_000_000);

    $this->post(route('login.store'), ['email' => 'user@promptedit.test', 'password' => 'demo-user-password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('lists the demo accounts on the login page', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/Login')
            ->where('demoAccounts', [
                ['role' => 'User', 'email' => 'user@promptedit.test', 'password' => 'demo-user-password'],
                ['role' => 'Admin', 'email' => 'admin@promptedit.test', 'password' => 'demo-admin-password'],
            ]));
});

it('hides them when demo accounts are turned off', function (): void {
    config(['demo.enabled' => false]);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('demoAccounts', []));
});

it('lets the demo admin open the admin report', function (): void {
    Http::fake(['*/usage*' => Http::response(pixelforgeJson('usage'))]);
    $this->seed(DemoAccountsSeeder::class);

    $this->post(route('login.store'), ['email' => 'admin@promptedit.test', 'password' => 'demo-admin-password']);

    $this->get(route('admin.report'))->assertOk();
});
