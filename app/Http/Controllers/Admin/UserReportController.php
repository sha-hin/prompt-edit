<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SpendReportRequest;
use App\Http\Requests\Admin\UsersReportRequest;
use App\Http\Resources\Admin\AdminGenerationResource;
use App\Http\Resources\Admin\CreditTransactionResource;
use App\Http\Resources\Admin\UserReportResource;
use App\Http\Resources\Admin\UserRowResource;
use App\Models\User;
use App\Reports\BuildUserReport;
use App\Reports\FinishedGenerations;
use Illuminate\Database\Query\JoinClause;
use Inertia\Inertia;
use Inertia\Response;

final class UserReportController extends Controller
{
    public const int USERS_PER_PAGE = 25;

    public const int ROWS_PER_PAGE = 10;

    /**
     * Every user, with what they spent in the range. Users with no activity are listed at $0.
     */
    public function index(UsersReportRequest $request): Response
    {
        $spend = FinishedGenerations::between($request->start(), $request->end())
            ->addSelect('user_id')
            ->groupBy('user_id')
            ->toBase();

        $users = User::query()
            ->leftJoinSub($spend, 'spend', fn (JoinClause $join) => $join->on('spend.user_id', '=', 'users.id'))
            ->select('users.*', 'spend.succeeded', 'spend.failed', 'spend.spend', 'spend.cost')
            ->when($request->search(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('users.name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('users.email', 'like', '%' . addcslashes($search, '%_\\') . '%')));

        match ($request->sort()) {
            'joined' => $users->orderByDesc('users.created_at'),
            'balance' => $users->orderByDesc('users.balance_nanos'),
            default => $users->orderByRaw('COALESCE(spend.spend, 0) DESC'),
        };

        $users = $users
            ->orderBy('users.id')                           // a stable order across pages
            ->paginate(self::USERS_PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => UserRowResource::collection($users),
            'filters' => [
                'start' => $request->start()->toDateString(),
                'end' => $request->end()->toDateString(),
                'search' => $request->search(),
                'sort' => $request->sort(),
            ],
        ]);
    }

    /**
     * One user: their spend by day in the range, every generation they made, and their credit ledger.
     */
    public function show(SpendReportRequest $request, User $user, BuildUserReport $buildUserReport): Response
    {
        return Inertia::render('admin/users/Show', [
            'report' => UserReportResource::make($buildUserReport($user, $request->start(), $request->end()))->resolve($request),
            'generations' => AdminGenerationResource::collection(
                $user->generations()->latest()->latest('id')
                    ->paginate(self::ROWS_PER_PAGE, pageName: 'generations_page')
                    ->withQueryString(),
            ),
            'ledger' => CreditTransactionResource::collection(
                $user->creditTransactions()
                    ->select('*')
                    ->selectRaw('SUM(amount_nanos) OVER (ORDER BY id) as balance_after')   // before LIMIT, so it runs over the whole ledger
                    ->latest('id')
                    ->paginate(self::ROWS_PER_PAGE, pageName: 'ledger_page')
                    ->withQueryString(),
            ),
        ]);
    }
}
