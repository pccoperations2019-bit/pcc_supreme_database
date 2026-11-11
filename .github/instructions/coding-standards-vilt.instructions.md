```markdown
# Coding Standards

These standards apply to all repositories in this organization. They are optimized for the **VILT** stack (Vue 3, Inertia.js, Laravel, Tailwind CSS) with **MySQL**.

---

## Tech Stacks

- **Web Framework:** `VILT` — Vue 3, Inertia.js, Laravel, Tailwind CSS
- **Database:** MySQL (InnoDB, utf8mb4)
- **Languages/Runtime:** PHP ≥ 8.2, Node.js ≥ 20 LTS
- **Package Managers:** Composer, npm (or pnpm)
- **Testing:** PHPUnit/Pest (PHP), Vitest (Vue), Playwright (E2E)

> If the project requires different versions, specify them in `.php-version`, `.nvmrc`, and `composer.json`/`package.json` and update this doc in the repo root.

---

## General Guidelines

1. **Consistency**  
   Keep naming, structure, and formatting consistent across the codebase. Prefer project-wide patterns over personal style.
2. **Readability**  
   Favor clarity over cleverness. Use meaningful names; keep functions small and focused.
3. **Documentation**  
   - Document public APIs, complex logic, and architectural decisions.  
   - Maintain a `README.md` with setup, scripts, and workflows.  
   - Add ADRs (`/docs/adr/`) for significant decisions.
4. **Principles**  
   - Single Responsibility, DRY, KISS.  
   - Avoid premature optimization; measure before optimizing.  
   - Prefer composition over inheritance.
5. **Error Handling**  
   Fail fast where appropriate, bubble meaningful exceptions, and provide user-friendly messages at the edges.
6. **Security**  
   Never commit secrets. Validate input, escape output, and use prepared statements / Eloquent. Enable CSRF, rate limits, and authorization checks.
7. **Performance**  
   Avoid N+1 queries, cache where helpful, paginate, use eager loading (`with()`), and add proper DB indexes.

---

## Project Structure

```bash
/app              # Laravel app code
/resources
/resources/js             # Vue (Inertia) frontend
/resources/css            # Tailwind entry + any CSS
/routes           # Route definitions
/database
/migrations     # DB migrations
/seeders
/tests            # PHPUnit/Pest
/tests/Feature
/tests/Unit
/tests/Browser    # Playwright/Cypress (if used)
```

---

## Naming Conventions

- **PHP (Laravel)**
  - Classes: `PascalCase`; methods & variables: `camelCase`.
  - Eloquent models: singular (`User`, `OrderItem`).
  - Table names: plural snake_case (`users`, `order_items`).
  - Migrations: prefix with timestamp; keep one concern per migration.
  - Env keys: `UPPER_SNAKE_CASE`.
- **Vue**
  - Components: `PascalCase.vue` (`UserCard.vue`).
  - Props/events: `camelCase` in script; `kebab-case` in templates.
  - Composables: `useThing.js`.
  - Files and dirs: `kebab-case` unless exporting a class.
- **CSS/Tailwind**
  - Prefer utility-first classes; extract components with `@apply` sparingly into `*.css` if repeated 3+ times.
  - Custom CSS variables: `--app-*`.

---

## Database Standards (MySQL)

- Use **InnoDB** and **utf8mb4** with `utf8mb4_0900_ai_ci` collation.
  - Why: InnoDB provides ACID transactions, row-level locking (MVCC), foreign keys, and crash recovery. utf8mb4 stores full Unicode (including emoji) and `utf8mb4_0900_ai_ci` offers modern Unicode 9 sorting with case- and accent-insensitive comparisons that match search expectations.
  - Alternatives: For case- or accent-sensitive needs, use `utf8mb4_0900_as_cs` per column or query. If constrained to MySQL 5.7, prefer `utf8mb4_unicode_520_ci` (or `utf8mb4_unicode_ci`).
- Add foreign keys with `onDelete('cascade')` where relationally safe.
- Index fields used in joins/filters; add composite indexes thoughtfully.
- Use `decimal` for currency; store times in UTC; log user-facing times with timezone.
- Avoid nullable columns unless semantically necessary.

---

## Frontend (Vue + Inertia)

- Use **Vue 3 + `<script setup>`**. JavaScript is the first-class and preferred language for the frontend in this project. Do not introduce TypeScript unless explicitly approved. Use JSDoc and `checkJs` for editor type checking.
  - Enable editor type checking with JS via JSDoc and `checkJs`. Example `jsconfig.json`:
    ```json
    {
      "compilerOptions": {
        "checkJs": true,
        "baseUrl": ".",
        "paths": { "@/*": ["resources/js/*"] }
      },
      "exclude": ["node_modules", "vendor"]
    }
    ```
  - Validate props and external data at runtime (simple guards/validators) and keep the server as the source of truth (Form Requests, API Resources).
- State: use component state first; for shared state use composables or Pinia (sparingly).
- API to front-end via Inertia responses; avoid duplicating server-side validation on the client.
- Accessibility (a11y): semantic HTML, keyboard nav, proper labels/aria, focus management on page changes.
- Use Tailwind classes; install `prettier-plugin-tailwindcss` to sort classes deterministically.

---

## Backend (Laravel)

- Controllers remain thin; push domain logic into Services/Actions.
- Validation with Form Requests. Return typed Resources for APIs.
- Use Policies/Gates for authorization.
- Queue long-running tasks; use events/listeners for side effects.
- Use `env()` only in config files; resolve via `config()` elsewhere.

---

## Testing

- **PHP**: Prefer **Pest** (or PHPUnit) with factories & database transactions.  
  Commands:
  - `php artisan test` (fast suite)
  - `php artisan test --group=e2e` (longer/e2e if defined)
- **Vue**: **Vitest** + **@vue/test-utils** for unit tests.  
  - `npm run test` / `npm run test:watch`
- **E2E**: **Playwright** for browser flows touching both ends.  
  - `npm run e2e`

Strive for coverage on core domain logic and critical views. Test behavior, not implementation details.

---

## Git Workflow

- **Branches**: `main` (stable), `develop` (optional), feature branches `feat/<scope>`, fixes `fix/<scope>`, experiments `exp/<scope>`.
- **Conventional Commits**:
  - `feat: add user invitations`
  - `fix(auth): handle token refresh`
  - `chore(deps): bump laravel to 11.x`
- **PRs**:
  - Small, single-purpose.  
  - Include screenshots for UI changes.  
  - Link issues, describe testing steps, and include migration impact if any.

---

## Code Review Checklist

- Scope: one problem solved?  
- Readability: clear names, small functions, dead code removed?  
- Correctness: tests added/updated; edge cases covered?  
- Security: validation, authorization, escaping?  
- Performance: N+1 avoided, indexes present?  
- Accessibility: keyboard/aria/contrast?  
- Docs: comments/README/ADR updated?

---

## Environment & Secrets

- `.env` is **never** committed.  
- Use GitHub Encrypted Secrets for CI/CD; use parameter store or vault in prod.  
- Rotate credentials regularly; least-privilege DB users per environment.

---

## Coding Tool Linting and Formatting

### Lint

Use **ESLint** for JS and Vue, **Prettier** for formatting, **Stylelint** (optional) for CSS.

**Install (example):**
```bash
npm i -D eslint @eslint/js eslint-plugin-vue \
       prettier eslint-config-prettier eslint-plugin-prettier \
       stylelint stylelint-config-standard \
       prettier-plugin-tailwindcss
