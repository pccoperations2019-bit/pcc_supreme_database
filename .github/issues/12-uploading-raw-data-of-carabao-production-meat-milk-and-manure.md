# Uploading Raw Data of Carabao Production: Meat, Milk and Manure

## Clarifying Questions

1. Confirmed: uploads will be allowed for center-level users and agency-level users (agency users may upload for all centers under their agency). Partners/farmers will not have upload permissions.
2. Provided sample CSV/XLSX columns from existing spreadsheet; the ticket will formalize required/optional fields and mapping to current models.
3. Confirmed: prefer mapping to existing models with referential validation rather than storing raw only in staging.

If any of the above statements need changes, update this ticket before implementation.

## Description

This feature implements a batch uploader for raw production data (meat, milk, manure) into the Data Warehousing System Module. Users with center-level or agency-level permissions will upload CSV or XLSX files containing production metrics. Uploaded rows will be validated and mapped to existing models (e.g., `Center`, `Farmer`, `Herd` where applicable). Valid rows are saved while invalid rows are partially imported and an error report (containing the original file with error annotations) is emailed to the uploader. Processing must respect center-level multi-tenancy and run as queued background jobs with notification on completion.

Business value: enables PCC and partners to ingest historical and ongoing production data for reporting, monitoring, and analytics.

## Requirements / Acceptance Criteria / Definition of Done

Functional

- A new UI page in the admin/center panel where authorized users can upload CSV or XLSX files. The page must show an upload form, a configurable mapping preview, and a progress indicator for background processing.
- Only center-level users and agency-level users (for their centers) may upload files. Uploads are restricted by tenant (center/agency) and checked via existing policies.
- The uploader accepts CSV and XLSX files. Supported encodings and date formats should be auto-detected with configurable fallbacks.
- The system performs a dry-run (preview) that validates and reports row counts, errors, and sample mapped rows. Dry-run preview is optional (nice-to-have) but recommended.
- Uploaded files are enqueued as background jobs; processing performs field-level validation, referential checks against `app/Models` (e.g., `Center`, `Farmer`, `Herd`), unit normalization, date normalization, and duplicate detection by (date, center, herd, production_type).
- Valid rows are persisted to the canonical production tables (or identified existing models) with an audit log entry and job trace.
- Invalid rows are not persisted; instead the system generates an error report using the original uploaded file format with an added column describing errors per row. The uploader receives an email with the error report attachment and an import summary.
- Import summary (rows processed, rows accepted, rows rejected, time taken, job id) is stored and available in the UI and in audit logs.
- Duplicate detection: if a row matches an existing record by (date, center, herd, production_type) the system either: (a) skip and report duplicate (default), or (b) update existing record if a "upsert" flag is enabled (configurable). Default should be skip-with-report.
# Uploading Raw Data of Carabao Production: Meat, Milk and Manure

## Clarifying Questions

1. Confirmed: uploads will be allowed for center-level users and agency-level users (agency users may upload for all centers under their agency). Partners/farmers will not have upload permissions.
2. Provided sample CSV/XLSX columns from existing spreadsheet; the ticket will formalize required/optional fields and mapping to current models.
3. Confirmed: prefer mapping to existing models with referential validation rather than storing raw only in staging.

If any of the above statements need changes, update this ticket before implementation.

## Description

This feature implements a batch uploader for raw production data (meat, milk, manure) into the Data Warehousing System Module. Users with center-level or agency-level permissions will upload CSV or XLSX files containing production metrics. Uploaded rows will be validated and mapped to existing models (e.g., `Center`, `Farmer`, `Herd` where applicable). Valid rows are saved while invalid rows are partially imported and an error report (containing the original file with error annotations) is emailed to the uploader. Processing must respect center-level multi-tenancy and run as queued background jobs with notification on completion.

Business value: enables PCC and partners to ingest historical and ongoing production data for reporting, monitoring, and analytics.

## Requirements / Acceptance Criteria / Definition of Done

