<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('lets an existing user see the admin report', function (): void {
    $user = User::factory()->create(['email' => 'ops@example.com']);

    $this->artisan('users:make-admin', ['email' => 'ops@example.com'])
        ->expectsOutput('ops@example.com can now see the admin report.')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

it('fails for an email nobody signed up with', function (): void {
    $this->artisan('users:make-admin', ['email' => 'nobody@example.com'])
        ->expectsOutput('No user has the email nobody@example.com.')
        ->assertFailed();
});

it('never lets is_admin be mass assigned', function (): void {
    User::factory()->create()->fill(['is_admin' => true]);
})->throws(MassAssignmentException::class);
