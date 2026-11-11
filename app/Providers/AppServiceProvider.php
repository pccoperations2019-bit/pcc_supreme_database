<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\Center;
use App\Models\CenterProductionGoal;
use App\Models\CenterSalesGoal;
use App\Models\ConsolidationUnit;
use App\Models\DairyBox;
use App\Models\DairyBoxSale;
use App\Models\Document;
use App\Models\EpahpCapacityBuilding;
use App\Models\EpahpGatasangKalabaw;
use App\Models\Farmer;
use App\Models\File;
use App\Models\Herd;
use App\Models\ManureProduction;
use App\Models\ManureSales;
use App\Models\MarketingUnit;
use App\Models\MeatProduction;
use App\Models\MeatSales;
use App\Models\MilkConsolidation;
use App\Models\MilkCost;
use App\Models\MilkFeedingProgram;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MilkTraded;
use App\Models\MilkUsage;
use App\Models\MilkUtilizationMonthlySummary;
use App\Models\MilkWastage;
use App\Models\MonthlyTask;
use App\Models\Partner;
use App\Models\Pl480;
use App\Models\ProcessingUnit;
use App\Models\Product;
use App\Models\ProductionUnit;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\ProjectSiteInfrastructure;
use App\Models\ProjectSiteMarketingInfrastructure;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Models\Tag;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        if ($this->app->runningUnitTests()
            && ! in_array(config('database.connections.mysql.database'), ['testing', 'test'], true)) {
            throw new RuntimeException(sprintf(
                'The test suite is configured to use the "%s" database. Run `php artisan config:clear` and retry.',
                (string) config('database.connections.mysql.database'),
            ));
        }

        if (app()->environment('production')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');

            Paginator::currentPathResolver(function () {
                return url()->current();
            });
        }

        Vite::prefetch(concurrency: 3);

        Relation::morphMap([
            'agency' => Agency::class,
            'center' => Center::class,
            'partner' => Partner::class,
            'farmer' => Farmer::class,
            'dairybox' => DairyBox::class,
            'milk_feeding_program' => MilkFeedingProgram::class,
            'herd' => Herd::class,
            'file' => File::class,
            'tag' => Tag::class,
            'production_unit' => ProductionUnit::class,
            'consolidation_unit' => ConsolidationUnit::class,
            'processing_unit' => ProcessingUnit::class,
            'marketing_unit' => MarketingUnit::class,
            'document' => Document::class,
            'center_production_goal' => CenterProductionGoal::class,
            'center_sales_goal' => CenterSalesGoal::class,
            'product' => Product::class,
            'project' => Project::class,
            'project_site' => ProjectSite::class,
            'milk_production' => MilkProduction::class,
            'milk_sale' => MilkSale::class,
            'milk_cost' => MilkCost::class,
            'milk_traded' => MilkTraded::class,
            'milk_usage' => MilkUsage::class,
            'milk_wastage' => MilkWastage::class,
            'milk_consolidation' => MilkConsolidation::class,
            'meat_production' => MeatProduction::class,
            'meat_sales' => MeatSales::class,
            'manure_production' => ManureProduction::class,
            'manure_sales' => ManureSales::class,
            'monthly_task' => MonthlyTask::class,
            'service' => Service::class,
            'dairy_box_sale' => DairyBoxSale::class,
            'servicerecord' => ServiceRecord::class,
            'pl480' => Pl480::class,
            'milk_utilization_monthly_summary' => MilkUtilizationMonthlySummary::class,
            'epahp_gatasang_kalabaw' => EpahpGatasangKalabaw::class,
            'epahp_capacity_building' => EpahpCapacityBuilding::class,
            'project_site_infrastructure' => ProjectSiteInfrastructure::class,
            'project_site_marketing_infrastructure' => ProjectSiteMarketingInfrastructure::class,
        ]);

        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $taskSources = [
            MilkProduction::class => ['milk', 'production'],
            MilkSale::class => ['milk', 'sales'],
            MeatProduction::class => ['meat', 'production'],
            MeatSales::class => ['meat', 'sales'],
            ManureProduction::class => ['manure', 'production'],
            ManureSales::class => ['manure', 'sales'],
        ];

        foreach ($taskSources as $modelClass => [$productType, $taskType]) {
            // Saving a production or sales row submits the matching task, rather than
            // completing it. Completing is a reviewer's decision now - see
            // MonthlyTask::markAsSubmitted().
            $modelClass::saved(fn ($record) => MonthlyTask::syncSubmissionFor($record, $productType, $taskType));
            $modelClass::deleted(fn ($record) => MonthlyTask::syncCompletionAfterDelete($record, $productType, $taskType));
        }
    }
}
