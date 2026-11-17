<?php

namespace App\Http\Controllers\Generations;

use App\Generations\CreateGeneration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\StoreGenerationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    /**
     * Show the studio: the prompt form and the user's generation history.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('generations/Index');
    }

    /**
     * Accept a generation request and queue it for Pixelforge.
     */
    public function store(StoreGenerationRequest $request, #[CurrentUser] User $user, CreateGeneration $createGeneration): RedirectResponse
    {
        $createGeneration->handle($user, $request->spec());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation queued.')]);

        return to_route('generations.index');
    }
}
