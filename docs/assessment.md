# Senior Product Engineer: take-home assessment

Thanks for applying to PromptEdit. This assessment is a small version of the work you'd do with us: integrate an AI provider, charge users accurately for what they generate, and build a UI people enjoy using.

## The provider: Pixelforge

Pixelforge is a fictional AI image-generation API we built for this assessment. It behaves like the real providers we integrate with:

- Generations are asynchronous. You submit a request, then learn the result from a webhook or by polling.
- It's priced per token. Some token counts are only known once a generation finishes, and the API never tells you a price. You work it out from the token usage and the published rates.
- It fails sometimes: rate limits, concurrency limits, overloads, generations that fail after being accepted, and webhooks delivered more than once or out of order.

Everything is documented in the API docs. Read them carefully. Most of what we look at is in there.

To start, create a Pixelforge account and an API key. The images are abstract placeholders captioned with your prompt, and nothing costs real money.

## What to build

Build an app with Laravel, Vue and Inertia where users can generate images. In the backend, you'll pass those requests on to Pixelforge, then show the generated images in the user's UI once they finish. Users sign in, and the app records what each generation cost them. Credits are simply US dollars here.

- **Generating.** A page where users write a prompt and choose a size, quality and optional reference image URLs. Show them what it will cost before they submit.
- **Pricing.** Users pay Pixelforge's cost plus a 25% markup. Part of Pixelforge's cost is only known after a generation completes, so the price you show up front is an estimate. Once a generation finishes, record what it actually cost the user. Generations that fail cost nothing. Pixelforge's costs come in fractions of a cent; how you handle that is up to you.
- **History.** Users see their generations with live status (queued, processing, succeeded or failed), the estimated and the actual cost, and the image. It's fine if images stop loading after Pixelforge's URLs expire.
- **Webhooks.** Receive Pixelforge's webhooks and verify their signatures. Your app must reach the right state even when events arrive twice or out of order, and an event delivered twice must never be recorded twice.
- **Deploy** it somewhere public, on any host you like. Laravel Cloud is probably the easiest.

## Stretch goals (bonus points)

Only once everything above works. Either or both earn bonus points:

- **Admin report.** A page for us, the operators, showing credit spend (what users were charged), provider costs (what Pixelforge charged us) and profit, for each day and each user. The provider costs should match what Pixelforge reports in `GET /v1/usage`, and we will check them against our own records.
- **Balances.** Track each user's credit balance, add a "Buy credits" button that adds credits to their account (a fake purchase, no real payments), and block generating images when the user can't afford it.

Some details are deliberately left open. When something is unclear, make a reasonable decision. We'll ask you about your choices in the technical interview.

## What to send us

Submit everything through the application form:

- A public GitHub repository.
- The URL of your deployed app. Leave a few generations on it so we can look around and compare your numbers with Pixelforge's records.
- The email address of your Pixelforge account.

## Time

We expect the required part to take about 2 to 4 hours, including deploying; the stretch goals are extra. Put correctness ahead of polish: a small app that records every cost exactly right beats a bigger one that doesn't.

## AI tools

Use whatever tools you normally use, including AI coding assistants. In the technical interview we'll ask you to walk through your code and extend it live, so make sure you understand every line you submit.

## What we look at

- **Correct money.** Every generation is recorded at exactly the right cost, never twice, and failed generations are free.
- **Robustness.** Your app copes with what Pixelforge does: limits, overloads, failures, and duplicate or out-of-order webhooks.
- **Product sense and UX.** It's clear what things cost, what's happening, and what went wrong.
- **Code quality.** Code that's easy to read and change.

## Tips

- The docs list prompt keywords such as `[fail]`, `[slow]` and `[duplicate]` that force specific behaviour, so you can test your error handling on purpose.
- The Pixelforge dashboard shows every webhook delivery attempt and what your endpoint answered, and can resend events.
- For webhooks to reach your machine during development, use a tunnel such as Expose, ngrok or Cloudflare Tunnel.
- Create one Pixelforge API key per environment, for example local and deployed. Each key has its own webhook URL and signing secret, and `GET /v1/usage` reports each key separately, so your deployed app's numbers can match it exactly.
