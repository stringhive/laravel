<?php

declare(strict_types=1);

use Stringhive\Lang\LangLoader;

function phpFixtures(): string
{
    return __DIR__.'/../fixtures/lang/php';
}

function jsonFixtures(): string
{
    return __DIR__.'/../fixtures/lang/json';
}

// ---------------------------------------------------------------------------
// phpLocales()
// ---------------------------------------------------------------------------

it('detects PHP locale directories', function () {
    $locales = (new LangLoader)->phpLocales(phpFixtures());

    expect($locales)->toContain('en')->toContain('es');
});

it('ignores vendor directory', function () {
    $dir = makeTempLangDir();
    mkdir($dir.'/vendor', 0755);
    mkdir($dir.'/en', 0755);

    $locales = (new LangLoader)->phpLocales($dir);

    expect($locales)->toContain('en')->not->toContain('vendor');

    removeTempDir($dir);
});

it('returns empty array when lang path does not exist', function () {
    expect((new LangLoader)->phpLocales('/this/does/not/exist'))->toBe([]);
});

// ---------------------------------------------------------------------------
// jsonLocales()
// ---------------------------------------------------------------------------

it('detects JSON locale files', function () {
    $locales = (new LangLoader)->jsonLocales(jsonFixtures());

    expect($locales)->toContain('en')->toContain('es');
});

it('returns empty array for missing path', function () {
    expect((new LangLoader)->jsonLocales('/this/does/not/exist'))->toBe([]);
});

// ---------------------------------------------------------------------------
// readPhpLocale()
// ---------------------------------------------------------------------------

it('reads PHP locale files as nested arrays', function () {
    $files = (new LangLoader)->readPhpLocale(phpFixtures(), 'en');

    expect($files)
        ->toHaveKey('app.php')
        ->toHaveKey('auth.php')
        ->and($files['app.php']['welcome']['title'])->toBe('Welcome')
        ->and($files['auth.php']['login']['email'])->toBe('Email Address');
});

it('returns empty array when PHP locale directory does not exist', function () {
    expect((new LangLoader)->readPhpLocale(phpFixtures(), 'fr'))->toBe([]);
});

it('skips PHP files that do not return an array', function () {
    $dir = makeTempLangDir();
    mkdir($dir.'/en', 0755);
    file_put_contents($dir.'/en/bad.php', '<?php return "not an array";');
    file_put_contents($dir.'/en/good.php', '<?php return ["key" => "value"];');

    $files = (new LangLoader)->readPhpLocale($dir, 'en');

    expect($files)->toHaveKey('good.php')->not->toHaveKey('bad.php');

    removeTempDir($dir);
});

it('skips files matching exclude patterns when reading PHP locale', function () {
    $files = (new LangLoader)->readPhpLocale(phpFixtures(), 'en', ['auth.php']);

    expect($files)->toHaveKey('app.php')->not->toHaveKey('auth.php');
});

it('supports glob wildcards in exclude patterns', function () {
    $dir = makeTempLangDir();
    mkdir($dir.'/en', 0755);
    file_put_contents($dir.'/en/app.php', '<?php return ["a" => "1"];');
    file_put_contents($dir.'/en/auth.php', '<?php return ["b" => "2"];');
    file_put_contents($dir.'/en/passwords.php', '<?php return ["c" => "3"];');

    // 'pass*.php' matches only passwords.php
    $files = (new LangLoader)->readPhpLocale($dir, 'en', ['pass*.php']);

    expect($files)->toHaveKey('app.php')->toHaveKey('auth.php')->not->toHaveKey('passwords.php');

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// isExcluded()
// ---------------------------------------------------------------------------

it('isExcluded returns true for an exact match', function () {
    expect((new LangLoader)->isExcluded('auth.php', ['auth.php']))->toBeTrue();
});

it('isExcluded returns false when no pattern matches', function () {
    expect((new LangLoader)->isExcluded('auth.php', ['app.php', 'passwords.php']))->toBeFalse();
});

it('isExcluded returns false for empty patterns', function () {
    expect((new LangLoader)->isExcluded('auth.php', []))->toBeFalse();
});

it('isExcluded supports fnmatch glob patterns', function () {
    expect((new LangLoader)->isExcluded('auth.php', ['*.php']))->toBeTrue();
    expect((new LangLoader)->isExcluded('en.json', ['*.php']))->toBeFalse();
});

// ---------------------------------------------------------------------------
// readJsonLocale()
// ---------------------------------------------------------------------------

it('reads JSON locale file as flat array', function () {
    $data = (new LangLoader)->readJsonLocale(jsonFixtures(), 'en');

    expect($data)
        ->toHaveKey('Hello')
        ->and($data['Hello'])->toBe('Hello');
});

it('reads translated JSON locale', function () {
    $data = (new LangLoader)->readJsonLocale(jsonFixtures(), 'es');

    expect($data['Hello'])->toBe('Hola');
});

it('returns empty array when JSON file does not exist', function () {
    expect((new LangLoader)->readJsonLocale(jsonFixtures(), 'fr'))->toBe([]);
});

it('returns empty array for invalid JSON', function () {
    $dir = makeTempLangDir();
    file_put_contents($dir.'/en.json', 'not-json');

    expect((new LangLoader)->readJsonLocale($dir, 'en'))->toBe([]);

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// writePhpLocale()
// ---------------------------------------------------------------------------

it('creates locale directory and writes files', function () {
    $dir = makeTempLangDir();
    $content = "<?php\n\nreturn ['title' => 'Hola'];";

    $written = (new LangLoader)->writePhpLocale($dir, 'es', ['app.php' => $content]);

    expect($written)->toHaveCount(1)
        ->and(file_exists($dir.'/es/app.php'))->toBeTrue()
        ->and(file_get_contents($dir.'/es/app.php'))->toBe($content);

    removeTempDir($dir);
});

it('writes multiple files for one locale', function () {
    $dir = makeTempLangDir();

    $written = (new LangLoader)->writePhpLocale($dir, 'es', [
        'app.php' => "<?php\n\nreturn ['a' => '1'];",
        'auth.php' => "<?php\n\nreturn ['b' => '2'];",
    ]);

    expect($written)->toHaveCount(2)
        ->and(file_exists($dir.'/es/app.php'))->toBeTrue()
        ->and(file_exists($dir.'/es/auth.php'))->toBeTrue();

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// writeLangFile()
// ---------------------------------------------------------------------------

it('writes a file to the lang root', function () {
    $dir = makeTempLangDir();
    $path = (new LangLoader)->writeLangFile($dir, 'es.json', '{"Hello":"Hola"}');

    expect($path)->toBe($dir.'/es.json')
        ->and(file_get_contents($path))->toBe('{"Hello":"Hola"}');

    removeTempDir($dir);
});
