<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| Pest-style tests in Feature and Unit run on a fresh database, with the clock frozen and
| outgoing HTTP blocked, so no test can reach the real Pixelforge API by accident.
| Use Http::fake() or a fake client bound in the container inside the test.
|
| The class-based tests that came with the starter kit (auth, settings) keep their own setup.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 10:00:00', 'UTC'));
        Http::preventStrayRequests();
    })
    ->in('Feature', 'Unit');
