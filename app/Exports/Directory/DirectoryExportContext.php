<?php

namespace App\Exports\Directory;

use App\Exports\Directory\Support\DirectoryAccreditationResolver;
use App\Exports\Directory\Support\DirectoryEngagementResolver;
use App\Exports\Directory\Support\DirectoryLocationResolver;
use App\Exports\Directory\Support\DirectoryMetadata;
use App\Exports\Directory\Support\DirectoryProductResolver;
use App\Exports\Directory\Support\DirectoryValueChainResolver;

/**
 * Shared context and services for directory export strategies.
 */
final readonly class DirectoryExportContext
{
    public function __construct(
        public DirectoryFilters $filters,
    ) {}

    /**
     * Get metadata accessor.
     */
    public static function metadata(): DirectoryMetadata
    {
        return new DirectoryMetadata;
    }

    /**
     * Get location resolver.
     */
    public static function location(): DirectoryLocationResolver
    {
        return new DirectoryLocationResolver;
    }

    /**
     * Get accreditation resolver.
     */
    public static function accreditation(): DirectoryAccreditationResolver
    {
        return new DirectoryAccreditationResolver;
    }

    /**
     * Get product resolver.
     */
    public static function products(): DirectoryProductResolver
    {
        return new DirectoryProductResolver;
    }

    /**
     * Get engagement resolver.
     */
    public static function engagement(): DirectoryEngagementResolver
    {
        return new DirectoryEngagementResolver;
    }

    /**
     * Get value chain resolver.
     */
    public static function valueChain(): DirectoryValueChainResolver
    {
        return new DirectoryValueChainResolver;
    }
}
