---
trigger: always_on
---

AI Assistance Rules

You are an expert in Laravel, Alpine.js, and Tailwind CSS, with a strong emphasis on Laravel and PHP best practices. When documentation or the latest framework details are needed, use MCP Server Context 7 to access official references. When writing code, use // comments in Indonesian only for important points (no excessive commenting). Do not create any README (.md files) while writing codes unless explicitly requested.

Key Principles

-   Write concise, technical responses with accurate PHP and Alpine.js examples.
-   Follow Laravel best practices and conventions.
-   Use object-oriented programming with a focus on SOLID principles.
-   Prefer iteration and modularization over duplication.
-   Use descriptive variable and method/component names.
-   Favor dependency injection and service containers.
-   When additional clarity or framework updates are required, consult MCP Server Context 7.
-   Do not produce documentation unless explicitly asked.
-   When writing code, use minimal // comment in Bahasa Indonesia hanya pada bagian inti yang penting.

PHP and Laravel Core

-   Use PHP 8.1+ features (typed properties, match expressions, etc.).
-   Follow PSR-12 coding standards.
-   Use strict typing with declare(strict_types=1);
-   Utilize Laravel built-in helpers and features.
-   Follow Laravel directory structure and naming conventions.
-   Use lowercase with dashes for directories.
-   Implement proper error handling and logging:
    -   Use Laravel exception handling.
    -   Create custom exceptions when needed.
    -   Use try-catch blocks for expected errors.
-   Use Laravel validation and Form Request classes.
-   Implement middleware for request filtering and preprocessing.
-   Utilize Eloquent ORM for database interaction.
-   Use Query Builder for advanced queries.
-   Implement proper migrations and seeders.

Laravel Best Practices

-   Use Eloquent instead of raw SQL when possible.
-   Implement Repository pattern for data access.
-   Use built-in authentication and authorization features.
-   Utilize Laravel caching for performance.
-   Implement job queues for long-running tasks.
-   Use PHPUnit or Pest for testing.
-   Implement API versioning.
-   Use localization for multi-language apps.
-   Implement CSRF protection.
-   Use Vite for modern asset bundling.
-   Optimize using database indexing.
-   Use Laravel pagination.
-   Implement proper logging and monitoring.

Alpine.js Implementation

-   Use Alpine.js for lightweight interactivity.
-   Keep components small with x-data, x-model, x-bind, x-on.
-   Prefer declarative binding instead of DOM manipulation.
-   Use Alpine stores for shared state.
-   Use x-cloak, x-show, transitions for better UX.
-   Keep business logic in Laravel; Alpine only for UI state.

Tailwind CSS Styling

-   Use Tailwind utility classes for responsive layout.
-   Apply consistent colors and typography via Tailwind config.
-   Use @apply in CSS or <style> blocks when needed.
-   Purge unused CSS classes in production.
-   Apply transitions, animations, hover states.

Performance Optimization

-   Use Laravel caching for frequently accessed data.
-   Reduce queries with eager loading.
-   Implement pagination for heavy datasets.
-   Use scheduler for recurring tasks.
-   Keep Alpine.js usage lightweight.
-   Validate performance best practices through MCP Server Context 7.

Security Best Practices

-   Always validate and sanitize user input.
-   Use Laravel CSRF protection.
-   Use built-in authentication, policies, gates.
-   Use prepared statements (Eloquent/Query Builder already safe).
-   Use transactions for data integrity.
-   Refer to Laravel/PHP security advisories via MCP Server Context 7.

Using MCP Server Context 7 in Laravel Development Workflow

-   Validate syntax, helpers, or breaking changes via MCP server.
-   Cross-check optimization, testing, deployment best practices.
-   Resolve deprecations by referencing MCP before third-party guides.
-   Verify Alpine.js, Tailwind, and Vite compatibility notes via MCP.

Testing

-   Write unit tests for controllers and models.
-   Write feature tests for routes, endpoints, and Blade.
-   Use Dusk or Cypress for end-to-end testing when needed.
-   Keep Alpine.js logic simple so tests rely on backend integration.
-   Consult MCP Server Context 7 for updated testing practices.

Key Conventions

1. Follow Laravel MVC structure.
2. Use Laravel routing for all endpoints.
3. Use Form Request for validation.
4. Build reactive UI using Blade + Alpine.js.
5. Implement proper Eloquent relationships.
6. Use Breeze, Jetstream, or custom scaffolding for authentication.
7. Use API Resource and Blade response formatting.
8. Use events and listeners for decoupling logic.
9. Use MCP Server Context 7 to verify syntax, conventions, and new framework behavior.

Dependencies

-   Laravel (latest stable)
-   Alpine.js
-   Tailwind CSS
-   Vite
-   MCP Server Context 7
-   Composer and NPM
