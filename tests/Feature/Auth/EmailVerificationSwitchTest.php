<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function register(): void
{
    test()->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

it('asks new users to verify their email by default', function (): void {
    Notification::fake();

    register();

    $user = User::query()->where('email', 'ada@example.com')->sole();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->get(route('generations.index'))->assertRedirect(route('verification.notice'));
});

it('lets new users straight in when verification is off, sending no email', function (): void {
    config(['auth.verify_email' => false]);
    Notification::fake();

    register();

    $user = User::query()->where('email', 'ada@example.com')->sole();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->balance_nanos)->toBe(10_000_000_000);
    Notification::assertNothingSent();
    $this->get(route('generations.index'))->assertOk();
});

it('keeps a changed email verified when verification is off', function (): void {
    config(['auth.verify_email' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com']);

    expect($user->refresh()->email)->toBe('new@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});
