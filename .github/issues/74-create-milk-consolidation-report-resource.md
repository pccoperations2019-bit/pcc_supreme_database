 # Create Milk Consolidation Report resource (Model + Migration)

 ## Description

 Implement a new persistent resource to store Milk Consolidation Report records used by the consolidation templates. The resource will capture per-center consolidation metrics for a date period including source references (partner/farmer/supplier), volume summary (in KGs), and financial summary. This enables backend queries, reporting, imports, and seed/demo data generation.

 ## Requirements / Acceptance Criteria

 - Migration created: `milk_consolidation_reports` with fields:
   - `period_start` (date), `period_end` (date)
   - `center_id` (nullable fk), `partner_id` (nullable fk), `farmer_id` (nullable fk), `supplier_name` (nullable string)
   - `total_raw_milk`, `total_from_center`, `total_from_partners`, `total_from_farmers`, `total_from_suppliers` (decimal(14,2))
   - `average_buying_price`, `total_cost`, `average_selling_price`, `revenue_generated`, `processing_cost` (decimal)
   - `notes` (text), `year` (smallInteger), `month` (tinyInteger)
   - Unique index on (`center_id`, `period_start`, `period_end`)

 - Eloquent Model `App\Models\MilkConsolidationReport` with:
   - guarded/fillable configuration
   - relationships: `center()`, `partner()`, `farmer()`
   - casts for date and decimal fields

 - Seeder `MilkConsolidationReportSeeder` with demo data and registered in `DatabaseSeeder`
 - Basic unit test ensuring creation and relationships

 ## Technical Notes

 - Volumes are stored as decimal with 2 decimal places and unit = KGs.
 - `partner_id` will be used for supplier reference when available; `supplier_name` stores textual vendor name otherwise.

 ## Files to be added

 - `database/migrations/2025_11_17_000000_create_milk_consolidation_reports_table.php`
 - `app/Models/MilkConsolidationReport.php`
 - `database/seeders/MilkConsolidationReportSeeder.php`
 - `tests/Unit/MilkConsolidationReportTest.php`

 ## Related

 Consolidation Report template: `.github/issues/74 - Consolidation Report Template.md`
