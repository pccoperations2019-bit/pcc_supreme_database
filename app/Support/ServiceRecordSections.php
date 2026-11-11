<?php

namespace App\Support;

/**
 * The `service_records` table is read through several indexes, one per service:
 * each of Production Support, Market Development, IEC and ESET lists the records
 * that point at its own `simplified_code`, and `service-records` lists them all.
 *
 * A record saved from the combined form belongs to exactly one of those
 * sections, so sending the user back to the combined list after a save drops
 * them into a page that is not the one their service is actually managed on.
 * This is the one place that says which index a service belongs to.
 */
class ServiceRecordSections
{
    /**
     * `simplified_code` of the service → the route name of the index that owns it.
     *
     * @var array<string, string>
     */
    public const INDEX_ROUTES = [
        '1.1.1' => 'production-support.index',
        '1.2.3' => 'market-development.index',
        '1.3.1' => 'iec.index',
        '1.3.5' => 'eset.index',
    ];

    /**
     * `simplified_code` of the service → the name of the feature it belongs to.
     *
     * These are the names the four indexes use for themselves, so a record is
     * named the same way wherever it is listed. IEC and ESET are acronyms and
     * are not title-cased.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        '1.1.1' => 'Production Support',
        '1.2.3' => 'Market Development',
        '1.3.1' => 'IEC',
        '1.3.5' => 'ESET',
    ];

    /**
     * The index a record should return to. A record with no service, or one
     * belonging to a service with no index of its own, stays on the combined
     * list - which is the only page that shows it.
     */
    public static function indexRouteFor(?string $simplifiedCode): string
    {
        return self::INDEX_ROUTES[(string) $simplifiedCode] ?? 'service-records.index';
    }

    /**
     * What a record of this service is called - "Production Support", "IEC".
     *
     * Null for a service with no section of its own, which is left unnamed
     * rather than given the combined list's name: only the record itself says
     * which service it recorded.
     */
    public static function labelFor(?string $simplifiedCode): ?string
    {
        return self::LABELS[(string) $simplifiedCode] ?? null;
    }
}
