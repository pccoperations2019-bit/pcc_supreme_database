---
applyTo: "app/Models/**/*.php"
---

# Model Conventions

## General Guidelines

1. **Namespace**: Ensure all models are under the `App\Models` namespace.
2. **Class Naming**: Use singular, PascalCase names for model classes (e.g., `User`, `Product`).
3. **File Naming**: Model files should be named in PascalCase matching the class name (e.g., `User.php` for the `User`
class).
4. **Base Model**: All models should extend the base `Illuminate\Database\Eloquent\Model` class.
5. **Timestamps**: If the model uses timestamps, ensure the `$timestamps` property is set to `true`. If not, set it to
`false`.
6. **Guarded**: Use the `$guarded` property to specify which attributes `should not be mass-assignable`. If all attributes are
mass-assignable, set `$guarded` to an empty array. Avoid using `$fillable` unless necessary.

## Enums 

## Casting and Attributes

## Relationships



