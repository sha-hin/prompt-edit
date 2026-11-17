<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;

$cube = ['prompt' => 'a red cube', 'size' => '1024x1024', 'quality' => 'low'];

it('lets a user generate after the estimate has refreshed many times while they typed', function () use ($cube): void {
    Queue::fake();
    fakePricing();
    $user = User::factory()->withCredits()->create();

    foreach (range(1, 30) as $_) {
        $this->actingAs($user)->postJson(route('generations.estimate'), $cube)->assertOk();
    }

    $this->actingAs($user)->post(route('generations.store'), $cube)->assertRedirect();
});

it('still limits each route on its own', function (string $route, int $limit) use ($cube): void {
    Queue::fake();
    fakePricing();
    $user = User::factory()->withCredits(1000)->create();

    foreach (range(1, $limit) as $_) {
        expect($this->actingAs($user)->postJson(route($route), $cube)->status())->not->toBe(429);
    }

    $this->actingAs($user)->postJson(route($route), $cube)->assertTooManyRequests();
})->with([
    'estimate' => ['generations.estimate', 60],
    'generate' => ['generations.store', 10],
]);

it('counts each user separately', function () use ($cube): void {
    Queue::fake();
    fakePricing();
    $busy = User::factory()->withCredits(1000)->create();

    foreach (range(1, 10) as $_) {
        $this->actingAs($busy)->post(route('generations.store'), $cube);
    }

    $this->actingAs(User::factory()->withCredits()->create())
        ->post(route('generations.store'), $cube)
        ->assertRedirect();
});
