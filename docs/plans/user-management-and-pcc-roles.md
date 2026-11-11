# Plan — User Management, Roles & PCC Staff Accounts

Status: **All phases implemented** · Revision 5

Decisions closed: visitor dropped (r1), approval via permission not role (r2), program scope via pivot (r2), **Executive holds `export-data` (decision 7, reversed)**.

**Decision 2 resolved as (b)**: `Center Admin` and `Encoder` are **two distinct roles**, not one account under two names. `center-admin` keeps its stored value and label; `encoder`, `reviewer` and `executive` are new. A centre therefore has three accounts, not two:

| Role | Can do |
|---|---|
| Center Admin | manage the center, manage farmers/partners, provision the encoder and reviewer |
| Encoder | enter data, submit documents for review — **no** user management |
| Reviewer | approve/reject documents for the centre — never enters data |

Roster: 12 centres × (1 center admin + 1 encoder + 1 reviewer) = 36 centre accounts.

| Phase | Status |
|---|---|
| 1 — Definitions | **done** |
| 2 — Scoping refactor | **done** |
| 3 — Migrations | **done** (2 files) |
| 4 — Backend user management | **done** |
| 5 — Per-center reviewer | **done** — `DocumentController` now reviews at center level, PCC keeps override |
| 6 — Frontend | **done** — 4 `Users/*` pages, nav entry, `useAuth` helpers, type augmentation |
| 7 — Tests | **done** — 76 in `tests/Feature/Users/`, 13 in `tests/Feature/Documents/CenterReviewerTest` |

**Suite state:** the 19 failures recorded on `main` are now down to **17** — two of the
pre-existing ones (`ProjectSiteTenancyTest`) were fixed as a side effect of scoping the
project-site listings. No new failures; recorded and diffed at every phase.
`pint`, `prettier` and `vite build` are clean.

### Centre-scoped surfaces found after the fact

The first scoping pass converted the three chokepoints but **not** the ~60 remaining
`hasRole(CENTER_ADMIN)` visibility checks, so the Encoder — a centre-scoped role — fell
through every one of them to its agency-wide branch and saw national data. All are now
`User::isCenterScoped()`. Auditing them surfaced three further authorisation holes:

- `StoreCenterRequest::authorize()` returned `true` unconditionally, so **any account could
  create a center** — which also mints its `center-admin`.
- `CenterController::show/edit/update/destroy` never called `authorize()`, leaving
  `CenterPolicy` dead code.
- `CBINProjectSiteController` / `CCDPProjectSiteController` had the same problem for
  `show/edit/update/destroy`, and `ProjectSiteFormDataService` returned every center,
  partner and dairy box in the country in the create form.

`NationalProgrammeAccessTest` covers PL480/EPAHP: readable by any report reader, writable
only by the PCC administrator. PL480 export follows the two-tier export permissions
(national or own-centre), not the write permission.

### Export permissions are two-tier (revision 5)

Enforcing decision 7 with a single `export-data` permission removed the encoder's ability to export *its own* center, which it had before. That was a regression, not the intent, and one permission cannot express both rules: the pivot builder spans every center and must stay PCC-level, while an encoder still needs its own center's reports.

`export-own-center` was therefore added. `User::canExportOwnCenter()` additionally requires the caller to be center-scoped **with** a `center_id`, because holding the permission is not a scope — every endpoint honouring it also pins the record set. The milk-production PDF export previously took `filter[center_id]` straight from the request with no pinning at all, so it is now forced to the caller's center and the queued document records the pinned filters, not the submitted ones.

---

## 0. What changed in revision 2

Reviewing my own revision 1 against the codebase, four things were wrong. All are corrected below.

