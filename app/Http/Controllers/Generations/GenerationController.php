<?php

namespace App\Http\Controllers\Generations;

use App\Credits\InsufficientCredits;
use App\Generations\CreateGeneration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\GenerationHistoryRequest;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    public const int RECENT = 6;

    public const int PER_PAGE = 24;

    /**
     * The studio: the prompt form and the latest few generations.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('generations/Index', [
            'generations' => fn () => GenerationResource::collection(   // closures, so polling reloads only these
                $user->generations()->latest()->latest('id')->limit(self::RECENT)->get(),
            ),
            'total' => fn (): int => $user->generations()->count(),
        ]);
    }

    /**
     * Every generation the user made, filtered by status and prompt.
     */
    public function history(GenerationHistoryRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('generations/History', [
            'generations' => fn () => GenerationResource::collection(
                $request->filter($user->generations()->getQuery())
                    ->latest()
                    ->latest('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString(),
            ),
            'filters' => $request->filters(),
        ]);
    }

    /**
     * One generation in full: the image, its settings, and what it cost.
     */
    public function show(Generation $generation): Response
    {
        Gate::authorize('view', $generation);

        return Inertia::render('generations/Show', [
            'generation' => fn () => GenerationResource::make($generation)->resolve(),
        ]);
    }

    /**
     * Accept a generation request and queue it for Pixelforge.
     */
    public function store(StoreGenerationRequest $request, #[CurrentUser] User $user, CreateGeneration $createGeneration): RedirectResponse
    {
        try {
            $createGeneration->handle($user, $request->spec());
        } catch (InsufficientCredits $e) {
            throw ValidationException::withMessages(['credits' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation queued.')]);

        return to_route('generations.index');
    }
}
