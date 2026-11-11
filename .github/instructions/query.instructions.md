---
applyTo: "app/Queries/**/*.php"
---

# Guide and Instructions for Query Classes

## Purpose 
Query Classes encapsulate database query logic in a reusable and organized manner. They separate query logic from other parts of the application, promoting cleaner code and easier maintenance.

## Don'ts

1. **Avoid Business Logic**: Query classes should strictly handle data retrieval; avoid including business logic or data mutations.
2. **No Direct Output**: Query classes should not handle presentation logic or generate direct output.
3. **Avoid Large Queries**: Break down complex queries into smaller, focused query classes rather than creating monolithic query classes.
4. **Use Spatie Query Builder for Datatables**: For filtering, sorting, and including relationships in datatables, use Laravel Spatie Query Builder package rather than implementing custom query classes.
5. **No Eloquent Models**: Avoid using Eloquent models directly within query classes; prefer using the query builder or raw SQL for better performance and flexibility.

## Guidelines

1. **Namespace**: Place all query classes under the `App\Queries` namespace.
2. **Class Naming**: Use descriptive, PascalCase names that reflect the query's purpose (e.g., `GetTopCenterProducersQuery`).
3. **File Naming**: Name query files in PascalCase matching the class name (e.g., `GetTopCenterProducersQuery.php`).
4. **Base Class**: Extend a base query class if one exists in the project.
5. **Method Structure**: Implement a standard method (e.g., `execute()`) that contains the query logic.
6. **Parameters**: Use constructor injection or method parameters to pass necessary data into the query class.
7. **Return Types**: Clearly define return types with appropriate type hints.
8. **Error Handling**: Implement proper exception handling within query classes.
9. **Documentation**: Include PHPDoc comments for classes and methods.
10. **Testing**: Write unit tests for each query class to ensure correct behavior.

## Use Cases

- Aggregating data for reports or analytics
- Computing summaries or statistics from large datasets
- Top N queries (e.g., top 10 users by activity) or leaderboard functionality
- Complex filtering and sorting operations reused across different parts of the application

## Example

```php
<?php

namespace App\Queries\Reports;

use App\Queries\BaseQuery; // remove/replace if your project has a different base query
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Query to fetch aggregated milk production (liters) for reports.
 *
 * Returns a collection of records with keys: date, total_liters
 */
class MilkProductionQuery extends BaseQuery
{
    protected ?int $agencyId;
    protected ?int $centerId;
    protected ?string $from;
    protected ?string $to;

    public function __construct(?int $agencyId = null, ?int $centerId = null, ?string $from = null, ?string $to = null)
    {
        $this->agencyId = $agencyId;
        $this->centerId = $centerId;
        $this->from = $from ? Carbon::parse($from)->startOfDay()->toDateTimeString() : null;
        $this->to = $to ? Carbon::parse($to)->endOfDay()->toDateTimeString() : null;
    }

    /**
     * Execute the query.
     *
     * @return Collection
     * @throws \Throwable
     */
    public function execute(): Collection
    {
        try {
            $query = DB::table('milk_productions')
                ->selectRaw('DATE(collected_at) as date, SUM(volume_liters) as total_liters')
                ->when($this->agencyId, fn($q) => $q->where('agency_id', $this->agencyId))
                ->when($this->centerId, fn($q) => $q->where('center_id', $this->centerId))
                ->when($this->from, fn($q) => $q->where('collected_at', '>=', $this->from))
                ->when($this->to, fn($q) => $q->where('collected_at', '<=', $this->to))
                ->groupBy('date')
                ->orderBy('date');

            return collect($query->get());
        } catch (\Exception $e) {
            // Let the caller handle/report errors; do not perform presentation here
            throw $e;
        }
    }
}
```

## Usage 

### Using Dependency Injection (Recommended)

Laravel's service container can instantiate and inject query classes:

```php
use App\Queries\Reports\MilkProductionQuery;

// In a controller or service class
public function getMilkProductionReport(MilkProductionQuery $query)
{
    $results = $query->execute();
    // Process results...
}

// Or using the app helper with specific parameters
$milkProductionQuery = app(MilkProductionQuery::class, [
    'agencyId' => 1,
    'centerId' => null,
    'from' => '2023-01-01',
    'to' => '2023-01-31',
]);
$results = $milkProductionQuery->execute();
```

### Direct Instantiation

For simpler cases, you can also instantiate query classes directly:

```php
$query = new MilkProductionQuery(
    agencyId: 1,
    from: '2023-01-01', 
    to: '2023-01-31'
);
$results = $query->execute();
```