# Tenancy Per Center and Agency Users

## Clarifying Questions (Answered)

1. Agency/Center relationship
  - Decision: Strict 1:N. One agency has many centers; each center belongs to exactly one agency.
  - Current org: One agency, “Philippine Carabao Center (PCC)”, with “Main Center” and multiple “Branch Centers”.
  - Tenancy levels: Agency is top-level; Center is sub-level.

2. User tenancy level
  - Decision: Support agency-level users (access to all centers under their agency) and center-level users (restricted to a single center).

3. Scope selection UX
  - Decision: Center-level users auto-route to their center. Agency-level users see a center dropdown in the dashboard to select working scope.

4. Email uniqueness
  - Decision: Email is globally unique across all tenants.

5. Roles in scope
  - Decision: `agency-admin` (agency/main center) and `center-admin` (branch/regional centers).

6. Guards/providers strategy
  - Decision: Single guard + policies. One `web` guard and one provider (`users` table). Tenancy enforced via authorization policies, middleware, and query scopes using `agency_id`/`center_id`.

7. SSO/IdP
  - Decision: None for now.

8. Laravel Spatie Permission Package
  - Decision: Use Spatie Laravel Permission package to manage roles and permissions for agency-level and center-level users.

## Description

Implement multi-tenancy boundaries for authentication and authorization across Agencies and Centers so that:

This story focuses on backend data model, policies, and middleware to enforce access boundaries and includes a minimal UI hook: a center dropdown for agency-level users (dashboard only). Subdomain routing remains out of scope.

Decisions consolidated from clarifications:

  - Agency-level: associated to an Agency and not bound to a single Center; OR
  - Center-level: associated to a Center (implicitly to that Center’s Agency).

## Requirements / Acceptance Criteria

### Data Model

- A new `agencies` table exists with `id`, `name`, optional `description`, and timestamps.
- `centers` table references `agencies.id` via `centers.agency_id` (FK, indexed).
- `users` table gains nullable `agency_id` and `center_id` FKs (both indexed).
- Roles and permissions are managed via `spatie/laravel-permission` (no `users.role` column).

### Authorization & Scoping

- Center-level users cannot read or modify resources outside their own Center.
- Agency-level users can read/modify resources for all Centers under their Agency but cannot access other Agencies.
- Policies/guards are covered by tests and demonstrated on at least one representative resource (e.g., Center and User lists and detail endpoints/actions).

### Middleware / Context

- A request-scoped “current tenant context” is derived from the authenticated user and made available to services/policies.
- Queries in protected areas are either filtered by policy checks or by explicit query scopes using the current context.
- For agency-level users, the selected center context from the dashboard dropdown is respected and stored in session.

### Tests

- Feature tests prove that:
  - A center-level user is forbidden from accessing another center’s resources.
  - An agency-level user can access multiple centers under the same agency, but not across agencies.
  - Guests are redirected/forbidden as appropriate.
  - Agency-level center selection persists and scopes list/detail views accordingly.

### Non-functional

- DB indexes exist for `centers.agency_id`, `users.center_id`, and `users.agency_id`.
- No N+1 regressions introduced in core listing endpoints covered by tests (use `with()` where sensible).

## Technical Specifications / Implementation Details / Scope of Work

This section outlines concrete steps to implement tenancy and role-based access.

### 1) Data Model & Migrations

- Add `agencies` table (new migration):
  - Columns: `id` (PK), `name` (string, required), `description` (text, nullable), `created_at`, `updated_at`.

- Update `centers` table (file: `database/migrations/2025_10_13_030913_create_centers_table.php`):
  - Add `agency_id` (FK -> `agencies.id`) with `cascadeOnDelete()` and index.

- Update `users` table (file: `database/migrations/0001_01_01_000000_create_users_table.php`):
  - Add `agency_id` (nullable FK -> `agencies.id`, indexed).
  - Add `center_id` (nullable FK -> `centers.id`, indexed).
  - Keep `email` unique globally.

- Indexes required:
  - `centers.agency_id`, `users.center_id`, `users.agency_id`.

- Validation/business rules (app layer):
  - Require at least one of `agency_id` or `center_id` for each user.
  - If both are present, ensure `users.agency_id` matches the agency of `users.center_id`.

### 2) Roles & Permissions (Spatie)

- Install and configure `spatie/laravel-permission` (publish config + migrations).
- Migrations create: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
- Roles: `agency-admin`, `center-admin`.
- Define permissions (e.g., `manage-agency`, `manage-center`, `view-center`, `manage-users`) and assign to roles.

### 3) Models

- `app/Models/User.php`
  - Add relationships: `belongsTo(Center::class)`, `belongsTo(Agency::class)`.
  - Use `Spatie\\Permission\\Traits\\HasRoles` with guard `web`.

- `app/Models/Center.php`
  - Add relationships: `belongsTo(Agency::class)`, `hasMany(User::class)`.

- `app/Models/Agency.php` (new)
  - Relationships: `hasMany(Center::class)`, optionally `hasMany(User::class)`.

### 4) Authorization (Policies) & Query Scoping

- Policies for `Center` and `User` should enforce:
  - Center-level: `authUser.center_id === model.center_id`.
  - Agency-level: `authUser.agency_id === model.agency_id`.
  - Combine with Spatie role checks: `hasRole('agency-admin')`, `hasRole('center-admin')`.
- If a model doesn’t store `agency_id`, derive via relation (e.g., `model.center.agency_id`).
- Provide a reusable scope/service to constrain queries based on current tenant context.

### 5) Tenant Context Resolver

- Create `app/Support/Tenancy/TenantContext.php`:
  - Reads `Auth::user()` and optional selected center from session.
  - Exposes: `isAgencyLevel()`, `agencyId()`, `centerId()`, `selectedCenterId()`.
  - Used by controllers/services/scopes for consistent filtering.

### 6) Middleware

- `EnsureTenancyScope`: bootstraps and shares tenant context per request.
- `SetSelectedCenter`: for agency-level users, validates and stores selected center in session (must belong to their agency).
- Use Spatie role middleware on routes where helpful: `role:agency-admin` or `role:center-admin`.

### 7) Routes / Minimal UI Hook

- Reuse existing auth routes.
- Add POST endpoint to update selected center for agency-level users (dashboard scope):
  - Validates center belongs to the user’s agency; persists in session.
  - Controllers read via Tenant Context.

### 8) Seeds & Factories

- Extend `DatabaseSeeder` to:
  - Create 1 Agency (PCC) with one Main Center and multiple Branch Centers.
  - Optionally create a second Agency for cross-agency negative tests.
  - Seed roles and permissions via Spatie models; clear permission cache.
  - Create agency-level users (assign `agency-admin`) and center-level users (assign `center-admin`) with unique emails.

### 9) Tests (PHPUnit/Pest)

- Verify:
  - Center user forbidden from accessing another center’s resources.
  - Agency user can access across own centers; blocked from other agencies.
  - Guests redirected/forbidden.
  - Agency-level center selection persists and scopes listings.
  - Role middleware/policies allow only appropriate roles.

### 10) Security & Privacy

- Enforce policies in all data-returning endpoints (including Inertia responses).
- Validate selected center against user’s agency on every use/update.
- Avoid logging cross-tenant identifiers and PII unnecessarily.

### 11) Performance & Indexing

- Ensure indexes exist per Data Model.
- Use eager loading (`with('center.agency')`) in lists to avoid N+1 queries.

### 12) Out of Scope (Follow-ups)

- Subdomain routing by center.
- Address schema refactor.
- Finer-grained permissions beyond `agency-admin`/`center-admin`.
