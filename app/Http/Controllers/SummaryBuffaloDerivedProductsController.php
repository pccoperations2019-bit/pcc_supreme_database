<?php

namespace App\Http\Controllers;

use App\Enums\CenterEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProgramEnum;
use App\Enums\ReportTypeEnum;
use App\Queries\Reports\SummaryManurePerCenterByYearQuery;
use App\Queries\Reports\SummaryManureSalesPerCenterByYearQuery;
use App\Queries\Reports\SummaryMeatPerCenterByYearQuery;
use App\Queries\Reports\SummaryMeatSalesPerCenterByYearQuery;
use App\Queries\Reports\SummaryMilkPerCenterByYearQuery;
use App\Queries\Reports\SummaryMilkSalesPerCenterByYearQuery;
use App\Support\Centers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SummaryBuffaloDerivedProductsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $reportTypeEnum = ReportTypeEnum::tryFrom(
            $request->input('reportType', ReportTypeEnum::PRODUCTION->value)
        ) ?? ReportTypeEnum::PRODUCTION;
        $reportType = $reportTypeEnum->value;

        $categoryEnum = ProductCategoryEnum::tryFrom(
            $request->input('category', ProductCategoryEnum::MILK->value)
        ) ?? ProductCategoryEnum::MILK;
        $category = $categoryEnum->value;
        $hasYearParam = $request->has('year');
        $hasCategoryParam = $request->has('category');
        $hasReportTypeParam = $request->has('reportType');

        $yearsTable = match ($categoryEnum) {
            ProductCategoryEnum::MANURE => $reportTypeEnum === ReportTypeEnum::SALES
                ? 'manure_sales'
                : 'manure_productions',
            ProductCategoryEnum::MEAT => $reportTypeEnum === ReportTypeEnum::SALES
                ? 'meat_sales'
                : 'meat_productions',
            default => $reportTypeEnum === ReportTypeEnum::SALES
                ? 'milk_sales'
                : 'milk_productions',
        };
        $years = DB::table($yearsTable)
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();
        $defaultYear = $years->first() ?? now()->year;
        $year = $hasYearParam ? (int) $request->input('year') : (int) $defaultYear;

        $summary = match ($categoryEnum) {
            ProductCategoryEnum::MANURE => $reportTypeEnum === ReportTypeEnum::SALES
                ? resolve(SummaryManureSalesPerCenterByYearQuery::class)->execute(year: $year)->toArray()
                : resolve(SummaryManurePerCenterByYearQuery::class)->execute(year: $year)->toArray(),
            ProductCategoryEnum::MEAT => $reportTypeEnum === ReportTypeEnum::SALES
                ? resolve(SummaryMeatSalesPerCenterByYearQuery::class)->execute(year: $year)->toArray()
                : resolve(SummaryMeatPerCenterByYearQuery::class)->execute(year: $year)->toArray(),
            default => $reportTypeEnum === ReportTypeEnum::SALES
                ? resolve(SummaryMilkSalesPerCenterByYearQuery::class)->execute(year: $year)->toArray()
                : resolve(SummaryMilkPerCenterByYearQuery::class)->execute(year: $year)->toArray(),
        };

        // This summary shows both programs, including their national centers.
        $activeCenterCodes = Centers::forActiveProgram(ProgramEnum::IH->value)
            ->merge(Centers::forActiveProgram(ProgramEnum::CBED->value))
            ->map(fn (CenterEnum $c) => $c->code())
            ->unique()
            ->values()
            ->all();

        $summary = array_values(array_filter($summary, function ($row) use ($activeCenterCodes) {
            $centerCode = is_array($row) ? ($row['center_code'] ?? null) : ($row->center_code ?? null);

            return in_array($centerCode, $activeCenterCodes, true);
        }));
        $centerCodes = $activeCenterCodes;

        return inertia('Reports/SummaryBuffaloDerivedProducts', [
            'milkProductionSummary' => $summary,
            'centerCodes' => $centerCodes,
            'selectedCategory' => $category,
            'selectedReportType' => $reportType,
            'selectedReportTypeLabel' => $reportTypeEnum->labels(),
            'selectedYear' => $year,
            'hasYearParam' => $hasYearParam,
            'hasCategoryParam' => $hasCategoryParam,
            'hasReportTypeParam' => $hasReportTypeParam,
            'years' => $years,
            'reportColors' => ReportTypeEnum::reportColors(),
        ]);
    }
}
