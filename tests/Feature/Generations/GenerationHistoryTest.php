<?php

use App\Enums\GenerationStatus;
use App\Http\Controllers\Generations\GenerationController;
use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists every generation the user made, newest first, in pages', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->count(GenerationController::PER_PAGE + 1)->create(['created_at' => now()->subHour()]);
    $newest = Generation::factory()->for($user)->create();
    Generation::factory()->create();                        // someone else's

    $this->actingAs($user)
        ->get(route('generations.history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('generations/History')
            ->has('generations.data', GenerationController::PER_PAGE)
            ->where('generations.data.0.id', $newest->id)
            ->where('generations.meta.total', GenerationController::PER_PAGE + 2)
            ->where('generations.meta.last_page', 2));
});

it('filters by status', function (string $status, int $count): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Succeeded]);
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Failed]);
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Pending]);
    Generation::factory()->for($user)->queued()->create();
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Processing]);

    $this->actingAs($user)
        ->get(route('generations.history', ['status' => $status]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('generations.data', $count)
            ->where('filters.status', $status));
})->with([
    'succeeded' => ['succeeded', 1],
    'failed' => ['failed', 1],
    'running' => ['running', 3],
]);

it('searches the prompt, treating % and _ as plain text', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['prompt' => 'A red cube on a table']);
    Generation::factory()->for($user)->create(['prompt' => 'A blue sphere']);
    Generation::factory()->for($user)->create(['prompt' => '100% cotton']);

    $this->actingAs($user)
        ->get(route('generations.history', ['search' => 'cube']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.prompt', 'A red cube on a table'));

    $this->actingAs($user)
        ->get(route('generations.history', ['search' => '%']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('generations.data', 1));
});

it('keeps the filters in the page links', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->count(GenerationController::PER_PAGE + 1)->create(['status' => GenerationStatus::Failed]);

    $this->actingAs($user)
        ->get(route('generations.history', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('generations.links.next', route('generations.history', ['status' => 'failed', 'page' => 2])));
});

it('rejects an unknown status', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('generations.history', ['status' => 'deleted']))
        ->assertSessionHasErrors('status');
});

it('requires a signed-in user', function (): void {
    $this->get(route('generations.history'))->assertRedirect(route('login'));
});
