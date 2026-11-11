 # Milk Consolidation Report

 ## Clarifying Questions (answered)

 1. Should `Partner`, `Farmer`, `Supplier` be foreign keys to existing models or plain text? — Answer: use existing Models via foreign keys.
 2. Should financial fields be raw inputs or computed? — Answer: computed (formulas should be supported); store computed values and preserve raw inputs where applicable.
 3. Period granularity and presets? — Answer: date-only (Y-m-d) with presets: month / quarter / year.
 4. DPM upload format and access method? — Answer: Excel only, no OAuth; users will upload Excel files via DPM.
 5. UI placement for views? — Answer: Same views accessible in IH Context and `Consolidation -> Milk` (Production -> Milk parity).

 ## Description

 Create a complete feature to support Milk Consolidation reporting via the Document Processing Module (DPM). This includes:

- A spreadsheet template (Excel) with per-center tables and demo data for testing.
- A backend resource (model + migration + factory + seeder) to persist milk consolidation records in `milk_consolidations` table.
- A DPM parser that accepts Excel uploads, maps columns to database fields, supports nullable source fields and computed financial formulas, and creates records.
- Datatable views in the IH Context and `Consolidation > Milk` page with filtering, pagination, sorting, and export capabilities.

 The goal is to allow operators to upload per-center Excel consolidation reports and view aggregated, queryable records in the application.

 ## Requirements / Acceptance Criteria / Definition of Done

 - Upload: Users can upload an Excel file via the DPM UI; the system validates and imports records into `milk_consolidations`.
 - Data model: A `milk_consolidations` table exists with appropriate columns and foreign keys to `centers`, `partners`, `farmers`, and `suppliers` (nullable where specified).
 - Period fields: `period_start` and `period_end` are date-only (`date` type). Views support presets for month / quarter / year and custom date ranges.
 - Computed financials: Support formulas for `average_buying_price`, `total_cost`, `average_selling_price`, `revenue_generated`, and `processing_cost`. DPM shall accept raw inputs and/or compute values when formula inputs are provided; final stored values must match computed results.
 - Demo data: A seeder provides demo records across multiple centers for QA/testing.
 - Views: `Consolidation > Milk` datatable shows list of consolidations with the following columns: `center`, `period_start`, `period_end`, `partner`, `farmer`, `supplier`, `total_raw_milk`, `total_from_center`, `total_from_partners`, `total_from_farmers`, `total_from_suppliers`, `average_buying_price`, `total_cost`, `average_selling_price`, `revenue_generated`, `processing_cost`, `created_at`.
 - Filters: Filters for `center`, `period range`, `partner`, `farmer`, `supplier`, and quick-presets (this month, last month, quarter, year).
 - Authorization: Only users with appropriate permission (e.g., `view-consolidation` / `import-consolidation`) can access views and upload via DPM.
 - Tests: Unit tests for the model and migration; feature tests for DPM import and datatable rendering.

 ## Technical Specifications / Implementation Details / Scope of Work

 1) Data model and migration

 - Create migration for `milk_consolidations` with these fields:
   - `id` (bigIncrements)
   - `center_id` (unsignedBigInteger, indexed, FK -> `centers.id`)
   - `partner_id` (unsignedBigInteger, nullable, FK -> `partners.id`)
   - `farmer_id` (unsignedBigInteger, nullable, FK -> `farmers.id`)
   - `supplier_id` (unsignedBigInteger, nullable, FK -> `suppliers.id`)
   - `period_start` (date)
   - `period_end` (date)
   - `total_raw_milk` (decimal(14,3), default 0)
   - `total_from_center` (decimal(14,3), default 0)
   - `total_from_partners` (decimal(14,3), default 0)
   - `total_from_farmers` (decimal(14,3), default 0)
   - `total_from_suppliers` (decimal(14,3), default 0)
   - `average_buying_price` (decimal(14,4), nullable)
   - `total_cost` (decimal(16,2), nullable)
   - `average_selling_price` (decimal(14,4), nullable)
   - `revenue_generated` (decimal(16,2), nullable)
   - `processing_cost` (decimal(16,2), nullable)
   - `metadata` (json, nullable) — store raw source row, file reference, and computed flags
   - `created_by` (unsignedBigInteger, nullable FK -> `users.id`)
   - timestamps

 - Add foreign key constraints with `onDelete('set null')` for nullable fks and `onDelete('cascade')` or expected behavior for `center_id` per existing conventions.

 2) Model

 - Create `app/Models/MilkConsolidation.php` Eloquent model.
 - Define relationships: `center()`, `partner()`, `farmer()`, `supplier()`, `creator()`.
 - Add casts for decimals and `metadata` JSON.
 - Add accessors for computed fields and helper methods (e.g., `scopeForPeriod`, `scopeForCenter`).

 3) Factory & Seeder

 - Create a factory `database/factories/MilkConsolidationFactory.php` with realistic demo values.
 - Create a seeder `database/seeders/MilkConsolidationSeeder.php` to insert demo data across multiple centers and sources.

 4) DPM Parser (Excel import)
    - Accept file uploads of Excel (`.xlsx`, `.xls`) only.
    - New Template `Institutional Herd - Milk Consolidation Report` with per-center tables and demo rows.

 5) Routes, Controller, and Views

 - Add DPM upload endpoint integration or register a DPM handler so uploaded files can be parsed.
 - Controller action or job to process import in background (queueable). Emit an application event `MilkConsolidationImported` with summary payload.
 - Views:
   - Use existing datatable components used by the project (see `resources/js` and existing IH components).
   - Add a `Consolidation -> Milk` route and menu item if not present.
   - Datatable columns and filters as listed in Acceptance Criteria.
   - Quick presets: month / quarter / year filter buttons.

 6) Authorization and Settings

 - Add or reuse permissions: `import:milk-consolidation`, `view:milk-consolidation`.
 - Add config option `consolidation.import.strict_validation` (boolean) to switch import behavior.

 7) Tests

 - Unit tests for model casts and scopes.
 - Feature tests for Excel import: success path, row-level validation errors, mapping by name vs id.
 - Browser or HTTP tests for datatable rendering and exports.

 8) Documentation / Template

 - Add an Excel template file to repository `docs/templates/milk_consolidation_template.xlsx` with per-center sample tables and demo rows.
 - Document the column mapping, accepted formats, and examples in `.github/issues/74 - Consolidation Report Template.md` and in a short README `docs/milk-consolidation.md`.

 ## Risk Assessment / Mitigation / Dependencies / Blockers / Assumptions

 - Dependency: Existing `centers`, `partners`, `farmers`, and `suppliers` tables and models must be present and populated for name-to-id resolution.
 - Blocker: Excel formula evaluation may require a library that evaluates formulas server-side; if the current Excel reader does not evaluate formulas, we will compute formulas server-side after reading raw inputs.
 - Assumption: Currency is PHP for all financial fields. If multi-currency support is needed, add `currency_code` field.
 - Data quality risk: Uploaded Excel with inconsistent column names — parser must be forgiving (case-insensitive, common synonyms) and provide clear import errors.

 ## Additional Notes / References / Resources / Links

 - Reference consolidations template: `.github/issues/74 - Consolidation Report Template.md` in this repo.
 - Excel template path (to add): `docs/templates/milk_consolidation_template.xlsx`.
 - Suggested implementation files to create/modify:
   - `app/Models/MilkConsolidation.php`
   - `database/migrations/2025_XX_XX_create_milk_consolidations_table.php`
   - `database/factories/MilkConsolidationFactory.php`
   - `database/seeders/MilkConsolidationSeeder.php`
   - `app/Actions/Document/MilkConsolidationImporter.php` or similar DPM handler
   - `app/Http/Controllers/Consolidation/MilkController.php`
   - `resources/js/Pages/Consolidation/MilkIndex.vue` (or equivalent Inertia page)
   - `docs/templates/milk_consolidation_template.xlsx`
