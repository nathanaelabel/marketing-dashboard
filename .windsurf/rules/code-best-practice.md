---
trigger: always_on
---

Windsurf Rules – Laravel, Alpine.js, Tailwind CSS, MCP Server Context 7

You are an expert in the stack: Laravel, Alpine.js, and Tailwind CSS, with a strong emphasis on Laravel and PHP best practices.
When documentation or the latest framework details are needed, leverage MCP Server Context 7 to access or verify up-to-date official references.

Key Principles

-   Write concise, technical responses with accurate PHP and Alpine.js examples.
-   Follow Laravel best practices and conventions.
-   Use object-oriented programming with a focus on SOLID principles.
-   Prefer iteration and modularization over duplication.
-   Use descriptive variable and method/component names.
-   Favor dependency injection and service containers.
-   When additional clarity or framework updates are required, consult MCP Server Context 7 for authoritative documentation.
-   Do not produce README or documentation unless explicitly asked.

PHP and Laravel Core

-   Use PHP 8.1+ features when appropriate (e.g., typed properties, match expressions).
-   Follow PSR-12 coding standards.
-   Use strict typing: declare(strict_types=1);
-   Utilize Laravel's built-in features and helpers when possible.
-   Follow Laravel's directory structure and naming conventions.
-   Use lowercase with dashes for directories (e.g., app/Http/Controllers).
-   Implement proper error handling and logging:
    -   Use Laravel's exception handling and logging features.
    -   Create custom exceptions when necessary.
    -   Use try-catch blocks for expected exceptions.
-   Use Laravel's validation features for form and request validation.
-   Implement middleware for request filtering and modification.
-   Utilize Laravel's Eloquent ORM for database interactions.
-   Use Laravel's query builder for complex database queries.
-   Implement proper database migrations and seeders.

Laravel Best Practices

-   Use Eloquent ORM instead of raw SQL queries when possible.
-   Implement Repository pattern for data access layer.
-   Use Laravel's built-in authentication and authorization features.
-   Utilize Laravel's caching mechanisms for improved performance.
-   Implement job queues for long-running tasks.
-   Use Laravel's built-in testing tools (PHPUnit, Pest) for unit and feature tests.
-   Implement API versioning for public APIs.
-   Use Laravel's localization features for multi-language support.
-   Implement proper CSRF protection and security measures.
-   Use Vite for modern frontend asset bundling and hot reloading.
-   Implement proper database indexing for improved query performance.
-   Use Laravel's built-in pagination features.
-   Implement proper error logging and monitoring.

Alpine.js Implementation

-   Use Alpine.js for lightweight interactivity on the frontend.
-   Keep components small and focused, using x-data, x-model, x-bind, and x-on effectively.
-   Prefer declarative bindings instead of imperative DOM manipulation.
-   Use Alpine stores for shared/global state management when needed.
-   Leverage x-cloak, x-show, and transitions for smooth UI experiences.
-   Keep business logic in Laravel, and use Alpine only for UI state and interactivity.

Tailwind CSS Styling

-   Utilize Tailwind's utility classes for responsive design.
-   Implement a consistent color scheme and typography using Tailwind's configuration.
-   Use Tailwind's @apply directive in CSS files or <style> blocks for reusable component styles.
-   Optimize for production by purging unused CSS classes.
-   Apply transitions, animations, and hover states for a polished UI.

Performance Optimization

-   Use Laravel's caching mechanisms for frequently accessed data.
-   Minimize database queries by eager loading relationships.
-   Implement pagination for large data sets.
-   Use Laravel's built-in scheduling features for recurring tasks.
-   Keep Alpine.js usage lightweight; avoid over-engineering interactivity.
-   When analyzing optimization techniques, cross-check performance best practices via MCP Server Context 7.

Security Best Practices

-   Always validate and sanitize user input.
-   Use Laravel's CSRF protection for all forms.
-   Implement proper authentication and authorization using Laravel's built-in features.
-   Use Laravel's prepared statements to prevent SQL injection.
-   Implement proper database transactions for data integrity.
-   Refer to the latest Laravel and PHP security advisories through MCP Server Context 7 when needed.

Using MCP Server Context 7 in Laravel Development Workflow

-   When implementing new Laravel features, always validate syntax, helper methods, or breaking changes through MCP Server Context 7 to ensure compliance with the latest Laravel documentation.
-   For optimization, testing, or deployment processes, query MCP Server Context 7 to cross-check the most current recommendations and official performance guidelines.
-   When encountering deprecations or unclear framework behavior, MCP Server Context 7 should serve as the authoritative source before relying on third-party articles or outdated tutorials.
-   During integration of Alpine.js or Tailwind CSS features, use MCP Server Context 7 to verify compatibility notes or configuration updates relevant to Laravel’s Vite pipeline.

Testing

-   Write unit tests for Laravel controllers and models.
-   Implement feature tests for endpoints and Blade responses.
-   Use Laravel Dusk or Cypress for end-to-end testing when necessary.
-   Keep Alpine.js logic simple enough that most testing is covered by Laravel + integration tests.
-   Consult MCP Server Context 7 for up-to-date testing best practices or new framework features.

Key Conventions

1. Follow Laravel's MVC architecture.
2. Use Laravel's routing system for defining application endpoints.
3. Implement proper request validation using Form Requests.
4. Use Blade + Alpine.js to build dynamic, reactive frontend interfaces.
5. Implement proper database relationships using Eloquent.
6. Use Laravel Breeze, Jetstream, or custom scaffolding for authentication.
7. Implement proper API resource transformations and Blade responses.
8. Use Laravel's event and listener system for decoupled code.
9. Utilize MCP Server Context 7 for framework documentation, new syntax, or breaking changes verification.

Dependencies

-   Laravel (latest stable version)
-   Alpine.js
-   Tailwind CSS
-   Vite for frontend tooling
-   MCP Server Context 7 (for documentation and framework reference)
-   Composer and NPM for dependency management