| # | Revision 1 said | Problem | Revision 2 |
|---|---|---|---|
| 1 | `visitor` role, 1 account | Nothing to visit — every route is behind `auth`. Worse, `DashboardController.php:27-34` fails **open** for anyone who isn't a center-admin, so a visitor would see the whole national dataset | **Dropped** |
| 2 | `super-admin-approval` role for 1 person | A one-member role holding one permission. Separation of duties is a *constraint*, not a role | **Dropped** → `approve-users` permission + self-approval prohibition |
| 3 | 5 program roles (`cbin-staff`, …) carrying the program | `ProjectProgramEnum` already has 8 values and grows. Role-per-program doesn't compose with it, can't hold "CBIN **and** CCDP", and pollutes the role namespace | **Dropped** → `user_programs` pivot. 12 roles → 5 |
| 4 | Invented `create-records` / `review-records` / `submit-records` / `view-reports` permissions, and treated encoder/reviewer as new abstract roles | **Missed that the encoder→reviewer handoff already exists** as a tested state machine in `Documents`. `center-admin` already *is* the encoder today | Encoder/reviewer now plug into the existing workflow; permissions cut 15 → 11 and aligned to existing naming |

Net effect: the roster shrinks, the role count halves, and the largest piece of work turns out to be a **change to an existing tested workflow**, not a new module.

---

## 1. The finding that reframes everything

The requested "encoder submits → reviewer approves" workflow **already exists** in the Documents module.

`DocumentStatusEnum` (`app/Enums/DocumentStatusEnum.php`):

```
DRAFT ──submit──▶ PENDING_REVIEW ──approve──▶ APPROVED ──▶ PROCESSING ──▶ PROCESSED
  ▲                    │
  └────── reject ──────┴──▶ REJECTED ──(uploader re-edits)──▶ DRAFT
```

Supporting infrastructure already in place:

- `documents.reviewed_by` (FK → `users`) and `documents.reviewed_at` — `app/Models/Document.php:38`
- `documents.submitted_at`, with `submitted_by` attributed as the activity-log `causer`
- `Document::isPendingReview()`, `isEditableByUploader()`, `isAwaitingReview()` — `app/Models/Document.php:101-110`
- Activity log records the full review trail with the causer
- Six passing tests in `tests/Feature/Documents/DocumentReviewWorkflowTest.php`

**And the current roles already map onto it:**

| Today | Role | Document behaviour |
|---|---|---|
| Uploads a file | `center-admin` | Goes **straight to `PENDING_REVIEW`** (`test_a_center_admin_upload_goes_straight_to_pending_review`) |
| Submits a draft | `center-admin` | `test_a_center_admin_can_submit_a_draft_for_review` |
| Approves / rejects / retries | `agency-admin` **only** | `test_a_center_admin_cannot_approve_or_reject` asserts 403 |
| Edits own document | `center-admin` | Only while `isEditableByUploader()` — draft or rejected |

So **`center-admin` is already the encoder.** The requested change is not "add an encoder role" — it is *rename/split* `center-admin`, and **relocate the review decision from PCC-level to center-level**.

That relocation is the single largest item in this plan and it is **not a role addition**. It touches:

- `DocumentController::authorizeReviewer()` (`:661`) — currently PCC-admin-only
- `DocumentController::reviewPermissions()` (`:650`) — `can_review` currently `hasRole(AGENCY_ADMIN)`
- `DocumentController::authorizeDocumentAction()` (`:674`) — center-admin branch must not let a reviewer edit
- `DocumentStatusEnum::awaitingReview()` docblock — says "while it waits for a **PCC admin** decision"
- `Document::isPendingReview()` docblock — same assumption
- The six tests above

---

## 2. Proposed roster

| Scope | Account | Count |
|---|---|---|
| Per Center | Encoder | 12 |
| Per Center | Reviewer | 12 |
| PCC-wide | PCC Administrator | 1 |
| PCC-wide | Executive | 1 |
| PCC-wide | CBIN program access | 1 |
| PCC-wide | CCDP program access | 1 |
| PCC-wide | MFP program access | 1 |
| PCC-wide | ESETS program access | 1 |
| PCC-wide | IDAS program access | 1 |
| | **Total** | **31** |

Program access is **data on the user**, not a role — so those last five rows cost zero new roles.

