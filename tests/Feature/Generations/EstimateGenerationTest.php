<?php

use App\Credits\CreditLedger;
use App\Models\User;
use App\Money\Money;
use Illuminate\Support\Facades\Http;

it('requires a signed-in user', function (): void {
    $this->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertUnauthorized();
});

it('returns the charge range with Pixelforge defaults filled in', function (): void {
    fakePricing();
    // "a red cube" is 10 chars -> 3 tokens (2-4); medium 1024x1024 is 1,100 output and 200-2,000 reasoning
    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'charge' => [
                    'min' => '0.058137500',
                    'max' => '0.086275000',
                    'display' => '$0.0581 – $0.0863',
                    'max_display' => '$0.0863',
                ],
                'tokens' => [
                    'min' => ['input_text' => 2, 'input_image' => 0, 'output' => 1_100, 'reasoning' => 200, 'total' => 1_302],
                    'max' => ['input_text' => 4, 'input_image' => 0, 'output' => 1_100, 'reasoning' => 2_000, 'total' => 3_104],
                ],
            ],
            'can_afford' => false,                                  // a factory user has no credit
        ]);
});

it('prices the worked maximum from the Pixelforge docs', function (): void {
    fakePricing();
    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', 'http://example.com/b.png'],
        ])
        ->assertOk()
        ->assertJsonPath('data.charge.min', '0.357781250')
        ->assertJsonPath('data.charge.max', '0.467343750')    // $0.373875 Pixelforge cost + 25%
        ->assertJsonPath('data.charge.display', '$0.3578 – $0.4673');
});

it('estimates while the prompt is still empty', function (): void {
    fakePricing();
    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [])
        ->assertOk()
        ->assertJsonPath('data.tokens.max.input_text', 0);
});

it('rejects invalid options', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'prompt too long' => [['prompt' => str_repeat('a', 4_001)], 'prompt'],
    'unknown size' => [['size' => '512x512'], 'size'],
    'unknown quality' => [['quality' => 'ultra'], 'quality'],
    'too many reference images' => [['reference_images' => array_fill(0, 5, 'https://example.com/a.png')], 'reference_images'],
    'reference image is not a URL' => [['reference_images' => ['not a url']], 'reference_images.0'],
    'reference image is not http(s)' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
]);

it('uses the live Pixelforge rates', function (): void {
    fakePricing(['input_text_tokens' => '10.00', 'input_image_tokens' => '20.00', 'output_tokens' => '80.00', 'reasoning_tokens' => '25.00']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertOk()
        ->assertJsonPath('data.charge.min', '0.116275000')           // twice the published rates, so twice the charge
        ->assertJsonPath('data.charge.max', '0.172550000');
});

it('still estimates with the configured rates when Pixelforge pricing is down', function (): void {
    Http::fake(['*/pricing' => Http::response(pixelforgeError('overloaded'), 503)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertOk()
        ->assertJsonPath('data.charge.min', '0.058137500');
});

it('says whether the user can afford the estimate maximum', function (int $credit, bool $canAfford): void {
    fakePricing();
    $user = User::factory()->create();
    app(CreditLedger::class)->grant($user, Money::nanos($credit), 'test-credit');

    $this->actingAs($user)
        ->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertJsonPath('can_afford', $canAfford);
})->with([
    'exactly the maximum' => [86_275_000, true],
    'one nano short' => [86_274_999, false],
]);
