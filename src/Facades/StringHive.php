<?php

declare(strict_types=1);

namespace Stringhive\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array locales()
 * @method static array hives()
 * @method static array hive(string $slug)
 * @method static array strings(string $slug, ?string $file = null, int $perPage = 100, int $page = 1)
 * @method static array allStrings(string $slug, ?string $file = null)
 * @method static array importStrings(string $slug, array $files, string $conflictStrategy = 'keep')
 * @method static array syncStrings(string $slug, array $files, string $conflictStrategy = 'keep')
 * @method static array importTranslations(string $slug, string $locale, array $files, string $overwriteStrategy = 'skip')
 * @method static array export(string $slug, string $format = 'json', ?string $locale = null)
 *
 * @see \Stringhive\StringHive
 */
class StringHive extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Stringhive\StringHive::class;
    }
}
