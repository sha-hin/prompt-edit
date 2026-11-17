<?php

namespace App\Http\Controllers\Generations;

use App\Credits\InsufficientCredits;
use App\Generations\CreateGeneration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    public const int PER_PAGE = 12;

    /**
     * Show the studio: the prompt form and the user's generation history.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('generations/Index', [
            'generations' => fn () => GenerationResource::collection(   // a closure, so polling reloads only this prop
                $user->generations()->latest()->latest('id')->paginate(self::PER_PAGE),
            ),
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
