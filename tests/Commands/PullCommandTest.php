<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Single locale (files written to lang/{locale}/{filename})
// ---------------------------------------------------------------------------

it('writes PHP files for a specific locale', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => [
            'app.php' => "<?php\n\nreturn ['title' => 'Hola'];",
            'auth.php' => "<?php\n\nreturn ['email' => 'Correo'];",
        ],
    ])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--locale' => 'es',
        '--format' => 'php',
        '--lang-path' => $dir,
    ])->assertExitCode(0);

    expect(file_exists($dir.'/es/app.php'))->toBeTrue()
        ->and(file_exists($dir.'/es/auth.php'))->toBeTrue();

    removeTempDir($dir);
});

it('calls export with correct format and locale', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response(['files' => []])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--locale' => 'es',
        '--format' => 'json',
        '--lang-path' => $dir,
    ])->assertExitCode(0);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'format=json') &&
        str_contains($r->url(), 'locale=es')
    );

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// All locales (files written to lang/{filename})
// ---------------------------------------------------------------------------

it('writes all-locale files to the lang root', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => [
            'es.json' => '{"Hello":"Hola"}',
            'fr.json' => '{"Hello":"Bonjour"}',
        ],
    ])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--format' => 'json',
        '--lang-path' => $dir,
    ])->assertExitCode(0);

    expect(file_exists($dir.'/es.json'))->toBeTrue()
        ->and(file_get_contents($dir.'/es.json'))->toBe('{"Hello":"Hola"}')
        ->and(file_exists($dir.'/fr.json'))->toBeTrue();

    removeTempDir($dir);
});

it('does not send locale param when pulling all locales', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response(['files' => []])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--lang-path' => $dir,
    ])->assertExitCode(0);

    Http::assertSent(fn ($r) => ! str_contains($r->url(), 'locale='));

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// Dry-run
// ---------------------------------------------------------------------------

it('does not write any files in dry-run mode', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['app.php' => "<?php\n\nreturn [];"],
    ])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--locale' => 'es',
        '--dry-run' => true,
        '--lang-path' => $dir,
    ])->assertExitCode(0);

    expect(is_dir($dir.'/es'))->toBeFalse();

    removeTempDir($dir);
});

it('outputs dry-run paths without writing', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([
        'files' => ['app.php' => "<?php\n\nreturn [];"],
    ])]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--locale' => 'es',
        '--dry-run' => true,
        '--lang-path' => $dir,
    ])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);

    removeTempDir($dir);
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

it('rejects invalid format', function () {
    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--format' => 'yaml',
    ])->assertExitCode(1);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

it('returns failure on 401', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->artisan('stringhive:pull', [
        'hive' => 'my-app',
        '--lang-path' => $dir,
    ])->assertExitCode(1);

    removeTempDir($dir);
});

it('returns failure when hive is not found', function () {
    $dir = makeTempLangDir();

    Http::fake(['*' => Http::response([], 404)]);

    $this->artisan('stringhive:pull', [
        'hive' => 'missing-hive',
        '--lang-path' => $dir,
    ])->assertExitCode(1);

    removeTempDir($dir);
});