````

**`.eslintrc.json` (Vue 3, JS-only):**

```json
{
  "root": true,
  "env": { "browser": true, "es2023": true },
  "extends": [
    "eslint:recommended",
    "plugin:vue/vue3-recommended",
    "plugin:prettier/recommended"
  ],
  "parserOptions": { "ecmaVersion": "latest", "sourceType": "module" },
  "rules": {
    "vue/multi-word-component-names": "off"
  }
}
```

**`.prettierrc`**

```json
{
  "semi": false,
  "singleQuote": true,
  "trailingComma": "all",
  "plugins": ["prettier-plugin-tailwindcss"]
}
```

**NPM scripts (excerpt):**

```json
{
  "scripts": {
    "lint": "eslint \"resources/js/**/*.{js,vue}\"",
    "lint:fix": "npm run lint -- --fix",
    "format": "prettier --write .",
    "test": "vitest run",
    "e2e": "playwright test"
  }
}
```

### Pint

Use **Laravel Pint** for PHP code style (PSR-12 by default).

**Install & run:**

```bash
composer require laravel/pint --dev
composer exec pint -- --version
composer pint         # format
composer pint --test  # CI mode (no write)
```

**`pint.json` (example):**

```json
{
  "preset": "laravel",
  "rules": {
    "single_quote": true,
    "ordered_imports": { "sort_algorithm": "alpha" }
  }
}
```

