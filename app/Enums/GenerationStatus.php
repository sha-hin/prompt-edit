<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';           // saved, not yet accepted by Pixelforge
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
