<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringhive\Lang\LangLoader;
use Stringhive\Stringhive;

function client(): Stringhive
{
    return new Stringhive('https://stringhive.test', 'test-token');
}

function phpLang(): string
{
    return __DIR__.'/fixtures/lang/php';
}

function jsonLang(): string
{
    return __DIR__.'/fixtures/lang/json';
}

// ---------------------------------------------------------------------------
// push() — PHP-style
// ---------------------------------------------------------------------------

it('push() imports PHP source strings', function () {
    Http::fake(['*' => Http::response(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])]);

    $result = client()->push('my-hive', langPath: phpLang());

    expect($result['source'])->toMatchArray(['created' => 2])
        ->and($result['translations'])->toBeArray();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' &&
        isset($r->data()['files']['app.php'], $r->data()['files']['auth.php'])
    );
});

it('push() imports PHP translations for every non-source locale', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])
        ->push(['created' => 1, 'updated' => 0, 'skipped' => 0, 'unknown' => 0]),
    ]);

    $result = client()->push('my-hive', langPath: phpLang());

    expect($result['translations'])->toHaveKey('es');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/translations/es'));
});

it('push() uses PUT when sync is true', function () {
    Http::fake(['*' => Http::response(['created' => 0, 'updated' => 0, 'unchanged' => 2, 'deleted' => 0, 'translations_cleared' => 0])]);

    client()->push('my-hive', langPath: phpLang(), sync: true);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT');
});

it('push() forwards conflict strategy', function () {
    Http::fake(['*' => Http::response(['created' => 0, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])]);

    client()->push('my-hive', langPath: phpLang(), conflictStrategy: 'clear');

    Http::assertSent(fn (Request $r) => isset($r->data()['conflict_strategy']) &&
        $r->data()['conflict_strategy'] === 'clear'
    );
});

it('push() returns null source and empty translations when no source locale is found', function () {
    Http::fake(['*' => Http::response([])]);

    $result = client()->push('my-hive', langPath: phpLang(), sourceLocale: 'fr');

    expect($result['source'])->toBeNull()
        ->and($result['translations'])->toBe([]);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// push() — JSON-style
// ---------------------------------------------------------------------------

it('push() imports JSON source strings', function () {
    Http::fake(['*' => Http::response(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])]);

    $result = client()->push('my-hive', langPath: jsonLang());

    expect($result['source'])->toMatchArray(['created' => 2]);

    Http::assertSent(fn (Request $r) => isset($r->data()['files']['en.json']) &&
        $r->data()['files']['en.json']['Hello'] === 'Hello'
    );
});

it('push() uses source locale filename as JSON file key for translations', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])
        ->push(['created' => 2, 'updated' => 0, 'skipped' => 0, 'unknown' => 0]),
    ]);

    client()->push('my-hive', langPath: jsonLang());

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/translations/es') &&
        isset($r->data()['files']['en.json'])
    );
});

// ---------------------------------------------------------------------------
// push() — LangLoader injection
// ---------------------------------------------------------------------------

it('push() accepts an injected LangLoader for testing', function () {
    Http::fake(['*' => Http::response(['created' => 1, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])]);

    $loader = Mockery::mock(LangLoader::class);
    $loader->allows('phpLocales')->andReturn(['en']);
    $loader->allows('jsonLocales')->andReturn([]);
    $loader->allows('readPhpLocale')->with(Mockery::any(), 'en')->andReturn(['app.php' => ['key' => 'val']]);

    $result = client()->push('my-hive', langPath: '/fake', loader: $loader);

    expect($result['source'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// pull() — single locale
// ---------------------------------------------------------------------------

it('pull() writes files for a specific locale', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['app.php' => "<?php\n\nreturn ['key'=>'val'];"],
    ])]);

    $result = client()->pull('my-hive', langPath: $dir, locale: 'es', format: 'php');

    expect($result['written'])->toBeTrue()
        ->and($result['paths'])->toContain($dir.'/es/app.php')
        ->and(file_exists($dir.'/es/app.php'))->toBeTrue();

    removeTempDir($dir);
});

it('pull() returns correct paths without writing in dry-run mode', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['app.php' => '<?php return [];', 'auth.php' => '<?php return [];'],
    ])]);

    $result = client()->pull('my-hive', langPath: $dir, locale: 'es', dryRun: true);

    expect($result['written'])->toBeFalse()
        ->and($result['paths'])->toHaveCount(2)
        ->and(is_dir($dir.'/es'))->toBeFalse();

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// pull() — all locales
// ---------------------------------------------------------------------------

it('pull() writes root files when no locale is specified', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['es.json' => '{"Hello":"Hola"}', 'fr.json' => '{"Hello":"Bonjour"}'],
    ])]);

    $result = client()->pull('my-hive', langPath: $dir, format: 'json');

    expect($result['written'])->toBeTrue()
        ->and($result['paths'])->toContain($dir.'/es.json')
        ->and(file_exists($dir.'/es.json'))->toBeTrue();

    removeTempDir($dir);
});

it('pull() populates paths even in dry-run for all-locale export', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['es.json' => '{}', 'fr.json' => '{}'],
    ])]);

    $result = client()->pull('my-hive', langPath: $dir, format: 'json', dryRun: true);

    expect($result['written'])->toBeFalse()
        ->and($result['paths'])->toHaveCount(2)
        ->and(file_exists($dir.'/es.json'))->toBeFalse();

    removeTempDir($dir);
});
