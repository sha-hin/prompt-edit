<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class GenerationImageController extends Controller
{
    /**
     * Download the image as a file. Browsers ignore `download` on another site's link, so the PNG is
     * passed through from Pixelforge's signed URL with an attachment header. Nothing is stored.
     */
    public function __invoke(Generation $generation, PixelforgeClient $pixelforge): Response|RedirectResponse
    {
        Gate::authorize('view', $generation);

        if (! $generation->imageAvailable() || $generation->image_url === null) {
            return $this->backWithError(__('This image has expired. Pixelforge keeps images for 24 hours.'));
        }

        try {
            $png = $pixelforge->downloadImage($generation->image_url);
        } catch (PixelforgeException|ConnectionException $e) {
            report($e);

            return $this->backWithError(__('The image could not be downloaded right now. Please try again.'));
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="promptedit-' . strtolower(substr($generation->id, -8)) . '.png"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function backWithError(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
