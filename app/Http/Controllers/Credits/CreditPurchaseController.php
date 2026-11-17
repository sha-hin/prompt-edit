<?php

namespace App\Http\Controllers\Credits;

use App\Credits\CreditLedger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credits\PurchaseCreditsRequest;
use App\Models\User;
use App\Money\Money;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class CreditPurchaseController extends Controller
{
    /**
     * A fake purchase: no payment, the pack's credit is added straight away.
     */
    public function __invoke(PurchaseCreditsRequest $request, #[CurrentUser] User $user, CreditLedger $ledger): RedirectResponse
    {
        $purchase = $ledger->purchase($user, $request->pack(), $request->string('idempotency_key')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added :amount in credits.', [
            'amount' => Money::nanos($purchase->amount_nanos)->format(2),
        ])]);

        return back();
    }
}
