<?php

namespace App\Providers;

use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(function (): PixelforgeClient {
            /** @var array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} $fallbackRates */
            $fallbackRates = config()->array('services.pixelforge.rates');

            return new PixelforgeClient(
                baseUrl: config()->string('services.pixelforge.base_url'),
                key: (string) config('services.pixelforge.key'),        // empty until set; Pixelforge answers 401
                timeout: config()->integer('services.pixelforge.timeout'),
                connectTimeout: config()->integer('services.pixelforge.connect_timeout'),
                fallbackRates: $fallbackRates,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimits();

        Gate::define('viewAdminReport', fn (User $user): bool => $user->is_admin);
    }

    /**
     * Per-user limits, one counter per limiter. A plain `throttle:10,1` keys only by
     * user, so every throttled route would share one counter: the estimate, which
     * refreshes as the user types, would use up the budget for generating.
     */
    protected function configureRateLimits(): void
    {
        $perUser = fn (int $perMinute): Closure => fn (Request $request): Limit => Limit::perMinute($perMinute)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('estimates', $perUser(60));
        RateLimiter::for('generations', $perUser(10));
        RateLimiter::for('downloads', $perUser(30));
        RateLimiter::for('credit-purchases', $perUser(10));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Lazy loading, unknown attributes and silently discarded attributes throw outside production.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
