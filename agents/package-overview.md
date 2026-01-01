# Laravel Slice Package - AI Agent Overview

## Package Purpose
Laravel Slice is a modular architecture package that enables developers to organize Laravel applications into discrete, self-contained slices. These slices can be horizontal UI components, core modules, or vertical feature slices that encapsulate related functionality.

## Core Concept
The package allows you to break down a monolithic Laravel application into manageable, independent modules called "slices" that can have their own:
- Views and UI components
- Database migrations
- Translations
- Routes
- Console commands
- Configuration files
- Features

## Main Components

### 1. Slice Class (`src/Slice.php`)
The central configuration object that defines what a slice contains:
- **Name**: Unique identifier for the slice
- **Base Path**: File system location
- **Base Namespace**: PHP namespace
- **Capabilities**: Flags for routes, translations, views, migrations
- **Commands**: Array of console commands
- **Features**: Array of additional features

### 2. SliceServiceProvider (`src/SliceServiceProvider.php`)
Abstract base class that handles slice registration and bootstrapping:
- **Registration**: Sets up slice paths and namespaces
- **Bootstrapping**: Loads routes, views, translations, migrations, config
- **Feature Integration**: Registers custom features
- **Console Integration**: Registers commands when running in console

### 3. Configuration Pattern
Slices are configured using a fluent interface in the `configure()` method:

```php
public function configure(Slice $slice): void
{
    $slice->setName('slice-name')
        ->useViews()           // Load views from resources/views
        ->useTranslations()    // Load translations from lang/
        ->useMigrations()      // Load migrations from database/migrations
        ->useRoutes()          // Load routes from routes/
        ->withConsoleCommands() // Register console commands
        ->withCommands([...])   // Specific command classes
        ->withFeature($feature); // Custom features
}
```

### 4. Commands (`src/Command/`)
Artisan commands for slice management:
- **MakeSlice**: Creates new slice directory structure
- **MakeComponent**: Creates slice components
- **MakeTest**: Creates slice tests
- **MigrateSlice**: Runs migrations for specific slice
- **SliceDefinitions**: Common slice path/namespace logic

### 5. Feature Interface (`src/Feature.php`)
Extensibility point for custom slice functionality:
```php
interface Feature
{
    public function register(Slice $slice): void;
}
```

## Directory Structure
Each slice follows a standardized structure:
```
slice-name/
├── config/           # Slice-specific configuration
├── lang/en/          # Translation files
├── resources/views/  # Blade templates
├── routes/           # Route definitions
├── src/              # PHP source code
├── tests/            # PHPUnit tests
└── database/migrations/ # Database migrations
```

## Key Features

### Auto-Discovery
- Composer autoloading integration
- Automatic service provider registration
- Namespace and path resolution

### Isolation
- Each slice has its own namespace
- Separate configuration namespacing
- Independent migration handling
- Isolated view and translation loading

### Flexibility
- Optional components (views, migrations, etc.)
- Custom feature extension points
- Console command integration
- Route organization

## Current Capabilities
- ✅ View loading and namespacing
- ✅ Translation loading with namespacing
- ✅ Migration loading (shared connection)
- ✅ Route loading
- ✅ Console command registration
- ✅ Configuration file merging
- ✅ Feature extension system
- ✅ Slice scaffolding commands

## Development Context
The package is actively being enhanced with database connection isolation features, allowing slices to have their own database connections rather than sharing the main application's connection.

## Usage Pattern
1. Create slice using `php artisan make:slice slice-name`
2. Extend `SliceServiceProvider` in the slice
3. Configure slice capabilities in `configure()` method
4. Register the service provider in composer.json or app config
5. Develop slice functionality in isolation

This architecture promotes:
- **Modularity**: Clear separation of concerns
- **Reusability**: Slices can be shared between projects
- **Maintainability**: Easier to manage large applications
- **Testing**: Isolated testing of slice functionality
- **Team Development**: Different teams can work on different slices
