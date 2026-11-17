# Pixelforge API Reference (working notes)

Source: https://pixelforge.promptedit.dev/docs. Captured 2026-11-17. If anything here disagrees with the live page, the live page wins.

Pixelforge is a **simulated** image-generation provider. The images are placeholders and nothing costs real money. Pricing, limits, timing, failures and webhooks all behave like a real provider would.

---

## Basics

- Base URL: `https://pixelforge.promptedit.dev/v1`
- JSON request and response bodies
- Timestamps are ISO 8601 UTC, for example `2026-11-04T14:03:11Z`
- ID prefixes: `gen_` for generations, `evt_` for webhook events
- Money is returned as exact decimal strings in USD, never floats

## Authentication

- Header: `Authorization: Bearer <key>`
- A key is shown once, when it's created. A revoked key stops working immediately.
- Each key has its own **webhook URL**, **signing secret** (`whsec_…`) and **usage report**. Use one key per environment.
- A missing, malformed or revoked key returns `401 invalid_api_key`.

---

## Endpoints

### POST /v1/generations: create a generation

Returns `202 Accepted` with the generation in `queued` status.

| Param              | Type   | Req | Notes                                                                                            |
| ------------------ | ------ | --- | ------------------------------------------------------------------------------------------------ |
| `prompt`           | string | yes | Max 4,000 chars                                                                                  |
| `size`             | string | no  | `1024x1024` (default), `1536x1024`, `1024x1536`                                                  |
| `quality`          | string | no  | `low`, `medium` (default), `high`                                                                |
| `reference_images` | URL[]  | no  | Up to 4 http(s) URLs. They are not downloaded, but each one is billed as 765 input image tokens. |
| `metadata`         | object | no  | Up to 20 string keys, each value up to 500 chars. Echoed back in the generation and in webhooks. |

- Unknown parameters are rejected with `422 validation_failed`.
- Optional header: `Idempotency-Key`, up to 255 chars. It never expires.

### GET /v1/generations/{id}: retrieve a generation

- A generation that belongs to another account returns `404 not_found`.

### GET /v1/generations: list generations

- Newest first, cursor-paginated.
- Query params: `limit` (1–100, default 20), `status` (`queued`, `processing`, `succeeded` or `failed`), `cursor` (the `next_cursor` from the previous page).
- Response fields: `object: "list"`, `data[]`, `has_more`, `next_cursor`.

### GET /v1/pricing: current rates (no auth)

- Returns `currency: "usd"`, `unit: "per_1m_tokens"` and the current `rates`.
- Also returns token-estimation data: characters per text token, text variance, tokens per reference image, and output and reasoning token figures by quality and size.

### GET /v1/usage: billed usage per UTC day

- Query params: `start_date`, `end_date` (`YYYY-MM-DD`, both inclusive). Defaults to the last 30 days. Max range is 92 days; a longer range returns `422 date_range_too_long`.
- Covers only generations created with **the calling key**.
- A generation is billed on the UTC day it **completed**, not the day it was created.
- Response: a `data[]` of daily entries (`date`, `generations`, token counts, `cost_usd`), with zero entries for empty days, plus a `total` object.
- `cost_usd` has 9 decimal places.

---

## Generation object