### Decisions needed before Phase 1

1. **Which 12 centers?** `ProductionCenterUserSeeder` defines 16 (`MMSU, DMMMSU, CSU, CLSU, UPLB, WVSU, LCSF, USF, VSU, MLPC, CMU, USM, MSU, GP, NIZ, NHQ`). Which four are excluded?
2. **Does `center-admin` survive?** The request lists only encoder + reviewer per center. Options:
   - **(a) `center-admin` → renamed `encoder`.** 2 users per center. Keeps all current behavior. *Recommended — smallest change.*
   - (b) Keep `center-admin` **and** add encoder + reviewer → 3 per center, 36 center users.
   - (c) Keep `center-admin`, encoder replaces only the data-entry half — requires splitting `manage-center`, i.e. the permission work in §4 anyway.
3. **Who holds `approve-users`?** The PCC Administrator creates users, so cannot approve their own creations. Either a **second PCC Administrator**, or delegate to the Executive, or relax the prohibition. *A second PCC Administrator is recommended — no new role, just a second account.*
4. **"1 for CBIN and CCDP"** — one account with both programs, or one each? `user_programs` supports either. Roster is 31 or 30.
5. **What is IDAS?** Zero occurrences anywhere in the codebase. ESET exists only as a service-record module (`eset.index`, filtered by `service.simplified_code = '1.3.5'`), **not** a program — so `esets` is also not in `ProjectProgramEnum`. Both need defining, or they ship as an unusable account with a program value nothing reads.
6. **Do center admins create their own encoder/reviewer?** Convenient, but it means the center provisions the account that reviews its own submissions. Recommend **no** — PCC Administrator provisions all 24; the approval gate stays meaningful.
7. ~~**May the Executive export data?**~~ — **Originally: no.** ~~Executive is in-app read-only (`view-center`, `view-farmers`, `view-reports`); all raw file exports go through the PCC Administrator.~~ **Reversed: yes.** The Executive now holds `export-data`, because withholding it made the Generate Report page a dead end behind a 403 for an account that is meant to work in-app. The role stays read-only for *data entry and account provisioning* — it holds neither `enter-data` nor any user-management permission — so `export-data` here means "may pull data out", not "may alter it". The nationwide-exposure concern behind the original decision is real and is now accepted rather than mitigated.

---

## 3. Roles

Five roles. Two new.

| Stored value | Label | Level | Status |
|---|---|---|---|
| `agency-admin` | **PCC Administrator** | PCC | existing, relabelled only |
| `executive` | Executive | PCC | **new** — read-only for entry and accounts, may export |
| `center-admin` | Center Admin | Center | existing — manages the centre and its accounts |
| `encoder` | Encoder | Center | **new** — data entry and document submission only |
| `reviewer` | Reviewer | Center | **new** |

`agency-admin` keeps its stored value — pure label change, no migration, existing logins unaffected.

### 3.1 Scoping model

Scope is **data**, never role:

| Column | Meaning |
|---|---|
| `users.agency_id` | null ⇒ PCC-wide |
| `users.center_id` | set ⇒ pinned to one center |
| `user_programs` pivot | which `ProjectProgramEnum` values this user may access; empty ⇒ all |

This replaces revision 1's "role encodes the program", which could not represent one user with two programs and would need a new role per future program.

### 3.2 Three chokepoints that must change

Every one hardcodes `center-admin`. Any new center-level role not threaded through them sees all data or none.

| File | Current | Required |
|---|---|---|
| `app/Models/Concerns/ScopedToCenter.php:16` | filters to `center_id` only when `hasRole(CENTER_ADMIN)` | filter when `$user->center_id !== null` and role is center-scoped |
| `app/Support/Tenancy/TenantContext.php:38` | `isCenterLevel()` true only for `center-admin` | delegate to `UserRoleEnum::isCenterScoped()` |
| `app/Policies/Concerns/HasScopeAccess.php:26` | agency-admin + agency, else center-admin + center | agency-level + agency, else center-scoped + `center_id` |

