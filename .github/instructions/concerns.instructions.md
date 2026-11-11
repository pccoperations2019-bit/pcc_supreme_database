# AI Coding Instructions: Concerns

## Purpose
This document outlines the rules and best practices for generating and organizing "Concern" traits in the S.U.P.R.E.M.E CBED POC Laravel project.

## Rules

1. **Location**
   - Place all reusable traits in a `Concerns` subfolder within their relevant domain (e.g., `app/Models/Concerns`, `app/Policies/Concerns`, `app/Controllers/Concerns`).
   - Do not place `Concerns` folders at the project root.

2. **Naming**
   - Trait names should be descriptive and use PascalCase (e.g., `HasScopeAccess`, `HasSoftDeletes`).
   - Prefix trait names with an action or behavior (e.g., `Has`, `Can`, `Handles`).

3. **Usage**
   - Use traits to encapsulate shared logic or behaviors that can be reused across multiple classes.
   - Avoid placing business logic in traits; keep them focused on reusable concerns.

4. **Documentation**
   - Each trait should include a PHPDoc block describing its purpose and usage.

5. **Coding Standards**
   - Follow Laravel 2025 and PHP 8.4 standards.
   - Ensure traits do not introduce side effects or global state.

6. **Examples**
   - Example trait location: `app/Policies/Concerns/HasScopeAccess.php`
   - Example usage in a policy:
     ```php
     use App\Policies\Concerns\HasScopeAccess;

     class UserPolicy
     {
         use HasScopeAccess;
         // ...
     }
     ```

## References
- [Laravel Documentation: Traits & Concerns](https://laravel.com/docs/10.x/eloquent#concerns-traits)
- Project Coding Standards in `.github/copilot-instructions.md`
