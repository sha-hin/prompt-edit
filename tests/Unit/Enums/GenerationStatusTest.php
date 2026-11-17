<?php

use App\Enums\GenerationStatus;

mutates(GenerationStatus::class);

it('only moves forward and never out of a final status', function (GenerationStatus $from, GenerationStatus $to, bool $allowed): void {
    expect($from->canMoveTo($to))->toBe($allowed);
})->with([
    [GenerationStatus::Pending, GenerationStatus::Queued, true],
    [GenerationStatus::Pending, GenerationStatus::Processing, true],
    [GenerationStatus::Pending, GenerationStatus::Succeeded, true],
    [GenerationStatus::Queued, GenerationStatus::Processing, true],
    [GenerationStatus::Queued, GenerationStatus::Failed, true],
    [GenerationStatus::Processing, GenerationStatus::Succeeded, true],
    [GenerationStatus::Processing, GenerationStatus::Failed, true],
    [GenerationStatus::Queued, GenerationStatus::Queued, false],
    [GenerationStatus::Processing, GenerationStatus::Queued, false],
    [GenerationStatus::Queued, GenerationStatus::Pending, false],
    [GenerationStatus::Succeeded, GenerationStatus::Failed, false],
    [GenerationStatus::Failed, GenerationStatus::Succeeded, false],
    [GenerationStatus::Succeeded, GenerationStatus::Processing, false],
]);

it('knows which statuses are final', function (GenerationStatus $status, bool $final): void {
    expect($status->isFinal())->toBe($final);
})->with([
    [GenerationStatus::Pending, false],
    [GenerationStatus::Queued, false],
    [GenerationStatus::Processing, false],
    [GenerationStatus::Succeeded, true],
    [GenerationStatus::Failed, true],
]);