Plus `app/Http/Requests/Concerns/ForcesCenterForCenterAdmin.php` and `DocumentController::isCenterAdmin()`.

### 3.3 Blast radius — measured, not estimated

| Location | `hasRole` / role checks |
|---|---|
| `app/` | **80** — 68 `CENTER_ADMIN`, 8 `AGENCY_ADMIN`, **8 raw `'center-admin'` string literals** that bypass the enum |
| `resources/js` | **103** `hasRole` / `isAgencyAdmin` / `isCenterAdmin` / `isAdmin` |

**76 of the 80 backend checks are `center-admin`.** Every one of them silently changes behaviour for `reviewer`. Sites matching the pattern *"not a center admin ⇒ agency-wide"* are the dangerous ones — they become data leaks.

The 8 raw string literals must be swept to the enum in the same pass.

**A second, distinct shape: deny-lists.** Four sites invert the logic — `abort_if($request->user()?->hasRole('center-admin'), 403)` in `PivotController.php:28,46,78,97`. These *block* center-admins and allow everyone else, so they grant rather than restrict. Every new non-center-admin role inherits full pivot access through them. See §4.1.

### 3.4 Two latent bugs that block the UI

- `HandleInertiaRequests::share()` passes `'user' => $request->user()` with **no `with('roles')`**. `useAuth.ts` reads `user.roles`, so **every frontend `hasRole()` currently resolves `false`.** Fix before any new role is used in the UI.
- The same middleware shares no `permissions` map, but `MainNav.vue:34` already reads `page.props.permissions` — always empty today. Add it in the same change.

---

## 4. Permissions

Revision 1 invented 15 record-level permissions (`create-records`, `update-records`, `delete-records`, …). Wrong shape: the app is organised by entity (`manage-farmers`, `view-center`, `manage-center`), and six generic record permissions do not map onto ~40 entity controllers.

Eleven new, each mapping to real behaviour. Existing seven kept verbatim.

| Permission | Purpose |
|---|---|
| `view-users` | See the user list |
| `create-users` | Create accounts |
| `update-users` | Edit accounts |
| `deactivate-users` | Deactivate (never hard-delete) |
| `approve-users` | Approve or reject pending accounts |
| `assign-roles` | Change a user's role |
| `enter-data` | Create/edit production, farmer, partner records |
| `submit-documents` | Submit a document for review |
| `review-documents` | Approve / reject / retry a document |
| `view-reports` | Dashboards, reports, pivots |
| `export-data` | Export directories / pivots |

### Matrix (`●` = granted)

| Permission | PCC Admin | Executive | Center Admin / Encoder | Reviewer |
|---|:--:|:--:|:--:|:--:|
| `manage-agency` | ● | | | |
| `manage-center` | ● | | ● | |
| `view-center` | ● | ● | ● | ● |
| `import-data` | ● | | | |
| `view-farmers` | ● | ● | ● | ● |
| `manage-farmers` | ● | | | |
| `view-users` | ● | | ● | |
| `create-users` | ● | | ● | |
| `update-users` | ● | | ● | |
| `deactivate-users` | ● | | ● | |
| `approve-users` | ●¹ | | | |
| `assign-roles` | ● | | | |
| `enter-data` | ● | | ● | |
| `submit-documents` | ● | | ● | |
| `review-documents` | ● | | | ● |
| `view-reports` | ● | ● | ● | ● |
| `export-data` | ●² | ●² | | |

¹ With a **self-approval prohibition** — see decision 3.
² The Executive holds `export-data` so the Generate Report page works (decision 7, reversed). It remains read-only for entry and provisioning: no `enter-data`, no user management.

**Constraints enforced outside the matrix** (these are the actual rules, and they are why the matrix alone is not the whole story):

