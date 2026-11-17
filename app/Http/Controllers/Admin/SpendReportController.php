<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SpendReportRequest;
use App\Http\Resources\SpendReportResource;
use App\Reports\BuildSpendReport;
use Inertia\Inertia;
use Inertia\Response;

final class SpendReportController extends Controller
{
    public function __invoke(SpendReportRequest $request, BuildSpendReport $buildSpendReport): Response
    {
        return Inertia::render('admin/Report', [
            'report' => SpendReportResource::make($buildSpendReport($request->start(), $request->end()))->resolve($request),
        ]);
    }
}
