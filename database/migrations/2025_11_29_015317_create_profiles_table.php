<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a unified view for profiles (centers, farmers, and partners)
        DB::statement('
            CREATE OR REPLACE VIEW profiles AS
            -- Centers as profiles
            SELECT
                c.id,
                c.id as center_id,
                "center" as profile_type,
                "ih" as category,
                c.code,
                c.name,
                c.name as display_name,
                COALESCE(pu_stats.production_units_count, 0) as production_units_count,
                COALESCE(cu_stats.consolidation_units_count, 0) as consolidation_units_count,
                COALESCE(pru_stats.processing_units_count, 0) as processing_units_count,
                COALESCE(mu_stats.marketing_units_count, 0) as marketing_units_count,
                c.status,
                c.category_size,
                c.created_at,
                c.updated_at,
                c.is_processing_partner,
                c.is_marketing_partner,
                c.is_dairybox,
                c.is_knpo_partner,
                c.is_fca_partner,
                c.has_cso,
                c.has_fda_lto,
                c.has_nda_lto,
                c.has_philgeps,
                c.has_rsbsa,
                c.has_ffedis,
                (SELECT region FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as region,
                (SELECT province FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as province,
                (SELECT district FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as district,
                (SELECT city FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as municipality,
                (SELECT barangay FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as barangay,
                (SELECT street FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as street,
                (SELECT CONCAT_WS(", ", street, barangay, city, province, region) FROM addresses WHERE addressable_id = c.id AND addressable_type = "center" LIMIT 1) as address
            FROM centers c
            LEFT JOIN (
                SELECT
                    center_id,
                    COUNT(*) as production_units_count
                FROM production_units
                GROUP BY center_id
            ) pu_stats ON pu_stats.center_id = c.id
            LEFT JOIN (
                SELECT
                    center_id,
                    COUNT(*) as consolidation_units_count
                FROM consolidation_units
                GROUP BY center_id
            ) cu_stats ON cu_stats.center_id = c.id
            LEFT JOIN (
                SELECT
                    center_id,
                    COUNT(*) as processing_units_count
                FROM processing_units
                GROUP BY center_id
            ) pru_stats ON pru_stats.center_id = c.id
            LEFT JOIN (
                SELECT
                    center_id,
                    COUNT(*) as marketing_units_count
                FROM marketing_units
                GROUP BY center_id
            ) mu_stats ON mu_stats.center_id = c.id

            UNION ALL

            -- Partners as profiles
            SELECT
                p.id,
                p.center_id as center_id,
                "partner" as profile_type,
                p.category as category,
                p.code,
                p.name,
                p.name as display_name,
                COALESCE(pu_stats.production_units_count, 0) as production_units_count,
                COALESCE(cu_stats.consolidation_units_count, 0) as consolidation_units_count,
                COALESCE(pru_stats.processing_units_count, 0) as processing_units_count,
                COALESCE(mu_stats.marketing_units_count, 0) as marketing_units_count,
                p.status,
                p.category_size,
                p.created_at,
                p.updated_at,
                p.is_processing_partner,
                p.is_marketing_partner,
                p.is_dairybox,
                p.is_knpo_partner,
                p.is_fca_partner,
                p.has_cso,
                p.has_fda_lto,
                p.has_nda_lto,
                p.has_philgeps,
                p.has_rsbsa,
                p.has_ffedis,
                (SELECT region FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as region,
                (SELECT province FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as province,
                (SELECT district FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as district,
                (SELECT city FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as municipality,
                (SELECT barangay FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as barangay,
                (SELECT street FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as street,
                (SELECT CONCAT_WS(", ", street, barangay, city, province, region) FROM addresses WHERE addressable_id = p.id AND addressable_type = "partner" LIMIT 1) as address
            FROM partners p
            LEFT JOIN (
                SELECT
                    partner_id,
                    COUNT(*) as production_units_count
                FROM production_units
                GROUP BY partner_id
            ) pu_stats ON pu_stats.partner_id = p.id
            LEFT JOIN (
                SELECT
                    partner_id,
                    COUNT(*) as consolidation_units_count
                FROM consolidation_units
                GROUP BY partner_id
            ) cu_stats ON cu_stats.partner_id = p.id
            LEFT JOIN (
                SELECT
                    partner_id,
                    COUNT(*) as processing_units_count
                FROM processing_units
                GROUP BY partner_id
            ) pru_stats ON pru_stats.partner_id = p.id
            LEFT JOIN (
                SELECT
                    partner_id,
                    COUNT(*) as marketing_units_count
                FROM marketing_units
                GROUP BY partner_id
            ) mu_stats ON mu_stats.partner_id = p.id

            ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS profiles');
    }
};