- Reviewer may **not** `enter-data`, `submit-documents`, or edit any document — `authorizeDocumentAction()` currently exempts non-center-admins entirely, which would let a reviewer edit anything. That exemption must become an explicit deny.
- Reviewer sees only their own center's documents.
- A center-level actor's `center_id` is force-pinned server-side; a PCC-level role must have `center_id = null`.
- Program access intersects with scope: a CBIN user at PCC level sees CBIN data across centers; at center level, CBIN data for their own center only.

### 4.1 Executive is not enforceable today

Executive and PCC Administrator are **the same scope** — both agency-wide, both non-center-admin, so both take the identical branch in `ScopedToCenter`, `DashboardController:27-34`, and `HasScopeAccess`. Executive differs *only* by capability.

That distinction is thinner than the matrix implies, because the export endpoints do not check permissions at all:

| Endpoint | Current gate |
|---|---|
| `pivot.fields` / `pivot.build` / `pivot.export` / `reports.pivot.export` | `abort_if($request->user()?->hasRole('center-admin'), 403)` — `PivotController.php:28,46,78,97`. A **deny-list**: asks "are you a center-admin?" and allows everyone else |
| `centers/{center}/partners/export` | `CenterController::exportPartners()` — no role gate in the method |
| `partners/{partner}/members/export` | `PartnerController::exportMembers()` — no role gate in the method |
| `milk-productions/export/pdf` | `MilkProductionController::exportPdf()` — no role gate |
| `reports/generate` | `GenerateReportController.php:51,98` — branches on `hasRole('center-admin')` |

So today an Executive and a PCC Administrator are **indistinguishable** to every export path. Withholding `export-data` changes nothing on its own; it only takes effect once these endpoints gate on capability. That is Phase 2 work, and without it the Executive role is a paper distinction.

*Outcome:* Phase 2 landed — these endpoints now gate on `canExportData()` / `canExportOwnCenter()` rather than on role names, so withholding `export-data` did make the Executive distinguishable. Decision 7 was then reversed anyway, because the withheld permission turned the Generate Report page into a 403 for an account meant to be usable.

---

## 5. Account approval

New accounts are created `pending` and cannot log in until approved.

```
PCC Administrator creates user
        │
        ▼
    status = pending ──── reject ────▶ rejected ──▶ creator edits & resubmits
        │
        ▼
 approve-users holder approves   ◄── must not be the creator
        │
        ▼
 status = active · approved_by · approved_at
        │
        ▼
    may log in
```

- `users.status`: `pending` | `active` | `rejected` | `deactivated` → new `app/Enums/UserStatusEnum.php` (+ JS mirror).
- `users.approved_by` (nullable FK → `users`, `nullOnDelete`), `users.approved_at`.
- **Backfill** existing users to `active`, `approved_at = created_at`, so nobody is locked out.
- Login gate in `app/Http/Requests/Auth/LoginRequest.php`: reject non-`active` before the throttled attempt is counted.
- Approve / reject / deactivate audited via the existing `LogsAuditTrail` trait (which already forces a row for password-only changes).

---

## 6. Phases

### Phase 0 — Answer §2 (six decisions)

### Phase 1 — Definitions
- Extend `config/roles.php` with the 2–3 new roles and 11 new permissions.
- Extend `app/Enums/UserRoleEnum.php`: `labels()`, `label()`, plus `isCenterScoped()`, `isPccLevel()`, `isProgramScoped()`.
- Mirror in `resources/js/enums/UserRoleEnum.js`.
- New `app/Enums/UserStatusEnum.php` + JS mirror.
- Run `RolesPermissionsSeeder` — already idempotent (`firstOrCreate` + `syncPermissions`), creates **roles only**, touches no users.
- Sweep the 8 raw `'center-admin'` literals in `app/` to the enum.

