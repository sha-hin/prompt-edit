<?php

use App\Enums\GenerationStatus;
use App\Http\Controllers\Generations\GenerationController;
use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the studio with the user\'s generations, newest first', function (): void {
    $user = User::factory()->create();
    $older = Generation::factory()->for($user)->create(['created_at' => now()->subMinute()]);
    $newer = Generation::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('generations/Index')
            ->has('generations.data', 2)
            ->where('generations.data.0.id', $newer->id)
            ->where('generations.data.1.id', $older->id));
});

it('never shows another user\'s generations', function (): void {
    $user = User::factory()->create();
    Generation::factory()->count(3)->create();             // each belongs to a different user

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->has('generations.data', 0));
});

it('pages the history', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->count(GenerationController::PER_PAGE + 2)->create();

    $this->actingAs($user)
        ->get(route('generations.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('generations.data', 2)
            ->where('generations.meta.current_page', 2)
            ->where('generations.meta.last_page', 2));
});

it('shows a succeeded generation\'s charge and image, but never Pixelforge\'s cost', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->queued()->create([
        'status' => GenerationStatus::Succeeded,
        'cost_nanos' => 11_685_000,
        'charge_nanos' => 14_606_250,
        'image_url' => 'https://pixelforge.promptedit.dev/images/gen_1.png',
        'image_url_expires_at' => now()->addDay(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('generations.data.0.status', 'succeeded')
            ->where('generations.data.0.charge', ['amount' => '0.014606250', 'display' => '$0.0146', 'exact' => '$0.014606250'])
            ->where('generations.data.0.estimate', ['min' => '0.058137500', 'max' => '0.086275000', 'display' => '$0.0581 – $0.0863'])
            ->where('generations.data.0.image_url', 'https://pixelforge.promptedit.dev/images/gen_1.png')
            ->where('generations.data.0.image_url_expires_at', '2026-11-18T10:00:00Z')
            ->missing('generations.data.0.cost_nanos')
            ->missing('generations.data.0.rates')
            ->missing('generations.data.0.idempotency_key'));
});

it('shows no charge while running and a zero charge once failed', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->queued()->create(['created_at' => now()->subMinute()]);
    Generation::factory()->for($user)->create([
        'status' => GenerationStatus::Failed,
        'charge_nanos' => 0,
        'error_message' => 'Prompt rejected.',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('generations.data.0.status', 'failed')
            ->where('generations.data.0.charge.display', '$0.0000')
            ->where('generations.data.0.error_message', 'Prompt rejected.')
            ->where('generations.data.1.status', 'queued')
            ->where('generations.data.1.charge', null));
});

it('requires a signed-in user', function (): void {
    $this->get(route('generations.index'))->assertRedirect(route('login'));
});

it('uses the studio as the home page', function (): void {
    $this->get(route('home'))->assertRedirect(route('generations.index'));
});
