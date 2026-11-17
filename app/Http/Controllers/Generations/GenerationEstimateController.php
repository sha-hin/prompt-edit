<?php

namespace App\Http\Controllers\Generations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\EstimateGenerationRequest;
use App\Http\Resources\CostEstimateResource;
use App\Pricing\GenerationEstimator;
use App\Pricing\Rates;

final class GenerationEstimateController extends Controller
{
    /**
     * Estimate the price range for the form's current values, before submitting.
     */
    public function __invoke(EstimateGenerationRequest $request, GenerationEstimator $estimator, Rates $rates): CostEstimateResource
    {
        return new CostEstimateResource($estimator->estimate($request->spec(), $rates));
    }
}