### Rector

Use **Rector** for automated PHP refactors and upgrades.

**Install & run:**

```bash
composer require --dev rector/rector
vendor/bin/rector process
vendor/bin/rector --dry-run
```

**`rector.php` (example):**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    $config->paths([
        __DIR__ . '/app',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ]);

    $config->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::LARAVEL_110
    ]);
};
```

> Run Rector in **dry-run** on CI; apply changes locally in separate PRs.

---

## Pre-commit Hooks

Use **Husky** + **lint-staged** for staged file checks.

**Install & configure:**

```bash
npm i -D husky lint-staged
npx husky init
```

**`package.json` (excerpt):**

```json
{
  "lint-staged": {
    "*.{js,vue}": ["eslint --fix", "prettier --write"],
    "*.{php}": ["composer pint -- --dirty", "vendor/bin/rector --dry-run || true"],
    "*.{css,scss,md,json,yml,yaml}": ["prettier --write"]
  }
}
```

**`.husky/pre-commit`:**

```bash
#!/usr/bin/env sh
. "$(dirname -- "$0")/_/husky.sh"

npx lint-staged
```

---

## GitHub Actions (CI)

**`.github/workflows/ci.yml`**

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [main]

jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', tools: composer }
      - run: composer install --no-ansi --no-interaction --no-progress --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --env=testing --force
      - run: composer pint -- --test
      - run: vendor/bin/rector --dry-run
      - run: php artisan test --colors=never

  node:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm' }
      - run: npm ci
      - run: npm run lint
      - run: npm run test
      - run: npm run build
```

> Add DB service containers if your tests require MySQL.

---

## Logging & Observability

* Use Laravel logging channels; no `dd()`/`var_dump()` in committed code.
* Tag logs with request IDs; avoid logging PII.
* Add health checks and basic metrics for critical flows.

---

## Accessibility & Internationalization

* Ensure WCAG 2.1 AA standards where practical.
* Use translation files for strings; avoid hardcoding user-facing text.

---

## AI Assistance Guidelines

These guidelines govern how contributors use AI tools (including chat models) in this repo.

1. **Attribution & Review**

   * All AI-generated code must be **reviewed by a human** maintainer.
   * Note “AI-assisted” in PR description when substantial portions came from AI.
2. **Security & Licensing**

   * Do not paste secrets or proprietary code into third-party tools.
   * Do not import licenses incompatible with the project.
3. **Correctness**

   * Ask AI for **tests** along with code.
   * Verify API usage and version compatibility against official docs.
4. **Style Compliance**

   * Instruct AI to follow these standards and run linters/formatters locally.
5. **Prompt Template (copy into your AI tool):**

   ```
   You are a senior VILT engineer. Generate code that:
   - Targets PHP 8.2+, Vue 3 + <script setup>, Inertia, Tailwind.
   - Follows PSR-12 (Laravel Pint), ESLint/Prettier, and our naming rules.
   - Adds/updates tests (Pest/Vitest) and notes required migrations.
   - Includes brief inline comments only where logic is non-obvious.
   - Returns only the diff or files to create, with paths from repo root.
   - Avoids introducing new dependencies unless justified.
   ```
6. **What Not To Do**

   * Don’t fabricate APIs or types.
   * Don’t bypass validations or authorization.
   * Don’t commit AI output that fails lint, Pint, Rector, or tests.

---

## Pull Request Template (suggested)

```
## Summary
[What changed and why]

## Screenshots / GIF
[UI changes]

## Testing
- [ ] Unit tests pass
- [ ] E2E scenario(s) verified
- [ ] Manual QA steps: [...]

## Migrations
- [ ] Requires migration
- [ ] Backward compatible

## Risk & Rollback
[Potential impact and rollback plan]

## Notes
AI-assisted: [Yes/No]
```

---

## License & Notices

* Ensure file headers and repo `LICENSE` are accurate.
* Third-party code must include appropriate notices.

---

## Adoption

1. Copy this file to your repo as `CODING_STANDARDS.md`.
2. Add tooling configs (`.eslintrc.json`, `.prettierrc`, `pint.json`, `rector.php`).
3. Enable CI with the provided GitHub Actions workflow.
4. Enforce branch protection and required checks (`lint`, `pint:test`, `rector:dry-run`, `tests`).