### Phase 2 — Scoping refactor ⚠️ before any new role exists
- Rewrite the three chokepoints in §3.2 against `UserRoleEnum::isCenterScoped()`.
- Rename `ForcesCenterForCenterAdmin` → reflect that it now covers every center-scoped role.
- Audit all 80 `hasRole()` sites; flip the fail-open ones to deny-by-default.
- **Invert the four `PivotController` deny-lists** (`:28,46,78,97`) to allow-lists on capability.
- Gate the ungated export methods: `CenterController::exportPartners()`, `PartnerController::exportMembers()`, `MilkProductionController::exportPdf()`.
- Add a regression test pinning which roles reach which export route. Originally asserted an Executive gets 403 everywhere; decision 7 was later reversed, so the test now pins the Executive's export reach and its continued lack of entry/provisioning rights.
- **Acceptance: `agency-admin` and `center-admin` behave byte-identically.** Existing `CenterAdmin/`, `Dashboard/`, `Audits/`, `ProjectSiteTenancy` suites must pass untouched.

### Phase 3 — Migrations
1. `add_status_to_users_table` — `status`, default `active`, indexed.
2. `add_approval_columns_to_users_table` — `approved_by`, `approved_at`.
3. `create_user_programs_table` — `user_id`, `program`, `project_program_id`, unique pair.

*Could merge 1–2 into one migration; kept separate only for reviewability.*

### Phase 4 — Backend user management
- `UserController` — `index` (QueryBuilder, matching house pattern), `create`, `store`, `show`, `edit`, `update`, `destroy` (deactivate only), `approve`, `reject`.
- `StoreUserRequest` / `UpdateUserRequest`:
  - `email` unique; `username` `alpha_dash` unique (mirrors `StoreCenterRequest`)
  - `role` must exist in `UserRoleEnum`; **`programs[]` validated against `ProjectProgramEnum` and never trusted raw**
  - cross-field: PCC-level ⇒ `center_id` null; center-level ⇒ `center_id` required and inside the actor's agency
  - `password` `min:8` + `confirmed`, **omitted on update** (blank ⇒ unchanged) — do not copy `CenterController`'s always-required pattern
- `UserProvisioningService` — the single place that creates a user, assigns role, scope, programs, status, and writes the audit row. **Refactor `CenterController::createCenterUser()` (`:235-247`) to call it**, so center-created admins get the same approval treatment instead of bypassing it. Three call sites justify the service.
- Activate `UserPolicy` — add `approve` / `reject` / `deactivate`; use the enum helpers; fix the null-center read on `HasScopeAccess` for users with no `center`.
- Routes: `Route::resource('users', ...)` + `PATCH users/{user}/approve|reject`, inside the existing `auth` group, gated by `permission:` middleware to match `audits.index`.
- **Fix `HandleInertiaRequests`**: `->load('roles')` + share a `permissions` map. Same change as §3.4.
- Standardise password hashing — the codebase currently mixes three styles. New code assigns plaintext and relies on the `'hashed'` cast (as `CenterController` does); note it in the PR.

### Phase 5 — Per-center reviewer (⚠️ architectural change)
Only if decision 2 or the request genuinely wants review at center level.
- `authorizeReviewer()` — accept the center-scoped reviewer for their own center's documents; PCC Administrator retains override.
- `reviewPermissions()` — `can_review` per role **and** per-document ownership, not a flat role flag.
- `authorizeDocumentAction()` — replace the `if (! $this->isCenterAdmin()) return;` exemption with an explicit reviewer deny.
- Update the `awaitingReview()` / `isPendingReview()` docblocks, which both hardcode "PCC admin".
- Update the six existing tests; add coverage for cross-center review attempts.
- **Alternative if this is not wanted:** keep review at PCC level, make the 12 per-center "reviewers" data verifiers who cannot approve. Cheaper, but then they are not reviewers.

### Phase 6 — Frontend
- `Users/Index.vue` + `Partials/UserTable.vue` — role badge, center, programs, status chip; filters by role / center / program / status.
- `Users/Create.vue`, `Edit.vue`, `Show.vue` + `Partials/UserForm.vue` — role select drives conditional center/program fields client-side; server re-validates.
- `Users/Partials/ApprovalDialog.vue` — approve / reject with reason.
- `useAuth.ts` — add `isEncoder`, `isReviewer`, `isExecutive`, `isProgramStaff`, `hasPermission()`.
- `resources/js/lib/navLinks.js` — "Users" entry gated by `permission: 'view-users'`.
  - **Placement:** a **sibling** of `Profiles`, not a child. `Profiles/Index` (index-only route) holds farmer/partner profiles — a different entity from user accounts. Nesting them will confuse both the UI and the route names.
