<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BeneficiarySourceController;
use App\Http\Controllers\CBINProjectSiteController;
use App\Http\Controllers\CCDPProjectSiteController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\CenterProductionGoalController;
use App\Http\Controllers\CenterSalesGoalController;
use App\Http\Controllers\ConsolidationUnitController;
use App\Http\Controllers\DairyBoxController;
use App\Http\Controllers\DairyBoxSalesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EpahpController;
use App\Http\Controllers\ESETController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileFetcherController;
use App\Http\Controllers\GenerateReportController;
use App\Http\Controllers\IECController;
use App\Http\Controllers\ManureProductionController;
use App\Http\Controllers\ManureSalesController;
use App\Http\Controllers\MarketDevelopmentController;
use App\Http\Controllers\MarketingUnitController;
use App\Http\Controllers\MeatProductionController;
use App\Http\Controllers\MeatSalesController;
use App\Http\Controllers\MilkConsolidationController;
use App\Http\Controllers\MilkFeedingProgramController;
use App\Http\Controllers\MilkProductionController;
use App\Http\Controllers\MilkSaleController;
use App\Http\Controllers\MilkTradedController;
use App\Http\Controllers\MilkUtilizationController;
use App\Http\Controllers\MilkUtilizationMonthlySummaryController;
use App\Http\Controllers\MonthlyTaskController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PivotController;
use App\Http\Controllers\PL480Controller;
use App\Http\Controllers\ProcessingUnitController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionSupportController;
use App\Http\Controllers\ProductionUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilesController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSiteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRecordController;
use App\Http\Controllers\SummaryBuffaloDerivedProductsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Xhr\AddressController;
use App\Http\Controllers\Xhr\CBEDDashbordChartController;
use App\Http\Controllers\Xhr\CBEDDashbordLeaderboardController;
use App\Http\Controllers\Xhr\CBEDDashbordMetricController;
use App\Http\Controllers\Xhr\DistrictController;
use App\Http\Controllers\Xhr\FarmersController;
use App\Http\Controllers\Xhr\FileGeneratePresignedUrl;
use App\Http\Controllers\Xhr\FileUploadController;
use App\Http\Controllers\Xhr\IHDashbordMetricController;
use App\Http\Controllers\Xhr\IHDashbordProductionChartController;
use App\Http\Controllers\Xhr\PartnersController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion. ' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::get('psgc/districts', [DistrictController::class, 'index'])->name('psgc.districts');
Route::middleware('auth')->group(function () {
    Route::get('/{path}/{filename}', FileFetcherController::class)
        ->where('path', '.*')
        ->where('filename', '^[^\/]+?\.[a-zA-Z0-9]+$')
        ->name('file.fetcher');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// User accounts. Authorization is enforced inside the controller and the form
// requests via UserPolicy and the config/roles.php permission matrix, so the
// route stays a plain resource plus the two approval transitions.
Route::middleware('auth')->group(function () {
    Route::patch('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
    Route::patch('users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
    Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::resource('users', UserController::class);
});

Route::middleware('auth')->group(function () {
    Route::resource('centers', CenterController::class);
    Route::post('centers/{center}/partners/export', [CenterController::class, 'exportPartners'])->name('centers.partners.export');
    Route::resource('partners', PartnerController::class);
    Route::post('partners/{partner}/members/export', [PartnerController::class, 'exportMembers'])->name('partners.members.export');
    Route::resource('farmers', FarmerController::class);
    Route::resource('services', ServiceController::class);
    Route::resource('service-records', ServiceRecordController::class);
    Route::resource('production-support', ProductionSupportController::class);
    Route::resource('market-development', MarketDevelopmentController::class);
    Route::resource('manure-productions', ManureProductionController::class);
    Route::resource('manure-sales', ManureSalesController::class);
    Route::resource('meat-productions', MeatProductionController::class);
    Route::resource('meat-sales', MeatSalesController::class);
    Route::resource('iec', IECController::class);
    Route::resource('eset', ESETController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('project-sites', ProjectSiteController::class);
    Route::resource('cbin-project-sites', CBINProjectSiteController::class)->parameters(['cbin-project-sites' => 'project_site']);
    Route::resource('ccdp-project-sites', CCDPProjectSiteController::class)->parameters(['ccdp-project-sites' => 'project_site']);
    Route::resource('products', ProductController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('documents', DocumentController::class);
    Route::post('documents/{document}/submit', [DocumentController::class, 'submit'])->name('documents.submit');
    Route::post('documents/{document}/approve', [DocumentController::class, 'approve'])->name('documents.approve');
    Route::post('documents/{document}/reject', [DocumentController::class, 'reject'])->name('documents.reject');

    Route::get('audits', [AuditController::class, 'index'])
        ->middleware('permission:view-audits')
        ->name('audits.index');
    Route::resource('files', FileController::class);
    Route::get('milk-productions/export/pdf', [MilkProductionController::class, 'exportPdf'])->name('milk-productions.export-pdf');
    Route::post('milk-productions/export/pdf', [MilkProductionController::class, 'queueExportPdf'])
        ->name('milk-productions.export-pdf.queue');
    Route::resource('milk-productions', MilkProductionController::class);
    Route::resource('milk-sales', MilkSaleController::class);
    Route::resource('milk-traders', MilkTradedController::class);
    Route::resource('milk-consolidations', MilkConsolidationController::class);
    Route::resource('milk-feeding-programs', MilkFeedingProgramController::class);
    Route::middleware('permission:view-monthly-tasks')->group(function () {
        Route::get('monthly-tasks/reports/completion-rate', [MonthlyTaskController::class, 'completionRateReport'])
            ->middleware('permission:view-monthly-task-reports')
            ->name('monthly-tasks.reports.completion-rate');

        Route::get('monthly-tasks/reports/totals', [MonthlyTaskController::class, 'totalsReport'])
            ->name('monthly-tasks.reports.totals');

        Route::post('monthly-tasks/generate', [MonthlyTaskController::class, 'generateTasks'])
            ->name('monthly-tasks.generate');

        Route::post('monthly-tasks/remind', [MonthlyTaskController::class, 'remind'])
            ->name('monthly-tasks.remind');

        Route::resource('monthly-tasks', MonthlyTaskController::class)
            ->middleware('permission:generate-monthly-tasks')
            ->only(['create', 'store', 'destroy']);

        Route::resource('monthly-tasks', MonthlyTaskController::class)
            ->only(['index', 'show']);

        Route::middleware('permission:update-monthly-tasks')->group(function () {
            Route::get('monthly-tasks/{monthlyTask}/edit', [MonthlyTaskController::class, 'edit'])
                ->name('monthly-tasks.edit');

            Route::patch('monthly-tasks/{monthlyTask}/status', [MonthlyTaskController::class, 'toggleStatus'])
                ->name('monthly-tasks.status');

            Route::middleware('permission:review-monthly-tasks')->group(function () {
                Route::post('monthly-tasks/{monthlyTask}/approve', [MonthlyTaskController::class, 'approve'])
                    ->name('monthly-tasks.approve');

                Route::post('monthly-tasks/{monthlyTask}/return', [MonthlyTaskController::class, 'returnFigures'])
                    ->name('monthly-tasks.return');
            });

            Route::put('monthly-tasks/{monthlyTask}', [MonthlyTaskController::class, 'update'])
                ->name('monthly-tasks.update');
        });
    });

    Route::resource('center-production-goals', CenterProductionGoalController::class);
    Route::resource('beneficiary-sources', BeneficiarySourceController::class)
        ->except(['index', 'show'])->middleware('permission:enter-data');
    Route::resource('beneficiary-sources', BeneficiarySourceController::class)->only(['index', 'show']);
    Route::resource('center-sales-goals', CenterSalesGoalController::class);
    Route::resource('dairy-boxes', DairyBoxController::class);
    Route::resource('dairy-box-sales', DairyBoxSalesController::class);

    Route::get('pl480', [PL480Controller::class, 'index'])->middleware('permission:view-reports')->name('pl480.index');
    // Export is a read-side operation like every other report export: any account
    // that may export (nationally or its own center) can queue it. Inputs stay
    // behind the PCC-administrator write permission.
    Route::post('pl480/export', [PL480Controller::class, 'export'])->name('pl480.export');
    Route::middleware('permission:manage-pl480')->group(function () {
        Route::post('pl480/inputs', [PL480Controller::class, 'upsertInput'])->name('pl480.inputs.upsert');
        Route::post('pl480/inputs/bulk', [PL480Controller::class, 'bulkUpsertInputs'])->name('pl480.inputs.bulk-upsert');
    });

    Route::prefix('epahp')->name('epahp.')->group(function () {
        Route::get('/', [EpahpController::class, 'index'])
            ->middleware('permission:view-reports')
            ->name('index');
    });

    Route::middleware('permission:manage-epahp')->prefix('epahp')->name('epahp.')->group(function () {
        Route::post('gatasang-kalabaw', [EpahpController::class, 'storeGatasangKalabaw'])->name('gatasang-kalabaw.store');
        Route::put('gatasang-kalabaw/{epahpGatasangKalabaw}', [EpahpController::class, 'updateGatasangKalabaw'])->name('gatasang-kalabaw.update');
        Route::delete('gatasang-kalabaw/{epahpGatasangKalabaw}', [EpahpController::class, 'destroyGatasangKalabaw'])->name('gatasang-kalabaw.destroy');
        Route::post('capacity-building', [EpahpController::class, 'storeCapacityBuilding'])->name('capacity-building.store');
        Route::put('capacity-building/{epahpCapacityBuilding}', [EpahpController::class, 'updateCapacityBuilding'])->name('capacity-building.update');
        Route::delete('capacity-building/{epahpCapacityBuilding}', [EpahpController::class, 'destroyCapacityBuilding'])->name('capacity-building.destroy');
    });
    Route::resource('profiles', ProfilesController::class)->only(['index']);

    Route::resource('centers.production-units', ProductionUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('centers.consolidation-units', ConsolidationUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('centers.processing-units', ProcessingUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('centers.marketing-units', MarketingUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('centers.products', ProductController::class)->except(['destroy']);
    Route::delete('centers/{center}/products/{product}', [ProductController::class, 'destroyFromCenter'])
        ->name('centers.products.destroy');

    Route::resource('partners.production-units', ProductionUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('partners.consolidation-units', ConsolidationUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('partners.processing-units', ProcessingUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('partners.marketing-units', MarketingUnitController::class)->only(['store', 'edit', 'update', 'destroy']);
    Route::resource('partners.products', ProductController::class)->except(['destroy']);
    Route::delete('partners/{partner}/products/{product}', [ProductController::class, 'destroyFromPartner'])
        ->name('partners.products.destroy');

    Route::prefix('reports')->group(function () {
        Route::get('summary-buffalo-derived-products', SummaryBuffaloDerivedProductsController::class)->name('summary-buffalo-derived-products');
        Route::get('generate', [GenerateReportController::class, 'index'])->name('reports.generate');

        // Same `auth`-only posture as reports.generate — no permission middleware
        // added or removed. The center filter is pinned in MilkUtilizationRequest.
        Route::get('milk-utilization', [MilkUtilizationController::class, 'page'])->name('reports.milk-utilization');
        Route::get('milk-utilization/data', MilkUtilizationController::class)->name('reports.milk-utilization.data');
        Route::post('milk-utilization/export', [MilkUtilizationController::class, 'export'])->name('reports.milk-utilization.export');

        // Monthly summary entries for manual input
        Route::get('milk-utilization/monthly-summary', [MilkUtilizationMonthlySummaryController::class, 'index'])
            ->name('reports.milk-utilization.monthly-summary');
        Route::post('milk-utilization/monthly-summary', [MilkUtilizationMonthlySummaryController::class, 'store'])
            ->name('reports.milk-utilization.monthly-summary.store');
        Route::put('milk-utilization/monthly-summary/{id}', [MilkUtilizationMonthlySummaryController::class, 'update'])
            ->name('reports.milk-utilization.monthly-summary.update');
        Route::delete('milk-utilization/monthly-summary/{id}', [MilkUtilizationMonthlySummaryController::class, 'destroy'])
            ->name('reports.milk-utilization.monthly-summary.destroy');

        Route::post('generate', [GenerateReportController::class, 'post'])->name('reports.generate.post');
        Route::get('exports/{document}', [GenerateReportController::class, 'showQueuedExport'])
            ->name('reports.exports.show');
        Route::get('exports/{document}/download', [GenerateReportController::class, 'downloadQueuedExport'])
            ->name('reports.exports.download');
        Route::get('template-download', [GenerateReportController::class, 'downloadTemplate'])
            ->name('reports.template.download');

        Route::get('pivot', [PivotController::class, 'index'])->middleware('permission:export-data')->name('reports.pivot');
        Route::get('pivot/fields', [PivotController::class, 'fields'])->middleware('permission:export-data')->name('reports.pivot.fields');
        Route::post('pivot/build', [PivotController::class, 'build'])->middleware('permission:export-data')->name('reports.pivot.build');
        Route::get('pivot/export', [PivotController::class, 'export'])->middleware('permission:export-data')->name('reports.pivot.export');
        Route::get('pivot/field-values', [PivotController::class, 'fieldValues'])->middleware('permission:export-data')->name('reports.pivot.field-values');
    });
});

Route::group(['prefix' => 'pivot', 'middleware' => ['auth', 'permission:export-data']], function () {
    Route::get('fields', [PivotController::class, 'fields'])->name('pivot.fields');
    Route::post('build', [PivotController::class, 'build'])->name('pivot.build');
    Route::get('export', [PivotController::class, 'export'])->name('pivot.export');
    Route::get('field-values', [PivotController::class, 'fieldValues'])->name('pivot.field-values');
});

Route::group(['prefix' => 'xhr', 'middleware' => ['auth']], function () {
    Route::get('addresses', [AddressController::class, 'index']);
    Route::get('cities', [AddressController::class, 'index'])->name('xhr.cities');
    Route::get('provinces', [AddressController::class, 'index'])->name('xhr.provinces');
    Route::get('psgc/districts', [DistrictController::class, 'index'])->name('xhr.psgc.districts');
    Route::get('cbed-dashboard-metrics', [CBEDDashbordMetricController::class, 'show']);
    Route::get('cbed-dashboard-leaderboard', [CBEDDashbordLeaderboardController::class, 'show']);
    Route::get('cbed-dashboard-chart', [CBEDDashbordChartController::class, 'show']);
    Route::get('ih-dashboard-chart', [IHDashbordProductionChartController::class, 'show']);
    Route::get('ih-dashboard-metrics', [IHDashbordMetricController::class, 'show']);
    Route::post('files-presigned-url', [FileGeneratePresignedUrl::class, 'generatePresignedUploadUrl']);
    Route::put('files-upload', FileUploadController::class);
    Route::get('partners', [PartnersController::class, 'index'])->name('xhr.partners');
    Route::get('farmers', [FarmersController::class, 'index'])->name('xhr.farmers');
    Route::get('documents/{document}/status', [DocumentController::class, 'showQueuedImport'])->name('xhr.documents.status');
    Route::get('documents/{document}/details', [DocumentController::class, 'showDetails'])->name('xhr.documents.details');
});

require __DIR__.'/auth.php';