Functional

- A new UI page in the admin/center panel where authorized users can upload CSV or XLSX files. The page must show an upload form, a configurable mapping preview, and a progress indicator for background processing.
- Only center-level users and agency-level users (for their centers) may upload files. Uploads are restricted by tenant (center/agency) and checked via existing policies.
- The uploader accepts CSV and XLSX files. Supported encodings and date formats should be auto-detected with configurable fallbacks.
- The system performs a dry-run (preview) that validates and reports row counts, errors, and sample mapped rows. Dry-run preview is optional (nice-to-have) but recommended.
- Uploaded files are enqueued as background jobs; processing performs field-level validation, referential checks against `app/Models` (e.g., `Center`, `Farmer`, `Herd`), unit normalization, date normalization, and duplicate detection by (date, center, herd, production_type).
- Valid rows are persisted to the canonical production tables (or identified existing models) with an audit log entry and job trace.
- Invalid rows are not persisted; instead the system generates an error report using the original uploaded file format with an added column describing errors per row. The uploader receives an email with the error report attachment and an import summary.
- Import summary (rows processed, rows accepted, rows rejected, time taken, job id) is stored and available in the UI and in audit logs.
- Duplicate detection: if a row matches an existing record by (date, center, herd, production_type) the system either: (a) skip and report duplicate (default), or (b) update existing record if a "upsert" flag is enabled (configurable). Default should be skip-with-report.

Non-functional

- The job must be queued and processed asynchronously (Laravel queue). For files up to ~100k rows, processing should be batched to avoid timeouts and memory spikes.
- Audit logs must record uploader id, center id, filename, parsed row counts, job id, and timestamps.
- Multi-tenancy: users can only upload and view results for centers they belong to (center-level data isolation enforced).
- Email notifications use existing mail configuration and include a short import summary and link to the job details and error report attachment.

Acceptance Criteria (testable)

1. Center user uploads a valid CSV with 1,000 rows; 980 rows are valid and persisted, 20 are invalid. System enqueues the job, processes asynchronously, persists 980 rows, stores a job record, sends an email to uploader with the error report containing 20 annotated rows, and shows import summary in UI.
2. Agency user uploads a file for a center they manage; the file processes and persists only rows for that center; rows with unmatched center codes are rejected and reported.
2.a. Rows that include `partner_code` and/or `herd_code` must resolve to existing `Partner`/`Herd` records. If these codes are invalid the rows are rejected and included in the error report with clear failure reasons.
3. Duplicate rows (same date+center+herd+type) are skipped by default and reported in the error report.
4. Permission checks prevent a partner/farmer user from accessing the upload page (403).
5. Dry-run preview (if used) shows a count of valid/invalid rows without persisting data.

## Technical Specifications / Implementation Details / Scope of Work

Data model and mapping

- Reference existing models in `app/Models`: `Center.php`, `Farmer.php`, `Partner.php`, `Herd` (if present).