- Empty / permission-denied states for roles that see the nav but not every row.

### Phase 7 — Tests
New `tests/Feature/Users/`:

| Test | Asserts |
|---|---|
| `StoreUserTest` | Per-role creation, scope + programs assigned, pending status, audit row, duplicate email/username rejected |
| `StoreUserScopeTest` | Center-level creator pinned to own center; cross-agency `center_id` rejected; PCC role cannot carry a center |
| `UserProgramScopeTest` | CBIN user reaches CBIN module, not CCDP/MFP; multi-program user reaches both |
| `UserApprovalTest` | Pending cannot log in; approve activates; reject blocks; creator cannot self-approve |
| `UserRoleScopingTest` | Reviewer sees only own center across index / dashboard / documents / exports |
| `ExecutiveReadOnlyTest` | Executive reads dashboards/reports/pivots and **may export** (`export-data`); still **no write endpoints** — no `enter-data`, no user provisioning |
| `UpdateUserRoleTest` | `assign-roles` required; cannot promote to a role you don't hold |
| `DeactivateUserTest` | Deactivated blocked at login; records retained |

Extend `tests/Feature/Centers/StoreCenterTest.php` — it asserts the created user gets `hasRole('center-admin')`, which changes if center creation routes through `UserProvisioningService`.

Keep using `Role::findOrCreate()` in `setUp` rather than adding user seeders. `phpunit.xml` already pins `DB_DATABASE=testing` / `BCRYPT_ROUNDS=4`, and `AppServiceProvider::boot` throws if pointed elsewhere.

### Phase 8 — Rollout
1. Phase 1–3 — definitions, migrations, backfill. No behaviour change.
2. Phase 2 must land and pass **before** Phase 4.
3. Phase 4 + 6 behind `permission:manage-users`, held only by the PCC Administrator.
4. Create the PCC-level accounts in the UI.
5. Per center: provision encoder + reviewer → approver activates → onboard.

---

## 7. Risks

| Risk | Mitigation |
|---|---|
| Un-updated `hasRole()` sites leak data to `reviewer` | Phase 2 audit of all 80 sites + per-role scoping tests. Fail-open sites flipped to deny-by-default |
| `PivotController` deny-lists *grant* pivot/export to every new role | Phase 2 inverts `:28,46,78,97` to allow-lists on `export-data`; `ExecutiveReadOnlyTest` pins each role's reach |
| `exportPartners()` / `exportMembers()` / `exportPdf()` have no role gate at all | Phase 2 gates them on `canExportSomething()`; covered by `ExecutiveReadOnlyTest` |
| Executive now reaches every export route (decision 7 reversed) | Accepted. Compensating control: the role holds no `enter-data` and no user-management permission, so it can read and export but never change a record or provision an account |
| Frontend `hasRole()` silently `false` today | Fix `HandleInertiaRequests` in Phase 4, before any new role reaches the UI |
| Reviewer gets document-edit rights via the `isCenterAdmin()` exemption | Phase 5 replaces the exemption with an explicit deny |
| Moving review to center level weakens document integrity | Requires a second pair of eyes only within one center. If unacceptable, take the Phase 5 alternative — data verifiers, no approval rights |
| Program scope spreading through ~40 controllers | Reuse the existing `center_project` / `center_product` pivot-scoping helpers |
| 7 existing permissions are declared but **never enforced** — the matrix is decorative | Decide explicitly: either start enforcing via `permission:` middleware, or document it as documentation-only. Do not leave it ambiguous |
| Relabelling `agency-admin` breaks hardcoded UI strings | Single enum source; grep literals in `resources/js` and tests |
| ESETS / IDAS have no program value to bind to | Blocked on decision 5 — shipping them now yields accounts that can see nothing |
| Executive is a paper role until Phase 2 lands | Accepted, and tracked. If Phase 2 slips, the Executive account must not be provisioned — it would carry the PCC Administrator's export rights. Sequence rollout accordingly |

