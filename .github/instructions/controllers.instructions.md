# Controllers Instructions

## General Guidelines

1. Naming Conventions:
   - Use descriptive names for controllers that reflect their purpose.
   - Follow the PascalCase convention for controller names (e.g., `MilkProductionController`).
   - Format may be `[Resource][Action]Controller` for specific actions (e.g., `UserProfileController`). Maybe `[Noun][Verb]Controller` for specific actions.
2. No Business Logic:
   - Controllers should not contain business logic. Delegate such logic to service classes or models.
3. RESTful Principles:
   - Adhere to RESTful principles when designing controller actions (e.g., index, show, create, update, delete).
4. Dependency Injection:
   - Use dependency injection to manage dependencies within controllers.
5. Error Handling:
   - Implement proper error handling and return appropriate HTTP status codes.  
6. Validation:
    - Validate incoming requests using form request classes or validation logic before processing.  
7. Response Formatting:
   - Ensure consistent response formatting, preferably using resource classes for JSON responses.
8. Middleware:
   - Apply middleware for authentication, authorization, and other cross-cutting concerns as needed.

## Types of Controllers
1. Resource Controllers:
   - Handle CRUD operations for a specific resource.
    Example:
    ```php
     class MilkProductionController extends Controller
     {
            public function index() { /* List all milk productions */ }
            public function show($id) { /* Show specific milk production */ }
            public function store(Request $request) { /* Create new milk production */ }
            public function update(Request $request, $id) { /* Update existing milk production */ }
            public function destroy($id) { /* Delete milk production */ }
     }
     ```
     Method naming conventions for resource controllers should follow standard RESTful actions: `index`, `show`, `store`, `update`, `destroy`.  
2. Singleton Controllers:
   - Manage actions that do not fit into standard CRUD operations.
   Example: 
   ```php
    class DashboardController extends Controller
    {
         public function index()
         {
              // Logic for displaying dashboard
         }
    }
    ```
    Method naming conventions for singleton controllers should be descriptive of the action being performed (e.g., `index`, `showStats`, `generateReport`).
3. API Controllers:
   - Designed specifically for API endpoints, often returning JSON responses.