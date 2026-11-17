<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function succeededWithImage(User $user, array $attributes = []): Generation
{
    return Generation::factory()->for($user)->queued()->create([
        'status' => GenerationStatus::Succeeded,
        'cost_nanos' => 11_685_000,
        'charge_nanos' => 14_606_250,
        'usage' => ['input_text_tokens' => 5, 'input_image_tokens' => 0, 'output_tokens' => 280, 'reasoning_tokens' => 34],
        'image_url' => 'https://pixelforge.promptedit.dev/images/gen_1.png?sig=abc',
        'image_url_expires_at' => now()->addDay(),
        'completed_at' => now(),
        ...$attributes,
    ]);
}

it('shows a generation to its owner, with tokens but not Pixelforge\'s cost', function (): void {
    $user = User::factory()->create();
    $generation = succeededWithImage($user);

    $this->actingAs($user)
        ->get(route('generations.show', $generation))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('generations/Show')
            ->where('generation.id', $generation->id)
            ->where('generation.usage.output_tokens', 280)
            ->where('generation.image.status', 'ready')
            ->missing('generation.cost')
            ->missing('generation.cost_nanos'));
});

it('hides other people\'s generations behind a 404', function (string $route): void {
    $generation = succeededWithImage(User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route($route, $generation))->assertNotFound();
})->with(['generations.show', 'generations.download']);

it('lets an operator open anyone\'s generation', function (): void {
    $generation = succeededWithImage(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create())->get(route('generations.show', $generation))->assertOk();
});

it('says the image expired once Pixelforge\'s link has run out', function (): void {
    $user = User::factory()->create();
    $generation = succeededWithImage($user, ['image_url_expires_at' => now()->subMinute()]);

    $this->actingAs($user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('generation.image', [
                'status' => 'expired',
                'url' => null,
                'download_url' => null,
                'expires_at' => now()->subMinute()->toIso8601ZuluString(),
            ]));
});

it('downloads the image as a PNG file, passed through from Pixelforge', function (): void {
    Http::fake(['pixelforge.promptedit.dev/images/*' => Http::response("\x89PNG fake bytes", 200, ['Content-Type' => 'image/png'])]);
    $user = User::factory()->create();
    $generation = succeededWithImage($user);

    $response = $this->actingAs($user)->get(route('generations.download', $generation));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="promptedit-' . strtolower(substr($generation->id, -8)) . '.png"');
    expect($response->getContent())->toBe("\x89PNG fake bytes");
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pixelforge.promptedit.dev/images/gen_1.png?sig=abc');
});

it('lets an operator download anyone\'s image', function (): void {
    Http::fake(['pixelforge.promptedit.dev/images/*' => Http::response("\x89PNG", 200)]);
    $generation = succeededWithImage(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create())->get(route('generations.download', $generation))->assertOk();
});

it('explains instead of downloading when there is no image to fetch', function (array $attributes, string $message): void {
    Http::fake();
    $user = User::factory()->create();
    $generation = succeededWithImage($user, $attributes);

    $this->actingAs($user)
        ->from(route('generations.show', $generation))
        ->get(route('generations.download', $generation))
        ->assertRedirect(route('generations.show', $generation));

    Http::assertNothingSent();
})->with([
    'expired' => [['image_url_expires_at' => '2026-11-16 09:00:00'], 'expired'],
    'failed' => [['status' => GenerationStatus::Failed, 'image_url' => null], 'expired'],
]);

it('says so when Pixelforge will not hand the image over', function (): void {
    Exceptions::fake();
    Http::fake(['pixelforge.promptedit.dev/images/*' => Http::response('', 403)]);
    $user = User::factory()->create();
    $generation = succeededWithImage($user);

    $this->actingAs($user)
        ->from(route('generations.show', $generation))
        ->get(route('generations.download', $generation))
        ->assertRedirect(route('generations.show', $generation));

    Exceptions::assertReportedCount(1);
});
