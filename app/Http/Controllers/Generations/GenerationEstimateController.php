<?php

namespace App\Http\Controllers\Generations;

use App\Credits\CreditLedger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Generations\EstimateGenerationRequest;
use App\Http\Resources\CostEstimateResource;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pricing\GenerationEstimator;
use Illuminate\Container\Attributes\CurrentUser;

final class GenerationEstimateController extends Controller
{
    /**
     * Estimate the price range for the form's current values, before submitting.
     */
    public function __invoke(
        EstimateGenerationRequest $request,
        #[CurrentUser] User $user,
        GenerationEstimator $estimator,
        PixelforgeClient $pixelforge,
        CreditLedger $ledger,
    ): CostEstimateResource {
        $estimate = $estimator->estimate($request->spec(), $pixelforge->pricing());

        return new CostEstimateResource($estimate)->additional([
            'can_afford' => $ledger->available($user)->nanos >= $estimate->max->charge->nanos,   // the same check store makes
        ]);
    }
}
