# StringHive for Laravel

The official Laravel package for the [StringHive](https://stringhive.com) API. Pull locales, manage source strings, push translations, export files. All the good stuff, none of the boilerplate.

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

Publish the config file if you want to look at it:

```bash
php artisan vendor:publish --tag=stringhive-config
```

---

## Configuration

Add these two lines to your `.env`:

```env
STRINGHIVE_URL=https://your-stringhive-domain.com
STRINGHIVE_TOKEN=your-api-token
```

That's it. The service provider auto-registers and the facade is ready to go.

---

## Usage

You can use the facade or resolve the client from the container -- your call.

```php
use Stringhive\Facades\StringHive;

// or inject it
public function __construct(private \Stringhive\StringHive $stringhive) {}
```

### Locales

Get all available locales on the platform:

```php
$locales = StringHive::locales();

// [
//   ['code' => 'en', 'name' => 'English', 'region' => 'US', 'rtl' => false, 'is_popular' => true],
//   ['code' => 'es', 'name' => 'Spanish', 'region' => 'ES', 'rtl' => false, 'is_popular' => true],
// ]
```

### Hives

List all hives your token has access to:

```php
$hives = StringHive::hives();

// [
//   ['slug' => 'my-app', 'name' => 'My App', 'source_locale' => 'en', 'locales' => ['es', 'fr'], 'string_count' => 1245],
// ]
```

Get stats for a single hive:

```php
$hive = StringHive::hive('my-app');

// [
//   'slug' => 'my-app',
//   'string_count' => 1245,
//   'locales' => [
//     'es' => ['translated' => 1200, 'approved' => 980, 'translated_percent' => 98.4, ...],
//   ],
// ]
```

### Source Strings

Fetch strings with pagination:

```php
$page = StringHive::strings('my-app', page: 2, perPage: 50);

// ['data' => [...], 'meta' => ['total' => 1245, 'last_page' => 25, ...]]
```

Filter by file:

```php
$strings = StringHive::strings('my-app', file: 'auth.php');
```

Don't want to deal with pages? Use `allStrings` and let the package loop for you:

```php
$all = StringHive::allStrings('my-app');
```

### Importing Source Strings

Push new or updated strings into a hive. Keys can be nested -- the API flattens them to dot notation automatically:

```php
$result = StringHive::importStrings('my-app', [
    'app.php' => [
        'welcome' => ['title' => 'Welcome to My App'],
        'footer'  => ['copyright' => 'All rights reserved'],
    ],
    'auth.php' => [
        'login' => ['email' => 'Email Address', 'password' => 'Password'],
    ],
]);

// ['created' => 4, 'updated' => 0, 'unchanged' => 1241, 'translations_cleared' => 0]
```

When a string's source value changes, existing translations are kept by default (`conflict_strategy: keep`). If you'd rather wipe them and start fresh:

```php
StringHive::importStrings('my-app', $files, conflictStrategy: 'clear');
```

### Syncing Source Strings (destructive)

Same as import, but **removes strings** that aren't in your payload. Per file -- so strings in `app.php` not in your import get deleted, strings in other files are untouched:

```php
$result = StringHive::syncStrings('my-app', $files);

// ['created' => 2, 'updated' => 0, 'unchanged' => 1240, 'deleted' => 3, 'translations_cleared' => 0]
```

### Importing Translations

Push translated strings into a hive for a specific locale:

```php
$result = StringHive::importTranslations('my-app', 'es', [
    'app.php' => [
        'welcome' => ['title' => 'Bienvenido a Mi App'],
    ],
]);

// ['created' => 1, 'updated' => 0, 'skipped' => 0, 'unknown' => 0]
```

By default existing translations are left alone (`overwrite_strategy: skip`). To replace everything:

```php
StringHive::importTranslations('my-app', 'es', $files, overwriteStrategy: 'overwrite');
```

### Exporting Translations

Export as flat JSON (one file per source file):

```php
$export = StringHive::export('my-app', locale: 'es');

// ['files' => ['app.json' => '{"welcome.title":"Bienvenido"}', ...]]
```

Export all locales at once (one file per locale):

```php
$export = StringHive::export('my-app');

// ['files' => ['en.json' => '{...}', 'es.json' => '{...}', 'fr.json' => '{...}']]
```

Need Laravel-style PHP arrays instead?

```php
$export = StringHive::export('my-app', format: 'php', locale: 'es');
```

---

## Exceptions

Every error maps to a typed exception so you can catch exactly what you care about:

```php
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Exceptions\StringLimitException;
use Stringhive\Exceptions\ValidationException;

try {
    StringHive::importStrings('my-app', $files);
} catch (AuthenticationException $e) {
    // bad or expired token
} catch (ForbiddenException $e) {
    // token doesn't have write permission, or no access to this hive
} catch (HiveNotFoundException $e) {
    // slug doesn't exist
} catch (StringLimitException $e) {
    // you've hit your plan's string quota
} catch (ValidationException $e) {
    // something in the payload was wrong
    $errors = $e->errors(); // ['field' => ['message']]
}
```

---

## Typed Resources

If you'd rather work with objects than plain arrays, each response type has a resource class with a `fromArray` constructor:

```php
use Stringhive\Resources\LocaleResource;
use Stringhive\Resources\HiveResource;
use Stringhive\Resources\SourceStringResource;
use Stringhive\Resources\TranslationStatsResource;

$locales = array_map(
    fn ($l) => LocaleResource::fromArray($l),
    StringHive::locales()
);

$locales[0]->code;       // 'en'
$locales[0]->isPopular;  // true
```

---

## License

MIT. See [LICENSE](LICENSE).
