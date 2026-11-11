<?php

namespace App\Http\Controllers;

use App\Actions\Audit\DescribeAuditActivityAction;
use App\Actions\Audit\ListAuditActivityAction;
use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Models\Center;
use App\Support\Centers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /**
     * The audit view.
     *
     * Reads spatie/laravel-activitylog - the same rows the document review
     * timeline shows - so there is one audit history in the system rather than a
     * second one built for this page. Filtering, sorting and pagination all
     * happen server side through Spatie\QueryBuilder, the same way every other
     * listing in the app works.
     *
     * Every role the route admits is read-only here: the page renders rows and
     * nothing else, and no filter can reach past the viewer's scope.
     *
     * The PCC Administrator and the Executive are both agency-wide, so both read
     * the whole log. A center-scoped account reaches the same page but pinned to
     * its own center, so the log cannot be used to read another center's history.
     * Which roles hold 'view-audits' is declared in config/roles.php.
     */
    public function index(
        Request $request,
        ListAuditActivityAction $listActivities,
        DescribeAuditActivityAction $describeActivity,
    ): Response {
        $viewer = $request->user();

        // Null for a PCC-wide viewer (PCC Administrator, Executive), whose scope is
        // the whole agency. A center-scoped account with no center is pinned to
        // nothing rather than to everything.
        $center = $viewer->isCenterScoped() ? Center::find($viewer->center_id) : null;

        $query = $viewer->isCenterScoped()
            ? $listActivities->executeForCenter($center)
            : $listActivities->execute();

        $activities = $query
            ->paginate(10)
            ->appends($request->query())
            // One instance across the whole page so its center lookup is resolved
            // once per request instead of once per row.
            ->through(fn (Activity $activity) => $describeActivity->execute($activity));

        return Inertia::render('Audits/Index', [
            'activities' => $activities,
            'filters' => [
                'categories' => $this->options(AuditCategoryEnum::filterable(), AuditCategoryEnum::labels()),
                'actions' => $this->options(AuditActionEnum::filterable(), AuditActionEnum::labels()),
                'users' => $listActivities->userOptions($center),
                // A center-scoped viewer's only center is already applied, so the
                // filter would be noise.
                'centers' => $center === null ? Centers::getCenterOptions() : [],
            ],
        ]);
    }

    /**
     * Turn an enum's value => label map into the value/label shape the existing
     * select components already expect, in the enum's declared order.
     *
     * @param  list<string>  $values
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private function options(array $values, array $labels): array
    {
        return array_map(
            fn (string $value) => ['value' => $value, 'label' => $labels[$value] ?? $value],
            $values,
        );
    }
}
