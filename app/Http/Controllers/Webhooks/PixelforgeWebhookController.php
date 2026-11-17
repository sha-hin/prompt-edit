<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PixelforgeWebhookController extends Controller
{
    /**
     * Receive a Pixelforge event: verify, store once, answer 200, process in a job.
     */
    public function __invoke(Request $request): Response
    {
        abort(Response::HTTP_NOT_IMPLEMENTED); // non-2xx, so Pixelforge keeps retrying until this is built
    }
}
