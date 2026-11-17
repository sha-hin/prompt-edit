<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A request Pixelforge answered with an error. isRetryable() tells the caller what to do.
 */
final class PixelforgeException extends RuntimeException
{
    public const int DEFAULT_RETRY_AFTER = 5;               // seconds, when Retry-After is missing or unreadable

    private function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly int $retryAfter,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message');
        $retryAfter = $response->header('Retry-After');

        return new self(
            is_string($message) ? $message : "Pixelforge answered HTTP {$response->status()}.",
            $response->status(),
            is_string($code) ? $code : 'http_' . $response->status(),
            is_numeric($retryAfter) ? max(1, (int) $retryAfter) : self::DEFAULT_RETRY_AFTER,
        );
    }

    /**
     * A 2xx whose body isn't what the API documents, such as an HTML page from a proxy.
     */
    public static function malformedResponse(string $detail): self
    {
        // 502, so it is retried: safe for creates, since the retry replays the same Idempotency-Key
        return new self("Pixelforge sent an unexpected response: {$detail}", 502, 'malformed_response', self::DEFAULT_RETRY_AFTER);
    }

    /**
     * Rate limits, concurrency limits and overloads clear up; everything else won't on retry.
     */
    public function isRetryable(): bool
    {
        if ($this->status === 429) {
            return $this->errorCode !== 'daily_limit_exceeded';   // resets at the next UTC day, not in seconds
        }

        return $this->status >= 500;
    }
}
