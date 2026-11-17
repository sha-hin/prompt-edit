<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Pixelforge\ProcessPixelforgeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PixelforgeWebhookController extends Controller
{
    /**
     * Receive a signed Pixelforge event (checked by VerifyPixelforgeSignature), apply it once, answer 200.
     */
    public function __invoke(Request $request, ProcessPixelforgeEvent $processEvent): JsonResponse
    {
        $event = json_decode($request->getContent(), true);         // parsed only now, after the raw body was verified

        abort_unless(
            is_array($event) && is_string($event['id'] ?? null) && is_string($event['type'] ?? null),
            Response::HTTP_BAD_REQUEST,
            'Not a Pixelforge event.',
        );

        $processEvent->handle($event['id'], $event['type'], $event);

        return response()->json(['received' => true]);
    }
}