- Instead of a single generic production table, create three product-specific tables and models to simplify downstream reporting and constraints:

  1. `milk_production` -> Model: `app/Models/MilkProduction.php`
    - id (bigint, PK)
    - center_id (unsignedBigInt, FK -> centers)
    - herd_id (nullable, unsignedBigInt, FK -> herds)
    - farmer_id (nullable, unsignedBigInt, FK -> farmers)
    - trans_date (date)
    - year (smallint)
    - month (tinyint)
    - week (tinyint)
    - duration (string)
    - production_code (string)
    - milk_volume_liters (decimal, nullable)
    - partner_type (enum: association, farmer, partner, null) -- category of partner providing the data
    - partner_code (string, nullable) -- coded identifier for the partner/association (must resolve to `Partner` when present)
    - herd_code (string, nullable) -- coded identifier for herd (used to resolve `Herd` when present)
    - milk_volume_from_other_source_liters (decimal, nullable)
    - farm_gate_price (decimal, nullable)
    - fed_to_calves (decimal, nullable)
    - milk_spoiled (decimal, nullable)
    - milking_times (tinyint, nullable)
    - number_of_lactating (smallint, nullable)
    - avg_milk_per_animal_per_day (decimal, nullable)
    - staff_male_count (smallint, nullable)
    - staff_female_count (smallint, nullable)
    - avg_monthly_salary_of_staff (decimal, nullable)
    - milk_feeding (decimal, nullable)
    - milk_traded (decimal, nullable)
    - milk_processed (decimal, nullable)
    - raw_milk_traded (decimal, nullable)
    - raw_milk_traded_sales (decimal, nullable)
    - value_added_products_sales (decimal, nullable)
    - milk_sales (decimal, nullable)
    - source_file (string)
    - batch_id (string)
    - created_by (unsignedBigInt)
    - created_at / updated_at

  2. `meat_production` -> Model: `app/Models/MeatProduction.php`
    - id, center_id, herd_id (nullable), farmer_id (nullable), trans_date, year, month, week, duration, production_code
    - meat_volume_kgs (decimal, nullable)
    - partner_type (enum: association, farmer, partner, null)
    - partner_code (string, nullable)
    - herd_code (string, nullable)
    - farm_gate_price (decimal, nullable)
    - meat_processed (decimal, nullable)
    - meat_traded (decimal, nullable)
    - meat_sales_value (decimal, nullable)
    - other_metrics (json, nullable)
    - source_file, batch_id, created_by, timestamps

  3. `manure_production` -> Model: `app/Models/ManureProduction.php`
    - id, center_id, herd_id (nullable), farmer_id (nullable), trans_date, year, month, week, duration, production_code
    - manure_volume_kgs (decimal, nullable)
    - partner_type (enum: association, farmer, partner, null)
    - partner_code (string, nullable)
    - herd_code (string, nullable)
    - manure_traded (decimal, nullable)
    - manure_processed (decimal, nullable)
    - manure_sales_value (decimal, nullable)
    - other_metrics (json, nullable)
    - source_file, batch_id, created_by, timestamps

- Maintain an `imports` table to store metadata for each upload job: `id, uploader_id, center_id, filename, production_type, rows_total, rows_success, rows_failed, job_id, status, error_report_path, created_at`.

- Rationale: product-specific tables make validation, unit-specific columns, and reporting simpler and avoid sparse columns on a single wide table. They also allow tailored indices for duplicate detection per product.

Import processing

- Use a Laravel form request for file upload validation (mime types, max size).
- Accept XLSX via `maatwebsite/excel` (already present in vendor list) and CSV via native parsing.
- Implement a mapping layer that maps incoming column headers to canonical fields. Provide sensible defaults derived from the provided sample header (e.g., `TRANS DATE` -> `trans_date`, `MILK VOLUME` -> `metric_volume`). The mapping layer must include partner/association and herd code columns mapping (for example `INSTITUTION`, `PRODUCTION CODE`, or a `PARTNER CODE` column). The importer should attempt to resolve `partner_code` to an existing `Partner` record and `herd_code` to an existing `Herd` record, attaching `partner_id`/`herd_id` when found. If codes cannot be resolved they should be flagged for review in the error report.
- Normalization steps:
  - Trim strings, normalize number formats (commas, decimals), parse and normalize dates to ISO (Y-m-d) honoring multiple date formats.
  - Unit normalization: if unit tags are present, convert to canonical units (liters for milk, kgs for manure). If units are ambiguous, log and mark row invalid.
  - Duplicate detection by (trans_date, center_id, herd_id, production_type). Optionally support an `upsert` flag in the upload form.

- Validation rules per row:
  - `center_code` or `center_id` must resolve to an existing `Center` accessible by uploader.
  - `trans_date` required and parseable as a date.
  - `production_type` must be one of [milk, meat, manure].
  - `metric_volume` numeric and >= 0 (when required for the production_type).
  - `partner_code` (when present) must resolve to an existing `Partner` and the resolved partner's type should match `partner_type` if provided. If not resolvable, the row is marked invalid and reported.
  - `herd_code` (when present) must resolve to an existing `Herd` and should belong to the specified `center_id` (or be explicitly allowed in mappings); otherwise mark the row invalid.

