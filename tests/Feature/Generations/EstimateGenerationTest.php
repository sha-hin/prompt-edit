<?php

use App\Models\User;

it('requires a signed-in user', function (): void {
    $this->postJson(route('generations.estimate'), ['prompt' => 'a red cube'])
        ->assertUnauthorized();
});

it('returns the charge range with Pixelforge defaults filled in', function (): void {
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
                ],
                'tokens' => [
                    'min' => ['input_text' => 2, 'input_image' => 0, 'output' => 1_100, 'reasoning' => 200, 'total' => 1_302],
                    'max' => ['input_text' => 4, 'input_image' => 0, 'output' => 1_100, 'reasoning' => 2_000, 'total' => 3_104],
                ],
            ],
        ]);
});

it('prices the worked maximum from the Pixelforge docs', function (): void {
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
