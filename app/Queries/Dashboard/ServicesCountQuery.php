<?php

namespace App\Queries\Dashboard;

use Illuminate\Support\Facades\DB;

class ServicesCountQuery
{
    /**
     * @param  mixed  $dateRange  int year or [start,end] Y-m-d – from Date::resolveDateRange. Sargable BETWEEN avoids YEAR() on Laragon.
     */
    public function execute(
        ?int $centerId = null,
        mixed $dateRange = null
    ): array {
        $query = DB::table('service_records')
            ->join('services', 'service_records.service_id', '=', 'services.id')
            ->when($centerId, function ($query) use ($centerId) {
                $query->where('service_records.center_id', $centerId);
            })
            ->where('service_records.program', 'cbed')
            ->when($dateRange !== null, function ($query) use ($dateRange) {
                if (is_int($dateRange) || (is_string($dateRange) && ctype_digit($dateRange))) {
                    $year = (int) $dateRange;
                    $start = sprintf('%04d-01-01', $year);
                    $end = sprintf('%04d-12-31', $year);
                    $query->where(function ($q) use ($start, $end) {
                        $q->whereBetween('service_records.date_conducted', [$start, $end])
                            ->orWhere(function ($q2) use ($start, $end) {
                                $q2->whereNull('service_records.date_conducted')->whereBetween('service_records.month_submitted', [$start, $end]);
                            });
                    });
                } elseif (is_array($dateRange) && count($dateRange) === 2) {
                    [$start, $end] = $dateRange;
                    $query->where(function ($q) use ($start, $end) {
                        $q->whereBetween('service_records.date_conducted', [$start, $end])
                            ->orWhere(function ($q2) use ($start, $end) {
                                $q2->whereNull('service_records.date_conducted')->whereBetween('service_records.month_submitted', [$start, $end]);
                            });
                    });
                }
            })
            ->selectRaw('
                SUM(CASE WHEN services.simplified_code = "1.1.1" THEN 1 ELSE 0 END) as production_support_services,
                SUM(CASE WHEN services.simplified_code = "1.2.3" THEN 1 ELSE 0 END) as market_development_services,
                SUM(CASE WHEN services.simplified_code = "1.3.1" THEN 1 ELSE 0 END) as iec_clients,
                SUM(CASE WHEN services.simplified_code = "1.3.5" THEN 1 ELSE 0 END) as clients_trained
            ')
            ->first();

        return [
            'production_support_services' => (int) ($query->production_support_services ?? 0),
            'market_development_services' => (int) ($query->market_development_services ?? 0),
            'iec_clients' => (int) ($query->iec_clients ?? 0),
            'clients_trained' => (int) ($query->clients_trained ?? 0),
        ];
    }
}