Processing and batching

- Break file into chunks (e.g., 500-2,000 rows per batch) and dispatch jobs per chunk to a queue worker. Jobs should report progress and persist batch-level results to the `imports` table.
- Use transactions per chunk so that valid rows in a chunk are persisted while invalid rows are reported and skipped.

Error reporting and notifications

- Generate an error CSV/XLSX with the same columns plus an `__ERROR__` column describing row-level validation failures. Attach this file to the email sent to uploader and store it on disk (private storage path) for 30 days.
- Send an email using existing mail templates; include import summary and a link to job details.

UI

- Add route(s) under `routes/web.php` (or `routes/auth.php`) and controller(s) under `app/Http/Controllers/ImportController.php` for:
  - GET upload form + dry-run preview
  - POST upload (accept file and mapping options)
  - GET import job details and download error report

- Frontend: use existing VILT stack (Inertia + Vue 3). Create a simple upload page component with:
  - file input and mapping preview
  - optional toggles: `dry_run`, `upsert_duplicates`
  - progress bar showing queued status and processed counts (polled from job status endpoint)

Security / Multi-tenancy

- Enforce authorization checks via existing policies (e.g., `CenterPolicy`, `AgencyPolicy`). Ensure `imports` and `production_records` queries are scoped to the user's accessible centers.

Config and environment

- Add `config/imports.php` for tuneable options: chunk_size, max_file_rows, max_file_size, default_duplicate_strategy (skip|upsert), error_report_ttl_days.

Testing

- Add unit tests for row mapping, normalization, and validation.
- Add an integration/feature test that uploads a sample XLSX/CSV and asserts the `imports` row, queued jobs, and email with attachment are created.

## Risk Assessment / Mitigation / Dependencies / Blockers / Assumptions

- Dependencies: assumes `maatwebsite/excel` is available (vendor already present). Relies on Laravel queues and mail configured in environment.
- Assumption: a canonical `Herd` model exists or will be added; if not present this ticket will create a `production_records` table that references `center_id` and optionally `farmer_id`.
- Risk: Large files (100k+ rows) may need additional worker scaling and memory tuning. Mitigation: chunked processing, configurable chunk size, and queue worker scaling.
- Risk: Ambiguous column headers from uploaded files. Mitigation: mapping preview UI and heuristic header matching (case-insensitive, whitespace-insensitive).

## Additional Notes / References / Resources

- Sample incoming header (provided by stakeholder):

```
MARKER, TRANS DATE, YEAR, MONTH, WEEK, DURATION, PRODUCTION CODE, PRODUCTION TYPE, MILK VOLUME, MILK VOLUME FROM OTHER SOURCE, FARM GATE PRICE, FED TO CALVES, MILK SPOILED, MILKING TIMES, NUMBER OF LACTATING, AVG MILK PER ANIMAL PER DAY, NUMBER OF STAFF MALE, NUMBER OF STAFF FEMALE, AVG MONTHLY SALARY OF STAFF, MILK FEEDING, MILK TRADED, MILK PROCESSED, RAW MILK TRADED, RAW MILK TRADED SALES, VALUE ADDED PRODUCTS SALES, MILK SALES
```

- Data elements: the import must capture both volume and value fields for each production type and attribute them to partner categories (association, farmer, other partners) using existing `Partner` coding. The importer should support resolving partner and herd codes and validate membership to ensure accurate attribution.

- Follow-up tickets (recommended):
  1. UI polish and progress streaming (WebSockets) for large imports.
  2. Implement upsert strategy and conflict resolution UI.
  3. Add data lineage and DW ETL pipelines to move canonical production records into analytical schema.
