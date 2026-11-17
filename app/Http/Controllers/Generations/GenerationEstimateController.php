<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class GenerationEstimateController extends Controller
{
    /**
     * Estimate the price range for the form's current values, before submitting.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED); // Pricing calculator comes in a later step
    }
}
