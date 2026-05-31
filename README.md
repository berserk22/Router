# Router Module

The Router module provides a dynamic routing system and redirection management for the SkeletonApp. It extends the base Slim Framework routing capabilities by allowing routes to be defined and managed via database entries and provides efficient route resolution with APCu caching.

## Overview

The module includes:
- **Dynamic Routing**: Route definitions stored in the database for flexible URL management.
- **Redirection Management**: Support for permanent and temporary URL redirects (301, 302, etc.).
- **APCu Caching**: High-performance caching of route definitions to minimize database queries.
- **Middleware Integration**: Acts as a middleware to intercept and handle requests before they reach the main application logic.
- **Template Integration**: Provides view plugins to generate URLs dynamically within templates.

## Requirements

- **PHP**: >= 8.2
- **SkeletonApp Core**: Integration with the base `Provider`, DI container, and Middleware system.
- **Slim Framework**: Core routing and HTTP handling.
- **Illuminate Database**: Eloquent ORM for database-driven routes and redirects.
- **APCu**: Optional but recommended for route caching.

## Project Structure

- `Db/`:
  - `Models/`: Eloquent models (`Routers`, `Redirect`).
  - `Schema.php`: Database migration and schema definition for `routers` and `redirect` tables.
- `Manager/`: 
  - `RouterManager.php`: Handles business logic for router entities.
- `Plugins/`:
  - `GetUrl.php`: View plugin for generating URLs from route names.
  - `getRouterType.php`: Helper for identifying router types.
- `ApcuCache.php`: APCu-based caching layer for routes.
- `Methods.php`: Collection and mapping of route methods and groups.
- `Redirect.php`: Logic for handling URL redirections.
- `Router.php`: Main routing middleware and request processor.
- `RouterTrait.php`: Shared utilities for router components.
- `ServiceProvider.php`: Module initialization, service registration, and integration.

## Setup & Run Commands

The module is integrated into the SkeletonApp ecosystem.

1.  **Installation**:
    ```bash
    composer require skeleton-app/router
    ```

2.  **Registration**:
    The module's `ServiceProvider` is automatically registered or should be added to the application bootstrap.

3.  **Database Migration**:
    Run the application's migration command to create the `routers` and `redirect` tables:
    ```bash
    php cli migration:run
    ```

## Usage

### Services
The module registers several services in the DI container:
- `Router\Methods`: Management of route methods and groups.
- `Router\Redirect`: Redirection handling service.
- `Router\ApcuCache`: Route caching service.
- `Router\Manager`: Router entity management.

### View Plugins
The following plugins are available for use in templates:
- `getUrl($name, $params = [])`: Generates a URL for a named route. It first checks the APCu cache for dynamic routes and falls back to Slim's named routes.

## Configuration (Env Vars / Config)

The module uses the application's configuration system.
- **APCu**: Ensure APCu is enabled in your PHP environment to benefit from route caching.

TODO: Document specific configuration keys if added to `config/config.ini`.

## Scripts

The module currently integrates with the main application's CLI for migrations.

TODO: Document any dedicated CLI commands for route management if implemented.

## Tests

TODO: Tests are not yet implemented for this module. When added, run them from the project root:
```bash
./vendor/bin/phpunit modules/Router/tests
```

## License

This project is licensed under a proprietary license as specified in `composer.json`.
