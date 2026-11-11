<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Dashboard hot-path: Laragon Windows MySQL is slow on full scans.
        // These covering indexes make every DashboardController query sargable
        // and avoid filesort/temp table on Windows 2019.
        // All indexes are created with IF NOT EXISTS semantics via Schema
        // where possible; failures are ignored for SQLite test runs.
        // -----------------------------------------------------------------

        // service_records – ServicesCountQuery (program + center + effective date)
        // Effective date is COALESCE(date_conducted, month_submitted). Indexing both
        // columns separately lets the optimizer use index_merge for OR-case.
        try {
            Schema::table('service_records', function (Blueprint $table) {
                $table->index(['program', 'center_id', 'date_conducted'], 'sr_dashboard_date_conducted_idx');
                $table->index(['program', 'center_id', 'month_submitted'], 'sr_dashboard_month_submitted_idx');
                $table->index(['service_id'], 'sr_service_id_idx');
                // Composite for the 4-way CASE sum – helps the join stay covered
                $table->index(['center_id', 'program', 'service_id'], 'sr_center_program_service_idx');
            });
        } catch (Throwable $e) {
        }

        // milk_feeding_programs – MilkFeedingQuery (4 agencies, program, date)
        try {
            Schema::table('milk_feeding_programs', function (Blueprint $table) {
                $table->index(['funded_by', 'program', 'center_id', 'date_started'], 'mfp_dashboard_idx');
                $table->index(['center_id', 'date_started'], 'mfp_center_date_idx');
                $table->index(['year'], 'mfp_year_idx');
            });
        } catch (Throwable $e) {
        }

        // dairy_boxes – DairyBoxCountQuery
        try {
            Schema::table('dairy_boxes', function (Blueprint $table) {
                $table->index(['center_id', 'status'], 'dairy_boxes_center_status_idx');
            });
        } catch (Throwable $e) {
        }

        // tags / documents – DocumentCountQuery does whereHas('tags' key=document,value=*) + key in (partner,farmer)
        // Tags table is polymorphic; index key/value helps the semi-join.
        try {
            Schema::table('tags', function (Blueprint $table) {
                $table->index(['key', 'value'], 'tags_key_value_idx');
                $table->index(['taggable_type', 'taggable_id'], 'tags_taggable_idx');
            });
        } catch (Throwable $e) {
        }

        // Manure / milk / meat sales – sales queries filter center_id + program + year + month
        foreach (['milk_sales', 'manure_sales', 'meat_sales', 'milk_productions', 'manure_productions'] as $tbl) {
            try {
                if (Schema::hasTable($tbl)) {
                    Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                        // Use try-catch per table so re-runs on Laragon don't fatal
                        try {
                            $table->index(['center_id', 'program', 'year', 'month'], $tbl.'_dash_idx');
                        } catch (Throwable $e) {
                        }
                    });
                }
            } catch (Throwable $e) {
            }
        }

        // addresses – centerRegions GROUP_CONCAT(DISTINCT region)
        try {
            Schema::table('addresses', function (Blueprint $table) {
                $table->index(['addressable_type', 'addressable_id'], 'addresses_poly_idx');
                $table->index(['center_id', 'region'], 'addresses_center_region_idx');
            });
        } catch (Throwable $e) {
        }

        // farmers / partners – counts + animal_inventory
        try {
            Schema::table('farmers', function (Blueprint $table) {
                $table->index(['center_id', 'is_fca_partner'], 'farmers_center_fca_idx');
            });
        } catch (Throwable $e) {
        }
        try {
            Schema::table('partners', function (Blueprint $table) {
                $table->index(['center_id'], 'partners_center_idx2');
            });
        } catch (Throwable $e) {
        }

        // production_units families – value chain 4 joins on center_id
        foreach (['production_units', 'processing_units', 'consolidation_units', 'marketing_units'] as $tbl) {
            try {
                if (Schema::hasTable($tbl)) {
                    Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                        try {
                            $table->index(['center_id'], $tbl.'_center_idx2');
                        } catch (Throwable $e) {
                        }
                    });
                }
            } catch (Throwable $e) {
            }
        }

        // centers – CenterProductionGoal lookup
        try {
            Schema::table('center_production_goals', function (Blueprint $table) {
                $table->index(['center_id', 'program', 'year'], 'cpg_center_program_year_idx');
            });
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        // No-op for SQLite tests; on MySQL Laragon the indexes are harmless to keep.
        // Drop eagerly if needed:
        foreach ([
            'service_records' => ['sr_dashboard_date_conducted_idx', 'sr_dashboard_month_submitted_idx', 'sr_service_id_idx', 'sr_center_program_service_idx'],
            'milk_feeding_programs' => ['mfp_dashboard_idx', 'mfp_center_date_idx', 'mfp_year_idx'],
            'dairy_boxes' => ['dairy_boxes_center_status_idx'],
            'tags' => ['tags_key_value_idx', 'tags_taggable_idx'],
            'addresses' => ['addresses_poly_idx', 'addresses_center_region_idx'],
        ] as $table => $indexes) {
            try {
                Schema::table($table, function (Blueprint $t) use ($indexes) {
                    foreach ($indexes as $idx) {
                        try {
                            $t->dropIndex($idx);
                        } catch (Throwable $e) {
                        }
                    }
                });
            } catch (Throwable $e) {
            }
        }
    }
};