| Field                                                       | Notes                                                                                                                                          |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`, `object`                                              | `gen_…`; `object` is always `"generation"`                                                                                                     |
| `status`                                                    | `queued` → `processing` → `succeeded` or `failed`. Both `succeeded` and `failed` are final.                                                    |
| `prompt`, `size`, `quality`, `reference_images`, `metadata` | As requested, with defaults filled in. `metadata` is `{}` when empty.                                                                          |
| `image_url`                                                 | Signed PNG URL once the generation succeeds; null otherwise                                                                                    |
| `image_url_expires_at`                                      | When `image_url` stops working                                                                                                                 |
| `usage`                                                     | Null until the generation succeeds. Then holds `input_text_tokens`, `input_image_tokens`, `output_tokens`, `reasoning_tokens`, `total_tokens`. |
| `rates`                                                     | Per-1M-token rates, **locked when the generation is created**                                                                                  |
| `error`                                                     | Null unless the generation failed; then holds `code` and `message`                                                                             |
| `created_at`, `started_at`, `completed_at`                  | Lifecycle timestamps                                                                                                                           |

- Typical timing: a few seconds in the queue, then 5–30 seconds processing.
- **The API never returns a price.** You calculate cost from `usage` × `rates`.

---

## Pricing and token counting

| Token type  | $ per 1M tokens |
| ----------- | --------------- |
| Input text  | 5.00            |
| Input image | 10.00           |
| Output      | 40.00           |
| Reasoning   | 12.50           |

- Input text: estimate it as `ceil(chars / 4)`. The actual count is within ±15% and is known only after completion.
- Input image: exactly 765 tokens per reference image.
- Output tokens are exact:

| Quality | 1024x1024 | 1536x1024 | 1024x1536 |
| ------- | --------- | --------- | --------- |
| low     | 280       | 420       | 420       |
| medium  | 1,100     | 1,650     | 1,650     |
| high    | 4,300     | 6,450     | 6,450     |

- Reasoning tokens fall in a range: low 0–500, medium 200–2,000, high 1,000–8,000.
- Worked maximum from the docs: a high-quality 1536x1024 image with a 400-char prompt and 2 reference images costs at most **$0.373875**.
- Only succeeded generations are billed. Use integer or decimal arithmetic, never floats.

---

## Errors

All errors share this shape:

```
{ "error": { "type", "code", "message", "param"?, "errors"? } }
```

`param` and `errors` appear only on validation errors.

| Code                         | Type                  | HTTP | Meaning                                          |
| ---------------------------- | --------------------- | ---- | ------------------------------------------------ |
| `invalid_api_key`            | authentication_error  | 401  | Missing, malformed or revoked key                |
| `validation_failed`          | invalid_request_error | 422  | Invalid or unknown parameter                     |
| `content_policy_violation`   | invalid_request_error | 400  | Prompt rejected. Nothing is created or billed.   |
| `not_found`                  | invalid_request_error | 404  | Doesn't exist, or belongs to another account     |
| `idempotency_key_reused`     | idempotency_error     | 409  | Same key sent with different parameters          |
| `rate_limit_exceeded`        | rate_limit_error      | 429  | Per-minute limit hit. Honor `Retry-After`.       |
| `concurrency_limit_exceeded` | rate_limit_error      | 429  | 3 generations already active. `Retry-After: 5`.  |
| `daily_limit_exceeded`       | rate_limit_error      | 429  | 500 generations already used this UTC day        |
| `overloaded`                 | api_error             | 503  | Temporary. `Retry-After: 2`. Nothing is created. |
| `date_range_too_long`        | invalid_request_error | 422  | Usage range is longer than 92 days               |

A generation that fails after it was accepted carries one of these codes in `error.code`:

- `generation_failed`: no image could be made for the prompt
- `internal_error`: a retry usually works

Neither is billed.

---

## Rate limits

Limits are **per account, across all keys**:

- 60 requests per minute
- 3 concurrent generations (queued + processing)
- 500 generations per UTC day

How they behave:

- A rejected request creates nothing and costs nothing.
- Every response carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`.
- To retry: honor `Retry-After`, back off progressively, and stop after a few attempts.
- Retries count toward the per-minute limit.

## Idempotency

Send an `Idempotency-Key` header on `POST /v1/generations`.

- **Same key, same params:** you get back the existing generation in its current status, with `202` and the header `Idempotent-Replayed: true`. No new generation is made and nothing extra is charged. A replay doesn't count against the concurrency or daily limits.
- **Same key, different params:** `409 idempotency_key_reused`.
- Keys are scoped to the account and never expire.

---

## Webhooks

- Each key has its own webhook URL and signing secret. Events go only to the URL of **the key that created the generation**.

| Event                   | When it's sent                              |
| ----------------------- | ------------------------------------------- |
| `generation.processing` | The generation leaves the queue             |
| `generation.succeeded`  | Finished. Includes `usage` and `image_url`. |
| `generation.failed`     | Failed. Includes `error`.                   |
| `ping`                  | Test event sent from the dashboard          |

- Payload fields: `id` (`evt_…`), `object: "event"`, `type`, `created_at`, and `data.object`. `data.object` is the full generation as it was when the event fired.
- Headers: `Pixelforge-Event-Id`, `Pixelforge-Event-Type`, `Pixelforge-Delivery-Attempt`, `Pixelforge-Signature`.

### Signature verification

- The header looks like `t=<unix_ts>,v1=<hex>`.
- The signature is a hex HMAC-SHA256 of `"<t>.<raw body>"`, keyed with the signing secret.
- Compare in constant time.
- Reject timestamps older than 5 minutes.
- Verify the **raw body** before parsing the JSON.
- Deduplicate on the event `id`.

### Delivery

- Success means any 2xx response within 10 seconds.
- Anything else is retried at 10s, 30s, 2m, 10m and 15m, for 6 attempts in total.
- Redirects are **not** followed.
- Delivery is at least once and may arrive **out of order**. Never move a generation back out of `succeeded` or `failed`.
- A webhook can arrive **before the create request returns**.
- Delivery isn't guaranteed, so keep a polling fallback.
- Local development: the URL must be public http(s). Private and loopback addresses are rejected, so use a tunnel such as ngrok, Expose or Cloudflare Tunnel. The dashboard shows delivery attempts and can resend events.

## Images

- `image_url` is a signed PNG at the requested size.
- It expires **24 hours after completion**. Download and store the image if you need it longer.
- Fetching the generation again returns the same expiry; it isn't refreshed.

---

## Testing keywords

Put these anywhere in the prompt. They're case-insensitive and still count toward text tokens.

| Keyword        | Effect                                          |
| -------------- | ----------------------------------------------- |
| `[blocked]`    | 400 `content_policy_violation` at submission    |
| `[overloaded]` | 503 `overloaded` at submission                  |
| `[fail]`       | Accepted, then fails. Not billed.               |
| `[succeed]`    | Never fails randomly                            |
| `[slow]`       | Takes 60–90 seconds instead of 5–30             |
| `[duplicate]`  | Every webhook for this generation is sent twice |

Without any keyword, the simulator randomly does three things:

- fails a small share of generations
- rejects a small share of create requests with 503
- occasionally sends a webhook event twice
