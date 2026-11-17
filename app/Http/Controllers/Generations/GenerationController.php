<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
    public function store(Request $request): RedirectResponse
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED); // CreateGeneration action comes in a later step
    }
}
