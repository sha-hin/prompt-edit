<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\EstimateGenerationRequest;
use App\Http\Resources\CostEstimateResource;
use App\Pixelforge\PixelforgeClient;
use App\Pricing\GenerationEstimator;

final class GenerationEstimateController extends Controller
{
    /**
     * Estimate the price range for the form's current values, before submitting.
     */
    public function __invoke(EstimateGenerationRequest $request, GenerationEstimator $estimator, PixelforgeClient $pixelforge): CostEstimateResource
    {
        return new CostEstimateResource($estimator->estimate($request->spec(), $pixelforge->pricing()));
    }
}
