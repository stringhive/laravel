# Stringhive for Laravel

The official Laravel package for [Stringhive](https://stringhive.com). Two Artisan commands to sync your translation files, a full API client if you want to go deeper, and zero boilerplate.

[![CI](https://github.com/stringhive/laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/stringhive/laravel/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/stringhive/laravel)](https://packagist.org/packages/stringhive/laravel)
[![License](https://img.shields.io/github/license/stringhive/laravel)](LICENSE)

---

## Requirements

- PHP 8.5+
- Laravel 13+

---

## Installation

```bash
composer require stringhive/laravel
```

Publish the config if you want to peek at it:

```bash
php artisan vendor:publish --tag=stringhive-config
```

---

## Configuration

One line in your `.env` and you're done:

```env
STRINGHIVE_TOKEN=your-api-token
```

The service provider auto-registers. The facade is ready. Off you go.

> `STRINGHIVE_URL` defaults to `https://www.stringhive.com`. Only set it if you're running a custom server.

---

## Artisan Commands

This is the main event. Two commands that understand Laravel's translation file structure (both PHP-style subdirectories and flat JSON files) and just do the right thing.

### Push: local files to Stringhive

```bash
php artisan stringhive:push <hive>
```

Reads everything under `lang/` and uploads it. Source locale strings go in as source strings; everything else goes in as translations.

```
Options:
  --sync                  Also delete strings in the hive that aren't in your files (per-file)
  --conflict-strategy=    What to do with translations when a source string changes: keep (default) or clear
  --source-locale=        Override the source locale (defaults to config app.locale)
  --lang-path=            Use a different lang directory
```

Examples:

```bash
# Basic push
php artisan stringhive:push my-app

# Push and clean up stale strings
php artisan stringhive:push my-app --sync

# Wipe translations whenever a source string changes
php artisan stringhive:push my-app --conflict-strategy=clear

# Push from a non-standard path
php artisan stringhive:push my-app --lang-path=/var/www/resources/lang
```

The output tells you what happened per locale:

```
Pushing to hive: my-app
  source — created: 12  updated: 3  unchanged: 1230  cleared: 0
  es — created: 8  updated: 1  skipped: 0  unknown: 0
  fr — created: 8  updated: 1  skipped: 0  unknown: 0
Done.
```

### Pull: Stringhive to local files

```bash
php artisan stringhive:pull <hive>
```

Exports translations from Stringhive and writes them to your `lang/` directory.

```
Options:
  --locale=       Pull a single locale only (omit to pull all locales)
  --format=       Export format: php (default) or json
  --dry-run       Preview what would be written without touching anything
  --lang-path=    Use a different lang directory
```

Examples:

```bash
# Pull all locales as PHP files
php artisan stringhive:pull my-app

# Pull just Spanish
php artisan stringhive:pull my-app --locale=es

# Pull as JSON files
php artisan stringhive:pull my-app --format=json

# See what would happen before committing
php artisan stringhive:pull my-app --dry-run
```

Dry-run output:

```
[dry-run] No files will be written.
Pulling from hive: my-app
  [dry-run] Would write: /var/www/lang/es/app.php
  [dry-run] Would write: /var/www/lang/es/auth.php
Done.
```

---

## Programmatic Usage

All the same power, available from PHP. Good for deploy scripts, scheduled commands, or anything where you need more control.

```php
use Stringhive\Facades\Stringhive;

// or inject the client directly
public function __construct(private \Stringhive\Stringhive $stringhive) {}
```

### push()

```php
$result = Stringhive::push(
    hive: 'my-app',
    langPath: null,           // defaults to lang_path()
    sourceLocale: null,       // defaults to config('app.locale')
    sync: false,              // delete strings absent from import
    conflictStrategy: 'keep', // 'keep' or 'clear'
);

// [
//   'source' => ['created' => 12, 'updated' => 3, 'unchanged' => 1230, 'translations_cleared' => 0],
//   'translations' => [
//     'es' => ['created' => 8, 'updated' => 1, 'skipped' => 0, 'unknown' => 0],
//   ],
// ]
```

### pull()

```php
$result = Stringhive::pull(
    hive: 'my-app',
    langPath: null,    // defaults to lang_path()
    locale: 'es',     // null pulls all locales
    format: 'php',    // 'php' or 'json'
    dryRun: false,    // true to preview without writing
);

// [
//   'files'   => ['app.php' => '<?php return [...];', ...],
//   'paths'   => ['/var/www/lang/es/app.php', ...],
//   'written' => true,
// ]
```

---

## API Reference

If you need to go lower-level, the full API is right there.

### Locales

All locales available on the platform:

```php
$locales = Stringhive::locales();
// [['code' => 'en', 'name' => 'English', 'region' => 'US', 'rtl' => false, 'is_popular' => true], ...]
```

### Hives

List all hives your token can see:

```php
$hives = Stringhive::hives();
// [['slug' => 'my-app', 'name' => 'My App', 'source_locale' => 'en', 'locales' => ['es', 'fr'], ...]]
```

Stats for one hive:

```php
$hive = Stringhive::hive('my-app');
// ['slug' => 'my-app', 'string_count' => 1245, 'locales' => ['es' => ['translated' => 1200, ...]]]
```

### Source Strings

Fetch with pagination:

```php
$page = Stringhive::strings('my-app', page: 2, perPage: 50);
// ['data' => [...], 'meta' => ['total' => 1245, 'last_page' => 25, ...]]
```

Filter by file, or just grab everything at once:

```php
$strings = Stringhive::strings('my-app', file: 'auth.php');

$all = Stringhive::allStrings('my-app'); // loops pages automatically
```

### Import Source Strings

Push new or updated source strings. Nested arrays are flattened to dot notation automatically:

```php
$result = Stringhive::importStrings('my-app', [
    'app.php' => [
        'welcome' => ['title' => 'Welcome to My App'],
    ],
    'auth.php' => [
        'login' => ['email' => 'Email Address'],
    ],
]);
// ['created' => 2, 'updated' => 0, 'unchanged' => 1243, 'translations_cleared' => 0]
```

Control what happens to existing translations when a source value changes:

```php
Stringhive::importStrings('my-app', $files, conflictStrategy: 'clear');
```

### Sync Source Strings

Like import, but **deletes strings** not present in your payload (scoped per file):

```php
$result = Stringhive::syncStrings('my-app', $files);
// ['created' => 2, 'updated' => 0, 'unchanged' => 1240, 'deleted' => 3, 'translations_cleared' => 0]
```

### Import Translations

Push translated strings for a specific locale:

```php
$result = Stringhive::importTranslations('my-app', 'es', [
    'app.php' => [
        'welcome' => ['title' => 'Bienvenido a Mi App'],
    ],
]);
// ['created' => 1, 'updated' => 0, 'skipped' => 0, 'unknown' => 0]
```

Existing translations are skipped by default. Overwrite them:

```php
Stringhive::importTranslations('my-app', 'es', $files, overwriteStrategy: 'overwrite');
```

### Export Translations

One locale, flat JSON (one file per source file):

```php
$export = Stringhive::export('my-app', locale: 'es');
// ['files' => ['app.json' => '{"welcome.title":"Bienvenido"}', ...]]
```

All locales at once (one file per locale):

```php
$export = Stringhive::export('my-app');
// ['files' => ['en.json' => '{...}', 'es.json' => '{...}', 'fr.json' => '{...}']]
```

Laravel-style PHP arrays:

```php
$export = Stringhive::export('my-app', format: 'php', locale: 'es');
```

---

## Exceptions

Every error maps to a typed exception:

```php
use Stringhive\Exceptions\AuthenticationException; // 401 — bad or expired token
use Stringhive\Exceptions\ForbiddenException;      // 403 — no write permission on this hive
use Stringhive\Exceptions\HiveNotFoundException;   // 404 — that slug doesn't exist
use Stringhive\Exceptions\StringLimitException;    // 422 — hit your plan's string quota
use Stringhive\Exceptions\ValidationException;     // 422 — bad payload

try {
    Stringhive::importStrings('my-app', $files);
} catch (StringLimitException $e) {
    // time to upgrade
} catch (ValidationException $e) {
    $errors = $e->errors(); // ['field' => ['error message']]
} catch (HiveNotFoundException $e) {
    // slug typo?
}
```

---

## Typed Resources

Prefer objects over arrays? Each response type has a readonly resource class:

```php
use Stringhive\Resources\LocaleResource;
use Stringhive\Resources\HiveResource;
use Stringhive\Resources\SourceStringResource;
use Stringhive\Resources\TranslationStatsResource;

$locales = array_map(
    fn ($l) => LocaleResource::fromArray($l),
    Stringhive::locales()
);

$locales[0]->code;      // 'en'
$locales[0]->isPopular; // true
```

---

## License

MIT. See [LICENSE](LICENSE).