---

## 8. Behaviour changes introduced (review these)

These are the places where the implementation deliberately differs from today's
behaviour. Each is either required by a decision above or a deny-by-default fix;
none is incidental, but all are worth a second opinion.

| # | Change | Why |
|---|---|---|
| 1 | **Bulk export is two-tier.** `pivot.*` and `reports/template-download` require `export-data` (PCC-level). `reports/generate`, `reports/exports/*`, `centers.*.partners/export`, `partners.*.members/export` and `milk-productions/export/pdf` accept `export-own-center` as well, pinned to the caller's own center. | Decision 7, without the regression — see "Export permissions are two-tier" above. The pivot builder is unchanged from before: it was always closed to encoders. |
| 2 | **New accounts start `pending` and cannot sign in.** Including the admin created by the center-creation form. | §5. Consequence: creating a center in the UI yields an account that needs approving before anyone can use it. |
| 3 | **A center-scoped role with no `center_id` sees nothing** (`whereRaw('1 = 0')`) and cannot create center-scoped records (403). | Previously it saw everything. Deny-by-default. |
| 4 | **`create_center` is gated on `manage-center`** rather than "is not a center admin". | The old check granted it to every non-encoder, so the reviewer would have inherited it. |
| 4b | **Document upload/edit require `submit-documents`; delete requires the PCC administrator; a reviewer is refused both.** `StoreDocumentRequest::authorize()` and `UpdateDocumentRequest::authorize()` previously returned `true` unconditionally, and `DocumentController::destroy()` had no gate at all. | Deny-by-default. Without these a reviewer would have inherited the encoder's upload and edit powers, and any signed-in account could delete a document by id. |
| 4c | **Document review accepts the document's own center reviewer**, with the PCC administrator keeping the nationwide override. | Phase 5. This is the change that weakens the two-eyes control: a center now approves its own documents. |
| 5 | **Report center filter follows every center-scoped role**, not just the encoder. | The reviewer now gets their own center's report options rather than all of them. |
| 6 | **Editing a user's centre, role, or password requires `update-users`; changing the *role* additionally requires `assign-roles`.** A blank password leaves credentials untouched. | §4. Previously none of this existed. |
| 7 | **A user cannot edit their own account through `/users/{id}`.** Self-service stays on `/profile`. | Keeps the approval trail meaningful and avoids two competing editors. |
| 8 | **`auth.user.roles` is now eager-loaded and `props.permissions` is shared.** | Fixes two live bugs — every frontend `hasRole()` was returning false and every permission-gated nav link was hidden. |

## 9. Still to decide

None of these block the code; all affect the data you load.

1. **Which 12 of the 16 centers** receive accounts, and whether it is one encoder
   and one reviewer each.
2. **Who holds `approve-users` in practice.** The code refuses self-approval, so
   this needs a second PCC administrator or a delegate. Nothing enforces that
   such an account exists — it is an operational requirement, and until it is
   met nobody can activate a new account.
3. **Phase 5's integrity trade-off is now live.** A center approves its own
   documents. The plan's alternative — reviewers as data verifiers with no
   approval rights, and approval staying at PCC level — is a one-line revert of
   `DocumentController::canReview()` if that is preferred.
4. **ESETS and IDAS still have no `ProjectProgramEnum` value**, so those two
   accounts cannot be given a program yet. `UserProgramScopeTest` asserts against
   the enum rather than a hardcoded list, so adding the values is the only step.
5. **Decision 6 was contradicted by the plan's own matrix.** The matrix grants the
   encoder `create-users`/`update-users`; the decision-6 note recommends the PCC
   administrator provision all 24 accounts. The matrix was followed, because it is
   the concrete artifact and its scoping constraints are tested. Confirm which was
   intended.
