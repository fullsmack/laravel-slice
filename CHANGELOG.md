# Changelog

All notable changes to this project are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

First stable release. Public API changes since 0.3.0 are listed under **Breaking**
and **Deprecated**; follow them to upgrade.

### Breaking

- **`RefreshSliceDatabase` was removed.** Use `RefreshAllDatabases` instead (in place of
  Laravel's `RefreshDatabase`). It runs automatically, so remove manual
  `$this->refreshSlice(...)` calls from `setUp()`. To limit the refreshed slices, declare
  `protected string|array|null $refreshSlice = ['pizza', 'order'];` on the test class.
- **New exception for configuration errors.** Missing `routes/`, `resources/views/`,
  `lang/` or `database/migrations/` directories, and a missing
  `{identifier}::database.default` config, now throw `SliceMisconfigured` instead of
  `SliceNotRegistered`. Both implement the new `SliceException` interface; catch that to
  handle either.
- `RefreshAllDatabases` now throws `SliceNotRegistered` / `SliceMisconfigured` instead
  of `RuntimeException`.
- `SliceNotRegistered::becauseNameIsNotDefined()` was renamed to
  `becauseIdentifierIsNotDefined()`. The directory and database factory methods moved
  to `SliceMisconfigured`.
- The lifecycle hooks `registeringSlice()`, `sliceRegistered()`, `bootingSlice()` and
  `sliceBooted()` are now `protected`. Overriding them as `public` still works.
- Slice config loading only reads `.php` files directly inside `config/`. Files in
  subdirectories are no longer loaded (they used to be registered under
  `identifier::sub/file`).
- Binding a model that does not use the `UsesConnection` trait via
  `useConnection($connection, $models)` now throws `SliceMisconfigured` instead of
  being silently skipped.
- The unused `--dir` option was removed from `make:component`, `make:test`,
  `make:migration`, `migrate` and `slice:test` (it remains on `make:slice`).
- The unused `architecture` key was removed from `config/laravel-slice.php`.
- `make:migration --slice` only writes `protected $connection = '…'` when the slice
  passes an explicit name to `useConnection('name')`. Config-resolved connections are
  supplied via `--database` by `migrate --slice` / `--all-slices`.

### Deprecated (to be removed in 2.0)

- `Slice::setName()` and `Slice::name()`: use `setIdentifier()` and `identifier()`.
- `Slice::withConnection()`: use `useConnection()`, which now also accepts the models
  to bind as its second argument.

### Added

- `Slice::setIdentifier()` and `Slice::hasExplicitConnection()`.
- `SliceException` marker interface and `SliceMisconfigured` exception.
- `RefreshAllDatabases`:
  - `$refreshSlice` accepts an array of slice identifiers.
  - In-memory SQLite slice connections keep their migrated schema between tests.
  - Slices on the default test connection are migrated together with the default database.
- `make:slice` validates the identifier and prints a registration hint when
  `discovery.type` is not `composer`.
- `slice:test` reports a clear error when `nunomaduro/collision` is not installed
  (now listed under `suggest`).
- Laravel 12 and 13 in the test suite and CI (PHP 8.2–8.4).
- `LICENSE` file.

### Fixed

- `migrate --slice=x` ran nothing (and reported success) unless `--realpath` was also passed.
- `make:test --slice` failed on Laravel 13 with `The "--slice" option does not exist`.
- `make:component --slice` generated `view('slice::components.components.name')` on
  current Laravel versions.
- Slice config loading crashed on files like `.gitkeep` or `app.settings.php`, and route
  loading `require`d non-PHP files such as `README.md`.
- `RefreshAllDatabases`:
  - Declaring `$refreshSlice` on a test class was a fatal trait conflict.
  - In-memory slice databases were empty after the first test.
  - A slice on the default connection wiped the default test database.
- `make:component`, `make:migration` and `make:test` returned exit code 0 for an
  unregistered slice.
- `migrate --slice` / `--all-slices` and `slice:list-migrations` crashed with an
  exception for a slice with missing connection config. The migrate commands now fail
  with a message, and the list shows `(missing config)`.
- `SliceRegistry::sliceForMigrationPath()` matched sibling directories sharing a prefix
  (e.g. `migrations-archive`).
- `make:slice` rewrote empty objects in `composer.json` as `[]`, escaped non-ASCII
  characters and dropped the trailing newline. It also left a half-created slice
  behind when `composer.json` was missing.
- `make:test --slice` produced a double directory separator in the file path.
- Package config is merged in `register()` so other providers can read it.
- README: the config publish command used the wrong tag (`--tag=laravel-slice`).
