```markdown
# [ENT] Establishing Core Entities and Relationships #4

## Description / Background / Objective / Context / Purpose *(REQUIRED)*

The **SUPREME‑CBED‑DB** platform models an organizational hierarchy where an **Agency** oversees multiple **Centers**, each Center manages multiple **Partners**, and each Partner registers multiple **Farmers**. Partners are institutions or groups, while Farmers are individual members. The initial data modelling must focus on **primary source data fields**—those essential for operational use, identification, and core metrics—before adding extended profile data. 

Existing design decisions include a shared polymorphic `Address` model, used by all core entities. This ticket updates the data model to emphasise first‑class fields (operational purpose, identifiers, and metrics) while maintaining the hierarchical relationships and polymorphic address association.

## Requirements / Acceptance Criteria / Definition of Done *(REQUIRED)*

- **Models & Tables**:
  - Four core Eloquent models: `Agency`, `Center`, `Partner`, `Farmer`.
  - Reusable `Address` model with polymorphic fields (`addressable_type`, `addressable_id`).
  - Tables: `agencies`, `centers`, `partners`, `farmers`, `addresses`.

- **First-Class Fields Classification**:  
  For each entity, categorise columns into **Identifiers**, **Operational**, and **Metrics**. Only these primary fields should be included in this ticket; extended or secondary fields will be added in future iterations.

  - **agencies**:
    - Identifiers: `name` (string), `slug` (string, unique), `agency_code` (string, unique, a friendly url).
    - Operational: `description` (text, nullable).
    - Metrics: optional integer columns such as `total_centers`, `total_partners`, `total_farmers` (nullable, to store cached counts; can also be computed on demand).
    - add `address_id` column

  - **centers**:
    - Identifiers: `agency_id` (foreign key), `name` (string), `slug` (string, unique), `center_code` (string, unique).
    - Operational: `description` (text, nullable).
    - Metrics: `total_partners` (integer, nullable), `total_farmers` (integer, nullable).
    - add `address_id` column

  - **partners**:
    - Identifiers: `center_id` (foreign key), `partner_code` (string, unique), `name` (string).
    - Operational: `status` (enum, e.g., active/inactive), `business_type` (string, nullable).
    - Metrics: `assets` (decimal, nullable), `net_income` (decimal, nullable), `total_farmers` (integer, nullable).
    - add `address_id` column

  - **farmers**:
    - Identifiers: `partner_id` (foreign key), `first_name` (string), `last_name` (string).
    - Operational: `sex` (enum), `age` (integer, nullable), `livestock_type` (enum), `farm_latitude`/`farm_longitude` (decimal, nullable).  
    - Metrics: optional numeric fields such as `livestock_count` (integer, nullable) or `annual_production` (decimal, nullable) if known.
    - add `address_id` column

  - **addresses**:
    - Polymorphic keys: `addressable_id` (unsigned big integer), `addressable_type` (string).
    reference: to ticket https://github.com/outsoarph/supreme-cbed-poc/issues/1

- **Relationships**:
  - `Agency` has many `Centers`.
  - `Center` belongs to `Agency` and has many `Partners`.
  - `Partner` belongs to `Center` and has many `Farmers`.
  - `Farmer` belongs to `Partner`.
  - Each of `Agency`, `Center`, `Partner`, and `Farmer` has one `Address` via a polymorphic `morphOne` relationship.
  - `Address` implements `morphTo()`.

- **Migrations & Refactoring**:
  - Create or adjust migrations for the tables above, ensuring that only first‑class fields are included.
  - Remove any `address_id` columns and rely solely on polymorphic addresses.
  - Add unique indexes on `slug` and `partner_code`.
  - Implement foreign key constraints with `onDelete('cascade')`.
  - Provide a seeder for a default `Agency` (PCC) and sample data for Centers, Partners, Farmers, and their addresses.

- **Testing**:
  - Tests verify creation, retrieval, update, and deletion of Agencies, Centers, Partners, and Farmers with their primary fields.
  - Tests ensure that metrics fields update correctly when counts or monetary values change.
  - Tests confirm polymorphic address relationships and cascading deletes.

- **Documentation**:
  - Update model docblocks and README to describe the classification of fields (Identifiers, Operational, Metrics).
  - Explain how to extend each entity with additional secondary fields in future tickets.

## Technical Specifications / Implementation Details / Scope of Work *(REQUIRED)*

- **Framework**: Laravel 10.x with Inertia.js and Vue 3; this ticket covers backend modelling only.
- **Polymorphic Setup**:
  - Use a migration to define the `addresses` table with `addressable_type` and `addressable_id`.
  - On each core model, implement `public function address() { return $this->morphOne(Address::class, 'addressable'); }`.
- **Schema Design**:
  - Use `unsignedBigInteger` for foreign keys.
  - Use appropriate data types for metrics (`integer` for counts, `decimal` for currency).
  - Use enums for categorical fields (e.g., `sex`, `status`, `livestock_type`).
- **Seeding**:
  - Seed the PCC agency and a handful of Centers/Partners/Farmers with realistic primary data fields.
- **Out of Scope**:
  - Secondary/profile fields (e.g., business_info, certifications, household_info, farming_info) will be handled in later tickets.
  - API endpoints, controllers, and UI components are not included here.

## Risk Assessment / Mitigation / Dependencies / Blockers / Assumptions *(OPTIONAL)*

- **Risks**: 
  - Future requirements may necessitate additional fields; focusing only on primary fields might mean schema changes later.
- **Mitigation**:
  - Document clearly which fields are considered first‑class versus secondary to manage expectations.
  - Use migrations to evolve the schema incrementally as new requirements emerge.
- **Dependencies**:
  - Existing `Address` model and migration.
  - Database configured for Laravel.
- **Assumptions**:
  - Primary data fields are limited to operational, identifier, and metric categories as described; extended fields will be added via separate tickets.

## Additional Notes / References / Resources / Links *(OPTIONAL)*

- Laravel documentation on [migrations](https://laravel.com/docs/10.x/migrations) and [polymorphic relationships](https://laravel.com/docs/10.x/eloquent-relationships#one-to-one-polymorphic-relations) provides useful guidance.
- Future modules like Programs & Services will build on the hierarchy defined here.
```
