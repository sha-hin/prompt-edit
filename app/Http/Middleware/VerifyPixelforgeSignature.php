<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject anything not signed by Pixelforge: HMAC-SHA256 of "<t>.<raw body>" in `Pixelforge-Signature: t=…,v1=…`.
 */
final class VerifyPixelforgeSignature
{
    public const int TOLERANCE_SECONDS = 300;               // Pixelforge: reject timestamps older than 5 minutes

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');

        abort_if($secret === '', Response::HTTP_INTERNAL_SERVER_ERROR, 'The Pixelforge webhook secret is not set.');  // 5xx, so Pixelforge retries once it is

        $signature = $this->parse((string) $request->header('Pixelforge-Signature'));
        $timestamp = $signature['t'] ?? '';

        abort_unless(
            ctype_digit($timestamp)
                && abs(now()->getTimestamp() - (int) $timestamp) <= self::TOLERANCE_SECONDS
                && hash_equals(hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret), $signature['v1'] ?? ''),
            Response::HTTP_BAD_REQUEST,
            'Invalid Pixelforge signature.',
        );

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }
}
